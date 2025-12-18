<?php

/**
 * YForm Value-Feld: Uppy Uploader
 * 
 * Ermöglicht File-Uploads mit Uppy in YForm-Formularen
 */
class rex_yform_value_uppy_uploader extends rex_yform_value_abstract
{
    public function enterObject()
    {
        $this->params['value_pool']['email'][$this->getName()] = $this->getValue();

        if ($this->needsOutput() && $this->isViewable()) {
            $this->params['form_output'][$this->getId()] = $this->parse('value.uppy.tpl.php', ['type' => 'upload']);
        }

        $this->params['value_pool']['sql'][$this->getName()] = $this->getValue();
    }

    public function getDescription(): string
    {
        return 'uppy|name|label|[category_id]|[max_files]|[max_filesize]|[allowed_types]';
    }

    public function getDefinitions(): array
    {
        return [
            'type' => 'value',
            'name' => 'uppy_uploader',
            'values' => [
                'name' => ['type' => 'name', 'label' => rex_i18n::msg('yform_values_defaults_name')],
                'label' => ['type' => 'text', 'label' => rex_i18n::msg('yform_values_defaults_label')],
                'category_id' => ['type' => 'text', 'label' => rex_i18n::msg('yform_values_uppy_category_id'), 'default' => '0'],
                'max_files' => ['type' => 'text', 'label' => rex_i18n::msg('yform_values_uppy_max_files'), 'default' => '10'],
                'max_filesize' => ['type' => 'text', 'label' => rex_i18n::msg('yform_values_uppy_max_filesize'), 'default' => '200'],
                'allowed_types' => ['type' => 'text', 'label' => rex_i18n::msg('yform_values_uppy_allowed_types'), 'default' => 'image/*,application/pdf'],
            ],
            'description' => rex_i18n::msg('yform_values_uppy_description'),
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
        $output = [];
        
        foreach ($files as $filename) {
            $media = rex_media::get(trim($filename));
            if ($media) {
                $output[] = '<a href="' . rex_url::backendPage('mediapool/detail', ['file_id' => $media->getId()]) . '">' . rex_escape($filename) . '</a>';
            } else {
                $output[] = rex_escape($filename);
            }
        }
        
        return implode(', ', $output);
    }
}
