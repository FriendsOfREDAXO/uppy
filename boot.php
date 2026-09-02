<?php

/**
 * Uppy Uploader AddOn für REDAXO
 * 
 * @package uppy
 * @author Friends Of REDAXO
 */

$addon = rex_addon::get('uppy');

// API-Klassen explizit registrieren (ab REDAXO 5.17)
rex_api_function::register('uppy_uploader', FriendsOfRedaxo\Uppy\UppyUploadHandler::class);
rex_api_function::register('uppy_metadata', FriendsOfRedaxo\Uppy\UppyMetadataHandler::class);
rex_api_function::register('uppy_ycom_auth', rex_api_uppy_ycom_auth::class);
rex_api_function::register('uppy_file_access', rex_api_uppy_file_access::class);

// Backend-Permission für die Verwaltung der YCom-Media-Auth-Defaults beim Upload
rex_perm::register(\FriendsOfRedaxo\Uppy\YcomAuthSettings::PERM, $addon->i18n('uppy_perm_ycom_media_auth'));

// YForm Integration - registrieren wenn YForm verfügbar ist
rex_extension::register('PACKAGES_INCLUDED', function() use ($addon) {
    if (rex_addon::get('yform')->isAvailable()) {
        rex_yform::addTemplatePath($addon->getPath('ytemplates'));
        
        // MEDIA_IS_IN_USE Extension registrieren (verhindert Löschen verwendeter Dateien)
        FriendsOfRedaxo\Uppy\UppyMediaCleanup::register();
    }
});

// Mediapool MIME-Types erweitern für Typen, die Uppy erlaubt aber der Mediapool nicht kennt
if (rex_addon::get('mediapool')->isAvailable()) {
    // Mapping: MIME-Type => Dateiendung(en) + mögliche alternative MIME-Types
    $uppyMimeMap = [
        // Bilder
        'image/jpeg' => ['ext' => 'jpg', 'alt' => ['image/pjpeg']],
        'image/png' => ['ext' => 'png'],
        'image/gif' => ['ext' => 'gif'],
        'image/webp' => ['ext' => 'webp'],
        'image/svg+xml' => ['ext' => 'svg'],
        'image/tiff' => ['ext' => 'tiff'],
        'image/bmp' => ['ext' => 'bmp'],
        'image/heic' => ['ext' => 'heic'],
        'image/avif' => ['ext' => 'avif'],
        'image/x-icon' => ['ext' => 'ico', 'alt' => ['image/vnd.microsoft.icon']],

        // Dokumente
        'application/pdf' => ['ext' => 'pdf'],
        'text/plain' => ['ext' => 'txt', 'alt' => ['application/octet-stream']],
        'text/csv' => ['ext' => 'csv', 'alt' => ['text/plain', 'application/octet-stream']],
        'text/calendar' => ['ext' => 'ics', 'alt' => ['text/plain', 'application/octet-stream']],
        'application/rtf' => ['ext' => 'rtf'],
        'application/json' => ['ext' => 'json', 'alt' => ['text/plain']],
        'text/xml' => ['ext' => 'xml', 'alt' => ['application/xml']],
        'text/vtt' => ['ext' => 'vtt'],
        'text/srt' => ['ext' => 'srt', 'alt' => ['text/plain']],
        'text/css' => ['ext' => 'css', 'alt' => ['text/plain']],

        // Archive
        'application/zip' => ['ext' => 'zip', 'alt' => ['application/x-zip-compressed']],
        'application/x-gzip' => ['ext' => 'gz', 'alt' => ['application/gzip']],
        'application/x-tar' => ['ext' => 'tar'],
        'application/x-rar-compressed' => ['ext' => 'rar', 'alt' => ['application/vnd.rar']],
        'application/x-7z-compressed' => ['ext' => '7z'],

        // Video
        'video/mp4' => ['ext' => 'mp4'],
        'video/mpeg' => ['ext' => 'mpeg'],
        'video/quicktime' => ['ext' => 'mov'],
        'video/webm' => ['ext' => 'webm'],
        'video/ogg' => ['ext' => 'ogv'],
        'video/x-msvideo' => ['ext' => 'avi'],
        'video/x-matroska' => ['ext' => 'mkv'],

        // Audio
        'audio/mpeg' => ['ext' => 'mp3'],
        'audio/wav' => ['ext' => 'wav', 'alt' => ['audio/x-wav']],
        'audio/ogg' => ['ext' => 'ogg'],
        'audio/aac' => ['ext' => 'aac'],
        'audio/midi' => ['ext' => 'midi', 'alt' => ['audio/x-midi']],
        'audio/flac' => ['ext' => 'flac'],
        'audio/mp4' => ['ext' => 'm4a'],
        'audio/webm' => ['ext' => 'weba'],

        // Office (Modern)
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['ext' => 'docx', 'alt' => ['application/octet-stream', 'application/encrypted']],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['ext' => 'xlsx', 'alt' => ['application/octet-stream', 'application/encrypted']],
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['ext' => 'pptx'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.template' => ['ext' => 'dotx', 'alt' => ['application/octet-stream']],
        'application/vnd.openxmlformats-officedocument.presentationml.template' => ['ext' => 'potx'],
        'application/vnd.openxmlformats-officedocument.presentationml.slideshow' => ['ext' => 'ppsx'],

        // Office (Legacy)
        'application/msword' => ['ext' => 'doc', 'alt' => ['application/octet-stream', 'application/encrypted']],
        'application/vnd.ms-excel' => ['ext' => 'xls', 'alt' => ['application/octet-stream', 'application/encrypted']],
        'application/vnd.ms-powerpoint' => ['ext' => 'ppt'],

        // OpenDocument
        'application/vnd.oasis.opendocument.text' => ['ext' => 'odt'],
        'application/vnd.oasis.opendocument.spreadsheet' => ['ext' => 'ods'],
        'application/vnd.oasis.opendocument.presentation' => ['ext' => 'odp'],

        // Fonts
        'font/woff' => ['ext' => 'woff', 'alt' => ['application/font-woff']],
        'font/woff2' => ['ext' => 'woff2'],
        'font/ttf' => ['ext' => 'ttf', 'alt' => ['application/x-font-ttf']],
        'font/otf' => ['ext' => 'otf', 'alt' => ['application/x-font-opentype']],

        // Sonstige
        'application/postscript' => ['ext' => 'eps'],
        'application/epub+zip' => ['ext' => 'epub'],
    ];

    $allowedTypes = rex_config::get('uppy', 'allowed_types', '');
    if ('' !== $allowedTypes) {
        $configuredTypes = array_map('trim', explode(',', $allowedTypes));
        $mediapoolMimes = rex_addon::get('mediapool')->getProperty('allowed_mime_types', []);
        $changed = false;

        foreach ($configuredTypes as $mime) {
            if ('' === $mime || str_ends_with($mime, '/*') || !str_contains($mime, '/')) {
                // Wildcards (z.B. image/*) und leere/ungültige Einträge lassen sich nicht auf
                // eine einzelne Dateiendung abbilden und werden hier übersprungen.
                continue;
            }

            if (isset($uppyMimeMap[$mime])) {
                $ext = $uppyMimeMap[$mime]['ext'];
                $alt = $uppyMimeMap[$mime]['alt'] ?? [];
            } else {
                // Unbekannter, in den Settings frei eingetragener MIME-Type: Dateiendung
                // heuristisch aus dem Subtype ableiten (text/css -> css,
                // application/x-foo -> foo, image/svg+xml -> svg).
                $subtype = substr($mime, strpos($mime, '/') + 1);
                $subtype = preg_replace('#^(x-|vnd\.)#', '', $subtype);
                $ext = mb_strtolower(trim(strtok($subtype, '+;')));
                $alt = ['text/plain', 'application/octet-stream'];
            }

            if ('' !== $ext && !isset($mediapoolMimes[$ext])) {
                $mediapoolMimes[$ext] = array_merge([$mime], $alt);
                $changed = true;
            }
        }

        if ($changed) {
            rex_addon::get('mediapool')->setProperty('allowed_mime_types', $mediapoolMimes);
        }
    }
}

// Backend-Integration
if (rex::isBackend() && rex::getUser()) {
    // Assets nur einmalig laden
    static $uppyScriptsLoaded = false;
    
    if (!$uppyScriptsLoaded) {
        // CSS Bundle (Uppy 5.0 + Custom Styles)
        $cssVersion = $addon->getVersion() . '.' . filemtime($addon->getPath('assets/dist/uppy-backend-bundle.css'));
        rex_view::addCssFile($addon->getAssetsUrl('dist/uppy-backend-bundle.css?v=' . $cssVersion));
        
        // Konfiguration als JSON für JavaScript verfügbar machen
        rex_view::setJsProperty('uppy_config', [
            'enable_resize' => rex_config::get('uppy', 'enable_resize', true),
            'resize_width' => rex_config::get('uppy', 'resize_width', 2000),
            'resize_height' => rex_config::get('uppy', 'resize_height', 2000),
            'resize_quality' => rex_config::get('uppy', 'resize_quality', 85),
            'fix_exif_orientation' => rex_config::get('uppy', 'fix_exif_orientation', true),
            'enable_webcam' => rex_config::get('uppy', 'enable_webcam', false),
            'enable_image_editor' => rex_config::get('uppy', 'enable_image_editor', false),
            'crop_ratios' => rex_config::get('uppy', 'crop_ratios', '1:1,16:9,4:3,3:2,free'),
            'enable_chunks' => rex_config::get('uppy', 'enable_chunks', true),
            'chunk_size' => rex_config::get('uppy', 'chunk_size', 5)
        ]);
        
        // Backend Dashboard Bundle
        $version = $addon->getVersion() . '.' . filemtime($addon->getPath('assets/dist/uppy-backend-bundle.js'));
        rex_view::addJsFile($addon->getAssetsUrl('dist/uppy-backend-bundle.js?v=' . $version));
        
        // Custom Widget Bundle (für YForm und andere Verwendungen)
        $customVersion = $addon->getVersion() . '.' . filemtime($addon->getPath('assets/dist/uppy-custom-widget-bundle.js'));
        rex_view::addJsFile($addon->getAssetsUrl('dist/uppy-custom-widget-bundle.js?v=' . $customVersion));
        
        $uppyScriptsLoaded = true;
    }

    // Settings-Seite: JS für Dateitypen-Auswahl
    if ('uppy/settings' === rex_be_controller::getCurrentPage()) {
        rex_view::addJsFile($addon->getAssetsUrl('js/uppy-settings.js'));
    }

    // Upload-Seite: JS für YCom-Media-Auth-Defaults (Backend-User mit Permission)
    // Auch auf mediapool/upload laden, falls die Uppy-Variante über replace_mediapool eingehängt ist –
    // sonst würde das Panel zwar gerendert, aber die Session-Defaults nicht persistiert/zurückgesetzt.
    $currentPage = rex_be_controller::getCurrentPage();
    $isUppyUploadPage = 'uppy/upload' === $currentPage
        || ('mediapool/upload' === $currentPage && rex_config::get('uppy', 'replace_mediapool', false));
    if ($isUppyUploadPage
        && \FriendsOfRedaxo\Uppy\YcomAuthSettings::isEnabled()
        && \FriendsOfRedaxo\Uppy\YcomAuthSettings::userMayManage(rex::getUser())
    ) {
        rex_view::addJsFile($addon->getAssetsUrl('js/uppy-ycom-auth.js'));
    }
}

// Optional: Mediapool-Upload-Seite ersetzen
if (rex_config::get('uppy', 'replace_mediapool', false)) {
    rex_extension::register('PAGES_PREPARED', function (rex_extension_point $ep) {
        $pages = $ep->getSubject();
        
        if (isset($pages['mediapool'])) {
            $mediapoolPage = $pages['mediapool'];
            if ($uploadPage = $mediapoolPage->getSubpage('upload')) {
                $uploadPage->setSubPath(
                    rex_path::addon('uppy', 'pages/upload.php')
                );
            }
        }
    });
}

// Optional: Mediapool-Detailseite um Uppy-Dateiersatz (inkl. Chunk-Upload) erweitern
if (rex::isBackend() && rex::getUser() && rex_config::get('uppy', 'enable_mediapool_replace', true)) {
    rex_extension::register('MEDIA_DETAIL_SIDEBAR', static function (rex_extension_point $ep) use ($addon) {
        $mediaRow = $ep->getParam('media');
        if (!$mediaRow instanceof rex_sql) {
            return $ep->getSubject();
        }

        $fileId = (int) $ep->getParam('id', 0);
        if ($fileId <= 0) {
            return $ep->getSubject();
        }

        $filename = (string) $mediaRow->getValue('filename');
        if ('' === $filename) {
            return $ep->getSubject();
        }

        $media = rex_media::get($filename);
        if (!$media) {
            return $ep->getSubject();
        }

        $user = rex::getUser();
        if (!$user || !$user->getComplexPerm('media')->hasCategoryPerm($media->getCategoryId())) {
            return $ep->getSubject();
        }

        $ext = '.' . strtolower((string) rex_file::extension($filename));
        $allowedTypes = $ext;
        if ('.jpg' === $ext || '.jpeg' === $ext) {
            $allowedTypes = '.jpg,.jpeg';
        }

        $maxFilesize = (int) rex_config::get('uppy', 'max_filesize', 200);
        $signature = \FriendsOfRedaxo\Uppy\Signature::create([
            'category_id' => (int) $media->getCategoryId(),
            'allowed_types' => $allowedTypes,
            'max_filesize' => (string) $maxFilesize,
            'upload_dir' => '',
        ]);

        $inputId = 'uppy-mediapool-replace-' . $fileId;
        $redirectUrl = rex_url::backendPage('mediapool/media', [
            'file_id' => $fileId,
            'info' => rex_i18n::msg('pool_file_infos_updated'),
        ], false);

        $body = '<div id="uppy-mediapool-replace">'
            . '<p class="text-muted uppy-mediapool-replace-note">'
            . rex_escape($addon->i18n('uppy_mediapool_replace_notice'))
            . '</p>'
            . '<p class="uppy-mediapool-replace-current">'
            . '<strong>' . rex_escape($addon->i18n('uppy_mediapool_replace_current_file')) . ':</strong> '
            . '<span class="rex-word-break">' . rex_escape($filename) . '</span>'
            . '</p>'
            . '<input type="hidden"'
            . ' id="' . rex_escape($inputId) . '"'
            . ' data-widget="uppy"'
            . ' data-max-files="1"'
            . ' data-max-filesize="' . $maxFilesize . '"'
            . ' data-allowed-types="' . rex_escape($allowedTypes) . '"'
            . ' data-category-id="' . (int) $media->getCategoryId() . '"'
            . ' data-replace-file-id="' . $fileId . '"'
            . ' data-reload-on-success="true"'
            . ' data-replace-redirect-url="' . rex_escape($redirectUrl) . '"'
            . ' data-uppy-signature="' . rex_escape($signature) . '"'
            . ' data-compact="true"'
            . ' data-compact-label="' . rex_escape($addon->i18n('uppy_mediapool_replace_button')) . '"'
            . ' />'
            . '</div>';

        $fragment = new rex_fragment();
        $fragment->setVar('title', $addon->i18n('uppy_mediapool_replace_title'));
        $fragment->setVar('body', $body, false);
        $section = $fragment->parse('core/page/section.php');

        return (string) $ep->getSubject() . $section;
    });

    rex_extension::register('OUTPUT_FILTER', static function (rex_extension_point $ep): void {
        if ('mediapool/media' !== rex_be_controller::getCurrentPage()) {
            return;
        }

        $subject = $ep->getSubject();
        if (!is_string($subject) || false === strpos($subject, 'name="file_new"')) {
            return;
        }

        // Entfernt nur das exakte dt/dd-Paar "Datei austauschen" und lässt alle anderen Felder unangetastet.
        $exchangeLabel = preg_quote(rex_i18n::msg('pool_file_exchange'), '#');
        $subject = (string) preg_replace(
            '#<dt>\s*<label[^>]*>\s*' . $exchangeLabel . '\s*</label>\s*</dt>\s*<dd>\s*<input[^>]*name="file_new"[^>]*>\s*</dd>#is',
            '',
            $subject,
            1
        );

        $ep->setSubject($subject);
    });
}

// Info-Center-Widget für schnellen Upload aus dem Dashboard
if (rex::isBackend()
    && rex::getUser()
    && rex_addon::get('info_center')->isAvailable()
    && class_exists(\KLXM\InfoCenter\InfoCenter::class)
) {
    rex_extension::register('PACKAGES_INCLUDED', static function (): void {
        if (!class_exists(\KLXM\InfoCenter\InfoCenter::class)) {
            return;
        }
        $infoCenter = \KLXM\InfoCenter\InfoCenter::getInstance();
        $infoCenter->registerWidget(new \FriendsOfRedaxo\Uppy\InfoCenter\UppyUploadWidget());
    });
}
