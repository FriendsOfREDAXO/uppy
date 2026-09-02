# Changelog

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
