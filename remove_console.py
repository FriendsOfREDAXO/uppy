#!/usr/bin/env python3
import re

with open('src/uppy-backend.js', 'r') as f:
    content = f.read()

# Entferne vollständige console.log/error/warn Zeilen (inkl. führendes Whitespace)
content = re.sub(r'^\s*console\.(log|error|warn)\([^;]*\);\s*$', '', content, flags=re.MULTILINE)

# Cleanup: Mehrfache leere Zeilen durch max. 2 ersetzen
content = re.sub(r'\n{3,}', '\n\n', content)

with open('src/uppy-backend.js', 'w') as f:
    f.write(content)

print("✓ Console statements entfernt")
