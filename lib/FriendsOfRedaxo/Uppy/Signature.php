<?php

namespace FriendsOfRedaxo\Uppy;

use rex_config;

use function count;
use function is_array;

/**
 * Signierte Upload-Konfiguration (HMAC-SHA256).
 *
 * Seit 2.11.0 enthält die Signatur die signierten Werte selbst, ein Ablaufdatum und
 * eine Bindung an die Browser-Session. Der Server liest Kategorie, erlaubte Typen,
 * Maximalgröße und Upload-Ordner ausschließlich aus der Signatur.
 *
 * Format: "u2.<base64url(JSON)>.<hmac>"
 */
class Signature {

    /** Standard-Gültigkeit einer Signatur in Sekunden (24 Stunden) */
    public const DEFAULT_TTL = 86400;

    private const PREFIX = 'u2';

    /**
     * Gibt das Secret zurück oder generiert ein neues
     */
    public static function getSecret(): string {
        $secret = rex_config::get('uppy', 'secret');
        if (!$secret) {
            $secret = bin2hex(random_bytes(32));
            rex_config::set('uppy', 'secret', $secret);
        }
        return $secret;
    }

    /**
     * Erstellt eine Signatur für die Parameter
     * (category_id, allowed_types, max_filesize, upload_dir).
     *
     * @param array<string, mixed> $params
     * @param int|null $ttl Gültigkeit in Sekunden (Standard: 24 Stunden)
     */
    public static function create(array $params, ?int $ttl = null): string {
        $payload = [
            'p' => self::normalize($params),
            'e' => time() + max(60, $ttl ?? self::DEFAULT_TTL),
            's' => self::sessionBinding(),
        ];

        $data = self::base64UrlEncode((string) json_encode($payload));

        return self::PREFIX . '.' . $data . '.' . self::hmac($data);
    }

    /**
     * Überprüft die Signatur gegen die erwarteten Parameter (Abwärtskompatibilität).
     *
     * @param array<string, mixed> $params
     */
    public static function verify(array $params, string $signature): bool {
        $decoded = self::decode($signature, true);
        if (null === $decoded) {
            return false;
        }

        return http_build_query(self::normalize($params)) === http_build_query(self::normalize($decoded));
    }

    /**
     * Prüft Signatur, Ablaufzeit und (optional) Session-Bindung und gibt die signierten
     * Parameter zurück – oder null, wenn die Signatur ungültig ist.
     *
     * @return array<string, string>|null
     */
    public static function decode(string $signature, bool $checkSession = true): ?array {
        $parts = explode('.', $signature);
        if (3 !== count($parts) || self::PREFIX !== $parts[0] || '' === $parts[1]) {
            return null;
        }

        if (!hash_equals(self::hmac($parts[1]), $parts[2])) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($parts[1]), true);
        if (!is_array($payload) || !is_array($payload['p'] ?? null) || !isset($payload['e'])) {
            return null;
        }

        if ((int) $payload['e'] < time()) {
            return null;
        }

        if ($checkSession) {
            $binding = (string) ($payload['s'] ?? '');
            $current = self::sessionBinding();
            if ('' === $binding || '' === $current || !hash_equals($binding, $current)) {
                return null;
            }
        }

        $params = [];
        foreach ($payload['p'] as $key => $value) {
            $params[(string) $key] = (string) $value;
        }

        return $params;
    }

    /**
     * Leere Werte entfernen, Werte als String, Schlüssel sortieren.
     *
     * @param array<string, mixed> $params
     * @return array<string, string>
     */
    private static function normalize(array $params): array {
        $result = [];
        foreach ($params as $key => $value) {
            if (null === $value || '' === $value || is_array($value)) {
                continue;
            }
            $result[(string) $key] = (string) $value;
        }
        ksort($result);

        return $result;
    }

    /**
     * Wert, der die Signatur an die aktuelle Browser-Session bindet
     * (leer, wenn keine Session gestartet werden kann).
     */
    private static function sessionBinding(): string {
        $nonce = Security::getSessionNonce();
        if ('' === $nonce) {
            return '';
        }

        return substr(hash_hmac('sha256', 'session|' . $nonce, self::getSecret()), 0, 32);
    }

    private static function hmac(string $data): string {
        return hash_hmac('sha256', self::PREFIX . '|' . $data, self::getSecret());
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }
}
