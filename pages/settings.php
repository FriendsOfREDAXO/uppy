<?php

$addon = rex_addon::get('uppy');

// POST-Daten verarbeiten
if (rex_post('config-submit', 'boolean')) {
    $config = rex_post('config', [
        ['allowed_types', 'string'],
        ['max_filesize', 'int'],
        ['max_files', 'int'],
        ['category_id', 'int'],
        ['enable_chunks', 'bool'],
        ['chunk_size', 'int'],
        ['replace_mediapool', 'bool'],
        ['enable_resize', 'bool'],
        ['resize_width', 'int'],
        ['resize_height', 'int'],
        ['resize_quality', 'int'],
        ['fix_exif_orientation', 'bool'],
        ['enable_webcam', 'bool'],
        ['auto_cleanup_enabled', 'bool'],
        ['enable_debug_logging', 'bool']
    ]);
    
    foreach ($config as $key => $value) {
        rex_config::set('uppy', $key, $value);
    }
    
    echo rex_view::success($addon->i18n('uppy_save_success'));
}


// Kategorien für Select laden
$categories = [];
$sql = rex_sql::factory();
$sql->setQuery('SELECT id, name, path FROM ' . rex::getTable('media_category') . ' ORDER BY path');

while ($sql->hasNext()) {
    $catId = (int) $sql->getValue('id');
    $catName = $sql->getValue('name');
    $catPath = $sql->getValue('path');
    
    // Pfad in lesbarer Form
    $pathParts = array_filter(explode('|', $catPath));
    $indent = str_repeat('&nbsp;&nbsp;', max(0, count($pathParts) - 1));
    
    $categories[$catId] = $indent . $catName;
    $sql->next();
}

$content = '';
$formElements = [];

// Allgemeine Einstellungen
$n = [];
$n['label'] = '<label for="uppy-allowed-types">' . $addon->i18n('uppy_allowed_types') . '</label>';
$n['field'] = '
<div class="input-group">
    <input class="form-control" type="text" id="uppy-allowed-types" name="config[allowed_types]" value="' . rex_escape(rex_config::get('uppy', 'allowed_types', 'image/jpeg,image/png,image/gif,image/webp,image/svg+xml,application/pdf,video/mp4,video/mpeg,video/quicktime,audio/mpeg,audio/wav')) . '" />
    <span class="input-group-btn">
        <button class="btn btn-default" type="button" id="uppy-select-types-btn" data-toggle="modal" data-target="#uppy-types-modal">
            <i class="rex-icon rex-icon-add"></i> ' . $addon->i18n('uppy_select_types') . '
        </button>
    </span>
</div>

<!-- Modal für Dateitypen-Auswahl -->
<div class="modal fade" id="uppy-types-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">' . $addon->i18n('uppy_select_types') . '</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Bilder</h5>
                        <div class="checkbox"><label><input type="checkbox" class="uppy-type-cb" value="image/jpeg"> JPEG (image/jpeg)</label></div>
                        <div class="checkbox"><label><input type="checkbox" class="uppy-type-cb" value="image/png"> PNG (image/png)</label></div>
                        <div class="checkbox"><label><input type="checkbox" class="uppy-type-cb" value="image/gif"> GIF (image/gif)</label></div>
                        <div class="checkbox"><label><input type="checkbox" class="uppy-type-cb" value="image/webp"> WebP (image/webp)</label></div>
                        <div class="checkbox"><label><input type="checkbox" class="uppy-type-cb" value="image/svg+xml"> SVG (image/svg+xml)</label></div>
                        <div class="checkbox"><label><input type="checkbox" class="uppy-type-cb" value="image/tiff"> TIFF (image/tiff)</label></div>
                        <div class="checkbox"><label><input type="checkbox" class="uppy-type-cb" value="image/bmp"> BMP (image/bmp)</label></div>
                        <div class="checkbox"><label><input type="checkbox" class="uppy-type-cb" value="image/heic"> HEIC (image/heic)</label></div>
                        
                        <h5>Dokumente</h5>
                        <div class="checkbox"><label><input type="checkbox" class="uppy-type-cb" value="application/pdf"> PDF (application/pdf)</label></div>
                        <div class="checkbox"><label><input type="checkbox" class="uppy-type-cb" value="text/plain"> Text (text/plain)</label></div>
                        <div class="checkbox"><label><input type="checkbox" class="uppy-type-cb" value="application/rtf"> RTF (application/rtf)</label></div>
                        <div class="checkbox"><label><input type="checkbox" class="uppy-type-cb" value="application/zip"> ZIP (application/zip)</label></div>
                    </div>
                    <div class="col-md-6">
                        <h5>Video</h5>
                        <div class="checkbox"><label><input type="checkbox" class="uppy-type-cb" value="video/mp4"> MP4 (video/mp4)</label></div>
                        <div class="checkbox"><label><input type="checkbox" class="uppy-type-cb" value="video/mpeg"> MPEG (video/mpeg)</label></div>
                        <div class="checkbox"><label><input type="checkbox" class="uppy-type-cb" value="video/quicktime"> QuickTime (video/quicktime)</label></div>
                        <div class="checkbox"><label><input type="checkbox" class="uppy-type-cb" value="video/webm"> WebM (video/webm)</label></div>
                        <div class="checkbox"><label><input type="checkbox" class="uppy-type-cb" value="video/ogg"> OGG (video/ogg)</label></div>
                        
                        <h5>Audio</h5>
                        <div class="checkbox"><label><input type="checkbox" class="uppy-type-cb" value="audio/mpeg"> MP3 (audio/mpeg)</label></div>
                        <div class="checkbox"><label><input type="checkbox" class="uppy-type-cb" value="audio/wav"> WAV (audio/wav)</label></div>
                        <div class="checkbox"><label><input type="checkbox" class="uppy-type-cb" value="audio/ogg"> OGG (audio/ogg)</label></div>
                        <div class="checkbox"><label><input type="checkbox" class="uppy-type-cb" value="audio/aac"> AAC (audio/aac)</label></div>
                        <div class="checkbox"><label><input type="checkbox" class="uppy-type-cb" value="audio/midi"> MIDI (audio/midi)</label></div>
                    </div>
                </div>
                <div class="row" style="margin-top: 15px; border-top: 1px solid #eee; padding-top: 10px;">
                    <div class="col-md-12">
                        <h5>Office (Modern)</h5>
                        <div class="checkbox"><label><input type="checkbox" class="uppy-type-cb" value="application/vnd.openxmlformats-officedocument.wordprocessingml.document"> Word .docx</label></div>
                        <div class="checkbox"><label><input type="checkbox" class="uppy-type-cb" value="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"> Excel .xlsx</label></div>
                        <div class="checkbox"><label><input type="checkbox" class="uppy-type-cb" value="application/vnd.openxmlformats-officedocument.presentationml.presentation"> PowerPoint .pptx</label></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">' . $addon->i18n('uppy_cancel') . '</button>
                <button type="button" class="btn btn-primary" id="uppy-apply-types">' . $addon->i18n('uppy_apply') . '</button>
            </div>
        </div>
    </div>
</div>

<script nonce="' . rex_response::getNonce() . '">
(function($) {
    // Modal öffnen und Checkboxen basierend auf Input setzen
    $("#uppy-types-modal").on("show.bs.modal", function() {
        var currentTypes = $("#uppy-allowed-types").val().split(",");
        currentTypes = currentTypes.map(function(t) { return t.trim(); });
        
        $(".uppy-type-cb").prop("checked", false);
        
        $(".uppy-type-cb").each(function() {
            if (currentTypes.indexOf($(this).val()) > -1) {
                $(this).prop("checked", true);
            }
        });
    });
    
    // Auswahl übernehmen
    $("#uppy-apply-types").on("click", function() {
        var selectedTypes = [];
        $(".uppy-type-cb:checked").each(function() {
            selectedTypes.push($(this).val());
        });
        
        $("#uppy-allowed-types").val(selectedTypes.join(","));
        $("#uppy-types-modal").modal("hide");
    });
})(jQuery);
</script>
';
$n['note'] = 'MIME-Types (kommagetrennt), z.B. <code>image/jpeg,image/png,image/webp,image/svg+xml,application/pdf</code>';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="uppy-max-filesize">' . $addon->i18n('uppy_max_filesize') . '</label>';
$n['field'] = '<input class="form-control" type="number" id="uppy-max-filesize" name="config[max_filesize]" value="' . rex_config::get('uppy', 'max_filesize', 200) . '" min="1" max="2000" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="uppy-max-files">' . $addon->i18n('uppy_max_files') . '</label>';
$n['field'] = '<input class="form-control" type="number" id="uppy-max-files" name="config[max_files]" value="' . rex_config::get('uppy', 'max_files', 30) . '" min="1" max="100" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="uppy-category">' . $addon->i18n('uppy_category_id') . '</label>';
$select = new rex_select();
$select->setName('config[category_id]');
$select->setId('uppy-category');
$select->setStyle('class="form-control"');
$select->addOption('--- ' . rex_i18n::msg('pool_kats_no') . ' ---', 0);
foreach ($categories as $id => $name) {
    $select->addOption($name, $id);
}
$select->setSelected(rex_config::get('uppy', 'category_id', 0));
$n['field'] = $select->get();
$formElements[] = $n;

// Chunk-Upload (TUS Protocol)
$n = [];
$n['label'] = '<label for="uppy-enable-chunks">' . $addon->i18n('uppy_enable_chunks') . '</label>';
$n['field'] = '<input type="checkbox" id="uppy-enable-chunks" name="config[enable_chunks]" value="1" ' . (rex_config::get('uppy', 'enable_chunks', true) ? 'checked' : '') . ' />';
$n['note'] = '<span class="text-muted">Ermöglicht den Upload sehr großer Dateien durch Aufteilung in kleine Pakete (Chunks). Umgeht das PHP upload_max_filesize Limit.</span>';
$formElements[] = $n;

// Bildoptimierung
$n = [];
$n['label'] = '<label for="uppy-enable-resize">' . $addon->i18n('uppy_enable_resize') . '</label>';
$n['field'] = '<input type="checkbox" id="uppy-enable-resize" name="config[enable_resize]" value="1" ' . (rex_config::get('uppy', 'enable_resize', true) ? 'checked' : '') . ' />';
$n['note'] = $addon->i18n('uppy_enable_resize_notice');
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="uppy-resize-width">' . $addon->i18n('uppy_resize_width') . '</label>';
$n['field'] = '<input class="form-control" type="number" id="uppy-resize-width" name="config[resize_width]" value="' . rex_config::get('uppy', 'resize_width', 2000) . '" min="100" max="10000" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="uppy-resize-height">' . $addon->i18n('uppy_resize_height') . '</label>';
$n['field'] = '<input class="form-control" type="number" id="uppy-resize-height" name="config[resize_height]" value="' . rex_config::get('uppy', 'resize_height', 2000) . '" min="100" max="10000" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="uppy-resize-quality">' . $addon->i18n('uppy_resize_quality') . '</label>';
$n['field'] = '<input class="form-control" type="number" id="uppy-resize-quality" name="config[resize_quality]" value="' . rex_config::get('uppy', 'resize_quality', 85) . '" min="1" max="100" />';
$n['note'] = $addon->i18n('uppy_resize_quality_notice');
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="uppy-fix-exif">' . $addon->i18n('uppy_fix_exif_orientation') . '</label>';
$n['field'] = '<input type="checkbox" id="uppy-fix-exif" name="config[fix_exif_orientation]" value="1" ' . (rex_config::get('uppy', 'fix_exif_orientation', true) ? 'checked' : '') . ' />';
$n['note'] = $addon->i18n('uppy_fix_exif_orientation_notice');
$formElements[] = $n;

// Webcam aktivieren
$n = [];
$n['label'] = '<label for="uppy-enable-webcam">' . $addon->i18n('uppy_enable_webcam') . '</label>';
$n['field'] = '<input type="checkbox" id="uppy-enable-webcam" name="config[enable_webcam]" value="1" ' . (rex_config::get('uppy', 'enable_webcam', false) ? 'checked' : '') . ' />';
$n['note'] = $addon->i18n('uppy_enable_webcam_notice');
$formElements[] = $n;

// Mediapool ersetzen
$n = [];
$n['label'] = '<label for="uppy-replace-mediapool">' . $addon->i18n('uppy_replace_mediapool') . '</label>';
$n['field'] = '<input type="checkbox" id="uppy-replace-mediapool" name="config[replace_mediapool]" value="1" ' . (rex_config::get('uppy', 'replace_mediapool', false) ? 'checked' : '') . ' />';
$n['note'] = $addon->i18n('uppy_replace_mediapool_notice');
$formElements[] = $n;

// YForm Auto-Cleanup (nur wenn YForm verfügbar)
if (rex_addon::get('yform')->isAvailable()) {
    $n = [];
    $n['label'] = '<label for="uppy-auto-cleanup">' . $addon->i18n('uppy_auto_cleanup_enabled') . '</label>';
    
    $select = new rex_select();
    $select->setId('uppy-auto-cleanup');
    $select->setName('config[auto_cleanup_enabled]');
    $select->setSize(1);
    $select->addOption($addon->i18n('uppy_auto_cleanup_disabled'), '0');
    $select->addOption($addon->i18n('uppy_auto_cleanup_enabled_label'), '1');
    $select->setSelected(rex_config::get('uppy', 'auto_cleanup_enabled', 0));
    
    $n['field'] = $select->get();
    $n['note'] = '<span class="text-warning"><i class="rex-icon fa-exclamation-triangle"></i> <strong>' . $addon->i18n('uppy_auto_cleanup_warning') . '</strong> ' . $addon->i18n('uppy_auto_cleanup_notice') . '</span>';
    $formElements[] = $n;
}

// Debug-Logging aktivieren
$n = [];
$n['label'] = '<label>' . $addon->i18n('uppy_enable_debug_logging') . '</label>';
$n['field'] = '<input type="checkbox" name="config[enable_debug_logging]" value="1" ' . (rex_config::get('uppy', 'enable_debug_logging', 0) ? 'checked="checked"' : '') . ' />';
$n['note'] = $addon->i18n('uppy_enable_debug_logging_notice');
$formElements[] = $n;

// API-Token (nur anzeigen)
$n = [];
$n['label'] = '<label>' . $addon->i18n('uppy_api_token') . '</label>';
$token = rex_config::get('uppy', 'api_token', '');
$n['field'] = '<input class="form-control" type="text" value="' . rex_escape($token) . '" readonly onclick="this.select()" />';
$n['note'] = $addon->i18n('uppy_api_token_notice');
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

// Submit-Button
$formElements = [];
$n = [];
$n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="config-submit" value="1">' . rex_i18n::msg('form_save') . '</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

// Form
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit');
$fragment->setVar('title', $addon->i18n('uppy_settings_general'));
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

$content = '
<form action="' . rex_url::currentBackendPage() . '" method="post">
    ' . $content . '
</form>
';

echo $content;
