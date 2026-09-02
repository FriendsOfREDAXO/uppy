<?php

namespace FriendsOfRedaxo\Uppy;

use rex_addon;
use rex_config;
use rex_login;
use rex_session;

use function rex_escape;
use function rex_set_session;
use function session_status;

class Utils
{
    /**
     * Setzt den Uppy-API-Token in die aktuelle Browser-Session.
     * Dies ist notwendig, damit Uppy im Frontend (ohne Backend/YCom-Login)
     * Dateien hochladen darf. Ohne konfigurierten Token passiert nichts -
     * die serverseitige Rechteprüfung in UppyUploadHandler::isAuthorized()
     * bleibt davon unberührt.
     *
     * Am besten ganz oben im Template oder im PHP-Code des Moduls aufrufen.
     */
    public static function ensureApiSession(): void
    {
        $apiToken = rex_config::get('uppy', 'api_token');
        if (!$apiToken) {
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            rex_login::startSession();
        }

        // Nur setzen, wenn noch nicht korrekt in der Session liegt
        if (rex_session('uppy_token', 'string') !== $apiToken) {
            rex_set_session('uppy_token', $apiToken);
        }
    }

    /**
     * Gibt die <link>/<script>-Tags zur Einbindung von Uppy im Frontend zurück
     * (CSS-Bundle + das Custom-Widget-JS, das Felder mit class="uppy-upload-widget"
     * automatisch initialisiert - keine eigene Init-Logik im Template nötig).
     */
    public static function assets(): string
    {
        $addon = rex_addon::get('uppy');

        $cssFile = 'dist/uppy-frontend-bundle.css';
        $jsFile = 'dist/uppy-custom-widget-bundle.js';

        $cssPath = $addon->getPath($cssFile);
        $jsPath = $addon->getPath($jsFile);

        $cssVersion = $addon->getVersion() . '.' . (is_file($cssPath) ? filemtime($cssPath) : 0);
        $jsVersion = $addon->getVersion() . '.' . (is_file($jsPath) ? filemtime($jsPath) : 0);

        $html = '<link rel="stylesheet" href="' . rex_escape($addon->getAssetsUrl($cssFile . '?v=' . $cssVersion)) . '">' . "\n";
        $html .= '<script src="' . rex_escape($addon->getAssetsUrl($jsFile . '?v=' . $jsVersion)) . '" defer></script>' . "\n";

        return $html;
    }

    /**
     * Alles, was ein Frontend-Template für Uppy braucht, in einem Aufruf:
     * Session-Token setzen + CSS/JS-Tags. Einmal ganz oben im Template ausgeben.
     */
    public static function init(): string
    {
        self::ensureApiSession();

        return self::assets();
    }

    /**
     * Rendert ein einzelnes Uppy-Upload-Feld aus einem Options-Array - ohne
     * Pipe-Notation und ohne die data-Attribute von Hand zusammenzubauen.
     *
     * @param array<string, mixed> $options siehe Field::render()
     */
    public static function field(string $name, array $options = [], string $value = ''): string
    {
        return Field::render($name, $options, $value);
    }
}
