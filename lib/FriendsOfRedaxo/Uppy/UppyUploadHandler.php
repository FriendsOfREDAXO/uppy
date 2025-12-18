<?php

namespace FriendsOfRedaxo\Uppy;

use rex;
use rex_api_function;
use rex_api_exception;
use rex_response;
use rex_path;
use rex_dir;
use rex_file;
use rex_request;
use rex_media;
use rex_media_service;
use rex_sql;
use rex_config;
use rex_addon;
use rex_plugin;
use rex_media_cache;
use rex_ycom_auth;
use rex_backend_login;
use rex_logger;
use rex_sql_exception;
use Exception;

/**
 * API-Handler für Uppy-Uploads
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
    protected $metadataDir = '';

    public function __construct()
    {
        $baseDir = rex_path::addonData('uppy', 'upload');
        $this->metadataDir = $baseDir . '/metadata';
        
        // Metadata-Verzeichnis erstellen, falls nicht vorhanden
        if (!is_dir($this->metadataDir)) {
            rex_dir::create($this->metadataDir);
        }
    }

    /**
     * Zentrale Response-Methode
     */
    protected function sendResponse($data, $statusCode = 200): void
    {
        rex_response::cleanOutputBuffers();
        if ($statusCode !== 200) {
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

                case 'upload':
                    $result = $this->handleUpload($categoryId);
                    $this->sendResponse($result);

                case 'delete':
                    $result = $this->handleDelete();
                    $this->sendResponse($result);

                default:
                    throw new rex_api_exception('Invalid function: ' . $func);
            }
        } catch (Exception $e) {
            rex_logger::logException($e);
            $this->sendResponse(['error' => $e->getMessage()], rex_response::HTTP_FORBIDDEN);
        }
    }

    /**
     * Prüft die Authentifizierung
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
            ($requestToken && hash_equals($apiToken, $requestToken)) ||
            ($sessionToken && hash_equals($apiToken, $sessionToken))
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
     * Vorbereitung: Metadaten speichern
     */
    protected function handlePrepare(): array
    {
        $fileId = rex_request('fileId', 'string', '');
        $metadata = rex_request('metadata', 'array', []);

        if (empty($fileId)) {
            throw new rex_api_exception('Missing fileId');
        }

        $metaFile = $this->metadataDir . '/' . $fileId . '.json';
        rex_file::put($metaFile, json_encode([
            'metadata' => $metadata,
            'timestamp' => time()
        ]));

        return ['status' => 'prepared', 'fileId' => $fileId];
    }

    /**
     * Standard-Upload für kleine Dateien
     */
    protected function handleUpload(int $categoryId): array
    {
        if (!isset($_FILES['file'])) {
            throw new rex_api_exception('No file uploaded');
        }

        $file = $_FILES['file'];
        
        // Metadaten aus POST-Parametern sammeln (Uppy sendet sie als separate Felder)
        $metadata = [];
        $multilangData = []; // Für mehrsprachige Felder
        
        // Kategorie IMMER aus POST-Parameter nehmen (aus Modal oder allowedMetaFields)
        // POST hat Vorrang vor URL-Parameter, da im Modal änderbar
        $postCategoryId = rex_request('category_id', 'int', null);
        if ($postCategoryId !== null) {
            $categoryId = $postCategoryId;
        }
        
        // Log zur Fehlersuche
        rex_logger::factory()->log('debug', 'Uppy Upload - category_id: ' . $categoryId . ' (URL: ' . rex_request('category_id', 'int', 0) . ', POST: ' . ($postCategoryId ?? 'null') . ')');
        
        // Standard-Felder
        if ($title = rex_request('title', 'string', '')) {
            $metadata['title'] = $title;
        }
        
        // MetaInfo-Felder (med_*)
        // Mehrsprachige Felder kommen bereits als JSON-String vom Frontend
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
                'filename' => $filename
            ]
        ];
    }

    /**
     * Datei löschen
     */
    protected function handleDelete(): array
    {
        $filename = rex_request('filename', 'string', '');
        
        if (empty($filename)) {
            throw new rex_api_exception('Missing filename');
        }

        $media = rex_media::get($filename);
        if ($media) {
            if (rex_media_service::deleteMedia($filename)) {
                return [
                    'success' => true,
                    'data' => ['filename' => $filename]
                ];
            }
        }

        throw new rex_api_exception('Could not delete file');
    }

    /**
     * Verarbeitet eine hochgeladene Datei und fügt sie zum Mediapool hinzu
     */
    protected function processUploadedFile(array $file, int $categoryId, array $metadata): string
    {
        // Validierung
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
                'error' => $file['error'] ?? 0
            ]
        ];

        $return = rex_media_service::addMedia($data, true);

        if (!is_array($return) || !isset($return['filename'])) {
            $error = is_array($return) && isset($return['message']) ? $return['message'] : 'Upload failed';
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
     * Speichert Metadaten für eine Mediendatei
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

        // MetaInfo-Felder
        $availableFields = $this->getAvailableMetaFields();
        
        foreach ($metadata as $key => $value) {
            if (in_array($key, $availableFields)) {
                $sql->setValue($key, $value);
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
     * Lädt Metadaten aus der Vorbereitungsphase
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
     * Löscht Metadaten-Datei
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
     * Räumt Chunk-Verzeichnis auf
     */
    protected function cleanupChunks(string $directory): void
    {
        if (is_dir($directory)) {
            rex_dir::delete($directory, true);
        }
    }

    /**
     * Gibt verfügbare MetaInfo-Felder zurück
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
