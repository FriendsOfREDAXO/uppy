# Uppy API-Dokumentation

Diese Dokumentation beschreibt die API-Endpunkte, PHP-Klassen und JavaScript-Integrationen des Uppy-Addons.

---

## Schnellreferenz

### Verfügbare Data-Attribute

| Attribut | Beschreibung | Standard |
|----------|--------------|----------|
| `data-widget="uppy"` | Aktiviert das Standard Dashboard Widget | - |
| `class="uppy-upload-widget"` | Aktiviert das Custom Widget (Minimal UI) | - |
| `data-category-id` | ID der Mediapool-Kategorie für Uploads | 0 |
| `data-max-files` | Maximale Anzahl an Dateien | 10 |
| `data-max-filesize` | Maximale Dateigröße in MB | 200 |
| `data-allowed-types` | Erlaubte Dateitypen (MIME-Types oder Extensions), kommagetrennt | (alle) |
| `data-enable-image-editor` | Aktiviert den Bild-Editor (true/false) | false |
| `data-enable-webcam` | Aktiviert die Webcam-Integration (true/false) | false |
| `data-lang` | Sprache des Widgets (de_DE, en_US) | de_DE |
| `data-uppy-signature` | Signatur für manipulationssichere Uploads (via Signature::create()) | - |

### API-Endpunkte

| Endpunkt | Beschreibung | Methode |
|----------|--------------|---------|
| `/redaxo/index.php?rex-api-call=uppy_uploader` | Haupt-Upload-Endpunkt (Chunk & Standard-Upload) | POST |
| `/redaxo/index.php?rex-api-call=uppy_metadata` | Lädt verfügbare Metainfo-Felder für ein File | GET |

### PHP-Klassen

| Klasse | Beschreibung |
|--------|--------------|
| `FriendsOfRedaxo\Uppy\UppyUploadHandler` | Verarbeitet Uploads (Chunks, Standard, Signatur-Validierung) |
| `FriendsOfRedaxo\Uppy\UppyMetadataHandler` | Liefert MetaInfo-Felder für Dateien |
| `FriendsOfRedaxo\Uppy\Signature` | Erstellt und validiert Signaturen für sichere Uploads |

---

## Inhaltsverzeichnis

1. [API-Endpunkte](#api-endpunkte)
2. [PHP-Klassen](#php-klassen)
3. [JavaScript-Integration](#javascript-integration)
4. [Data-Attribute Referenz](#data-attribute-referenz)
5. [Signatur-System](#signatur-system)
6. [Metadaten-Handling](#metadaten-handling)
7. [Chunk-Upload](#chunk-upload)

---

## API-Endpunkte

### 1. Upload-Endpunkt

**Endpunkt:** `/redaxo/index.php?rex-api-call=uppy_uploader`  
**Methode:** `POST`  
**Klasse:** `FriendsOfRedaxo\Uppy\UppyUploadHandler`

#### Funktionalität

Verarbeitet Datei-Uploads in drei Modi:
- **Standard-Upload**: Komplette Datei in einem Request
- **Chunk-Upload**: Datei aufgeteilt in mehrere Chunks (für große Dateien)
- **Signierte Uploads**: Mit Signatur-Validierung für erhöhte Sicherheit

#### Request-Parameter

| Parameter | Typ | Beschreibung | Erforderlich |
|-----------|-----|--------------|--------------|
| `file` | File | Die hochzuladende Datei | Ja |
| `category_id` | Integer | Mediapool-Kategorie ID | Nein (Standard: 0) |
| `title` | String | Datei-Titel | Nein |
| `metadata` | JSON String | MetaInfo-Felder als JSON | Nein |
| `uppy_signature` | String | Signatur für sichere Uploads | Nein |
| `uppy_chunk_index` | Integer | Chunk-Index (bei Chunk-Upload) | Nein |
| `uppy_chunk_total` | Integer | Anzahl Chunks gesamt | Nein |
| `uppy_chunk_id` | String | Eindeutige Chunk-Session-ID | Nein |

#### Chunk-Upload Parameter

Bei aktiviertem Chunk-Upload werden zusätzliche Parameter gesendet:

```javascript
{
    uppy_chunk_index: 0,        // Aktueller Chunk (0-basiert)
    uppy_chunk_total: 5,        // Gesamtanzahl Chunks
    uppy_chunk_id: "abc123",    // Eindeutige Session-ID
    file: [Blob]                // Chunk-Daten
}
```

#### Response-Format

**Erfolg:**
```json
{
    "success": true,
    "data": {
        "filename": "beispiel.jpg",
        "title": "Beispiel Bild",
        "file_id": 123
    }
}
```

**Fehler:**
```json
{
    "success": false,
    "error": "Fehlermeldung"
}
```

**Chunk-Upload (intermediate):**
```json
{
    "success": true,
    "chunk_received": true,
    "chunk_index": 2,
    "chunks_received": 3,
    "chunks_total": 5
}
```

#### Validierung

Der Endpunkt validiert:
- Dateigröße (via `max_filesize`)
- Dateityp (via `allowed_types`)
- Signatur (wenn vorhanden)
- Mediapool-Kategorierechte
- MetaInfo-Pflichtfelder

---

### 2. Metadaten-Endpunkt

**Endpunkt:** `/redaxo/index.php?rex-api-call=uppy_metadata`  
**Methode:** `GET`  
**Klasse:** `FriendsOfRedaxo\Uppy\UppyMetadataHandler`

#### Funktionalität

Liefert verfügbare MetaInfo-Felder für den Mediapool, inklusive mehrsprachiger Felder.

#### Request-Parameter

| Parameter | Typ | Beschreibung | Erforderlich |
|-----------|-----|--------------|--------------|
| `filename` | String | Dateiname (für Kontext) | Nein |

#### Response-Format

```json
{
    "success": true,
    "data": {
        "fields": [
            {
                "name": "med_copyright",
                "label": "Copyright",
                "type": "text",
                "required": false,
                "multilang": false
            },
            {
                "name": "med_description",
                "label": "Beschreibung",
                "type": "textarea",
                "required": false,
                "multilang": true,
                "languages": [
                    {"id": 1, "code": "de", "name": "Deutsch"},
                    {"id": 2, "code": "en", "name": "English"}
                ]
            }
        ]
    }
}
```

#### Feld-Typen

Unterstützte MetaInfo-Feldtypen:
- `text` - Einzeiliges Textfeld
- `textarea` - Mehrzeiliges Textfeld
- `select` - Dropdown-Auswahl
- `date` - Datumsfeld
- `datetime` - Datum + Zeit

---

## PHP-Klassen

### 1. UppyUploadHandler

**Namespace:** `FriendsOfRedaxo\Uppy`  
**Extends:** `rex_api_function`

#### Methoden

##### `execute(): rex_api_result`

Hauptmethode, die alle Upload-Anfragen verarbeitet.

```php
public function execute(): rex_api_result
```

**Rückgabe:** `rex_api_result` mit success/error Status

##### `handleChunkUpload(): rex_api_result`

Verarbeitet Chunk-Uploads.

```php
protected function handleChunkUpload(array $fileData, int $chunkIndex, int $totalChunks, string $chunkId): rex_api_result
```

**Parameter:**
- `$fileData` - Array mit Dateidaten (`tmp_name`, `name`, etc.)
- `$chunkIndex` - Aktueller Chunk-Index (0-basiert)
- `$totalChunks` - Gesamtanzahl Chunks
- `$chunkId` - Eindeutige Session-ID

##### `assembleChunks(): string`

Fügt alle Chunks zu einer kompletten Datei zusammen.

```php
protected function assembleChunks(string $chunkId, string $filename): string
```

**Rückgabe:** Pfad zur zusammengesetzten Datei

##### `validateSignature(): bool`

Validiert signierte Upload-Anfragen.

```php
protected function validateSignature(array $params, string $signature): bool
```

**Parameter:**
- `$params` - Array mit zu validierenden Parametern
- `$signature` - Übermittelte Signatur

**Rückgabe:** `true` wenn Signatur gültig, sonst `false`

#### Verwendungsbeispiel

```php
// Wird automatisch über rex_api_function aufgerufen
// Aufruf via JavaScript/XHR an: /redaxo/index.php?rex-api-call=uppy_uploader
```

---

### 2. UppyMetadataHandler

**Namespace:** `FriendsOfRedaxo\Uppy`  
**Extends:** `rex_api_function`

#### Methoden

##### `execute(): rex_api_result`

Liefert verfügbare MetaInfo-Felder.

```php
public function execute(): rex_api_result
```

##### `getMetaInfoFields(): array`

Liest alle MetaInfo-Felder aus der Datenbank.

```php
protected function getMetaInfoFields(): array
```

**Rückgabe:** Array mit Feld-Definitionen

#### Verwendungsbeispiel

```php
// JavaScript-Aufruf
fetch('/redaxo/index.php?rex-api-call=uppy_metadata')
    .then(response => response.json())
    .then(data => {
        console.log(data.data.fields);
    });
```

---

### 3. Signature

**Namespace:** `FriendsOfRedaxo\Uppy`

#### Methoden

##### `create(array $params): string`

Erstellt eine HMAC-SHA256 Signatur für gegebene Parameter.

```php
public static function create(array $params): string
```

**Parameter:**
- `$params` - Assoziatives Array mit Parametern (z.B. `category_id`, `allowed_types`, `max_filesize`)

**Rückgabe:** Base64-kodierte Signatur

**Beispiel:**
```php
use FriendsOfRedaxo\Uppy\Signature;

$params = [
    'category_id' => 1,
    'allowed_types' => 'image/jpeg,image/png',
    'max_filesize' => 10 // MB
];

$signature = Signature::create($params);
// Rückgabe: "a7f8d9c2e3b4..."
```

##### `verify(array $params, string $signature): bool`

Verifiziert eine Signatur gegen gegebene Parameter.

```php
public static function verify(array $params, string $signature): bool
```

**Parameter:**
- `$params` - Parameter die validiert werden sollen
- `$signature` - Zu prüfende Signatur

**Rückgabe:** `true` wenn gültig, sonst `false`

**Beispiel:**
```php
$valid = Signature::verify($params, $signature);

if ($valid) {
    // Signatur ist korrekt
} else {
    // Manipulation erkannt
}
```

##### `getSecret(): string`

Liefert den geheimen Schlüssel für Signaturen.

```php
protected static function getSecret(): string
```

**Rückgabe:** Geheimer Schlüssel aus REDAXO-Konfiguration

---

## JavaScript-Integration

### Backend Dashboard Widget

**Bundle:** `assets/dist/uppy-backend-bundle.js`  
**Automatisch geladen:** Im REDAXO Backend

#### Aktivierung

```html
<input type="hidden" 
       name="REX_INPUT_VALUE[1]" 
       value="REX_VALUE[1]"
       data-widget="uppy"
       data-category-id="1">
```

#### Konfiguration

Globale Konfiguration über `rex_view::setJsProperty()`:

```php
rex_view::setJsProperty('uppy_config', [
    'enable_resize' => true,
    'resize_width' => 2000,
    'resize_height' => 2000,
    'resize_quality' => 85,
    'enable_chunks' => true,
    'chunk_size' => 5, // MB
    'enable_webcam' => false,
    'enable_image_editor' => false
]);
```

#### Events

Das Widget feuert folgende Events:

**upload-success**
```javascript
uppy.on('upload-success', (file, response) => {
    console.log('Upload erfolgreich:', file.name);
    console.log('Server-Response:', response.body.data);
});
```

**upload-error**
```javascript
uppy.on('upload-error', (file, error) => {
    console.error('Upload fehlgeschlagen:', error);
});
```

**complete**
```javascript
uppy.on('complete', (result) => {
    console.log('Alle Uploads abgeschlossen:', result);
});
```

---

### Frontend Custom Widget

**Bundle:** `assets/dist/uppy-custom-widget-bundle.js`  
**CSS:** `assets/dist/uppy-frontend-bundle.css`  
**Manuell einbinden:** Ja

#### Einbindung

```php
$uppy = rex_addon::get('uppy');

// CSS im <head>
echo '<link rel="stylesheet" href="'. $uppy->getAssetsUrl('dist/uppy-frontend-bundle.css') .'">';

// JS vor </body>
echo '<script src="'. $uppy->getAssetsUrl('dist/uppy-custom-widget-bundle.js') .'"></script>';
```

#### HTML

```html
<input type="hidden" 
       class="uppy-upload-widget"
       name="my_files" 
       value=""
       data-category-id="1"
       data-max-files="5">
```

#### JavaScript-API

**Manuelle Initialisierung:**

```javascript
import { UppyCustomWidget } from './uppy-custom-widget';

const inputElement = document.querySelector('#my-upload-field');
const widget = new UppyCustomWidget(inputElement, {
    maxFiles: 10,
    allowedTypes: ['image/*'],
    categoryId: 1
});
```

**Uppy-Instanz zugreifen:**

```javascript
const uppy = widget.uppy;

uppy.on('file-added', (file) => {
    console.log('Datei hinzugefügt:', file.name);
});
```

---

## Data-Attribute Referenz

### Allgemein

| Attribut | Typ | Beschreibung | Standard | Widget |
|----------|-----|--------------|----------|---------|
| `data-widget` | String | Aktiviert Dashboard Widget | - | Backend |
| `class="uppy-upload-widget"` | String | Aktiviert Custom Widget | - | Frontend |
| `data-category-id` | Integer | Mediapool-Kategorie ID | 0 | Beide |
| `data-max-files` | Integer | Max. Anzahl Dateien | 10 | Beide |
| `data-max-filesize` | Integer | Max. Dateigröße in MB | 200 | Beide |
| `data-allowed-types` | String | Erlaubte MIME-Types (kommagetrennt) | alle | Beide |
| `data-lang` | String | Sprache (de_DE, en_US) | de_DE | Beide |

### Features

| Attribut | Typ | Beschreibung | Standard | Widget |
|----------|-----|--------------|----------|---------|
| `data-enable-image-editor` | Boolean | Bildbearbeitung aktivieren | false | Beide |
| `data-enable-webcam` | Boolean | Webcam-Integration | false | Beide |
| `data-uppy-signature` | String | Signatur für sichere Uploads | - | Beide |

### Beispiel: Alle Optionen

```html
<input type="hidden" 
       data-widget="uppy"
       data-category-id="1"
       data-max-files="5"
       data-max-filesize="10"
       data-allowed-types="image/jpeg,image/png,image/webp"
       data-enable-image-editor="true"
       data-enable-webcam="false"
       data-lang="de_DE">
```

---

## Signatur-System

### Zweck

Das Signatur-System verhindert, dass Benutzer clientseitige Einschränkungen manipulieren (z.B. Dateigröße oder erlaubte Typen über Browser-DevTools ändern).

### Funktionsweise

1. **Server-seitig**: Parameter werden mit einem geheimen Schlüssel signiert
2. **Client-seitig**: Signatur wird mit Upload gesendet
3. **Server-seitig**: Signatur wird validiert, manipulierte Parameter werden abgelehnt

### Implementierung

#### 1. Signatur erstellen

```php
use FriendsOfRedaxo\Uppy\Signature;

$params = [
    'category_id' => 1,
    'allowed_types' => 'image/jpeg,image/png',
    'max_filesize' => 5 // MB
];

$signature = Signature::create($params);
```

#### 2. HTML ausgeben

```php
<input type="hidden" 
       data-widget="uppy"
       data-category-id="<?= $params['category_id'] ?>"
       data-allowed-types="<?= $params['allowed_types'] ?>"
       data-max-filesize="<?= $params['max_filesize'] ?>"
       data-uppy-signature="<?= $signature ?>">
```

#### 3. Server-seitige Validierung

Geschieht automatisch im `UppyUploadHandler`. Bei ungültiger Signatur:

```json
{
    "success": false,
    "error": "Invalid signature"
}
```

### Sicherheitshinweise

- **Geheimer Schlüssel**: Wird aus `rex_config::get('uppy', 'signature_secret')` gelesen
- Falls nicht gesetzt, wird automatisch ein zufälliger Schlüssel generiert
- **Parameter-Reihenfolge**: Spielt keine Rolle, Array wird intern sortiert
- **Manipulation**: Jede Änderung an signierten Parametern macht die Signatur ungültig

---

## Metadaten-Handling

### MetaInfo-Felder

Uppy unterstützt automatisch alle MetaInfo-Felder vom Typ `med_*`.

#### Unterstützte Feldtypen

- `text` - Einzeiliger Text
- `textarea` - Mehrzeiliger Text
- `select` - Dropdown
- `date` - Datum
- `datetime` - Datum + Zeit

#### Mehrsprachige Felder

Felder können pro Sprache unterschiedliche Werte haben:

```json
{
    "med_description": {
        "1": "Deutsche Beschreibung",
        "2": "English description"
    }
}
```

#### Upload mit Metadaten

**JavaScript:**
```javascript
uppy.on('file-added', (file) => {
    uppy.setFileMeta(file.id, {
        metadata: JSON.stringify({
            med_copyright: '© 2025 Firma GmbH',
            med_description: {
                '1': 'Deutsche Beschreibung',
                '2': 'English description'
            }
        })
    });
});
```

**PHP (serverseitig):**
```php
// Wird automatisch vom UppyUploadHandler verarbeitet
$metadata = json_decode($_POST['metadata'], true);
```

---

## Chunk-Upload

### Konfiguration

**PHP (global):**
```php
rex_config::set('uppy', 'enable_chunks', true);
rex_config::set('uppy', 'chunk_size', 5); // MB
```

**JavaScript (pro Widget):**
```javascript
// Automatisch aktiviert wenn enable_chunks = true
```

### Ablauf

1. **Datei wird in Chunks aufgeteilt** (client-seitig)
2. **Chunks werden sequenziell hochgeladen**
3. **Server speichert Chunks temporär** in `rex_path::cache('uppy_chunks/')`
4. **Bei letztem Chunk**: Chunks werden zusammengefügt
5. **Komplette Datei** wird in Mediapool eingefügt

### Chunk-Speicherort

```
redaxo/cache/uppy_chunks/
    abc123_beispiel.jpg.part.0
    abc123_beispiel.jpg.part.1
    abc123_beispiel.jpg.part.2
    ...
```

### Cleanup

Chunks werden automatisch gelöscht:
- Nach erfolgreicher Assembly
- Nach 24 Stunden (via REDAXO Cache-Cleanup)

---

## YForm-Integration

### value.uppy_uploader.php

**Pfad:** `lib/yform/value/uppy_uploader.php`

#### Verwendung

```php
// In YForm-Tabelle definieren:
$yform->setValueField('uppy_uploader', [
    'name' => 'files',
    'label' => 'Dateien',
    'category_id' => 1,
    'max_files' => 10
]);
```

#### HTML-Ausgabe

```html
<input type="hidden" 
       name="files"
       value="datei1.jpg,datei2.jpg"
       data-widget="uppy"
       data-category-id="1"
       data-max-files="10">
```

---

## Beispiele

### 1. Einfacher Upload

```html
<input type="hidden" 
       data-widget="uppy"
       data-category-id="1"
       name="my_files">
```

### 2. Nur Bilder, mit Editor

```html
<input type="hidden" 
       data-widget="uppy"
       data-category-id="1"
       data-allowed-types="image/*"
       data-enable-image-editor="true"
       name="images">
```

### 3. Signierter Upload

```php
<?php
use FriendsOfRedaxo\Uppy\Signature;

$params = [
    'category_id' => 1,
    'allowed_types' => 'application/pdf',
    'max_filesize' => 2
];
$sig = Signature::create($params);
?>

<input type="hidden" 
       data-widget="uppy"
       data-category-id="<?= $params['category_id'] ?>"
       data-allowed-types="<?= $params['allowed_types'] ?>"
       data-max-filesize="<?= $params['max_filesize'] ?>"
       data-uppy-signature="<?= $sig ?>"
       name="documents">
```

### 4. Frontend Custom Widget

```php
$uppy = rex_addon::get('uppy');
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $uppy->getAssetsUrl('dist/uppy-frontend-bundle.css') ?>">
</head>
<body>
    <form method="post">
        <label>Bilder hochladen:</label>
        <input type="hidden" 
               class="uppy-upload-widget"
               name="user_images"
               data-category-id="1"
               data-max-files="5"
               data-allowed-types="image/*">
        
        <button type="submit">Speichern</button>
    </form>
    
    <script src="<?= $uppy->getAssetsUrl('dist/uppy-custom-widget-bundle.js') ?>"></script>
</body>
</html>
```

---

## Troubleshooting

### Upload schlägt fehl

**Problem:** Upload liefert 413 Error  
**Lösung:** PHP-Limits erhöhen:
```ini
upload_max_filesize = 200M
post_max_size = 200M
max_execution_time = 300
```

**Problem:** Chunk-Upload funktioniert nicht  
**Lösung:** Cache-Ordner prüfen:
```php
rex_dir::create(rex_path::cache('uppy_chunks'));
```

### Signatur-Fehler

**Problem:** "Invalid signature"  
**Lösung:** 
- Prüfen ob alle Parameter korrekt übergeben werden
- Sicherstellen dass `signature_secret` konsistent ist
- Cache leeren (`rex_delete_cache()`)

### Metadaten werden nicht gespeichert

**Problem:** MetaInfo-Felder leer nach Upload  
**Lösung:**
- Prüfen ob Felder im MetaInfo-Addon definiert sind
- JSON-Format der Metadaten validieren
- Browser-Console auf JavaScript-Fehler prüfen

---

## Support

Bei Fragen oder Problemen:
- **GitHub Issues**: https://github.com/FriendsOfREDAXO/uppy/issues
- **REDAXO Slack**: #addons Kanal
- **Forum**: https://friendsofredaxo.github.io/

---

## Changelog

Siehe [CHANGELOG.md](CHANGELOG.md) für Versionshistorie.
