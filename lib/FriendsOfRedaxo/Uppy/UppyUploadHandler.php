<?php

namespace FriendsOfRedaxo\Uppy;

use DateTime;
use Exception;
use rex;
use rex_addon;
use rex_api_exception;
use rex_api_function;
use rex_backend_login;
use rex_config;
use rex_dir;
use rex_extension;
use rex_extension_point;
use rex_file;
use rex_formatter;
use rex_i18n;
use rex_logger;
use rex_media;
use rex_media_cache;
use rex_media_service;
use rex_path;
use rex_response;
use rex_sql;
use rex_sql_exception;
use rex_string;

use function in_array;
use function is_array;
use function is_resource;
use function is_string;

use const JSON_ERROR_NONE;
use const LOCK_EX;
use const LOCK_UN;
use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;
use const GLOB_ONLYDIR;
use const SORT_NUMERIC;
use const UPLOAD_ERR_FORM_SIZE;
use const UPLOAD_ERR_INI_SIZE;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_OK;

/**
 * API-Handler für Uppy-Uploads.
 *
 * Unterstützt:
 * - Standard-Upload für kleine Dateien
 * - Chunk-Upload für große Dateien
 * - Metadaten-Speicherung
 *
 * @package uppy
 */
class UppyUploadHandler extends rex_api_function
{
    protected $published = true;
    protected $chunksDir = '';
    protected $metadataDir = '';

    public function __construct()
    {
        $baseDir = rex_path::addonData('uppy', 'upload');
        $this->chunksDir = $baseDir . '/chunks';
        $this->metadataDir = $baseDir . '/metadata';

        // Verzeichnisse erstellen, falls nicht vorhanden
        if (!is_dir($this->chunksDir)) {
            rex_dir::create($this->chunksDir);
        }
        if (!is_dir($this->metadataDir)) {
            rex_dir::create($this->metadataDir);
        }

        // Zwischenspeicher für Chunks/Metadaten zusätzlich gegen Web-Zugriff sperren
        // (redaxo/data ist bereits durch die REDAXO-.htaccess geschützt; bei nginx siehe README)
        if (!is_file($baseDir . '/.htaccess')) {
            rex_file::put($baseDir . '/.htaccess', "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n");
        }
    }

    /**
     * Zentrale Response-Methode.
     */
    protected function sendResponse($data, $statusCode = 200): void
    {
        rex_response::cleanOutputBuffers();
        // Status IMMER setzen – im Frontend kann YRewrite/Structure bereits
        // einen 404-Status gesetzt haben, der sonst bestehen bleibt.
        rex_response::setStatus($statusCode);
        rex_response::sendJson($data);
        exit;
    }

    /**
     * Ergebnis der Rechteprüfung und wirksame Upload-Konfiguration des aktuellen Aufrufs.
     *
     * @var array{category_id: int, allowed_types: string, max_bytes: int, upload_dir: string, enforce_types: bool}
     */
    protected array $uploadConfig = [
        'category_id' => 0,
        'allowed_types' => '',
        'max_bytes' => 0,
        'upload_dir' => '',
        'enforce_types' => true,
    ];

    protected bool $isBackendRequest = false;

    public function execute()
    {
        try {
            // Authentifizierung prüfen (Backend-User oder gültige, an die Session gebundene Signatur)
            $auth = Security::authorize();
            $this->isBackendRequest = $auth['backend'];
            $this->uploadConfig = Security::resolveUploadConfig($auth);

            $func = rex_request('func', 'string', '');
            $categoryId = $this->uploadConfig['category_id'];

            switch ($func) {
                case 'prepare':
                    $result = $this->handlePrepare();
                    $this->sendResponse($result);

                    // no break
                case 'upload':
                    $result = $this->handleUpload($categoryId);
                    $this->sendResponse($result);

                    // no break
                case 'chunk':
                    $result = $this->handleChunkUpload($categoryId);
                    $this->sendResponse($result);

                    // no break
                case 'finalize':
                    $result = $this->handleFinalizeUpload($categoryId);
                    $this->sendResponse($result);

                    // no break
                default:
                    throw new rex_api_exception('Invalid function');
            }
        } catch (rex_api_exception $e) {
            // API Exceptions sind "erwartete" Fehler (z.B. Validierung) -> 400 Bad Request
            rex_logger::factory()->log('warning', 'Uppy API Exception: ' . $e->getMessage());
            $status = 'Unauthorized access' === $e->getMessage() ? rex_response::HTTP_FORBIDDEN : rex_response::HTTP_BAD_REQUEST;
            $this->sendResponse(['error' => $e->getMessage()], $status);
        } catch (Exception $e) {
            // Unerwartete Fehler -> 500 Internal Server Error (ohne interne Details)
            rex_logger::logException($e);
            $this->sendResponse(['error' => 'Upload failed'], rex_response::HTTP_INTERNAL_ERROR);
        }
    }

    /**
     * Prüft die Authentifizierung.
     *
     * @deprecated seit 2.11.0, die Prüfung liegt zentral in Security::authorize()
     */
    protected function isAuthorized(): bool
    {
        try {
            Security::authorize();
            return true;
        } catch (rex_api_exception) {
            return false;
        }
    }

    /**
     * Räumt verwaiste Chunk-Ordner, Metadaten und Temp-Dateien auf (älter als 24 Stunden).
     * Läuft höchstens alle 15 Minuten.
     */
    protected function cleanupStaleUploads(): void
    {
        $marker = rex_path::addonData('uppy', 'upload/.last_cleanup');
        if (is_file($marker) && filemtime($marker) > time() - 900) {
            return;
        }
        @touch($marker);

        $limit = time() - 86400;
        foreach (glob($this->chunksDir . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (filemtime($dir) < $limit) {
                $this->cleanupChunks($dir);
            }
        }
        foreach (glob($this->metadataDir . '/*.json') ?: [] as $file) {
            if (filemtime($file) < $limit) {
                @unlink($file);
            }
        }
        foreach (glob(rex_path::addonData('uppy', 'upload/') . '*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $limit) {
                @unlink($file);
            }
        }
    }

    /**
     * Maximale Größe einer einzelnen Chunk-Anfrage in Bytes (effektive Chunk-Größe + Puffer).
     */
    protected function getMaxChunkBytes(): int
    {
        return self::clientChunkBytes() + 128 * 1024;
    }

    /**
     * Effektive Chunk-Größe in Bytes: der kleinere Wert aus der Einstellung
     * (uppy/chunk_size in MB) und dem PHP-Upload-Limit, abzüglich eines Puffers
     * für den Multipart-Overhead. Client (boot.php -> JS) und Server leiten die
     * Chunk-Größe aus genau dieser Methode ab, sonst sendet der Client Chunks,
     * die PHP verwirft (upload_max_filesize) oder die Anzahl-Prüfung ablehnt.
     */
    public static function clientChunkBytes(): int
    {
        $configBytes = max(1, (int) rex_config::get('uppy', 'chunk_size', 5)) * 1024 * 1024;
        $limit = self::phpUploadLimitBytes();
        if ($limit > 0) {
            return min($configBytes, max(256 * 1024, $limit - 128 * 1024));
        }

        return $configBytes;
    }

    /**
     * Kleinstes relevantes PHP-Upload-Limit (upload_max_filesize / post_max_size) in Bytes.
     * 0 = unbekannt / unbegrenzt.
     */
    public static function phpUploadLimitBytes(): int
    {
        $values = array_filter([
            self::iniBytes((string) ini_get('upload_max_filesize')),
            self::iniBytes((string) ini_get('post_max_size')),
        ], static fn (int $v): bool => $v > 0);

        return $values ? min($values) : 0;
    }

    /**
     * Wandelt einen ini-Größenwert (z.B. "2M", "8M", "1G") in Bytes um.
     */
    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ('' === $value) {
            return 0;
        }
        $number = (int) $value;
        return match (strtolower($value[strlen($value) - 1])) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /**
     * Vorbereitung: Metadaten speichern.
     */
    protected function handlePrepare(): array
    {
        $fileId = rex_request('fileId', 'string', '');
        // WICHTIG: metadata als String lesen und dann als JSON parsen
        $metadataJson = rex_request('metadata', 'string', '{}');
        $metadata = json_decode($metadataJson, true) ?? [];

        if (empty($fileId)) {
            throw new rex_api_exception('Missing fileId');
        }
        Security::assertValidFileId($fileId);
        if (!is_array($metadata)) {
            $metadata = [];
        }

        $this->cleanupStaleUploads();

        // Debug-Logging
        if (rex::isDebugMode() && rex_config::get('uppy', 'enable_debug_logging', false)) {
            rex_logger::factory()->log('debug', 'UPPY PREPARE: fileId=' . $fileId);
            rex_logger::factory()->log('debug', 'UPPY PREPARE: metadata JSON=' . $metadataJson);
            rex_logger::factory()->log('debug', 'UPPY PREPARE: metadata parsed=' . print_r($metadata, true));
        }

        $metaFile = $this->metadataDir . '/' . Security::storageId($fileId) . '.json';
        $metaData = [
            'metadata' => $metadata,
            'timestamp' => time(),
        ];
        rex_file::put($metaFile, json_encode($metaData));

        // Verifizieren dass Datei geschrieben wurde
        if (rex::isDebugMode() && rex_config::get('uppy', 'enable_debug_logging', false)) {
            if (file_exists($metaFile)) {
                rex_logger::factory()->log('debug', 'UPPY PREPARE: Metadata file saved: ' . $metaFile);
            } else {
                rex_logger::factory()->log('error', 'UPPY PREPARE: Failed to save metadata file: ' . $metaFile);
            }
        }

        return ['status' => 'prepared', 'fileId' => $fileId];
    }

    /**
     * Standard-Upload für kleine Dateien.
     */
    protected function handleUpload(int $categoryId): array
    {
        if (rex::isDebugMode() && rex_config::get('uppy', 'enable_debug_logging', false)) {
            rex_logger::factory()->log('debug', 'handleUpload called. FILES: ' . print_r($_FILES, true));
        }

        if (!isset($_FILES['file'])) {
            rex_logger::factory()->log('error', 'No file uploaded in handleUpload');
            throw new rex_api_exception('No file uploaded');
        }

        $file = $_FILES['file'];
        $fileId = rex_request('fileId', 'string', '');

        // Debug-Logging
        if (rex::isDebugMode() && rex_config::get('uppy', 'enable_debug_logging', false)) {
            rex_logger::factory()->log('debug', 'UPPY UPLOAD START: fileId=' . $fileId);
        }

        // Metadaten aus prepare-Schritt laden (wie bei FilePond)
        $metadata = [];
        if (!empty($fileId)) {
            $metadata = $this->loadMetadata($fileId);

            // Debug: Was wurde geladen?
            if (rex::isDebugMode() && rex_config::get('uppy', 'enable_debug_logging', false)) {
                rex_logger::factory()->log('debug', 'UPPY UPLOAD: Loaded metadata from fileId ' . $fileId . ': ' . print_r($metadata, true));
            }

            // Metadaten-Datei nach dem Laden löschen
            $this->deleteMetadata($fileId);
        } else {
            if (rex::isDebugMode() && rex_config::get('uppy', 'enable_debug_logging', false)) {
                rex_logger::factory()->log('warning', 'UPPY UPLOAD: No fileId provided!');
            }
        }

        // Zusätzlich: Metadaten aus POST-Parametern sammeln (Fallback/Überschreibung)
        // Dies ermöglicht es, dass das Dashboard-Modal Werte überschreiben kann

        // Kategorie kommt ausschließlich aus Security::resolveUploadConfig()
        // (Signatur bzw. bei Backend-Usern ohne Signatur aus dem Request inkl. Rechteprüfung).

        // Log zur Fehlersuche
        if (rex::isDebugMode() && rex_config::get('uppy', 'enable_debug_logging', false)) {
            rex_logger::factory()->log('debug', 'Uppy Upload - category_id: ' . $categoryId . ', fileId: ' . $fileId);
            rex_logger::factory()->log('debug', 'Final metadata before processing: ' . print_r($metadata, true));
        }

        // Standard-Felder aus POST (überschreiben prepare-Metadaten falls vorhanden) aus POST (überschreiben prepare-Metadaten falls vorhanden)
        if ($title = rex_request('title', 'string', '')) {
            $metadata['title'] = $title;
        }

        // MetaInfo-Felder (med_*) aus POST
        // Mehrsprachige Felder kommen als JSON-String vom Frontend
        foreach ($_POST as $key => $value) {
            if (str_starts_with($key, 'med_')) {
                $metadata[$key] = $value;
            }
        }

        // Datei verarbeiten
        $replaceFileId = rex_request('replace_file_id', 'int', 0);
        $filename = $this->processUploadedFile($file, $categoryId, $metadata);

        return [
            'success' => true,
            'data' => [
                'filename' => $filename,
                'replaced' => $replaceFileId > 0,
            ],
        ];
    }

    /**
     * Chunk-Upload Handler
     * Orientiert an filepond's chunk-upload.
     */
    protected function handleChunkUpload(int $categoryId): array
    {
        if (!isset($_FILES['file'])) {
            throw new rex_api_exception('No chunk uploaded');
        }

        $file = $_FILES['file'];
        $fileId = rex_request('fileId', 'string', '');
        $chunkIndex = rex_request('chunkIndex', 'int', 0);
        $totalChunks = rex_request('totalChunks', 'int', 1);

        if (empty($fileId)) {
            throw new rex_api_exception('Missing fileId');
        }
        Security::assertValidFileId($fileId);

        $uploadError = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        if (UPLOAD_ERR_INI_SIZE === $uploadError || UPLOAD_ERR_FORM_SIZE === $uploadError) {
            throw new rex_api_exception('Chunk too large for the server upload limit (upload_max_filesize)');
        }
        if (UPLOAD_ERR_OK !== $uploadError || !is_uploaded_file((string) $file['tmp_name'])) {
            throw new rex_api_exception('Invalid chunk upload');
        }

        // Anzahl und Größe der Chunks begrenzen
        $maxBytes = $this->uploadConfig['max_bytes'];
        $maxChunkBytes = $this->getMaxChunkBytes();
        $maxChunks = max(1, min(10000, (int) ceil($maxBytes / max(1, self::clientChunkBytes())) + 2));
        if ($totalChunks < 1 || $totalChunks > $maxChunks || $chunkIndex < 0 || $chunkIndex >= $totalChunks) {
            throw new rex_api_exception('Invalid chunk parameters');
        }
        $chunkBytes = (int) filesize((string) $file['tmp_name']);
        if ($chunkBytes > $maxChunkBytes) {
            throw new rex_api_exception('Chunk too large');
        }

        $this->cleanupStaleUploads();

        // Chunk-Verzeichnis für diese Datei (Name an die Session gebunden)
        $fileChunkDir = $this->chunksDir . '/' . Security::storageId($fileId);
        if (!is_dir($fileChunkDir)) {
            rex_dir::create($fileChunkDir);
        }

        // Lock-Mechanismus
        $lockFile = $fileChunkDir . '/.lock';
        $lock = fopen($lockFile, 'w+');

        if (!flock($lock, LOCK_EX)) {
            fclose($lock);
            throw new rex_api_exception('Could not acquire lock');
        }

        try {
            // Gesamtgröße aller bisherigen Chunks (ohne den aktuellen Index) prüfen
            clearstatcache();
            $existingBytes = 0;
            foreach (scandir($fileChunkDir) as $f) {
                if ('.' !== $f && '..' !== $f && '.lock' !== $f && (string) $chunkIndex !== $f && is_file($fileChunkDir . '/' . $f)) {
                    $existingBytes += (int) filesize($fileChunkDir . '/' . $f);
                }
            }
            if ($existingBytes + $chunkBytes > $maxBytes) {
                flock($lock, LOCK_UN);
                fclose($lock);
                $this->cleanupChunks($fileChunkDir);
                throw new rex_api_exception('File too large (Max: ' . rex_formatter::bytes($maxBytes) . ')');
            }

            // Chunk speichern
            $chunkPath = $fileChunkDir . '/' . $chunkIndex;
            if (!move_uploaded_file($file['tmp_name'], $chunkPath)) {
                throw new rex_api_exception("Failed to save chunk $chunkIndex");
            }

            // Prüfen ob alle Chunks da sind
            clearstatcache();
            $uploadedChunks = 0;
            foreach (scandir($fileChunkDir) as $f) {
                if ('.' !== $f && '..' !== $f && '.lock' !== $f && is_file($fileChunkDir . '/' . $f)) {
                    ++$uploadedChunks;
                }
            }

            flock($lock, LOCK_UN);
            fclose($lock);
            @unlink($lockFile);

            return [
                'status' => 'chunk-success',
                'chunkIndex' => $chunkIndex,
                'totalChunks' => $totalChunks,
                'uploadedChunks' => $uploadedChunks,
            ];
        } catch (Exception $e) {
            if (isset($lock) && is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
            @unlink($lockFile);
            throw $e;
        }
    }

    /**
     * Finalisierung des Chunk-Uploads
     * Fügt Chunks zusammen und fügt Datei zum Mediapool hinzu.
     */
    protected function handleFinalizeUpload(int $categoryId): array
    {
        $fileId = rex_request('fileId', 'string', '');
        $fileName = Security::cleanClientFilename(rex_request('fileName', 'string', ''));
        $totalChunks = rex_request('totalChunks', 'int', 0);

        if (empty($fileId) || '' === $fileName) {
            throw new rex_api_exception('Missing fileId or fileName');
        }
        Security::assertValidFileId($fileId);
        if ($totalChunks < 1 || $totalChunks > 10000) {
            throw new rex_api_exception('Invalid chunk parameters');
        }
        $storageId = Security::storageId($fileId);

        // Debug-Logging
        if (rex::isDebugMode() && rex_config::get('uppy', 'enable_debug_logging', false)) {
            rex_logger::factory()->log('debug', 'UPPY FINALIZE: fileId=' . $fileId . ', fileName=' . $fileName);
        }

        $fileChunkDir = $this->chunksDir . '/' . $storageId;

        if (!is_dir($fileChunkDir)) {
            throw new rex_api_exception('Chunk directory not found');
        }

        // Dateiname vor dem Zusammenfügen prüfen (Sperrliste)
        try {
            Security::assertFilenameAllowed($fileName, '' !== $this->uploadConfig['upload_dir']);
        } catch (rex_api_exception $e) {
            $this->cleanupChunks($fileChunkDir);
            $this->deleteMetadata($fileId);
            throw $e;
        }

        // Chunks zusammenführen
        $tmpFile = rex_path::addonData('uppy', 'upload/') . $storageId;
        $out = fopen($tmpFile, 'w');

        if (!$out) {
            throw new rex_api_exception('Could not create output file');
        }

        // Dateisystem-Cache leeren
        clearstatcache();

        // Prüfen ob alle Chunks vorhanden sind
        $actualChunks = 0;
        $chunkFiles = [];
        foreach (scandir($fileChunkDir) as $f) {
            if ('.' !== $f && '..' !== $f && '.lock' !== $f && is_file($fileChunkDir . '/' . $f)) {
                ++$actualChunks;
                $chunkFiles[] = $f;
            }
        }

        if ($actualChunks < $totalChunks) {
            fclose($out);
            $this->cleanupChunks($fileChunkDir);
            throw new rex_api_exception("Expected $totalChunks chunks, but found only $actualChunks");
        }

        // Chunks sortieren und zusammenfügen
        sort($chunkFiles, SORT_NUMERIC);

        for ($i = 0; $i < $totalChunks; ++$i) {
            $chunkPath = $fileChunkDir . '/' . $i;
            if (!file_exists($chunkPath)) {
                fclose($out);
                $this->cleanupChunks($fileChunkDir);
                throw new rex_api_exception("Chunk $i is missing");
            }

            $in = fopen($chunkPath, 'r');
            if (!$in) {
                fclose($out);
                $this->cleanupChunks($fileChunkDir);
                throw new rex_api_exception("Could not open chunk $i");
            }

            stream_copy_to_stream($in, $out);
            fclose($in);
        }

        fclose($out);
        clearstatcache();

        if ((int) filesize($tmpFile) > $this->uploadConfig['max_bytes']) {
            $this->cleanupChunks($fileChunkDir);
            rex_file::delete($tmpFile);
            throw new rex_api_exception('File too large (Max: ' . rex_formatter::bytes($this->uploadConfig['max_bytes']) . ')');
        }

        // Metadaten laden
        $metadata = $this->loadMetadata($fileId);

        // Debug-Logging
        if (rex::isDebugMode() && rex_config::get('uppy', 'enable_debug_logging', false)) {
            rex_logger::factory()->log('debug', 'UPPY FINALIZE: Loaded metadata: ' . print_r($metadata, true));
        }

        // Datei zum Mediapool hinzufügen
        // MIME-Type-Erkennung: $tmpFile hat keine Dateiendung (Pfad = addonData/upload/{fileId}),
        // daher greift rex_file::mimeType()'s eigener Extension-Fallback nicht (z.B. CSS wird
        // von mime_content_type() nur als "text/plain" erkannt). Fallback hier anhand des
        // echten Dateinamens nachholen, sonst schlägt die Dateityp-Prüfung fälschlich fehl.
        $mimeType = rex_file::mimeType($tmpFile);
        if ('text/plain' === $mimeType) {
            $mimeType = match (strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) {
                'css' => 'text/css',
                'js' => 'application/javascript',
                'svg' => 'image/svg+xml',
                'vtt' => 'text/vtt',
                default => $mimeType,
            };
        }

        $file = [
            'name' => $fileName,
            'tmp_name' => $tmpFile,
            'type' => $mimeType,
            'size' => filesize($tmpFile),
            'error' => 0,
        ];

        $replaceFileId = rex_request('replace_file_id', 'int', 0);
        try {
            $filename = $this->processUploadedFile($file, $categoryId, $metadata);
        } finally {
            // Aufräumen (auch bei abgelehnten Dateien)
            $this->cleanupChunks($fileChunkDir);
            $this->deleteMetadata($fileId);
            rex_file::delete($tmpFile);
        }

        return [
            'success' => true,
            'data' => [
                'filename' => $filename,
                'replaced' => $replaceFileId > 0,
            ],
        ];
    }

    /**
     * Räumt Chunk-Verzeichnis auf.
     */
    protected function cleanupChunks(string $directory): void
    {
        if (is_dir($directory)) {
            $files = array_merge(glob($directory . '/*') ?: [], glob($directory . '/.lock') ?: []);
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($directory);
        }
    }

    /**
     * Verarbeitet eine hochgeladene Datei und fügt sie zum Mediapool hinzu.
     */
    protected function processUploadedFile(array $file, int $categoryId, array $metadata): string
    {
        // Konfiguration stammt ausschließlich aus Security::resolveUploadConfig()
        // (Signatur bzw. Backend-Kontext) – freie Request-Parameter werden nicht ausgewertet.
        $uploadDir = $this->uploadConfig['upload_dir'];
        $isCustomFolder = '' !== $uploadDir;

        $file['name'] = Security::cleanClientFilename((string) ($file['name'] ?? ''));
        if (UPLOAD_ERR_OK !== (int) ($file['error'] ?? UPLOAD_ERR_OK) || !is_file((string) $file['tmp_name'])) {
            throw new rex_api_exception('Upload failed');
        }

        // 1. Sperrliste (alle Upload-Wege)
        Security::assertFilenameAllowed($file['name'], $isCustomFolder);

        // 2. Dateigröße (signierter Wert bzw. globale Einstellung)
        $file['size'] = (int) filesize((string) $file['tmp_name']);
        if ($file['size'] > $this->uploadConfig['max_bytes']) {
            throw new rex_api_exception('File too large (Max: ' . rex_formatter::bytes($this->uploadConfig['max_bytes']) . ')');
        }

        // 3. Echter MIME-Type (finfo) gegen erlaubte Typen, aktive Inhalte (SVG) prüfen
        $file['type'] = Security::assertContentAllowed(
            (string) $file['tmp_name'],
            $file['name'],
            $this->uploadConfig['allowed_types'],
            $this->uploadConfig['enforce_types'],
            $isCustomFolder,
        );

        // Temp-Datei braucht eine korrekte Dateiendung: REDAXO-Core
        // (rex_mediapool::isAllowedMimeType, rex_media_service::addMedia) bestimmt den
        // MIME-Type über rex_file::mimeType() am physischen Pfad. Sowohl die reassemblierte
        // Chunk-Upload-Datei als auch PHP's eigene $_FILES-Tempdatei haben keine Endung,
        // wodurch generische Text-Typen (CSS/JS/SVG/VTT) nur als text/plain erkannt und
        // von der Mediapool-Prüfung fälschlich abgelehnt werden.
        $tmpFileWithExt = null;
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if ('' !== $ext && !str_ends_with($file['tmp_name'], '.' . $ext) && is_file($file['tmp_name'])) {
            $candidate = $file['tmp_name'] . '.' . $ext;
            if (copy($file['tmp_name'], $candidate)) {
                $file['tmp_name'] = $candidate;
                $tmpFileWithExt = $candidate;
            }
        }

        try {
            $replaceFileId = rex_request('replace_file_id', 'int', 0);
            if ($replaceFileId > 0) {
                return $this->replaceExistingMediaFile($replaceFileId, $file, $metadata);
            }

            // Upload in einen bestimmten Ordner (nicht Mediapool) – nur über signierte Konfiguration
            if ($isCustomFolder) {
                return $this->handleCustomFolderUpload($file, $uploadDir);
            }

            // Datei zum Mediapool hinzufügen
            // rex_media_service::addMedia erwartet diese Struktur:
            $data = [
                'title' => $metadata['title'] ?? pathinfo($file['name'], PATHINFO_FILENAME),
                'category_id' => $categoryId,
                'file' => [
                    'name' => $file['name'],
                    'path' => $file['tmp_name'], // wird als Fallback für tmp_name verwendet
                    'tmp_name' => $file['tmp_name'],
                    'error' => $file['error'] ?? 0,
                ],
            ];

            $return = rex_media_service::addMedia($data, true);

            if (!is_array($return) || !isset($return['filename'])) {
                $error = is_array($return) && isset($return['message']) ? $return['message'] : 'Upload failed';

                // Log detailed error for debugging
                rex_logger::factory()->log('error', 'UPPY_UPLOAD_ERROR: Upload failed for file: ' . $file['name'] . '. Error: ' . $error, [], __FILE__, __LINE__);

                throw new rex_api_exception($error);
            }

            $savedFilename = $return['filename'];

            // Datei in der Session merken (Metadaten-Bearbeitung und E-Mail-Anhänge im Frontend)
            Security::rememberUploadedFile($savedFilename);

            // Metadaten speichern
            if (!empty($metadata)) {
                $this->saveMediaMetadata($savedFilename, $metadata);
            }

            // YCom Media Auth Defaults aus Backend-Session anwenden
            $this->applyYcomMediaAuthDefaults($savedFilename);

            // Extension Point: UPPY_UPLOAD_COMPLETE
            // Ermöglicht das Eingreifen nach erfolgreichem Upload
            $savedFilename = rex_extension::registerPoint(new rex_extension_point('UPPY_UPLOAD_COMPLETE', $savedFilename, [
                'original_file' => $file,
                'category_id' => $categoryId,
                'metadata' => $metadata,
                'upload_dir' => $uploadDir,
                'is_custom_folder' => !empty($uploadDir)
            ]));

            return $savedFilename;
        } finally {
            if (null !== $tmpFileWithExt) {
                rex_file::delete($tmpFileWithExt);
            }
        }
    }

    /**
     * Ersetzt eine bestehende Mediapool-Datei (Dateiinhalt) via rex_media_service::updateMedia.
     */
    protected function replaceExistingMediaFile(int $fileId, array $file, array $metadata): string
    {
        $media = rex_media::forId($fileId);
        if (!$media) {
            throw new rex_api_exception(rex_i18n::msg('pool_file_not_found'));
        }

        $user = rex::getUser();
        if (!$user || !$user->getComplexPerm('media')->hasCategoryPerm($media->getCategoryId())) {
            throw new rex_api_exception(rex_i18n::msg('no_permission'));
        }

        $data = [
            'category_id' => $media->getCategoryId(),
            'title' => $metadata['title'] ?? $media->getTitle(),
            'file' => [
                'name' => $file['name'],
                'tmp_name' => $file['tmp_name'],
                'error' => $file['error'] ?? 0,
            ],
        ];

        $result = rex_media_service::updateMedia($media->getFileName(), $data);
        if (!is_array($result) || !isset($result['filename'])) {
            throw new rex_api_exception('Replace media failed');
        }

        $savedFilename = (string) $result['filename'];

        if ([] !== $metadata) {
            $this->saveMediaMetadata($savedFilename, $metadata);
        }

        return $savedFilename;
    }

    /**
     * Verarbeitet den Upload in einen benutzerdefinierten Ordner.
     */
    protected function handleCustomFolderUpload(array $file, string $uploadDir): string
    {
        $targetDir = $this->resolveCustomUploadDirectory($uploadDir);

        // Dateiname wie im Mediapool normalisieren (Kleinbuchstaben, nur a-z, 0-9, Punkt, Binde- und Unterstrich)
        $info = pathinfo((string) $file['name']);
        $baseName = rex_string::normalize((string) ($info['filename'] ?? ''), '_', '-.');
        $extension = rex_string::normalize((string) ($info['extension'] ?? ''), '_', '');
        $baseName = ltrim($baseName, '.-');
        if ('' === $baseName) {
            $baseName = 'file';
        }
        $filename = $baseName . '.' . $extension;
        Security::assertFilenameAllowed($filename, true);

        // Kollisionsprüfung: Vorhandene Dateien nicht überschreiben
        $filename = $this->getNonConflictingFilename($targetDir, $filename);

        $targetPath = $targetDir . '/' . $filename;

        // Wenn es ein Chunk-Upload war, liegt die Datei bereits als reguläre Datei in $file['tmp_name']
        // Wenn es ein Standard-Upload war, muss move_uploaded_file verwendet werden
        $success = false;
        if (is_uploaded_file($file['tmp_name'])) {
            $success = move_uploaded_file($file['tmp_name'], $targetPath);
        } else {
            $success = rex_file::copy($file['tmp_name'], $targetPath);
        }

        if (!$success) {
            throw new rex_api_exception('Failed to save file to custom directory');
        }
        @chmod($targetPath, 0644);

        // In der Session merken (z.B. für die YForm-Aktion uppy2email)
        $realTarget = realpath($targetPath);
        if (false !== $realTarget) {
            Security::rememberUploadedFile($realTarget);
        }

        return $filename;
    }

    /**
     * Löst das Custom-Upload-Verzeichnis auf und erzwingt dabei:
     * - Lage unterhalb von rex_path::base() oder eines in den Einstellungen freigegebenen Ordners
     * - keine REDAXO-Systemordner (redaxo/, assets/, media/, vendor/ …)
     * - Schutz-.htaccess und index.html beim Anlegen
     *
     * @throws rex_api_exception
     */
    protected function resolveCustomUploadDirectory(string $uploadDir): string
    {
        return Security::resolveCustomDirectory($uploadDir, true);
    }

    /**
     * Generiert einen Dateinamen, der im Zielordner noch nicht existiert.
     */
    protected function getNonConflictingFilename(string $dir, string $filename): string
    {
        if (!file_exists($dir . '/' . $filename)) {
            return $filename;
        }

        $info = pathinfo($filename);
        $name = $info['filename'];
        $ext = isset($info['extension']) ? '.' . $info['extension'] : '';

        $i = 1;
        while (file_exists($dir . '/' . $name . '_' . $i . $ext)) {
            ++$i;
        }

        return $name . '_' . $i . $ext;
    }

    /**
     * Speichert Metadaten für eine Mediendatei.
     */
    protected function saveMediaMetadata(string $filename, array $metadata): void
    {
        // Seit REDAXO 5.21.5 merkt sich der Instance-Pool auch „nicht gefunden“: rex_media_service::addMedia()
        // fragt beim Suchen eines freien Namens rex_media::get() ab und hinterlässt dabei null im Pool (Issue #51).
        rex_media::clearInstance($filename);
        $media = rex_media::get($filename);
        if (!$media) {
            return;
        }

        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('media'));
        $sql->setWhere(['filename' => $filename]);

        // Standard-Felder
        if (isset($metadata['title'])) {
            $sql->setValue('title', $metadata['title']);
        }

        // Hole Feldtypen aus Metainfo, um Date/DateTime/Time richtig zu behandeln
        $fieldTypes = $this->getMetaFieldTypes();

        // MetaInfo-Felder
        $availableFields = $this->getAvailableMetaFields();

        foreach ($metadata as $key => $value) {
            if (in_array($key, $availableFields)) {
                $fieldType = $fieldTypes[$key] ?? null;

                // Date/DateTime/Time Felder: HTML5 Input Werte in Unix Timestamp konvertieren
                if ('date' === $fieldType || 'datetime' === $fieldType || 'time' === $fieldType) {
                    $timestamp = $this->convertToTimestamp($value, $fieldType);
                    $sql->setValue($key, $timestamp);
                }
                // Prüfen ob der Wert ein JSON-String ist (mehrsprachige Felder)
                elseif (is_string($value) && $this->isJson($value)) {
                    // Bereits als JSON - direkt speichern
                    $sql->setValue($key, $value);
                } elseif (is_array($value)) {
                    // Array - als JSON speichern
                    $sql->setValue($key, json_encode($value));
                } else {
                    // Normaler String-Wert
                    $sql->setValue($key, $value);
                }
            }
        }

        try {
            $sql->update();
            rex_media_cache::delete($filename);
        } catch (rex_sql_exception $e) {
            rex_logger::logException($e);
        }
    }

    /**
     * Wendet die in der Backend-Session hinterlegten YCom-Media-Auth-Defaults
     * auf die soeben hochgeladene Datei an. Voraussetzung: ycom/media_auth
     * Plugin verfügbar und der eingeloggte Backend-User besitzt die Permission.
     */
    protected function applyYcomMediaAuthDefaults(string $filename): void
    {
        if (!YcomAuthSettings::isEnabled()) {
            return;
        }
        if (!rex_backend_login::hasSession()) {
            return;
        }
        if (!YcomAuthSettings::userMayManage(rex::getUser())) {
            return;
        }

        $defaults = YcomAuthSettings::getSessionDefaults();

        try {
            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('media'));
            $sql->setWhere(['filename' => $filename]);
            $sql->setValue('ycom_auth_type', $defaults['ycom_auth_type']);

            if (YcomAuthSettings::isGroupSupportAvailable()) {
                $sql->setValue('ycom_group_type', $defaults['ycom_group_type']);
                $sql->setValue('ycom_groups', implode(',', $defaults['ycom_groups']));
            }

            $sql->update();
            rex_media_cache::delete($filename);
        } catch (rex_sql_exception $e) {
            rex_logger::logException($e);
        }
    }

    /**
     * Konvertiert HTML5 Date/DateTime/Time Input Werte zu Unix Timestamp.
     *
     * @param string $value Der Input-Wert (z.B. "2024-01-15", "2024-01-15T14:30", "14:30")
     * @param string $type Der Feldtyp ('date', 'datetime', 'time')
     * @return int Unix Timestamp
     */
    protected function convertToTimestamp(string $value, string $type): int
    {
        if (empty($value)) {
            return 0;
        }

        try {
            switch ($type) {
                case 'date':
                    // Format: "2024-01-15"
                    $date = new DateTime($value);
                    $date->setTime(0, 0, 0);
                    return $date->getTimestamp();

                case 'datetime':
                    // Format: "2024-01-15T14:30"
                    $date = new DateTime($value);
                    return $date->getTimestamp();

                case 'time':
                    // Format: "14:30"
                    // Time ohne Datum - nutze Unix Epoche (1970-01-01)
                    $parts = explode(':', $value);
                    $hour = (int) ($parts[0] ?? 0);
                    $minute = (int) ($parts[1] ?? 0);
                    return mktime($hour, $minute, 0, 0, 0, 0);

                default:
                    return 0;
            }
        } catch (Exception $e) {
            rex_logger::factory()->log('warning', 'Date conversion failed for value: ' . $value . ', type: ' . $type);
            return 0;
        }
    }

    /**
     * Hole Feldtypen aller MetaInfo-Felder.
     *
     * @return array Assoc-Array: field_name => type_label
     */
    protected function getMetaFieldTypes(): array
    {
        static $cache = null;

        if (null !== $cache) {
            return $cache;
        }

        $cache = [];

        if (!rex_addon::get('metainfo')->isAvailable()) {
            return $cache;
        }

        try {
            $sql = rex_sql::factory();
            $sql->setQuery(
                'SELECT f.name, t.label FROM ' . rex::getTable('metainfo_field') . ' f ' .
                'LEFT JOIN ' . rex::getTable('metainfo_type') . ' t ON f.type_id = t.id ' .
                'WHERE f.name LIKE "med_%"',
            );

            while ($sql->hasNext()) {
                $name = $sql->getValue('name');
                $label = $sql->getValue('label');

                // Normalisiere type labels
                $type = match ($label) {
                    'date' => 'date',
                    'datetime' => 'datetime',
                    'time' => 'time',
                    'lang_date' => 'date',
                    'lang_datetime' => 'datetime',
                    'lang_time' => 'time',
                    default => $label,
                };

                $cache[$name] = $type;
                $sql->next();
            }
        } catch (rex_sql_exception $e) {
            rex_logger::logException($e);
        }

        return $cache;
    }

    /**
     * Prüft ob ein String ein valides JSON ist.
     */
    protected function isJson(string $string): bool
    {
        json_decode($string);
        return JSON_ERROR_NONE === json_last_error();
    }

    /**
     * Lädt Metadaten aus der Vorbereitungsphase.
     */
    protected function loadMetadata(string $fileId): array
    {
        if (empty($fileId)) {
            return [];
        }

        if (1 !== preg_match(Security::FILE_ID_PATTERN, $fileId)) {
            return [];
        }

        $metaFile = $this->metadataDir . '/' . Security::storageId($fileId) . '.json';
        if (rex_file::get($metaFile)) {
            $data = json_decode(rex_file::get($metaFile), true);
            return $data['metadata'] ?? [];
        }

        return [];
    }

    /**
     * Löscht Metadaten-Datei.
     */
    protected function deleteMetadata(string $fileId): void
    {
        if (empty($fileId)) {
            return;
        }

        if (1 !== preg_match(Security::FILE_ID_PATTERN, $fileId)) {
            return;
        }

        $metaFile = $this->metadataDir . '/' . Security::storageId($fileId) . '.json';
        rex_file::delete($metaFile);
    }

    /**
     * Gibt verfügbare MetaInfo-Felder zurück.
     */
    protected function getAvailableMetaFields(): array
    {
        $fields = [];

        if (!rex_addon::get('metainfo')->isAvailable()) {
            return $fields;
        }

        try {
            $sql = rex_sql::factory();
            $sql->setQuery('SELECT name FROM ' . rex::getTable('metainfo_field') . ' WHERE name LIKE "med_%"');

            while ($sql->hasNext()) {
                $fields[] = $sql->getValue('name');
                $sql->next();
            }
        } catch (rex_sql_exception $e) {
            rex_logger::logException($e);
        }

        return $fields;
    }
}
