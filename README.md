# Uppy Uploader für REDAXO

Ein modernes File-Upload-AddOn für REDAXO CMS, basierend auf [Uppy 5.0](https://uppy.io/).

![Screenshot](https://github.com/FriendsOfREDAXO/uppy/blob/assets/uppy_screen2.png?raw=true)

## Features

- ✅ Moderne, responsive File-Upload-Oberfläche mit Drag & Drop
- ✅ **Chunk-Upload** für große Dateien (konfigurierbar)
- ✅ **Client-seitige Bildoptimierung** (Resize, EXIF-Korrektur)
- ✅ **Image Editor** (optional) - Bildbearbeitung mit festen Seitenverhältnissen
- ✅ **Webcam-Integration** für direkte Foto-Aufnahme
- ✅ **Metadaten-Verwaltung** mit MetaInfo-Integration
- ✅ **Mehrsprachige Metafelder** (metainfo_lang_fields Support)
- ✅ **REDAXO Mediapool Widget-Kompatibilität** (REX_MEDIA, REX_MEDIALIST)
- ✅ **Dark Theme** Support (auto + manuell)
- ✅ Backend- und Frontend-Integration
- ✅ YForm-Integration mit Multi-Upload
- ✅ Optional: Ersetzt die Standard-Mediapool-Upload-Seite
- ✅ Lokaler Build (keine CDN-Abhängigkeiten)

## Voraussetzungen

- REDAXO >= 5.17.1
- PHP >= 8.1
- Node.js und npm (für Entwicklung/Build)

## Installation

### Über den Installer

1. AddOn über den REDAXO-Installer installieren
2. AddOn aktivieren

### Manuell (Development)

1. Repository in `redaxo/src/addons/` klonen
2. Im Terminal zum AddOn-Verzeichnis wechseln:
   ```bash
   cd redaxo/src/addons/uppy
   ```
3. NPM-Pakete installieren:
   ```bash
   npm install
   ```
4. Assets bauen:
   ```bash
   npm run build
   ```
5. AddOn im Backend aktivieren

## Konfiguration

Gehe zu **Uppy → Einstellungen** im Backend:

- **Erlaubte Dateitypen**: MIME-Types (z.B. `image/jpeg,image/png,image/gif,image/webp,image/svg+xml,application/pdf`)
- **Maximale Dateigröße**: In MB (Standard: 200 MB, abhängig von PHP `upload_max_filesize`)
- **Maximale Anzahl Dateien**: Pro Upload-Session
- **Standard-Kategorie**: Mediapool-Kategorie für Uploads
- **Bildoptimierung**: Client-seitige Resize-Funktion
  - Maximale Breite/Höhe
  - JPEG-Qualität (1-100)
  - EXIF-Orientierung korrigieren
- **Image Editor** (optional): Bildbearbeitung mit Zuschneiden, Drehen, Spiegeln
  - Feste Seitenverhältnisse: 1:1, 16:9, 4:3, 3:2, free
  - Perfekt für Avatare (1:1), Header (16:9) usw.
  - Basiert auf Cropper.js
  - Siehe [Demo-Seite](index.php?page=uppy/demo) für Beispiele
- **Webcam**: Aktivieren für Foto-Aufnahme
- **Mediapool ersetzen**: Standard-Upload-Seite durch Uppy ersetzen

## Verwendung

### Im Backend

Uppy wird automatisch im Backend geladen. Verwende data-Attribute für Input-Felder:

```html
<input type="file" 
       data-widget="uppy" 
       data-category-id="1"
       data-max-files="10"
       data-max-filesize="200"
       data-allowed-types="image/*,application/pdf">
```

**Verfügbare Data-Attribute:**

- `data-widget="uppy"` - Aktiviert Uppy für das Input-Feld
- `data-category-id="ID"` - Mediapool-Kategorie (optional)
- `data-max-files="10"` - Maximale Anzahl Dateien (optional)
- `data-max-filesize="200"` - Max. Größe in MB (optional)
- `data-allowed-types="..."` - Erlaubte MIME-Types (optional)
- `data-enable-webcam="true"` - Webcam aktivieren (optional)
- `data-enable-image-editor="true"` - Image Editor aktivieren (optional)

### Image Editor

Der Image Editor ermöglicht die Bildbearbeitung direkt im Browser **vor** dem Upload:

```html
<input type="file" 
       data-widget="uppy" 
       data-category-id="1"
       data-max-files="1"
       data-allowed-types="image/*"
       data-enable-image-editor="true">
```

**Features:**
- ✅ Zuschneiden mit festen Seitenverhältnissen (1:1, 16:9, 4:3, 3:2, free)
- ✅ Drehen (90° Schritte + freies Drehen)
- ✅ Spiegeln (horizontal/vertikal)
- ✅ Zoom In/Out
- ✅ Automatisches Öffnen bei Einzel-Uploads (maxFiles=1)
- ✅ Edit-Button bei Multi-Uploads
- ✅ Basiert auf Cropper.js (MIT License)

**Anwendungsfälle:**
- **Avatar-Uploads**: `data-max-files="1"` + `data-enable-image-editor="true"` für quadratische Bilder (1:1)
- **Header-Bilder**: Erzwingt 16:9 Format für einheitliche Website-Header
- **Produkt-Fotos**: Einheitliche Seitenverhältnisse für Shop-Kataloge

**Siehe Demo:** **Uppy → Demo** im Backend für Live-Beispiele

### Mediapool Widget-Integration

Das AddOn ist vollständig kompatibel mit REDAXO's REX_MEDIA und REX_MEDIALIST Widgets:

**REX_MEDIA (Einzeldatei):**
```php
REX_MEDIA[id="1" widget="1"]
```

**REX_MEDIALIST (Mehrere Dateien):**
```php
REX_MEDIALIST[id="1" widget="1"]
```

Bei Upload über den Mediapool-Button öffnet sich Uppy in einem Popup. Nach dem Upload erscheinen:
- **REX_MEDIA**: "Übernehmen"-Button (schließt Popup)
- **REX_MEDIALIST**: "Übernehmen"-Buttons + "Alle übernehmen" (Popup bleibt offen)

### Im Frontend

Im Frontend musst du die Assets manuell im Template einbinden:

**Im Template HEAD-Bereich:**

```html
<link rel="stylesheet" href="<?= rex_url::addonAssets('uppy', 'uppy-core.min.css') ?>">
<link rel="stylesheet" href="<?= rex_url::addonAssets('uppy', 'uppy-dashboard.min.css') ?>">
<link rel="stylesheet" href="<?= rex_url::addonAssets('uppy', 'uppy-webcam.min.css') ?>">
<link rel="stylesheet" href="<?= rex_url::addonAssets('uppy', 'uppy-custom.css') ?>">
```
mit vollständiger Multi-Upload-Unterstützung bereit:

**Im Tablemanager:**

```
uppy_uploader|datei|Datei hochladen|1|10|200|image/*,application/pdf|0
```

**Parameter:**
- `name` - Feldname
- `label` - Beschriftung
- `category_id` - Mediapool-Kategorie (0 = keine)
- `max_files` - Max. Anzahl Dateien
- `max_filesize` - Max. Größe in MB
- `allowed_types` - Erlaubte Dateitypen
- `enable_webcam` - Webcam aktivieren (1/0)

**Im Code:**

```php
$yform->setValueField('uppy_uploader', [
    'name' => 'datei',
    'label' => 'Datei hochladen',
    'category_id' => 1,         // Mediapool-Kategorie
    'max_files' => 10,          // Max. Anzahl Dateien
    'max_filesize' => 200,      // Max. Größe in MB
    'allowed_types' => 'image/*,application/pdf',
    'enable_webcam' => false    // Webcam aktivieren
]);
```

**Features:**
- Mehrfach-Upload (Werte kommagetrennt gespeichert)
- Vorschau bereits hochgeladener Dateien mit Thumbnails
- Einzelne Dateien entfernen mit Fade-Out-Animation
- Automatische Wert-Aktualisierung bei Upload
- Thumbnail-Anzeige in der Tabellen-Übersicht

**Ausgabe im Frontend:**

```php
$files = $item->getValue('datei'); // Kommagetrennte Dateinamen
$fileArray = array_filter(array_map('trim', explode(',', $files)));

foreach ($fileArray as $filename) {
    $media = rex_media::get($filename
```php
$yform->setValueField('uppy_uploader', [
    'name' => 'datei',
    'label' => 'Datei hochladen',
    'category_id' => 1,        // Mediapool-Kategorie
    'max_files' => 10,          // Max. Anzahl Dateien
    'max_filesize' => 200,      // Max. Größe in MB
    'allowed_types' => 'image/*,application/pdf'
]);
```

**Ausgabe im Frontend:**

```php
$files = $item->getValue('datei'); // Kommagetrennte Dateinamen
$fileArray = explode(',', $files);

foreach ($fileArray as $filename) {
    $media = rex_media::get(trim($filename));
    if ($media) {
        echo '<img src="' . $media->getUrl() . '" alt="' . $media->getTitle() . '">';
    }
}
```

## Metadaten

### Automatische MetaInfo-Erkennung

Das AddOn erkennt automatisch alle verfügbaren MetaInfo-Felder:
- Standard-Felder: `title`, `med_description`, `med_alt`, `med_copyright`
- Benutzerdefinierte Felder aus MetaInfo-AddOn
- **Mehrsprachige Felder** (metainfo_lang_fields):
  - `lang_text_all` (Einzeilige Texte)
  - `lang_textarea_all` (Mehrzeilige Texte)

### Metadaten-Editor

Nach dem Upload öffnet sich automatisch ein Modal mit allen verfügbaren Metadatenfeldern. 

**Mehrsprachige Felder** werden mit Collapse-UI angezeigt:
- Erste Sprache direkt sichtbar
- Button "Weitere Sprachen (X)" für zusätzliche Sprachen

### Label-Übersetzung

Felder mit `translate:` Präfix werden automatisch übersetzt:

```php
// In MetaInfo-Feld-Konfiguration
'label' => 'translate:pool_file_description'

// Wird zu:
rex_i18n::msg('pool_file_description')
```

## Technische Details

### Build-System

Das AddOn verwendet **esbuild** für lokalen JavaScript-Build (keine CDN-Abhängigkeiten):

```bash
# Pakete installieren
npm install

# Alle Assets bauen
npm run build

# Nur JavaScript
npm run build:js

# Nur CSS
npm run build:css
```

**Dependencies:**
- @uppy/core (^5.0.0)
- @uppy/dashboard (^5.0.0)
- @uppy/webcam (^5.0.0)
- @uppy/xhr-upload (^5.0.0)

### API-Endpunkte

Das AddOn registriert folgende API-Funktionen:

**Upload:**
```
index.php?rex-api-call=uppy_uploader
```

**Metadaten:**
```
index.php?rex-api-call=uppy_metadata&action=fields
index.php?rex-api-call=uppy_metadata&action=load&file=...
index.php?rex-api-call=uppy_metadata&action=save
```

### Namespace-Struktur

```
FriendsOfRedaxo\Uppy\
├── UppyUploadHandler     - Upload-API
└── UppyMetadataHandler   - Metadaten-API
```

### Client-seitige Features

- **Canvas-basierte Bildoptimierung** (Resize, Qualität)
- **EXIF-Orientierung** automatisch korrigieren
- **Chunk-Upload** für große Dateien (automatisch)
- **Progress-Tracking** mit visueller Anzeige
- **Dark Theme** (Auto-Detect + manuell)

## Beispiele

### Einfacher Upload im Modul

```php
// Modul-Eingabe
<input type="file" 
       name="REX_INPUT_VALUE[1]"
       data-widget="uppy" 
       data-uppy-category="1"
       data-uppy-max-files="5"
       data-uppy-allowed-types="image/*">
```

```php
// Modul-Ausgabe
<?php
$files = 'REX_VALUE[1]';
$fileArray = array_filter(explode(',', $files));

foreach ($fileArray as $filename):
    $media = rex_media::get(trim($filename));
    if ($media): ?>
        <figure>
            <img src="<?= $media->getUrl() ?>" 
                 alt="<?= $media->getValue('med_alt') ?>"
                 loading="lazy">
            <figcaption><?= $media->getTitle() ?></figcaption>
        </figure>
    <?php endif;
endforeach;
?>
```

### Upload mit Media Manager

```php
<?php
$files = 'REX_VALUE[1]';
$fileArray = array_filter(explode(',', $files));

foreach ($fileArray as $filename):
    $media = rex_media::get(trim($filename));
    if ($media): 
        $imageUrl = rex_media_manager::getUrl('artikel_teaser', $filename);
        ?>
        <img src="<?= $imageUrl ?>" 
             alt="<?= $media->getValue('med_alt') ?>">
    <?php endif;
endforeach;
?>
```

## Lizenz

MIT License 2.0.0-beta1 (2024-12-18)

- ✅ Uppy 5.0 Integration mit lokalem Build
- ✅ Chunk-Upload für große Dateien
- ✅ Client-seitige Bildoptimierung (Resize, EXIF-Korrektur)
- ✅ Webcam-Support für Foto-Aufnahme
- ✅ MetaInfo-Integration mit mehrsprachigen Feldern (metainfo_lang_fields)
- ✅ **REDAXO Mediapool Widget-Kompatibilität** (REX_MEDIA, REX_MEDIALIST)
- ✅ **"Alle übernehmen" Button für MEDIALIST**
- ✅ Dark Theme Support (Auto-Detect)
- ✅ **Verbesserte YForm-Integration** mit Multi-Upload
- ✅ **Friends of REDAXO Namespace** (FriendsOfRedaxo\Uppy)
- ✅ Backend- und Frontend-Unterstützung
- ✅ Kategorie-Auswahl mit Berechtigungsprüfung
- ✅ Lokaler Build ohne CDN-Abhängigkeiten

- **Issues & Bugs**: https://github.com/FriendsOfREDAXO/uppy/issues
- **Diskussionen**: https://github.com/FriendsOfREDAXO/uppy/discussions
- **Slack**: https://friendsofredaxo.slack.com

- ✅ **Image Editor** - Bildbearbeitung mit festen Seitenverhältnissen (1:1, 16:9, 4:3, 3:2, free)
- ✅ Demo-Seite für Image Editor Features
- ⚠️ Audio Plugin noch nicht verfügbar (wartet auf Uppy 5.x Kompatibilität)

## Changelog

### Version 2.0.0-beta1 (2024-12-18)

**Neue Features:**
- ✅ **Image Editor Integration** - Bildbearbeitung mit Cropper.js
  - Aktivierung per Feld via `data-enable-image-editor="true"`
  - Feste Seitenverhältnisse: 1:1, 16:9, 4:3, 3:2, free
  - Automatisches Öffnen bei Einzel-Uploads (maxFiles=1)
  - Zuschneiden, Drehen, Spiegeln, Zoomen
- ✅ **Demo-Seite** für Image Editor Features
- ✅ **Dynamische UI-Höhen** - Dashboard passt sich Dateianzahl an
- ✅ **Optimierte Preview-Bilder** - Max. 80px Höhe in FileCards
- ✅ **Explizite MIME-Types** - Bessere Dateivalidierung (SVG, WebP supported)

**Verbesserungen:**
- ✅ Image Editor wird pro Feld konfiguriert (nicht global)
- ✅ Dynamische Anzeige "Dateien hochladen (max. X)" aus `data-max-files`
- ✅ Console-Logs reduziert (nur kritische Fehler)
- ✅ Verbesserte Benutzerführung bei Bildbearbeitung
- ✅ Whitespace-Trimming bei allowedTypes-Parsing

**Technisch:**
- Uppy 5.2.0 Core
- @uppy/image-editor@4.1.0 mit Cropper.js
- @uppy/xhr-upload@5.0.0
- ESBuild lokaler Build (keine CDN-Abhängigkeiten)

**Geplante Features für 2.1.0:**
- 🔄 Chunked Uploads für große Dateien (TUS Protocol oder Custom Implementation)


## Credits

**Uppy Uploader AddOn für REDAXO**

- **Autor:** [Friends Of REDAXO](https://github.com/FriendsOfREDAXO)
- **Haupt-Entwickler:** [Thomas Skerbis](https://github.com/skerbis)
- **Basiert auf:** [Uppy](https://uppy.io/) by Transloadit (MIT License)
- **Image Editor:** [Cropper.js](https://fengyuanchen.github.io/cropperjs/) (MIT License)

**Besonderer Dank an:**
- Das REDAXO Core-Team für das ausgezeichnete CMS
- Transloadit für Uppy - eines der besten Upload-Frameworks
- Die REDAXO Community für Feedback und Testing

**Support & Community:**
- GitHub Issues: https://github.com/FriendsOfREDAXO/uppy/issues
- REDAXO Slack: https://friendsofredaxo.slack.com
- Forum: https://redaxo.org/forum/

**Lizenz:** MIT License

---

Made with ❤️ for the REDAXO Community
