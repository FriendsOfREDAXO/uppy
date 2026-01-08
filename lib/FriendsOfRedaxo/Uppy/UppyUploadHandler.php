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
use rex_file;
use rex_formatter;
use rex_logger;
use rex_media;
use rex_media_cache;
use rex_media_service;
use rex_path;
use rex_plugin;
use rex_response;
use rex_sql;
use rex_sql_exception;
use rex_ycom_auth;

use function in_array;
use function is_array;
use function is_resource;
use function is_string;

use const JSON_ERROR_NONE;
use const LOCK_EX;
use const LOCK_UN;
use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;
use const SORT_NUMERIC;

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
    }

    /**
     * Zentrale Response-Methode.
     */
    protected function sendResponse($data, $statusCode = 200): void
    {
        rex_response::cleanOutputBuffers();
        if (200 !== $statusCode) {
            rex_response::setStatus($statusCode);
        }
        rex_response::sendJson($data);
        exit;
    }

    public function execute()
    {
        try {
            // Authentifizierung prüfen
            if (!$this->isAuthorized()) {
                throw new rex_api_exception('Unauthorized access');
            }

            $func = rex_request('func', 'string', '');
            $categoryId = rex_request('category_id', 'int', 0);

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
                    throw new rex_api_exception('Invalid function: ' . $func);
            }
        } catch (rex_api_exception $e) {
            // API Exceptions sind "erwartete" Fehler (z.B. Validierung) -> 400 Bad Request
            rex_logger::factory()->log('warning', 'Uppy API Exception: ' . $e->getMessage());
            $this->sendResponse(['error' => $e->getMessage()], rex_response::HTTP_BAD_REQUEST);
        } catch (Exception $e) {
            // Unerwartete Fehler -> 500 Internal Server Error
            rex_logger::logException($e);
            $this->sendResponse(['error' => $e->getMessage()], rex_response::HTTP_INTERNAL_ERROR);
        }
    }

    /**
     * Prüft die Authentifizierung.
     */
    protected function isAuthorized(): bool
    {
        // Backend-User
        $user = rex_backend_login::createUser();
        if ($user) {
            return true;
        }

        // API-Token
        $apiToken = rex_config::get('uppy', 'api_token');
        $requestToken = rex_request('api_token', 'string', null);
        $sessionToken = rex_session('uppy_token', 'string', '');

        if ($apiToken && (
            ($requestToken && hash_equals($apiToken, $requestToken))
            || ($sessionToken && hash_equals($apiToken, $sessionToken))
        )) {
            return true;
        }

        // YCom-User
        if (rex_plugin::get('ycom', 'auth')->isAvailable()) {
            if (rex_ycom_auth::getUser()) {
                return true;
            }
        }

        return false;
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

        // Debug-Logging
        if (rex::isDebugMode() && rex_config::get('uppy', 'enable_debug_logging', false)) {
            rex_logger::factory()->log('debug', 'UPPY PREPARE: fileId=' . $fileId);
            rex_logger::factory()->log('debug', 'UPPY PREPARE: metadata JSON=' . $metadataJson);
            rex_logger::factory()->log('debug', 'UPPY PREPARE: metadata parsed=' . print_r($metadata, true));
        }

        $metaFile = $this->metadataDir . '/' . $fileId . '.json';
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

        // Kategorie aus POST-Parameter (hat Vorrang über URL-Parameter)
        $postCategoryId = rex_request('category_id', 'int', null);
        if (null !== $postCategoryId) {
            $categoryId = $postCategoryId;
        }

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
        $filename = $this->processUploadedFile($file, $categoryId, $metadata);

        return [
            'success' => true,
            'data' => [
                'filename' => $filename,
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

        // Chunk-Verzeichnis für diese Datei
        $fileChunkDir = $this->chunksDir . '/' . $fileId;
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
                @unlink($lockFile);
            }
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
        $fileName = rex_request('fileName', 'string', '');
        $totalChunks = rex_request('totalChunks', 'int', 0);

        if (empty($fileId) || empty($fileName)) {
            throw new rex_api_exception('Missing fileId or fileName');
        }

        // Debug-Logging
        if (rex::isDebugMode() && rex_config::get('uppy', 'enable_debug_logging', false)) {
            rex_logger::factory()->log('debug', 'UPPY FINALIZE: fileId=' . $fileId . ', fileName=' . $fileName);
        }

        $fileChunkDir = $this->chunksDir . '/' . $fileId;

        if (!is_dir($fileChunkDir)) {
            throw new rex_api_exception('Chunk directory not found');
        }

        // Chunks zusammenführen
        $tmpFile = rex_path::addonData('uppy', 'upload/') . $fileId;
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

        // Metadaten laden
        $metadata = $this->loadMetadata($fileId);

        // Debug-Logging
        if (rex::isDebugMode() && rex_config::get('uppy', 'enable_debug_logging', false)) {
            rex_logger::factory()->log('debug', 'UPPY FINALIZE: Loaded metadata: ' . print_r($metadata, true));
        }

        // Datei zum Mediapool hinzufügen
        $file = [
            'name' => $fileName,
            'tmp_name' => $tmpFile,
            'type' => rex_file::mimeType($tmpFile),
            'size' => filesize($tmpFile),
            'error' => 0,
        ];

        $filename = $this->processUploadedFile($file, $categoryId, $metadata);

        // Aufräumen
        $this->cleanupChunks($fileChunkDir);
        $this->deleteMetadata($fileId);
        rex_file::delete($tmpFile);

        return [
            'success' => true,
            'data' => [
                'filename' => $filename,
            ],
        ];
    }

    /**
     * Räumt Chunk-Verzeichnis auf.
     */
    protected function cleanupChunks(string $directory): void
    {
        if (is_dir($directory)) {
            $files = glob($directory . '/*');
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
        // 1. Signatur-Prüfung (Manipulationsschutz)
        $signature = rex_request('uppy_signature', 'string', '');

        if ($signature) {
            // Parameter sammeln, die signiert sein müssen
            $params = [
                'category_id' => $categoryId,
                'allowed_types' => rex_request('uppy_allowed_types', 'string', ''),
                'max_filesize' => rex_request('uppy_max_filesize', 'string', ''),
            ];

            // Signatur prüfen
            if (!Signature::verify($params, $signature)) {
                rex_logger::logError('UPPY_SECURITY', 'Invalid signature for upload. Params: ' . json_encode($params), [], __FILE__);
                throw new rex_api_exception('Security violation: Invalid signature');
            }

            // 2. Erweiterte Validierung basierend auf signierten Parametern

            // Dateigröße prüfen
            if (!empty($params['max_filesize'])) {
                $maxSize = (int) $params['max_filesize'];
                if ($file['size'] > $maxSize) {
                    throw new rex_api_exception('File too large (Max: ' . rex_formatter::bytes($maxSize) . ')');
                }
            }

            // Dateityp prüfen
            if (!empty($params['allowed_types'])) {
                $allowedTypes = explode(',', $params['allowed_types']);
                $fileType = $file['type'];
                $fileExt = '.' . pathinfo($file['name'], PATHINFO_EXTENSION);

                $isValid = false;
                foreach ($allowedTypes as $type) {
                    $type = trim($type);
                    // MIME-Type Match (z.B. image/* oder image/jpeg)
                    if (str_ends_with($type, '/*')) {
                        $baseType = substr($type, 0, -2);
                        if (str_starts_with($fileType, $baseType)) {
                            $isValid = true;
                            break;
                        }
                    } elseif ($type === $fileType) {
                        $isValid = true;
                        break;
                    }
                    // Extension Match (z.B. .jpg)
                    elseif (str_starts_with($type, '.') && 0 === strcasecmp($type, $fileExt)) {
                        $isValid = true;
                        break;
                    }
                }

                if (!$isValid) {
                    throw new rex_api_exception('File type not allowed');
                }
            }
        }

        // 3. Standard-Validierung (Global)
        $maxSize = rex_config::get('uppy', 'max_filesize', 200) * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            throw new rex_api_exception('File too large');
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
            rex_logger::logError('UPPY_UPLOAD_ERROR', 'Upload failed for file: ' . $file['name'] . '. Error: ' . $error, [], __FILE__);

            throw new rex_api_exception($error);
        }

        $savedFilename = $return['filename'];

        // Metadaten speichern
        if (!empty($metadata)) {
            $this->saveMediaMetadata($savedFilename, $metadata);
        }

        return $savedFilename;
    }

    /**
     * Speichert Metadaten für eine Mediendatei.
     */
    protected function saveMediaMetadata(string $filename, array $metadata): void
    {
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

        $metaFile = $this->metadataDir . '/' . $fileId . '.json';
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

        $metaFile = $this->metadataDir . '/' . $fileId . '.json';
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
