// Loading manager for handling loading states
export class LoadingManager {
    constructor() {
        this.overlay = document.getElementById('loading-overlay');
        this.isLoading = false;
        this.loadingStack = 0;
        this.loadingMessage = null;
        
        this.init();
    }

    init() {
        // Ensure overlay exists
        if (!this.overlay) {
            this.createOverlay();
        }
        
        // Hide initially
        this.hide();
    }

    /**
     * Create loading overlay if it doesn't exist
     */
    createOverlay() {
        this.overlay = document.createElement('div');
        this.overlay.id = 'loading-overlay';
        this.overlay.className = 'loading-overlay hidden';
        
        this.overlay.innerHTML = `
            <div class="loading-content">
                <div class="loading-spinner"></div>
                <div class="loading-message"></div>
            </div>
        `;
        
        document.body.appendChild(this.overlay);
        this.loadingMessage = this.overlay.querySelector('.loading-message');
    }

    /**
     * Show loading overlay
     * @param {string} message - Optional loading message
     */
    show(message = '') {
        this.loadingStack++;
        
        if (!this.isLoading) {
            this.isLoading = true;
            this.overlay.classList.remove('hidden');
            
            // Add slight delay to prevent flashing for quick operations
            this.showTimeout = setTimeout(() => {
                this.overlay.style.opacity = '1';
            }, 100);
        }
        
        if (message && this.loadingMessage) {
            this.loadingMessage.textContent = message;
            this.loadingMessage.style.display = 'block';
        }
    }

    /**
     * Hide loading overlay
     */
    hide() {
        this.loadingStack = Math.max(0, this.loadingStack - 1);
        
        if (this.loadingStack === 0 && this.isLoading) {
            this.isLoading = false;
            
            clearTimeout(this.showTimeout);
            
            this.overlay.style.opacity = '0';
            
            setTimeout(() => {
                if (!this.isLoading) {
                    this.overlay.classList.add('hidden');
                    if (this.loadingMessage) {
                        this.loadingMessage.style.display = 'none';
                        this.loadingMessage.textContent = '';
                    }
                }
            }, 300);
        }
    }

    /**
     * Force hide loading (clear stack)
     */
    forceHide() {
        this.loadingStack = 0;
        this.hide();
    }

    /**
     * Update loading message
     * @param {string} message - New message
     */
    updateMessage(message) {
        if (this.loadingMessage && this.isLoading) {
            this.loadingMessage.textContent = message;
            this.loadingMessage.style.display = message ? 'block' : 'none';
        }
    }

    /**
     * Show loading for a specific duration
     * @param {number} duration - Duration in milliseconds
     * @param {string} message - Loading message
     */
    showFor(duration, message = '') {
        this.show(message);
        
        setTimeout(() => {
            this.hide();
        }, duration);
    }

    /**
     * Show loading for a promise
     * @param {Promise} promise - Promise to wait for
     * @param {string} message - Loading message
     * @returns {Promise} The original promise
     */
    showForPromise(promise, message = '') {
        this.show(message);
        
        return promise.finally(() => {
            this.hide();
        });
    }

    /**
     * Wrap a function with loading state
     * @param {Function} fn - Function to wrap
     * @param {string} message - Loading message
     * @returns {Function} Wrapped function
     */
    wrapFunction(fn, message = '') {
        return async (...args) => {
            this.show(message);
            try {
                return await fn(...args);
            } finally {
                this.hide();
            }
        };
    }

    /**
     * Create a progress-aware loading indicator
     * @param {string} message - Base message
     * @returns {Object} Progress controller
     */
    createProgress(message = '') {
        const progressId = Date.now();
        this.show(message);
        
        return {
            update: (current, total, customMessage = null) => {
                const percent = total > 0 ? Math.round((current / total) * 100) : 0;
                const msg = customMessage || `${message} (${percent}%)`;
                this.updateMessage(msg);
            },
            
            setMessage: (newMessage) => {
                this.updateMessage(newMessage);
            },
            
            complete: () => {
                this.hide();
            }
        };
    }

    /**
     * Show inline loading indicator for specific element
     * @param {HTMLElement} element - Target element
     * @param {string} message - Loading message
     * @returns {Function} Function to hide the indicator
     */
    showInline(element, message = 'Loading...') {
        const originalContent = element.innerHTML;
        const originalStyle = element.style.cssText;
        
        element.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: center; gap: 10px; padding: 20px;">
                <div style="width: 20px; height: 20px; border: 2px solid #333; border-top: 2px solid #fff; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                <span>${message}</span>
            </div>
        `;
        
        element.style.opacity = '0.7';
        element.style.pointerEvents = 'none';
        
        return () => {
            element.innerHTML = originalContent;
            element.style.cssText = originalStyle;
        };
    }

    /**
     * Show skeleton loading for gallery
     */
    showGallerySkeleton() {
        const gallery = document.getElementById('gallery');
        if (!gallery) return;
        
        const skeletonItems = Array.from({ length: 12 }, (_, i) => `
            <div class="thumb loading" style="height: ${150 + (i % 3) * 50}px;">
                <div style="width: 100%; height: 100%; background: linear-gradient(90deg, #333 25%, #444 50%, #333 75%); background-size: 200% 100%; animation: loading 1.5s infinite;"></div>
            </div>
        `).join('');
        
        gallery.innerHTML = `<div class="gallery-grid">${skeletonItems}</div>`;
        
        return () => {
            gallery.innerHTML = '';
        };
    }

    /**
     * Show loading button state
     * @param {HTMLButtonElement} button - Button element
     * @param {string} loadingText - Text to show while loading
     * @returns {Function} Function to restore button
     */
    showButtonLoading(button, loadingText = 'Loading...') {
        const originalText = button.textContent;
        const originalDisabled = button.disabled;
        
        button.textContent = loadingText;
        button.disabled = true;
        button.style.opacity = '0.7';
        
        return () => {
            button.textContent = originalText;
            button.disabled = originalDisabled;
            button.style.opacity = '';
        };
    }

    /**
     * Get current loading state
     * @returns {boolean} True if currently loading
     */
    isCurrentlyLoading() {
        return this.isLoading;
    }

    /**
     * Get loading stack count
     * @returns {number} Number of active loading operations
     */
    getLoadingCount() {
        return this.loadingStack;
    }

    /**
     * Add CSS for loading animations if not present
     */
    ensureLoadingStyles() {
        if (!document.getElementById('loading-styles')) {
            const style = document.createElement('style');
            style.id = 'loading-styles';
            style.textContent = `
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                
                @keyframes loading {
                    0% { background-position: 200% 0; }
                    100% { background-position: -200% 0; }
                }
                
                .loading-overlay {
                    transition: opacity 0.3s ease;
                }
                
                .loading-content {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 15px;
                }
                
                .loading-message {
                    color: white;
                    font-size: 16px;
                    text-align: center;
                    max-width: 300px;
                    display: none;
                }
            `;
            
            document.head.appendChild(style);
        }
    }

    /**
     * Destroy loading manager
     */
    destroy() {
        this.forceHide();
        clearTimeout(this.showTimeout);
        
        if (this.overlay && this.overlay.parentNode) {
            this.overlay.parentNode.removeChild(this.overlay);
        }
    }
}
