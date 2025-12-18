/**
 * Uppy Frontend Integration für REDAXO
 * 
 * Vanilla JavaScript (kein jQuery)
 * Initialisiert Uppy-Instanzen im Frontend
 * Verwendet Uppy 5.0 mit Dashboard-Plugin
 */

(function() {
    'use strict';

    // Tracking für initialisierte Elemente
    const initializedElements = new WeakSet();

    /**
     * Initialisiert alle Uppy-Widgets
     */
    function initUppyWidgets() {
        const widgets = document.querySelectorAll('input[data-widget="uppy"]:not([data-uppy-initialized])');
        
        widgets.forEach(widget => {
            if (initializedElements.has(widget)) {
                return;
            }
            
            initializeUppyWidget(widget);
            initializedElements.add(widget);
            widget.setAttribute('data-uppy-initialized', 'true');
        });
    }

    /**
     * Initialisiert ein einzelnes Uppy-Widget
     */
    function initializeUppyWidget(inputElement) {
        // Konfiguration aus data-Attributen
        const config = {
            categoryId: parseInt(inputElement.dataset.uppyCategory || '0'),
            maxFiles: parseInt(inputElement.dataset.uppyMaxFiles || '10'),
            maxFileSize: parseInt(inputElement.dataset.uppyMaxFilesize || '200') * 1024 * 1024,
            allowedTypes: (inputElement.dataset.uppyAllowedTypes || '').split(',').filter(Boolean),
            fieldName: inputElement.name || 'uppy-file',
            locale: inputElement.dataset.uppyLang || document.documentElement.lang || 'de_DE',
            enableChunks: inputElement.dataset.uppyEnableChunks !== 'false',
            chunkSize: parseInt(inputElement.dataset.uppyChunkSize || '5') * 1024 * 1024,
            apiToken: inputElement.dataset.uppyApiToken || ''
        };

        // Container für Uppy Dashboard erstellen
        const container = document.createElement('div');
        container.className = 'uppy-container';
        inputElement.parentNode.insertBefore(container, inputElement.nextSibling);
        
        // Input-Element verstecken
        inputElement.style.display = 'none';

        // Metadaten-Felder vom Server laden und dann Uppy initialisieren
        loadMetadataFields(config.apiToken).then(function(metaFields) {
            // Uppy-Instanz erstellen (Uppy 5.0)
            const uppy = new Uppy({
                id: 'uppy-' + Math.random().toString(36).substr(2, 9),
                autoProceed: false,
                allowMultipleUploadBatches: true,
                restrictions: {
                    maxFileSize: config.maxFileSize,
                    maxNumberOfFiles: config.maxFiles,
                    allowedFileTypes: config.allowedTypes.length > 0 ? config.allowedTypes : null
                },
                locale: getUppyLocale(config.locale)
            });

            // Dashboard Plugin (Uppy 5.0 UMD)
            uppy.use(Uppy.Dashboard, {
                target: container,
                inline: true,
                width: '100%',
                height: 400,
                showProgressDetails: true,
                proudlyDisplayPoweredByUppy: false,
                note: getTranslation(config.locale, 'note', config.maxFiles),
                metaFields: metaFields
            });
            
            // Compressor Plugin für Resize und EXIF-Korrektur
            addCompressorPlugin(uppy, config);
            
            initializeUppyPlugins(uppy, config, inputElement);
        }).catch(function(error) {
            console.error('Fehler beim Laden der Metadaten-Felder:', error);
            // Fallback: Uppy ohne Metadaten-Felder initialisieren
            initializeUppyFallback(container, config, inputElement);
        });
    }

    /**
     * Initialisiert Uppy-Plugins und Event-Handler
     */
    function initializeUppyPlugins(uppy, config, inputElement) {

        // XHR Upload Plugin mit API-Token
        uppy.use(Uppy.XHRUpload, {
            endpoint: getUploadEndpoint(config.apiToken, config.categoryId),
            fieldName: 'file',
            formData: true,
            bundle: false,
            headers: config.apiToken ? {
                'X-Uppy-Token': config.apiToken
            } : {},
            getResponseData: (responseText) => {
                try {
                    return JSON.parse(responseText);
                } catch {
                    return { filename: responseText };
                }
            },
            getResponseError: (responseText) => {
                try {
                    const data = JSON.parse(responseText);
                    return data.error || 'Upload failed';
                } catch {
                    return responseText || 'Upload failed';
                }
            }
        });

        // Event-Handler
        uppy.on('file-added', (file) => {
            console.log('Datei hinzugefügt:', file.name);
            
            // Metadaten vorbereiten
            prepareMetadata(file.id, file.meta, config.categoryId, config.apiToken);
        });

        uppy.on('upload', (data) => {
            console.log('Upload gestartet:', data.fileIDs.length, 'Dateien');
        });

        uppy.on('upload-success', (file, response) => {
            console.log('Upload erfolgreich:', file.name);
            
            const filename = response.body.filename || file.name;
            
            // Dateiname zum Input hinzufügen
            updateInputValue(inputElement, filename);
            
            // Optionale Callback-Funktion aufrufen
            if (typeof window.uppyUploadSuccess === 'function') {
                window.uppyUploadSuccess(filename, file, response);
            }
            
            // Custom Event auslösen
            const event = new CustomEvent('uppy:upload-success', {
                detail: { filename, file, response },
                bubbles: true
            });
            inputElement.dispatchEvent(event);
        });

        uppy.on('upload-error', (file, error, response) => {
            console.error('Upload-Fehler:', file.name, error);
            
            // Optionale Callback-Funktion aufrufen
            if (typeof window.uppyUploadError === 'function') {
                window.uppyUploadError(file, error, response);
            }
            
            // Custom Event auslösen
            const event = new CustomEvent('uppy:upload-error', {
                detail: { file, error, response },
                bubbles: true
            });
            inputElement.dispatchEvent(event);
        });

        uppy.on('complete', (result) => {
            console.log('Alle Uploads abgeschlossen:', result.successful.length, 'erfolgreich,', result.failed.length, 'fehlgeschlagen');
            
            // Custom Event auslösen
            const event = new CustomEvent('uppy:complete', {
                detail: { result },
                bubbles: true
            });
            inputElement.dispatchEvent(event);
        });

        // Widget-Instanz speichern
        inputElement.uppyInstance = uppy;
    }

    /**
     * Bereitet Metadaten vor dem Upload vor
     */
    function prepareMetadata(fileId, metadata, categoryId, apiToken) {
        const endpoint = getBasePath() + '/redaxo/index.php?rex-api-call=uppy_uploader&func=prepare';
        
        const params = {
            fileId: fileId,
            metadata: metadata,
            category_id: categoryId
        };
        
        if (apiToken) {
            params.api_token = apiToken;
        }
        
        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(params)
        }).catch(error => {
            console.error('Fehler beim Vorbereiten der Metadaten:', error);
        });
    }

    /**
     * Fügt Image-Processing hinzu für Resize und EXIF-Korrektur
     */
    function addCompressorPlugin(uppy, config) {
        // Standard-Werte für Frontend
        const enableResize = config.enableResize !== false;
        const resizeWidth = config.resizeWidth || 2000;
        const resizeHeight = config.resizeHeight || 2000;
        const resizeQuality = config.resizeQuality || 85;
        const fixOrientation = config.fixOrientation !== false;
        
        if (!enableResize && !fixOrientation) {
            return;
        }
        
        const maxWidth = parseInt(resizeWidth);
        const maxHeight = parseInt(resizeHeight);
        const quality = parseInt(resizeQuality) / 100;
        
        // Preprocessor für Bildoptimierung
        uppy.addPreProcessor(async (fileIDs) => {
            for (const fileID of fileIDs) {
                const file = uppy.getFile(fileID);
                
                // Nur Bilder verarbeiten
                if (!file.type.startsWith('image/')) {
                    continue;
                }
                
                try {
                    const resizedBlob = await resizeImage(file.data, maxWidth, maxHeight, quality, fixOrientation);
                    
                    // Update file data
                    uppy.setFileState(fileID, {
                        data: resizedBlob,
                        size: resizedBlob.size
                    });
                    
                    console.log(`Bild optimiert: ${file.name} (${formatBytes(file.size)} → ${formatBytes(resizedBlob.size)})`);
                } catch (error) {
                    console.warn(`Fehler beim Optimieren von ${file.name}:`, error);
                }
            }
        });
        
        console.log('Image Processing aktiviert (Frontend)');
    }
    
    /**
     * Resized ein Bild clientseitig mit Canvas API
     */
    async function resizeImage(blob, maxWidth, maxHeight, quality, fixOrientation) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const url = URL.createObjectURL(blob);
            
            img.onload = () => {
                URL.revokeObjectURL(url);
                
                let width = img.width;
                let height = img.height;
                
                // Seitenverhältnis beibehalten
                if (width > maxWidth || height > maxHeight) {
                    const ratio = Math.min(maxWidth / width, maxHeight / height);
                    width = Math.floor(width * ratio);
                    height = Math.floor(height * ratio);
                }
                
                // Canvas erstellen
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                
                // Als Blob zurückgeben
                canvas.toBlob(
                    (resizedBlob) => {
                        if (resizedBlob) {
                            resolve(resizedBlob);
                        } else {
                            reject(new Error('Canvas to Blob failed'));
                        }
                    },
                    blob.type || 'image/jpeg',
                    quality
                );
            };
            
            img.onerror = () => {
                URL.revokeObjectURL(url);
                reject(new Error('Image load failed'));
            };
            
            img.src = url;
        });
    }
    
    /**
     * Formatiert Bytes in lesbarer Form
     */
    function formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    /**
     * Lädt Metadaten-Felder vom Server
     */
    function loadMetadataFields(apiToken) {
        let url = getBasePath() + '/redaxo/index.php?rex-api-call=uppy_metadata&action=get_fields';
        if (apiToken) {
            url += '&api_token=' + encodeURIComponent(apiToken);
        }
        
        return fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.fields) {
                return convertFieldsToUppyFormat(data.fields);
            }
            throw new Error('Failed to load metadata fields');
        });
    }

    /**
     * Konvertiert Server-Felder in Uppy Dashboard-Format
     */
    function convertFieldsToUppyFormat(fields) {
        return fields.map(field => ({
            id: field.name,
            name: field.label,
            placeholder: field.type === 'textarea' ? '' : field.label
        }));
    }
    
    /**
     * Fallback-Initialisierung ohne Metadaten
     */
    function initializeUppyFallback(container, config, inputElement) {
        const uppy = new Uppy({
            id: 'uppy-' + Math.random().toString(36).substr(2, 9),
            autoProceed: false,
            allowMultipleUploadBatches: true,
            restrictions: {
                maxFileSize: config.maxFileSize,
                maxNumberOfFiles: config.maxFiles,
                allowedFileTypes: config.allowedTypes.length > 0 ? config.allowedTypes : null
            },
            locale: getUppyLocale(config.locale)
        });

        uppy.use(Uppy.Dashboard, {
            target: container,
            inline: true,
            width: '100%',
            height: 400,
            showProgressDetails: true,
            proudlyDisplayPoweredByUppy: false,
            note: getTranslation(config.locale, 'note', config.maxFiles)
        });
        
        // Compressor Plugin auch im Fallback
        addCompressorPlugin(uppy, config);
        
        initializeUppyPlugins(uppy, config, inputElement);
    }

    /**
     * Aktualisiert den Wert des Input-Elements
     */
    function updateInputValue(inputElement, filename) {
        const currentValue = inputElement.value;
        
        if (currentValue) {
            // Mehrere Dateien (kommagetrennt)
            inputElement.value = currentValue + ',' + filename;
        } else {
            inputElement.value = filename;
        }
        
        // Change-Event auslösen
        const event = new Event('change', { bubbles: true });
        inputElement.dispatchEvent(event);
    }

    /**
     * Gibt den Upload-Endpoint zurück
     */
    function getUploadEndpoint(apiToken, categoryId) {
        let url = getBasePath() + '/redaxo/index.php?rex-api-call=uppy_uploader&func=upload&category_id=' + categoryId;
        
        if (apiToken) {
            url += '&api_token=' + encodeURIComponent(apiToken);
        }
        
        return url;
    }

    /**
     * Ermittelt den Basepfad
     */
    function getBasePath() {
        const baseElement = document.querySelector('base');
        if (baseElement && baseElement.href) {
            return baseElement.href.replace(/\/$/, '');
        }
        return window.location.origin;
    }

    /**
     * Gibt die Uppy-Locale zurück
     */
    function getUppyLocale(locale) {
        if (locale === 'de_DE' || locale === 'de-de' || locale === 'de') {
            return Uppy.locales?.de_DE || undefined;
        }
        return Uppy.locales?.en_US || undefined;
    }

    /**
     * Gibt Label für ein Feld zurück
     */
    function getFieldLabel(fieldName, locale) {
        const labels = {
            de_DE: {
                'title': 'Titel',
                'med_alt': 'Alt-Text',
                'med_copyright': 'Copyright',
                'med_description': 'Beschreibung'
            },
            en_US: {
                'title': 'Title',
                'med_alt': 'Alt Text',
                'med_copyright': 'Copyright',
                'med_description': 'Description'
            }
        };
        
        const lang = locale.replace('-', '_');
        return labels[lang]?.[fieldName] || fieldName;
    }

    /**
     * Gibt Placeholder für ein Feld zurück
     */
    function getFieldPlaceholder(fieldName, locale) {
        return '';
    }

    /**
     * Gibt Übersetzung zurück
     */
    function getTranslation(locale, key, maxFiles) {
        const translations = {
            de_DE: {
                'note': `Dateien hochladen (max. ${maxFiles})`
            },
            en_US: {
                'note': `Upload files (max. ${maxFiles})`
            }
        };
        
        const lang = locale.replace('-', '_');
        return translations[lang]?.[key] || '';
    }

    // Initialisierung nach DOM-Ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Uppy Frontend: DOMContentLoaded, initialisiere Widgets');
            initUppyWidgets();
        });
    } else {
        // DOM bereits geladen
        console.log('Uppy Frontend: Sofort-Initialisierung (DOM bereits geladen)');
        initUppyWidgets();
    }

    // Mutation Observer für dynamisch hinzugefügte Elemente
    function setupMutationObserver() {
        if (!document.body) {
            // Warte auf body
            setTimeout(setupMutationObserver, 50);
            return;
        }
        
        const observer = new MutationObserver(function(mutations) {
            let hasNewWidgets = false;
            
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        if (node.matches && node.matches('input[data-widget="uppy"]:not([data-uppy-initialized])')) {
                            hasNewWidgets = true;
                        } else if (node.querySelectorAll) {
                            const widgets = node.querySelectorAll('input[data-widget="uppy"]:not([data-uppy-initialized])');
                            if (widgets.length > 0) {
                                hasNewWidgets = true;
                            }
                        }
                    }
                });
            });
            
            if (hasNewWidgets) {
                console.log('Uppy Frontend: Neue Widgets erkannt, initialisiere');
                initUppyWidgets();
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
    setupMutationObserver();

    // Globale API für externe Zugriffe
    window.UppyREDAXO = {
        init: initUppyWidgets,
        getInstanceByElement: function(element) {
            return element.uppyInstance || null;
        }
    };

})();
