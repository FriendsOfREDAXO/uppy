# Changelog

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
