/**
 * Uppy Backend Integration für REDAXO
 * Verwendet lokale NPM-Module statt CDN
 */
import Uppy from '@uppy/core';
import Dashboard from '@uppy/dashboard';
import Webcam from '@uppy/webcam';
import XHRUpload from '@uppy/xhr-upload';
import Tus from '@uppy/tus';
import ImageEditor from '@uppy/image-editor';
import German from '@uppy/locales/lib/de_DE';

window.UPPY_BUNDLE_LOADED = true;

/**
 * Initialisiert alle Uppy-Widgets auf der Seite
 */
function initUppyWidgets() {
    const widgets = document.querySelectorAll('input[data-widget="uppy"]:not([data-uppy-initialized])');
    
    if (widgets.length === 0) {
        return;
    }

    widgets.forEach(function(inputElement) {
        initializeUppyWidget(inputElement);
    });
}

/**
 * Initialisiert ein einzelnes Uppy-Widget
 */
function initializeUppyWidget(inputElement) {
    if (inputElement.dataset.uppyInitialized) {
        return;
    }
    
    inputElement.dataset.uppyInitialized = 'true';
    
    // Input-Element verstecken
    inputElement.style.display = 'none';
    
    // Container für Uppy Dashboard erstellen
    const container = document.createElement('div');
    container.className = 'uppy-wrapper';
    container.style.minHeight = '350px';
    container.style.marginBottom = '30px';
    inputElement.parentNode.insertBefore(container, inputElement.nextSibling);
    
    // Konfiguration aus data-Attributen oder Defaults
    const config = {
        apiToken: inputElement.dataset.apiToken || '', // Im Backend optional (User ist bereits authentifiziert)
        maxFiles: parseInt(inputElement.dataset.maxFiles) || 10,
        maxFileSize: (parseInt(inputElement.dataset.maxFilesize) || 200) * 1024 * 1024, // MB in Bytes umrechnen
        allowedTypes: inputElement.dataset.allowedTypes ? inputElement.dataset.allowedTypes.split(',') : [],
        categoryId: parseInt(inputElement.dataset.categoryId) || 0,
        locale: inputElement.dataset.locale || 'de-DE'
    };
    
    // Uppy-Config aus PHP (boot.php)
    const uppyConfig = window.rex?.uppy_config || {};
    config.enable_resize = uppyConfig.enable_resize !== false;
    config.resize_width = uppyConfig.resize_width || 2000;
    config.resize_height = uppyConfig.resize_height || 2000;
    config.resize_quality = uppyConfig.resize_quality || 85;
    config.fix_exif_orientation = uppyConfig.fix_exif_orientation !== false;
    config.enable_chunks = uppyConfig.enable_chunks !== false;
    config.chunk_size = (uppyConfig.chunk_size || 5) * 1024 * 1024; // MB in Bytes
    
    // Metadaten-Felder laden
    loadMetadataFields(config.apiToken).then(function(metaFields) {

        // Uppy-Instanz erstellen
        const uppy = new Uppy({
            id: 'uppy-' + Math.random().toString(36).substr(2, 9),
            autoProceed: false,
            allowMultipleUploadBatches: true,
            restrictions: {
                maxFileSize: config.maxFileSize,
                maxNumberOfFiles: config.maxFiles,
                allowedFileTypes: config.allowedTypes.length > 0 ? config.allowedTypes : null
            },
            locale: config.locale === 'de-DE' ? German : undefined
        });
        
        // DEBUG: Global verfügbar machen
        if (!window.uppyInstances) {
            window.uppyInstances = [];
        }
        window.uppyInstances.push(uppy);
        window.uppyDebug = uppy; // Letzte Instanz für einfachen Zugriff

        // WICHTIG: Image Editor MUSS vor Dashboard registriert werden
        const globalConfig = window.rex?.uppy_config || {};
        // Image Editor aus data-Attribut des Feldes lesen, nicht global
        const enableImageEditor = inputElement.dataset.enableImageEditor === 'true';
        
        if (enableImageEditor) {
            registerImageEditor(uppy, config, globalConfig);
        }
        
        // Dashboard Plugin - MIT metaFields damit Edit-Button erscheint
        const dashboardOptions = {
            inline: true,
            target: container,
            width: '100%',
            height: 'auto',
            showProgressDetails: true,
            proudlyDisplayPoweredByUppy: false,
            note: getTranslation(config.locale, 'note', config.maxFiles),
            disablePageScrollWhenModalOpen: false,
            // metaFields MÜSSEN angegeben werden, sonst gibt es keinen Edit-Button
            metaFields: metaFields.length > 0 ? metaFields : undefined
        };
        
        // Wenn Image Editor aktiv ist, automatisch öffnen bei Bild-Upload (nur Einzel-Uploads)
        if (enableImageEditor && config.maxFiles === 1) {
            dashboardOptions.autoOpen = 'imageEditor';
        }
        
        uppy.use(Dashboard, dashboardOptions);
        
        // Eigenes Modal für Metadaten nach Upload
        // ABER: Wenn Image Editor aktiv ist, kein Modal - User soll erst Bild bearbeiten
        if (!enableImageEditor && metaFields && metaFields.length > 0) {
            setupMetadataModal(uppy, metaFields);
        }
        
        // Compressor Plugin für Resize und EXIF-Korrektur
        addCompressorPlugin(uppy, config);
        
        initializeUppyPlugins(uppy, config, inputElement, metaFields);
    }).catch(function(error) {

        // Fallback: Uppy ohne Metadaten-Felder initialisieren
        initializeUppyFallback(container, config, inputElement);
    });
}

/**
 * Registriert Image Editor Plugin (MUSS vor Dashboard aufgerufen werden)
 */
function registerImageEditor(uppy, config, globalConfig) {
    const cropRatios = globalConfig.crop_ratios || '1:1,16:9,4:3,3:2,free';
    
    const ratios = cropRatios.split(',').map(ratio => {
        if (ratio === 'free') return null;
        const parts = ratio.split(':').map(Number);
        return parts.length === 2 ? parts[0] / parts[1] : null;
    }).filter(r => r !== null);
    
    try {
        uppy.use(ImageEditor, {
            id: 'ImageEditor',
            quality: config.resize_quality / 100, // 85 -> 0.85
            cropperOptions: {
                viewMode: 1,
                background: false,
                autoCropArea: 1,
                responsive: true,
                aspectRatio: ratios.length > 0 ? ratios[0] : null,
            },
            actions: {
                revert: true,
                rotate: true,
                granularRotate: true,
                flip: true,
                zoomIn: true,
                zoomOut: true,
                cropSquare: cropRatios.includes('1:1'),
                cropWidescreen: cropRatios.includes('16:9'),
                cropWidescreenVertical: cropRatios.includes('9:16'),
            },
            locale: {
                strings: {
                    revert: 'Zurücksetzen',
                    rotate: 'Drehen',
                    zoomIn: 'Vergrößern',
                    zoomOut: 'Verkleinern',
                    flipHorizontal: 'Horizontal spiegeln',
                    aspectRatioSquare: '1:1',
                    aspectRatioLandscape: '16:9',
                    aspectRatioPortrait: '9:16',
                }
            }
        });
    } catch (error) {
        console.error('Uppy Image Editor Fehler:', error);
    }
}

/**
 * Initialisiert Uppy-Plugins und Event-Handler
 */
function initializeUppyPlugins(uppy, config, inputElement, metaFields) {
    // Webcam Plugin nur laden wenn aktiviert
    const globalConfig = window.rex?.uppy_config || {};
    const enableWebcam = globalConfig.enable_webcam || false;
    
    if (enableWebcam) {
        uppy.use(Webcam, {
            modes: ['picture'],
            mirror: true,
            facingMode: 'user',
            showRecordingLength: false,
            preferredImageMimeType: 'image/jpeg',
            locale: {
                strings: {
                    pluginNameCamera: 'Kamera',
                    smile: 'Lächeln!',
                    takePicture: 'Foto aufnehmen',
                    startRecording: 'Aufnahme starten',
                    stopRecording: 'Aufnahme stoppen',
                    allowAccessTitle: 'Bitte erlaube den Zugriff auf deine Kamera',
                    allowAccessDescription: 'Um Fotos aufzunehmen, erlaube bitte den Zugriff auf deine Kamera.',
                }
            }
        });
    }
    
    // Upload Plugin: TUS für Chunked Upload oder XHR für kleine Dateien
    if (config.enable_chunks) {
        // TUS Protocol für Chunked Uploads
        uppy.use(Tus, {
            endpoint: function() {
                const currentCategoryId = parseInt(inputElement.dataset.categoryId) || 0;
                const tokenParam = config.apiToken ? '&api_token=' + encodeURIComponent(config.apiToken) : '';
                return window.location.origin + '/redaxo/index.php?rex-api-call=uppy_uploader&func=chunk' + tokenParam + '&category_id=' + currentCategoryId;
            },
            chunkSize: config.chunk_size,
            retryDelays: [0, 1000, 3000, 5000],
            withCredentials: true,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
    } else {
        // XHR Upload für Standard-Uploads
        uppy.use(XHRUpload, {
            endpoint: function(file) {
                const currentCategoryId = parseInt(inputElement.dataset.categoryId) || 0;
                const tokenParam = config.apiToken ? '&api_token=' + encodeURIComponent(config.apiToken) : '';
                return window.location.origin + '/redaxo/index.php?rex-api-call=uppy_uploader&func=upload' + tokenParam + '&category_id=' + currentCategoryId;
            },
            formData: true,
            fieldName: 'file',
            allowedMetaFields: true,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            getResponseData: function(responseText, response) {
                try {
                    return JSON.parse(responseText);
                } catch (e) {
                    return { success: false, message: 'Invalid response' };
                }
            },
            getResponseError: function(responseText) {
                try {
                    const response = JSON.parse(responseText);
                    return response.error || response.message || 'Upload failed';
                } catch (e) {
                    return responseText;
                }
            }
        });
    }
    
    // Event-Handler
    setupEventHandlers(uppy, config, inputElement, metaFields);
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
        locale: config.locale === 'de-DE' ? German : undefined
    });
    
    uppy.use(Dashboard, {
        inline: true,
        target: container,
        width: '100%',
        height: 350,
        showProgressDetails: true,
        proudlyDisplayPoweredByUppy: false,
        note: getTranslation(config.locale, 'note')
    });
    
    addCompressorPlugin(uppy, config);
    initializeUppyPlugins(uppy, config, inputElement);
}

/**
 * Event-Handler einrichten
 */
function setupEventHandlers(uppy, config, inputElement, metaFields) {
    // Upload erfolgreich
    uppy.on('upload-success', function(file, response) {
        
        if (response.body && response.body.success && response.body.data) {
            const filename = response.body.data.filename;
            const title = response.body.data.title || '';
            
            // Datei in Uppy-Meta speichern für spätere Verwendung
            uppy.setFileMeta(file.id, {
                ...file.meta,
                savedFilename: filename,
                savedTitle: title
            });
            
            // Wenn Image Editor aktiv ist UND Metadaten vorhanden sind: Modal öffnen
            const globalConfig = window.rex?.uppy_config || {};
            const enableImageEditor = globalConfig.enable_image_editor || false;
            
            if (enableImageEditor && metaFields && metaFields.length > 0) {
                // Kurze Verzögerung damit Upload-Feedback sichtbar ist
                setTimeout(function() {
                    showMetadataModal(uppy, file, metaFields);
                }, 500);
            }
            
            // Hidden Input aktualisieren
            if (inputElement) {
                const currentValue = inputElement.value;
                const files = currentValue ? currentValue.split(',') : [];
                if (!files.includes(filename)) {
                    files.push(filename);
                    inputElement.value = files.join(',');
                }
            }
            
            // jQuery Event für YForm-Integration auslösen
            if (typeof jQuery !== 'undefined') {
                jQuery(document).trigger('uppyUploadSuccess', {
                    widgetId: inputElement?.id,
                    filename: filename,
                    title: title,
                    file: file
                });
            }
            
            // Mediapool-Widget-Integration (REX_MEDIA / REX_MEDIALIST)
            const openerField = inputElement?.dataset?.uppyOpenerField || window.rex?.uppyOpenerInputField;
            if (openerField && window.opener) {
                // Für REX_MEDIA: Nicht automatisch übernehmen, Button anzeigen
                // Für REX_MEDIALIST: Auch nicht automatisch, Liste mit Buttons anzeigen

            }
        }
    });
    
    // Alle Uploads abgeschlossen - zeige Übernehmen-Liste
    uppy.on('complete', function(result) {
        const openerField = inputElement?.dataset?.uppyOpenerField || window.rex?.uppyOpenerInputField;

        // Zeige Liste wenn openerField gesetzt ist (auch ohne window.opener)
        if (openerField && result.successful.length > 0) {
            showUploadedFilesList(result.successful, openerField, inputElement);
        }
    });
}

/**
 * Zeigt Liste der hochgeladenen Dateien mit Übernehmen-Buttons
 */
function showUploadedFilesList(files, openerField, inputElement) {
    // Liste bereits vorhanden? Entfernen
    let listContainer = document.getElementById('uppy-uploaded-files-list');
    if (!listContainer) {
        listContainer = document.createElement('div');
        listContainer.id = 'uppy-uploaded-files-list';
        listContainer.className = 'uppy-uploaded-files-list';
        
        // Nach dem Uppy Widget einfügen
        const uppyWrapper = inputElement.parentNode.querySelector('.uppy-wrapper');
        if (uppyWrapper && uppyWrapper.parentNode) {
            uppyWrapper.parentNode.insertBefore(listContainer, uppyWrapper.nextSibling);
        }
    }
    
    // HTML für Dateiliste erstellen
    let html = '<h4>Hochgeladene Dateien</h4><ul class="list-group">';
    
    files.forEach(function(file) {
        const filename = file.meta.savedFilename || file.name;
        const title = file.meta.savedTitle || '';
        
        html += '<li class="list-group-item">';
        html += '<span class="uppy-file-name">' + filename + '</span>';
        
        if (openerField.indexOf('REX_MEDIA_') === 0 || openerField.indexOf('REX_MEDIALIST_') === 0) {
            // Eindeutige ID für Button
            const btnId = 'uppy-select-' + filename.replace(/[^a-z0-9]/gi, '-');
            
            html += '<button type="button" class="btn btn-xs btn-select btn-highlight pull-right" ';
            html += 'id="' + btnId + '" ';
            html += 'data-filename="' + filename + '" ';
            html += 'data-title="' + title.replace(/"/g, '&quot;') + '" ';
            html += 'data-opener-field="' + openerField + '">';
            html += 'Übernehmen</button>';
        }
        
        html += '</li>';
    });
    
    html += '</ul>';
    
    // Bei MEDIALIST: "Alle übernehmen" Button hinzufügen
    if (openerField.indexOf('REX_MEDIALIST_') === 0 && files.length > 1) {
        html += '<div style="margin-top: 10px; text-align: center;">';
        html += '<button type="button" class="btn btn-success" id="uppy-select-all-btn" ';
        html += 'data-opener-field="' + openerField + '">';
        html += '<i class="fa fa-check-double"></i> Alle übernehmen (' + files.length + ' Dateien)</button>';
        html += '</div>';
    }
    
    listContainer.innerHTML = html;
    
    // Event-Listener für Übernehmen-Buttons
    const buttons = listContainer.querySelectorAll('button[data-opener-field]');
    buttons.forEach(function(button) {
        button.addEventListener('click', function() {
            const filename = this.getAttribute('data-filename');
            const title = this.getAttribute('data-title');
            const openerField = this.getAttribute('data-opener-field');
            
            if (openerField.indexOf('REX_MEDIA_') === 0) {
                // REX_MEDIA: Rufe globale selectMedia Funktion auf
                if (typeof selectMedia === 'function') {
                    selectMedia(filename, title);
                }
            } else if (openerField.indexOf('REX_MEDIALIST_') === 0) {
                // REX_MEDIALIST: Rufe globale selectMedialist Funktion auf
                if (typeof selectMedialist === 'function') {
                    selectMedialist(filename);
                }
            }
        });
    });
    
    // "Alle übernehmen" Button Handler
    const selectAllBtn = document.getElementById('uppy-select-all-btn');
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            if (typeof selectMedialist === 'function') {
                files.forEach(function(file) {
                    const filename = file.meta.savedFilename || file.name;
                    selectMedialist(filename);
                });
            }
        });
    }
}

/**
 * Event-Handler einrichten (fortgesetzt)
 */
function setupEventHandlersOld(uppy, config, inputElement) {
    // Upload erfolgreich - ALTE VERSION
    uppy.on('upload-success', function(file, response) {
        if (response.body && response.body.success && response.body.data) {
            const filename = response.body.data.filename;
            
            // Für NON-Widget Verwendung: Automatisch ins Input
            const openerField = inputElement?.dataset?.uppyOpenerField || window.rex?.uppyOpenerInputField;
            if (!openerField || !window.opener) {
                // Hidden Input aktualisieren
                if (inputElement) {
                    const currentValue = inputElement.value;
                    const files = currentValue ? currentValue.split(',') : [];
                    if (!files.includes(filename)) {
                        files.push(filename);
                        inputElement.value = files.join(',');
                    }
                }
            }
        }
    });
}

// Alte setupEventHandlers umbenennen falls noch irgendwo verwendet
function setupEventHandlersOriginal(uppy, config, inputElement) {
    uppy.on('upload-success', function(file, response) {
        if (response.body && response.body.success && response.body.data) {
            const filename = response.body.data.filename;
            
            // Mediapool-Widget-Integration (REX_MEDIA / REX_MEDIALIST) - ALTE VERSION
            const openerField = inputElement?.dataset?.uppyOpenerField || window.rex?.uppyOpenerInputField;
            if (openerField && window.opener) {
                // Media-Widget callback - DIREKT (alt)
                if (typeof window.selectMedia === 'function') {
                    const title = response.body.data.title || '';
                    window.selectMedia(filename, title);
                } else if (typeof window.selectMedialist === 'function' && openerField.indexOf('REX_MEDIALIST_') === 0) {
                    window.selectMedialist(filename);
                }
            }

        }
    });
    
    // Upload-Fehler
    uppy.on('upload-error', function(file, error, response) {

    });
    
    // Datei entfernt
    uppy.on('file-removed', function(file) {
        if (file.meta && file.meta.filename && inputElement) {
            const currentValue = inputElement.value;
            const files = currentValue.split(',').filter(function(f) {
                return f !== file.meta.filename;
            });
            inputElement.value = files.join(',');
        }
    });
}

/**
 * Lädt Metadaten-Felder vom Server
 */
function loadMetadataFields(apiToken) {
    
    // Im Backend ist Token optional, da User bereits authentifiziert ist
    const url = apiToken 
        ? window.location.origin + '/redaxo/index.php?rex-api-call=uppy_metadata&action=get_fields&api_token=' + apiToken
        : window.location.origin + '/redaxo/index.php?rex-api-call=uppy_metadata&action=get_fields';
    
    return fetch(url)
        .then(function(response) {
            console.log('Metadata API Response Status:', response.status);
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(function(data) {
            console.log('Metadata API Response:', data);
            if (data.success && data.data) {
                console.log('API data.data:', data.data.length, 'Felder geladen');
                return data.data.map(function(field) {
                    const mappedField = {
                        id: field.id,
                        name: field.name,
                        placeholder: field.placeholder || '',
                        type: field.type || 'text',
                        is_multilang: field.is_multilang || false,
                        clang_id: field.clang_id,
                        base_field: field.base_field,
                        languages: field.languages || undefined
                    };

                    return mappedField;
                });
            }
            return [];
        })
        .catch(function(error) {
            console.error('Uppy Metadaten-Fehler:', error);
            return [];
        });
}

/**
 * Fügt Compressor-Plugin hinzu (Client-seitige Bildoptimierung)
 */
function addCompressorPlugin(uppy, config) {
    if (!config.enable_resize) {
        return;
    }
    
    uppy.on('file-added', function(file) {
        if (!file.type || !file.type.startsWith('image/')) {
            return;
        }
        
        resizeImage(file, config).then(function(resizedBlob) {
            if (resizedBlob) {
                uppy.setFileState(file.id, {
                    data: resizedBlob,
                    size: resizedBlob.size
                });
            }
        }).catch(function(error) {

        });
    });
}

/**
 * Resized ein Bild mit Canvas API
 */
function resizeImage(file, config) {
    return new Promise(function(resolve, reject) {
        const img = new Image();
        const reader = new FileReader();
        
        reader.onload = function(e) {
            img.onload = function() {
                try {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    
                    let width = img.width;
                    let height = img.height;
                    
                    // Proportional skalieren
                    if (width > config.resize_width || height > config.resize_height) {
                        const ratio = Math.min(config.resize_width / width, config.resize_height / height);
                        width = Math.floor(width * ratio);
                        height = Math.floor(height * ratio);
                    }
                    
                    canvas.width = width;
                    canvas.height = height;
                    
                    // EXIF-Orientierung korrigieren
                    if (config.fix_exif_orientation) {
                        ctx.drawImage(img, 0, 0, width, height);
                    } else {
                        ctx.drawImage(img, 0, 0, width, height);
                    }
                    
                    canvas.toBlob(function(blob) {
                        resolve(blob);
                    }, file.type, config.resize_quality / 100);
                } catch (error) {
                    reject(error);
                }
            };
            
            img.onerror = reject;
            img.src = e.target.result;
        };
        
        reader.onerror = reject;
        reader.readAsDataURL(file.data);
    });
}

/**
 * Gibt Übersetzung zurück
 */
function getTranslation(locale, key, maxFiles) {
    maxFiles = maxFiles || 10;
    
    const translations = {
        'de-DE': {
            'note': `Dateien hochladen (max. ${maxFiles})`
        },
        'en-US': {
            'note': `Upload files (max. ${maxFiles})`
        }
    };
    
    return translations[locale]?.[key] || '';
}

/**
 * Mutation Observer für dynamisch hinzugefügte Elemente
 */
function setupMutationObserver() {
    if (!document.body) {
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

            initUppyWidgets();
        }
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
}

/**
 * Eigenes Modal für Metadaten nach Upload
 */
function setupMetadataModal(uppy, metaFields) {
    console.log('setupMetadataModal aufgerufen mit', metaFields.length, 'Feldern');
    
    const globalConfig = window.rex?.uppy_config || {};
    const enableImageEditor = globalConfig.enable_image_editor || false;
    
    if (enableImageEditor) {
        console.log('Image Editor ist aktiv - Edit-Button wird NICHT überschrieben');
        console.log('Metadaten-Modal öffnet sich automatisch nach Upload');
        // Wenn Image Editor aktiv ist, Edit-Button nicht abfangen
        // Metadata-Modal wird über upload-success Event geöffnet
        return;
    }

    // Nur wenn KEIN Image Editor: Edit-Button für Metadata-Modal abfangen
    console.log('Image Editor inaktiv - Edit-Button wird für Metadata-Modal verwendet');

    // MutationObserver für File-Items
    const observeFileItems = function() {
        const filesContainer = document.querySelector('.uppy-Dashboard-filesInner');
        if (!filesContainer) {
            console.log('Warte auf Dashboard Container...');
            setTimeout(observeFileItems, 100);
            return;
        }
        
        console.log('Dashboard Container gefunden, starte Observer');

        const processFileItem = function(fileItem) {
            const fileId = fileItem.id;
            
            // Standard Edit-Button finden
            let editBtn = null;
            const allActions = fileItem.querySelectorAll('.uppy-Dashboard-Item-action');
            allActions.forEach(btn => {
                if (btn.classList.contains('uppy-Dashboard-Item-action--edit')) {
                    editBtn = btn;
                }
            });
            
            if (!editBtn) {
                console.log('Kein Edit-Button gefunden für:', fileId);
                return;
            }
            
            // Prüfen ob Handler bereits registriert
            if (editBtn.hasAttribute('data-uppy-custom-handler')) {
                console.log('Handler bereits registriert für:', fileId);
                return;
            }

            console.log('Registriere Click-Handler für:', fileId);
            
            // Markiere als verarbeitet
            editBtn.setAttribute('data-uppy-custom-handler', 'true');
            
            // Click-Event abfangen und unser Modal öffnen
            editBtn.addEventListener('click', function(e) {
                console.log('Edit-Button geklickt für:', fileId);
                e.preventDefault();
                e.stopPropagation();
                
                // File-Objekt aus Uppy holen
                const files = uppy.getFiles();
                const file = files.find(f => fileItem.id.includes(f.id));
                
                if (file) {
                    console.log('Öffne Metadata Modal für:', file.name);
                    showMetadataModal(uppy, file, metaFields);
                } else {
                    console.error('Datei nicht gefunden in Uppy:', fileId);
                }
            }, true);
        };
        
        // Bereits vorhandene File-Items verarbeiten
        const existingItems = filesContainer.querySelectorAll('.uppy-Dashboard-Item');
        console.log('Verarbeite', existingItems.length, 'existierende Items');
        existingItems.forEach(processFileItem);
        
        // Observer für neue Items
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === Node.ELEMENT_NODE && 
                        node.classList && 
                        node.classList.contains('uppy-Dashboard-Item')) {

                        processFileItem(node);
                    }
                });
            });
        });
        
        observer.observe(filesContainer, {
            childList: true,
            subtree: true
        });
        
        console.log('MutationObserver gestartet');
    };
    
    // Start nach kurzer Verzögerung
    setTimeout(observeFileItems, 200);
}

/**
 * Zeigt Modal mit Metadaten-Feldern an
 */
function showMetadataModal(uppy, file, metaFields) {
    // Modal HTML erstellen
    const modalHTML = `
        <div class="modal fade uppy-metadata-modal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                        <h4 class="modal-title">Metadaten bearbeiten: ${file.name}</h4>
                    </div>
                    <div class="modal-body">
                        <form class="uppy-metadata-form">
                            ${metaFields.map(field => {

                                const value = file.meta[field.id] || '';
                                const fieldType = field.type || 'text';
                                const isMultilang = field.is_multilang || false;
                                
                                if (fieldType === 'select' && field.options) {
                                    // Select-Feld (z.B. für Kategorie)
                                    const options = field.options.map(opt => {
                                        const selected = opt.value == value ? 'selected' : '';
                                        return `<option value="${opt.value}" ${selected}>${opt.label}</option>`;
                                    }).join('');
                                    
                                    return `
                                        <div class="form-group">
                                            <label class="control-label">${field.name}</label>
                                            <select name="${field.id}" class="form-control selectpicker" data-live-search="true">
                                                ${options}
                                            </select>
                                        </div>
                                    `;
                                } else if (isMultilang && field.languages) {
                                    // Mehrsprachiges Feld: Hidden Field + erste Sprache + Collapse für weitere
                                    let parsedValues = {};
                                    try {
                                        if (typeof value === 'string' && value) {
                                            parsedValues = JSON.parse(value).reduce((acc, item) => {
                                                acc[item.clang_id] = item.value;
                                                return acc;
                                            }, {});
                                        }
                                    } catch (e) {}
                                    
                                    const inputTag = fieldType === 'lang_textarea' ? 'textarea' : 'input';
                                    const collapseId = 'collapse-' + field.id.replace(/[^a-z0-9]/gi, '-');
                                    
                                    // Erste Sprache (Hauptsprache)
                                    const firstLang = field.languages[0];
                                    const firstValue = parsedValues[firstLang.clang_id] || '';
                                    let firstLanguageInput = '';
                                    
                                    if (inputTag === 'textarea') {
                                        firstLanguageInput = `
                                            <textarea 
                                                class="form-control uppy-lang-input" 
                                                data-clang-id="${firstLang.clang_id}"
                                                rows="3"
                                                placeholder="${firstLang.clang_name}..."
                                            >${firstValue}</textarea>
                                        `;
                                    } else {
                                        firstLanguageInput = `
                                            <input 
                                                type="text" 
                                                class="form-control uppy-lang-input" 
                                                data-clang-id="${firstLang.clang_id}"
                                                value="${firstValue}"
                                                placeholder="${firstLang.clang_name}..."
                                            >
                                        `;
                                    }
                                    
                                    // Weitere Sprachen (wenn vorhanden)
                                    let additionalLanguages = '';
                                    if (field.languages.length > 1) {
                                        const additionalInputs = field.languages.slice(1).map(lang => {
                                            const langValue = parsedValues[lang.clang_id] || '';
                                            if (inputTag === 'textarea') {
                                                return `
                                                    <div class="form-group form-group-sm" style="margin-top: 10px;">
                                                        <label style="font-weight: normal;"><i class="fa fa-flag"></i> ${lang.clang_name} (${lang.clang_code})</label>
                                                        <textarea 
                                                            class="form-control uppy-lang-input" 
                                                            data-clang-id="${lang.clang_id}"
                                                            rows="2"
                                                            placeholder="${lang.clang_name}..."
                                                        >${langValue}</textarea>
                                                    </div>
                                                `;
                                            } else {
                                                return `
                                                    <div class="form-group form-group-sm" style="margin-top: 10px;">
                                                        <label style="font-weight: normal;"><i class="fa fa-flag"></i> ${lang.clang_name} (${lang.clang_code})</label>
                                                        <input 
                                                            type="text" 
                                                            class="form-control uppy-lang-input" 
                                                            data-clang-id="${lang.clang_id}"
                                                            value="${langValue}"
                                                            placeholder="${lang.clang_name}..."
                                                        >
                                                    </div>
                                                `;
                                            }
                                        }).join('');
                                        
                                        additionalLanguages = `
                                            <button type="button" 
                                                    class="btn btn-default btn-sm" 
                                                    data-toggle="collapse" 
                                                    data-target="#${collapseId}"
                                                    style="margin-top: 5px; margin-bottom: 10px;">
                                                <i class="fa fa-globe"></i> Weitere Sprachen (${field.languages.length - 1})
                                            </button>
                                            <div class="collapse" id="${collapseId}">
                                                ${additionalInputs}
                                            </div>
                                        `;
                                    }
                                    
                                    return `
                                        <div class="form-group uppy-multilang-field" data-field-id="${field.id}">
                                            <label class="control-label">${field.name}</label>
                                            <input type="hidden" name="${field.id}" class="uppy-lang-hidden" value="${value}">
                                            ${firstLanguageInput}
                                            ${additionalLanguages}
                                        </div>
                                    `;
                                } else if (fieldType === 'textarea') {
                                    return `
                                        <div class="form-group">
                                            <label for="meta-${field.id}">${field.name}</label>
                                            <textarea 
                                                class="form-control" 
                                                id="meta-${field.id}" 
                                                name="${field.id}"
                                                rows="3"
                                                placeholder="${field.placeholder || ''}"
                                            >${value}</textarea>
                                        </div>
                                    `;
                                } else {
                                    return `
                                        <div class="form-group">
                                            <label for="meta-${field.id}">${field.name}</label>
                                            <input 
                                                type="text" 
                                                class="form-control" 
                                                id="meta-${field.id}" 
                                                name="${field.id}"
                                                placeholder="${field.placeholder || ''}"
                                                value="${value}"
                                            >
                                        </div>
                                    `;
                                }
                            }).join('')}
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Abbrechen</button>
                        <button type="button" class="btn btn-primary uppy-metadata-save">Speichern</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Modal zum DOM hinzufügen
    const modalEl = document.createElement('div');
    modalEl.innerHTML = modalHTML;
    document.body.appendChild(modalEl.firstElementChild);
    
    const modal = jQuery('.uppy-metadata-modal').last();
    
    // Save Button Handler
    modal.find('.uppy-metadata-save').on('click', function() {
        // Keine Pflichtfeld-Validierung mehr

        // Mehrsprachige Felder zuerst in Hidden Fields als JSON speichern
        modal.find('.uppy-multilang-field').each(function() {
            const container = jQuery(this);
            const fieldId = container.data('field-id');
            const hiddenInput = container.find('.uppy-lang-hidden');
            const data = [];
            
            container.find('.uppy-lang-input').each(function() {
                const input = jQuery(this);
                const clangId = parseInt(input.data('clang-id'), 10);
                const value = input.val() || '';
                
                if (value.trim()) {
                    data.push({
                        clang_id: clangId,
                        value: value.trim()
                    });
                }
            });
            
            hiddenInput.val(JSON.stringify(data));
        });
        
        // Jetzt alle Felder aus Form Data sammeln
        const form = modal.find('.uppy-metadata-form')[0];
        const formData = new FormData(form);
        const metadata = {};
        
        for (const [key, value] of formData.entries()) {
            metadata[key] = value;
        }
        
        // Metadaten in Uppy-Datei speichern
        uppy.setFileMeta(file.id, metadata);

        // Modal schließen
        modal.modal('hide');
    });
    
    // Modal anzeigen
    modal.modal('show');
    
    // Bootstrap selectpicker initialisieren (nach dem Modal angezeigt wird)
    modal.on('shown.bs.modal', function() {
        if (jQuery.fn.selectpicker) {
            modal.find('.selectpicker').selectpicker();

        }
    });
    
    // Modal nach Schließen aus DOM entfernen
    modal.on('hidden.bs.modal', function() {
        modal.remove();
    });
}

// Initialisierung bei rex:ready

if (typeof jQuery !== 'undefined') {
    jQuery(document).on('rex:ready', function() {

        initUppyWidgets();
        setupMutationObserver();
    });

} else {

}
