// Keyboard handler for navigation and shortcuts
export class KeyboardHandler {
    constructor() {
        this.app = null;
        this.isEnabled = true;
        this.shortcuts = new Map();
        
        this.init();
    }

    /**
     * Initialize keyboard handler
     * @param {Object} app - Main application instance
     */
    init(app = null) {
        this.app = app;
        
        // Set up default shortcuts
        this.setupDefaultShortcuts();
        
        // Add global event listener
        document.addEventListener('keydown', (e) => {
            if (this.isEnabled) {
                this.handleKeyPress(e);
            }
        });
    }

    /**
     * Set up default keyboard shortcuts
     */
    setupDefaultShortcuts() {
        // Modal navigation
        this.addShortcut(['ArrowLeft'], (e) => {
            if (this.isModalOpen()) {
                e.preventDefault();
                this.app?.navigateModal('prev');
            }
        });

        this.addShortcut(['ArrowRight'], (e) => {
            if (this.isModalOpen()) {
                e.preventDefault();
                this.app?.navigateModal('next');
            }
        });

        this.addShortcut(['Escape'], (e) => {
            if (this.isModalOpen()) {
                e.preventDefault();
                this.app?.closeModal();
            } else if (this.isSidePanelOpen()) {
                e.preventDefault();
                this.app?.toggleSidePanel();
            }
        });

        this.addShortcut([' ', 'Space'], (e) => {
            if (this.isModalOpen()) {
                e.preventDefault();
                this.toggleVideoPlayback();
            }
        });

        // Directory navigation
        this.addShortcut(['Alt', 'ArrowLeft'], (e) => {
            e.preventDefault();
            this.navigateToPrevious();
        });

        this.addShortcut(['Alt', 'ArrowRight'], (e) => {
            e.preventDefault();
            this.navigateToNext();
        });

        this.addShortcut(['Alt', 'ArrowUp'], (e) => {
            e.preventDefault();
            this.navigateToParent();
        });

        this.addShortcut(['Alt', 'Home'], (e) => {
            e.preventDefault();
            this.navigateToHome();
        });

        // File operations
        this.addShortcut(['Delete'], (e) => {
            if (this.isSidePanelOpen()) {
                e.preventDefault();
                this.deleteCurrentFile();
            }
        });

        this.addShortcut(['Ctrl', 's'], (e) => {
            if (this.isSidePanelOpen()) {
                e.preventDefault();
                this.downloadCurrentFile();
            }
        });

        this.addShortcut(['i'], (e) => {
            if (this.isModalOpen()) {
                e.preventDefault();
                this.showFileInfo();
            }
        });

        // Gallery navigation
        this.addShortcut(['Home'], (e) => {
            if (!this.isModalOpen() && !this.isSidePanelOpen()) {
                e.preventDefault();
                this.selectFirstItem();
            }
        });

        this.addShortcut(['End'], (e) => {
            if (!this.isModalOpen() && !this.isSidePanelOpen()) {
                e.preventDefault();
                this.selectLastItem();
            }
        });

        // Help
        this.addShortcut(['F1', '?'], (e) => {
            e.preventDefault();
            this.showHelp();
        });

        // Layout switching
        this.addShortcut(['1'], (e) => {
            if (!this.isInputFocused()) {
                e.preventDefault();
                this.switchLayout('grid');
            }
        });

        this.addShortcut(['2'], (e) => {
            if (!this.isInputFocused()) {
                e.preventDefault();
                this.switchLayout('masonry');
            }
        });

        this.addShortcut(['3'], (e) => {
            if (!this.isInputFocused()) {
                e.preventDefault();
                this.switchLayout('flex');
            }
        });
    }

    /**
     * Add a keyboard shortcut
     * @param {Array|string} keys - Key combination or single key
     * @param {Function} callback - Callback function
     * @param {string} description - Description for help
     */
    addShortcut(keys, callback, description = '') {
        const keyString = Array.isArray(keys) ? keys.join('+') : keys;
        this.shortcuts.set(keyString.toLowerCase(), {
            keys: Array.isArray(keys) ? keys : [keys],
            callback,
            description
        });
    }

    /**
     * Remove a keyboard shortcut
     * @param {Array|string} keys - Key combination to remove
     */
    removeShortcut(keys) {
        const keyString = Array.isArray(keys) ? keys.join('+') : keys;
        this.shortcuts.delete(keyString.toLowerCase());
    }

    /**
     * Handle key press events
     * @param {KeyboardEvent} e - Keyboard event
     */
    handleKeyPress(e) {
        // Don't handle shortcuts if user is typing in an input
        if (this.isInputFocused()) {
            return;
        }

        // Build key combination string
        const keys = [];
        
        if (e.ctrlKey) keys.push('Ctrl');
        if (e.altKey) keys.push('Alt');
        if (e.shiftKey) keys.push('Shift');
        if (e.metaKey) keys.push('Meta');
        
        // Add the main key
        let mainKey = e.key;
        if (mainKey === ' ') mainKey = 'Space';
        
        keys.push(mainKey);
        
        const keyString = keys.join('+').toLowerCase();
        
        // Check for exact match
        const shortcut = this.shortcuts.get(keyString);
        if (shortcut) {
            shortcut.callback(e);
            return;
        }

        // Check for single key shortcuts
        const singleKey = mainKey.toLowerCase();
        const singleShortcut = this.shortcuts.get(singleKey);
        if (singleShortcut && !e.ctrlKey && !e.altKey && !e.shiftKey && !e.metaKey) {
            singleShortcut.callback(e);
        }
    }

    /**
     * Check if modal is open
     * @returns {boolean} True if modal is open
     */
    isModalOpen() {
        const modal = document.getElementById('modal');
        return modal && modal.classList.contains('show');
    }

    /**
     * Check if side panel is open
     * @returns {boolean} True if side panel is open
     */
    isSidePanelOpen() {
        const sidePanel = document.getElementById('side-panel');
        return sidePanel && sidePanel.classList.contains('open');
    }

    /**
     * Check if an input element is focused
     * @returns {boolean} True if input is focused
     */
    isInputFocused() {
        const activeElement = document.activeElement;
        const inputTypes = ['input', 'textarea', 'select'];
        return inputTypes.includes(activeElement.tagName.toLowerCase()) ||
               activeElement.contentEditable === 'true';
    }

    /**
     * Toggle video playback in modal
     */
    toggleVideoPlayback() {
        const modal = document.getElementById('modal');
        const video = modal?.querySelector('video');
        
        if (video) {
            if (video.paused) {
                video.play();
            } else {
                video.pause();
            }
        }
    }

    /**
     * Navigate to previous directory
     */
    navigateToPrevious() {
        if (this.app?.breadcrumbManager?.previousPath) {
            this.app.loadDirectory(this.app.breadcrumbManager.previousPath);
        }
    }

    /**
     * Navigate to next directory
     */
    navigateToNext() {
        if (this.app?.breadcrumbManager?.nextPath) {
            this.app.loadDirectory(this.app.breadcrumbManager.nextPath);
        }
    }

    /**
     * Navigate to parent directory
     */
    navigateToParent() {
        this.app?.breadcrumbManager?.navigateToParent();
    }

    /**
     * Navigate to home directory
     */
    navigateToHome() {
        this.app?.breadcrumbManager?.navigateToHome();
    }

    /**
     * Delete current file (if side panel is open)
     */
    deleteCurrentFile() {
        const currentItem = this.app?.sidePanelManager?.getCurrentItem();
        if (currentItem && this.app?.sidePanelManager?.onDelete) {
            this.app.sidePanelManager.onDelete(currentItem);
        }
    }

    /**
     * Download current file (if side panel is open)
     */
    downloadCurrentFile() {
        const currentItem = this.app?.sidePanelManager?.getCurrentItem();
        if (currentItem && this.app?.sidePanelManager?.onDownload) {
            this.app.sidePanelManager.onDownload(currentItem);
        }
    }

    /**
     * Show file info for current modal item
     */
    showFileInfo() {
        const currentMedia = this.app?.modalManager?.getCurrentMedia();
        if (currentMedia) {
            this.app?.sidePanelManager?.open(currentMedia);
        }
    }

    /**
     * Select first item in gallery
     */
    selectFirstItem() {
        const firstItem = document.querySelector('.gallery-container .thumb');
        if (firstItem) {
            firstItem.click();
        }
    }

    /**
     * Select last item in gallery
     */
    selectLastItem() {
        const items = document.querySelectorAll('.gallery-container .thumb');
        if (items.length > 0) {
            items[items.length - 1].click();
        }
    }

    /**
     * Switch gallery layout
     * @param {string} layout - Layout type
     */
    switchLayout(layout) {
        this.app?.mosaicLayout?.setLayoutType(layout);
        this.showToast(`Switched to ${layout} layout`);
    }

    /**
     * Show help dialog
     */
    showHelp() {
        const shortcuts = Array.from(this.shortcuts.entries())
            .filter(([_, shortcut]) => shortcut.description)
            .map(([keys, shortcut]) => `${keys}: ${shortcut.description}`)
            .join('\n');

        const helpText = `
Keyboard Shortcuts:

Modal Navigation:
← → : Navigate between images/videos
Space: Play/pause video
Esc: Close modal
I: Show file info

Directory Navigation:
Alt + ←: Previous directory
Alt + →: Next directory  
Alt + ↑: Parent directory
Alt + Home: Home directory

File Operations:
Delete: Delete current file
Ctrl + S: Download current file

Gallery:
1, 2, 3: Switch layout modes
Home/End: Select first/last item
F1 or ?: Show this help

General:
Esc: Close panels/modals
        `.trim();

        alert(helpText);
    }

    /**
     * Show temporary toast message
     * @param {string} message - Message to show
     */
    showToast(message) {
        const toast = document.createElement('div');
        toast.className = 'toast-message';
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            z-index: 10000;
            font-size: 14px;
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 2000);
    }

    /**
     * Enable keyboard handling
     */
    enable() {
        this.isEnabled = true;
    }

    /**
     * Disable keyboard handling
     */
    disable() {
        this.isEnabled = false;
    }

    /**
     * Get all registered shortcuts
     * @returns {Map} Map of shortcuts
     */
    getShortcuts() {
        return new Map(this.shortcuts);
    }
}
