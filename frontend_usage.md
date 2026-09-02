# Uppy im Frontend

Es gibt zwei Wege, Uppy im Frontend einzusetzen: **direkt per PHP-Helper** (kein YForm
nötig, empfohlen für einfache Upload-Formulare) oder **eingebettet in YForm**
(Pipe-Notation, `setValueField`, YORM-Datasets).

## Voraussetzungen (Immer erforderlich)

Damit Uppy im Frontend funktioniert, müssen zwei Dinge erledigt sein: Assets einbinden
und ein Session-Token für nicht angemeldete Besucher setzen. Beides erledigt ein
einziger Aufruf ganz oben im Template:

```php
<?php
// Setzt (falls konfiguriert) den API-Token in die Session und gibt CSS/JS-Tags zurück.
// Die serverseitige Rechteprüfung (Token/Signatur/YCom) bleibt davon unberührt -
// ohne konfigurierten Token in den Uppy-Einstellungen passiert hier gar nichts.
echo \FriendsOfRedaxo\Uppy\Utils::init();
?>
```

Das gibt aus:
```html
<link rel="stylesheet" href="/assets/addons/uppy/dist/uppy-frontend-bundle.css?v=...">
<script src="/assets/addons/uppy/dist/uppy-custom-widget-bundle.js?v=..." defer></script>
```

`Utils::init()` lädt bewusst das schlanke Frontend-CSS-Bundle (nicht das größere
Backend-Bundle mit Dark-Mode/Dashboard-Styles) und das Custom-Widget-JS, das jedes
Feld mit `class="uppy-upload-widget"` beim Laden automatisch initialisiert - eigenes
Init-JavaScript im Template ist nicht nötig.

Falls du Assets und Session-Token getrennt steuern willst, gibt es auch
`Utils::assets()` (nur die Tags) und `Utils::ensureApiSession()` (nur der Token) einzeln.

---

## Variante 0: Ohne YForm (direkt per PHP)

Für ein einfaches Upload-Formular ohne YForm reicht `Utils::field()` - kein
Pipe-String, keine data-Attribute von Hand, keine manuelle Signatur:

```php
<?php
use FriendsOfRedaxo\Uppy\Utils;

echo Utils::init();

if (rex_post('submit', 'string')) {
    $files = array_filter(explode(',', rex_post('uploads', 'string', '')));
    foreach ($files as $filename) {
        // z.B. in der eigenen Tabelle speichern, an eine E-Mail hängen, ...
    }
}
?>
<form method="post">
    <?= Utils::field('uploads', [
        'category_id'   => 1,        // Mediapool-Kategorie
        'max_files'     => 5,
        'max_filesize'  => 10,       // MB
        'allowed_types' => 'image/*,application/pdf',
    ]) ?>
    <button type="submit" name="submit" value="1">Absenden</button>
</form>
```

`Utils::field($name, $options, $value)` kennt dieselben Optionen wie das YForm-Feld
(`category_id`, `upload_folder`, `max_files`, `max_filesize`, `allowed_types`,
`enable_webcam`, `enable_image_editor`, `allow_mediapool`, `show_file_access`,
`file_access_mode`), baut die Sicherheits-Signatur automatisch und fällt für alles,
was nicht angegeben wird, auf die globalen Uppy-Einstellungen zurück.

---

## Variante 1: Pipe-Schreibweise (YForm)
(Für Module oder "Nur-Text" Formulare)

In der YForm-Definition verwendest du den Typ `uppy_uploader`.

**Syntax:**
`uppy_uploader|name|label|[category_id]|[upload_folder]|[max_files]|[max_filesize]|[allowed_types]|[enable_webcam]|[enable_image_editor]|[allow_mediapool]|[show_file_access]|[file_access_mode]`

**Beispiel:**
```text
text|name|Name:*
email|email|E-Mail:*
# Uppy Feld: Mediapool-Kategorie 1, Max 5 Dateien, Nur Bilder
uppy_uploader|uploads|Dateien hochladen|1||5||image/*
validate|empty|name|Bitte Namen angeben
validate|empty|email|Bitte E-Mail angeben
action|db|rex_my_table
# Optional: Als E-Mail Anhang versenden
action|uppy2email|uploads|attachments
```

> Es gibt kein `MinFiles`-Feld. Die Reihenfolge ist `category_id`, `upload_folder`, `max_files`, `max_filesize`, `allowed_types`, ... (siehe `getDescription()` in `lib/yform/value/uppy_uploader.php`). Werte, die weggelassen werden sollen, bleiben als leeres Pipe-Segment stehen (`||`).

---

## Variante 2: Klassisches PHP (YForm)
(Wenn du das Formular objektorientiert mit `rex_yform` baust)

Füge das Feld über `setValueField` hinzu.

```php
<?php
$yform = new rex_yform();

// ... andere Felder ...
$yform->setValueField('text', ['name', 'Dein Name']);

// Uppy Feld hinzufügen
// Format: ['name', 'label', 'category_id', 'upload_folder', 'max_files', 'max_filesize', 'allowed_types']
$yform->setValueField('uppy_uploader', [
    'uploads',           // name
    'Dokumente',         // label
    0,                   // category_id (0 = keine feste Mediapool-Kategorie)
    '',                  // upload_folder (leer = Mediapool)
    10,                  // max_files
    200,                 // max_filesize (MB)
    '.pdf,.jpg,.png'     // allowed_types
]);

// ... Validierungen & Actions ...
$yform->setActionField('db', ['rex_my_table']);

// Optional: Uploads an E-Mail anhängen (muss VOR tpl2email/email kommen)
$yform->setActionField('uppy2email', ['uploads', 'attachments']);

// E-Mail versenden
$yform->setActionField('tpl2email', ['contact_request', 'info@example.com']);

echo $yform->getForm();
?>
```

---

## Variante 3: YORM (yform_dataset)
(Wenn du mit Datensätzen und dem Table Manager arbeitest)

Bei YORM definierst du die Felder normalerweise im Backend im **Table Manager**. Dort legst du ein Feld vom Typ `uppy_uploader` an. Im Frontend gibst du das Formular dann über das Dataset aus.

### Schritt 1: Konfiguration im Backend
1. Gehe zu YForm > Table Manager.
2. Wähle deine Tabelle (z.B. `rex_bewerbungen`).
3. Füge ein neues Feld hinzu: Typ `uppy_uploader`.
   *   Name: `docs`
   *   Label: `Zeugnisse`
   *   Maximale Dateianzahl: `3`
   *   Erlaubte Typen: `.pdf`

### Schritt 2: Ausgabe im Frontend (PHP)

Hier ist ein vollständigeres Beispiel, das auch sicherstellt, dass ein Submit-Button da ist und die Daten sauber verarbeitet werden.

```php
<?php
// 1. Dataset erstellen (leer für neu, oder mit ID laden)
$dataset = rex_yform_manager_dataset::create('rex_bewerbungen');

// 2. YForm-Instanz aus dem Dataset holen
$yform = $dataset->getForm();

// 3. WICHTIGE Parameter setzen

// Sicherstellen, dass immer ein NEUER Datensatz angelegt wird (verhindert versehentliches Editieren durch URL-Parameter)
$yform->setObjectparams('main_where', '');
$yform->setObjectparams('main_id', -1);

// Formular an die aktuelle URL senden
$yform->setObjectparams('form_action', rex_getUrl());

// Feldnamen im HTML "sauber" halten (z.B. "upload" statt "yform[...][upload]")
// Dies hilft oft bei Upload-Feldern und JavaScript-Zugriff
$yform->setObjectparams('real_field_names', true);

// Versteckt das Formular nach erfolgreichem Versand (Danke-Nachricht)
$yform->setObjectparams('form_showformafterupdate', 0);

// 4. Submit-Button hinzufügen (falls nicht im Table Manager definiert)
$yform->setValueField('submit', ['submit', 'Absenden', '', 'no_db']);

// 5. Erfolgsmeldung definieren
$yform->setActionField('showtext', ['Vielen Dank, die Daten wurden gespeichert.', '<div class="alert alert-success">', '</div>']);

// Optional: E-Mail Versand mit Anhängen
// Hinweis: 'docs' ist der Feldname, 'attachments' der Platzhalter im Mail-Template
$yform->setActionField('uppy2email', ['docs', 'attachments']);
$yform->setActionField('tpl2email', ['bewerbung_eingang', 'hr@example.com']);

// 6. Formular ausgeben & verarbeiten
echo $yform->getForm();
?>
```

*Hinweis: Auch hier müssen die **Voraussetzungen** (Assets & Session) im Template erfüllt sein!*

---

## Sicherheit (Signaturen)

Das Addon nutzt Signaturen, um sicherzustellen, dass Frontend-Nutzer keine Restriktionen (wie erlaubte Dateitypen oder maximale Dateigrößen) umgehen können.

*   **Bei YForm:** Das Feld `uppy_uploader` kümmert sich **automatisch** um die Erstellung und Prüfung der Signatur. Du musst nichts weiter tun.
*   **Bei `Utils::field()` (Variante 0):** Ebenfalls automatisch - `Field::render()` signiert die übergebenen Optionen selbst.
*   **Bei komplett eigenem HTML/JS (kein `Utils::field()`):** Du musst die Signatur selbst erstellen und mitsenden. Nutze dazu `FriendsOfRedaxo\Uppy\Signature::create(...)` - die Werte müssen exakt den `data-*`-Attributen entsprechen, die `UppyUploadHandler::processUploadedFile()` prüft (`category_id`, `allowed_types`, `max_filesize`, `upload_dir`).

---

## Fehlerbehebung & Tipps

Falls es im Frontend zu Problemen beim Upload kommt (z.B. "403 Forbidden" oder "Upload fehlgeschlagen"), prüfe folgende Punkte:

### 1. Caching & Proxies (WICHTIG!)
Der Aufruf von `Utils::ensureApiSession()` muss bei jedem Seitenaufruf dynamisch ausgeführt werden.

*   **REDAXO Cache:** Platziere den Code im Template/Header.
*   **Reverse Proxies / CDNs (Cloudflare, BunnyCDN, Varnish):**
    *   Diese Dienste cachen oft HTML-Seiten statisch. Wenn ein Besucher eine gecachte Seite erhält, wurde der PHP-Code nicht ausgeführt → **Kein Session-Token** → **Upload schlägt fehl (403 Forbidden)**.
    *   **Lösung:** Schließe Seiten mit Upload-Formularen vom CDN-Cache aus ("Bypass Cache" Rules).

### 2. Session Cookies & Domains
Der Upload funktioniert nur, wenn der Browser Cookies akzeptiert und die Session korrekt an den Server gesendet wird.
*   **Cookie-Consent:** Uppy setzt technisch notwendige Upload-Cookies (Session). Prüfe, ob dein Cookie-Banner diese nicht blockiert.
*   **Domains:** Wenn du unterschiedliche Domains nutzt (z.B. Assets von CDN oder Subdomains), können Session-Cookies blockiert werden (SameSite-Policy). Stelle sicher, dass Frontend und Upload-Endpunkt unter derselben Domain laufen.
*   **REDAXO Konfiguration:** Prüfe in der `config.yml` oder im Backend unter System, ob die Domain-Einstellungen korrekt gesetzt sind, damit Cookies richtig zugeordnet werden.

### 3. Server-Timeouts & WAFs
Auch wenn Uppy "Chunk Upload" unterstützt (Dateien werden gestückelt), können Zwischenstellen Probleme bereiten.
*   **PHP:** Prüfe `max_execution_time` und `max_input_time`.
*   **Webserver (Nginx/Apache):** Prüfe Timeouts für lange Requests.
*   **Nginx als Proxy:** Nutzt du Nginx als Reverse Proxy (z.B. vor Docker), blockiert die Standardeinstellung von `client_max_body_size` (oft 1MB) oft selbst kleinste Chunks. Erhöhe diesen Wert (z.B. 50M) und passe ggf. `proxy_read_timeout` an.
*   **Cloudflare / WAF:**
    *   **Upload Limits:** Cloudflare Free hat z.B. oft ein 100MB Limit pro Request. Dank Chunk-Upload (Pakete à 5-10MB) ist das meist kein Problem, solange die Chunks kleiner als das Limit sind.
    *   **Security Rules:** Eine Web Application Firewall kann File-Uploads fälschlicherweise blockieren. Prüfe die WAF-Logs, wenn Uploads abbrechen.

### 4. Content Security Policy (CSP)
Wenn du eine strikte CSP einsetzt, benötigt Uppy folgende Freigaben:

**Erforderliche Header-Direktiven:**
*   `img-src 'self' data: blob:;` (Für Thumbnails & Previews)
*   `connect-src 'self';` (Für den Upload-Endpunkt, XHR)
*   `style-src 'self' 'unsafe-inline';` (Für dynamische Positionierung des Modals)
*   `media-src 'self' blob:;` (Nur bei Nutzung der Webcam)

**Beispiel HTTP Header:**
```http
Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; connect-src 'self';
```

**Beispiel Nginx Config:**
```nginx
add_header Content-Security-Policy "default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; connect-src 'self'; media-src 'self' blob:;";
```

### 5. Debugging
Öffne die Entwicklertools deines Browsers (F12) und schaue in den Tab "Netzwerk".
*   Status **403**: Token fehlt, Session abgelaufen oder Signatur ungültig.
*   Status **413**: Datei/Body zu groß (prüfe Webserver-Limits wie `client_max_body_size` bei Nginx).
*   Status **500**: Server-Fehler (siehe REDAXO System Log).

