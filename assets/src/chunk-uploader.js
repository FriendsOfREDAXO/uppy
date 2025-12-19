/**
 * Custom Chunk Uploader für große Dateien
 * Orientiert an filepond's chunk-upload Implementierung
 */
export class ChunkUploader {
    constructor(uppy, opts) {
        this.uppy = uppy;
        this.id = 'ChunkUploader'; // ID für Uppy
        this.type = 'uploader';    // Typ für Uppy
        this.opts = Object.assign({
            endpoint: '',
            chunkSize: 5 * 1024 * 1024, // 5MB default
            categoryId: 0,
            apiToken: ''
        }, opts);
        
        this.uploaders = new Map();
    }

    install() {
        this.uppy.addUploader(this.uploadFile.bind(this));
    }

    uninstall() {
        // Cleanup
    }

    async uploadFile(fileIDs) {
        const promises = fileIDs.map(id => this.uploadSingleFile(id));
        return Promise.all(promises);
    }

    async uploadSingleFile(fileID) {
        const file = this.uppy.getFile(fileID);
        const chunkSize = this.opts.chunkSize;
        const totalSize = file.data.size;
        const totalChunks = Math.ceil(totalSize / chunkSize);
        
        console.log(`ChunkUpload Start: ${file.name}, Size: ${totalSize}, Chunks: ${totalChunks}`);
        
        try {
            // Prepare: Get fileId from server
            const fileId = await this.prepareUpload(file);
            console.log(`Prepare erfolgreich, fileId: ${fileId}`);
            
            this.uppy.emit('upload-progress', this.uppy.getFile(fileID), {
                uploader: this,
                bytesUploaded: 0,
                bytesTotal: totalSize
            });

            // Upload chunks sequentially
            for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                const start = chunkIndex * chunkSize;
                const end = Math.min(start + chunkSize, totalSize);
                const chunk = file.data.slice(start, end);
                
                console.log(`Uploading chunk ${chunkIndex + 1}/${totalChunks}`);
                await this.uploadChunk(fileId, chunk, chunkIndex, totalChunks, file);
                
                // Progress update (wird auch im uploadChunk gemacht, aber hier zur Sicherheit nochmal für 100% des Chunks)
                this.uppy.emit('upload-progress', this.uppy.getFile(fileID), {
                    uploader: this,
                    bytesUploaded: end,
                    bytesTotal: totalSize
                });
            }

            console.log('Alle Chunks hochgeladen, starte Finalize...');
            // Finalize: Merge chunks and add to mediapool
            const result = await this.finalizeUpload(fileId, file, totalChunks);
            
            console.log('Finalize erfolgreich:', result);
            
            try {
                this.uppy.emit('upload-success', this.uppy.getFile(fileID), result);
            } catch (emitError) {
                console.error('Fehler beim Emitten von upload-success:', emitError);
            }
            
            return result;
        } catch (error) {
            console.error('ChunkUpload Fehler:', error);
            this.uppy.emit('upload-error', this.uppy.getFile(fileID), error);
            throw error;
        }
    }

    async prepareUpload(file) {
        const fileId = 'uppy_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        const formData = new FormData();
        formData.append('fileId', fileId);
        formData.append('fileName', file.name);
        formData.append('fieldName', 'file');
        
        // Endpoint URL enthält bereits Signatur-Parameter (siehe Constructor)
        const url = `${this.opts.endpoint}&func=prepare&api_token=${this.opts.apiToken}`;
        
        const response = await fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            const text = await response.text();
            console.error('Prepare failed:', text);
            throw new Error('Prepare failed: ' + response.status);
        }
        
        const data = await response.json();
        return data.fileId || fileId;
    }

    async uploadChunk(fileId, chunk, chunkIndex, totalChunks, file) {
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('file', chunk, file.name);
            formData.append('fileId', fileId);
            formData.append('chunkIndex', chunkIndex);
            formData.append('totalChunks', totalChunks);
            formData.append('fieldName', 'file');
            
            // Endpoint URL enthält bereits Signatur-Parameter
            const url = `${this.opts.endpoint}&func=chunk&category_id=${this.opts.categoryId}&api_token=${this.opts.apiToken}`;
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.withCredentials = true;
            
            // Upload Progress für diesen Chunk
            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    const chunkSize = this.opts.chunkSize;
                    const start = chunkIndex * chunkSize;
                    const totalUploaded = start + e.loaded;
                    
                    // Progress an Uppy melden
                    // Wichtig: Immer das aktuellste File-Objekt holen
                    const currentFile = this.uppy.getFile(file.id);
                    if (currentFile) {
                        this.uppy.emit('upload-progress', currentFile, {
                            uploader: this,
                            bytesUploaded: totalUploaded,
                            bytesTotal: file.data.size
                        });
                    }
                }
            };
            
            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        resolve(response);
                    } catch (err) {
                        console.error(`Chunk ${chunkIndex} JSON parse error:`, err);
                        reject(new Error(`Chunk ${chunkIndex} invalid response`));
                    }
                } else {
                    console.error(`Chunk ${chunkIndex} upload failed:`, xhr.statusText);
                    reject(new Error(`Chunk ${chunkIndex} upload failed: ` + xhr.status));
                }
            };
            
            xhr.onerror = () => reject(new Error(`Chunk ${chunkIndex} network error`));
            
            xhr.send(formData);
        });
    }

    async finalizeUpload(fileId, file, totalChunks) {
        const formData = new FormData();
        formData.append('fileId', fileId);
        formData.append('fileName', file.name);
        formData.append('totalChunks', totalChunks);
        formData.append('fieldName', 'file');
        
        // Metadaten mitsenden
        if (file.meta) {
            formData.append('metadata', JSON.stringify(file.meta));
        }
        
        // Endpoint URL enthält bereits Signatur-Parameter
        const url = `${this.opts.endpoint}&func=finalize&category_id=${this.opts.categoryId}&api_token=${this.opts.apiToken}`;
        
        const response = await fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            const text = await response.text();
            console.error('Finalize failed:', text);
            throw new Error('Finalize failed: ' + response.status);
        }
        
        const data = await response.json();
        
        // Uppy erwartet ein Response-Objekt mit status und body
        const result = {
            status: response.status,
            body: {
                success: data.success || true,
                data: {
                    filename: data.data?.filename || data.filename,
                    title: data.data?.title || ''
                }
            },
            uploadURL: ''
        };
        
        return result;
    }
}
