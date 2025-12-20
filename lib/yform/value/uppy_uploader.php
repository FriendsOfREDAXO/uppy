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
    public function preValidateAction(): void
    {
        // Nur wenn Auto-Cleanup aktiviert ist
        if (!rex_config::get('uppy', 'auto_cleanup_enabled', 0)) {
            return;
        }
        
        if (!isset($this->params['send']) || !$this->params['send']) {
            return;
        }
        
        // Original Value aus der Datenbank holen
        $originalValue = '';
        if (isset($this->params['main_id']) && $this->params['main_id'] > 0) {
            $sql = rex_sql::factory();
            $sql->setQuery('SELECT ' . $sql->escapeIdentifier($this->getName()) . 
                          ' FROM ' . $sql->escapeIdentifier($this->params['main_table']) . 
                          ' WHERE id = ' . (int)$this->params['main_id']);
            if ($sql->getRows() > 0) {
                $originalValue = (string)$sql->getValue($this->getName());
            }
        }

        // Neuen Wert aus dem Formular holen
        $newValue = '';
        if (isset($_REQUEST['FORM']) && is_array($_REQUEST['FORM'])) {
            foreach ($_REQUEST['FORM'] as $form) {
                if (is_array($form) && isset($form[$this->getId()])) {
                    $newValue = (string)$form[$this->getId()];
                    break;
                }
            }
        }

        // Gelöschte Dateien ermitteln und verarbeiten
        $originalFiles = array_filter(explode(',', $originalValue));
        $newFiles = array_filter(explode(',', $newValue));
        $deletedFiles = array_diff($originalFiles, $newFiles);

        foreach ($deletedFiles as $filename) {
            $filename = trim($filename);
            if (empty($filename)) {
                continue;
            }
            
            rex_logger::factory()->log('debug', sprintf(
                'Uppy Auto-Cleanup: Prüfe Datei "%s" (Tabelle: %s, ID: %s)',
                $filename,
                $this->params['main_table'] ?? 'unknown',
                $this->params['main_id'] ?? 'unknown'
            ));
            
            try {
                if ($media = rex_media::get($filename)) {
                    // Prüfen ob die Datei noch von anderen Datensätzen verwendet wird
                    $inUse = false;
                    $sql = rex_sql::factory();
                    
                    // Alle YForm Tabellen durchsuchen
                    $yformTables = rex_yform_manager_table::getAll();
                    foreach ($yformTables as $table) {
                        foreach ($table->getFields() as $field) {
                            if ($field->getType() === 'value' && $field->getTypeName() === 'uppy_uploader') {
                                $tableName = $table->getTableName();
                                $fieldName = $field->getName();
                                
                                // Aktuelle Tabelle und ID für Ausschluss
                                $currentTable = $this->params['main_table'] ?? '';
                                $currentId = (int)($this->params['main_id'] ?? 0);
                                
                                // WHERE-Bedingung: Datei kommt vor UND (andere Tabelle ODER andere ID)
                                $query = "SELECT id FROM $tableName WHERE FIND_IN_SET(:filename, $fieldName) > 0";
                                
                                // Aktuellen Datensatz ausschließen (nur wenn gleiche Tabelle)
                                if ($tableName === $currentTable && $currentId > 0) {
                                    $query .= " AND id != :id";
                                }
                                
                                try {
                                    $params = [':filename' => $filename];
                                    if ($tableName === $currentTable && $currentId > 0) {
                                        $params[':id'] = $currentId;
                                    }
                                    
                                    rex_logger::factory()->log('debug', sprintf(
                                        'Uppy Auto-Cleanup: Query: %s | Params: %s',
                                        $query,
                                        json_encode($params)
                                    ));
                                    
                                    $result = $sql->getArray($query, $params);
                                    if (count($result) > 0) {
                                        $inUse = true;
                                        rex_logger::factory()->log('debug', sprintf(
                                            'Uppy Auto-Cleanup: Datei "%s" noch in Verwendung in Tabelle "%s"',
                                            $filename,
                                            $tableName
                                        ));
                                        break 2;
                                    }
                                } catch (Exception $e) {
                                    rex_logger::factory()->log('warning', sprintf(
                                        'Uppy Auto-Cleanup: Query-Fehler bei Tabelle "%s": %s',
                                        $tableName,
                                        $e->getMessage()
                                    ));
                                    continue;
                                }
                            }
                        }
                    }
                    
                    // Extension Point: MEDIA_IS_IN_USE prüfen
                    if (!$inUse) {
                        $warnings = rex_extension::registerPoint(new rex_extension_point(
                            'MEDIA_IS_IN_USE',
                            [],
                            [
                                'filename' => $filename,
                                'media' => $media,
                                'ignore_table' => $this->params['main_table'] ?? '',
                                'ignore_id' => (int)($this->params['main_id'] ?? 0),
                                'ignore_field' => $this->getName(),
                            ]
                        ));
                        
                        if (is_array($warnings) && !empty($warnings)) {
                            $inUse = true;
                            rex_logger::factory()->log('debug', sprintf(
                                'Uppy Auto-Cleanup: Datei "%s" noch in Verwendung (Extension Point)',
                                $filename
                            ));
                        }
                    }

                    // Datei löschen wenn sie nicht mehr verwendet wird
                    if (!$inUse && rex_media::get($filename)) {
                        rex_logger::factory()->log('debug', sprintf(
                            'Uppy Auto-Cleanup: Lösche Datei "%s"',
                            $filename
                        ));
                        
                        // Temporär die ignore-Parameter global setzen, damit MEDIA_IS_IN_USE sie sieht
                        // wenn deleteMedia() intern den Extension Point aufruft
                        $GLOBALS['uppy_cleanup_ignore'] = [
                            'table' => $this->params['main_table'] ?? '',
                            'id' => (int)($this->params['main_id'] ?? 0),
                            'field' => $this->getName(),
                        ];
                        
                        try {
                            rex_media_service::deleteMedia($filename);
                            
                            rex_logger::factory()->log('info', sprintf(
                                'Uppy Auto-Cleanup: Datei "%s" aus Tabelle "%s" (ID: %s) gelöscht.',
                                $filename,
                                $this->params['main_table'] ?? 'unknown',
                                $this->params['main_id'] ?? 'unknown'
                            ));
                        } finally {
                            // Cleanup
                            unset($GLOBALS['uppy_cleanup_ignore']);
                        }
                    } else {
                        rex_logger::factory()->log('debug', sprintf(
                            'Uppy Auto-Cleanup: Datei "%s" NICHT gelöscht (inUse: %s)',
                            $filename,
                            $inUse ? 'true' : 'false'
                        ));
                    }
                } else {
                    rex_logger::factory()->log('debug', sprintf(
                        'Uppy Auto-Cleanup: Datei "%s" nicht im Mediapool gefunden',
                        $filename
                    ));
                }
            } catch (Exception $e) {
                // Fehler beim Löschen werden ignoriert
                rex_logger::factory()->log('warning', sprintf(
                    'Uppy Auto-Cleanup: Fehler beim Löschen von "%s": %s',
                    $filename,
                    $e->getMessage()
                ));
            }
        }
    }
    
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
        return 'uppy_uploader|name|label|[category_id]|[max_files]|[max_filesize]|[allowed_types]|[enable_webcam]|[enable_image_editor]';
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
                'enable_image_editor' => ['type' => 'checkbox', 'label' => 'Image Editor aktivieren', 'default' => '0'],
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
