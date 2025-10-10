// Main application entry point
import { API } from './api.js';
import { GalleryRenderer } from './gallery.js';
import { BreadcrumbManager } from './breadcrumb.js';
import { ModalManager } from './modal.js';
import { SidePanelManager } from './sidebar.js';
import { JustifiedLayout } from './justified.js';
import { KeyboardHandler } from './keyboard.js';
import { LoadingManager } from './loading.js';

class PhotoBrowserApp {
    constructor() {
        this.api = new API();
        this.galleryRenderer = new GalleryRenderer();
        this.breadcrumbManager = new BreadcrumbManager();
        this.modalManager = new ModalManager();
        this.sidePanelManager = new SidePanelManager();
        this.mosaicLayout = new JustifiedLayout();
        this.keyboardHandler = new KeyboardHandler();
        this.loadingManager = new LoadingManager();
        
        this.currentPath = '/';
        this.currentMedia = [];
        this.currentDirectories = [];
        
        this.init();
    }

    async init() {
        try {
            // Initialize all components
            this.setupEventListeners();
            this.keyboardHandler.init(this);
            
            // Load initial directory
            await this.loadDirectory(this.currentPath);
            
            console.log('Photo Browser App initialized successfully');
        } catch (error) {
            console.error('Failed to initialize app:', error);
            this.showError('Failed to initialize application');
        }
    }

    setupEventListeners() {
        // Breadcrumb navigation
        this.breadcrumbManager.onNavigate = (path) => {
            this.loadDirectory(path);
        };

        // Gallery item clicks
        this.galleryRenderer.onItemClick = (item, index) => {
            if (item.type === 'folder') {
                this.loadDirectory(item.relativePath);
            } else {
                this.modalManager.open(this.currentMedia, index);
            }
        };

        // Gallery item context menu (right-click)
        this.galleryRenderer.onItemRightClick = (item, event) => {
            event.preventDefault();
            if (item.type !== 'folder') {
                this.sidePanelManager.open(item);
            }
        };

        // Modal events
        this.modalManager.onNavigate = (direction) => {
            // Navigation handled internally by modal manager
        };

        this.modalManager.onClose = () => {
            // Modal closed
        };

        // Side panel events
        this.sidePanelManager.onDownload = (item) => {
            this.downloadFile(item);
        };

        this.sidePanelManager.onDelete = (item) => {
            this.deleteFile(item);
        };

        this.sidePanelManager.onInfo = (item) => {
            this.showFileInfo(item);
        };

        // Window resize for mosaic layout
        window.addEventListener('resize', () => {
            this.mosaicLayout.refresh();
        });

        // Browser back/forward navigation
        window.addEventListener('popstate', (event) => {
            const path = event.state?.path || '/';
            this.loadDirectory(path, false);
        });
    }

    async loadDirectory(path, pushState = true) {
        try {
            this.loadingManager.show();
            
            // Update current path
            this.currentPath = path;
            
            // Update browser history
            if (pushState) {
                const url = new URL(window.location);
                url.searchParams.set('path', path);
                history.pushState({ path }, '', url);
            }

            // Load directory data and breadcrumb in parallel
            const [directoryData, breadcrumbData] = await Promise.all([
                this.api.getDirectory(path),
                this.api.getBreadcrumb(path)
            ]);

            // Update current data
            this.currentMedia = directoryData.media || [];
            this.currentDirectories = directoryData.folders || [];
            
            // Update UI components
            this.breadcrumbManager.update(breadcrumbData.breadcrumbs, breadcrumbData.navigation);
            
            // Combine media and directories for gallery
            const allItems = [...this.currentDirectories, ...this.currentMedia];
            this.galleryRenderer.render(allItems);
            
            // Apply mosaic layout
            setTimeout(() => {
                this.mosaicLayout.apply();
            }, 100);

        } catch (error) {
            console.error('Failed to load directory:', error);
            this.showError(`Failed to load directory: ${path}`);
        } finally {
            this.loadingManager.hide();
        }
    }

    async downloadFile(item) {
        try {
            this.loadingManager.show(`Downloading ${item.name}...`);
            
            const response = await fetch(`/api/media/${item.relativePath}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            
            const a = document.createElement('a');
            a.href = url;
            a.download = item.name;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            this.sidePanelManager.showSuccess('File downloaded successfully');
            
        } catch (error) {
            console.error('Download failed:', error);
            this.sidePanelManager.showError('Download failed');
        } finally {
            this.loadingManager.hide();
        }
    }

    async deleteFile(item) {
        if (!confirm(`Are you sure you want to delete "${item.name}"?`)) {
            return;
        }

        try {
            this.loadingManager.show(`Deleting ${item.name}...`);
            
            const response = await fetch(`/api/media/${item.relativePath}`, {
                method: 'DELETE'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Remove item from current media array
            const index = this.currentMedia.findIndex(media => media.relativePath === item.relativePath);
            if (index > -1) {
                this.currentMedia.splice(index, 1);
                
                // Re-render gallery
                const allItems = [...this.currentDirectories, ...this.currentMedia];
                this.galleryRenderer.render(allItems);
                this.mosaicLayout.apply();
            }
            
            this.sidePanelManager.showSuccess('File deleted successfully');
            this.sidePanelManager.close();
            
        } catch (error) {
            console.error('Delete failed:', error);
            this.sidePanelManager.showError('Delete failed');
        } finally {
            this.loadingManager.hide();
        }
    }

    showFileInfo(item) {
        this.sidePanelManager.showFileInfo(item);
    }

    showError(message) {
        const gallery = document.getElementById('gallery');
        gallery.innerHTML = `
            <div class="error-message">
                <h2>Error</h2>
                <p>${message}</p>
                <button onclick="location.reload()" class="action-btn" style="margin-top: 20px;">
                    Reload Page
                </button>
            </div>
        `;
    }

    // Public API for keyboard shortcuts and external access
    navigateModal(direction) {
        this.modalManager.navigate(direction);
    }

    closeModal() {
        this.modalManager.close();
    }

    toggleSidePanel() {
        this.sidePanelManager.toggle();
    }

    getCurrentMedia() {
        return this.currentMedia;
    }

    getCurrentPath() {
        return this.currentPath;
    }
}

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Get initial path from URL
    const urlParams = new URLSearchParams(window.location.search);
    const initialPath = urlParams.get('path') || '/';
    
    // Create and start the app
    window.photoBrowserApp = new PhotoBrowserApp();
    
    // Set initial path if different from default
    if (initialPath !== '/') {
        window.photoBrowserApp.loadDirectory(initialPath);
    }
});

export { PhotoBrowserApp };
