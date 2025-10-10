// Mosaic layout manager for gallery
export class MosaicLayout {
    constructor() {
        this.gallery = document.getElementById('gallery');
        this.layoutType = 'grid'; // grid, masonry, flex
        this.isEnabled = true;
        
        this.init();
    }

    init() {
        // Observe resize changes
        this.resizeObserver = new ResizeObserver(() => {
            if (this.isEnabled) {
                this.refresh();
            }
        });
        
        this.resizeObserver.observe(this.gallery);
    }

    /**
     * Apply mosaic layout to current gallery items
     */
    apply() {
        if (!this.isEnabled) return;

        const items = this.gallery.querySelectorAll('.thumb');
        if (items.length === 0) return;

        switch (this.layoutType) {
            case 'masonry':
                this.applyMasonryLayout(items);
                break;
            case 'flex':
                this.applyFlexLayout(items);
                break;
            default:
                this.applyGridLayout(items);
        }
    }

    /**
     * Apply CSS Grid layout
     * @param {NodeList} items - Gallery items
     */
    applyGridLayout(items) {
        this.gallery.className = 'gallery-container gallery-grid';
        
        items.forEach(item => {
            // Reset any custom styles
            item.style.gridRowEnd = '';
            item.style.flexBasis = '';
            item.style.height = '';
        });
    }

    /**
     * Apply masonry-style grid layout
     * @param {NodeList} items - Gallery items
     */
    applyMasonryLayout(items) {
        this.gallery.className = 'gallery-container gallery-masonry';
        
        items.forEach(item => {
            const img = item.querySelector('img, video');
            if (img) {
                this.calculateMasonrySize(item, img);
            }
        });
    }

    /**
     * Apply flexible mosaic layout
     * @param {NodeList} items - Gallery items
     */
    applyFlexLayout(items) {
        this.gallery.className = 'gallery-container gallery-flex';
        
        // Group items into rows for better mosaic effect
        this.arrangeFlexRows(items);
    }

    /**
     * Calculate masonry grid size for item
     * @param {HTMLElement} item - Gallery item
     * @param {HTMLElement} media - Media element (img/video)
     */
    calculateMasonrySize(item, media) {
        const loadHandler = () => {
            const aspectRatio = media.naturalHeight / media.naturalWidth || media.videoHeight / media.videoWidth || 1;
            const baseHeight = 200; // Base grid row height in pixels
            const gridRowHeight = 20; // CSS grid-auto-rows value
            
            // Calculate number of grid rows needed
            const rows = Math.ceil((baseHeight * aspectRatio) / gridRowHeight);
            
            // Set CSS custom property for grid-row-end
            item.style.setProperty('--grid-rows', Math.max(8, Math.min(25, rows)));
            item.style.gridRowEnd = `span var(--grid-rows)`;
        };

        if (media.complete || media.readyState >= 1) {
            loadHandler();
        } else {
            media.addEventListener('load', loadHandler);
            media.addEventListener('loadedmetadata', loadHandler);
        }
    }

    /**
     * Arrange items in flexible mosaic rows
     * @param {NodeList} items - Gallery items
     */
    arrangeFlexRows(items) {
        const targetRowHeight = 250;
        const itemsArray = Array.from(items);
        let currentRow = [];
        let currentRowWidth = 0;
        const containerWidth = this.gallery.clientWidth - 40; // Account for padding

        itemsArray.forEach((item, index) => {
            const img = item.querySelector('img, video');
            if (!img) return;

            const aspectRatio = this.getAspectRatio(img);
            const itemWidth = targetRowHeight * aspectRatio;
            
            currentRow.push({ item, width: itemWidth, aspectRatio });
            currentRowWidth += itemWidth;

            // Check if we should finalize this row
            const isLastItem = index === itemsArray.length - 1;
            const rowIsFull = currentRowWidth >= containerWidth * 0.8;
            
            if (rowIsFull || isLastItem) {
                this.finalizeFlexRow(currentRow, currentRowWidth, containerWidth, targetRowHeight);
                currentRow = [];
                currentRowWidth = 0;
            }
        });
    }

    /**
     * Finalize a flex row with proper sizing
     * @param {Array} row - Array of items in the row
     * @param {number} rowWidth - Current row width
     * @param {number} containerWidth - Container width
     * @param {number} targetHeight - Target height for the row
     */
    finalizeFlexRow(row, rowWidth, containerWidth, targetHeight) {
        if (row.length === 0) return;

        const scaleFactor = Math.min(1, containerWidth / rowWidth);
        const actualHeight = targetHeight * scaleFactor;

        row.forEach(({ item, aspectRatio }) => {
            const width = actualHeight * aspectRatio;
            const percentage = (width / containerWidth) * 100;
            
            item.style.flexBasis = `${Math.max(20, Math.min(60, percentage))}%`;
            item.style.height = `${actualHeight}px`;
        });
    }

    /**
     * Get aspect ratio from media element
     * @param {HTMLElement} media - Media element
     * @returns {number} Aspect ratio
     */
    getAspectRatio(media) {
        if (media.tagName === 'VIDEO') {
            return media.videoWidth && media.videoHeight 
                ? media.videoWidth / media.videoHeight 
                : 16 / 9; // Default video aspect ratio
        } else {
            return media.naturalWidth && media.naturalHeight 
                ? media.naturalWidth / media.naturalHeight 
                : 4 / 3; // Default image aspect ratio
        }
    }

    /**
     * Set layout type
     * @param {string} type - Layout type (grid, masonry, flex)
     */
    setLayoutType(type) {
        if (['grid', 'masonry', 'flex'].includes(type)) {
            this.layoutType = type;
            this.apply();
        }
    }

    /**
     * Refresh layout (recalculate and apply)
     */
    refresh() {
        if (this.isEnabled) {
            // Debounce refresh calls
            clearTimeout(this.refreshTimeout);
            this.refreshTimeout = setTimeout(() => {
                this.apply();
            }, 100);
        }
    }

    /**
     * Enable/disable mosaic layout
     * @param {boolean} enabled - Whether to enable layout
     */
    setEnabled(enabled) {
        this.isEnabled = enabled;
        if (enabled) {
            this.apply();
        } else {
            this.resetLayout();
        }
    }

    /**
     * Reset all layout styles
     */
    resetLayout() {
        const items = this.gallery.querySelectorAll('.thumb');
        items.forEach(item => {
            item.style.gridRowEnd = '';
            item.style.flexBasis = '';
            item.style.height = '';
        });
        
        this.gallery.className = 'gallery-container';
    }

    /**
     * Get optimal layout type based on screen size and content
     * @param {number} itemCount - Number of items
     * @returns {string} Recommended layout type
     */
    getOptimalLayout(itemCount) {
        const screenWidth = window.innerWidth;
        
        if (screenWidth < 768) {
            return 'grid'; // Simple grid for mobile
        } else if (itemCount < 20) {
            return 'flex'; // Flexible layout for smaller collections
        } else {
            return 'masonry'; // Masonry for large collections
        }
    }

    /**
     * Auto-select best layout
     */
    autoLayout() {
        const items = this.gallery.querySelectorAll('.thumb');
        const optimal = this.getOptimalLayout(items.length);
        this.setLayoutType(optimal);
    }

    /**
     * Add animation to layout changes
     */
    addLayoutAnimation() {
        this.gallery.style.transition = 'all 0.3s ease';
        
        setTimeout(() => {
            this.gallery.style.transition = '';
        }, 300);
    }

    /**
     * Destroy the layout manager
     */
    destroy() {
        if (this.resizeObserver) {
            this.resizeObserver.disconnect();
        }
        
        clearTimeout(this.refreshTimeout);
        this.resetLayout();
    }
}
