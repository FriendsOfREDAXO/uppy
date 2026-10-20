# Changelog

## Unreleased

### 🐛 Bugfixes
- **Backend-Upload in Unterordner-Installationen (404 „Prepare failed“)**: Die Backend-Bundles hatten den Backend-Pfad fest als `/redaxo/index.php` verdrahtet. Lag REDAXO nicht im Domain-Root unter `/redaxo/` (Unterordner-Installation oder umbenanntes Backend), gingen Prepare-, Upload-, Chunk- und Metadaten-Anfragen auf eine nicht existierende URL (404). Die URL kommt jetzt serverseitig aus `rex_url::backendController()` (`window.rex.uppy_config.api_url`). Bundle neu gebaut.

## Version 2.11.0 (2026-10-19)

Sicherheits-Release. Allen Installationen wird das Update dringend empfohlen. Die Minor-Version steigt, weil sich das Verhalten für Integratoren ändert (siehe „Geänderte Verhalten“) – Formulare, die über das YForm-Feld, `Utils::field()` oder `Signature::create()` ausgegeben werden, laufen ohne Anpassung weiter.

### 🔒 Sicherheit
- **Uploads ohne Backend-Login nur noch mit gültiger Signatur**: Jede Upload- und Metadaten-Anfrage ohne Backend-Login braucht jetzt die signierte Feld-Konfiguration. Kategorie, erlaubte Dateitypen, Maximalgröße und Upload-Ordner werden ausschließlich aus der Signatur gelesen, nicht mehr aus Request-Parametern. Die Signatur enthält ein Ablaufdatum (24 Stunden) und ist an die Browser-Session gebunden. Ein Upload-Ordner verlangt auch bei Backend-Usern eine Signatur.
- **Sperrliste für Dateinamen auf allen Upload-Wegen**: Ausführbare und serverrelevante Endungen werden abgelehnt, und zwar in jedem Teil des Dateinamens und unabhängig von der Schreibweise. HTML/XML werden immer abgelehnt, SVG nur ohne aktive Inhalte angenommen, in Upload-Ordnern zusätzlich kein SVG und kein JavaScript. Der Dateityp wird aus dem Dateiinhalt bestimmt (nicht aus der Browser-Angabe) und gegen die erlaubten Typen geprüft.
- **Upload-Ordner abgesichert**: Upload-Ordner müssen unterhalb der Installation oder eines freigegebenen Ordners liegen; REDAXO-Systemordner und versteckte Ordner sind gesperrt. Neu angelegte Upload-Ordner erhalten eine `.htaccess` gegen Skriptausführung und eine leere `index.html`. Dateinamen werden dort wie im Mediapool normalisiert.
- **Datei-Anzeige im Backend (`uppy_file_access`)**: Ordner und Dateiname werden mit denselben Regeln geprüft; nur Bilder, Videos, Audio und PDF werden noch inline angezeigt, alles andere als Download.
- **E-Mail-Anhänge (`uppy2email`)**: Dateinamen aus dem Formular werden auf den reinen Namen reduziert, der Upload-Ordner wird geprüft, und im Frontend werden nur Dateien angehängt, die in derselben Session hochgeladen wurden.
- **API-Token nicht mehr in URLs**: Die JavaScript-Bundles senden keinen Token mehr. Für Server-zu-Server-Aufrufe wird der Token nur noch als Header `X-Uppy-Token` oder POST-Feld `api_token` angenommen. Der Token allein erlaubt keinen Upload mehr.
- **Einstellung „Sicherheits-Checks deaktivieren“ entfernt**: Sie hob alle Prüfungen für anonyme Besucher auf. Der Wert wird beim Update bzw. beim Öffnen der Einstellungen gelöscht. Der Extension Point `UPPY_AUTH_CHECK` bleibt, ersetzt aber nicht die Signatur, die Sperrliste und die Ordner-Prüfungen.
- **Metadaten im Frontend**: Ohne Backend-Login lassen sich nur noch Metadaten von Dateien lesen und ändern, die in derselben Session hochgeladen wurden. Backend-User brauchen das Recht auf die Medienkategorie.
- **Chunk-Upload**: Upload-IDs werden streng geprüft und intern an die Session gebunden, Anzahl und Größe der Teile sind begrenzt, abgelehnte und verwaiste Teile werden aufgeräumt (nach 24 Stunden). Der Zwischenspeicher erhält zusätzlich eine `.htaccess`.
- **Ausgabe im Browser maskiert**: Dateinamen, Titel, Metadaten, Feldnamen und Sprachbezeichnungen werden im Custom-Widget und im Backend-Dashboard als Text ausgegeben.
- **Fehlermeldungen**: Interne Fehlerdetails werden nicht mehr an den Browser gesendet; anonyme Anfragen führen nicht mehr zu einem 500-Fehler, weil die Session vor der Prüfung gestartet wird.
- Die Rechteprüfung von Upload- und Metadaten-Endpunkt liegt jetzt zentral in der neuen Klasse `FriendsOfRedaxo\Uppy\Security`.

### ⚠️ Geänderte Verhalten (für Integratoren)
- Eigene Frontend-Einbindungen **ohne** `data-uppy-signature` funktionieren ohne Backend-Login nicht mehr. Feld mit `Utils::field()`, dem YForm-Feld oder `Signature::create()` ausgeben.
- Seiten mit Upload-Feld dürfen nicht aus einem gemeinsamen Seiten-Cache kommen (Signatur gehört zur Session, 24 Stunden gültig). Ältere Signaturen aus Version 2.10 werden nicht mehr angenommen – betroffene Seiten einmal neu laden.
- `upload_folder` darf nicht mehr in `media/`, `assets/`, `redaxo/` (außer eigenen Unterordnern von `redaxo/data/`) oder `vendor/` zeigen. Ordner außerhalb der Installation (z.B. `../data/uploads`) müssen unter **Uppy → Einstellungen → Freigegebene Ordner für Upload-Ordner** eingetragen werden.
- Dateinamen in Upload-Ordnern werden normalisiert (Kleinbuchstaben, `a-z`, `0-9`, `-`, `_`, `.`).
- Ein mitgesendeter Token in der URL (`api_token=…`) wird ignoriert.
- `Signature::create()` liefert ein neues Format (`u2.…`); `Signature::verify()` funktioniert weiter, neu ist `Signature::decode()`.
- `UppyUploadHandler::isAuthorized()` und `UppyMetadataHandler::isAuthorized()` sind deprecated und rufen `Security::authorize()` auf.
- Bei nginx greifen die `.htaccess`-Dateien nicht – Hinweise zur Server-Konfiguration stehen in der README.

### 🐛 Bugfixes
- **Metadaten ab REDAXO 5.21.5 wieder gespeichert** (#51): Seit REDAXO 5.21.5 merkt sich der Medien-Instanz-Cache auch „nicht gefunden“; Titel und MetaInfo-Felder wurden dadurch nach dem Upload nicht mehr gespeichert. Behoben.
- **Source-Map-Verweise aus den Uppy-Styles entfernt**: Die mitgelieferten Uppy-CSS-Dateien verwiesen auf nicht ausgelieferte Source-Maps, was 404-Anfragen im Browser erzeugte.
- Klarere Fehlermeldung, wenn ein Chunk größer ist als `upload_max_filesize` von PHP.

### 📦 Abhängigkeiten
- `@uppy/core` `6.2.0` → `6.3.0`, `@uppy/dashboard` und `@uppy/webcam` `6.0.1` → `6.0.2`, `@uppy/image-editor` `5.0.0` → `5.0.1`, `@uppy/locales` `5.3.0` → `5.3.1` (#52). Bundles neu gebaut.

## Version 2.10.3 (2026-10-08)

### 📦 Abhängigkeiten
- `@uppy/core` `6.0.1` → `6.2.0`, `@uppy/dashboard`, `@uppy/webcam` und `@uppy/xhr-upload` `6.0.0` → `6.0.1`, `@uppy/locales` `5.2.0` → `5.3.0` (#49, #50). Die Minor-Releases von `@uppy/core` bringen strengere TypeScript-Typen, neue Fehler-Hilfsfunktionen und experimentelle Dateiverwaltungs-Funktionen für `@uppy/s3`. `fetcher` fasst Header-Namen, die sich nur in der Groß-/Kleinschreibung unterscheiden, jetzt zusammen. `Webcam.start()` gibt jetzt ein Promise zurück. Keine dieser Änderungen betrifft die vom AddOn genutzten APIs. `@uppy/locales` bringt Aserbaidschanisch (`az_AZ`).
- Bundles (`assets/dist/`) und die Uppy-Styles (`assets/css/uppy-core.min.css`, `uppy-dashboard.min.css`, `uppy-webcam.min.css`) neu gebaut. Der Build ist mit `npm ci && npm run build` reproduzierbar.

## Version 2.10.2 (2026-09-14)

### 📦 Abhängigkeiten
- `@uppy/core` `6.0.0` → `6.0.1`. Reiner Patch-Release: markiert `AbortController`/`AbortSignal`-Re-Exports und die `hasProperty`-Hilfsfunktion als deprecated (zugunsten der nativen Globals bzw. `Object.hasOwn`), keine Breaking Changes. Beides wird vom AddOn nicht verwendet. Restliche Uppy-Pakete (`@uppy/dashboard`, `@uppy/image-editor`, `@uppy/locales`, `@uppy/webcam`, `@uppy/xhr-upload`) und `esbuild` waren bereits aktuell.
- Bundles (`assets/dist/uppy-backend-bundle.js`, `assets/dist/uppy-custom-widget-bundle.js`) neu gebaut. CSS unverändert (keine Style-Änderungen in `@uppy/core` 6.0.1).

## Version 2.10.1 (2026-09-03)

### 🐛 Bugfixes
- **`TypeError` beim Speichern eines YForm-Datensatzes mit leerem Uppy-Feld**: `rex_yform_value_uppy_uploader::getValue()` liefert für ein noch leeres Feld (z. B. beim Anlegen eines neuen Datensatzes) `null` zurück. `Field::render()` erwartet aber striktes `string $value`, wodurch PHP schon beim Funktionsaufruf mit `TypeError: Argument #3 ($value) must be of type string, null given` abbrach, bevor der Default-Wert `''` greifen konnte. `value.uppy.tpl.php` castet den Feldwert jetzt vor der Übergabe explizit auf `string`.

## Version 2.10.0 (2026-09-02)

### 🐛 Bugfixes
- **`Utils::ensureApiSession()` konnte im Frontend mit einer `rex_exception` abbrechen**: Die Methode rief `rex_set_session()` auf, ohne vorher sicherzustellen, dass überhaupt eine PHP-Session aktiv ist. `rex_set_session()`/`rex_request::setSession()` wirft aber `Session not started, call rex_login::startSession() before!`, wenn `session_status() !== PHP_SESSION_ACTIVE` ist – z.B. beim allerersten Aufruf einer Seite ohne bestehende Session-Cookie. Die YForm-Vorlage (`value.uppy.tpl.php`) hatte diesen `rex_login::startSession()`-Aufruf bereits korrekt, `Utils::ensureApiSession()` (für eigene, nicht-YForm-Templates) jedoch nicht. Behoben.

### 🎉 Neue Features
- **Einfache Frontend-Integration ohne YForm**: Neue PHP-API für den direkten Einsatz in Modulen/Templates, ohne Pipe-Notation, data-Attribute oder Signaturberechnung von Hand:
  - `FriendsOfRedaxo\Uppy\Utils::init()` setzt (falls konfiguriert) den Session-Token und gibt CSS-/JS-Tags in einem Aufruf aus.
  - `FriendsOfRedaxo\Uppy\Utils::assets()` / `Utils::ensureApiSession()` für getrennte Steuerung.
  - `FriendsOfRedaxo\Uppy\Utils::field($name, $options, $value)` rendert das komplette Upload-Feld (inkl. Sicherheits-Signatur) aus einem Options-Array (`category_id`, `upload_folder`, `max_files`, `max_filesize`, `allowed_types`, `enable_webcam`, `enable_image_editor`, `allow_mediapool`, `show_file_access`, `file_access_mode`).
  - Neue Klasse `FriendsOfRedaxo\Uppy\Field` kapselt die Attribut-/Signatur-Logik; der YForm-Value-Typ (`ytemplates/bootstrap/value.uppy.tpl.php`) nutzt jetzt dieselbe Klasse, damit YForm- und Non-YForm-Einbindung nicht mehr auseinanderlaufen können.
  - `Utils::assets()` lädt bewusst das schlanke `uppy-frontend-bundle.css` statt des größeren Backend-Bundles mit Dark-Mode-/Dashboard-Styles.
  - Rechteprüfungen bleiben unverändert serverseitig in `UppyUploadHandler::isAuthorized()`/`Signature::verify()` – die neue API ändert nur, wie die nötigen HTML-/Session-Bausteine erzeugt werden.
- **Kompakter Button-Modus**: Neue Option `data-compact="true"` (bzw. `Utils::field(..., ['compact' => true])`) reduziert das Upload-Widget auf einen einzelnen Button statt großer Dropzone/Leerer-Zustand-Box. Implementiert in beiden Rendering-Pfaden des AddOns: im `UppyCustomWidget` (`.uppy-upload-widget`, z.B. YForm-Felder) und in der nativen Inline-Dashboard-Initialisierung für `input[data-widget="uppy"]` in `uppy-backend.js` (dort öffnet der Button das Dashboard per `trigger`-Option als Modal statt es mit `inline: true` fest einzubetten). Genutzt für das "Datei ersetzen"-Panel auf der Mediapool-Detailseite, das dadurch spürbar kompakter in der Sidebar sitzt statt eine ganzseitige Dropzone zu zeigen.
  - Der Button im Backend-Pfad (`uppy-backend.js`) nutzt jetzt REDAXO's eigene `.btn.btn-primary`-Klassen aus `be_style` statt eigener Farben – dadurch passt er sich automatisch an Theme und Dark-Mode an, genau wie alle anderen Backend-Buttons. Der `UppyCustomWidget`-Kompaktbutton (auch im Frontend nutzbar, wo `be_style` nicht existiert) behält seine eigenständige `.uppy-btn`-Farbgebung.
  - Selbstgebautes Upload-Icon (Pfeil in Tray, inline SVG im selben Feather-artigen Stil wie die übrigen Widget-Icons) statt keinem/Plus-Icon, in beiden Rendering-Pfaden.
  - Demo-Seite (`pages/demo.php`, Tab "Standard") um ein Live-Beispiel des Kompakt-Modus samt HTML- und `Utils::field()`-Code ergänzt.

### 📦 Abhängigkeiten
- Uppy-Pakete aktualisiert: `@uppy/core`, `@uppy/dashboard`, `@uppy/webcam`, `@uppy/xhr-upload` auf `6.0.0`, `@uppy/image-editor` auf `5.0.0`, `@uppy/locales` auf `5.2.0`. Die Major-Änderungen (Merge von `@uppy/utils`/`@uppy/store-default`/`@uppy/companion-client`/`@uppy/provider-views` in `@uppy/core`, Companion-Token-Handling) betreffen keine der hier verwendeten APIs.
- Build-Skript korrigiert: `npm run build` erzeugt jetzt erst die CSS-Kopien aus `node_modules` (`build:css`) und bündelt danach (`build:js`) – vorher lief die Reihenfolge umgekehrt, wodurch ein CSS-Bundle nach einem Paket-Update kurzzeitig die alten Vendor-Styles enthalten konnte.
- Verwaistes, nicht mehr erzeugtes `assets/dist/uppy-frontend-bundle.js` (samt Sourcemap) entfernt – `build.js` baut seit Längerem nur noch `uppy-backend-bundle.js` und `uppy-custom-widget-bundle.js`, die alte Datei war nirgends mehr referenziert und wäre bei Verwendung veraltet gewesen.

### 📝 Dokumentation
- `frontend_usage.md` und `FRONTEND_SETUP.md` überarbeitet: neue "Variante 0" (direkter PHP-Helper) als einfachster Einstieg, falsche/widersprüchliche Beispiele entfernt (u.a. ein Beispiel, das den API-Token per `data-api-token` offenlegte, obwohl direkt darüber genau davor gewarnt wurde) und das zuvor empfohlene, tatsächlich unnötige manuelle Re-Init-`<script>` gestrichen (das Custom-Widget-Bundle initialisiert `.uppy-upload-widget`-Felder bereits selbst beim Laden).

## Version 2.9.3 (2026-08-31)

### 🐛 Bugfixes
- **Irreführender 500-Fehler bei fehlgeschlagener Signaturprüfung im Frontend-Upload**: `UppyUploadHandler` rief bei ungültiger Signatur bzw. fehlgeschlagenem Mediapool-Upload `rex_logger::logError()` mit falschen Argumenttypen auf (String statt `int` für `$errno`). Dadurch flog beim Loggen selbst eine `InvalidArgumentException` (`logger.php:72`), die die eigentliche, aussagekräftige Fehlermeldung ("Security violation: Invalid signature" bzw. den Mediapool-Fehler) verdeckte und stattdessen als nichtssagender 500er ankam. Betroffen waren `UppyUploadHandler.php` (Signatur- und Upload-Fehler) sowie `install.php` (Verzeichnis-Anlage). Alle drei Stellen nutzen jetzt korrekt `rex_logger::factory()->log('error', ...)`.
- **Falsche Pipe-Notation in `frontend_usage.md`**: Die Doku beschrieb `uppy_uploader|name|Label|MinFiles|MaxFiles|AllowedTypes` – ein `MinFiles`-Feld existiert im YForm-Value-Typ gar nicht. Die tatsächliche Reihenfolge ist `name|label|category_id|upload_folder|max_files|max_filesize|allowed_types|...` (siehe `getDescription()`/`getDefinitions()` in `lib/yform/value/uppy_uploader.php`). Nach der bisherigen Doku landeten z.B. bei `uppy_uploader|uploads|Label|1|5|image/*` die Werte `1` und `5` fälschlich in `category_id`/`upload_folder` statt in einem Min/Max-Files-Paar – das führte in der Praxis zu falsch signierten Requests und in der Folge zum oben genannten 500er. Doku korrigiert (beide Varianten: Pipe-Notation und `setValueField`).

## Version 2.9.1 (2026-08-24)

### 🐛 Bugfixes
- **Text-basierte MIME-Types (z.B. `text/css`) wurden beim Upload fälschlich abgelehnt** (`400 File type not allowed` bzw. `Dateityp nicht zulässig`), obwohl sie in den Einstellungen als erlaubter Typ hinterlegt waren:
  - Die zusammengeführte Datei aus Chunk-Uploads (und die PHP-eigene Upload-Tempdatei) besaß keine Dateiendung. Generische Text-Typen wie CSS werden von `mime_content_type()` nur als `text/plain` erkannt und benötigen für die korrekte Zuordnung die Dateiendung – die fehlte am Temp-Pfad.
  - Vor der Übergabe an `rex_media_service::addMedia()`/`updateMedia()` wird die Temp-Datei jetzt bei Bedarf auf einen Pfad mit korrekter Endung kopiert (und danach wieder aufgeräumt), damit auch REDAXO-Core (`rex_mediapool::isAllowedMimeType()`) den MIME-Type richtig erkennt.
  - `text/css` fehlte zusätzlich in der internen Mapping-Tabelle, über die Uppy erlaubte MIME-Types beim Boot an den Mediapool (`allowed_mime_types`) weiterreicht – dadurch wurde `css` nie als bekannte Endung registriert.
- **Beliebige eigene MIME-Types** (Feld „Eigene MIME-Types“ in den Einstellungen) werden jetzt automatisch mit einer aus dem Subtype abgeleiteten Dateiendung an den Mediapool gemeldet, auch wenn sie nicht in der festen Mapping-Tabelle stehen (z.B. `text/css` → `css`, `application/x-foo` → `foo`). Für bekannte Sonderfälle (z.B. `application/vnd.ms-excel` → `xls`) bleibt die feste Tabelle maßgeblich.

## Version 2.9.0 (2026-08-10)

### 🎉 Neue Features
- **Datei-Ersetzen im Mediapool mit Uppy**: Auf der Medien-Detailseite (`mediapool/media`) steht ein eigenes Uppy-Panel zum Ersetzen bestehender Dateien zur Verfügung.
- **Chunk-Replace für große Dateien**: Auch das Ersetzen nutzt den Chunk-Workflow (`prepare` / `chunk` / `finalize`) und funktioniert damit zuverlässig für große Dateien.

### ✨ Verbesserungen
- **Sichere Rechteprüfung beim Replace**: Ersetzen ist nur für berechtigte Backend-User in erlaubten Mediapool-Kategorien möglich.
- **UX nach erfolgreichem Replace**: Automatischer Reload auf die Detailseite mit REDAXO-Info-Meldung.
- **Dark-Mode-angepasstes Panel**: Neue Replace-Panel-Styles folgen REDAXO Theme-Layern (Light, explizit Dark, Auto-Dark).

### ⚙️ Einstellungen
- Neue Option: **„Datei-Ersetzen im Mediapool mit Uppy“** zum Ein-/Ausschalten des Panels auf der Mediapool-Detailseite.

## Version 2.8.0 (2026-05-04)

### ✨ Verbesserungen
- **Optionale Medienanzeige bei Custom-Ordnern** (`upload_folder`) .Dateien koennen im Widget optional angezeigt und heruntergeladen werden.
- **Lightbox fuer Bilder und Videos** im Widget: Vorschau per Klick (inkl. Video-Playback mit Controls).

### 🔒 Security
- **Interner Backend-API-Endpunkt** fuer Dateiabruf aus Custom-Ordnern (`rex-api-call=uppy_file_access`) mit Backend-Session-Pflicht und robuster Pfadauflösung relativ zum Webroot (inkl. `../`).

### 🧭 Verhalten
- `upload_folder` ist frei konfigurierbar relativ zum Webroot (z.B. `redaxo/data`, `../data/uploads`, `../../meine_daten`).

## Version 2.7.0 (2026-XX-XX)

### 🎉 Neue Features
- **YCom-Medienberechtigungen beim Upload setzen**: Optionales, ausklappbares Panel auf der Upload-Seite, mit dem berechtigte Backend-User die Frontend-Sichtbarkeit (`ycom_auth_type`, `ycom_group_type`, `ycom_groups`) für alle in der Sitzung neu hochgeladenen Dateien vorbelegen können.
  - Voraussetzung: Plugin `ycom/media_auth` aktiv.
  - In den Einstellungen aktivierbar via Schalter **„YCom-Medienberechtigungen beim Upload setzen“**.
  - Eigene Backend-Permission `uppy[ycom_media_auth]` (oder Admin) zur Steuerung, wer das Panel sehen und nutzen darf.
  - Werte werden pro Backend-Session gespeichert und automatisch auf jede neue Datei angewendet (Standard- und Chunk-Upload).
  - Gruppenfelder erscheinen nur, wenn `ycom/group` verfügbar ist und ein passender Auth-/Gruppentyp gewählt ist.
  - Status-Badge im Panel-Header zeigt jederzeit, ob aktuell „Alle (öffentlich)“ oder „Nur eingeloggte“ als Default greift; Reset-Button setzt die Sitzungs-Defaults sofort zurück (UI + Session).
- **Info-Center Widget**: Wenn das AddOn `info_center` installiert ist, registriert Uppy ein Dashboard-Widget mit Kategorieauswahl, Drag&Drop-Bereich und direktem Link zur kompletten Upload-Seite.

### 🐛 Bugfixes
- **Custom-Widget-Modus**: Upload-Endpoint wird nun explizit auf den Backend-Controller gesetzt, damit die Backend-Session-Cookies mitgesendet werden. Dadurch greifen YCom-Auth-Defaults und sonstige Backend-User-bezogene Logik auch im Custom-Widget korrekt.

### 🔒 Security
- Strikte Doppelprüfung der YCom-Auth-Defaults: Plugin aktiv **und** Setting aktiviert **und** Backend-Session **und** Permission – server- wie clientseitig.

## Version 2.6.0 (2026-02-19)

### 🎉 Neue Features
- **Erweiterte Dateitypen-Auswahl**: Settings-Seite mit vollständigem Dateitypen-Katalog in 8 Gruppen (Bilder, Dokumente, Archive, Video, Audio, Office, OpenDocument, Fonts).
- **Automatische Mediapool-Erweiterung**: Vom Uppy konfigurierte MIME-Types werden zur Laufzeit automatisch im Mediapool freigeschaltet – keine manuelle Pflege der `allowed_mime_types` mehr nötig.
- **Neue Dateiformate**: Unterstützung für ICS (iCalendar), JSON, XML, VTT, SRT, EPUB, EPS, FLAC, M4A, AVI, MKV, ICO, RAR, 7z, GZ, TAR, OpenDocument (ODT/ODS/ODP), Font-Dateien (WOFF/WOFF2/TTF/OTF) und Office-Vorlagen (DOTX/POTX/PPSX).

### ✨ Verbesserungen
- **Dateitypen-UI neu gestaltet**: Modal durch übersichtliches Accordion mit Inline-Checkboxen und Badge-Zähler pro Gruppe ersetzt.
- **Sichtbare Konfiguration**: Aktive MIME-Types werden in einem Textarea-Feld angezeigt statt in einem versteckten Input.
- **Eigene MIME-Types**: Freitext-Feld für benutzerdefinierte MIME-Types, die nicht im Katalog enthalten sind.

### 🐛 Bugfixes
- **Dateitypen-Auswahl funktionierte nicht**: Fehlender JavaScript-Handler für die Übernahme ausgewählter Dateitypen behoben.

## Version 2.5.0 (2026-02-10)

### 🎉 Neue Features
- **Helper Klasse**: `FriendsOfRedaxo\Uppy\Utils::ensureApiSession()` zum einfachen Setzen des API-Tokens im Frontend.
- **Not-Aus**: Option "Auth Checks deaktivieren" für Notfälle oder internes Testen hinzugefügt (mit visueller Warnung).
- **YForm Action**: `uppy2email` berücksichtigt nun explizit den konfigurierten `upload_folder` (Custom Folder), falls gesetzt.
- **Extension Points**: Neue Hooks `UPPY_AUTH_CHECK` (Custom Auth Provider) und `UPPY_UPLOAD_COMPLETE` (Post-Processing).

### 🔒 Security
- **Auth-Hierarchie**: Strikte Priorisierung implementiert: Backend User > Custom Auth (EP) > Not-Aus > YCom User > API Token.
- **Settings UI**: Verbesserte Darstellung der Sicherheitseinstellungen (API Token Warnung, Farbkodierung für Auth-Status).
- **API Token**: Fallback-Mechanismus gehärtet, Token wird nicht mehr im HTML-Markup exposed.

### 📖 Dokumentation
- **Struktur**: Aufteilung der Dokumentation in Übersicht (README) und Integration (`frontend_usage.md`).
- **Frontend-Guide**: Neue, ausführliche Anleitung für YForm-Integration, Nutzung in eigenen Formularen und API-Features.
- **Troubleshooting**: Tipps zu Caching, Reverse Proxies (Nginx, Cloudflare), CSP-Headern und Server-Limits ergänzt.

## Version 2.4.0 (2026-02-09)

### 🎉 Neue Features
- **E-Mail-Anhänge in YForm**: Neue Action `uppy2email` ermöglicht das direkte Versenden hochgeladener Dateien als E-Mail-Anhang.
  - Verwendung: `action|uppy2email|feldname`
- **Verbesserte YForm-Integration**: Dateien werden nun korrekt im `value_pool` für 'email' bereitgestellt.
- **Auto-Cleanup**: Ungenutzte Dateien werden besser erkannt und bereinigt (inkl. Prüfung in YForm-Tabellen und MEDIA_IS_IN_USE Extension Point).

## Version 2.3.0 (2026-01-21)

### 🎉 Neue Features
- **Direkter Datei-Upload**: Unterstützung für den Upload in benutzerdefinierte Ordner (relativ zum REDAXO-Root), unter Umgehung des Medienpools.
- **Sicherheits-Signaturen**: HMAC-SHA256 Signaturprüfung für sensible Upload-Parameter (Zielordner, Dateitypen, Größenlimits), um Manipulationen im Frontend zu verhindern.
- **Custom-Widget Modus für Backend**: Neue Einstellung ermöglicht die Nutzung des Listen-Widgets auf der Haupt-Upload-Seite (inkl. Metadaten-Editor).
- **Kollisionsschutz**: Automatische Dateinamen-Iteration (z.B. `datei_1.jpg`), um das Überschreiben existierender Dateien im Zielordner zu verhindern.

### ✨ Verbesserungen
- **Liste leeren Button**: Im Custom-Widget mit Sicherheitsabfrage zum schnellen Entfernen aller Dateien hinzugefügt.
- **UX-Optimierung**: Verbesserter Hinweistext im leeren Zustand des Widgets mit explizitem Drag & Drop Hinweis.
- **Listen-UI**: Sortier-Pfeile (Up/Down) auf den Standard-Uploadseiten ausgeblendet, da dort keine Sortierung nötig ist.
- **Wartung**: Sämtliche npm-Abhängigkeiten auf den neuesten Stand gebracht und Bundles neu generiert.

### 🐛 Bugfixes
- **Dynamische Kategoriewahl**: Die Zielkategorie wird nun beim Start des Uploads live aus dem DOM gelesen (Fix für verworfene Kategoriewahl).
- **Validierungs-Fix**: Fehlerhafte Byte-Berechnung bei der Dateigrößenprüfung im PHP-Backend behoben (MB wurden fälschlicherweise als Bytes interpretiert).

### 📝 Dokumentation
- README um Abschnitte zu Direkt-Uploads, Signaturen und neuen YForm-Parametern erweitert.

---

## Version 2.2.0 (2026-01-12)

### 🎉 Neue Features
- **Drag & Drop auf Widget:** Dateien können jetzt direkt auf das Upload-Widget gezogen werden
  - Visuelles Feedback beim Hovern (blaue Umrandung)
  - Modal öffnet sich automatisch mit den gezogenen Dateien
  - Respektiert `max-files` Limit

### 🐛 Bugfixes
- **Frontend-Upload:** XHRUpload Response wird jetzt korrekt aus dem XHR-Objekt extrahiert
- **Chunked-Upload:** Chunk-Size Kalkulation korrigiert (MB zu Bytes Konvertierung)
- **Chunked-Upload:** Loop-Problem durch falsches `complete` Event behoben
- **Chunked-Upload:** Unnötige Debug-Logs entfernt

### ✨ Verbesserungen
- **Frontend-Integration:** Vollständige Dokumentation für Frontend-Usage hinzugefügt
- **Chunked-Upload:** Chunk-Size wird jetzt korrekt als MB interpretiert (nicht als Bytes)
- **Error-Handling:** Verbesserte Fehlerbehandlung bei ungültigen Server-Responses

### 📝 Dokumentation
- README um Frontend-Integration erweitert
- Beispiel-Code für Frontend-Upload mit Chunked-Support
- Drag & Drop Feature dokumentiert

---

## Version 2.1.0

- Initiale stabile Version mit Uppy 5.0
- Dashboard Widget mit Drag & Drop
- Chunk-Upload Support
- Image Editor Integration
- YForm Integration
- MetaInfo Support
