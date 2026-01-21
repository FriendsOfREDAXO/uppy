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
$catsSel = new rex_media_category_select();
$catsSel->setStyle('class="form-control"');
$catsSel->setSize(1);
$catsSel->setName('category_id');
$catsSel->setId('uppy-category');
$catsSel->setAttribute('class', 'selectpicker form-control');
$catsSel->setAttribute('data-live-search', 'true');
$catsSel->setSelected(rex_config::get('uppy', 'category_id', 0));

// "Keine Kategorie" Option wenn Berechtigung vorhanden
if (rex::requireUser()->getComplexPerm('media')->hasAll()) {
    $catsSel->addOption(rex_i18n::msg('pool_kats_no'), '0');
}

$n['field'] = $catsSel->get();
$formElements[] = $n;

// Uppy Widget
$n = [];
$n['label'] = '<label>Upload</label>';
$n['field'] = '
<input type="file" 
       name="uppy_files" 
       id="uppy-upload-widget"
       data-widget="uppy"
       data-category-id="' . rex_config::get('uppy', 'category_id', 0) . '"
       data-max-files="' . rex_config::get('uppy', 'max_files', 30) . '"
       data-max-filesize="' . rex_config::get('uppy', 'max_filesize', 200) . '"
       data-allowed-types="' . rex_config::get('uppy', 'allowed_types', 'image/jpeg,image/png,image/gif,image/webp,image/svg+xml,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,video/mp4,video/mpeg,video/quicktime') . '"
       data-enable-chunks="' . (rex_config::get('uppy', 'enable_chunks', true) ? 'true' : 'false') . '"
       data-chunk-size="' . rex_config::get('uppy', 'chunk_size', 5) . '"
       data-uppy-opener-field="' . rex_escape($openerInputField) . '"
/>';
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
document.getElementById("uppy-category").addEventListener("change", function() {
    var widget = document.getElementById("uppy-upload-widget");
    widget.dataset.categoryId = this.value;
    
    // Hinweis: Die Uppy-Instanz liest die Kategorie-ID beim Upload dynamisch aus dem data-Attribut.
    // Ein Re-Initialisieren ist daher nicht mehr notwendig und würde gewählte Dateien verwerfen.
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
