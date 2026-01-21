# Changelog

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
