<?php

namespace FriendsOfRedaxo\Uppy;

use rex_config;
use rex_session;

class Utils
{
    /**
     * Setzt den Uppy-API-Token in die aktuelle Browser-Session.
     * Dies ist notwendig, damit Uppy im Frontend (ohne Backend/YCom-Login)
     * Dateien hochladen darf.
     *
     * Am besten ganz oben im Template oder im PHP-Code des Moduls aufrufen.
     */
    public static function ensureApiSession(): void
    {
        $apiToken = rex_config::get('uppy', 'api_token');
        
        // Nur setzen, wenn ein Token konfiguriert ist und noch nicht korrekt in der Session liegt
        if ($apiToken && rex_session('uppy_token', 'string') !== $apiToken) {
            rex_set_session('uppy_token', $apiToken);
        }
    }
}
