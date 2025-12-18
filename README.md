# Uppy Uploader für REDAXO

Ein modernes File-Upload-AddOn für REDAXO CMS, basierend auf [Uppy 5.0](https://uppy.io/).

## Features

- ✅ Moderne, responsive File-Upload-Oberfläche mit Drag & Drop
- ✅ **Chunk-Upload** für große Dateien (konfigurierbar)
- ✅ **Client-seitige Bildoptimierung** (Resize, EXIF-Korrektur)
- ✅ **Webcam-Integration** für direkte Foto-Aufnahme
- ✅ **Metadaten-Verwaltung** mit MetaInfo-Integration
- ✅ **Mehrsprachige Metafelder** (metainfo_lang_fields Support)
- ✅ **Dark Theme** Support (auto + manuell)
- ✅ Backend- und Frontend-Integration
- ✅ YForm-Integration
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

- **Erlaubte Dateitypen**: MIME-Types (z.B. `image/*,application/pdf`)
- **Maximale Dateigröße**: In MB (Standard: 200 MB)
- **Maximale Anzahl Dateien**: Pro Upload-Session
- **Standard-Kategorie**: Mediapool-Kategorie für Uploads
- **Chunk-Upload**: Aktivieren für große Dateien (empfohlen)
- **Chunk-Größe**: In MB (Standard: 5 MB)
- **Bildoptimierung**: Client-seitige Resize-Funktion
  - Maximale Breite/Höhe
  - JPEG-Qualität (1-100)
  - EXIF-Orientierung korrigieren
- **Webcam**: Aktivieren für Foto-Aufnahme
- **Mediapool ersetzen**: Standard-Upload-Seite durch Uppy ersetzen

## Verwendung

### Im Backend

Uppy wird automatisch im Backend geladen. Verwende data-Attribute für Input-Felder:

```html
<input type="file" 
       data-widget="uppy" 
       data-uppy-category="1"
       data-uppy-max-files="10"
       data-uppy-max-filesize="200"
       data-uppy-allowed-types="image/*,application/pdf">
```

**Verfügbare Data-Attribute:**

- `data-widget="uppy"` - Aktiviert Uppy für das Input-Feld
- `data-uppy-category="ID"` - Mediapool-Kategorie (optional)
- `data-uppy-max-files="10"` - Maximale Anzahl Dateien (optional)
- `data-uppy-max-filesize="200"` - Max. Größe in MB (optional)
- `data-uppy-allowed-types="..."` - Erlaubte MIME-Types (optional)

### Im Frontend

Im Frontend musst du die Assets manuell im Template einbinden:

**Im Template HEAD-Bereich:**

```html
<link rel="stylesheet" href="<?= rex_url::addonAssets('uppy', 'uppy-core.min.css') ?>">
<link rel="stylesheet" href="<?= rex_url::addonAssets('uppy', 'uppy-dashboard.min.css') ?>">
<link rel="stylesheet" href="<?= rex_url::addonAssets('uppy', 'uppy-webcam.min.css') ?>">
<link rel="stylesheet" href="<?= rex_url::addonAssets('uppy', 'uppy-custom.css') ?>">
```

**Vor `</body>`:**

```html
<script src="<?= rex_url::addonAssets('uppy', 'uppy-frontend-bundle.js') ?>"></script>
```

**HTML für Upload-Feld:**

```html
<input type="file" 
       data-widget="uppy" 
       data-uppy-category="1"
       data-uppy-max-files="10"
       data-uppy-allowed-types="image/*,application/pdf">
```

### YForm-Integration

Das AddOn stellt ein YForm-Value-Feld bereit:

**Im Tablemanager:**

```
uppy_uploader|datei|Datei hochladen|1|10|200|image/*,application/pdf
```

**Im Code:**

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

MIT License - siehe [LICENSE](LICENSE)

Copyright (c) 2025 Friends Of REDAXO

## Credits

- Basiert auf [Uppy](https://uppy.io/) von Transloadit
- Entwickelt von [Friends Of REDAXO](https://github.com/FriendsOfREDAXO)

## Support

- **Issues & Bugs**: https://github.com/FriendsOfREDAXO/uppy/issues
- **Diskussionen**: https://github.com/FriendsOfREDAXO/uppy/discussions
- **Slack**: https://friendsofredaxo.slack.com

## Changelog

### Version 1.0.0

- ✅ Uppy 5.0 Integration mit lokalem Build
- ✅ Chunk-Upload für große Dateien
- ✅ Client-seitige Bildoptimierung
- ✅ Webcam-Support
- ✅ MetaInfo-Integration mit mehrsprachigen Feldern
- ✅ Dark Theme Support
- ✅ YForm-Integration
- ✅ Backend- und Frontend-Unterstützung
