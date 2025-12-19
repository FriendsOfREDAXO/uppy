<?php

namespace FriendsOfRedaxo\Uppy;

use rex_config;

class Signature {
    
    /**
     * Gibt das Secret zurück oder generiert ein neues
     */
    public static function getSecret(): string {
        $secret = rex_config::get('uppy', 'secret');
        if (!$secret) {
            try {
                $secret = bin2hex(random_bytes(32));
            } catch (\Exception $e) {
                $secret = md5(uniqid('uppy_secret', true));
            }
            rex_config::set('uppy', 'secret', $secret);
        }
        return $secret;
    }

    /**
     * Erstellt eine Signatur für die Parameter
     */
    public static function create(array $params): string {
        // Nur relevante Parameter signieren und leere entfernen
        $signParams = array_filter($params, function($v) {
            return $v !== null && $v !== '';
        });
        
        // Sortieren für Konsistenz
        ksort($signParams);
        
        $query = http_build_query($signParams);
        return hash_hmac('sha256', $query, self::getSecret());
    }

    /**
     * Überprüft die Signatur
     */
    public static function verify(array $params, string $signature): bool {
        if (empty($signature)) {
            return false;
        }
        $expected = self::create($params);
        return hash_equals($expected, $signature);
    }
}
