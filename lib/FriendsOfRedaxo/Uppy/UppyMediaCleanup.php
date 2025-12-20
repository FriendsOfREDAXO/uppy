<?php

namespace FriendsOfRedaxo\Uppy;

use rex;
use rex_extension;
use rex_extension_point;
use rex_sql;
use rex_addon;
use rex_url;
use rex_logger;

/**
 * Extension Point Handler für MEDIA_IS_IN_USE
 * 
 * Prüft ob hochgeladene Medien in YForm-Tables mit uppy_uploader-Feldern verwendet werden
 * und verhindert das Löschen von Dateien die noch in Verwendung sind.
 * 
 * @package uppy
 */
class UppyMediaCleanup
{
    /**
     * Extension Point: MEDIA_IS_IN_USE
     * 
     * Wird aufgerufen wenn eine Mediendatei gelöscht werden soll.
     * Prüft alle YForm-Tables auf Verwendung der Datei.
     * 
     * @param rex_extension_point $ep
     * @return array Liste von Warnmeldungen mit HTML-Links zu den verwendenden Einträgen
     */
    public static function isMediaInUse(rex_extension_point $ep): array
    {
        $warnings = $ep->getSubject();
        $filename = $ep->getParam('filename');
        
        if (empty($filename)) {
            return $warnings;
        }
        
        // Prüfe ob YForm verfügbar ist
        if (!rex_addon::get('yform')->isAvailable()) {
            return $warnings;
        }
        
        // Optional: Aktuellen Datensatz ignorieren (für Cleanup während Speichern)
        $ignoreTable = $ep->getParam('ignore_table', '');
        $ignoreId = (int)$ep->getParam('ignore_id', 0);
        $ignoreField = $ep->getParam('ignore_field', '');
        
        // Fallback: Aus globalem Kontext wenn deleteMedia() intern aufruft
        if (!$ignoreTable && isset($GLOBALS['uppy_cleanup_ignore'])) {
            $ignoreTable = $GLOBALS['uppy_cleanup_ignore']['table'] ?? '';
            $ignoreId = (int)($GLOBALS['uppy_cleanup_ignore']['id'] ?? 0);
            $ignoreField = $GLOBALS['uppy_cleanup_ignore']['field'] ?? '';
            
            rex_logger::factory()->log('debug', sprintf(
                'UppyMediaCleanup: Verwende globale ignore-Parameter (table: %s, id: %d, field: %s)',
                $ignoreTable,
                $ignoreId,
                $ignoreField
            ));
        }
        
        $sql = rex_sql::factory();
        $escapedFilename = $sql->escape($filename);
        
        // Alle YForm-Tables mit uppy_uploader-Feldern finden
        $tableFields = self::getUppyFields();
        
        if (empty($tableFields)) {
            return $warnings;
        }
        
        $usageEntries = [];
        
        // Jede Tabelle/Feld-Kombination durchsuchen
        foreach ($tableFields as $tableData) {
            $tableName = $tableData['table_name'];
            $fieldName = $tableData['field_name'];
            $tableLabel = $tableData['table_label'];
            
            // Aktuellen Datensatz/Feld komplett ignorieren falls angegeben
            if ($ignoreTable && $ignoreId && $ignoreField && 
                $tableName === $ignoreTable && 
                $fieldName === $ignoreField) {
                rex_logger::factory()->log('debug', sprintf(
                    'UppyMediaCleanup: Überspringe Feld "%s" in Tabelle "%s" (wird gerade bearbeitet)',
                    $fieldName,
                    $tableName
                ));
                continue;
            }
            
            // Prüfe ob Datei im komma-separierten String vorkommt
            // FIND_IN_SET funktioniert mit komma-separierten Werten
            $query = sprintf(
                "SELECT id, %s FROM %s WHERE FIND_IN_SET(%s, %s) > 0",
                $sql->escapeIdentifier($fieldName),
                $sql->escapeIdentifier($tableName),
                $escapedFilename,
                $sql->escapeIdentifier($fieldName)
            );
            
            // Bei gleicher Tabelle: aktuellen Datensatz ausschließen
            if ($ignoreTable && $ignoreId && $tableName === $ignoreTable) {
                $query .= sprintf(" AND id != %d", $ignoreId);
            }
            
            $result = $sql->getArray($query);
            
            foreach ($result as $row) {
                $id = (int) $row['id'];
                $fieldValue = (string) $row[$fieldName];
                
                // Generiere Link zum YForm-Manager Datensatz (Edit-Seite)
                $editUrl = rex_url::backendPage('yform/manager/data_edit', [
                    'table_name' => $tableName,
                    'data_id' => $id,
                    'func' => 'edit',
                ]);
                
                $usageEntries[] = sprintf(
                    '<li><a href="%s">%s (ID: %d)</a></li>',
                    $editUrl,
                    rex_escape($tableLabel),
                    $id
                );
            }
        }
        
        // Warnung hinzufügen wenn Einträge gefunden wurden
        if (!empty($usageEntries)) {
            $warnings[] = '<strong>Medium wird in folgenden YForm-Einträgen verwendet:</strong><br><ul>' . 
                          implode('', $usageEntries) . 
                          '</ul>';
        }
        
        return $warnings;
    }
    
    /**
     * Findet alle YForm-Tables die uppy_uploader-Felder haben
     * 
     * @return array Array mit table_name, field_name, table_label
     */
    private static function getUppyFields(): array
    {
        $sql = rex_sql::factory();
        
        // Suche in rex_yform_table nach Tabellen mit uppy_uploader-Feldern
        $query = "
            SELECT 
                t.table_name,
                t.name as table_label,
                f.name as field_name,
                f.label as field_label
            FROM " . rex::getTable('yform_table') . " t
            JOIN " . rex::getTable('yform_field') . " f ON t.table_name = f.table_name
            WHERE f.type_id = 'value'
            AND f.type_name = 'uppy_uploader'
            ORDER BY t.table_name, f.name
        ";
        
        try {
            $result = $sql->getArray($query);
            return $result ?: [];
        } catch (\Exception $e) {
            // Bei Fehler (z.B. YForm-Tables existieren nicht) leeres Array zurückgeben
            return [];
        }
    }
    
    /**
     * Registriert den Extension Point
     * 
     * Sollte in boot.php aufgerufen werden
     * 
     * @return void
     */
    public static function register(): void
    {
        rex_extension::register('MEDIA_IS_IN_USE', [self::class, 'isMediaInUse']);
    }
}
