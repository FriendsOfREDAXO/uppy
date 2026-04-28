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

    function buildFormData() {
        var fd = new FormData();
        fd.append('ycom_auth_type', getValue('uppy-ycom-auth-type') || '0');
        fd.append('ycom_group_type', getValue('uppy-ycom-group-type') || '0');
        getMultiValues('uppy-ycom-groups').forEach(function (v) {
            fd.append('ycom_groups[]', v);
        });
        return fd;
    }

    function save() {
        return fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: buildFormData()
        }).catch(function () {
            // Fehler still behandeln; Defaults sind nicht kritisch
        });
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

    /**
     * Status-Badge im Panel-Header aktualisieren.
     */
    function updateBadge() {
        var badge = document.getElementById('uppy-ycom-auth-status');
        if (!badge) {
            return;
        }
        var authType = parseInt(getValue('uppy-ycom-auth-type') || '0', 10);
        if (authType === 1) {
            badge.textContent = badge.dataset.protectedText || 'eingeloggte';
            badge.classList.remove('label-default');
            badge.classList.add('label-warning');
        } else {
            badge.textContent = badge.dataset.publicText || 'öffentlich';
            badge.classList.remove('label-warning');
            badge.classList.add('label-default');
        }
    }

    function onChange() {
        updateVisibility();
        updateBadge();
        save();
    }

    function reset() {
        var auth = document.getElementById('uppy-ycom-auth-type');
        var groupType = document.getElementById('uppy-ycom-group-type');
        var groups = document.getElementById('uppy-ycom-groups');
        if (auth) { auth.value = '0'; }
        if (groupType) { groupType.value = '0'; }
        if (groups) {
            for (var i = 0; i < groups.options.length; i++) {
                groups.options[i].selected = false;
            }
        }
        onChange();
    }

    function init() {
        var ids = ['uppy-ycom-auth-type', 'uppy-ycom-group-type', 'uppy-ycom-groups'];
        var found = false;
        ids.forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', onChange);
                found = true;
            }
        });

        var resetBtn = document.getElementById('uppy-ycom-auth-reset');
        if (resetBtn) {
            resetBtn.addEventListener('click', function (e) {
                e.preventDefault();
                reset();
            });
        }

        if (found) {
            updateVisibility();
            updateBadge();
            // Beim ersten Laden Defaults aus dem aktuellen Form-State sicher in die Session schreiben.
            save();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
