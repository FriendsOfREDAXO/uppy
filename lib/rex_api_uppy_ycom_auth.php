<?php

declare(strict_types=1);

use FriendsOfRedaxo\Uppy\YcomAuthSettings;

/**
 * API-Endpoint zum Speichern der YCom-Media-Auth-Defaults pro Backend-Session.
 *
 * Wird vom Upload-Formular aufgerufen, sobald ein berechtigter Backend-User
 * eines der drei Felder (Auth-Typ, Group-Typ, Gruppen) verändert.
 */
class rex_api_uppy_ycom_auth extends rex_api_function
{
    protected $published = false;

    public function execute()
    {
        rex_response::cleanOutputBuffers();

        // Backend-Session zwingend
        if (!rex_backend_login::hasSession()) {
            rex_response::setStatus(rex_response::HTTP_FORBIDDEN);
            rex_response::sendJson(['error' => 'forbidden']);
            exit;
        }

        $user = rex::getUser();
        if (!YcomAuthSettings::isEnabled() || !YcomAuthSettings::userMayManage($user)) {
            rex_response::setStatus(rex_response::HTTP_FORBIDDEN);
            rex_response::sendJson(['error' => 'forbidden']);
            exit;
        }

        $authType = rex_request('ycom_auth_type', 'int', 0);
        $groupType = rex_request('ycom_group_type', 'int', 0);
        $groupsRaw = rex_request('ycom_groups', 'array', []);

        $groups = [];
        foreach ($groupsRaw as $g) {
            $gid = (int) $g;
            if ($gid > 0) {
                $groups[] = $gid;
            }
        }

        YcomAuthSettings::setSessionDefaults([
            'ycom_auth_type' => $authType,
            'ycom_group_type' => $groupType,
            'ycom_groups' => $groups,
        ]);

        rex_response::sendJson([
            'success' => true,
            'defaults' => YcomAuthSettings::getSessionDefaults(),
        ]);
        exit;
    }
}
