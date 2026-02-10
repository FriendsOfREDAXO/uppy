# Uppy im Frontend mit YForm

Diese Anleitung beschränkt sich auf die Nutzung innerhalb von **YForm**.

## Voraussetzungen (Immer erforderlich)

Damit Uppy im Frontend funktioniert, müssen zwei Dinge erledigt sein:

### 1. Assets einbinden
Da YForm im Frontend keine Assets automatisch lädt, musst du CSS und JS manuell einbinden.

**Empfohlener Ort für CSS** (im `<head>` deines Templates):
```html
<link rel="stylesheet" href="/assets/addons/uppy/dist/uppy-backend-bundle.css">
```

**Empfohlener Ort für JS** (am Ende des `<body>` oder mit `defer`):
```html
<!-- Uppy Core & Plugins -->
<script src="/assets/addons/uppy/dist/uppy-backend-bundle.js"></script>

<!-- YForm Integration (Initialisiert die Felder automatisch) -->
<script src="/assets/addons/uppy/dist/uppy-custom-widget-bundle.js"></script>
```

### 2. Session Token setzen
Damit Gäste Dateien hochladen dürfen, muss ein Token in der Session existieren. **Führe diesen PHP-Code vor der Ausgabe des Formulars aus** (z.B. oben im Template oder Header-Fragment):

**Empfohlen (Helper):**
```php
<?php
\FriendsOfRedaxo\Uppy\Utils::ensureApiSession();
?>
```

**Alternative (Manuell):**
```php
<?php
if ($apiToken = rex_config::get('uppy', 'api_token')) {
    rex_set_session('uppy_token', $apiToken);
}
?>
```

---

## Variante 1: Pipe-Schreibweise
(Für Module oder "Nur-Text" Formulare)

In der YForm-Definition verwendest du den Typ `uppy_uploader`.

**Syntax:**
`uppy_uploader|name|Label|MinFiles|MaxFiles|AllowedTypes`

**Beispiel:**
```text
text|name|Name:*
email|email|E-Mail:*
# Uppy Feld: Pflichtfeld (mind. 1), Max 5 Dateien, Nur Bilder
uppy_uploader|uploads|Dateien hochladen|1|5|image/*
validate|empty|name|Bitte Namen angeben
validate|empty|email|Bitte E-Mail angeben
action|db|rex_my_table
# Optional: Als E-Mail Anhang versenden
action|uppy2email|uploads|attachments
```

---

## Variante 2: Klassisches PHP
(Wenn du das Formular objektorientiert mit `rex_yform` baust)

Füge das Feld über `setValueField` hinzu.

```php
<?php
$yform = new rex_yform();

// ... andere Felder ...
$yform->setValueField('text', ['name', 'Dein Name']);

// Uppy Feld hinzufügen
// Format: ['name', 'Label', 'MinFiles', 'MaxFiles', 'AllowedTypes']
$yform->setValueField('uppy_uploader', [
    'uploads',           // name
    'Dokumente',         // Label
    0,                   // MinFiles (0 = Optional)
    10,                  // MaxFiles
    '.pdf,.jpg,.png'     // AllowedTypes
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
*   **Bei eigener API-Nutzung:** Wenn du Uppy komplett manuell (ohne YForm-Feld) nutzt, musst du die Signatur selbst erstellen und mitsenden. Nutze dazu `FriendsOfRedaxo\Uppy\Signature::create(...)`.

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

