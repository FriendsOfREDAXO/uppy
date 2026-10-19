<?php

namespace FriendsOfRedaxo\Uppy;

use rex;
use rex_api_exception;
use rex_backend_login;
use rex_config;
use rex_dir;
use rex_extension;
use rex_extension_point;
use rex_file;
use rex_logger;
use rex_login;
use rex_mediapool;
use rex_path;
use rex_plugin;
use rex_string;
use rex_user;
use rex_ycom_auth;

use function count;
use function in_array;
use function is_array;
use function is_string;

use const DIRECTORY_SEPARATOR;
use const PHP_SESSION_ACTIVE;

/**
 * Zentrale Sicherheitslogik für alle Uppy-Endpunkte.
 *
 * - Authentifizierung (Backend-User, signierte Feld-Konfiguration, Token/YCom/Extension Point)
 * - Ermittlung der Upload-Konfiguration ausschließlich aus der Signatur (Frontend)
 * - Sperrliste für Dateiendungen, Prüfung des echten MIME-Types und aktiver Inhalte
 * - Auflösung und Eingrenzung von Custom-Upload-Ordnern
 *
 * @package uppy
 */
final class Security
{
    /** Session-Schlüssel der im aktuellen Besuch hochgeladenen Mediapool-Dateien */
    private const SESSION_UPLOADS = 'uppy_uploaded_files';

    /** Maximale Anzahl gemerkter Dateien pro Session */
    private const SESSION_UPLOADS_LIMIT = 200;

    /** Erlaubte Zeichen für die Upload-ID (Chunks/Metadaten) */
    public const FILE_ID_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

    /**
     * Endungen, die auf dem Server ausführbar sind oder die Server-Konfiguration ändern.
     * Werden in JEDEM Punkt-Segment des Dateinamens geprüft (z.B. shell.php.jpg).
     */
    private const BLOCKED_SEGMENTS = [
        'php', 'php2', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'phtml', 'pht', 'phps', 'phar',
        'pl', 'py', 'pyc', 'rb', 'cgi', 'fcgi', 'sh', 'bash',
        'asp', 'aspx', 'ascx', 'ashx', 'asmx', 'jsp', 'jspx', 'jsf', 'cfm', 'shtml', 'shtm',
        'htaccess', 'htpasswd', 'ini', 'exe', 'dll', 'bat', 'cmd', 'ps1', 'vbs', 'msi', 'scr',
    ];

    /**
     * Endungen, die der Browser als aktive Inhalte (HTML/Skript) ausführt. Werden an der
     * letzten Endung geprüft und sind auf allen Upload-Wegen gesperrt.
     */
    private const BLOCKED_ACTIVE_EXTENSIONS = [
        'html', 'htm', 'xhtml', 'xht', 'mht', 'mhtml', 'xml', 'xsl', 'xslt', 'svgz', 'swf', 'hta', 'wasm',
    ];

    /** Zusätzlich im Custom-Ordner gesperrt (dort gibt es keine Prüfung durch den Mediapool). */
    private const BLOCKED_CUSTOM_EXTENSIONS = ['js', 'mjs', 'cjs', 'svg'];

    /**
     * Startet die PHP-Session, falls noch nicht aktiv (rex_session() wirft sonst eine Exception).
     */
    public static function startSession(): bool
    {
        if (PHP_SESSION_ACTIVE === session_status()) {
            return true;
        }
        if (headers_sent()) {
            return false;
        }
        rex_login::startSession();

        return PHP_SESSION_ACTIVE === session_status();
    }

    /**
     * Gibt den eingeloggten Backend-User zurück (auch bei Aufrufen über die Frontend-index.php).
     */
    public static function getBackendUser(): ?rex_user
    {
        $user = rex_backend_login::createUser();

        return $user instanceof rex_user ? $user : null;
    }

    /**
     * Zufälliger Wert pro Browser-Session, an den Signaturen und Upload-IDs gebunden werden.
     * Bleibt bei session_regenerate_id() (z.B. YCom-Login) erhalten.
     */
    public static function getSessionNonce(): string
    {
        if (!self::startSession()) {
            return '';
        }

        $nonce = rex_session('uppy_session_nonce', 'string', '');
        if ('' === $nonce) {
            $nonce = bin2hex(random_bytes(16));
            rex_set_session('uppy_session_nonce', $nonce);
        }

        return $nonce;
    }

    /**
     * API-Token aus Header (X-Uppy-Token) oder POST-Feld – nie aus der URL.
     */
    public static function getRequestToken(): string
    {
        $token = (string) rex_server('HTTP_X_UPPY_TOKEN', 'string', '');
        if ('' === $token) {
            $token = (string) rex_post('api_token', 'string', '');
        }

        return $token;
    }

    /**
     * Signatur aus Header (X-Uppy-Signature) oder Request-Parameter.
     */
    public static function getRequestSignature(): string
    {
        $signature = (string) rex_server('HTTP_X_UPPY_SIGNATURE', 'string', '');
        if ('' === $signature) {
            $signature = (string) rex_request('uppy_signature', 'string', '');
        }

        return $signature;
    }

    /**
     * Prüft, ob ein Nicht-Backend-Aufruf zugelassen ist (API-Token, YCom, Extension Point).
     * Ersetzt NICHT die Signaturprüfung – die ist für alle Nicht-Backend-Aufrufe zusätzlich Pflicht.
     */
    public static function isFrontendIdentityAuthorized(): bool
    {
        self::startSession();

        $apiToken = (string) rex_config::get('uppy', 'api_token', '');
        $requestToken = self::getRequestToken();
        $sessionToken = (string) rex_session('uppy_token', 'string', '');
        $tokenValid = '' !== $apiToken && (
            ('' !== $requestToken && hash_equals($apiToken, $requestToken))
            || ('' !== $sessionToken && hash_equals($apiToken, $sessionToken))
        );

        // Extension Point: eigene Entscheidung über den Zugriff. Signatur, Sperrliste und
        // Ordner-Prüfungen gelten trotzdem.
        $epResult = rex_extension::registerPoint(new rex_extension_point('UPPY_AUTH_CHECK', null, [
            'token_valid' => $tokenValid,
            'request_token' => $requestToken,
            'session_token' => $sessionToken,
        ]));
        if (null !== $epResult) {
            return (bool) $epResult;
        }

        $ycomUser = self::getYcomUser();

        // YCom-Pflicht: ohne eingeloggten YCom-User kein Upload
        if (rex_config::get('uppy', 'ycom_auth_enabled', 0)) {
            if (null === $ycomUser) {
                rex_logger::factory()->log('info', 'Uppy: Upload blocked. YCom auth required but no user logged in.');
                return false;
            }
            return true;
        }

        if ($tokenValid) {
            return true;
        }

        // Kompatibilität: eingeloggter YCom-User ohne Token
        return null !== $ycomUser;
    }

    /**
     * Prüft die Berechtigung für einen API-Aufruf und gibt den Kontext zurück.
     *
     * @return array{backend: bool, user: ?rex_user, signed: bool, config: ?array<string, mixed>}
     * @throws rex_api_exception
     */
    public static function authorize(bool $requireSignatureForBackend = false): array
    {
        self::startSession();

        $user = self::getBackendUser();
        $signature = self::getRequestSignature();
        $config = null;

        if (null !== $user) {
            // Backend: Signatur optional (Mediapool-Upload), aber falls vorhanden muss sie gültig sein.
            if ('' !== $signature) {
                $config = Signature::decode($signature, false);
                if (null === $config) {
                    self::logSecurity('Invalid or expired signature (backend user)');
                    throw new rex_api_exception('Security violation: Invalid signature');
                }
            } elseif ($requireSignatureForBackend) {
                throw new rex_api_exception('Security violation: Missing signature');
            }

            return ['backend' => true, 'user' => $user, 'signed' => null !== $config, 'config' => $config];
        }

        // Frontend / API: Signatur ist Pflicht und an die Session gebunden
        if ('' === $signature) {
            self::logSecurity('Missing signature for non-backend request');
            throw new rex_api_exception('Unauthorized access');
        }

        $config = Signature::decode($signature, true);
        if (null === $config) {
            self::logSecurity('Invalid, expired or foreign signature for non-backend request');
            throw new rex_api_exception('Security violation: Invalid signature');
        }

        if (!self::isFrontendIdentityAuthorized()) {
            throw new rex_api_exception('Unauthorized access');
        }

        return ['backend' => false, 'user' => null, 'signed' => true, 'config' => $config];
    }

    /**
     * Ermittelt die wirksame Upload-Konfiguration.
     * Ist eine Signatur vorhanden, stammen ALLE Werte aus ihr – Request-Parameter werden ignoriert.
     *
     * @param array{backend: bool, user: ?rex_user, signed: bool, config: ?array<string, mixed>} $auth
     * @return array{category_id: int, allowed_types: string, max_bytes: int, upload_dir: string, enforce_types: bool}
     * @throws rex_api_exception
     */
    public static function resolveUploadConfig(array $auth): array
    {
        $globalMax = (int) round((float) rex_config::get('uppy', 'max_filesize', 200) * 1024 * 1024);

        if ($auth['signed'] && is_array($auth['config'])) {
            $signed = $auth['config'];
            $maxBytes = $globalMax;
            if (isset($signed['max_filesize']) && (float) $signed['max_filesize'] > 0) {
                $maxBytes = min($globalMax, (int) round((float) $signed['max_filesize'] * 1024 * 1024));
            }
            $allowedTypes = trim((string) ($signed['allowed_types'] ?? ''));
            if ('' === $allowedTypes) {
                $allowedTypes = (string) rex_config::get('uppy', 'allowed_types', '');
            }

            $config = [
                'category_id' => (int) ($signed['category_id'] ?? 0),
                'allowed_types' => $allowedTypes,
                'max_bytes' => $maxBytes,
                'upload_dir' => (string) ($signed['upload_dir'] ?? ''),
                'enforce_types' => true,
            ];
        } else {
            // Nur Backend-User ohne Signatur (Mediapool-Upload-Seite): Kategorie aus dem Request,
            // Custom-Ordner nur über eine Signatur.
            if ('' !== trim(rex_request('upload_dir', 'string', ''))) {
                self::logSecurity('upload_dir without signature rejected');
                throw new rex_api_exception('Security violation: upload_dir requires a signature');
            }

            $config = [
                'category_id' => rex_request('category_id', 'int', 0),
                'allowed_types' => '',
                'max_bytes' => $globalMax,
                'upload_dir' => '',
                'enforce_types' => false,
            ];
        }

        // Backend-User: Rechte für die Medienkategorie prüfen
        if ($auth['backend'] && null !== $auth['user'] && '' === $config['upload_dir']) {
            $mediaPerm = $auth['user']->getComplexPerm('media');
            if ($mediaPerm && !$mediaPerm->hasCategoryPerm($config['category_id'])) {
                throw new rex_api_exception('No permission for this media category');
            }
        }

        return $config;
    }

    /**
     * Prüft eine Upload-ID (fileId) auf erlaubte Zeichen.
     *
     * @throws rex_api_exception
     */
    public static function assertValidFileId(string $fileId): void
    {
        if (1 !== preg_match(self::FILE_ID_PATTERN, $fileId)) {
            throw new rex_api_exception('Invalid fileId');
        }
    }

    /**
     * Interner, an die Session gebundener Speichername für Chunks/Metadaten einer Upload-ID.
     */
    public static function storageId(string $fileId): string
    {
        self::assertValidFileId($fileId);

        return hash('sha256', self::getSessionNonce() . '|' . $fileId);
    }

    /**
     * Bereinigt einen vom Client gelieferten Dateinamen (nur Basisname, keine Steuerzeichen).
     */
    public static function cleanClientFilename(string $filename): string
    {
        $filename = str_replace(["\0", '\\'], ['', '/'], $filename);
        $filename = basename($filename);
        $filename = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $filename);

        return trim($filename);
    }

    /**
     * Prüft den Dateinamen gegen die Sperrliste (alle Punkt-Segmente, Groß-/Kleinschreibung egal).
     *
     * @throws rex_api_exception
     */
    public static function assertFilenameAllowed(string $filename, bool $customFolder): void
    {
        $lower = mb_strtolower($filename);

        if ('' === $lower || '.' === $lower[0] || str_contains($lower, '/') || str_contains($lower, ':')) {
            self::logSecurity('Blocked filename: ' . $filename);
            throw new rex_api_exception('File type not allowed');
        }

        $segments = explode('.', $lower);
        foreach ($segments as $index => $segment) {
            $segment = trim($segment);
            if (0 === $index && 1 < count($segments)) {
                // Erster Teil ist der Name selbst – trotzdem prüfen, falls er z.B. "php" lautet und
                // eine weitere Endung folgt (php.jpg ist harmlos, .php am Anfang wurde oben abgewiesen).
                continue;
            }
            if (in_array($segment, self::BLOCKED_SEGMENTS, true) || preg_match('/^php\d*$/', $segment) || preg_match('/^pht/', $segment)) {
                self::logSecurity('Blocked file extension: ' . $filename);
                throw new rex_api_exception('File type not allowed');
            }
        }

        $extension = 1 < count($segments) ? (string) end($segments) : '';
        if ('' === $extension) {
            throw new rex_api_exception('File type not allowed');
        }

        if (in_array($extension, self::BLOCKED_ACTIVE_EXTENSIONS, true)
            || ($customFolder && in_array($extension, self::BLOCKED_CUSTOM_EXTENSIONS, true))
        ) {
            self::logSecurity('Blocked active content extension: ' . $filename);
            throw new rex_api_exception('File type not allowed');
        }

        // Zusätzlich die Sperrliste des Mediapools anwenden
        if (class_exists(rex_mediapool::class) && !rex_mediapool::isAllowedExtension($filename)) {
            self::logSecurity('Blocked by mediapool extension list: ' . $filename);
            throw new rex_api_exception('File type not allowed');
        }
    }

    /**
     * Prüft den Inhalt einer Datei: echter MIME-Type (finfo) gegen erlaubte Typen und
     * Dateiendung, sowie SVG-Dateien auf aktive Inhalte.
     *
     * @throws rex_api_exception
     */
    public static function assertContentAllowed(string $path, string $filename, string $allowedTypes, bool $enforceTypes, bool $customFolder): string
    {
        $extension = mb_strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $mimeType = (string) (rex_file::mimeType($path, $filename) ?? 'application/octet-stream');
        $mimeType = mb_strtolower($mimeType);

        // Ausführbare Inhalte unabhängig von der Endung ablehnen
        $blockedMimes = [
            'text/x-php', 'application/x-php', 'application/x-httpd-php', 'text/x-shellscript', 'application/x-sh',
            'text/x-perl', 'text/x-python', 'application/x-executable', 'application/x-dosexec', 'application/x-mach-binary',
            'text/html', 'application/xhtml+xml',
        ];
        if (in_array($mimeType, $blockedMimes, true)) {
            self::logSecurity('Blocked content type ' . $mimeType . ' for ' . $filename);
            throw new rex_api_exception('File type not allowed');
        }

        if ('svg' === $extension || 'image/svg+xml' === $mimeType) {
            if ($customFolder || !self::isSafeSvg($path)) {
                self::logSecurity('Blocked SVG with active content: ' . $filename);
                throw new rex_api_exception('File type not allowed');
            }
        }

        if ($enforceTypes && '' !== trim($allowedTypes) && !self::matchesAllowedTypes($mimeType, $extension, $allowedTypes)) {
            throw new rex_api_exception('File type not allowed');
        }

        // Custom-Ordner: Endung und echter Inhalt müssen zusammenpassen (gleiche Liste wie im Mediapool)
        if ($customFolder && class_exists(rex_mediapool::class)) {
            $map = rex_mediapool::getAllowedMimeTypes();
            if ([] !== $map) {
                if (isset($map[$extension])) {
                    if (!in_array($mimeType, $map[$extension], true)) {
                        self::logSecurity('MIME type ' . $mimeType . ' does not match extension of ' . $filename);
                        throw new rex_api_exception('File type not allowed');
                    }
                } elseif (!self::allowedTypesListExtension($allowedTypes, $extension)) {
                    // Unbekannte Endung nur, wenn sie ausdrücklich (z.B. ".stl") freigegeben ist
                    throw new rex_api_exception('File type not allowed');
                }
            }
        }

        return $mimeType;
    }

    /**
     * Prüft einen MIME-Type/eine Endung gegen eine Liste wie "image/*,application/pdf,.stl".
     */
    public static function matchesAllowedTypes(string $mimeType, string $extension, string $allowedTypes): bool
    {
        foreach (explode(',', $allowedTypes) as $type) {
            $type = mb_strtolower(trim($type));
            if ('' === $type) {
                continue;
            }
            if ('*' === $type || '*/*' === $type) {
                return true;
            }
            if (str_starts_with($type, '.')) {
                if ('.' . $extension === $type) {
                    return true;
                }
                continue;
            }
            if (str_ends_with($type, '/*')) {
                if (str_starts_with($mimeType, substr($type, 0, -1))) {
                    return true;
                }
                continue;
            }
            if ($type === $mimeType) {
                return true;
            }
        }

        return false;
    }

    private static function allowedTypesListExtension(string $allowedTypes, string $extension): bool
    {
        foreach (explode(',', $allowedTypes) as $type) {
            if ('.' . $extension === mb_strtolower(trim($type))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Einfache Prüfung von SVG-Dateien auf Skripte, Event-Handler und eingebettete Dokumente.
     */
    public static function isSafeSvg(string $path): bool
    {
        $content = rex_file::get($path);
        if (!is_string($content)) {
            return false;
        }

        $patterns = [
            '/<\s*script/i',
            '/<\s*foreignObject/i',
            '/<\s*(iframe|embed|object|handler|listener)\b/i',
            '/\son[a-z]+\s*=/i',
            '/(java|vb)script\s*:/i',
            '/data\s*:\s*text\/html/i',
            '/<!ENTITY/i',
            '/<\?php/i',
        ];
        foreach ($patterns as $pattern) {
            if (1 === preg_match($pattern, $content)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Basisverzeichnisse, unterhalb derer Custom-Upload-Ordner liegen dürfen:
     * die REDAXO-Installation (rex_path::base()) sowie in den Einstellungen freigegebene Ordner.
     *
     * @return list<string> realpath ohne abschließenden Separator
     */
    public static function getAllowedBaseDirs(): array
    {
        $bases = [];
        $candidates = [rex_path::base()];

        $extra = (string) rex_config::get('uppy', 'upload_base_dirs', '');
        foreach (preg_split('/[\r\n]+/', $extra) ?: [] as $line) {
            $line = trim($line);
            if ('' !== $line && (str_starts_with($line, '/') || 1 === preg_match('#^[A-Za-z]:[\\\\/]#', $line))) {
                $candidates[] = $line;
            }
        }

        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if (false !== $real && is_dir($real)) {
                $bases[] = rtrim($real, '/\\');
            }
        }

        return array_values(array_unique($bases));
    }

    /**
     * Systemordner, in die nie direkt hochgeladen werden darf.
     *
     * @return list<string>
     */
    private static function getProtectedDirs(): array
    {
        $dirs = [
            rex_path::backend(),
            rex_path::assets(),
            rex_path::media(),
            rex_path::src(),
            rex_path::cache(),
            rex_path::bin(),
            rex_path::base('vendor'),
            rex_path::frontend('vendor'),
            rex_path::base('node_modules'),
        ];

        $result = [];
        foreach ($dirs as $dir) {
            $real = realpath($dir);
            $result[] = rtrim(false !== $real ? $real : $dir, '/\\');
        }

        return array_values(array_unique($result));
    }

    /**
     * Ausnahmen innerhalb geschützter Ordner: eigene Unterordner von redaxo/data
     * (nicht über das Web erreichbar), ausgenommen core- und AddOn-Daten.
     */
    private static function isAllowedDataSubdir(string $path): bool
    {
        $data = realpath(rex_path::data());
        if (false === $data) {
            return false;
        }
        $data = rtrim($data, '/\\');
        if (!self::isInside($path, $data) || $path === $data) {
            return false;
        }

        foreach ([rex_path::coreData(), rex_path::data('addons'), rex_path::log()] as $blocked) {
            $realBlocked = realpath($blocked);
            $blocked = rtrim(false !== $realBlocked ? $realBlocked : $blocked, '/\\');
            if (self::isInside($path, $blocked)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Prüft, ob $path gleich $base ist oder darunter liegt (Grenze am Separator).
     */
    public static function isInside(string $path, string $base): bool
    {
        $path = rtrim($path, '/\\');
        $base = rtrim($base, '/\\');

        return $path === $base || str_starts_with($path, $base . DIRECTORY_SEPARATOR) || str_starts_with($path, $base . '/');
    }

    /**
     * Löst einen Custom-Upload-Ordner (relativ zu rex_path::base()) sicher auf.
     *
     * @param bool $create Ordner bei Bedarf anlegen (inkl. Schutz-.htaccess und index.html)
     * @throws rex_api_exception
     */
    public static function resolveCustomDirectory(string $uploadDir, bool $create = true): string
    {
        $uploadDir = trim(str_replace('\\', '/', $uploadDir));

        if ('' === $uploadDir || str_contains($uploadDir, "\0") || str_contains($uploadDir, ':')) {
            throw new rex_api_exception('Invalid upload directory');
        }

        // Führender Slash bedeutet weiterhin "relativ zur Installation" (bisheriges Verhalten)
        $base = rtrim(str_replace('\\', '/', (string) realpath(rex_path::base())), '/');
        if ('' === $base) {
            throw new rex_api_exception('Invalid upload directory');
        }

        // Pfad lexikalisch normalisieren (.. und . auflösen, ohne dass der Ordner existieren muss)
        $parts = explode('/', $base);
        foreach (explode('/', $uploadDir) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }
            if ('..' === $segment) {
                if (count($parts) <= 1) {
                    throw new rex_api_exception('Invalid upload directory');
                }
                array_pop($parts);
                continue;
            }
            if (str_starts_with($segment, '.')) {
                // Versteckte Ordner (.git, .well-known …) sind tabu
                throw new rex_api_exception('Invalid upload directory');
            }
            $parts[] = $segment;
        }
        $target = implode('/', $parts);

        // Symlinks berücksichtigen: nächsten existierenden Vorfahren auflösen
        $existing = $target;
        $rest = [];
        while (!is_dir($existing)) {
            $rest[] = basename($existing);
            $parent = dirname($existing);
            if ($parent === $existing) {
                throw new rex_api_exception('Invalid upload directory');
            }
            $existing = $parent;
        }
        $realExisting = realpath($existing);
        if (false === $realExisting) {
            throw new rex_api_exception('Invalid upload directory');
        }
        $resolved = rtrim($realExisting, '/\\');
        foreach (array_reverse($rest) as $segment) {
            $resolved .= '/' . $segment;
        }

        self::assertDirectoryAllowed($resolved, $uploadDir);

        if (!is_dir($resolved)) {
            if (!$create) {
                throw new rex_api_exception('Upload directory not found');
            }
            if (!rex_dir::create($resolved)) {
                throw new rex_api_exception('Could not create target directory');
            }
            self::protectDirectory($resolved);
            $real = realpath($resolved);
            if (false === $real) {
                throw new rex_api_exception('Could not resolve created target directory');
            }
            $resolved = $real;
            self::assertDirectoryAllowed($resolved, $uploadDir);
        }

        return $resolved;
    }

    /**
     * @throws rex_api_exception
     */
    private static function assertDirectoryAllowed(string $resolved, string $uploadDir): void
    {
        $inBase = false;
        foreach (self::getAllowedBaseDirs() as $allowedBase) {
            if (self::isInside($resolved, $allowedBase) && rtrim($resolved, '/\\') !== $allowedBase) {
                $inBase = true;
                break;
            }
        }

        if (!$inBase) {
            self::logSecurity('upload_dir outside of allowed base directories: ' . $uploadDir);
            throw new rex_api_exception('Invalid upload directory');
        }

        // Frontend-Root selbst ist ebenfalls tabu
        $frontend = realpath(rex_path::frontend());
        if (false !== $frontend && rtrim($resolved, '/\\') === rtrim($frontend, '/\\')) {
            throw new rex_api_exception('Invalid upload directory');
        }

        foreach (self::getProtectedDirs() as $protected) {
            if (self::isInside($resolved, $protected) && !self::isAllowedDataSubdir($resolved)) {
                self::logSecurity('upload_dir points to a REDAXO system directory: ' . $uploadDir);
                throw new rex_api_exception('Invalid upload directory');
            }
        }
    }

    /**
     * Legt in einem neu erstellten Upload-Ordner eine .htaccess (keine Skriptausführung) und
     * eine leere index.html (kein Verzeichnislisting) an.
     */
    public static function protectDirectory(string $dir): void
    {
        $htaccess = $dir . '/.htaccess';
        if (!is_file($htaccess)) {
            rex_file::put($htaccess, <<<'HTACCESS'
                # Angelegt vom REDAXO-AddOn Uppy: in diesem Upload-Ordner keine Skripte ausführen
                <IfModule mod_php.c>
                    php_flag engine off
                </IfModule>
                <IfModule mod_php7.c>
                    php_flag engine off
                </IfModule>
                <IfModule mod_php8.c>
                    php_flag engine off
                </IfModule>
                <FilesMatch "(?i)\.(php\d?|phtml|pht|phps|phar|inc|pl|py|cgi|fcgi|sh|bash|asp|aspx|jsp|shtml?|html?|xhtml|svgz?|js|htaccess|htpasswd|ini)$">
                    <IfModule mod_authz_core.c>
                        Require all denied
                    </IfModule>
                    <IfModule !mod_authz_core.c>
                        Order allow,deny
                        Deny from all
                    </IfModule>
                </FilesMatch>
                <IfModule mod_headers.c>
                    Header set X-Content-Type-Options "nosniff"
                </IfModule>

                HTACCESS);
        }

        $index = $dir . '/index.html';
        if (!is_file($index)) {
            rex_file::put($index, '');
        }
    }

    /**
     * Merkt sich eine im Frontend hochgeladene Mediapool-Datei in der Session, damit nur
     * diese Dateien anschließend über die Metadaten-API bearbeitet werden können.
     */
    public static function rememberUploadedFile(string $filename): void
    {
        if (!self::startSession()) {
            return;
        }
        $files = rex_session(self::SESSION_UPLOADS, 'array', []);
        $files[] = $filename;
        $files = array_slice(array_values(array_unique(array_map('strval', $files))), -self::SESSION_UPLOADS_LIMIT);
        rex_set_session(self::SESSION_UPLOADS, $files);
    }

    public static function wasUploadedInSession(string $filename): bool
    {
        if (!self::startSession()) {
            return false;
        }
        $files = rex_session(self::SESSION_UPLOADS, 'array', []);

        return in_array($filename, $files, true);
    }

    private static function getYcomUser(): mixed
    {
        if (!class_exists(rex_ycom_auth::class) || !rex_plugin::get('ycom', 'auth')->isAvailable()) {
            return null;
        }

        return rex_ycom_auth::getUser() ?: null;
    }

    private static function logSecurity(string $message): void
    {
        rex_logger::factory()->log('warning', 'UPPY_SECURITY: ' . $message . ' (IP ' . rex_server('REMOTE_ADDR', 'string', '') . ')');
    }
}
