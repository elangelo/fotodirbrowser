// Side panel manager for file actions and information
import { API } from './api.js';

export class SidePanelManager {
    constructor() {
        this.sidePanel = document.getElementById('side-panel');
        this.sidePanelClose = document.getElementById('side-panel-close');
        this.sidePanelTitle = document.getElementById('side-panel-title');
        this.actionDownload = document.getElementById('action-download');
        this.actionDelete = document.getElementById('action-delete');
        this.actionInfo = document.getElementById('action-info');
        this.fileInfo = document.getElementById('file-info');
        
        this.api = new API();
        this.currentItem = null;
        this.isOpen = false;
        
        this.onDownload = null;
        this.onDelete = null;
        this.onInfo = null;
        
        this.init();
    }

    init() {
        // Close button
        this.sidePanelClose.addEventListener('click', () => this.close());

        // Action buttons
        this.actionDownload.addEventListener('click', () => {
            if (this.currentItem && this.onDownload) {
                this.onDownload(this.currentItem);
            }
        });

        this.actionDelete.addEventListener('click', () => {
            if (this.currentItem && this.onDelete) {
                this.onDelete(this.currentItem);
            }
        });

        this.actionInfo.addEventListener('click', () => {
            if (this.currentItem) {
                this.showFileInfo(this.currentItem);
            }
        });

        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.close();
            }
        });

        // Close on outside click (mobile)
        this.addBackdropSupport();
    }

    /**
     * Open side panel with file information
     * @param {Object} item - Media item
     */
    open(item) {
        this.currentItem = item;
        this.isOpen = true;
        
        this.sidePanelTitle.textContent = item.name;
        this.sidePanel.classList.add('open');
        
        // Show file preview and basic info
        this.renderFilePreview(item);
        this.renderBasicInfo(item);
        
        // Show backdrop on mobile
        this.showBackdrop();
    }

    /**
     * Close side panel
     */
    close() {
        this.isOpen = false;
        this.sidePanel.classList.remove('open');
        this.currentItem = null;
        this.clearMessages();
        this.hideBackdrop();
    }

    /**
     * Toggle side panel
     */
    toggle() {
        if (this.isOpen) {
            this.close();
        } else if (this.currentItem) {
            this.open(this.currentItem);
        }
    }

    /**
     * Render file preview thumbnail
     * @param {Object} item - Media item
     */
    renderFilePreview(item) {
        const existing = this.fileInfo.querySelector('.file-preview');
        if (existing) existing.remove();

        const preview = document.createElement('div');
        preview.className = 'file-preview-container';
        
        const thumbnailURL = this.api.getMediaURL(item.relativePath, '200');
        
        if (item.type === 'video') {
            preview.innerHTML = `
                <video class="file-preview" muted preload="none">
                    <source src="${thumbnailURL}" type="video/mp4" />
                </video>
            `;
        } else {
            preview.innerHTML = `
                <img class="file-preview" src="${thumbnailURL}" alt="${item.name}" loading="lazy" />
            `;
        }
        
        this.fileInfo.insertBefore(preview, this.fileInfo.firstChild);
    }

    /**
     * Render basic file information
     * @param {Object} item - Media item
     */
    renderBasicInfo(item) {
        const infoContainer = document.createElement('div');
        infoContainer.innerHTML = `
            <h4>File Information</h4>
            <div class="info-group">
                <div class="info-item">
                    <span class="info-label">Name:</span>
                    <span class="info-value">${this.escapeHtml(item.name)}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Type:</span>
                    <span class="info-value">${item.type.toUpperCase()}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Extension:</span>
                    <span class="info-value">${item.extension}</span>
                </div>
                ${this.renderFileSize(item)}
                ${this.renderCreationDate(item)}
                ${this.renderFilePath(item)}
            </div>
        `;

        // Clear existing info (except preview)
        const preview = this.fileInfo.querySelector('.file-preview-container');
        this.fileInfo.innerHTML = '';
        if (preview) {
            this.fileInfo.appendChild(preview);
        }
        this.fileInfo.appendChild(infoContainer);
    }

    /**
     * Render detailed file information
     * @param {Object} item - Media item
     */
    async showFileInfo(item) {
        try {
            this.showMessage('Loading detailed information...', 'info');
            
            // For now, show basic info (extended info would come from API)
            const detailedInfo = document.createElement('div');
            detailedInfo.innerHTML = `
                <h4>Detailed Information</h4>
                <div class="info-group">
                    ${this.renderTechnicalInfo(item)}
                    ${this.renderLocationInfo(item)}
                    ${this.renderAccessInfo(item)}
                </div>
            `;

            this.fileInfo.appendChild(detailedInfo);
            this.clearMessages();
            
        } catch (error) {
            console.error('Failed to load detailed info:', error);
            this.showError('Failed to load detailed information');
        }
    }

    /**
     * Render file size information
     * @param {Object} item - Media item
     * @returns {string} File size HTML
     */
    renderFileSize(item) {
        if (!item.stats || !item.stats.size) return '';
        
        const size = this.formatFileSize(item.stats.size);
        return `
            <div class="info-item">
                <span class="info-label">Size:</span>
                <span class="info-value">${size}</span>
            </div>
        `;
    }

    /**
     * Render creation date information
     * @param {Object} item - Media item
     * @returns {string} Creation date HTML
     */
    renderCreationDate(item) {
        if (!item.stats || !item.stats.creationTime) return '';
        
        const date = new Date(item.stats.creationTime);
        const formatted = date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        
        return `
            <div class="info-item">
                <span class="info-label">Created:</span>
                <span class="info-value">${formatted}</span>
            </div>
        `;
    }

    /**
     * Render file path information
     * @param {Object} item - Media item
     * @returns {string} File path HTML
     */
    renderFilePath(item) {
        return `
            <div class="info-item">
                <span class="info-label">Path:</span>
                <span class="info-value">${this.escapeHtml(item.relativePath)}</span>
            </div>
        `;
    }

    /**
     * Render technical information (dimensions, codec, etc.)
     * @param {Object} item - Media item
     * @returns {string} Technical info HTML
     */
    renderTechnicalInfo(item) {
        // This would be populated from actual metadata
        const info = [];
        
        if (item.type === 'image') {
            info.push('<div class="info-item"><span class="info-label">Format:</span><span class="info-value">JPEG</span></div>');
            info.push('<div class="info-item"><span class="info-label">Color Space:</span><span class="info-value">sRGB</span></div>');
        } else if (item.type === 'video') {
            info.push('<div class="info-item"><span class="info-label">Codec:</span><span class="info-value">H.264</span></div>');
            info.push('<div class="info-item"><span class="info-label">Container:</span><span class="info-value">MP4</span></div>');
        }
        
        return info.join('');
    }

    /**
     * Render location information
     * @param {Object} item - Media item
     * @returns {string} Location info HTML
     */
    renderLocationInfo(item) {
        const directory = item.relativePath.substring(0, item.relativePath.lastIndexOf('/')) || '/';
        
        return `
            <div class="info-item">
                <span class="info-label">Directory:</span>
                <span class="info-value">${this.escapeHtml(directory)}</span>
            </div>
        `;
    }

    /**
     * Render access information
     * @param {Object} item - Media item
     * @returns {string} Access info HTML
     */
    renderAccessInfo(item) {
        const modifiedDate = item.stats?.modificationTime 
            ? new Date(item.stats.modificationTime).toLocaleDateString()
            : 'Unknown';
            
        return `
            <div class="info-item">
                <span class="info-label">Modified:</span>
                <span class="info-value">${modifiedDate}</span>
            </div>
        `;
    }

    /**
     * Show success message
     * @param {string} message - Success message
     */
    showSuccess(message) {
        this.showMessage(message, 'success');
    }

    /**
     * Show error message
     * @param {string} message - Error message
     */
    showError(message) {
        this.showMessage(message, 'error');
    }

    /**
     * Show message with type
     * @param {string} message - Message text
     * @param {string} type - Message type (success, error, info)
     */
    showMessage(message, type = 'info') {
        this.clearMessages();
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `action-progress ${type}`;
        messageDiv.innerHTML = `<p class="progress-text">${this.escapeHtml(message)}</p>`;
        
        this.fileInfo.appendChild(messageDiv);
        
        // Auto-hide success/info messages
        if (type !== 'error') {
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.remove();
                }
            }, 3000);
        }
    }

    /**
     * Clear all messages
     */
    clearMessages() {
        this.fileInfo.querySelectorAll('.action-progress').forEach(msg => msg.remove());
    }

    /**
     * Add backdrop support for mobile
     */
    addBackdropSupport() {
        this.backdrop = document.createElement('div');
        this.backdrop.className = 'side-panel-backdrop';
        document.body.appendChild(this.backdrop);
        
        this.backdrop.addEventListener('click', () => this.close());
    }

    /**
     * Show backdrop
     */
    showBackdrop() {
        if (window.innerWidth <= 768) {
            this.backdrop.classList.add('show');
        }
    }

    /**
     * Hide backdrop
     */
    hideBackdrop() {
        this.backdrop.classList.remove('show');
    }

    /**
     * Format file size for display
     * @param {number} bytes - File size in bytes
     * @returns {string} Formatted file size
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    /**
     * Escape HTML to prevent XSS
     * @param {string} text - Text to escape
     * @returns {string} Escaped text
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Get current item
     * @returns {Object|null} Current item
     */
    getCurrentItem() {
        return this.currentItem;
    }

    /**
     * Update item information (after modification)
     * @param {Object} updatedItem - Updated item data
     */
    updateItem(updatedItem) {
        if (this.currentItem && this.currentItem.relativePath === updatedItem.relativePath) {
            this.currentItem = updatedItem;
            this.renderBasicInfo(updatedItem);
        }
    }
}
