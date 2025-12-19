<?php

namespace FriendsOfRedaxo\Uppy;

use rex;
use rex_api_function;
use rex_api_exception;
use rex_response;
use rex_request;
use rex_media;
use rex_sql;
use rex_sql_exception;
use rex_clang;
use rex_i18n;
use rex_addon;
use rex_plugin;
use rex_backend_login;
use rex_ycom_auth;
use rex_config;
use rex_logger;
use rex_media_cache;
use rex_media_category;
use rex_media_category_select;
use Exception;

/**
 * API für MetaInfo-Felder-Verwaltung
 * 
 * Stellt Endpunkte bereit um:
 * - Verfügbare MetaInfo-Felder abzurufen
 * - Metadaten zu laden
 * - Metadaten zu speichern
 * 
 * @package uppy
 */
class UppyMetadataHandler extends rex_api_function
{
    protected $published = true;

    /**
     * Zentrale Response-Methode
     */
    protected function sendResponse($data, $statusCode = 200): void
    {
        rex_response::cleanOutputBuffers();
        if ($statusCode !== 200) {
            rex_response::setStatus($statusCode);
        }
        rex_response::sendJson($data);
        exit;
    }

    public function execute()
    {
        try {
            // Authentifizierung prüfen
            if (!$this->isAuthorized()) {
                throw new rex_api_exception('Unauthorized access');
            }

            $action = rex_request('action', 'string', '');

            switch ($action) {
                case 'get_fields':
                    $this->getMetaInfoFields();
                    break;

                case 'get_field_config':
                    $this->getFieldConfig();
                    break;

                case 'load_metadata':
                    $this->loadMetadata();
                    break;

                case 'save_metadata':
                    $this->saveMetadata();
                    break;

                default:
                    throw new rex_api_exception('Invalid action: ' . $action);
            }
        } catch (Exception $e) {
            rex_logger::logException($e);
            $this->sendResponse(['error' => $e->getMessage()], rex_response::HTTP_FORBIDDEN);
        }
    }

    /**
     * Prüft die Authentifizierung
     */
    protected function isAuthorized(): bool
    {
        // Backend-User
        $user = rex_backend_login::createUser();
        if ($user) {
            return true;
        }

        // API-Token
        $apiToken = rex_config::get('uppy', 'api_token');
        $requestToken = rex_request('api_token', 'string', null);

        if ($apiToken && $requestToken && hash_equals($apiToken, $requestToken)) {
            return true;
        }

        // YCom-User
        if (rex_plugin::get('ycom', 'auth')->isAvailable()) {
            if (rex_ycom_auth::getUser()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gibt alle verfügbaren MetaInfo-Felder zurück
     * Nur Text- und Input-Felder werden berücksichtigt
     */
    protected function getMetaInfoFields(): void
    {
        $fields = [];

        // Standard-Feld title immer hinzufügen
        $fields[] = [
            'id' => 'title',
            'name' => 'Titel',
            'placeholder' => 'Titel eingeben',
            'type' => 'text',
            'is_multilang' => false
        ];
        
        // Kategorie NICHT hier - wird global auf Upload-Seite ausgewählt

        // Automatisch alle verfügbaren Text-Felder aus MetaInfo laden
        if (rex_addon::get('metainfo')->isAvailable()) {
            try {
                // Prüfe ob metainfo_lang_fields verfügbar ist und hole dessen type_ids
                $hasLangFields = rex_addon::get('metainfo_lang_fields')->isAvailable();
                $languages = $hasLangFields ? \rex_clang::getAll() : [];
                
                // Standard type_ids: 1 = Text, 2 = Textarea
                $allowedTypeIds = [1, 2];
                
                // Wenn metainfo_lang_fields aktiv ist, hole die type_ids für lang_text_all und lang_textarea_all
                if ($hasLangFields) {
                    $typeSql = rex_sql::factory();
                    $typeSql->setQuery(
                        'SELECT id FROM ' . rex::getTable('metainfo_type') . 
                        ' WHERE label IN ("lang_text_all", "lang_textarea_all")'
                    );
                    while ($typeSql->hasNext()) {
                        $allowedTypeIds[] = (int) $typeSql->getValue('id');
                        $typeSql->next();
                    }
                }
                
                $sql = rex_sql::factory();
                // Text, Textarea und ggf. lang_text_all, lang_textarea_all für med_* Felder
                $sql->setQuery(
                    'SELECT * FROM ' . rex::getTable('metainfo_field') . 
                    ' WHERE name LIKE "med_%" AND type_id IN (' . implode(',', $allowedTypeIds) . ') ORDER BY priority'
                );

                $count = $sql->getRows();
                rex_logger::factory()->log('debug', 'Uppy MetaInfo: Gefundene Felder: ' . $count . ' (type_ids: ' . implode(',', $allowedTypeIds) . ')');

                while ($sql->hasNext()) {
                    $fieldName = $sql->getValue('name');
                    $fieldLabel = $sql->getValue('title') ?: ucfirst($fieldName);
                    
                    // Übersetze Label falls es mit "translate:" beginnt
                    if (str_starts_with($fieldLabel, 'translate:')) {
                        $fieldLabel = rex_i18n::msg(substr($fieldLabel, 10));
                    }
                    
                    $typeId = (int) $sql->getValue('type_id');
                    $params = $sql->getValue('params');
                    
                    // Hole den type_label um zu prüfen ob es ein lang-Feld ist
                    $typeLabelSql = rex_sql::factory();
                    $typeLabelSql->setQuery(
                        'SELECT label FROM ' . rex::getTable('metainfo_type') . ' WHERE id = ?',
                        [$typeId]
                    );
                    $typeLabel = $typeLabelSql->getValue('label');
                    
                    // Prüfe ob es ein mehrsprachiges Feld ist (lang_text_all oder lang_textarea_all)
                    $isMultilang = $hasLangFields && in_array($typeLabel, ['lang_text_all', 'lang_textarea_all']);
                    
                    if ($isMultilang && count($languages) > 0) {
                        // EIN Feld mit Array von Sprachen
                        $languagesList = [];
                        foreach ($languages as $clang) {
                            $languagesList[] = [
                                'clang_id' => $clang->getId(),
                                'clang_name' => $clang->getName(),
                                'clang_code' => $clang->getCode()
                            ];
                        }
                        
                        $fields[] = [
                            'id' => $fieldName,
                            'name' => $fieldLabel,
                            'placeholder' => $fieldLabel . ' eingeben',
                            'is_multilang' => true,
                            'type' => str_replace('_all', '', $typeLabel), // lang_text oder lang_textarea
                            'languages' => $languagesList
                        ];
                    } else {
                        // Normales Feld
                        $fields[] = [
                            'id' => $fieldName,
                            'name' => $fieldLabel,
                            'placeholder' => $fieldLabel . ' eingeben',
                            'is_multilang' => false,
                            'type' => $typeId == 2 ? 'textarea' : 'text'
                        ];
                    }

                    $sql->next();
                }
            } catch (rex_sql_exception $e) {
                rex_logger::logException($e);
            }
        } else {
            rex_logger::factory()->log('debug', 'Uppy: MetaInfo AddOn ist nicht verfügbar');
        }

        $this->sendResponse([
            'success' => true,
            'data' => $fields
        ]);
    }

    /**
     * Gibt Konfiguration für ein spezifisches Feld zurück
     */
    protected function getFieldConfig(): void
    {
        $fieldName = rex_request('field', 'string', '');

        if (empty($fieldName)) {
            throw new rex_api_exception('Missing field parameter');
        }

        $fieldInfo = $this->analyzeField($fieldName);

        if (!$fieldInfo) {
            throw new rex_api_exception('Field not found: ' . $fieldName);
        }

        $this->sendResponse([
            'success' => true,
            'field' => $fieldInfo
        ]);
    }

    /**
     * Gibt alle verfügbaren MetaInfo-Felder zurück
     */
    protected function getAvailableMetaFields(): array
    {
        $fields = [];
        
        if (!rex_addon::get('metainfo')->isAvailable()) {
            return $fields;
        }

        try {
            // Prüfe ob metainfo_lang_fields verfügbar ist und hole dessen type_ids
            $hasLangFields = rex_addon::get('metainfo_lang_fields')->isAvailable();
            
            // Standard type_ids: 1 = Text, 2 = Textarea
            $allowedTypeIds = [1, 2];
            
            // Wenn metainfo_lang_fields aktiv ist, hole die type_ids für lang_text_all und lang_textarea_all
            if ($hasLangFields) {
                $typeSql = rex_sql::factory();
                $typeSql->setQuery(
                    'SELECT id FROM ' . rex::getTable('metainfo_type') . 
                    ' WHERE label IN ("lang_text_all", "lang_textarea_all")'
                );
                while ($typeSql->hasNext()) {
                    $allowedTypeIds[] = (int) $typeSql->getValue('id');
                    $typeSql->next();
                }
            }
            
            $sql = rex_sql::factory();
            $sql->setQuery(
                'SELECT name FROM ' . rex::getTable('metainfo_field') . 
                ' WHERE name LIKE "med_%" AND type_id IN (' . implode(',', $allowedTypeIds) . ') ORDER BY priority'
            );

            while ($sql->hasNext()) {
                $fields[] = $sql->getValue('name');
                $sql->next();
            }
        } catch (rex_sql_exception $e) {
            rex_logger::logException($e);
        }

        return $fields;
    }

    /**
     * Lädt vorhandene Metadaten für eine Datei
     */
    protected function loadMetadata(): void
    {
        $filename = rex_request('filename', 'string', '');

        if (empty($filename)) {
            throw new rex_api_exception('Missing filename parameter');
        }

        $media = rex_media::get($filename);
        
        if (!$media) {
            throw new rex_api_exception('Media not found: ' . $filename);
        }

        $metadata = [
            'title' => $media->getTitle(),
        ];

        // Alle verfügbaren MetaInfo-Felder laden
        $fieldNames = $this->getAvailableMetaFields();

        foreach ($fieldNames as $fieldName) {
            if ($fieldName === 'title') {
                continue;
            }

            if (method_exists($media, 'getValue')) {
                $value = $media->getValue($fieldName);
                if ($value !== null && $value !== '') {
                    $metadata[$fieldName] = $value;
                }
            }
        }

        $this->sendResponse([
            'success' => true,
            'metadata' => $metadata
        ]);
    }

    /**
     * Speichert Metadaten für eine Datei
     */
    protected function saveMetadata(): void
    {
        $filename = rex_request('filename', 'string', '');
        $metadataRaw = rex_request('metadata', 'string', '');
        $metadata = json_decode($metadataRaw, true);

        if (!is_array($metadata)) {
            // Fallback: Versuche es als Array zu lesen (falls es doch als Array kommt)
            $metadata = rex_request('metadata', 'array', []);
        }

        if (empty($filename)) {
            throw new rex_api_exception('Missing filename parameter');
        }

        $media = rex_media::get($filename);
        
        if (!$media) {
            throw new rex_api_exception('Media not found: ' . $filename);
        }

        // SQL Update vorbereiten
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('media'));
        $sql->setWhere(['filename' => $filename]);

        // Titel ist Standardfeld
        if (isset($metadata['title'])) {
            $sql->setValue('title', $metadata['title']);
        }

        // Alle verfügbaren MetaInfo-Felder durchgehen
        $fieldNames = $this->getAvailableMetaFields();

        foreach ($fieldNames as $fieldName) {
            if ($fieldName === 'title') {
                continue;
            }
            
            if (isset($metadata[$fieldName])) {
                $value = $metadata[$fieldName];
                
                // Wenn Array, dann ist es ein mehrsprachiges Feld -> in JSON konvertieren
                if (is_array($value)) {
                    $jsonValue = [];
                    foreach ($value as $clangId => $val) {
                        $jsonValue[] = [
                            'clang_id' => (int) $clangId,
                            'value' => $val
                        ];
                    }
                    $sql->setValue($fieldName, json_encode($jsonValue));
                } else {
                    $sql->setValue($fieldName, $value);
                }
            }
        }

        // Update durchführen
        try {
            $sql->update();
            
            // Cache löschen für diese Datei
            rex_media_cache::delete($filename);
            
            $this->sendResponse([
                'success' => true,
                'message' => rex_i18n::msg('uppy_metadata_saved')
            ]);
        } catch (rex_sql_exception $e) {
            rex_logger::logException($e);
            throw new rex_api_exception('Database error while saving metadata');
        }
    }

    /**
     * Analysiert ein Feld und gibt seine Eigenschaften zurück
     */
    protected function analyzeField(string $fieldName): ?array
    {
        // Standard-Feld title
        if ($fieldName === 'title') {
            return [
                'name' => 'title',
                'label' => 'Titel',
                'type' => 'text',
                'required' => false,
                'multilingual' => false,
                'languages' => []
            ];
        }

        // MetaInfo-Felder
        if (!rex_addon::get('metainfo')->isAvailable()) {
            return null;
        }

        try {
            $sql = rex_sql::factory();
            $sql->setQuery(
                'SELECT * FROM ' . rex::getTable('metainfo_field') . ' WHERE name = ?',
                [$fieldName]
            );

            if ($sql->getRows() === 0) {
                return null;
            }

            $typeId = (int) $sql->getValue('type_id');
            $attributes = $sql->getValue('attributes');

            return [
                'name' => $fieldName,
                'label' => $sql->getValue('title') ?: ucfirst($fieldName),
                'type' => $this->getFieldTypeByTypeId($typeId),
                'required' => false,
                'multilingual' => $this->isMultilingual($attributes),
                'languages' => $this->isMultilingual($attributes) ? $this->getAvailableLanguages() : []
            ];

        } catch (rex_sql_exception $e) {
            rex_logger::logException($e);
            return null;
        }
    }

    /**
     * Ermittelt den Feldtyp anhand der Type-ID
     */
    protected function getFieldTypeByTypeId(int $typeId): string
    {
        return match ($typeId) {
            1 => 'text',        // Text
            2 => 'textarea',    // Textarea
            3 => 'select',      // Select
            4 => 'radio',       // Radio
            5 => 'checkbox',    // Checkbox
            6 => 'media',       // REX_MEDIA_WIDGET
            7 => 'medialist',   // REX_MEDIALIST_WIDGET
            8 => 'link',        // REX_LINK_WIDGET
            9 => 'linklist',    // REX_LINKLIST_WIDGET
            10 => 'date',       // Date
            11 => 'datetime',   // Datetime
            13 => 'time',       // Time
            default => 'text',
        };
    }

    /**
     * Prüft ob ein Feld mehrsprachig ist
     */
    protected function isMultilingual(?string $attributes): bool
    {
        if (empty($attributes)) {
            return false;
        }

        // Prüfe auf translatable-Attribut
        return str_contains($attributes, 'translatable') || 
               str_contains($attributes, 'multilingual') ||
               str_contains($attributes, 'lang');
    }

    /**
     * Gibt alle verfügbaren Sprachen zurück
     */
    protected function getAvailableLanguages(): array
    {
        $languages = [];

        foreach (rex_clang::getAll() as $clang) {
            $languages[] = [
                'id' => $clang->getId(),
                'code' => $clang->getCode(),
                'name' => $clang->getName()
            ];
        }

        return $languages;
    }
}
