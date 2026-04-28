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
        ['ycom_auth_enabled', 'bool'],
        ['ycom_media_auth_defaults_enabled', 'bool'],
        ['auth_disable_checks', 'bool'],
        ['enable_resize', 'bool'],
        ['resize_width', 'int'],
        ['resize_height', 'int'],
        ['resize_quality', 'int'],
        ['fix_exif_orientation', 'bool'],
        ['enable_webcam', 'bool'],
        ['use_custom_widget', 'bool'],
        ['auto_cleanup_enabled', 'bool'],
        ['enable_debug_logging', 'bool']
    ]);
    
    foreach ($config as $key => $value) {
        rex_config::set('uppy', $key, $value);
    }
    
    echo rex_view::success($addon->i18n('uppy_save_success'));
}

// Generelle Warnung wenn Auth-Checks deaktiviert sind (Not-Aus aktiv)
if (rex_config::get('uppy', 'auth_disable_checks', 0)) {
    echo rex_view::warning(
        '<i class="rex-icon fa-exclamation-triangle"></i> ' .
        '<strong>ACHTUNG: ' . $addon->i18n('uppy_auth_disable_checks') . ' ist derzeit aktiv!</strong><br>' .
        $addon->i18n('uppy_auth_disable_checks_warning')
    );
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

// Allgemeine Einstellungen – Dateitypen als Inline-Checkboxen
$currentTypesValue = rex_config::get('uppy', 'allowed_types', 'image/jpeg,image/png,image/gif,image/webp,image/svg+xml,application/pdf,video/mp4,video/mpeg,video/quicktime,audio/mpeg,audio/wav');
$currentTypes = array_map('trim', explode(',', $currentTypesValue));

$typeGroups = [
    'Bilder' => [
        'image/jpeg' => 'JPEG',
        'image/png' => 'PNG',
        'image/gif' => 'GIF',
        'image/webp' => 'WebP',
        'image/svg+xml' => 'SVG',
        'image/tiff' => 'TIFF',
        'image/bmp' => 'BMP',
        'image/heic' => 'HEIC',
        'image/avif' => 'AVIF',
        'image/x-icon' => 'ICO',
    ],
    'Dokumente' => [
        'application/pdf' => 'PDF',
        'text/plain' => 'Text (.txt)',
        'text/csv' => 'CSV',
        'text/calendar' => 'iCalendar (.ics)',
        'application/rtf' => 'RTF',
        'application/json' => 'JSON',
        'text/xml' => 'XML',
        'text/vtt' => 'WebVTT (.vtt)',
        'text/srt' => 'Untertitel (.srt)',
        'application/epub+zip' => 'E-Book (.epub)',
        'application/postscript' => 'PostScript (.eps)',
    ],
    'Archive' => [
        'application/zip' => 'ZIP',
        'application/x-gzip' => 'GZIP (.gz)',
        'application/x-tar' => 'TAR',
        'application/x-rar-compressed' => 'RAR',
        'application/x-7z-compressed' => '7-Zip (.7z)',
    ],
    'Video' => [
        'video/mp4' => 'MP4',
        'video/mpeg' => 'MPEG',
        'video/quicktime' => 'QuickTime (.mov)',
        'video/webm' => 'WebM',
        'video/ogg' => 'OGG Video',
        'video/x-msvideo' => 'AVI',
        'video/x-matroska' => 'MKV',
    ],
    'Audio' => [
        'audio/mpeg' => 'MP3',
        'audio/wav' => 'WAV',
        'audio/ogg' => 'OGG Audio',
        'audio/aac' => 'AAC',
        'audio/midi' => 'MIDI',
        'audio/flac' => 'FLAC',
        'audio/mp4' => 'M4A',
        'audio/webm' => 'WebM Audio',
    ],
    'Office' => [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word (.docx)',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Excel (.xlsx)',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'PowerPoint (.pptx)',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.template' => 'Word-Vorlage (.dotx)',
        'application/vnd.openxmlformats-officedocument.presentationml.template' => 'PowerPoint-Vorlage (.potx)',
        'application/vnd.openxmlformats-officedocument.presentationml.slideshow' => 'PowerPoint-Show (.ppsx)',
        'application/msword' => 'Word (.doc)',
        'application/vnd.ms-excel' => 'Excel (.xls)',
        'application/vnd.ms-powerpoint' => 'PowerPoint (.ppt)',
    ],
    'OpenDocument' => [
        'application/vnd.oasis.opendocument.text' => 'Writer (.odt)',
        'application/vnd.oasis.opendocument.spreadsheet' => 'Calc (.ods)',
        'application/vnd.oasis.opendocument.presentation' => 'Impress (.odp)',
    ],
    'Fonts' => [
        'font/woff' => 'WOFF',
        'font/woff2' => 'WOFF2',
        'font/ttf' => 'TrueType (.ttf)',
        'font/otf' => 'OpenType (.otf)',
    ],
];

// Alle bekannten MIME-Types sammeln
$knownTypes = [];
foreach ($typeGroups as $types) {
    foreach ($types as $mime => $label) {
        $knownTypes[] = $mime;
    }
}
// Unbekannte/eigene Types extrahieren
$customTypes = array_filter($currentTypes, static function ($t) use ($knownTypes) {
    return '' !== $t && !in_array($t, $knownTypes, true);
});

// Checkbox-HTML aufbauen
$fieldHtml = '<textarea class="form-control" id="uppy-allowed-types" name="config[allowed_types]" rows="5" readonly style="margin-bottom:10px; cursor:default;">' . rex_escape($currentTypesValue) . '</textarea>';

$fieldHtml .= '<div class="panel-group" id="uppy-types-accordion" role="tablist">';

$panelIndex = 0;
foreach ($typeGroups as $groupName => $types) {
    $panelId = 'uppy-type-panel-' . $panelIndex;
    $collapseId = 'uppy-type-collapse-' . $panelIndex;

    // Zähle aktive Types in dieser Gruppe
    $activeCount = 0;
    foreach ($types as $mime => $label) {
        if (in_array($mime, $currentTypes, true)) {
            ++$activeCount;
        }
    }
    $badge = $activeCount > 0 ? ' <span class="badge">' . $activeCount . '</span>' : '';

    $fieldHtml .= '<div class="panel panel-default">';
    $fieldHtml .= '<div class="panel-heading" role="tab" id="' . $panelId . '">';
    $fieldHtml .= '<h4 class="panel-title"><a role="button" data-toggle="collapse" data-parent="#uppy-types-accordion" href="#' . $collapseId . '" aria-expanded="false" aria-controls="' . $collapseId . '" style="text-decoration:none;">';
    $fieldHtml .= rex_escape($groupName) . $badge;
    $fieldHtml .= '</a></h4></div>';
    $fieldHtml .= '<div id="' . $collapseId . '" class="panel-collapse collapse" role="tabpanel" aria-labelledby="' . $panelId . '">';
    $fieldHtml .= '<div class="panel-body"><div class="row">';

    foreach ($types as $mime => $label) {
        $checked = in_array($mime, $currentTypes, true) ? ' checked' : '';
        $fieldHtml .= '<div class="col-sm-4 col-md-3"><div class="checkbox" style="margin:2px 0;"><label><input type="checkbox" class="uppy-type-cb" value="' . rex_escape($mime) . '"' . $checked . '> ' . rex_escape($label) . '</label></div></div>';
    }

    $fieldHtml .= '</div></div></div></div>';
    ++$panelIndex;
}

$fieldHtml .= '</div>'; // panel-group

$fieldHtml .= '<div style="margin-top: 8px;">';
$fieldHtml .= '<label for="uppy-custom-types"><small>Eigene MIME-Types (kommagetrennt):</small></label>';
$fieldHtml .= '<input class="form-control" type="text" id="uppy-custom-types" placeholder="z.B. text/calendar,application/xml" value="' . rex_escape(implode(', ', $customTypes)) . '" />';
$fieldHtml .= '</div>';

$n = [];
$n['label'] = '<label>' . $addon->i18n('uppy_allowed_types') . '</label>';
$n['field'] = $fieldHtml;
$n['note'] = 'Dateitypen auswählen oder eigene MIME-Types eintragen. Änderungen werden automatisch übernommen.';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="uppy-max-filesize">' . $addon->i18n('uppy_max_filesize') . '</label>';
$n['field'] = '<input class="form-control" type="number" id="uppy-max-filesize" name="config[max_filesize]" value="' . rex_config::get('uppy', 'max_filesize', 200) . '" min="1" max="2000" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="uppy-max-files">' . $addon->i18n('uppy_max_files') . '</label>';
$n['field'] = '<input class="form-control" type="number" id="uppy-max-files" name="config[max_files]" value="' . rex_config::get('uppy', 'max_files', 30) . '" min="0" max="100" />';
$n['note'] = '0 = unbegrenzt';
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

// YCom Auth (nur wenn YCom verfügbar)
if (rex_addon::get('ycom')->isAvailable()) {
    $n = [];
    $n['label'] = '<label for="uppy-ycom-auth">' . $addon->i18n('uppy_ycom_auth_enabled') . '</label>';
    $n['field'] = '<input type="checkbox" id="uppy-ycom-auth" name="config[ycom_auth_enabled]" value="1" ' . (rex_config::get('uppy', 'ycom_auth_enabled', 0) ? 'checked' : '') . ' />';
    $n['note'] = $addon->i18n('uppy_ycom_auth_notice');
    $formElements[] = $n;

    // Optionale UI auf der Upload-Seite zum Vorbelegen der YCom-Medien-Berechtigungen
    if (rex_plugin::get('ycom', 'media_auth')->isAvailable()) {
        $n = [];
        $n['label'] = '<label for="uppy-ycom-media-auth-defaults">' . $addon->i18n('uppy_ycom_media_auth_defaults_enabled') . '</label>';
        $n['field'] = '<input type="checkbox" id="uppy-ycom-media-auth-defaults" name="config[ycom_media_auth_defaults_enabled]" value="1" ' . (rex_config::get('uppy', 'ycom_media_auth_defaults_enabled', 0) ? 'checked' : '') . ' />';
        $n['note'] = $addon->i18n('uppy_ycom_media_auth_defaults_enabled_notice');
        $formElements[] = $n;
    }
}

// EXPERTEN: Auth Checks deaktivieren
$authChecksDisabled = rex_config::get('uppy', 'auth_disable_checks', 0);
$containerStyle = $authChecksDisabled
    ? 'border: 2px solid #d9534f; padding: 15px; background-color: #fff1f0; border-radius: 3px;' 
    : 'border: 2px solid #3c763d; padding: 15px; background-color: #dff0d8; border-radius: 3px;';

$statusText = $authChecksDisabled
    ? '<span style="color: #d9534f; font-weight: bold;"><i class="rex-icon fa-exclamation-triangle"></i> ' . $addon->i18n('uppy_auth_disable_checks_warning') . '</span>'
    : '<span style="color: #3c763d; font-weight: bold;"><i class="rex-icon fa-check-circle"></i> Authentifizierungs-Prüfungen sind aktiv (Sicher/Empfohlen)</span>';

$n = [];
$n['label'] = '<label for="uppy-auth-disable">' . $addon->i18n('uppy_auth_disable_checks') . '</label>';
$n['field'] = '<div style="' . $containerStyle . '">';
$n['field'] .= '<input type="checkbox" id="uppy-auth-disable" name="config[auth_disable_checks]" value="1" ' . ($authChecksDisabled ? 'checked' : '') . ' />';
$n['field'] .= '<span style="margin-left: 10px;">' . $statusText . '</span>';
$n['field'] .= '<div class="text-muted" style="margin-top: 5px;">' . $addon->i18n('uppy_security_info') . '</div>';
$n['field'] .= '</div>';
$n['note'] = $addon->i18n('uppy_auth_disable_checks_notice');
$formElements[] = $n;

// Custom Widget Modus
$n = [];
$n['label'] = '<label for="uppy-use-custom-widget">Custom-Widget Modus</label>';
$n['field'] = '<input type="checkbox" id="uppy-use-custom-widget" name="config[use_custom_widget]" value="1" ' . (rex_config::get('uppy', 'use_custom_widget', false) ? 'checked' : '') . ' />';
$n['note'] = 'Verwendet das Custom-Widget (bekannt aus YForm) auch auf der Upload-Seite und im Medienpool. Dies ermöglicht das nachträgliche Bearbeiten von Metadaten direkt nach dem Upload.';
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
$n['field'] = '<div class="alert alert-danger" style="margin-bottom: 10px; font-weight: bold;"><i class="rex-icon fa-exclamation-triangle"></i> ' . $addon->i18n('uppy_api_token_security_warning') . '</div>';
$n['field'] .= '<input class="form-control" type="text" value="' . rex_escape($token) . '" readonly onclick="this.select()" />';
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
