import Uppy from '@uppy/core';
import Dashboard from '@uppy/dashboard';
import Webcam from '@uppy/webcam';
import XHRUpload from '@uppy/xhr-upload';
import ImageEditor from '@uppy/image-editor';
import German from '@uppy/locales/lib/de_DE';

export class UppyCustomWidget {
    constructor(inputElement, options = {}) {
        this.input = inputElement;
        this.options = options;
        this.container = null;
        this.listContainer = null;
        this.uppy = null;
        
        this.init();
    }

    init() {
        if (this.input.dataset.uppyInitialized) return;
        this.input.dataset.uppyInitialized = 'true';
        this.input.style.display = 'none';

        this.renderUI();
        this.initUppy();
        this.renderList();
        
        // Listen for external changes to the input
        this.input.addEventListener('change', () => this.renderList());
    }

    getConfig() {
        const globalConfig = window.rex?.uppy_config || {};
        
        return {
            apiToken: this.input.dataset.apiToken || '',
            maxFiles: parseInt(this.input.dataset.maxFiles) || parseInt(globalConfig.max_files) || 10,
            maxFileSize: (parseInt(this.input.dataset.maxFilesize) || parseInt(globalConfig.max_file_size) || 200) * 1024 * 1024,
            allowedTypes: this.input.dataset.allowedTypes 
                ? this.input.dataset.allowedTypes.split(',').map(t => t.trim()) 
                : (globalConfig.allowed_types ? globalConfig.allowed_types.split(',').map(t => t.trim()) : []),
            categoryId: parseInt(this.input.dataset.categoryId) || 0,
            locale: this.input.dataset.locale || 'de-DE',
            ...this.options
        };
    }

    getIcon(name) {
        const icons = {
            add: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>',
            file: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>',
            up: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg>',
            down: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>',
            remove: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>',
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
        
        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'uppy-btn uppy-btn-primary';
        addBtn.innerHTML = this.getIcon('add') + ' Dateien hinzufügen';
        addBtn.addEventListener('click', () => {
            if (this.uppy) {
                this.uppy.getPlugin('Dashboard').openModal();
            }
        });
        this.container.appendChild(addBtn);
        
        this.input.parentNode.insertBefore(this.container, this.input.nextSibling);
    }

    renderList() {
        this.listContainer.innerHTML = '';
        const files = this.getFiles();
        
        if (files.length === 0) {
            const emptyState = document.createElement('li');
            emptyState.className = 'uppy-empty-state';
            emptyState.textContent = 'Keine Dateien ausgewählt';
            this.listContainer.appendChild(emptyState);
            return;
        }
        
        files.forEach((filename, index) => {
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
                    <button type="button" class="uppy-btn" data-action="edit" title="Metadaten bearbeiten">${this.getIcon('edit')}</button>
                    ${index > 0 ? `<button type="button" class="uppy-btn" data-action="up" title="Nach oben">${this.getIcon('up')}</button>` : ''}
                    ${index < files.length - 1 ? `<button type="button" class="uppy-btn" data-action="down" title="Nach unten">${this.getIcon('down')}</button>` : ''}
                    <button type="button" class="uppy-btn uppy-btn-danger" data-action="remove" title="Löschen">${this.getIcon('remove')}</button>
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
    }

    getFiles() {
        return this.input.value ? this.input.value.split(',').map(f => f.trim()).filter(f => f) : [];
    }

    setFiles(files) {
        this.input.value = files.join(',');
        this.input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    addFile(filename) {
        const files = this.getFiles();
        if (!files.includes(filename)) {
            files.push(filename);
            this.setFiles(files);
        }
    }

    removeFile(index) {
        const files = this.getFiles();
        files.splice(index, 1);
        this.setFiles(files);
    }

    moveFile(index, direction) {
        const files = this.getFiles();
        const newIndex = index + direction;
        if (newIndex >= 0 && newIndex < files.length) {
            [files[index], files[newIndex]] = [files[newIndex], files[index]];
            this.setFiles(files);
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

        // XHR Upload
        const tokenParam = config.apiToken ? encodeURIComponent(config.apiToken) : '';
        this.uppy.use(XHRUpload, {
            endpoint: window.location.origin + '/redaxo/index.php?rex-api-call=uppy_uploader&func=upload&api_token=' + tokenParam + '&category_id=' + config.categoryId,
            formData: true,
            fieldName: 'file',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            getResponseData: (responseText, response) => {
                let rawText = responseText;
                if (typeof responseText === 'object' && responseText !== null) {
                     if (typeof responseText.responseText === 'string') rawText = responseText.responseText;
                     else if (typeof responseText.response === 'string') rawText = responseText.response;
                     else return responseText;
                }
                try { return JSON.parse(rawText); } 
                catch (e) { return { success: false, message: 'Invalid response' }; }
            }
        });

        this.uppy.on('upload-success', (file, response) => {
            if (response.body && (response.body.success || response.body.status === 'ok')) {
                const responseData = response.body.data || response.body;
                this.addFile(responseData.filename);
                
                // Optional: Open metadata modal after upload
                // this.openMetadataModal(responseData.filename);
            }
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
            const group = document.createElement('div');
            group.className = 'uppy-widget-form-group';
            
            const value = metadata[field.name] || '';
            const label = `<label class="uppy-widget-label">${field.label || field.name}</label>`;
            
            let inputHtml = '';
            
            if (field.type === 'select') {
                const options = (field.options || []).map(opt => 
                    `<option value="${opt.value}" ${opt.value == value ? 'selected' : ''}>${opt.label}</option>`
                ).join('');
                inputHtml = `<select name="${field.name}" class="uppy-widget-select">${options}</select>`;
            } else if (field.type === 'textarea') {
                inputHtml = `<textarea name="${field.name}" class="uppy-widget-textarea" rows="3">${value}</textarea>`;
            } else {
                inputHtml = `<input type="text" name="${field.name}" class="uppy-widget-input" value="${value}">`;
            }
            
            group.innerHTML = label + inputHtml;
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
            const formData = new FormData(form);
            const newMetadata = {};
            for (const [key, value] of formData.entries()) {
                newMetadata[key] = value;
            }
            
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
