<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Uppy;

use rex_plugin;
use rex_user;

use function array_map;
use function is_array;

/**
 * Verwaltet die Backend-User-Defaults für YCom Media Auth bei Uppy-Uploads.
 *
 * Defaults werden pro Backend-Session gespeichert und beim Upload auf alle
 * Dateien angewendet (Bulk-Pre-Set vor dem Upload).
 */
final class YcomAuthSettings
{
    /** Backend-Permission, die für die Verwaltung erforderlich ist. */
    public const PERM = 'uppy[ycom_media_auth]';

    private const SESSION_KEY = 'uppy_ycom_auth_defaults';

    /**
     * Prüft, ob das ycom/media_auth Plugin verfügbar ist (Spalten existieren).
     */
    public static function isAvailable(): bool
    {
        return rex_plugin::get('ycom', 'media_auth')->isAvailable();
    }

    /**
     * Ist das Feature in den Uppy-Einstellungen aktiviert?
     */
    public static function isEnabled(): bool
    {
        return self::isAvailable()
            && (bool) \rex_config::get('uppy', 'ycom_media_auth_defaults_enabled', false);
    }

    /**
     * Prüft, ob das ycom/group Plugin verfügbar ist.
     */
    public static function isGroupSupportAvailable(): bool
    {
        return rex_plugin::get('ycom', 'group')->isAvailable();
    }

    /**
     * Darf der Backend-User die YCom-Auth-Einstellungen für Uploads setzen?
     */
    public static function userMayManage(?rex_user $user): bool
    {
        if (null === $user) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }
        return $user->hasPerm(self::PERM);
    }

    /**
     * Liefert die aktuell gespeicherten Defaults aus der Backend-Session.
     *
     * @return array{ycom_auth_type: int, ycom_group_type: int, ycom_groups: list<int>}
     */
    public static function getSessionDefaults(): array
    {
        $defaults = \rex_session(self::SESSION_KEY, 'array', []);

        $authType = isset($defaults['ycom_auth_type']) ? (int) $defaults['ycom_auth_type'] : 0;
        $groupType = isset($defaults['ycom_group_type']) ? (int) $defaults['ycom_group_type'] : 0;

        $groups = [];
        if (isset($defaults['ycom_groups']) && is_array($defaults['ycom_groups'])) {
            foreach ($defaults['ycom_groups'] as $g) {
                $g = (int) $g;
                if ($g > 0) {
                    $groups[] = $g;
                }
            }
        }

        return [
            'ycom_auth_type' => 1 === $authType ? 1 : 0,
            'ycom_group_type' => $groupType,
            'ycom_groups' => $groups,
        ];
    }

    /**
     * Schreibt die Defaults in die Backend-Session.
     *
     * @param array{ycom_auth_type: int, ycom_group_type: int, ycom_groups: list<int>} $defaults
     */
    public static function setSessionDefaults(array $defaults): void
    {
        \rex_set_session(self::SESSION_KEY, [
            'ycom_auth_type' => 1 === (int) $defaults['ycom_auth_type'] ? 1 : 0,
            'ycom_group_type' => (int) $defaults['ycom_group_type'],
            'ycom_groups' => array_map('intval', $defaults['ycom_groups']),
        ]);
    }
}
