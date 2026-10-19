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
| `data-allow-mediapool` | Zeigt Button für Medienpool-Auswahl (true/false) | false |
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
| `func` | String | `prepare`, `upload`, `chunk` oder `finalize` | Ja |
| `file` | File | Die hochzuladende Datei bzw. der Chunk | Ja (upload/chunk) |
| `uppy_signature` | String | Signierte Feld-Konfiguration (auch als Header `X-Uppy-Signature`) | **Ja ohne Backend-Login**, für Upload-Ordner immer |
| `category_id` | Integer | Mediapool-Kategorie – wird nur bei Backend-Usern **ohne** Signatur ausgewertet | Nein |
| `title` | String | Datei-Titel | Nein |
| `metadata` | JSON String | MetaInfo-Felder als JSON (bei `prepare`) | Nein |
| `fileId` | String | Upload-ID, nur `A-Z a-z 0-9 _ -`, max. 64 Zeichen | Ja (prepare/chunk/finalize) |
| `chunkIndex` / `totalChunks` | Integer | Chunk-Nummer (0-basiert) und Anzahl | Ja (chunk/finalize) |
| `fileName` | String | Original-Dateiname (bei `finalize`) | Ja (finalize) |
| `replace_file_id` | Integer | Mediapool-Datei ersetzen (nur Backend) | Nein |

Seit 2.11.0 werden `upload_dir`, `uppy_allowed_types` und `uppy_max_filesize` aus dem Request **nicht mehr ausgewertet** – die Werte kommen ausschließlich aus der Signatur. Der API-Token (für Server-zu-Server-Aufrufe) wird nur noch als Header `X-Uppy-Token` oder POST-Feld `api_token` angenommen, nicht mehr in der URL.

#### Chunk-Upload Parameter

Bei aktiviertem Chunk-Upload sendet das Widget nacheinander `prepare`, mehrere `chunk`-Anfragen und `finalize`:

```javascript
{
    fileId: "uppy_1760000000000_abc123def", // Upload-ID
    chunkIndex: 0,                          // Aktueller Chunk (0-basiert)
    totalChunks: 5,                         // Gesamtanzahl Chunks
    file: [Blob]                            // Chunk-Daten
}
```

Grenzen: ein Chunk darf höchstens die eingestellte Chunk-Größe (+64 KB) haben, alle Chunks zusammen höchstens die erlaubte Maximalgröße; die Anzahl der Chunks ist entsprechend begrenzt.

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

Der Endpunkt validiert (zentral in `FriendsOfRedaxo\Uppy\Security`):
- Berechtigung: Backend-Login **oder** gültige, an die Session gebundene Signatur plus API-Token/YCom/`UPPY_AUTH_CHECK`
- Dateigröße (signiertes `max_filesize`, höchstens die globale Einstellung)
- Dateiname gegen die Sperrliste (alle Punkt-Segmente, z.B. `bild.php.jpg`) und die Sperrliste des Mediapools
- Echten Dateityp per `finfo` gegen `allowed_types`; SVG nur ohne Skripte
- Upload-Ordner: nur unterhalb der Installation bzw. freigegebener Ordner, keine Systemordner
- Mediapool-Kategorierechte (Backend-User)

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

##### Sicherheitsprüfungen (`Security`)

Seit 2.11.0 liegt die gesamte Rechte- und Dateiprüfung in `FriendsOfRedaxo\Uppy\Security` und wird von Upload- und Metadaten-Endpunkt gemeinsam genutzt:

- `Security::authorize(): array` – Backend-User oder gültige Signatur + Token/YCom/EP, sonst `rex_api_exception`
- `Security::resolveUploadConfig(array $auth): array` – wirksame Kategorie, Typen, Maximalgröße, Upload-Ordner
- `Security::assertFilenameAllowed(string $filename, bool $customFolder): void`
- `Security::assertContentAllowed(string $path, string $filename, string $allowedTypes, bool $enforceTypes, bool $customFolder): string`
- `Security::resolveCustomDirectory(string $uploadDir, bool $create = true): string`

`UppyUploadHandler::isAuthorized()` und `UppyMetadataHandler::isAuthorized()` bleiben für abgeleitete Klassen erhalten (deprecated) und rufen `Security::authorize()` auf.

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

##### `create(array $params, ?int $ttl = null): string`

Erstellt eine signierte Feld-Konfiguration (HMAC-SHA256). Sie enthält die Werte selbst, ein Ablaufdatum (Standard 24 Stunden, `Signature::DEFAULT_TTL`) und eine Bindung an die aktuelle Browser-Session (startet die Session bei Bedarf).

```php
public static function create(array $params, ?int $ttl = null): string
```

**Parameter:**
- `$params` - Assoziatives Array (`category_id`, `allowed_types`, `max_filesize`, `upload_dir`)
- `$ttl` - Gültigkeit in Sekunden (optional)

**Rückgabe:** Signatur im Format `u2.<daten>.<hmac>` (Format seit 2.11.0; ältere Signaturen werden nicht mehr angenommen)

**Beispiel:**
```php
use FriendsOfRedaxo\Uppy\Signature;

$params = [
    'category_id' => 1,
    'allowed_types' => 'image/jpeg,image/png',
    'max_filesize' => 10 // MB
];

$signature = Signature::create($params);
// Rückgabe: "u2.eyJwIjp7..."
```

##### `decode(string $signature, bool $checkSession = true): ?array`

Prüft HMAC, Ablaufdatum und (optional) die Session-Bindung und gibt die signierten Werte zurück, sonst `null`.

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

- **Geheimer Schlüssel**: Wird aus `rex_config::get('uppy', 'secret')` gelesen
- Falls nicht gesetzt, wird automatisch ein zufälliger Schlüssel generiert
- **Parameter-Reihenfolge**: Spielt keine Rolle, Array wird intern sortiert
- **Manipulation**: Jede Änderung an signierten Werten macht die Signatur ungültig
- **Pflicht ohne Backend-Login** (seit 2.11.0): Anfragen ohne gültige Signatur werden abgelehnt
- **Session-Bindung und Ablauf**: Die Signatur gilt nur in der Session, in der sie erzeugt wurde, und 24 Stunden lang. Seiten mit Upload-Feld nicht aus einem gemeinsamen Seiten-Cache ausliefern
- **Werte nur aus der Signatur**: Kategorie, Typen, Größe und Upload-Ordner werden nicht mehr aus Request-Parametern gelesen

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
3. **Server speichert Chunks temporär** in `rex_path::addonData('uppy', 'upload/chunks/')`
4. **Bei letztem Chunk**: Chunks werden zusammengefügt
5. **Komplette Datei** wird in Mediapool eingefügt

### Chunk-Speicherort

```
redaxo/data/addons/uppy/upload/chunks/
    <sha256 aus Session und fileId>/0
    <sha256 aus Session und fileId>/1
    ...
```

Der Ordner ist durch die REDAXO-`.htaccess` und eine eigene `.htaccess` gegen Web-Zugriff gesperrt (bei nginx `redaxo/data` sperren). Die Ordnernamen sind an die Session gebunden, sodass fremde Sessions nicht in laufende Uploads schreiben können.

### Cleanup

Chunks werden automatisch gelöscht:
- Nach erfolgreicher Assembly und auch bei abgelehnten Dateien
- Nach 24 Stunden (Prüfung höchstens alle 15 Minuten bei neuen Uploads)

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

## Extension Points

Das Addon stellt folgende Extension Points bereit, um in den Upload-Prozess einzugreifen:

### `UPPY_AUTH_CHECK`

Wird bei Anfragen ohne Backend-Login aufgerufen und erlaubt eigene Authentifizierungs-Logik (z.B. Prüfung eigener Session-Variablen, IP-Whitelists, etc.). Gibt der EP einen Wert ungleich `null` zurück, entscheidet dieser statt API-Token/YCom.

**Seit 2.11.0:** Der EP ersetzt **nicht** die Signaturprüfung, die Sperrliste und die Ordner-Prüfungen – auch mit `true` braucht die Anfrage eine gültige, an die Session gebundene Signatur.

**Parameter:**
- `subject` (bool): `false` (Standard). Muss `true` zurückgeben, um Zugriff zu gewähren.
- `params` (array):
  - `token_valid` (bool): Ob das API-Token technisch korrekt war (aber ggf. nicht ausgereicht hat).
  - `request_token` (string|null): Das Token aus dem Request.
  - `session_token` (string): Das Token aus der Session.

**Beispiel:**

```php
rex_extension::register('UPPY_AUTH_CHECK', function (rex_extension_point $ep) {
    // Beispiel: Zugriff erlauben, wenn eine bestimmte Session-Variable gesetzt ist
    if (rex_session('custom_upload_permission', 'boolean', false)) {
        return true;
    }
    
    // Oder: IP-Whitelist prüfen
    if ($_SERVER['REMOTE_ADDR'] === '192.168.1.50') {
        return true;
    }
    
    return $ep->getSubject();
});
```

### `UPPY_UPLOAD_COMPLETE`

Wird aufgerufen, nachdem eine Datei erfolgreich hochgeladen und verarbeitet wurde (inkl. Verschieben in den Zielordner oder Hinzufügen zum Medienpool).

**Parameter:**
- `subject` (string): Der Dateiname der hochgeladenen Datei.
- `params` (array):
  - `original_file` (array): Das ursprüngliche `$_FILES`-Element.
  - `category_id` (int): Die ID der Medienpool-Kategorie (0 bei Custom Folder Uploads).
  - `metadata` (array): Die empfangenen Metadaten (Titel, Alt-Text, etc.).
  - `upload_dir` (string): Der Pfad zum Custom Upload Folder (falls verwendet).
  - `is_custom_folder` (bool): `true` wenn in einen Custom Folder hochgeladen wurde, `false` bei Medienpool.

**Beispiel:**

```php
rex_extension::register('UPPY_UPLOAD_COMPLETE', function (rex_extension_point $ep) {
    $filename = $ep->getSubject();
    $params = $ep->getParams();
    
    // Beispiel: Loggen des Uploads
    $category = $params['is_custom_folder'] 
        ? 'Custom Folder: ' . $params['upload_dir'] 
        : 'Mediapool Category: ' . $params['category_id'];
        
    rex_logger::logError(null, "Neue Datei hochgeladen: $filename in $category", __FILE__, __LINE__);
});
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
**Lösung:** Chunk-Größe (Einstellungen) muss kleiner als `upload_max_filesize` sein; Schreibrechte für `redaxo/data/addons/uppy/upload/` prüfen.

### Signatur-Fehler

**Problem:** "Unauthorized access" (403) oder "Invalid signature" (400)  
**Lösung:** 
- Feld mit `Utils::field()`, dem YForm-Feld oder `Signature::create()` ausgeben – ohne Signatur gibt es ohne Backend-Login keinen Upload
- Seite mit dem Upload-Feld nicht cachen (Signatur ist an die Session gebunden und 24 Stunden gültig)
- Session-Cookie muss beim Upload mitgesendet werden (gleiche Domain)
- Sicherstellen dass `secret` in der Uppy-Konfiguration konsistent ist

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
