<?php

namespace FriendsOfRedaxo\Uppy;

use rex_addon;
use rex_config;
use rex;
use rex_login;
use rex_request;
use rex_session;
use rex_string;
use rex_url;

use function rex_escape;
use function rex_set_session;
use function session_status;

class Utils
{
    /**
     * Setzt den Uppy-API-Token in die aktuelle Browser-Session.
     * Dies ist notwendig, damit Uppy im Frontend (ohne Backend/YCom-Login)
     * Dateien hochladen darf. Der Token allein reicht seit 2.11.0 nicht:
     * Zusätzlich braucht jede Anfrage die signierte Feld-Konfiguration
     * (siehe Security::authorize()). Der Token wird nie ins HTML geschrieben.
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

        $cssPath = $addon->getPath('assets/' . $cssFile);
        $jsPath = $addon->getPath('assets/' . $jsFile);

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
     * Wurzel-relativer Pfad zum Frontend- bzw. Backend-Controller, z. B. "/unterordner/redaxo/index.php".
     *
     * rex_url liefert Pfade relativ zum aufgerufenen Skript ("index.php", "../index.php", "./redaxo/index.php").
     * Der Browser löst sie aber gegen die Seiten-URL auf, die im Frontend durch Rewrites beliebig tief sein kann
     * (/de/kontakt/). Deshalb hier gegen SCRIPT_NAME auflösen: funktioniert in Unterordnern und mit umbenanntem
     * Backend, ohne jQuery oder window.rex im Browser.
     *
     * @param array<string, scalar> $params
     */
    public static function controllerUrl(bool $backend = false, array $params = []): string
    {
        $relative = $backend ? rex_url::backendController([], false) : rex_url::frontendController([], false);

        if (preg_match('#^(/|[a-z][a-z0-9+.-]*://)#i', $relative)) {
            $path = $relative;
        } else {
            $script = str_replace('\\', '/', rex_request::server('SCRIPT_NAME', 'string', ''));
            if ('' === $script) {
                // ohne Webrequest (Konsole): Frontend-Basis aus der Server-Einstellung
                $script = rtrim((string) parse_url(rex::getServer(), PHP_URL_PATH), '/') . '/index.php';
            }
            $segments = [];
            foreach (explode('/', dirname($script) . '/' . $relative) as $segment) {
                if ('' === $segment || '.' === $segment) {
                    continue;
                }
                if ('..' === $segment) {
                    array_pop($segments);
                    continue;
                }
                $segments[] = $segment;
            }
            $path = '/' . implode('/', $segments);
        }

        $query = rex_string::buildQuery($params, '&');

        return $path . ('' !== $query ? '?' . $query : '');
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
