// Gallery renderer module for displaying media items
import { API } from './api.js';

export class GalleryRenderer {
    constructor() {
        this.api = new API();
        this.gallery = document.getElementById('gallery');
        this.onItemClick = null;
        this.onItemRightClick = null;
        
        this.init();
    }

    init() {
        // Add initial gallery classes for styling
        this.gallery.classList.add('gallery-grid');
    }

    /**
     * Render gallery items (folders and media)
     * @param {Array} items - Array of media items and folders
     */
    render(items) {
        if (!items || items.length === 0) {
            this.renderEmpty();
            return;
        }

        // Clear existing content
        this.gallery.innerHTML = '';

        // Add gallery grid class
        this.gallery.className = 'gallery-container gallery-grid';

        // Render each item
        items.forEach((item, index) => {
            const element = this.createItemElement(item, index);
            this.gallery.appendChild(element);
        });
    }

    /**
     * Render empty directory state
     */
    renderEmpty() {
        this.gallery.innerHTML = `
            <div class="empty-directory">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M10,4H4C2.89,4 2,4.89 2,6V18A2,2 0 0,0 4,20H20A2,2 0 0,0 22,18V8C22,6.89 21.1,6 20,6H12L10,4Z" />
                </svg>
                <h3>Empty Directory</h3>
                <p>This directory contains no photos or videos.</p>
            </div>
        `;
    }

    /**
     * Create HTML element for a gallery item
     * @param {Object} item - Media item or folder
     * @param {number} index - Item index
     * @returns {HTMLElement} Gallery item element
     */
    createItemElement(item, index) {
        const element = document.createElement('div');
        element.className = 'thumb';
        element.dataset.index = index;
        element.dataset.type = item.type;
        element.dataset.path = item.relativePath;

        if (item.type === 'folder') {
            element.innerHTML = this.createFolderHTML(item);
            element.classList.add('folder-thumb');
        } else {
            element.innerHTML = this.createMediaHTML(item);
            element.classList.add(`${item.type}-thumb`);
        }

        // Add event listeners
        element.addEventListener('click', (e) => {
            e.preventDefault();
            if (this.onItemClick) {
                this.onItemClick(item, index);
            }
        });

        element.addEventListener('contextmenu', (e) => {
            if (this.onItemRightClick) {
                this.onItemRightClick(item, e);
            }
        });

        return element;
    }

    /**
     * Create HTML for folder item
     * @param {Object} folder - Folder item
     * @returns {string} Folder HTML
     */
    createFolderHTML(folder) {
        const tagsHTML = folder.tags && folder.tags.length > 0 
            ? `<div class="tagcloud">
                ${folder.tags.map(tag => `<div class="thumbtag">${this.escapeHtml(tag)}</div>`).join('')}
               </div>`
            : '';

        return `
            ${tagsHTML}
            <div class="thumbimg">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M10,4H4C2.89,4 2,4.89 2,6V18A2,2 0 0,0 4,20H20A2,2 0 0,0 22,18V8C22,6.89 21.1,6 20,6H12L10,4Z" />
                </svg>
            </div>
            <div class="thumblabel">
                ${this.escapeHtml(folder.name)}
            </div>
        `;
    }

    /**
     * Create HTML for media item (image/video)
     * @param {Object} media - Media item
     * @returns {string} Media HTML
     */
    createMediaHTML(media) {
        const mediaURL = this.api.getMediaURL(media.relativePath, '300');
        const isVideo = media.type === 'video';
        
        const videoOverlay = isVideo ? `
            <div class="video-overlay">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M8,5.14V19.14L19,12.14L8,5.14Z" />
                </svg>
            </div>
        ` : '';

        const mediaElement = isVideo 
            ? `<video preload="metadata" muted>
                 <source src="${mediaURL}" type="video/mp4" />
               </video>`
            : `<img src="${mediaURL}" alt="${this.escapeHtml(media.name)}" loading="lazy" />`;

        return `
            <div class="media-container">
                ${mediaElement}
                ${videoOverlay}
            </div>
            <div class="thumblabel">
                ${this.escapeHtml(media.name)}
            </div>
        `;
    }

    /**
     * Calculate size class for mosaic layout based on item properties
     * @param {Object} item - Media item
     * @returns {string} Size class
     */
    calculateSizeClass(item) {
        // Simple size calculation based on file size and creation time
        // This creates a varied mosaic layout
        const hash = this.simpleHash(item.name + item.relativePath);
        const size = hash % 100;

        if (size < 25) return 'size-small';
        if (size < 60) return 'size-medium';
        if (size < 85) return 'size-large';
        return 'size-xlarge';
    }

    /**
     * Simple hash function for consistent sizing
     * @param {string} str - String to hash
     * @returns {number} Hash value
     */
    simpleHash(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convert to 32-bit integer
        }
        return Math.abs(hash);
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
     * Update gallery layout class
     * @param {string} layoutType - Layout type (grid, masonry, flex)
     */
    setLayout(layoutType = 'grid') {
        this.gallery.className = 'gallery-container';
        
        switch (layoutType) {
            case 'masonry':
                this.gallery.classList.add('gallery-masonry');
                break;
            case 'flex':
                this.gallery.classList.add('gallery-flex');
                break;
            default:
                this.gallery.classList.add('gallery-grid');
        }
    }

    /**
     * Get gallery item element by index
     * @param {number} index - Item index
     * @returns {HTMLElement|null} Gallery item element
     */
    getItemElement(index) {
        return this.gallery.querySelector(`[data-index="${index}"]`);
    }

    /**
     * Highlight gallery item
     * @param {number} index - Item index to highlight
     */
    highlightItem(index) {
        // Remove existing highlights
        this.gallery.querySelectorAll('.thumb.highlighted').forEach(item => {
            item.classList.remove('highlighted');
        });

        // Add highlight to current item
        const item = this.getItemElement(index);
        if (item) {
            item.classList.add('highlighted');
        }
    }

    /**
     * Remove all highlights
     */
    clearHighlights() {
        this.gallery.querySelectorAll('.thumb.highlighted').forEach(item => {
            item.classList.remove('highlighted');
        });
    }

    /**
     * Filter items by search query
     * @param {string} query - Search query
     */
    filter(query) {
        const items = this.gallery.querySelectorAll('.thumb');
        const lowerQuery = query.toLowerCase();

        items.forEach(item => {
            const name = item.querySelector('.thumblabel').textContent.toLowerCase();
            const tags = Array.from(item.querySelectorAll('.thumbtag')).map(tag => tag.textContent.toLowerCase());
            
            const matches = name.includes(lowerQuery) || tags.some(tag => tag.includes(lowerQuery));
            
            if (matches) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    }

    /**
     * Clear search filter
     */
    clearFilter() {
        const items = this.gallery.querySelectorAll('.thumb');
        items.forEach(item => {
            item.style.display = '';
        });
    }
}
