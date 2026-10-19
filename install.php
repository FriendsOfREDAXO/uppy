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
        rex_logger::factory()->log('error', 'UPPY_INSTALL: Konnte Verzeichnis nicht erstellen: ' . $dir, [], __FILE__, __LINE__);
        $this->setProperty('installmsg', 'Fehler beim Erstellen der Upload-Verzeichnisse');
        return false;
    }
}

// API-Token generieren, falls noch nicht vorhanden
if (!rex_config::get('uppy', 'api_token')) {
    $token = bin2hex(random_bytes(32));
    rex_config::set('uppy', 'api_token', $token);
}

// Seit 2.11.0 entfernt: "Sicherheits-Checks deaktivieren" (öffentlicher Upload ohne Prüfung)
if (rex_config::has('uppy', 'auth_disable_checks')) {
    rex_config::remove('uppy', 'auth_disable_checks');
}

// Upload-Zwischenspeicher zusätzlich gegen Web-Zugriff sperren (redaxo/data ist ohnehin geschützt)
$htaccess = $uploadDir . '/.htaccess';
if (!is_file($htaccess)) {
    rex_file::put($htaccess, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n");
}
