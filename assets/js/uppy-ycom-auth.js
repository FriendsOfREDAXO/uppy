/**
 * Uppy YCom Media Auth Defaults
 *
 * Speichert die im Upload-Formular ausgewählten YCom-Media-Auth-Defaults
 * (Auth-Typ / Group-Typ / Gruppen) live in der Backend-Session.
 *
 * Die Werte werden vom Server beim Upload auf jede neu hochgeladene Datei
 * angewendet (siehe FriendsOfRedaxo\Uppy\UppyUploadHandler::applyYcomMediaAuthDefaults()).
 */
(function () {
    'use strict';

    var endpoint = 'index.php?rex-api-call=uppy_ycom_auth';

    function getValue(id) {
        var el = document.getElementById(id);
        return el ? el.value : '';
    }

    function getMultiValues(id) {
        var el = document.getElementById(id);
        if (!el || !el.options) {
            return [];
        }
        var values = [];
        for (var i = 0; i < el.options.length; i++) {
            if (el.options[i].selected) {
                values.push(el.options[i].value);
            }
        }
        return values;
    }

    function save() {
        var fd = new FormData();
        fd.append('ycom_auth_type', getValue('uppy-ycom-auth-type') || '0');
        fd.append('ycom_group_type', getValue('uppy-ycom-group-type') || '0');

        var groups = getMultiValues('uppy-ycom-groups');
        groups.forEach(function (v) {
            fd.append('ycom_groups[]', v);
        });

        fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).catch(function () {
            // Fehler still behandeln; Defaults sind nicht kritisch
        });
    }

    function init() {
        var ids = ['uppy-ycom-auth-type', 'uppy-ycom-group-type', 'uppy-ycom-groups'];
        var found = false;
        ids.forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', function () {
                    save();
                    updateVisibility();
                });
                found = true;
            }
        });

        if (found) {
            updateVisibility();
            // Beim ersten Laden Defaults aus dem aktuellen Form-State sicher in die Session schreiben.
            save();
        }
    }

    /**
     * Sichtbarkeit der abhängigen Felder steuern:
     * - Gruppentyp-Select erscheint nur, wenn Auth = "nur eingeloggte"
     * - Gruppen-Mehrfachauswahl erscheint nur, wenn ein konkreter Gruppentyp (>0) gewählt ist
     */
    function updateVisibility() {
        var authType = parseInt(getValue('uppy-ycom-auth-type') || '0', 10);
        var groupType = parseInt(getValue('uppy-ycom-group-type') || '0', 10);

        var groupTypeRow = document.getElementById('uppy-ycom-group-type-row');
        var groupsRow = document.getElementById('uppy-ycom-groups-row');

        if (groupTypeRow) {
            groupTypeRow.style.display = (authType === 1) ? '' : 'none';
        }
        if (groupsRow) {
            groupsRow.style.display = (authType === 1 && groupType > 0) ? '' : 'none';
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
