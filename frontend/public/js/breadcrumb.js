// Breadcrumb navigation manager
export class BreadcrumbManager {
    constructor() {
        this.breadcrumbContainer = document.getElementById('breadcrumb');
        this.prevNav = document.getElementById('prev-nav');
        this.nextNav = document.getElementById('next-nav');
        this.onNavigate = null;
        
        this.init();
    }

    init() {
        // Set up navigation arrow event listeners
        this.prevNav.addEventListener('click', () => {
            if (!this.prevNav.classList.contains('inactivenavigation') && this.previousPath) {
                if (this.onNavigate) {
                    this.onNavigate(this.previousPath);
                }
            }
        });

        this.nextNav.addEventListener('click', () => {
            if (!this.nextNav.classList.contains('inactivenavigation') && this.nextPath) {
                if (this.onNavigate) {
                    this.onNavigate(this.nextPath);
                }
            }
        });
    }

    /**
     * Update breadcrumb navigation
     * @param {Array} breadcrumbs - Array of breadcrumb items
     * @param {Object} navigation - Previous/next navigation data
     */
    update(breadcrumbs, navigation) {
        this.renderBreadcrumbs(breadcrumbs);
        this.updateNavigation(navigation);
    }

    /**
     * Render breadcrumb items
     * @param {Array} breadcrumbs - Array of breadcrumb objects {name, path}
     */
    renderBreadcrumbs(breadcrumbs) {
        if (!breadcrumbs || breadcrumbs.length === 0) {
            this.breadcrumbContainer.innerHTML = '<li><a href="#" data-path="/">Home</a></li>';
            return;
        }

        const breadcrumbHTML = breadcrumbs.map((crumb, index) => {
            const isLast = index === breadcrumbs.length - 1;
            const activeClass = isLast ? ' active' : '';
            
            return `
                <li>
                    <a href="#" 
                       data-path="${this.escapeHtml(crumb.path)}" 
                       class="baseNavigation${activeClass}">
                        ${this.escapeHtml(crumb.name)}
                    </a>
                </li>
            `;
        }).join('');

        this.breadcrumbContainer.innerHTML = breadcrumbHTML;

        // Add click event listeners to breadcrumb links
        this.breadcrumbContainer.querySelectorAll('a[data-path]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const path = link.dataset.path;
                if (this.onNavigate && path) {
                    this.onNavigate(path);
                }
            });
        });
    }

    /**
     * Update previous/next navigation arrows
     * @param {Object} navigation - Navigation object with previous and next paths
     */
    updateNavigation(navigation) {
        this.previousPath = navigation.previous;
        this.nextPath = navigation.next;

        // Update previous arrow
        if (this.previousPath) {
            this.prevNav.classList.remove('inactivenavigation');
            this.prevNav.classList.add('nav-arrow');
            this.prevNav.title = `Previous directory: ${this.previousPath}`;
        } else {
            this.prevNav.classList.add('inactivenavigation');
            this.prevNav.classList.remove('nav-arrow');
            this.prevNav.title = 'No previous directory';
        }

        // Update next arrow
        if (this.nextPath) {
            this.nextNav.classList.remove('inactivenavigation');
            this.nextNav.classList.add('nav-arrow');
            this.nextNav.title = `Next directory: ${this.nextPath}`;
        } else {
            this.nextNav.classList.add('inactivenavigation');
            this.nextNav.classList.remove('nav-arrow');
            this.nextNav.title = 'No next directory';
        }
    }

    /**
     * Get current breadcrumb path
     * @returns {string} Current path
     */
    getCurrentPath() {
        const activeLink = this.breadcrumbContainer.querySelector('a.active');
        return activeLink ? activeLink.dataset.path : '/';
    }

    /**
     * Get parent path of current location
     * @returns {string|null} Parent path or null if at root
     */
    getParentPath() {
        const links = this.breadcrumbContainer.querySelectorAll('a[data-path]');
        if (links.length <= 1) return null;
        
        return links[links.length - 2].dataset.path;
    }

    /**
     * Navigate to parent directory
     */
    navigateToParent() {
        const parentPath = this.getParentPath();
        if (parentPath && this.onNavigate) {
            this.onNavigate(parentPath);
        }
    }

    /**
     * Navigate to home directory
     */
    navigateToHome() {
        if (this.onNavigate) {
            this.onNavigate('/');
        }
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
     * Set loading state for navigation
     * @param {boolean} loading - Whether navigation is loading
     */
    setLoading(loading) {
        if (loading) {
            this.breadcrumbContainer.style.opacity = '0.6';
            this.prevNav.style.pointerEvents = 'none';
            this.nextNav.style.pointerEvents = 'none';
        } else {
            this.breadcrumbContainer.style.opacity = '1';
            this.prevNav.style.pointerEvents = '';
            this.nextNav.style.pointerEvents = '';
        }
    }

    /**
     * Add keyboard navigation support
     */
    addKeyboardSupport() {
        document.addEventListener('keydown', (e) => {
            // Alt + Left Arrow: Previous directory
            if (e.altKey && e.key === 'ArrowLeft') {
                e.preventDefault();
                if (this.previousPath) {
                    this.onNavigate(this.previousPath);
                }
            }
            
            // Alt + Right Arrow: Next directory
            if (e.altKey && e.key === 'ArrowRight') {
                e.preventDefault();
                if (this.nextPath) {
                    this.onNavigate(this.nextPath);
                }
            }
            
            // Alt + Up Arrow: Parent directory
            if (e.altKey && e.key === 'ArrowUp') {
                e.preventDefault();
                this.navigateToParent();
            }
            
            // Alt + Home: Home directory
            if (e.altKey && e.key === 'Home') {
                e.preventDefault();
                this.navigateToHome();
            }
        });
    }

    /**
     * Generate breadcrumb path from directory path
     * @param {string} path - Directory path
     * @returns {Array} Array of breadcrumb objects
     */
    static generateBreadcrumbs(path) {
        const breadcrumbs = [{ name: 'Home', path: '/' }];
        
        if (path && path !== '/') {
            const parts = path.split('/').filter(part => part);
            let buildPath = '';
            
            for (const part of parts) {
                buildPath += '/' + part;
                breadcrumbs.push({
                    name: part,
                    path: buildPath
                });
            }
        }
        
        return breadcrumbs;
    }

    /**
     * Format breadcrumb for display (truncate long names)
     * @param {string} name - Breadcrumb name
     * @param {number} maxLength - Maximum length
     * @returns {string} Formatted name
     */
    static formatBreadcrumbName(name, maxLength = 20) {
        if (name.length <= maxLength) return name;
        return name.substring(0, maxLength - 3) + '...';
    }
}
