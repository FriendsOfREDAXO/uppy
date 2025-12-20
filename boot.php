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

// YForm Integration - registrieren wenn YForm verfügbar ist
rex_extension::register('PACKAGES_INCLUDED', function() use ($addon) {
    if (rex_addon::get('yform')->isAvailable()) {
        rex_yform::addTemplatePath($addon->getPath('ytemplates'));
        
        // MEDIA_IS_IN_USE Extension registrieren (verhindert Löschen verwendeter Dateien)
        FriendsOfRedaxo\Uppy\UppyMediaCleanup::register();
    }
});

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
