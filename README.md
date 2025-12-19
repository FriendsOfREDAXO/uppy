# Uppy Uploader für REDAXO
Multiuploader für REDAXO basierend auf [Uppy 5.0](https://uppy.io/).

![Screenshot](https://github.com/FriendsOfREDAXO/uppy/blob/assets/uppy_screen2.png?raw=true)

## Features

- ✅ **Moderne UI**: Responsive File-Upload-Oberfläche mit Drag & Drop
- ✅ **Chunk-Upload**: Unterstützung für sehr große Dateien durch Aufteilung in kleine Pakete (umgeht PHP `upload_max_filesize`)
- ✅ **Client-seitige Bildoptimierung**: Automatisches Resizing und EXIF-Korrektur vor dem Upload
- ✅ **Image Editor**: Integrierte Bildbearbeitung (Zuschneiden, Drehen, Spiegeln) mit festen Seitenverhältnissen
- ✅ **Webcam-Integration**: Direkte Foto-Aufnahme im Browser
- ✅ **Metadaten-Verwaltung**: Automatische Erkennung und Editor für MetaInfo-Felder (inkl. Mehrsprachigkeit)
- ✅ **Dark Theme**: Automatische Erkennung und manuelle Umschaltung
- ✅ **Lokaler Build**: Keine externen CDN-Abhängigkeiten (DSGVO-konform)

## Voraussetzungen

- REDAXO >= 5.17.1
- PHP >= 8.1

## Installation

1. AddOn über den REDAXO-Installer herunterladen und installieren.
2. AddOn aktivieren.

## Konfiguration

Die globalen Einstellungen finden Sie unter **Uppy → Einstellungen**:

- **Erlaubte Dateitypen**: Definieren Sie die zulässigen MIME-Types.
  - *Neu:* Nutzen Sie den Button **"Dateitypen auswählen"**, um bequem aus einer Liste gängiger Formate (Bilder, Dokumente, Video, Audio) zu wählen.
- **Maximale Dateigröße**: Obergrenze pro Datei in MB.
- **Maximale Anzahl Dateien**: Limit pro Upload-Vorgang.
- **Standard-Kategorie**: Zielkategorie im Mediapool (falls keine spezifische gewählt wird).
- **Chunk-Upload**: Ermöglicht den Upload von Dateien, die größer sind als das PHP-Limit (`upload_max_filesize`).
- **Bildoptimierung**: Aktiviert das client-seitige Verkleinern von Bildern (spart Bandbreite und Server-Ressourcen).
- **Image Editor**: Aktiviert die Bildbearbeitungswerkzeuge.

## Verwendung

### Im Backend (Module / AddOns)

Uppy kann einfach über `data`-Attribute in einem `hidden` Input-Feld aktiviert werden. Das Widget kümmert sich um die Darstellung und das Speichern der Dateinamen.

```html
<input type="hidden" 
       name="REX_INPUT_VALUE[1]" 
       value="REX_VALUE[1]"
       data-widget="uppy" 
       data-category-id="1"
       data-max-files="10"
       data-max-filesize="200"
       data-allowed-types="image/*,application/pdf">
```

**Verfügbare Attribute:**
- `data-widget="uppy"`: Aktiviert das Widget
- `data-category-id="1"`: Ziel-Kategorie ID im Mediapool
- `data-max-files="5"`: Maximale Anzahl Dateien
- `data-allowed-types="image/*"`: Erlaubte Typen (MIME-Types oder Extensions)
- `data-enable-image-editor="true"`: Image Editor aktivieren
- `data-lang="de_DE"`: Sprache erzwingen (optional)

### YForm Integration

In YForm können Sie das Uppy-Widget einfach über das Feld "Attribute" konfigurieren. Hierzu wird ein JSON-Objekt verwendet.

**Beispiel 1: Standard Dashboard**
```json
{
    "data-widget": "uppy",
    "data-category-id": "1",
    "data-max-files": "10"
}
```

**Beispiel 2: Custom Widget (Minimal UI)**
```json
{
    "class": "uppy-upload-widget",
    "data-category-id": "1",
    "data-max-files": "5",
    "data-allowed-types": "image/*"
}
```

### Frontend Integration

Für die Verwendung im Frontend (z.B. in eigenen Formularen) steht das **Custom Widget** zur Verfügung. Es ist leichtgewichtig und initialisiert sich automatisch.

1. **Assets einbinden:**
   Laden Sie das CSS und JS Bundle in Ihrem Template oder Modul.

   ```php
   $uppy = rex_addon::get('uppy');
   
   // CSS
   echo '<link rel="stylesheet" href="'. $uppy->getAssetsUrl('css/uppy-custom-widget.css') .'">';
   
   // JS (am Ende des Body)
   echo '<script src="'. $uppy->getAssetsUrl('dist/uppy-custom-widget-bundle.js') .'"></script>';
   ```

2. **HTML Markup:**
   Verwenden Sie ein `input type="hidden"` mit der Klasse `uppy-upload-widget`. Das Skript erkennt diese Klasse automatisch.

   ```html
   <input 
       type="hidden" 
       class="uppy-upload-widget"
       name="my_upload_field" 
       value=""
       data-category-id="1"
       data-max-files="5"
       data-allowed-types="image/jpeg,image/png"
   >
   ```

## Sicherheit: Manipulationsschutz (Signierte Uploads)

Standardmäßig werden Einschränkungen wie `data-max-filesize` oder `data-allowed-types` nur client-seitig im Browser geprüft. Ein versierter Nutzer könnte diese Werte über die Entwicklertools manipulieren.

Um dies zu verhindern, können Sie die Parameter serverseitig signieren. Dabei wird ein Hash über die erlaubten Werte generiert. Der Server prüft beim Upload, ob die Signatur zu den gesendeten Parametern passt.

### Funktionsweise
1. Sie definieren die Parameter in PHP.
2. Die Klasse `FriendsOfRedaxo\Uppy\Signature` erstellt einen Hash (HMAC-SHA256) mit einem geheimen Schlüssel.
3. Sie übergeben die Signatur im Attribut `data-uppy-signature`.
4. Das Widget sendet die Signatur und die Parameter beim Upload mit.
5. Der Server validiert die Signatur und lehnt den Upload ab, wenn die Regeln verletzt wurden.

### Beispiel

```php
<?php
use FriendsOfRedaxo\Uppy\Signature;

// Parameter definieren
$params = [
    'category_id' => 1,
    'allowed_types' => 'image/jpeg,image/png',
    'max_filesize' => 500 * 1024 // 500 KB in Bytes
];

// Signatur erstellen
$signature = Signature::create($params);
?>

<!-- Widget mit Signatur ausgeben -->
<input type="hidden" 
       name="REX_INPUT_VALUE[1]" 
       value="REX_VALUE[1]"
       data-widget="uppy" 
       data-category-id="<?= $params['category_id'] ?>"
       data-allowed-types="<?= $params['allowed_types'] ?>"
       data-max-filesize="<?= $params['max_filesize'] ?>"
       data-uppy-signature="<?= $signature ?>">
```

> **Hinweis:** Wenn eine Signatur vorhanden ist, werden die signierten Parameter (`category_id`, `allowed_types`, `max_filesize`) serverseitig strikt durchgesetzt.

## Demo Seite

Eine ausführliche Demo mit Live-Beispielen und Quellcode finden Sie im Backend unter **Uppy → Demo**.

## Technische Details

### Metadaten & Mehrsprachigkeit
Das AddOn unterstützt automatisch alle im System definierten Metainfo-Felder (`med_...`). 
Mehrsprachige Felder werden erkannt und können direkt im Upload-Dialog für alle Sprachen gepflegt werden. Die Speicherung erfolgt als JSON-String in der Datenbank.

### Chunk Upload
Der Chunk-Upload teilt große Dateien in kleine Blöcke (Standard: 5MB) und sendet diese sequenziell an den Server. Dies verhindert Timeouts und Memory-Limits bei großen Uploads.

### SVG Support
SVG-Dateien werden automatisch vom client-seitigen Resizing ausgeschlossen, um eine Rasterisierung (Umwandlung in PNG) zu verhindern. Sie bleiben als Vektorgrafiken erhalten.

### Build
Das Projekt nutzt `esbuild` für das Bundling der Assets.
```bash
## Entwicklung & Build-Prozess

Das AddOn verwendet moderne Frontend-Tools, um JavaScript und CSS zu bündeln. Es werden keine externen CDNs verwendet (DSGVO-konform).

### Voraussetzungen
- Node.js (>= 18)
- NPM

### Installation der Abhängigkeiten
```bash
cd redaxo/src/addons/uppy
npm install
```

### Build-Befehle
```bash
# Kompletten Build ausführen (JS & CSS)
npm run build

# Nur JavaScript bauen (via esbuild)
npm run build:js

# Nur CSS kopieren und bereitstellen
npm run build:css
```

### Ordnerstruktur

Die Assets sind strikt nach Quelle und Ziel getrennt, um die Entwicklung übersichtlich zu halten:

```text
assets/
├── src/                  # JavaScript Quellcode (ES Modules)
│   ├── uppy-backend.js       # Hauptlogik für das Backend
│   └── uppy-custom-widget.js # Logik für das Custom Widget (Frontend & Backend)
│
├── dist/                 # Kompilierte, minifizierte Bundles (Production)
│   ├── uppy-backend-bundle.js
│   └── uppy-custom-widget-bundle.js
│
├── css/                  # Stylesheets (Uppy Core + Plugins + Custom)
│   ├── uppy-core.min.css
│   ├── uppy-custom.css
│   └── ...
│
└── locales/              # Sprachdateien
```

Der Build-Prozess (`build.js`) nimmt die Dateien aus `assets/src/`, bündelt sie mit `esbuild` und speichert das Ergebnis in `assets/dist/`. CSS-Dateien werden aus `node_modules` nach `assets/css/` kopiert.
```

## Autor

**Friends Of REDAXO**

* http://www.redaxo.org
* https://github.com/FriendsOfREDAXO

**Projektleitung**

[Thomas Skerbis](https://github.com/skerbis)

## Lizenz
MIT
