<?php

namespace FriendsOfRedaxo\Uppy;

use rex_config;
use rex_i18n;
use rex_url;

use function rex_escape;

/**
 * Rendert das Uppy-Upload-Feld (verstecktes Input-Element inkl. aller data-Attribute
 * und Sicherheits-Signatur) aus einem einfachen Options-Array.
 *
 * Wird sowohl vom YForm-Value-Typ (ytemplates/bootstrap/value.uppy.tpl.php) als auch
 * von FriendsOfRedaxo\Uppy\Utils::field() für die direkte Frontend-Einbindung genutzt,
 * damit es nur eine Stelle gibt, die weiß, welche data-Attribute Uppy erwartet.
 */
class Field
{
    /**
     * @param string $name Formularfeld-Name (name-Attribut des versteckten Inputs)
     * @param array{
     *     id?: string,
     *     category_id?: int|string,
     *     upload_folder?: string,
     *     max_files?: int|string,
     *     max_filesize?: int|string,
     *     allowed_types?: string,
     *     enable_webcam?: bool,
     *     enable_image_editor?: bool,
     *     allow_mediapool?: bool,
     *     show_file_access?: bool,
     *     file_access_mode?: string,
     *     compact?: bool,
     *     compact_label?: string,
     *     locale?: string,
     * } $options
     * @param string $value Aktueller (kommagetrennter) Dateiname-Wert
     */
    public static function render(string $name, array $options = [], string $value = ''): string
    {
        $categoryId = array_key_exists('category_id', $options)
            ? (int) $options['category_id']
            : (int) rex_config::get('uppy', 'category_id', 1);

        $uploadFolder = (string) ($options['upload_folder'] ?? '');

        // 0 ist bei max_files gueltig (unbegrenzt), daher explizit auf Vorhandensein pruefen.
        $maxFiles = array_key_exists('max_files', $options)
            ? (int) $options['max_files']
            : (int) rex_config::get('uppy', 'max_files', 10);

        $maxFilesize = array_key_exists('max_filesize', $options)
            ? (int) $options['max_filesize']
            : (int) rex_config::get('uppy', 'max_filesize', 200);

        $allowedTypes = (string) ($options['allowed_types'] ?? rex_config::get('uppy', 'allowed_types', '*'));

        $enableWebcam = (bool) ($options['enable_webcam'] ?? rex_config::get('uppy', 'enable_webcam', false));
        $enableImageEditor = (bool) ($options['enable_image_editor'] ?? rex_config::get('uppy', 'enable_image_editor', false));
        $allowMediapool = (bool) ($options['allow_mediapool'] ?? false);
        $showFileAccess = (bool) ($options['show_file_access'] ?? false);

        $fileAccessMode = (string) ($options['file_access_mode'] ?? 'download');
        if (!in_array($fileAccessMode, ['download', 'both'], true)) {
            $fileAccessMode = 'download';
        }

        $compact = (bool) ($options['compact'] ?? false);
        $compactLabel = (string) ($options['compact_label'] ?? '');

        $id = (string) ($options['id'] ?? ('uppy-field-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $name)));

        // Signatur gegen Manipulation der obigen Werte im Frontend (siehe Signature::verify()
        // in UppyUploadHandler::processUploadedFile) - Werte muessen exakt den data-Attributen entsprechen.
        $signature = Signature::create([
            'category_id' => $categoryId,
            'allowed_types' => $allowedTypes,
            'max_filesize' => $maxFilesize,
            'upload_dir' => $uploadFolder,
        ]);

        // Immer die Frontend-index.php verwenden (wie FilePond), unabhaengig vom Kontext:
        // im Backend wird /redaxo/../index.php genutzt, im Frontend direkt /index.php,
        // aber beides funktioniert vom Browser aus zuverlaessig als absolute URL.
        $apiEndpoint = rex_url::frontendController(['rex-api-call' => 'uppy_uploader']);
        $fileAccessEndpoint = rex_url::backendController(['rex-api-call' => 'uppy_file_access']);

        $attrs = [
            'type' => 'hidden',
            'id' => $id,
            'name' => $name,
            'value' => $value,
            'class' => 'uppy-upload-widget',
            'data-api-endpoint' => $apiEndpoint,
            'data-category-id' => (string) $categoryId,
            'data-upload-dir' => $uploadFolder,
            'data-max-files' => (string) $maxFiles,
            'data-max-filesize' => (string) $maxFilesize,
            'data-allowed-types' => $allowedTypes,
            'data-enable-webcam' => $enableWebcam ? '1' : '0',
            'data-enable-image-editor' => $enableImageEditor ? '1' : '0',
            'data-allow-mediapool' => $allowMediapool ? 'true' : 'false',
            'data-enable-file-links' => $showFileAccess ? 'true' : 'false',
            'data-file-access-mode' => $fileAccessMode,
            'data-file-access-endpoint' => $fileAccessEndpoint,
            'data-link-view-label' => rex_i18n::msg('uppy_file_access_view'),
            'data-link-download-label' => rex_i18n::msg('uppy_file_access_download'),
            'data-uppy-signature' => $signature,
        ];

        if (isset($options['locale'])) {
            $attrs['data-locale'] = (string) $options['locale'];
        }

        if ($compact) {
            $attrs['data-compact'] = 'true';
            if ('' !== $compactLabel) {
                $attrs['data-compact-label'] = $compactLabel;
            }
        }

        $html = '<input';
        foreach ($attrs as $attr => $attrValue) {
            $html .= ' ' . $attr . '="' . rex_escape($attrValue) . '"';
        }
        $html .= ' />';

        return $html;
    }
}
