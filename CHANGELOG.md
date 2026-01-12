# Changelog

## Version 2.2.0 (2026-01-12)

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

---

## Version 2.1.0

- Initiale stabile Version mit Uppy 5.0
- Dashboard Widget mit Drag & Drop
- Chunk-Upload Support
- Image Editor Integration
- YForm Integration
- MetaInfo Support
