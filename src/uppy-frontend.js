/**
 * Uppy Frontend Integration für REDAXO
 * Verwendet lokale NPM-Module statt CDN
 * Vanilla JavaScript - kein jQuery!
 */
import Uppy from '@uppy/core';
import Dashboard from '@uppy/dashboard';
import XHRUpload from '@uppy/xhr-upload';
import German from '@uppy/locales/lib/de_DE';
import { UppyCustomWidget } from './uppy-custom-widget';

// Frontend ist nur im Frontend verfügbar, daher sofort initialisieren
document.addEventListener('DOMContentLoaded', function() {
    initUppyWidgets();
    setupMutationObserver();
});

function initUppyWidgets() {
    // Standard Dashboard Widgets
    const widgets = document.querySelectorAll('input[data-widget="uppy"]:not([data-uppy-initialized])');
    if (widgets.length > 0) {
        console.log('Uppy Frontend: Initialisiere ' + widgets.length + ' Dashboard Widget(s)');
        widgets.forEach(function(inputElement) {
            initializeUppyWidget(inputElement);
        });
    }

    // Custom Widgets (.uppy-upload-widget)
    const customWidgets = document.querySelectorAll('.uppy-upload-widget:not([data-uppy-initialized])');
    if (customWidgets.length > 0) {
        console.log('Uppy Frontend: Initialisiere ' + customWidgets.length + ' Custom Widget(s)');
        customWidgets.forEach(function(inputElement) {
            new UppyCustomWidget(inputElement);
        });
    }
}

function initializeUppyWidget(inputElement) {
    if (inputElement.dataset.uppyInitialized) {
        return;
    }
    
    inputElement.dataset.uppyInitialized = 'true';
    
    // Container für Uppy Dashboard erstellen
    const container = document.createElement('div');
    container.className = 'uppy-wrapper';
    inputElement.parentNode.insertBefore(container, inputElement.nextSibling);
    
    // Konfiguration aus data-Attributen
    const config = {
        apiToken: inputElement.dataset.apiToken || '',
        maxFiles: parseInt(inputElement.dataset.maxFiles) || 10,
        maxFileSize: parseInt(inputElement.dataset.maxFilesize) || (200 * 1024 * 1024),
        allowedTypes: inputElement.dataset.allowedTypes ? inputElement.dataset.allowedTypes.split(',') : [],
        categoryId: parseInt(inputElement.dataset.categoryId) || 0,
        locale: inputElement.dataset.locale || 'de-DE',
        enable_resize: inputElement.dataset.enableResize !== 'false',
        resize_width: parseInt(inputElement.dataset.resizeWidth) || 2000,
        resize_height: parseInt(inputElement.dataset.resizeHeight) || 2000,
        resize_quality: parseInt(inputElement.dataset.resizeQuality) || 85,
        fix_exif_orientation: inputElement.dataset.fixExifOrientation !== 'false'
    };
    
    // Metadaten-Felder laden
    loadMetadataFields(config.apiToken).then(function(metaFields) {
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
            note: getTranslation(config.locale, 'note'),
            metaFields: metaFields
        });
        
        addCompressorPlugin(uppy, config);
        initializeUppyPlugins(uppy, config, inputElement);
    }).catch(function(error) {
        console.error('Fehler beim Laden der Metadaten-Felder:', error);
        initializeUppyFallback(container, config, inputElement);
    });
}

function initializeUppyPlugins(uppy, config, inputElement) {
    uppy.use(XHRUpload, {
        endpoint: window.location.origin + '/index.php?rex-api-call=uppy_uploader&func=upload&api_token=' + encodeURIComponent(config.apiToken) + '&category_id=' + config.categoryId,
        formData: true,
        fieldName: 'file',
        allowedMetaFields: true, // WICHTIG: Alle Metadaten mitsenden
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        getResponseData: function(responseText, response) {
            try {
                return JSON.parse(responseText);
            } catch (e) {
                return { success: false, message: 'Invalid response' };
            }
        }
    });
    
    setupEventHandlers(uppy, config, inputElement);
}

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

function setupEventHandlers(uppy, config, inputElement) {
    uppy.on('upload-success', function(file, response) {
        if (response.body && response.body.success && response.body.data) {
            const filename = response.body.data.filename;
            
            if (inputElement) {
                const currentValue = inputElement.value;
                const files = currentValue ? currentValue.split(',') : [];
                if (!files.includes(filename)) {
                    files.push(filename);
                    inputElement.value = files.join(',');
                }
            }
            
            console.log('Upload erfolgreich:', filename);
        }
    });
    
    uppy.on('upload-error', function(file, error, response) {
        console.error('Upload-Fehler:', error);
    });
    
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

function loadMetadataFields(apiToken) {
    return fetch(window.location.origin + '/index.php?rex-api-call=uppy_metadata&action=get_fields&api_token=' + apiToken)
        .then(function(response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(function(data) {
            if (data.success && data.data) {
                return data.data.map(function(field) {
                    return {
                        id: field.id,
                        name: field.name,
                        placeholder: field.placeholder || ''
                    };
                });
            }
            return [];
        });
}

function addCompressorPlugin(uppy, config) {
    if (!config.enable_resize) {
        return;
    }
    
    uppy.on('file-added', function(file) {
        // SVGs nicht resizen, da Canvas sie rasterisiert (zu PNG konvertiert)
        if (!file.type || !file.type.startsWith('image/') || file.type === 'image/svg+xml') {
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
            console.error('Resize-Fehler:', error);
        });
    });
}

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
                    
                    if (width > config.resize_width || height > config.resize_height) {
                        const ratio = Math.min(config.resize_width / width, config.resize_height / height);
                        width = Math.floor(width * ratio);
                        height = Math.floor(height * ratio);
                    }
                    
                    canvas.width = width;
                    canvas.height = height;
                    
                    ctx.drawImage(img, 0, 0, width, height);
                    
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

function getTranslation(locale, key) {
    const translations = {
        'de-DE': {
            'note': 'Dateien hochladen (max. 10)'
        },
        'en-US': {
            'note': 'Upload files (max. 10)'
        }
    };
    
    return translations[locale]?.[key] || '';
}

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
            console.log('Uppy Frontend: Neue Widgets erkannt, initialisiere');
            initUppyWidgets();
        }
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
}
