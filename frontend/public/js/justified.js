// Justified gallery layout - matches the original jQuery Mosaic plugin behavior
export class JustifiedLayout {
    constructor() {
        this.gallery = document.getElementById('gallery');
        this.targetRowHeight = 250;
        this.maxRowHeight = 350;
        this.minRowHeight = 180;
        this.spacing = 2;
        this.containerPadding = 20;
        this.lastRowJustification = 'left'; // 'left', 'center', or 'justify'
        
        this.init();
    }

    init() {
        // Observe resize changes
        this.resizeObserver = new ResizeObserver(() => {
            this.refresh();
        });
        
        this.resizeObserver.observe(this.gallery);
    }

    /**
     * Apply justified layout to gallery items
     */
    apply() {
        const items = Array.from(this.gallery.querySelectorAll('.thumb'));
        if (items.length === 0) return;

        // Update target row height based on viewport
        this.targetRowHeight = this.getResponsiveRowHeight();
        this.maxRowHeight = this.targetRowHeight * 1.4;
        this.minRowHeight = this.targetRowHeight * 0.7;

        // Reset gallery styles
        this.gallery.style.display = 'block';
        this.gallery.style.padding = `${this.containerPadding}px`;
        
        // Get container width
        const containerWidth = this.gallery.clientWidth - (this.containerPadding * 2);
        
        // Calculate justified rows
        const rows = this.calculateRows(items, containerWidth);
        
        // Apply the layout
        this.applyRowLayout(rows);
    }

    /**
     * Calculate justified rows from items
     * @param {Array} items - Gallery items
     * @param {number} containerWidth - Available width
     * @returns {Array} Array of rows with sized items
     */
    calculateRows(items, containerWidth) {
        const rows = [];
        let currentRow = [];
        let currentRowWidth = 0;

        for (let i = 0; i < items.length; i++) {
            const item = items[i];
            const aspectRatio = this.getItemAspectRatio(item);
            const itemWidth = this.targetRowHeight * aspectRatio;
            
            // Add item to current row
            currentRow.push({
                element: item,
                aspectRatio,
                originalWidth: itemWidth
            });
            
            currentRowWidth += itemWidth;
            
            // Check if we should finalize this row
            const isLastItem = i === items.length - 1;
            const nextItemWidth = isLastItem ? 0 : this.targetRowHeight * this.getItemAspectRatio(items[i + 1] || { dataset: { aspectRatio: '1' } });
            const spacingWidth = this.spacing * (currentRow.length - 1);
            const totalCurrentWidth = currentRowWidth + spacingWidth;
            const totalWithNextItem = totalCurrentWidth + nextItemWidth + this.spacing;
            
            const shouldFinalize = isLastItem || totalWithNextItem > containerWidth;
            
            if (shouldFinalize) {
                // Calculate the justified row
                const justifiedRow = this.justifyRow(currentRow, totalCurrentWidth, containerWidth, isLastItem);
                rows.push(justifiedRow);
                
                // Reset for next row
                currentRow = [];
                currentRowWidth = 0;
            }
        }

        return rows;
    }

    /**
     * Justify a single row to fit the container width
     * @param {Array} rowItems - Items in the row
     * @param {number} currentWidth - Current total width
     * @param {number} targetWidth - Target width to fit
     * @param {boolean} isLastRow - Whether this is the last row
     * @returns {Object} Justified row data
     */
    justifyRow(rowItems, currentWidth, targetWidth, isLastRow = false) {
        if (rowItems.length === 0) return { items: [], height: this.targetRowHeight };

        // For the last row with few items, don't stretch them too much
        if (isLastRow && rowItems.length < 3) {
            const totalItemWidth = rowItems.reduce((sum, item) => sum + item.originalWidth, 0);
            const spacingWidth = this.spacing * (rowItems.length - 1);
            
            if (totalItemWidth + spacingWidth < targetWidth * 0.7) {
                // Don't justify the last row if it would stretch images too much
                return this.createNonJustifiedRow(rowItems, this.targetRowHeight);
            }
        }

        // Calculate scale factor to fit the row
        const totalItemWidth = rowItems.reduce((sum, item) => sum + item.originalWidth, 0);
        const spacingWidth = this.spacing * (rowItems.length - 1);
        const availableWidth = targetWidth - spacingWidth;
        const scaleFactor = availableWidth / totalItemWidth;
        
        // Calculate final height (with min/max constraints)
        let finalHeight = this.targetRowHeight * scaleFactor;
        finalHeight = Math.max(this.minRowHeight, Math.min(this.maxRowHeight, finalHeight));
        
        // If height was constrained, recalculate scale factor
        const heightScaleFactor = finalHeight / this.targetRowHeight;
        
        // Calculate final widths for each item
        const justifiedItems = rowItems.map((item, index) => {
            const width = item.originalWidth * heightScaleFactor;
            
            return {
                element: item.element,
                width: Math.round(width),
                height: Math.round(finalHeight)
            };
        });

        return {
            items: justifiedItems,
            height: Math.round(finalHeight),
            justified: true
        };
    }

    /**
     * Create a non-justified row (for last row when it shouldn't be stretched)
     * @param {Array} rowItems - Items in the row
     * @param {number} height - Target height
     * @returns {Object} Row data
     */
    createNonJustifiedRow(rowItems, height) {
        const items = rowItems.map(item => ({
            element: item.element,
            width: Math.round(height * item.aspectRatio),
            height: Math.round(height)
        }));

        return {
            items,
            height: Math.round(height),
            justified: false
        };
    }

    /**
     * Apply the calculated row layout to DOM elements
     * @param {Array} rows - Calculated rows
     */
    applyRowLayout(rows) {
        // Create a new container for the justified layout
        const justifiedContainer = document.createElement('div');
        justifiedContainer.className = 'justified-gallery';
        justifiedContainer.style.cssText = `
            display: block;
            width: 100%;
            overflow: hidden;
        `;

        rows.forEach(row => {
            const rowElement = document.createElement('div');
            rowElement.className = 'justified-row';
            
            const rowStyle = `
                display: flex;
                margin-bottom: ${this.spacing}px;
                height: ${row.height}px;
                ${!row.justified ? 'justify-content: ' + this.lastRowJustification + ';' : ''}
            `;
            rowElement.style.cssText = rowStyle;

            row.items.forEach((item, index) => {
                const itemElement = item.element;
                
                // Apply justified sizing to the original element
                const itemStyle = `
                    width: ${item.width}px;
                    height: ${item.height}px;
                    margin-right: ${index < row.items.length - 1 ? this.spacing : 0}px;
                    flex-shrink: 0;
                    overflow: hidden;
                    border-radius: 6px;
                    position: relative;
                `;
                itemElement.style.cssText = itemStyle;

                // Ensure images/videos fill their containers
                const media = itemElement.querySelector('img, video');
                if (media) {
                    media.style.cssText = `
                        width: 100%;
                        height: 100%;
                        object-fit: cover;
                        display: block;
                        transition: transform 0.3s ease;
                    `;
                }

                // Handle folder items
                const thumbimg = itemElement.querySelector('.thumbimg');
                if (thumbimg) {
                    // On mobile, ensure folders have enough space for labels
                    const isMobile = window.innerWidth <= 768;
                    const labelHeight = isMobile ? 35 : 40;
                    
                    thumbimg.style.cssText = `
                        width: 100%;
                        height: calc(100% - ${labelHeight}px);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        background: linear-gradient(135deg, #333 0%, #222 100%);
                    `;
                }

                // Handle labels
                const thumblabel = itemElement.querySelector('.thumblabel');
                if (thumblabel) {
                    const isMobile = window.innerWidth <= 768;
                    const labelHeight = isMobile ? 35 : 40;
                    const fontSize = isMobile ? 10 : 11;
                    
                    thumblabel.style.cssText = `
                        height: ${labelHeight}px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        padding: ${isMobile ? '6px' : '8px'};
                        font-size: ${fontSize}px;
                        font-weight: 500;
                        background: linear-gradient(180deg, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.9) 100%);
                        color: #fff;
                        text-overflow: ellipsis;
                        white-space: nowrap;
                        overflow: hidden;
                        position: absolute;
                        bottom: 0;
                        left: 0;
                        right: 0;
                    `;
                }

                // Add hover effect if not already added
                if (!itemElement.hasAttribute('data-hover-added')) {
                    itemElement.setAttribute('data-hover-added', 'true');
                    
                    itemElement.addEventListener('mouseenter', () => {
                        if (media) {
                            media.style.transform = 'scale(1.05)';
                        }
                        itemElement.style.zIndex = '10';
                        itemElement.style.boxShadow = '0 8px 32px rgba(0,0,0,0.4)';
                    });

                    itemElement.addEventListener('mouseleave', () => {
                        if (media) {
                            media.style.transform = 'scale(1)';
                        }
                        itemElement.style.zIndex = '1';
                        itemElement.style.boxShadow = 'none';
                    });
                }

                rowElement.appendChild(itemElement);
            });

            justifiedContainer.appendChild(rowElement);
        });

        // Replace gallery content
        this.gallery.innerHTML = '';
        this.gallery.appendChild(justifiedContainer);
    }

    /**
     * Get aspect ratio for a gallery item
     * @param {HTMLElement} item - Gallery item element
     * @returns {number} Aspect ratio
     */
    getItemAspectRatio(item) {
        if (!item) return 1;

        // Check if aspect ratio is cached
        if (item.dataset.aspectRatio) {
            return parseFloat(item.dataset.aspectRatio);
        }

        const media = item.querySelector('img, video');
        let aspectRatio = 1; // Default square ratio

        if (media) {
            if (media.tagName === 'IMG') {
                if (media.naturalWidth && media.naturalHeight) {
                    aspectRatio = media.naturalWidth / media.naturalHeight;
                } else {
                    // Image not loaded yet, use a default and set up load handler
                    aspectRatio = 4/3;
                    media.addEventListener('load', () => {
                        item.dataset.aspectRatio = media.naturalWidth / media.naturalHeight;
                        this.refresh();
                    }, { once: true });
                }
            } else if (media.tagName === 'VIDEO') {
                if (media.videoWidth && media.videoHeight) {
                    aspectRatio = media.videoWidth / media.videoHeight;
                } else {
                    // Video metadata not loaded yet
                    aspectRatio = 16/9;
                    media.addEventListener('loadedmetadata', () => {
                        item.dataset.aspectRatio = media.videoWidth / media.videoHeight;
                        this.refresh();
                    }, { once: true });
                }
            }
        } else {
            // Folder or other item - use square ratio
            aspectRatio = 1;
        }

        // Cache the aspect ratio
        item.dataset.aspectRatio = aspectRatio;
        return aspectRatio;
    }

    /**
     * Get responsive row height based on viewport width
     * @returns {number} Adjusted row height
     */
    getResponsiveRowHeight() {
        const viewportWidth = window.innerWidth;
        
        if (viewportWidth <= 480) {
            return 160; // Small phones - increased to ensure label visibility
        } else if (viewportWidth <= 768) {
            return 180; // Tablets and larger phones - increased for labels
        } else if (viewportWidth <= 1200) {
            return 250; // Desktop
        } else {
            return 300; // Large desktop
        }
    }

    /**
     * Set target row height
     * @param {number} height - Target height in pixels
     */
    setRowHeight(height) {
        this.targetRowHeight = height;
        this.maxRowHeight = height * 1.3;
        this.minRowHeight = height * 0.7;
        this.refresh();
    }

    /**
     * Refresh the layout
     */
    refresh() {
        // Debounce refresh calls
        clearTimeout(this.refreshTimeout);
        this.refreshTimeout = setTimeout(() => {
            this.apply();
        }, 100);
    }

    /**
     * Destroy the layout manager
     */
    destroy() {
        if (this.resizeObserver) {
            this.resizeObserver.disconnect();
        }
        clearTimeout(this.refreshTimeout);
    }
}
