<?php

/**
 * YForm Value-Feld: Uppy Uploader
 * 
 * Ermöglicht File-Uploads mit Uppy in YForm-Formularen
 * 
 * @package redaxo\uppy
 */
class rex_yform_value_uppy_uploader extends rex_yform_value_abstract
{
    public function enterObject()
    {
        // Wert für E-Mail-Versand verfügbar machen
        $this->params['value_pool']['email'][$this->getName()] = $this->getValue();

        // Output für Formular
        if ($this->needsOutput() && $this->isViewable()) {
            $this->params['form_output'][$this->getId()] = $this->parse('value.uppy.tpl.php');
        }

        // Wert für Datenbank speichern (kommagetrennte Dateinamen)
        $this->params['value_pool']['sql'][$this->getName()] = $this->getValue();
    }

    public function getDescription(): string
    {
        return 'uppy_uploader|name|label|[category_id]|[max_files]|[max_filesize]|[allowed_types]|[enable_webcam]';
    }

    public function getDefinitions(): array
    {
        return [
            'type' => 'value',
            'name' => 'uppy_uploader',
            'values' => [
                'name' => ['type' => 'name', 'label' => rex_i18n::msg('yform_values_defaults_name')],
                'label' => ['type' => 'text', 'label' => rex_i18n::msg('yform_values_defaults_label')],
                'category_id' => ['type' => 'text', 'label' => 'Kategorie-ID', 'default' => '0'],
                'max_files' => ['type' => 'text', 'label' => 'Max. Dateien', 'default' => '10'],
                'max_filesize' => ['type' => 'text', 'label' => 'Max. Dateigröße (MB)', 'default' => '200'],
                'allowed_types' => ['type' => 'text', 'label' => 'Erlaubte Dateitypen', 'default' => 'image/*,application/pdf'],
                'enable_webcam' => ['type' => 'checkbox', 'label' => 'Webcam aktivieren', 'default' => '0'],
            ],
            'description' => 'Uppy File Upload Widget',
            'db_type' => ['text'],
        ];
    }

    public static function getSearchField($params)
    {
        $params['searchForm']->setValueField('text', [
            'name' => $params['field']->getName(),
            'label' => $params['field']->getLabel(),
        ]);
    }

    public static function getListValue($params)
    {
        $value = $params['subject'];
        
        if (empty($value)) {
            return '-';
        }
        
        // Kommagetrennte Dateinamen
        $files = array_filter(explode(',', $value));
        if (empty($files)) {
            return '-';
        }
        
        $output = [];
        foreach ($files as $filename) {
            $filename = trim($filename);
            $media = rex_media::get($filename);
            
            if ($media) {
                // Link zur Medienpool-Detailseite
                $url = rex_url::backendPage('mediapool/detail', ['file_name' => $filename]);
                
                // Bei Bildern: Thumbnail anzeigen
                if ($media->isImage() && rex_addon::get('media_manager')->isAvailable()) {
                    $thumbnail = '<img src="' . rex_url::frontend('index.php?rex_media_type=rex_media_small&rex_media_file=' . urlencode($filename)) . '" alt="" style="max-width: 50px; max-height: 50px; vertical-align: middle; margin-right: 5px;" />';
                    $output[] = '<a href="' . $url . '">' . $thumbnail . rex_escape($filename) . '</a>';
                } else {
                    $output[] = '<a href="' . $url . '">' . rex_escape($filename) . '</a>';
                }
            } else {
                $output[] = '<span style="color: #999;">' . rex_escape($filename) . ' (nicht gefunden)</span>';
            }
        }
        
        return implode('<br>', $output);
    }
}
