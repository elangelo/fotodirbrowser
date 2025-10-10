// Modal/Lightbox manager for media viewing
import { API } from './api.js';

export class ModalManager {
    constructor() {
        this.modal = document.getElementById('modal');
        this.modalContent = this.modal.querySelector('.modal-content');
        this.modalMedia = document.getElementById('modal-media');
        this.modalClose = document.getElementById('modal-close');
        this.modalPrev = document.getElementById('modal-prev');
        this.modalNext = document.getElementById('modal-next');
        this.modalThumbnails = document.getElementById('modal-thumbnails');
        
        this.api = new API();
        this.currentMedia = [];
        this.currentIndex = 0;
        this.isOpen = false;
        
        this.onNavigate = null;
        this.onClose = null;
        
        this.init();
    }

    init() {
        // Close modal events
        this.modalClose.addEventListener('click', () => this.close());
        
        // Click outside to close
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.close();
            }
        });

        // Navigation arrows
        this.modalPrev.addEventListener('click', () => this.navigate('prev'));
        this.modalNext.addEventListener('click', () => this.navigate('next'));

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (!this.isOpen) return;

            switch (e.key) {
                case 'Escape':
                    this.close();
                    break;
                case 'ArrowLeft':
                    e.preventDefault();
                    this.navigate('prev');
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    this.navigate('next');
                    break;
                case ' ':
                    e.preventDefault();
                    this.togglePlayback();
                    break;
            }
        });

        // Touch/swipe support for mobile
        this.addTouchSupport();
    }

    /**
     * Open modal with media array and starting index
     * @param {Array} mediaArray - Array of media items
     * @param {number} startIndex - Starting index
     */
    open(mediaArray, startIndex = 0) {
        this.currentMedia = mediaArray.filter(item => item.type !== 'folder');
        this.currentIndex = Math.max(0, Math.min(startIndex, this.currentMedia.length - 1));
        
        if (this.currentMedia.length === 0) return;

        this.isOpen = true;
        this.modal.classList.add('show');
        document.body.style.overflow = 'hidden';

        this.renderThumbnails();
        this.showMedia(this.currentIndex);
        this.updateNavigation();

        // Show keyboard hint
        this.showKeyboardHint();
    }

    /**
     * Close modal
     */
    close() {
        this.isOpen = false;
        this.modal.classList.remove('show');
        document.body.style.overflow = '';
        
        // Clean up media
        this.modalMedia.innerHTML = '';
        this.modalThumbnails.innerHTML = '';
        
        if (this.onClose) {
            this.onClose();
        }
    }

    /**
     * Navigate to next/previous media
     * @param {string} direction - 'next' or 'prev'
     */
    navigate(direction) {
        if (!this.isOpen || this.currentMedia.length === 0) return;

        const newIndex = direction === 'next' 
            ? (this.currentIndex + 1) % this.currentMedia.length
            : (this.currentIndex - 1 + this.currentMedia.length) % this.currentMedia.length;

        this.currentIndex = newIndex;
        this.showMedia(this.currentIndex);
        this.updateNavigation();
        this.updateThumbnailSelection();

        if (this.onNavigate) {
            this.onNavigate(direction);
        }
    }

    /**
     * Show media at specific index
     * @param {number} index - Media index
     */
    showMedia(index) {
        if (index < 0 || index >= this.currentMedia.length) return;

        const media = this.currentMedia[index];
        const mediaURL = this.api.getMediaURL(media.relativePath);

        // Show loading state
        this.modalMedia.innerHTML = `
            <div class="modal-loading">
                <div class="loading-spinner"></div>
            </div>
        `;

        // Create media element
        let mediaElement;
        if (media.type === 'video') {
            mediaElement = document.createElement('video');
            mediaElement.src = mediaURL;
            mediaElement.controls = true;
            mediaElement.autoplay = false;
            mediaElement.preload = 'metadata';
        } else {
            mediaElement = document.createElement('img');
            mediaElement.src = mediaURL;
            mediaElement.alt = media.name;
        }

        // Handle loading
        const onLoad = () => {
            this.modalMedia.innerHTML = '';
            this.modalMedia.appendChild(mediaElement);
            this.showMediaInfo(media);
        };

        const onError = () => {
            this.modalMedia.innerHTML = `
                <div class="error-message">
                    <h3>Failed to load media</h3>
                    <p>${media.name}</p>
                </div>
            `;
        };

        if (media.type === 'video') {
            mediaElement.addEventListener('loadedmetadata', onLoad);
            mediaElement.addEventListener('error', onError);
        } else {
            mediaElement.addEventListener('load', onLoad);
            mediaElement.addEventListener('error', onError);
        }
    }

    /**
     * Render thumbnail navigation strip
     */
    renderThumbnails() {
        this.modalThumbnails.innerHTML = '';

        this.currentMedia.forEach((media, index) => {
            const thumbnail = document.createElement('div');
            thumbnail.className = 'modal-thumbnail';
            thumbnail.dataset.index = index;

            const thumbnailURL = this.api.getMediaURL(media.relativePath, '100');
            
            if (media.type === 'video') {
                thumbnail.innerHTML = `
                    <video muted preload="none">
                        <source src="${thumbnailURL}" type="video/mp4" />
                    </video>
                    <div class="video-indicator">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M8,5.14V19.14L19,12.14L8,5.14Z" />
                        </svg>
                    </div>
                `;
            } else {
                thumbnail.innerHTML = `
                    <img src="${thumbnailURL}" alt="${media.name}" loading="lazy" />
                `;
            }

            thumbnail.addEventListener('click', () => {
                this.currentIndex = index;
                this.showMedia(index);
                this.updateNavigation();
                this.updateThumbnailSelection();
            });

            this.modalThumbnails.appendChild(thumbnail);
        });

        this.updateThumbnailSelection();
    }

    /**
     * Update thumbnail selection
     */
    updateThumbnailSelection() {
        this.modalThumbnails.querySelectorAll('.modal-thumbnail').forEach((thumb, index) => {
            thumb.classList.toggle('active', index === this.currentIndex);
        });

        // Scroll active thumbnail into view
        const activeThumbnail = this.modalThumbnails.querySelector('.modal-thumbnail.active');
        if (activeThumbnail) {
            activeThumbnail.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
    }

    /**
     * Update navigation arrows
     */
    updateNavigation() {
        const hasPrev = this.currentMedia.length > 1;
        const hasNext = this.currentMedia.length > 1;

        this.modalPrev.classList.toggle('disabled', !hasPrev);
        this.modalNext.classList.toggle('disabled', !hasNext);
    }

    /**
     * Show media information overlay
     * @param {Object} media - Media item
     */
    showMediaInfo(media) {
        const existing = this.modalMedia.querySelector('.modal-info');
        if (existing) existing.remove();

        const info = document.createElement('div');
        info.className = 'modal-info';
        
        const fileSize = this.formatFileSize(media.stats?.size || 0);
        const creationDate = media.stats?.creationTime 
            ? new Date(media.stats.creationTime).toLocaleDateString()
            : 'Unknown';

        info.innerHTML = `
            <h3>${this.escapeHtml(media.name)}</h3>
            <p><strong>Type:</strong> ${media.type.toUpperCase()}</p>
            <p><strong>Size:</strong> ${fileSize}</p>
            <p><strong>Created:</strong> ${creationDate}</p>
            <p><strong>Path:</strong> ${this.escapeHtml(media.relativePath)}</p>
        `;

        this.modalMedia.appendChild(info);

        // Auto-hide info after 3 seconds
        setTimeout(() => {
            if (info.parentNode) {
                info.classList.add('show');
                setTimeout(() => {
                    if (info.parentNode) {
                        info.remove();
                    }
                }, 3000);
            }
        }, 500);
    }

    /**
     * Toggle video playback
     */
    togglePlayback() {
        const video = this.modalMedia.querySelector('video');
        if (video) {
            if (video.paused) {
                video.play();
            } else {
                video.pause();
            }
        }
    }

    /**
     * Show keyboard hint
     */
    showKeyboardHint() {
        const existing = this.modal.querySelector('.keyboard-hint');
        if (existing) existing.remove();

        const hint = document.createElement('div');
        hint.className = 'keyboard-hint';
        hint.textContent = 'Use arrow keys to navigate • ESC to close • Space to play/pause';
        
        this.modal.appendChild(hint);
        
        setTimeout(() => {
            hint.classList.add('show');
        }, 1000);
    }

    /**
     * Add touch/swipe support for mobile
     */
    addTouchSupport() {
        let startX = 0;
        let startY = 0;
        let isDragging = false;

        this.modalMedia.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            isDragging = true;
        });

        this.modalMedia.addEventListener('touchmove', (e) => {
            if (!isDragging) return;
            e.preventDefault();
        });

        this.modalMedia.addEventListener('touchend', (e) => {
            if (!isDragging) return;
            isDragging = false;

            const endX = e.changedTouches[0].clientX;
            const endY = e.changedTouches[0].clientY;
            const diffX = startX - endX;
            const diffY = startY - endY;

            // Horizontal swipe
            if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
                if (diffX > 0) {
                    this.navigate('next');
                } else {
                    this.navigate('prev');
                }
            }
            // Vertical swipe down to close
            else if (diffY < -100) {
                this.close();
            }
        });
    }

    /**
     * Format file size for display
     * @param {number} bytes - File size in bytes
     * @returns {string} Formatted file size
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
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
     * Get current media item
     * @returns {Object|null} Current media item
     */
    getCurrentMedia() {
        return this.currentMedia[this.currentIndex] || null;
    }

    /**
     * Jump to specific media index
     * @param {number} index - Target index
     */
    jumpTo(index) {
        if (index >= 0 && index < this.currentMedia.length) {
            this.currentIndex = index;
            this.showMedia(index);
            this.updateNavigation();
            this.updateThumbnailSelection();
        }
    }
}
