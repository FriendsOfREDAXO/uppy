<?php

/**
 * Installation des Uppy-AddOns
 */

// Upload-Verzeichnisse erstellen
$uploadDir = rex_path::addonData('uppy', 'upload');
$chunksDir = $uploadDir . '/chunks';
$metadataDir = $uploadDir . '/metadata';

$dirs = [$uploadDir, $chunksDir, $metadataDir];

foreach ($dirs as $dir) {
    if (!rex_dir::create($dir)) {
        rex_logger::logError('UPPY_INSTALL', 'Konnte Verzeichnis nicht erstellen: ' . $dir, [], __FILE__);
        $this->setProperty('installmsg', 'Fehler beim Erstellen der Upload-Verzeichnisse');
        return false;
    }
}

// API-Token generieren, falls noch nicht vorhanden
if (!rex_config::get('uppy', 'api_token')) {
    $token = bin2hex(random_bytes(32));
    rex_config::set('uppy', 'api_token', $token);
}
