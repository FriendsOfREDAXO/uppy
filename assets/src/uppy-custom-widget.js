import Uppy from '@uppy/core';
import Dashboard from '@uppy/dashboard';
import Webcam from '@uppy/webcam';
import XHRUpload from '@uppy/xhr-upload';
import ImageEditor from '@uppy/image-editor';
import German from '@uppy/locales/lib/de_DE';
import { ChunkUploader } from './chunk-uploader';

// Patch missing translations in German locale
if (German && German.strings) {
    German.strings.help = 'Hilfe';
}

export class UppyCustomWidget {
    constructor(inputElement, options = {}) {
        this.input = inputElement;
        this.options = options;
        this.container = null;
        this.listContainer = null;
        this.uppy = null;
        this.mediapoolWidgetId = null; // Eindeutige ID für Medienpool-Callbacks
        
        this.init();
    }

    init() {
        if (this.input.dataset.uppyInitialized) return;
        this.input.dataset.uppyInitialized = 'true';
        this.input.style.display = 'none';

        // Eindeutige Widget-ID für Medienpool-Callbacks generieren
        this.mediapoolWidgetId = 'uppy_' + Math.random().toString(36).substr(2, 9);
        
        // Globalen Callback für Medienpool registrieren
        this.registerMediapoolCallback();

        // Track uploading files for visual feedback - MUSS VOR renderList() initialisiert werden
        this.uploadingFiles = new Map(); // fileId -> filename

        this.renderUI();
        this.initUppy();
        this.renderList();
        
        // Mutation Observer für dynamisch nachgeladene Felder (z.B. YForm)
        this.setupMutationObserver();
        this.initListeners();
    }

    setupMutationObserver() {
        // ... (wird später implementiert falls nötig)
    }
    
    registerMediapoolCallback() {
        const self = this;
        
        // Globalen Callback für writeREXMedialist registrieren
        // Dies wird von REDAXO's Medienpool aufgerufen
        if (!window.uppyMediapoolCallbacks) {
            window.uppyMediapoolCallbacks = {};
            
            // Globale writeREXMedialist-Funktion erweitern
            const originalWriteREXMedialist = window.writeREXMedialist;
            window.writeREXMedialist = function(id) {
                // Prüfen ob es ein Uppy-Widget ist
                if (window.uppyMediapoolCallbacks[id]) {
                    window.uppyMediapoolCallbacks[id]();
                    return false;
                }
                // Fallback auf Original-Funktion
                if (originalWriteREXMedialist) {
                    return originalWriteREXMedialist(id);
                }
                return false;
            };
        }
        
        // Callback für dieses Widget registrieren
        window.uppyMediapoolCallbacks[this.mediapoolWidgetId] = function() {
            self.handleMediapoolSelection();
        };
    }
    
    handleMediapoolSelection() {
        const selectElement = document.getElementById('REX_MEDIALIST_SELECT_' + this.mediapoolWidgetId);
        if (!selectElement) return;
        
        const config = this.getConfig();
        const maxFiles = config.maxFiles || 10;
        const currentFiles = this.getFiles();
        const allowMultiple = maxFiles > 1;
        
        // Alle Optionen aus dem Select-Element lesen
        const options = Array.from(selectElement.options);
        const selectedFilenames = options.map(opt => opt.value).filter(f => f);
        
        if (!allowMultiple && selectedFilenames.length > 0) {
            // Single-Select: Ersetze vorhandene Datei
            this.setFiles([selectedFilenames[0]]);
        } else {
            // Multi-Select: Ermittle nur die NEUEN Dateien
            const newFiles = selectedFilenames.filter(filename => !currentFiles.includes(filename));
            
            const remainingSlots = maxFiles - currentFiles.length;
            const filesToAdd = newFiles.slice(0, remainingSlots);
            
            filesToAdd.forEach(filename => {
                this.addFile(filename);
            });
            
            // Still enforcement - kein Alert, da es hinter dem Medienpool-Fenster wäre
        }
        
        this.renderList();
    }
        
    initListeners() {
        // Listen for external changes to the input
        this.input.addEventListener('change', () => this.renderList());
    }

    getConfig() {
        const globalConfig = window.rex?.uppy_config || {};
        
        // maxFiles: 0 = unbegrenzt, explizit prüfen ob Attribut vorhanden ist
        let maxFiles;
        if (this.input.dataset.maxFiles !== undefined && this.input.dataset.maxFiles !== '') {
            maxFiles = parseInt(this.input.dataset.maxFiles);
        } else if (globalConfig.max_files !== undefined) {
            maxFiles = parseInt(globalConfig.max_files);
        } else {
            maxFiles = 10; // Default nur wenn nichts gesetzt
        }
        
        // Nur negative Werte auf 10 setzen
        if (maxFiles < 0) {
            maxFiles = 10;
        }
        
        return {
            apiToken: this.input.dataset.apiToken || '',
            maxFiles: maxFiles,
            maxFileSize: (parseInt(this.input.dataset.maxFilesize) || parseInt(globalConfig.max_file_size) || 200) * 1024 * 1024,
            allowedTypes: this.input.dataset.allowedTypes 
                ? this.input.dataset.allowedTypes.split(',').map(t => t.trim()) 
                : (globalConfig.allowed_types ? globalConfig.allowed_types.split(',').map(t => t.trim()) : []),
            categoryId: parseInt(this.input.dataset.categoryId) || 0,
            locale: this.input.dataset.locale || this.input.dataset.lang || 'de-DE',
            enableImageEditor: this.input.dataset.enableImageEditor === 'true',
            enableWebcam: this.input.dataset.enableWebcam === 'true',
            ...this.options
        };
    }

    getIcon(name) {
        const icons = {
            add: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>',
            file: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>',
            up: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg>',
            down: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>',
            remove: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line></svg>',
            edit: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>'
        };
        return icons[name] || '';
    }

    renderUI() {
        this.container = document.createElement('div');
        this.container.className = 'uppy-custom-widget';
        
        this.listContainer = document.createElement('ul');
        this.listContainer.className = 'uppy-file-list';
        this.container.appendChild(this.listContainer);
        
        // Button Container für mehrere Buttons mit File Count Info
        this.buttonContainer = document.createElement('div');
        this.buttonContainer.className = 'uppy-button-group';
        this.buttonContainer.style.display = 'flex';
        this.buttonContainer.style.gap = '8px';
        this.buttonContainer.style.alignItems = 'center';
        
        this.addBtn = document.createElement('button');
        this.addBtn.type = 'button';
        this.addBtn.className = 'uppy-btn uppy-btn-primary';
        this.addBtn.innerHTML = this.getIcon('add') + ' Dateien hochladen';
        this.addBtn.addEventListener('click', () => {
            if (this.uppy) {
                this.uppy.getPlugin('Dashboard').openModal();
            }
        });
        this.buttonContainer.appendChild(this.addBtn);
        
        // Medienpool-Button nur wenn Attribut gesetzt ist
        if (this.input.dataset.allowMediapool === 'true') {
            this.mediapoolBtn = document.createElement('button');
            this.mediapoolBtn.type = 'button';
            this.mediapoolBtn.className = 'uppy-btn uppy-btn-secondary';
            this.mediapoolBtn.innerHTML = '<i class="fa fa-folder-open"></i> Aus Medienpool wählen';
            this.mediapoolBtn.addEventListener('click', () => this.openMediapoolSelection());
            this.buttonContainer.appendChild(this.mediapoolBtn);
        }
        
        // File count info neben die Buttons
        this.fileCountInfo = document.createElement('div');
        this.fileCountInfo.className = 'uppy-file-count-info';
        this.fileCountInfo.style.cssText = 'font-size: 12px; padding: 4px 8px; border-radius: 3px; background-color: #333; color: #fff; margin-left: auto;';
        this.buttonContainer.appendChild(this.fileCountInfo);
        
        this.container.appendChild(this.buttonContainer);
        
        this.input.parentNode.insertBefore(this.container, this.input.nextSibling);
    }

    renderList() {
        this.listContainer.innerHTML = '';
        const files = this.getFiles();
        
        if (files.length === 0 && this.uploadingFiles.size === 0) {
            const emptyState = document.createElement('li');
            emptyState.className = 'uppy-empty-state';
            emptyState.textContent = 'Keine Dateien ausgewählt';
            this.listContainer.appendChild(emptyState);
            this.updateButtonVisibility();
            return;
        }
        
        // Show uploading files first
        if (this.uploadingFiles.size > 0) {
            this.uploadingFiles.forEach((filename, fileId) => {
                const li = document.createElement('li');
                li.className = 'uppy-file-item uppy-file-uploading';
                li.dataset.fileId = fileId;
                
                li.innerHTML = `
                    <div class="uppy-file-preview">
                        <div class="uppy-file-icon uppy-icon-uploading">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="uppy-spinner">
                                <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                            </svg>
                        </div>
                        <div class="uppy-file-info">
                            <span class="uppy-filename" title="${filename}">${filename}</span>
                            <span class="uppy-upload-status">Wird hochgeladen...</span>
                        </div>
                    </div>
                `;
                
                this.listContainer.appendChild(li);
            });
        }
        
        files.forEach((filename, index) => {
            // Skip files that are currently uploading
            if (Array.from(this.uploadingFiles.values()).includes(filename)) {
                return;
            }
            
            const li = document.createElement('li');
            li.className = 'uppy-file-item';
            
            const isImage = /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(filename);
            const thumbUrl = isImage ? `/media/${filename}` : '';
            
            const previewHtml = isImage 
                ? `<img src="${thumbUrl}" class="uppy-thumbnail" alt="${filename}" onerror="this.style.display='none'">`
                : `<div class="uppy-file-icon">${this.getIcon('file')}</div>`;
                
            li.innerHTML = `
                <div class="uppy-file-preview">
                    ${previewHtml}
                    <div class="uppy-file-info">
                        <span class="uppy-filename" title="${filename}">${filename}</span>
                    </div>
                </div>
                <div class="uppy-actions">
                    <button type="button" class="uppy-btn uppy-btn-danger" data-action="remove" title="Löschen">${this.getIcon('remove')}</button>
                    <button type="button" class="uppy-btn" data-action="edit" title="Metadaten bearbeiten">${this.getIcon('edit')}</button>
                    <span style="width: 24px; display: inline-block;"></span>
                    <button type="button" class="uppy-btn" data-action="down" title="Nach unten" ${index === files.length - 1 ? 'style="visibility: hidden;"' : ''}>${this.getIcon('down')}</button>
                    <button type="button" class="uppy-btn" data-action="up" title="Nach oben" ${index === 0 ? 'style="visibility: hidden;"' : ''}>${this.getIcon('up')}</button>
                </div>
            `;
            
            // Event Delegation for buttons
            li.querySelectorAll('button[data-action]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const action = e.currentTarget.dataset.action;
                    if (action === 'remove') this.removeFile(index);
                    if (action === 'up') this.moveFile(index, -1);
                    if (action === 'down') this.moveFile(index, 1);
                    if (action === 'edit') this.openMetadataModal(filename);
                });
            });
            
            this.listContainer.appendChild(li);
        });
        
        this.updateButtonVisibility();
    }

    updateButtonVisibility() {
        const config = this.getConfig();
        const maxFiles = config.maxFiles;
        const currentFileCount = this.getFiles().length;
        const isUnlimited = maxFiles === 0;
        const isMaxReached = !isUnlimited && currentFileCount >= maxFiles;
        
        // Dateianzahl anzeigen
        if (this.fileCountInfo) {
            if (isUnlimited) {
                this.fileCountInfo.textContent = `Anzahl: ${currentFileCount}`;
                this.fileCountInfo.style.color = '#fff'; // Weiß
                this.fileCountInfo.style.fontWeight = 'normal';
            } else {
                this.fileCountInfo.textContent = `Anzahl: ${currentFileCount}/${maxFiles}`;
                if (isMaxReached) {
                    this.fileCountInfo.style.color = '#ff8c00'; // Orange
                    this.fileCountInfo.style.fontWeight = 'bold';
                } else {
                    this.fileCountInfo.style.color = '#fff'; // Weiß
                    this.fileCountInfo.style.fontWeight = 'normal';
                }
            }
        }
        
        // Nur Buttons ausblenden wenn Maximum erreicht, nicht bei unbegrenzt
        if (this.addBtn) {
            this.addBtn.style.display = isMaxReached ? 'none' : 'inline-flex';
        }
        if (this.mediapoolBtn) {
            this.mediapoolBtn.style.display = isMaxReached ? 'none' : 'inline-flex';
        }
    }

    getFiles() {
        return this.input.value ? this.input.value.split(',').map(f => f.trim()).filter(f => f) : [];
    }

    setFiles(files) {
        this.input.value = files.join(',');
        this.input.dispatchEvent(new Event('change', { bubbles: true }));
        // NICHT syncUppyWithFiles() hier aufrufen - das kann während Uploads problematisch sein
    }

    addFile(filename) {
        const files = this.getFiles();
        if (!files.includes(filename)) {
            files.push(filename);
            this.input.value = files.join(',');
            this.input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    syncUppyWithFiles() {
        // Synchronisiere das versteckte Input mit Uppy Dashboard
        // Dies ist relevant wenn Dateien extern (z.B. über Medienpool) hinzugefügt werden
        if (!this.uppy) return;
        
        const currentFiles = this.getFiles();
        const uppyFiles = this.uppy.getFiles();
        
        // Entferne Dateien aus Uppy die nicht mehr im Input sind
        uppyFiles.forEach(file => {
            if (!currentFiles.includes(file.name)) {
                this.uppy.removeFile(file.id);
            }
        });
        
        // WICHTIG: Restrictions nicht während des Uploads ändern!
        // Prüfe ob gerade Uploads laufen
        const hasActiveUploads = uppyFiles.some(file => 
            file.progress && file.progress.uploadStarted && !file.progress.uploadComplete
        );
        
        if (!hasActiveUploads) {
            // Update Uppy Restrictions NUR wenn keine Uploads laufen
            const config = this.getConfig();
            const maxFiles = config.maxFiles || 10;
            const existingFileCount = currentFiles.length;
            const remainingSlots = Math.max(0, maxFiles - existingFileCount);
            
            // Setze neue maxNumberOfFiles Restriction
            this.uppy.setOptions({
                restrictions: {
                    maxFileSize: config.maxFileSize,
                    maxNumberOfFiles: remainingSlots,
                    allowedFileTypes: config.allowedTypes.length > 0 ? config.allowedTypes : null
                }
            });
        }
        
        // Hinweis: Neue Dateien können nicht zu Uppy hinzugefügt werden, 
        // da sie nicht als File-Objekte vorliegen (nur Dateinamen aus Medienpool)
        // Das Dashboard zeigt daher nur hochgeladene Dateien, nicht die aus dem Medienpool
    }

    removeFile(index) {
        const files = this.getFiles();
        files.splice(index, 1);
        this.input.value = files.join(',');
        this.input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    moveFile(index, direction) {
        const files = this.getFiles();
        const newIndex = index + direction;
        if (newIndex >= 0 && newIndex < files.length) {
            [files[index], files[newIndex]] = [files[newIndex], files[index]];
            this.input.value = files.join(',');
            this.input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    openMediapoolSelection() {
        const config = this.getConfig();
        const maxFiles = config.maxFiles || 10;
        const currentFileCount = this.getFiles().length;
        const allowMultiple = maxFiles > 1;
        
        // Bei max_files=1 wird ersetzt, bei mehr wird geprüft ob noch Platz ist
        if (!allowMultiple && currentFileCount >= 1) {
            // Single-Select: Vorhandene Datei wird ersetzt
        } else if (currentFileCount >= maxFiles) {
            // Multi-Select: Maximum erreicht - still return ohne Alert
            return;
        }
        
        // Verstecktes Select-Element und Input erstellen (wie bei MForm imglist)
        const selectId = 'REX_MEDIALIST_SELECT_' + this.mediapoolWidgetId;
        const inputId = 'REX_MEDIALIST_' + this.mediapoolWidgetId;
        
        // Prüfen ob Elemente bereits existieren, sonst erstellen
        let selectElement = document.getElementById(selectId);
        let inputElement = document.getElementById(inputId);
        
        if (!selectElement) {
            selectElement = document.createElement('select');
            selectElement.id = selectId;
            selectElement.name = 'REX_MEDIALIST_SELECT[' + this.mediapoolWidgetId + ']';
            selectElement.style.display = 'none';
            selectElement.size = 10;
            document.body.appendChild(selectElement);
        }
        
        if (!inputElement) {
            inputElement = document.createElement('input');
            inputElement.type = 'hidden';
            inputElement.id = inputId;
            inputElement.name = 'REX_INPUT_MEDIALIST[' + this.mediapoolWidgetId + ']';
            inputElement.value = '';
            document.body.appendChild(inputElement);
        }
        
        // Parameter für Medienpool vorbereiten
        let params = '';
        if (config.categoryId) {
            params += '&rex_file_category=' + config.categoryId;
        }
        if (config.types) {
            params += '&args[types]=' + encodeURIComponent(config.types);
        }
        
        // Medienpool öffnen (Multi-Select oder Single-Select)
        if (allowMultiple && typeof openREXMedialist === 'function') {
            openREXMedialist(this.mediapoolWidgetId, params);
        } else if (typeof openMediaPool === 'function') {
            openMediaPool(inputId);
        }
    }

    initUppy() {
        const config = this.getConfig();
        
        this.uppy = new Uppy({
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

        this.uppy.use(Dashboard, {
            inline: false,
            trigger: null, // We handle trigger manually
            target: 'body',
            width: 750,
            height: 550,
            showProgressDetails: true,
            proudlyDisplayPoweredByUppy: false,
            closeModalOnClickOutside: true,
            closeAfterFinish: false,
            disablePageScrollWhenModalOpen: false
        });

        if (config.enableImageEditor) {
            this.uppy.use(ImageEditor, {
                target: Dashboard,
                quality: 0.8,
            });
        }

        if (config.enableWebcam) {
            this.uppy.use(Webcam, {
                target: Dashboard,
                modes: ['picture'],
                mirror: true,
                facingMode: 'user',
            });
        }

        // XHR Upload
        const tokenParam = config.apiToken ? encodeURIComponent(config.apiToken) : '';
        
        // Signatur-Parameter vorbereiten
        let signatureParams = '';
        const signature = this.input.dataset.uppySignature;
        
        if (signature) {
            const allowedTypes = this.input.dataset.allowedTypes || '';
            const maxFilesize = this.input.dataset.maxFilesize || '';
            
            signatureParams = `&uppy_signature=${encodeURIComponent(signature)}` +
                              `&uppy_allowed_types=${encodeURIComponent(allowedTypes)}` +
                              `&uppy_max_filesize=${encodeURIComponent(maxFilesize)}`;
        }

        // Upload-Methode wählen (Chunk oder XHR)
        const globalConfig = window.rex?.uppy_config || {};
        const enableChunks = globalConfig.enable_chunks === true;

        if (enableChunks) {
            // Chunk-Size: Config ist in MB, wir brauchen Bytes
            const chunkSizeMB = globalConfig.chunk_size || 5;
            const chunkSizeBytes = chunkSizeMB * 1024 * 1024;
            
            const chunkUploader = new ChunkUploader(this.uppy, {
                endpoint: window.location.origin + '/redaxo/index.php?rex-api-call=uppy_uploader' + signatureParams,
                chunkSize: chunkSizeBytes,
                categoryId: config.categoryId,
                apiToken: tokenParam
            });
            chunkUploader.install();
        } else {
            this.uppy.use(XHRUpload, {
                endpoint: window.location.origin + '/redaxo/index.php?rex-api-call=uppy_uploader&func=upload&api_token=' + tokenParam + '&category_id=' + config.categoryId + signatureParams,
                formData: true,
                fieldName: 'file',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                limit: 5,
                getResponseData: (xhr) => {
                    // Hole die Response aus dem XHR-Objekt
                    let responseText = xhr.response || xhr.responseText;
                    
                    if (typeof responseText === 'object' && responseText !== null) {
                        // Response ist bereits als Objekt geparst
                        return responseText;
                    }
                    
                    if (typeof responseText === 'string') {
                        try {
                            return JSON.parse(responseText);
                        } catch (e) {
                            console.error('Uppy: Failed to parse server response as JSON:', e.message);
                            console.error('Response (first 500 chars):', responseText.substring(0, 500));
                            return { success: false, message: 'Invalid JSON response from server' };
                        }
                    }
                    
                    console.error('Uppy: No valid response data received from server');
                    return { success: false, message: 'No response data' };
                },
                getResponseError: (responseText) => {
                    try {
                        const response = JSON.parse(responseText);
                        return response.error || response.message || 'Upload failed';
                    } catch (e) {
                        return responseText;
                    }
                }
            });
        }

        this.uppy.on('upload-success', (file, response) => {
            // Remove from uploading list
            this.uploadingFiles.delete(file.id);
            
            if (response.body && (response.body.success || response.body.status === 'ok')) {
                const responseData = response.body.data || response.body;
                this.addFile(responseData.filename);
                
                // Datei aus Uppy entfernen nach erfolgreichem Upload
                this.uppy.removeFile(file.id);
                
                // Re-render to show final list without uploading state
                this.renderList();
                
                // Prüfen ob ALLE Uploads fertig sind und Maximum erreicht ist
                const config = this.getConfig();
                const maxFiles = config.maxFiles || 10;
                const currentFileCount = this.getFiles().length;
                const allUploadsComplete = this.uploadingFiles.size === 0;
                
                // Modal nur schließen wenn alle Uploads fertig und Maximum erreicht
                if (allUploadsComplete && currentFileCount >= maxFiles) {
                    const dashboard = this.uppy.getPlugin('Dashboard');
                    if (dashboard) {
                        dashboard.closeModal();
                    }
                }
                
                // Optional: Open metadata modal after upload
                // this.openMetadataModal(responseData.filename);
            } else {
                console.warn('Uppy: Upload erfolgte, aber Server-Response hat unerwartetes Format:', response.body);
                this.renderList();
            }
        });
        
        // Track upload start
        this.uppy.on('upload', (data) => {
            if (data.fileIDs) {
                data.fileIDs.forEach(fileID => {
                    const file = this.uppy.getFile(fileID);
                    if (file) {
                        this.uploadingFiles.set(file.id, file.name);
                    }
                });
                this.renderList();
            }
        });
        
        // Handle upload errors
        this.uppy.on('upload-error', (file, error) => {
            console.error('Upload error:', file.name, error);
            this.uploadingFiles.delete(file.id);
            this.renderList();
        });
    }

    async fetchMetaFields() {
        const config = this.getConfig();
        const tokenParam = config.apiToken ? `&api_token=${encodeURIComponent(config.apiToken)}` : '';
        
        try {
            const response = await fetch(`${window.location.origin}/redaxo/index.php?rex-api-call=uppy_metadata&action=get_fields${tokenParam}`);
            const data = await response.json();
            return data.success ? data.data : [];
        } catch (e) {
            console.error('Failed to fetch meta fields:', e);
            return [];
        }
    }

    async fetchMetadata(filename) {
        const config = this.getConfig();
        const tokenParam = config.apiToken ? `&api_token=${encodeURIComponent(config.apiToken)}` : '';
        
        try {
            const response = await fetch(`${window.location.origin}/redaxo/index.php?rex-api-call=uppy_metadata&action=load_metadata&filename=${encodeURIComponent(filename)}${tokenParam}`);
            const data = await response.json();
            return (data.success && data.metadata) ? data.metadata : {};
        } catch (e) {
            console.error('Failed to fetch metadata:', e);
            return {};
        }
    }

    async saveMetadata(filename, metadata) {
        const config = this.getConfig();
        const tokenParam = config.apiToken ? `&api_token=${encodeURIComponent(config.apiToken)}` : '';
        
        const formData = new FormData();
        formData.append('filename', filename);
        formData.append('metadata', JSON.stringify(metadata));
        
        try {
            const response = await fetch(`${window.location.origin}/redaxo/index.php?rex-api-call=uppy_metadata&action=save_metadata${tokenParam}`, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            return data.success;
        } catch (e) {
            console.error('Failed to save metadata:', e);
            return false;
        }
    }

    async openMetadataModal(filename) {
        const fields = await this.fetchMetaFields();
        const metadata = await this.fetchMetadata(filename);
        
        this.renderMetadataModal(filename, fields, metadata);
    }

    renderMetadataModal(filename, fields, metadata) {
        // Remove existing modal if any
        const existingModal = document.querySelector('.uppy-widget-modal-backdrop');
        if (existingModal) existingModal.remove();

        const backdrop = document.createElement('div');
        backdrop.className = 'uppy-widget-modal-backdrop';
        
        const modal = document.createElement('div');
        modal.className = 'uppy-widget-modal';
        
        // Header
        const header = document.createElement('div');
        header.className = 'uppy-widget-modal-header';
        header.innerHTML = `
            <h3 class="uppy-widget-modal-title">Metadaten: ${filename}</h3>
            <button type="button" class="uppy-widget-modal-close">&times;</button>
        `;
        
        // Body
        const body = document.createElement('div');
        body.className = 'uppy-widget-modal-body';
        
        const form = document.createElement('form');
        
        // Title Field (Standard)
        const titleGroup = document.createElement('div');
        titleGroup.className = 'uppy-widget-form-group';
        titleGroup.innerHTML = `
            <label class="uppy-widget-label">Titel</label>
            <input type="text" name="title" class="uppy-widget-input" value="${metadata.title || ''}">
        `;
        form.appendChild(titleGroup);
        
        // MetaInfo Fields
        fields.forEach(field => {
            // Skip title as it is already rendered
            if (field.id === 'title') return;

            const group = document.createElement('div');
            group.className = 'uppy-widget-form-group';
            
            const label = `<label class="uppy-widget-label">${field.name}</label>`;
            group.innerHTML = label;
            
            if (field.is_multilang && field.languages && field.languages.length > 0) {
                // Parse existing values
                let values = {};
                try {
                    const raw = metadata[field.id];
                    if (raw) {
                        // Try parsing as JSON first
                        let parsed;
                        try {
                            parsed = JSON.parse(raw);
                        } catch (e) {
                            // If not JSON, maybe it's just a string (legacy)
                            parsed = null;
                        }

                        if (Array.isArray(parsed)) {
                            parsed.forEach(item => values[item.clang_id] = item.value);
                        } else if (typeof raw === 'string') {
                            // Fallback: use raw string for default language
                            values[field.languages[0].clang_id] = raw;
                        }
                    }
                } catch(e) {
                    console.warn('Error parsing metadata value', e);
                }

                // First Language (Main)
                const firstLang = field.languages[0];
                const firstVal = values[firstLang.clang_id] || '';
                const firstInputName = `${field.id}[${firstLang.clang_id}]`;
                
                let firstInputHtml = '';
                if (field.type === 'textarea') {
                    firstInputHtml = `<textarea name="${firstInputName}" class="uppy-widget-textarea" data-clang-id="${firstLang.clang_id}" rows="3" placeholder="${firstLang.clang_name}...">${firstVal}</textarea>`;
                } else {
                    firstInputHtml = `<input type="text" name="${firstInputName}" class="uppy-widget-input" data-clang-id="${firstLang.clang_id}" value="${firstVal}" placeholder="${firstLang.clang_name}...">`;
                }
                group.innerHTML += firstInputHtml;

                // Additional Languages (Collapse)
                if (field.languages.length > 1) {
                    const collapseBtn = document.createElement('button');
                    collapseBtn.type = 'button';
                    collapseBtn.className = 'uppy-widget-collapse-btn';
                    collapseBtn.innerHTML = `<span>🌐</span> Weitere Sprachen (${field.languages.length - 1})`;
                    
                    const collapseContent = document.createElement('div');
                    collapseContent.className = 'uppy-widget-collapse-content';
                    
                    field.languages.slice(1).forEach(lang => {
                        const val = values[lang.clang_id] || '';
                        const inputName = `${field.id}[${lang.clang_id}]`;
                        
                        const langLabel = document.createElement('label');
                        langLabel.className = 'uppy-widget-lang-label';
                        langLabel.textContent = `${lang.clang_name} (${lang.clang_code})`;
                        
                        let inputHtml = '';
                        if (field.type === 'textarea') {
                            inputHtml = `<textarea name="${inputName}" class="uppy-widget-textarea" data-clang-id="${lang.clang_id}" rows="2" placeholder="${lang.clang_name}...">${val}</textarea>`;
                        } else {
                            inputHtml = `<input type="text" name="${inputName}" class="uppy-widget-input" data-clang-id="${lang.clang_id}" value="${val}" placeholder="${lang.clang_name}...">`;
                        }
                        
                        const wrapper = document.createElement('div');
                        wrapper.appendChild(langLabel);
                        wrapper.innerHTML += inputHtml;
                        collapseContent.appendChild(wrapper);
                    });
                    
                    collapseBtn.onclick = () => {
                        collapseContent.classList.toggle('is-open');
                    };
                    
                    group.appendChild(collapseBtn);
                    group.appendChild(collapseContent);
                }

            } else {
                // Normal Field
                const value = metadata[field.id] || '';
                let inputHtml = '';
                
                if (field.type === 'select') {
                    const options = (field.options || []).map(opt => 
                        `<option value="${opt.value}" ${opt.value == value ? 'selected' : ''}>${opt.label}</option>`
                    ).join('');
                    inputHtml = `<select name="${field.id}" class="uppy-widget-select">${options}</select>`;
                } else if (field.type === 'textarea') {
                    inputHtml = `<textarea name="${field.id}" class="uppy-widget-textarea" rows="3">${value}</textarea>`;
                } else {
                    inputHtml = `<input type="text" name="${field.id}" class="uppy-widget-input" value="${value}">`;
                }
                group.innerHTML += inputHtml;
            }
            
            form.appendChild(group);
        });
        
        body.appendChild(form);
        
        // Footer
        const footer = document.createElement('div');
        footer.className = 'uppy-widget-modal-footer';
        footer.innerHTML = `
            <button type="button" class="uppy-btn uppy-widget-modal-cancel">Abbrechen</button>
            <button type="button" class="uppy-btn uppy-btn-primary uppy-widget-modal-save">Speichern</button>
        `;
        
        modal.appendChild(header);
        modal.appendChild(body);
        modal.appendChild(footer);
        backdrop.appendChild(modal);
        document.body.appendChild(backdrop);
        
        // Show modal (next tick for transition)
        requestAnimationFrame(() => backdrop.classList.add('is-visible'));
        
        // Event Handlers
        const close = () => {
            backdrop.classList.remove('is-visible');
            setTimeout(() => backdrop.remove(), 300);
        };
        
        backdrop.querySelector('.uppy-widget-modal-close').addEventListener('click', close);
        backdrop.querySelector('.uppy-widget-modal-cancel').addEventListener('click', close);
        
        backdrop.querySelector('.uppy-widget-modal-save').addEventListener('click', async () => {
            const newMetadata = {};
            
            // Handle Title (always present)
            const titleInput = form.querySelector('input[name="title"]');
            if (titleInput) {
                newMetadata['title'] = titleInput.value;
            }

            // Handle other fields
            fields.forEach(field => {
                if (field.id === 'title') return;

                if (field.is_multilang) {
                    // Collect all inputs for this field
                    const inputs = form.querySelectorAll(`[name^="${field.id}["]`);
                    const langData = [];
                    
                    inputs.forEach(input => {
                        const clangId = parseInt(input.dataset.clangId);
                        const val = input.value;
                        if (val && !isNaN(clangId)) {
                            langData.push({
                                clang_id: clangId,
                                value: val
                            });
                        }
                    });
                    
                    // Save as JSON string
                    newMetadata[field.id] = JSON.stringify(langData);
                } else {
                    // Normal field
                    const input = form.querySelector(`[name="${field.id}"]`);
                    if (input) {
                        newMetadata[field.id] = input.value;
                    }
                }
            });
            
            const success = await this.saveMetadata(filename, newMetadata);
            if (success) {
                close();
            } else {
                alert('Fehler beim Speichern der Metadaten');
            }
        });
        
        // Close on backdrop click
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) close();
        });
    }
}

// Auto-Initialization
document.addEventListener('DOMContentLoaded', () => {
    const customWidgets = document.querySelectorAll('.uppy-upload-widget:not([data-uppy-initialized])');
    if (customWidgets.length > 0) {
        customWidgets.forEach(inputElement => {
            new UppyCustomWidget(inputElement);
        });
    }
});

