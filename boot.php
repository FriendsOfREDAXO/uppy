<?php

/**
 * Uppy Uploader AddOn für REDAXO
 * 
 * @package uppy
 * @author Friends Of REDAXO
 */

// API-Klassen explizit registrieren (ab REDAXO 5.17)
rex_api_function::register('uppy_uploader', FriendsOfRedaxo\Uppy\UppyUploadHandler::class);
rex_api_function::register('uppy_metadata', FriendsOfRedaxo\Uppy\UppyMetadataHandler::class);

// YForm Template-Pfad hinzufügen
rex_yform::addTemplatePath($this->getPath('ytemplates'));

// Backend-Integration
if (rex::isBackend() && rex::getUser()) {
    // Assets nur einmalig laden
    static $uppyScriptsLoaded = false;
    
    if (!$uppyScriptsLoaded) {
        // CSS - Core, Dashboard, Webcam und Image Editor (Uppy 5.0)
        rex_view::addCssFile($this->getAssetsUrl('uppy-core.min.css'));
        rex_view::addCssFile($this->getAssetsUrl('uppy-dashboard.min.css'));
        rex_view::addCssFile($this->getAssetsUrl('uppy-webcam.min.css'));
        rex_view::addCssFile($this->getAssetsUrl('uppy-image-editor.min.css'));
        rex_view::addCssFile($this->getAssetsUrl('uppy-dark-overrides.css'));
        rex_view::addCssFile($this->getAssetsUrl('uppy-custom.css'));
        
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
        
        // JavaScript Bundle (lokal gebaut mit esbuild) - mit Timestamp für Cache-Busting
        $version = $this->getVersion() . '.' . filemtime($this->getPath('assets/uppy-backend-bundle.js'));
        rex_view::addJsFile($this->getAssetsUrl('uppy-backend-bundle.js?v=' . $version));
        
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
