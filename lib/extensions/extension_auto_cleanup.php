<?php

namespace FriendsOfRedaxo\Uppy;

use rex;
use rex_extension;
use rex_extension_point;
use rex_sql;
use rex_addon;
use rex_config;
use rex_media;
use rex_logger;
use rex_path;
use rex_media_cache;

/**
 * Extension Point Handler für automatisches Cleanup nicht mehr verwendeter Medien
 * 
 * Wird nach dem Speichern von YForm-Einträgen getriggert und löscht Medien
 * die nicht mehr in uppy_uploader-Feldern verwendet werden.
 * 
 * WICHTIG: Nur aktiv wenn rex_config::get('uppy', 'auto_cleanup_enabled') === true
 * 
 * @package uppy
 */
class UppyAutoCleanup
{
    /**
     * Extension Point: REX_YFORM_SAVED
     * 
     * Wird aufgerufen nachdem ein YForm-Datensatz gespeichert wurde.
     * Vergleicht alte und neue Werte und löscht nicht mehr verwendete Dateien.
     * 
     * @param rex_extension_point $ep
     * @return void
     */
    public static function handleYformSaved(rex_extension_point $ep): void
    {
        // Nur wenn Auto-Cleanup aktiviert ist
        if (!rex_config::get('uppy', 'auto_cleanup_enabled', false)) {
            return;
        }
        
        $params = $ep->getParams();
        $table = $params['table'] ?? null;
        $id = $params['id'] ?? null;
        $data = $params['data'] ?? [];
        $form = $params['form'] ?? null;
        
        if (!$table || !$id) {
            return;
        }
        
        // Finde alle uppy_uploader-Felder in dieser Tabelle
        $uppyFields = self::getUppyFieldsForTable($table);
        
        if (empty($uppyFields)) {
            return;
        }
        
        // Für jedes uppy-Feld prüfen ob Dateien entfernt wurden
        foreach ($uppyFields as $fieldName) {
            $oldValue = self::getOldFieldValue($table, $id, $fieldName);
            $newValue = $data[$fieldName] ?? '';
            
            // Vergleiche alte und neue Dateiliste
            $oldFiles = array_filter(array_map('trim', explode(',', $oldValue)));
            $newFiles = array_filter(array_map('trim', explode(',', $newValue)));
            
            // Finde entfernte Dateien
            $removedFiles = array_diff($oldFiles, $newFiles);
            
            if (empty($removedFiles)) {
                continue;
            }
            
            // Prüfe jede entfernte Datei ob sie noch wo anders verwendet wird
            foreach ($removedFiles as $filename) {
                if (self::isFileStillInUse($filename, $table, $id, $fieldName)) {
                    // Datei wird noch verwendet -> nicht löschen
                    rex_logger::factory()->log('info', sprintf(
                        'Uppy Auto-Cleanup: Datei "%s" wird noch verwendet, nicht gelöscht.',
                        $filename
                    ));
                    continue;
                }
                
                // Datei wird nirgendwo mehr verwendet -> aus Mediapool löschen
                self::deleteMediaFile($filename);
            }
        }
    }
    
    /**
     * Findet alle uppy_uploader-Feldnamen für eine bestimmte Tabelle
     * 
     * @param string $tableName
     * @return array Liste von Feldnamen
     */
    private static function getUppyFieldsForTable(string $tableName): array
    {
        $sql = rex_sql::factory();
        
        $query = "
            SELECT name
            FROM " . rex::getTable('yform_field') . "
            WHERE table_name = :table_name
            AND type_id = 'value'
            AND type_name = 'uppy_uploader'
        ";
        
        try {
            $result = $sql->getArray($query, ['table_name' => $tableName]);
            return array_column($result, 'name');
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Holt den alten Wert eines Feldes vor dem Update
     * 
     * @param string $tableName
     * @param int $id
     * @param string $fieldName
     * @return string
     */
    private static function getOldFieldValue(string $tableName, int $id, string $fieldName): string
    {
        $sql = rex_sql::factory();
        
        try {
            $query = sprintf(
                "SELECT %s FROM %s WHERE id = :id",
                $sql->escapeIdentifier($fieldName),
                $sql->escapeIdentifier($tableName)
            );
            
            $result = $sql->getArray($query, ['id' => $id]);
            
            if (!empty($result)) {
                return (string) $result[0][$fieldName];
            }
        } catch (\Exception $e) {
            rex_logger::factory()->log('warning', sprintf(
                'Uppy Auto-Cleanup: Fehler beim Laden des alten Werts: %s',
                $e->getMessage()
            ));
        }
        
        return '';
    }
    
    /**
     * Prüft ob eine Datei noch in anderen uppy-Feldern verwendet wird
     * 
     * @param string $filename
     * @param string $excludeTable Table die gerade gespeichert wurde (optional)
     * @param int|null $excludeId ID des Datensatzes der gerade gespeichert wurde (optional)
     * @param string|null $excludeField Feld das gerade gespeichert wurde (optional)
     * @return bool true wenn Datei noch verwendet wird
     */
    private static function isFileStillInUse(
        string $filename, 
        ?string $excludeTable = null, 
        ?int $excludeId = null,
        ?string $excludeField = null
    ): bool {
        $sql = rex_sql::factory();
        
        // Alle YForm-Tables mit uppy-Feldern
        $tableFields = self::getAllUppyFields();
        
        foreach ($tableFields as $data) {
            $table = $data['table_name'];
            $field = $data['field_name'];
            
            // Überspringe den gerade gespeicherten Datensatz
            if ($excludeTable === $table && $excludeField === $field) {
                $whereClause = $excludeId ? " AND id != " . (int) $excludeId : '';
            } else {
                $whereClause = '';
            }
            
            $query = sprintf(
                "SELECT COUNT(*) as count FROM %s 
                 WHERE FIND_IN_SET(%s, %s) > 0 %s",
                $sql->escapeIdentifier($table),
                $sql->escape($filename),
                $sql->escapeIdentifier($field),
                $whereClause
            );
            
            try {
                $result = $sql->getArray($query);
                if (!empty($result) && (int) $result[0]['count'] > 0) {
                    return true; // Datei wird noch verwendet
                }
            } catch (\Exception $e) {
                // Bei Fehler sicherheitshalber nicht löschen
                rex_logger::factory()->log('warning', sprintf(
                    'Uppy Auto-Cleanup: Fehler bei Verwendungs-Check: %s',
                    $e->getMessage()
                ));
                return true;
            }
        }
        
        // Prüfe auch ob Datei in MetaInfo-Feldern (rex_media, rex_article etc.) verwendet wird
        // Extension Point MEDIA_IS_IN_USE nutzen
        $warnings = rex_extension::registerPoint(new rex_extension_point(
            'MEDIA_IS_IN_USE',
            [],
            ['filename' => $filename]
        ));
        
        if (!empty($warnings)) {
            return true; // Datei wird noch verwendet
        }
        
        return false;
    }
    
    /**
     * Alle YForm-Tables mit uppy_uploader-Feldern
     * 
     * @return array
     */
    private static function getAllUppyFields(): array
    {
        static $cache = null;
        
        if ($cache !== null) {
            return $cache;
        }
        
        $sql = rex_sql::factory();
        
        $query = "
            SELECT table_name, name as field_name
            FROM " . rex::getTable('yform_field') . "
            WHERE type_id = 'value'
            AND type_name = 'uppy_uploader'
        ";
        
        try {
            $cache = $sql->getArray($query);
            return $cache;
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Löscht eine Mediendatei aus dem Mediapool
     * 
     * @param string $filename
     * @return void
     */
    private static function deleteMediaFile(string $filename): void
    {
        $media = rex_media::get($filename);
        
        if (!$media) {
            return;
        }
        
        try {
            // Media-Service zum Löschen nutzen
            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('media'));
            $sql->setWhere('filename = :filename', ['filename' => $filename]);
            $sql->delete();
            
            // Physische Datei löschen
            $mediaPath = rex_path::media($filename);
            if (file_exists($mediaPath)) {
                @unlink($mediaPath);
            }
            
            // Media Manager Cache löschen
            if (rex_addon::get('media_manager')->isAvailable()) {
                rex_media_cache::delete($filename);
            }
            
            rex_logger::factory()->log('info', sprintf(
                'Uppy Auto-Cleanup: Datei "%s" wurde aus dem Mediapool gelöscht.',
                $filename
            ));
        } catch (\Exception $e) {
            rex_logger::factory()->log('error', sprintf(
                'Uppy Auto-Cleanup: Fehler beim Löschen der Datei "%s": %s',
                $filename,
                $e->getMessage()
            ));
        }
    }
    
    /**
     * Registriert den Extension Point
     * 
     * Sollte in boot.php aufgerufen werden (nur wenn YForm verfügbar)
     * 
     * @return void
     */
    public static function register(): void
    {
        // Nur registrieren wenn aktiviert
        if (!rex_config::get('uppy', 'auto_cleanup_enabled', false)) {
            return;
        }
        
        rex_extension::register('REX_YFORM_SAVED', [self::class, 'handleYformSaved'], rex_extension::LATE);
    }
}
