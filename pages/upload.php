<?php

$addon = rex_addon::get('uppy');

// Parameter für Mediapool-Widget-Integration
$openerInputField = rex_request('opener_input_field', 'string', '');

// Einführungstext
$content = '<p>' . $addon->i18n('uppy_upload_intro') . '</p>';

// Upload-Widget
$formElements = [];

$n = [];
$n['label'] = '<label>' . $addon->i18n('uppy_select_category') . '</label>';

// rex_media_category_select verwenden (wie im Standard-Mediapool)
$selectedCategory = rex_request('category_id', 'int', 0);
$catsSel = new rex_media_category_select(true); // checkPerm = true: nur erlaubte Kategorien anzeigen
$catsSel->setStyle('class="form-control"');
$catsSel->setSize(1);
$catsSel->setName('category_id');
$catsSel->setId('uppy-category');
$catsSel->setAttribute('class', 'selectpicker form-control');
$catsSel->setAttribute('data-live-search', 'true');

// Custom-Widget Modus prüfen
$useCustomWidget = rex_config::get('uppy', 'use_custom_widget', false);

// "Keine Kategorie" Option wenn Berechtigung vorhanden
$mediaPerm = rex::getUser() ? rex::getUser()->getComplexPerm('media') : null;
if ($mediaPerm instanceof rex_media_perm && $mediaPerm->hasAll()) {
    $catsSel->addOption(rex_i18n::msg('pool_kats_no'), '0');
    if ($selectedCategory === 0) {
        $selectedCategory = (int) rex_config::get('uppy', 'category_id', 0);
    }
} elseif ($selectedCategory === 0) {
    // Eingeschränkter User ohne explizit gesetzte Kategorie:
    // erste verfügbare Kategorie automatisch vorauswählen
    $rootCats = rex_media_category::getRootCategories();
    foreach ($rootCats as $cat) {
        if (!($mediaPerm instanceof rex_media_perm) || $mediaPerm->hasCategoryPerm($cat->getId())) {
            $selectedCategory = $cat->getId();
            break;
        }
    }
}
$catsSel->setSelected($selectedCategory);

$n['field'] = $catsSel->get();
$formElements[] = $n;

// YCom Media Auth Defaults (nur wenn aktiviert + verfügbar + User berechtigt).
// Wird als ausklappbares Panel direkt nach der Kategorieauswahl gerendert.
$ycomAuthHtml = '';
if (\FriendsOfRedaxo\Uppy\YcomAuthSettings::isEnabled()
    && \FriendsOfRedaxo\Uppy\YcomAuthSettings::userMayManage(rex::getUser())) {
    $ycomDefaults = \FriendsOfRedaxo\Uppy\YcomAuthSettings::getSessionDefaults();
    $hasGroupSupport = \FriendsOfRedaxo\Uppy\YcomAuthSettings::isGroupSupportAvailable();

    // Auth-Typ Select
    $authSel = new rex_select();
    $authSel->setName('ycom_auth_type');
    $authSel->setId('uppy-ycom-auth-type');
    $authSel->setSize(1);
    $authSel->setAttribute('class', 'form-control');
    $authSel->addArrayOptions([
        0 => $addon->i18n('uppy_ycom_auth_all'),
        1 => $addon->i18n('uppy_ycom_auth_only_logged_in'),
    ]);
    $authSel->setSelected($ycomDefaults['ycom_auth_type']);

    $rowAuth = '<div class="form-group">'
        . '<label for="uppy-ycom-auth-type">' . rex_escape($addon->i18n('uppy_ycom_auth_type')) . '</label>'
        . $authSel->get()
        . '<p class="help-block text-muted">' . rex_escape($addon->i18n('uppy_ycom_auth_type_note')) . '</p>'
        . '</div>';

    // Group-Typ + Gruppen (nur bei "nur eingeloggte" + ycom/group)
    $rowGroupType = '';
    $rowGroups = '';
    if ($hasGroupSupport) {
        $groupTypeSel = new rex_select();
        $groupTypeSel->setName('ycom_group_type');
        $groupTypeSel->setId('uppy-ycom-group-type');
        $groupTypeSel->setSize(1);
        $groupTypeSel->setAttribute('class', 'form-control');
        $groupTypeSel->addArrayOptions([
            0 => rex_i18n::msg('ycom_group_forallgroups'),
            1 => rex_i18n::msg('ycom_group_inallgroups'),
            2 => rex_i18n::msg('ycom_group_inonegroup'),
            3 => rex_i18n::msg('ycom_group_nogroups'),
        ]);
        $groupTypeSel->setSelected($ycomDefaults['ycom_group_type']);

        $rowGroupType = '<div class="form-group" id="uppy-ycom-group-type-row">'
            . '<label for="uppy-ycom-group-type">' . rex_escape($addon->i18n('uppy_ycom_group_type')) . '</label>'
            . $groupTypeSel->get()
            . '</div>';

        $ycomGroups = [];
        if (class_exists(rex_ycom_group::class)) {
            try {
                $ycomGroups = rex_ycom_group::getGroups();
            } catch (\Throwable $e) {
                $ycomGroups = [];
            }
        }

        $groupSel = new rex_select();
        $groupSel->setName('ycom_groups[]');
        $groupSel->setId('uppy-ycom-groups');
        $groupSel->setMultiple();
        $groupSel->setSize(min(8, max(3, count($ycomGroups))));
        $groupSel->setAttribute('class', 'form-control');
        foreach ($ycomGroups as $gid => $gname) {
            $groupSel->addOption($gname, (string) $gid);
        }
        if (!empty($ycomDefaults['ycom_groups'])) {
            $groupSel->setSelected($ycomDefaults['ycom_groups']);
        }

        $rowGroups = '<div class="form-group" id="uppy-ycom-groups-row">'
            . '<label for="uppy-ycom-groups">' . rex_escape($addon->i18n('uppy_ycom_groups')) . '</label>'
            . $groupSel->get()
            . '<p class="help-block text-muted">' . rex_escape($addon->i18n('uppy_ycom_groups_note')) . '</p>'
            . '</div>';
    }

    $ycomAuthHtml = '
<div class="panel panel-default" id="uppy-ycom-auth-panel" style="margin-top:15px;">
    <div class="panel-heading">
        <h3 class="panel-title">
            <a role="button" data-toggle="collapse" href="#uppy-ycom-auth-body" aria-expanded="false" aria-controls="uppy-ycom-auth-body" class="collapsed">
                <i class="rex-icon fa-solid fa-lock"></i> ' . rex_escape($addon->i18n('uppy_ycom_auth_panel_title')) . '
            </a>
        </h3>
    </div>
    <div id="uppy-ycom-auth-body" class="panel-collapse collapse" aria-expanded="false">
        <div class="panel-body">
            <p class="text-muted">' . rex_escape($addon->i18n('uppy_ycom_auth_panel_intro')) . '</p>
            ' . $rowAuth . $rowGroupType . $rowGroups . '
        </div>
    </div>
</div>';

    // Als Form-Element direkt nach der Kategorieauswahl einreihen
    $n = [];
    $n['field'] = $ycomAuthHtml;
    $formElements[] = $n;
}

// Uppy Widget
$n = [];
$n['label'] = '<label>Upload</label>';

if ($useCustomWidget) {
    // Custom Widget Variante (bekannt aus YForm)
    // WICHTIG: data-api-endpoint explizit auf den Backend-Endpoint setzen,
    // damit die Backend-Session (inkl. YCom-Media-Auth-Defaults) verwendet wird.
    // Default des Custom-Widgets wäre "/index.php?rex-api-call=uppy_uploader" (Frontend).
    $apiEndpoint = rex_url::backendController(['rex-api-call' => 'uppy_uploader'], false);

    $n['field'] = '
    <input type="hidden" 
           name="uppy_files" 
           id="uppy-upload-widget"
           class="uppy-upload-widget"
           data-api-endpoint="' . rex_escape($apiEndpoint) . '"
           data-category-id="' . $selectedCategory . '"
           data-max-files="' . rex_config::get('uppy', 'max_files', 30) . '"
           data-max-filesize="' . rex_config::get('uppy', 'max_filesize', 200) . '"
           data-allowed-types="' . rex_config::get('uppy', 'allowed_types', '*') . '"
           data-enable-chunks="' . (rex_config::get('uppy', 'enable_chunks', true) ? 'true' : 'false') . '"
           data-chunk-size="' . rex_config::get('uppy', 'chunk_size', 5) . '"
           data-enable-image-editor="true"
           data-enable-webcam="true"
           data-enable-sorting="false"
           data-allow-mediapool="false"
           data-uppy-opener-field="' . rex_escape($openerInputField) . '"
    />';
} else {
    // Standard Dashboard Variante
    $n['field'] = '
    <input type="file" 
           name="uppy_files" 
           id="uppy-upload-widget"
           data-widget="uppy"
           data-category-id="' . $selectedCategory . '"
           data-max-files="' . rex_config::get('uppy', 'max_files', 30) . '"
           data-max-filesize="' . rex_config::get('uppy', 'max_filesize', 200) . '"
           data-allowed-types="' . rex_config::get('uppy', 'allowed_types', '*') . '"
           data-enable-chunks="' . (rex_config::get('uppy', 'enable_chunks', true) ? 'true' : 'false') . '"
           data-chunk-size="' . rex_config::get('uppy', 'chunk_size', 5) . '"
           data-uppy-opener-field="' . rex_escape($openerInputField) . '"
    />';
}
$formElements[] = $n;

// JavaScript für selectMedia/selectMedialist Funktionen
if ($openerInputField) {
    $n = [];
    $n['field'] = '
<script>
// Opener-Input-Field für JavaScript verfügbar machen
rex.uppyOpenerInputField = ' . json_encode($openerInputField) . ';
rex.mediapoolOpenerInputField = ' . json_encode($openerInputField) . ';

// REDAXO-kompatible selectMedia/selectMedialist Funktionen
function selectMedia(filename, alt) {
    if (typeof alt === "undefined") alt = "";
    
    if (window.opener) {
        // Popup-Modus: Wie REDAXO Mediapool
        var event = opener.jQuery.Event("rex:selectMedia");
        opener.jQuery(window).trigger(event, [filename, alt]);
        
        if (!event.isDefaultPrevented()) {
            if (rex.mediapoolOpenerInputField) {
                var input = opener.document.getElementById(rex.mediapoolOpenerInputField);
                if (input !== null) {
                    input.value = filename;
                    opener.jQuery(input).trigger("change");
                    self.close();
                } else {
                    console.log("Media input field not found");
                }
            } else {
                self.close();
            }
        }
    } else {
        console.error("selectMedia: window.opener not available");
    }
}

function selectMedialist(filename) {
    if (window.opener) {
        // Popup-Modus: Wie REDAXO Mediapool
        if (rex.mediapoolOpenerInputField && 0 === rex.mediapoolOpenerInputField.indexOf("REX_MEDIALIST_")) {
            var openerId = rex.mediapoolOpenerInputField.slice("REX_MEDIALIST_".length);
            var medialist = "REX_MEDIALIST_SELECT_" + openerId;
            
            var source = opener.document.getElementById(medialist);
            if (source) {
                var option = opener.document.createElement("OPTION");
                option.text = filename;
                option.value = filename;
                source.options.add(option, source.options.length);
                opener.writeREXMedialist(openerId);
                console.log("Media added to list:", filename);
            } else {
                console.error("Medialist select not found:", medialist);
            }
        }
    } else {
        console.error("selectMedialist: window.opener not available");
    }
}
</script>';
    $formElements[] = $n;
}

// Kategorie-Auswahl Change-Handler
$n = [];
$n['field'] = '
<script>
// Initialsynchronisation: data-category-id mit dem aktuell gewählten Select-Wert abgleichen.
(function() {
    var sel = document.getElementById("uppy-category");
    var widget = document.getElementById("uppy-upload-widget");
    if (sel && widget && sel.value.length > 0) {
        widget.dataset.categoryId = sel.value;
    }
})();

document.getElementById("uppy-category").addEventListener("change", function() {
    var widget = document.getElementById("uppy-upload-widget");
    widget.dataset.categoryId = this.value;
    
    // Hinweis: Die Uppy-Instanz liest die Kategorie-ID beim Upload dynamisch aus dem data-Attribut.
    // Ein Re-Initialisieren ist daher nicht mehr notwendig und würde gewählte Dateien verwerfen.

    // Bei Custom-Widget (bekannt aus YForm) Instanz ebenfalls aktualisieren falls nötig
    if (widget.uppyWidget) {
        // Die Kategorie wird dort in getFiles() / fetchMetadata etc. genutzt
    }
});
</script>
';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

// Panel
$fragment = new rex_fragment();
$fragment->setVar('title', $addon->i18n('uppy_upload_title'));
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
