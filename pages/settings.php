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
        ['enable_webcam', 'bool']
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
$n['field'] = '<input class="form-control" type="text" id="uppy-allowed-types" name="config[allowed_types]" value="' . rex_escape(rex_config::get('uppy', 'allowed_types', 'image/jpeg,image/png,image/gif,image/webp,image/svg+xml,video/*,application/pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx')) . '" />';
$n['note'] = 'MIME-Types oder Dateiendungen mit Punkt, z.B. <code>image/*,video/*,.pdf</code> oder explizit <code>image/jpeg,image/png,image/webp,image/svg+xml</code>';
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

// Chunk-Upload (TUS Protocol - aktuell deaktiviert wegen fehlender Backend-Implementierung)
$n = [];
$n['label'] = '<label for="uppy-enable-chunks">' . $addon->i18n('uppy_enable_chunks') . '</label>';
$n['field'] = '<input type="checkbox" id="uppy-enable-chunks" name="config[enable_chunks]" value="1" disabled ' . (rex_config::get('uppy', 'enable_chunks', false) ? 'checked' : '') . ' />';
$n['note'] = '<span class="text-muted">Chunked Upload ist aktuell deaktiviert. Verwenden Sie PHP upload_max_filesize für große Dateien.</span>';
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
$n['label'] = '<label for="uppy-enable-webcam">Webcam aktivieren</label>';
$n['field'] = '<input type="checkbox" id="uppy-enable-webcam" name="config[enable_webcam]" value="1" ' . (rex_config::get('uppy', 'enable_webcam', false) ? 'checked' : '') . ' />';
$n['note'] = 'Ermöglicht die Aufnahme von Fotos über die Webcam direkt im Uploader';
$formElements[] = $n;

// Mediapool ersetzen
$n = [];
$n['label'] = '<label for="uppy-replace-mediapool">' . $addon->i18n('uppy_replace_mediapool') . '</label>';
$n['field'] = '<input type="checkbox" id="uppy-replace-mediapool" name="config[replace_mediapool]" value="1" ' . (rex_config::get('uppy', 'replace_mediapool', false) ? 'checked' : '') . ' />';
$n['note'] = $addon->i18n('uppy_replace_mediapool_notice');
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
