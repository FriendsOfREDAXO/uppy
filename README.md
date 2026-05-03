# Uppy Uploader für REDAXO
Multiuploader für REDAXO basierend auf [Uppy 5.0](https://uppy.io/).

![Screenshot](https://github.com/FriendsOfREDAXO/uppy/blob/assets/uppy_screen2.png?raw=true)

## Features

- ✅ **Moderne UI**: Responsive File-Upload-Oberfläche mit Drag & Drop
- ✅ **Drag & Drop auf Widget**: Dateien direkt auf das Upload-Widget ziehen - Modal öffnet sich automatisch
- ✅ **Chunk-Upload**: Unterstützung für sehr große Dateien durch Aufteilung in kleine Pakete (umgeht PHP `upload_max_filesize`)
- ✅ **Client-seitige Bildoptimierung**: Automatisches Resizing und EXIF-Korrektur vor dem Upload
- ✅ **Image Editor**: Integrierte Bildbearbeitung (Zuschneiden, Drehen, Spiegeln) mit festen Seitenverhältnissen
- ✅ **Webcam-Integration**: Direkte Foto-Aufnahme im Browser
- ✅ **Metadaten-Verwaltung**: Automatische Erkennung und Editor für MetaInfo-Felder (inkl. Mehrsprachigkeit)
- ✅ **YCom-Medienberechtigungen** (optional): Frontend-Sichtbarkeit pro Upload-Sitzung vorbelegen (siehe unten)
- ✅ **Dark Theme**: Automatische Erkennung und manuelle Umschaltung
- ✅ **Lokaler Build**: Keine externen CDN-Abhängigkeiten (DSGVO-konform)

## Voraussetzungen

- REDAXO >= 5.17.1
- PHP >= 8.1

## Installation

1. AddOn über den REDAXO-Installer herunterladen und installieren.
2. AddOn aktivieren.

## Konfiguration

Die globalen Einstellungen befinden sich unter **Uppy → Einstellungen**:

- **Erlaubte Dateitypen**: Definition der zulässigen MIME-Types.
  - Der Button **"Dateitypen auswählen"** ermöglicht die Auswahl aus einer Liste gängiger Formate (Bilder, Dokumente, Video, Audio).
- **Maximale Dateigröße**: Obergrenze pro Datei in MB.
- **Maximale Anzahl Dateien**: Limit pro Upload-Vorgang.
- **Standard-Kategorie**: Zielkategorie im Mediapool (falls keine spezifische gewählt wird).
- **Chunk-Upload**: Ermöglicht den Upload von Dateien, die größer sind als das PHP-Limit (`upload_max_filesize`).
- **Bildoptimierung**: Aktiviert das client-seitige Verkleinern von Bildern (spart Bandbreite und Server-Ressourcen).
- **Image Editor**: Aktiviert die Bildbearbeitungswerkzeuge.

## Verwendung

### Im Backend

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

### Im Frontend / YForm

Für die Verwendung im Frontend (z.B. mit YForm) gibt es eine ausführliche Dokumentation:
👉 **[Frontend-Nutzung & YForm Integration](frontend_usage.md)**

Dort findest du Anleitungen für:
- Integration in YForm (Pipe, PHP, YORM)
- Nutzung in eigenen Formularen
- E-Mail-Versand von Anhängen (`uppy2email`)
- Frontend-Assets und Authentifizierung (via `FriendsOfRedaxo\Uppy\Utils::ensureApiSession()`)

## Backend-Attribute

**Verfügbare Attribute für das Widget:**
- `data-widget="uppy"`: Aktiviert das Dashboard Widget
- `data-category-id="1"`: Ziel-Kategorie ID im Mediapool
- `data-upload-dir="media/my_folder/"`: Ziel-Ordner im Dateisystem (relativ zum Root, deaktiviert Mediapool-Upload)
- `data-max-files="5"`: Maximale Anzahl Dateien
- `data-max-filesize="200"`: Max. Dateigröße in MB (nicht Bytes!)
- `data-allowed-types="image/*"`: Erlaubte Typen (MIME-Types oder Extensions)
- `data-enable-image-editor="true"`: Image Editor aktivieren (optional)
- `data-enable-webcam="true"`: Webcam-Integration aktivieren (optional)
- `data-allow-mediapool="true"`: Medienpool-Auswahl Button aktivieren (optional)
- `data-enable-file-links="true"`: Optionale Datei-Anzeige/Download fuer `upload_folder` aktivieren (nur Backend)
- `data-file-access-mode="download|both"`: Nur Download oder Ansehen+Download (nur bei `data-enable-file-links="true"`)
- `data-lang="de_DE"`: Sprache erzwingen (optional)
- `max_files` - Maximale Anzahl Dateien (optional, Standard aus Einstellungen)
- `max_filesize` - Maximale Dateigröße in MB (optional, Standard aus Einstellungen)
- `allowed_types` - Erlaubte Dateitypen als JSON (optional, Standard aus Einstellungen)
- `enable_webcam` - Webcam aktivieren: `1` oder `0` (optional, Standard aus Einstellungen)
- `enable_image_editor` - Image Editor aktivieren: `1` oder `0` (optional, Standard aus Einstellungen)
- `allow_mediapool` - Medienpool-Auswahl aktivieren: `1` oder `0` (optional, Standard: deaktiviert)

**Beispiel im Tablemanager:**
```
Field: uppy_uploader
Name: gallery
Label: Bildergalerie
category_id: 5
upload_folder: media/galleries/
max_files: 10
enable_image_editor: 1
allow_mediapool: 1
```

> **Info:** Beim Upload in einen Ordner wird automatisch geprüft, ob die Datei bereits existiert. Falls ja, wird ein Suffix angehängt (z.B. `bild_1.jpg`), um Überschreiben zu verhindern.

### Optionale Medienanzeige bei `upload_folder`

Wenn Dateien nicht in den Mediapool, sondern in einen eigenen Ordner (z.B. `public/redaxo/data/...`) hochgeladen werden, kann die Anzeige im Widget optional aktiviert werden:

- **Download-Link je Datei**
- **Optionaler View-Modus** (`file_access_mode=both`) mit Lightbox fuer Bilder und Videos

Die Auslieferung erfolgt ueber einen internen Backend-API-Endpunkt (`rex-api-call=uppy_file_access`) mit Session-Pruefung und Pfadvalidierung.

**Wichtig zur Pfadangabe:**
- `upload_folder` wird immer relativ zum Webroot aufgeloest und darf bewusst auch `..` enthalten.
- Beispiele: `redaxo/data`, `../data/uploads`, `../../meine_daten`.
- Damit kannst du je nach Hosting-Setup frei bestimmen, ob innerhalb oder oberhalb des Webroots gespeichert wird.

Die hochgeladenen Dateien werden als komma-separierte Liste der Dateinamen gespeichert. In der Listenansicht werden Vorschaubilder (bei Bildern) oder Icons (bei anderen Dateitypen) mit einem kompakten Design angezeigt.

**Listenansicht Features:**
- Zeigt bei Einzeldateien: Thumbnail/Icon + Medienpool-Titel (bei Mediapool-Upload) oder Dateiname (bei Ordner-Upload)
- Zeigt bei mehreren Dateien: Icon (Bilder/Dokumente) + Anzahl + Dateiendungen
- Kompaktes Design mit inline-flex Layout für bessere Übersicht in Tabellen

#### Variante 3: Über setValueField()

Alternativ kann das Feld auch programmatisch über `setValueField()` hinzugefügt werden:

```php
$yform->setValueField('uppy_uploader', [
    'uppyupload',           // Name des Felds
    'upload',               // Label
    '0',                    // Kategorie-ID (0 = Standard)
    'media/test/',          // Ziel-Ordner (Leer = Mediapool)
    '10',                   // Max. Anzahl Dateien (0 = unbegrenzt)
    '200',                  // Max. Dateigröße in MB
    'image/*,application/pdf', // Erlaubte Dateitypen
    '1',                    // Image Editor aktivieren (1/0)
    '0',                    // Webcam aktivieren (1/0)
    '1',                    // Medienpool-Button aktivieren (1/0)
    '1',                    // Datei-Anzeige/Download fuer upload_folder aktivieren (1/0)
    'both'                  // Zugriffstyp: download|both
]);
```

**Parameter:**
1. Feldname
2. Label
3. Kategorie-ID (0 = Standard aus Einstellungen)
4. Ziel-Ordner (relativ zum Root, z.B. `media/test/`)
5. Max. Anzahl Dateien (0 = unbegrenzt)
6. Max. Dateigröße in MB
7. Erlaubte Dateitypen (kommasepariert)
8. Image Editor (1 = aktiviert, 0 = deaktiviert)
9. Webcam (1 = aktiviert, 0 = deaktiviert)
10. Medienpool-Button (1 = aktiviert, 0 = deaktiviert)
11. Datei-Anzeige/Download fuer Custom-Ordner (1 = aktiviert, 0 = deaktiviert)
12. Zugriffstyp: `download` oder `both`

### Automatisches Cleanup

Optional kann das automatische Löschen nicht mehr verwendeter Dateien aktiviert werden (**Uppy → Einstellungen → Automatisches Cleanup**). 

**Wichtig:** Diese Funktion löscht Dateien **automatisch und unwiderruflich** aus dem Mediapool, sobald YForm-Einträge gespeichert werden und Dateien aus `uppy_uploader`-Feldern entfernt wurden.

**Sicherheitsmerkmale:**
- Prüft vor dem Löschen, ob die Datei noch in anderen YForm-Feldern oder MetaInfo-Feldern verwendet wird
- Ist standardmäßig **deaktiviert**
- Protokolliert alle Löschvorgänge im REDAXO-Logger

**Schutz vor versehentlichem Löschen:**
Das AddOn verhindert auch das manuelle Löschen von Dateien im Mediapool, die noch in YForm-Feldern verwendet werden (via `MEDIA_IS_IN_USE` Extension Point).

> **Tipp:** Mit `data-allow-mediapool="true"` (JSON), `allow_mediapool: 1` (Tablemanager) oder Parameter 9 = '1' (setValueField) erscheint ein zusätzlicher Button, um bestehende Dateien aus dem Mediapool auszuwählen, statt neue hochzuladen.

### Frontend Integration

Für die Verwendung im Frontend (z.B. in eigenen Formularen) steht das **Custom Widget** zur Verfügung. Es ist leichtgewichtig und initialisiert sich automatisch.

#### Authentifizierung

Der Upload-Handler unterstützt **drei Authentifizierungsmethoden** (in dieser Reihenfolge geprüft):

**1. Backend-User (automatisch)**
- Wenn ein REDAXO Backend-User eingeloggt ist, funktioniert der Upload automatisch
- Keine zusätzliche Konfiguration nötig

**2. API-Token (Session-basiert)**
- Für öffentliche Frontend-Formulare ohne User-Login
- Token wird serverseitig in der PHP-Session gespeichert (nie im Browser sichtbar!)

Am einfachsten über den Helper (empfohlen):
```php
// Im Template EINMALIG ausführen
\FriendsOfRedaxo\Uppy\Utils::ensureApiSession();
```

*Alternative (manuell):*
```php
rex_set_session('uppy_token', rex_config::get('uppy', 'api_token'));
```

**3. YCom-User (automatisch)**
- Wenn YCom installiert ist und ein User eingeloggt ist
- Funktioniert automatisch ohne weitere Konfiguration

> **Sicherheitshinweis:** Der Session-Token wird nie im Browser-DOM oder in Cookies übertragen. 
> Er bleibt serverseitig in der PHP-Session und wird bei jedem Upload validiert.

#### Integration

1. **Assets einbinden:**
   Das CSS und JS Bundle muss im Template oder Modul eingebunden werden.

   ```php
   $uppy = rex_addon::get('uppy');
   
   // CSS Bundle (im <head>)
   echo '<link rel="stylesheet" href="'. $uppy->getAssetsUrl('dist/uppy-frontend-bundle.css') .'">';
   
   // JS Bundle (am Ende des <body>)
   echo '<script src="'. $uppy->getAssetsUrl('dist/uppy-custom-widget-bundle.js') .'"></script>';
   ```

2. **HTML Markup:**
   Ein `input type="hidden"` mit der Klasse `uppy-upload-widget` wird verwendet. Das Skript erkennt diese Klasse automatisch.

   ```html
   <input 
       type="hidden" 
       class="uppy-upload-widget"
       name="my_upload_field" 
       value=""
       data-category-id="1"
       data-max-files="5"
       data-max-filesize="10"
       data-allowed-types="image/jpeg,image/png"
   >
   ```

3. **Verfügbare Attribute (Frontend Widget):**
   - `data-category-id="1"`: Ziel-Kategorie ID im Mediapool
   - `data-max-files="5"`: Maximale Anzahl Dateien
   - `data-max-filesize="10"`: Max. Dateigröße in MB
   - `data-allowed-types="image/*"`: Erlaubte Typen (MIME-Types oder Extensions)
   - `data-enable-image-editor="true"`: Image Editor aktivieren (optional)
   - `data-enable-webcam="true"`: Webcam aktivieren (optional)
   - `data-allow-mediapool="true"`: Medienpool-Auswahl Button aktivieren (optional)

### Medienpool-Integration

Mit `data-allow-mediapool="true"` wird ein zusätzlicher Button "Aus Medienpool wählen" angezeigt:

```html
<input 
    type="hidden" 
    class="uppy-upload-widget"
    name="my_upload_field" 
    value=""
    data-category-id="1"
    data-max-files="5"
    data-allow-mediapool="true"
>
```

**Features:**
- Auswahl bestehender Dateien aus dem REDAXO Mediapool
- Unterstützt Einzelauswahl (max_files=1) und Mehrfachauswahl
- Respektiert die `max-files` Einschränkung automatisch
- Zeigt eine Anzahl-Anzeige (z.B. "Anzahl: 2/5") neben den Buttons

## YCom-Medienberechtigungen beim Upload (optional)

Wenn das Plugin **`ycom/media_auth`** aktiv ist, kann auf der Upload-Seite ein optionales Panel eingeblendet werden, mit dem berechtigte Backend-User die Frontend-Sichtbarkeit für alle in der aktuellen Sitzung neu hochgeladenen Dateien vorbelegen können.

**Aktivieren:**

1. **Uppy → Einstellungen** öffnen.
2. Schalter **„YCom-Medienberechtigungen beim Upload setzen"** aktivieren (erscheint nur, wenn `ycom/media_auth` installiert ist).
3. In der Benutzerverwaltung den gewünschten Backend-Usern die Permission **`uppy[ycom_media_auth]`** zuweisen (oder Admin sein).

**Bedienung:**

- Auf der Upload-Seite erscheint nach der Kategorieauswahl das ausklappbare Panel **„YCom-Medienberechtigungen (optional)"**.
- Auswahlmöglichkeiten:
  - **Frontend-Sichtbarkeit**: `Alle (öffentlich)` oder `Nur eingeloggte YCom-Benutzer`.
  - **Gruppenprüfung** (nur sichtbar bei „nur eingeloggte" und wenn `ycom/group` aktiv ist): „in allen Gruppen", „in einer der Gruppen", „in keiner Gruppe", „für alle Gruppen".
  - **YCom-Gruppen**: Mehrfachauswahl der zu prüfenden Gruppen (nur sichtbar, wenn ein konkreter Gruppentyp gewählt ist).
- Die Werte werden pro Backend-Session gespeichert und automatisch auf jede neu hochgeladene Datei in den Spalten `ycom_auth_type`, `ycom_group_type` und `ycom_groups` von `rex_media` geschrieben.
- Standard-Verhalten ohne Auswahl: alle Dateien sind öffentlich (`ycom_auth_type = 0`).

**Sicherheit:** Server- und clientseitig wird stets geprüft, ob das Plugin verfügbar ist, das Setting aktiv ist, eine Backend-Session besteht und die Permission gesetzt ist. Anderenfalls hat das Panel keine Wirkung.

## Sicherheit: Manipulationsschutz (Signierte Uploads)

Standardmäßig werden Einschränkungen wie `data-max-filesize` oder `data-allowed-types` nur client-seitig im Browser geprüft. Ein versierter Nutzer könnte diese Werte über die Entwicklertools manipulieren.

Um dies zu verhindern, lassen sich die Parameter serverseitig signieren. Dabei wird ein Hash über die erlaubten Werte generiert. Der Server prüft beim Upload, ob die Signatur zu den gesendeten Parametern passt.

> **Wichtig:** Bei Verwendung über **YForm (`uppy_uploader`)** oder im **REDAXO-Backend** kümmert sich das AddOn **automatisch** um die Erstellung und Prüfung der Signaturen. Du musst hier nichts weiter tun. Die manuelle Erstellung (siehe unten) ist nur für eigene Frontend-Implementierungen erforderlich.

1. Alle sensiblen Parameter werden serverseitig mit einem geheimen Schlüssel (`rex_config`) als HMAC-SHA256 gehasht.
2. Die Signatur wird im Attribut `data-uppy-signature` übergeben.
3. Der Server validiert vor der finalen Verarbeitung der Datei, ob die mitgelieferten Parameter mit der Signatur übereinstimmen.

**Validierte Parameter:**
*   `category_id`
*   `upload_dir`
*   `allowed_types`
*   `max_filesize`

Wenn die Signatur fehlt oder ungültig ist, lehnt der Server den Upload mit einem Fehler (400 Bad Request) ab.

### Funktionsweise
1. Die Parameter werden in PHP definiert.
2. Die Klasse `FriendsOfRedaxo\Uppy\Signature` erstellt einen Hash (HMAC-SHA256) mit einem geheimen Schlüssel.
3. Die Signatur wird im Attribut `data-uppy-signature` übergeben.
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
    'max_filesize' => 500, // in MB
    'upload_dir' => 'media/protected/' // Optional: Zielordner schützen
];

// Signatur erstellen
$signature = Signature::create($params);
?>


<!-- Widget mit Signatur ausgeben -->
<input type="hidden" 
       name="REX_INPUT_VALUE[1]" 
       value=""
       data-widget="uppy" 
       data-category-id="<?= $params['category_id'] ?>"
       data-allowed-types="<?= $params['allowed_types'] ?>"
       data-max-filesize="<?= $params['max_filesize'] ?>"
       data-upload-dir="<?= $params['upload_dir'] ?>"
       data-uppy-signature="<?= $signature ?>">
```

> **Hinweis:** Wenn eine Signatur vorhanden ist, werden die signierten Parameter (`category_id`, `allowed_types`, `max_filesize`, `upload_dir`) serverseitig strikt durchgesetzt.

## Demo Seite

Eine ausführliche Demo mit Live-Beispielen und Quellcode befindet sich im Backend unter **Uppy → Demo**.

## Technische Details

### Metadaten & Mehrsprachigkeit
Das AddOn unterstützt automatisch alle im System definierten Metainfo-Felder (`med_...`). 
Mehrsprachige Felder werden erkannt und können direkt im Upload-Dialog für alle Sprachen gepflegt werden. Die Speicherung erfolgt als JSON-String in der Datenbank.

#### Unterstützte Feldtypen
Das Uppy AddOn erkennt automatisch folgende MetaInfo-Feldtypen und rendert diese mit passender UI:

**Text-Felder:**
- `text` - Einzeiliges Textfeld
- `textarea` - Mehrzeiliges Textfeld
- `select` - Auswahlfeld (Dropdown)

**Datums-Felder (NEU):**
- `date` - Datumswahl mit HTML5 Date-Picker (nur Datum, ohne Uhrzeit)
- `datetime` - Datum + Uhrzeit mit HTML5 DateTime-Picker
- `time` - Nur Uhrzeit mit HTML5 Time-Picker

**Mehrsprachige Felder:**
- `lang_text` - Mehrsprachiges Textfeld (alle Sprachen)
- `lang_textarea` - Mehrsprachiges Textarea (alle Sprachen)

**Vorteile der HTML5 Date-Picker:**
- ✅ Native Browser-UI (touchscreen-optimiert für mobile Geräte)
- ✅ Automatische Validierung
- ✅ Keine zusätzlichen JavaScript-Abhängigkeiten
- ✅ Bessere UX als Select-Dropdowns
- ✅ Speicherung als Unix-Timestamp (kompatibel mit REDAXO Standard)

> **Hinweis:** Date/DateTime/Time Felder werden als Unix-Timestamp gespeichert, genau wie die Standard REDAXO MetaInfo-Felder. Die Konvertierung erfolgt automatisch.

### Chunk Upload
Der Chunk-Upload teilt große Dateien in kleine Blöcke (Standard: 5MB) und sendet diese sequenziell an den Server. Dies verhindert Timeouts und Memory-Limits bei großen Uploads.

### SVG Support
SVG-Dateien werden automatisch vom client-seitigen Resizing ausgeschlossen, um eine Rasterisierung (Umwandlung in PNG) zu verhindern. Sie bleiben als Vektorgrafiken erhalten.

## Entwicklung & Build-Prozess

Das AddOn verwendet moderne Frontend-Tools, um JavaScript und CSS zu bündeln. Es werden keine externen CDNs verwendet (DSGVO-konform).

### Voraussetzungen
- Node.js (>= 18)
- npm

### Installation der Abhängigkeiten
```bash
cd redaxo/src/addons/uppy
npm install
```

### Build-Befehle
```bash
# Kompletten Build ausführen (JS & CSS Bundles)
npm run build

# Nur JavaScript bauen
npm run build:js

# Nur CSS-Bundles erstellen
node build.js  # erstellt uppy-backend-bundle.css & uppy-frontend-bundle.css
```

### Was wird gebaut?

Der Build-Prozess (`build.js`) erstellt folgende Bundles:

**JavaScript:**
- `assets/dist/uppy-backend-bundle.js` - Backend Dashboard Widget
- `assets/dist/uppy-custom-widget-bundle.js` - Frontend Custom Widget

**CSS:**
- `assets/dist/uppy-backend-bundle.css` - Alle Backend Styles (8 Dateien kombiniert)
- `assets/dist/uppy-frontend-bundle.css` - Minimales Frontend CSS

### Ordnerstruktur

```text
assets/
├── src/                  # JavaScript Quellcode (ES Modules)
│   ├── uppy-backend.js       # Backend Dashboard Widget
│   ├── uppy-custom-widget.js # Custom Widget (Frontend & Backend)
│   ├── chunk-uploader.js     # Chunk-Upload Implementierung
│   └── uppy-dashboard-styles.css # Custom Animations
│
├── dist/                 # Kompilierte Bundles (generiert via build.js)
│   ├── uppy-backend-bundle.js
│   ├── uppy-backend-bundle.css
│   ├── uppy-custom-widget-bundle.js
│   └── uppy-frontend-bundle.css
│
├── css/                  # Uppy Core Styles (kopiert aus node_modules)
│   ├── uppy-core.min.css
│   ├── uppy-dashboard.min.css
│   ├── uppy-custom.css
│   └── ...
│
└── locales/              # Sprachdateien (kopiert aus node_modules)
    ├── de_DE.min.js
    └── en_US.min.js
```

## Entwickler & API

Das Addon bietet umfangreiche Möglichkeiten zur Erweiterung und Integration:

- **API-Dokumentation**: Detaillierte Beschreibung der PHP-Klassen, Endpunkte und Extension Points finden Sie in [API.md](API.md).
- **Frontend-Integration**: Anleitung zur Nutzung von Uppy in eigenen Frontend-Formularen in [frontend_usage.md](frontend_usage.md).

## Autor

**Friends Of REDAXO**

* http://www.redaxo.org
* https://github.com/FriendsOfREDAXO

**Projektleitung**

[Thomas Skerbis](https://github.com/skerbis)

## Lizenz
MIT
