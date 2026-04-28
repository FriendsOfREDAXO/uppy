<?php

namespace FriendsOfRedaxo\Uppy\InfoCenter;

use KLXM\InfoCenter\AbstractWidget;
use rex;
use rex_addon;
use rex_be_controller;
use rex_config;
use rex_escape;
use rex_media_category_select;
use rex_media_perm;
use rex_url;

use function sprintf;

/**
 * Info-Center-Widget für schnellen Uppy-Upload aus dem Dashboard.
 *
 * Bindet das Custom-Widget des Uppy-AddOns inline ein und steuert
 * die Zielkategorie über ein Media-Category-Select.
 */
class UppyUploadWidget extends AbstractWidget
{
    /** @var bool */
    protected bool $supportsLazyLoading = false;

    public function __construct()
    {
        parent::__construct();
        $this->title = '☁️ Uppy Upload';
        $this->priority = 0.6; // direkt nach FilePond (0.5)
    }

    public function render(): string
    {
        if (!rex::isBackend()) {
            return '';
        }

        if (!rex_addon::get('uppy')->isAvailable()) {
            return $this->wrapContent('<p class="text-muted">Uppy AddOn nicht verfügbar</p>');
        }

        // Eindeutige IDs für mehrere Widget-Instanzen
        $widgetId = 'uppy-info-center-' . uniqid();
        $categorySelectId = $widgetId . '-category';

        // Kategorie-Auswahl (nur erlaubte Kategorien)
        $catsSel = new rex_media_category_select(true);
        $catsSel->setId($categorySelectId);
        $catsSel->setName('category_id');
        $catsSel->setSize(1);
        $catsSel->setAttribute('class', 'form-control');
        $catsSel->setAttribute('data-live-search', 'true');

        $defaultCategory = (int) rex_config::get('uppy', 'category_id', 0);
        $mediaPerm = rex::getUser() ? rex::getUser()->getComplexPerm('media') : null;
        if ($mediaPerm instanceof rex_media_perm && $mediaPerm->hasAll()) {
            $catsSel->addOption('Root-Kategorie', '0');
        }
        $catsSel->setSelected($defaultCategory);

        // Backend-Endpoint, damit Backend-Session-Cookies mitgesendet werden
        $apiEndpoint = rex_url::backendController(['rex-api-call' => 'uppy_uploader'], false);

        // Settings auslesen
        $maxFiles = (int) rex_config::get('uppy', 'max_files', 30);
        $maxFilesize = (int) rex_config::get('uppy', 'max_filesize', 200);
        $allowedTypes = (string) rex_config::get('uppy', 'allowed_types', '*');
        $enableChunks = rex_config::get('uppy', 'enable_chunks', true) ? 'true' : 'false';
        $chunkSize = (int) rex_config::get('uppy', 'chunk_size', 5);

        // Link zur kompletten Upload-Seite (mit YCom-Auth-Panel etc.)
        $uploadPageUrl = rex_url::backendPage('uppy/upload');

        $content = sprintf(
            '<div class="uppy-info-center-widget">
                <div class="form-group" style="margin-bottom:10px;">
                    <label for="%1$s" class="control-label" style="margin-bottom:4px;">Kategorie</label>
                    %2$s
                </div>

                <div class="form-group">
                    <input type="hidden"
                           name="uppy_files"
                           id="%3$s"
                           class="uppy-upload-widget"
                           data-api-endpoint="%4$s"
                           data-category-id="%5$d"
                           data-max-files="%6$d"
                           data-max-filesize="%7$d"
                           data-allowed-types="%8$s"
                           data-enable-chunks="%9$s"
                           data-chunk-size="%10$d"
                           data-enable-image-editor="true"
                           data-enable-webcam="true"
                           data-enable-sorting="false"
                           data-allow-mediapool="false"
                           value="">
                </div>

                <div style="margin-top:8px; display:flex; justify-content:space-between; align-items:center;">
                    <small class="text-muted">
                        <i class="rex-icon rex-icon-info"></i>
                        Dateien hierher ziehen oder über den Button auswählen.
                    </small>
                    <a href="%11$s" class="btn btn-link btn-xs" title="Zur vollen Upload-Seite">
                        <i class="rex-icon fa-solid fa-up-right-from-square"></i> Upload-Seite
                    </a>
                </div>
            </div>

            <script>
            (function () {
                var categorySelect = document.getElementById(%12$s);
                var uppyInput = document.getElementById(%13$s);
                if (categorySelect && uppyInput) {
                    categorySelect.addEventListener("change", function () {
                        uppyInput.dataset.categoryId = this.value;
                    });
                }
            })();
            </script>',
            rex_escape($categorySelectId),
            $catsSel->get(),
            rex_escape($widgetId),
            rex_escape($apiEndpoint),
            $defaultCategory,
            $maxFiles,
            $maxFilesize,
            rex_escape($allowedTypes),
            $enableChunks,
            $chunkSize,
            rex_escape($uploadPageUrl),
            json_encode($categorySelectId),
            json_encode($widgetId),
        );

        return $this->wrapContent($content);
    }
}
