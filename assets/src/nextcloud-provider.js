/**
 * Nextcloud Provider für Uppy
 * Ermöglicht Import von Dateien aus Nextcloud
 */

export async function initNextcloudProvider(uppy, config) {
    console.log('[Nextcloud] Init started');
    
    // Prüfe ob Nextcloud verfügbar ist
    const available = await checkNextcloudAvailable();
    
    console.log('[Nextcloud] Check result:', available);
    
    if (!available.success || !available.data.available) {
        console.log('[Nextcloud] Not available, skipping');
        return false;
    }
    
    console.log('[Nextcloud] Available, adding button');
    
    // Füge Button zum Dashboard hinzu
    addNextcloudButton(uppy, config);
    
    return true;
}

async function checkNextcloudAvailable() {
    try {
        const response = await fetch(
            window.location.origin + '/redaxo/index.php?rex-api-call=uppy_nextcloud&func=check_available',
            { credentials: 'same-origin' }
        );
        return await response.json();
    } catch (error) {
        console.error('Nextcloud check failed:', error);
        return { success: false };
    }
}

function addNextcloudButton(uppy, config) {
    console.log('[Nextcloud] addNextcloudButton called');
    
    // Warte bis Dashboard geladen ist
    const dashboard = uppy.getPlugin('Dashboard');
    if (!dashboard) {
        console.log('[Nextcloud] Dashboard not found, retrying in 100ms');
        setTimeout(() => addNextcloudButton(uppy, config), 100);
        return;
    }
    
    console.log('[Nextcloud] Dashboard found, setting up observer');
    
    // Observer für Dashboard-UI
    const observer = new MutationObserver(() => {
        const addMorePanel = document.querySelector('.uppy-Dashboard-AddFiles');
        console.log('[Nextcloud] Mutation observed, panel found:', !!addMorePanel);
        if (addMorePanel && !document.querySelector('.uppy-nextcloud-button')) {
            console.log('[Nextcloud] AddFiles found, inserting button');
            insertNextcloudButton(addMorePanel, uppy, config);
            observer.disconnect();
        }
    });
    
    observer.observe(document.body, { childList: true, subtree: true });
    
    // Prüfe auch sofort ob Panel schon da ist
    const existingPanel = document.querySelector('.uppy-Dashboard-AddFiles');
    console.log('[Nextcloud] Immediate check - AddFiles found:', !!existingPanel);
    
    if (existingPanel && !document.querySelector('.uppy-nextcloud-button')) {
        console.log('[Nextcloud] AddFiles already exists, inserting button immediately');
        insertNextcloudButton(existingPanel, uppy, config);
    }
}

function insertNextcloudButton(panel, uppy, config) {
    console.log('[Nextcloud] insertNextcloudButton called');
    console.log('[Nextcloud] Panel structure:', panel.outerHTML);
    console.log('[Nextcloud] Panel children:', Array.from(panel.children).map(c => c.className));
    
    const button = document.createElement('button');
    button.className = 'uppy-DashboardTab uppy-nextcloud-button';
    button.type = 'button';
    button.setAttribute('data-uppy-acquirer', 'nextcloud');
    
    console.log('[Nextcloud] Button created');
    
    button.innerHTML = `
        <svg aria-hidden="true" focusable="false" width="32" height="32" viewBox="0 0 32 32">
            <path fill="currentColor" d="M16 3C8.82 3 3 8.82 3 16s5.82 13 13 13 13-5.82 13-13S23.18 3 16 3zm0 2c6.065 0 11 4.935 11 11s-4.935 11-11 11S5 22.065 5 16 9.935 5 16 5zm-1 3v2h2V8h-2zm0 3v11h2V11h-2z"/>
        </svg>
        <div class="uppy-DashboardTab-name">Nextcloud</div>
    `;
    
    button.addEventListener('click', () => showNextcloudBrowser(uppy, config));
    
    // Füge Button zum Panel hinzu
    const browseFoldersBtn = panel.querySelector('[data-uppy-acquirer="MyDevice"]');
    console.log('[Nextcloud] MyDevice button found:', !!browseFoldersBtn);
    
    if (browseFoldersBtn) {
        console.log('[Nextcloud] Inserting after MyDevice button');
        browseFoldersBtn.parentNode.insertBefore(button, browseFoldersBtn.nextSibling);
    } else {
        console.log('[Nextcloud] Appending to panel directly');
        panel.appendChild(button);
    }
    
    console.log('[Nextcloud] Button inserted, now in DOM:', document.contains(button));
    console.log('[Nextcloud] Button visible:', button.offsetParent !== null);
}

async function showNextcloudBrowser(uppy, config) {
    const modal = createBrowserModal();
    document.body.appendChild(modal);
    
    // Lade Root-Dateien
    await loadNextcloudFiles(modal, '/', uppy, config);
    
    // Close Button
    modal.querySelector('.uppy-nextcloud-close').addEventListener('click', () => {
        modal.remove();
    });
}

function createBrowserModal() {
    const modal = document.createElement('div');
    modal.className = 'uppy-nextcloud-modal';
    modal.innerHTML = `
        <div class="uppy-nextcloud-overlay"></div>
        <div class="uppy-nextcloud-content">
            <div class="uppy-nextcloud-header">
                <h3>Nextcloud Dateien</h3>
                <button type="button" class="uppy-nextcloud-close">&times;</button>
            </div>
            <div class="uppy-nextcloud-breadcrumb"></div>
            <div class="uppy-nextcloud-body">
                <div class="uppy-nextcloud-loading">Lade...</div>
            </div>
        </div>
    `;
    
    return modal;
}

async function loadNextcloudFiles(modal, path, uppy, config) {
    const body = modal.querySelector('.uppy-nextcloud-body');
    body.innerHTML = '<div class="uppy-nextcloud-loading">Lade...</div>';
    
    try {
        const response = await fetch(
            window.location.origin + '/redaxo/index.php?rex-api-call=uppy_nextcloud&func=list_files&path=' + encodeURIComponent(path),
            { credentials: 'same-origin' }
        );
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Fehler beim Laden');
        }
        
        renderFileList(modal, result.data.files, result.data.path, uppy, config);
        updateBreadcrumb(modal, result.data.path, uppy, config);
        
    } catch (error) {
        body.innerHTML = `<div class="uppy-nextcloud-error">Fehler: ${error.message}</div>`;
    }
}

function renderFileList(modal, files, currentPath, uppy, config) {
    const body = modal.querySelector('.uppy-nextcloud-body');
    
    if (!files || files.length === 0) {
        body.innerHTML = '<div class="uppy-nextcloud-empty">Keine Dateien gefunden</div>';
        return;
    }
    
    const list = document.createElement('ul');
    list.className = 'uppy-nextcloud-filelist';
    
    files.forEach(file => {
        const li = document.createElement('li');
        li.className = 'uppy-nextcloud-file';
        
        const icon = file.type === 'folder' ? '📁' : '📄';
        
        li.innerHTML = `
            <span class="uppy-nextcloud-icon">${icon}</span>
            <span class="uppy-nextcloud-name">${file.name}</span>
            <span class="uppy-nextcloud-size">${file.size || ''}</span>
        `;
        
        if (file.type === 'folder') {
            li.style.cursor = 'pointer';
            li.addEventListener('click', () => {
                loadNextcloudFiles(modal, file.path, uppy, config);
            });
        } else {
            const importBtn = document.createElement('button');
            importBtn.type = 'button';
            importBtn.className = 'uppy-nextcloud-import-btn';
            importBtn.textContent = 'Importieren';
            importBtn.addEventListener('click', (e) => {
                importFileFromNextcloud(file.path, config, modal, uppy, e.target);
            });
            li.appendChild(importBtn);
        }
        
        list.appendChild(li);
    });
    
    body.innerHTML = '';
    body.appendChild(list);
}

function updateBreadcrumb(modal, path, uppy, config) {
    const breadcrumb = modal.querySelector('.uppy-nextcloud-breadcrumb');
    const parts = path.split('/').filter(p => p);
    
    let html = '<a href="#" data-path="/">Home</a>';
    let currentPath = '';
    
    parts.forEach(part => {
        currentPath += '/' + part;
        html += ` / <a href="#" data-path="${currentPath}">${part}</a>`;
    });
    
    breadcrumb.innerHTML = html;
    
    // Event Listener für Breadcrumb-Links
    breadcrumb.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const targetPath = link.getAttribute('data-path');
            loadNextcloudFiles(modal, targetPath, uppy, config);
        });
    });
}

async function importFileFromNextcloud(path, config, modal, uppy, buttonElement) {
    console.log('[Nextcloud] Import file:', path);
    
    const formData = new FormData();
    formData.append('path', path);
    formData.append('category_id', config.categoryId || 0);
    
    // Zeige Loading-State im Button
    const originalText = buttonElement.textContent;
    buttonElement.disabled = true;
    buttonElement.textContent = 'Importiere...';
    
    try {
        const response = await fetch(
            window.location.origin + '/redaxo/index.php?rex-api-call=uppy_nextcloud&func=import_file',
            {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }
        );
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Import fehlgeschlagen');
        }
        
        // Zeige Erfolg-Nachricht
        const filename = result.data.filename || path.split('/').pop();
        uppy.log(`[Nextcloud] Datei "${filename}" erfolgreich importiert`, 'info');
        
        // Triggere complete event für Mediapool-Refresh
        if (typeof window.rexMediapoolRefresh === 'function') {
            window.rexMediapoolRefresh();
        }
        
        // Schließe Modal
        modal.remove();
        
        // Zeige Erfolgs-Banner (REDAXO-Style)
        showSuccessMessage(`Datei "${filename}" wurde erfolgreich importiert`);
        
        // Reload page um neue Datei anzuzeigen
        if (config.refreshOnSuccess) {
            window.location.reload();
        }
        
    } catch (error) {
        console.error('[Nextcloud] Import error:', error);
        buttonElement.disabled = false;
        buttonElement.textContent = originalText;
        showErrorMessage('Import fehlgeschlagen: ' + error.message);
    }
}

function showSuccessMessage(message) {
    const alert = document.createElement('div');
    alert.className = 'alert alert-success alert-dismissible fade in';
    alert.innerHTML = `
        <button type="button" class="close" data-dismiss="alert">×</button>
        <strong>Erfolg!</strong> ${message}
    `;
    
    const container = document.querySelector('.rex-page-main') || document.body;
    container.insertBefore(alert, container.firstChild);
    
    setTimeout(() => {
        alert.style.transition = 'opacity 0.3s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 300);
    }, 5000);
}

function showErrorMessage(message) {
    const alert = document.createElement('div');
    alert.className = 'alert alert-danger alert-dismissible fade in';
    alert.innerHTML = `
        <button type="button" class="close" data-dismiss="alert">×</button>
        <strong>Fehler!</strong> ${message}
    `;
    
    const container = document.querySelector('.rex-page-main') || document.body;
    container.insertBefore(alert, container.firstChild);
    
    setTimeout(() => {
        alert.style.transition = 'opacity 0.3s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 300);
    }, 8000);
}
