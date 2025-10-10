// API module for handling server communication
export class API {
    constructor() {
        this.baseURL = window.location.origin;
    }

    /**
     * Get directory contents
     * @param {string} path - Directory path to load
     * @returns {Promise<Object>} Directory data with folders and media
     */
    async getDirectory(path = '/') {
        try {
            const response = await fetch(`${this.baseURL}/api/directory?path=${encodeURIComponent(path)}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            return data;
            
        } catch (error) {
            console.error('API: Failed to get directory:', error);
            throw error;
        }
    }

    /**
     * Get breadcrumb navigation data
     * @param {string} path - Current directory path
     * @returns {Promise<Object>} Breadcrumb and navigation data
     */
    async getBreadcrumb(path = '/') {
        try {
            const response = await fetch(`${this.baseURL}/api/breadcrumb?path=${encodeURIComponent(path)}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            return data;
            
        } catch (error) {
            console.error('API: Failed to get breadcrumb:', error);
            throw error;
        }
    }

    /**
     * Get media file URL for serving/thumbnails
     * @param {string} relativePath - Relative path to media file
     * @param {string} size - Size parameter for thumbnails (optional)
     * @returns {string} Media URL
     */
    getMediaURL(relativePath, size = null) {
        let url = `${this.baseURL}/api/media/${relativePath}`;
        if (size) {
            url += `?size=${encodeURIComponent(size)}`;
        }
        return url;
    }

    /**
     * Delete a media file
     * @param {string} relativePath - Relative path to media file
     * @returns {Promise<Object>} Delete response
     */
    async deleteMedia(relativePath) {
        try {
            const response = await fetch(`${this.baseURL}/api/media/${relativePath}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            return data;
            
        } catch (error) {
            console.error('API: Failed to delete media:', error);
            throw error;
        }
    }

    /**
     * Download a media file
     * @param {string} relativePath - Relative path to media file
     * @returns {Promise<Blob>} File blob
     */
    async downloadMedia(relativePath) {
        try {
            const response = await fetch(`${this.baseURL}/api/media/${relativePath}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const blob = await response.blob();
            return blob;
            
        } catch (error) {
            console.error('API: Failed to download media:', error);
            throw error;
        }
    }

    /**
     * Get file metadata/info
     * @param {string} relativePath - Relative path to media file
     * @returns {Promise<Object>} File metadata
     */
    async getFileInfo(relativePath) {
        try {
            const response = await fetch(`${this.baseURL}/api/info/${relativePath}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            return data;
            
        } catch (error) {
            console.error('API: Failed to get file info:', error);
            throw error;
        }
    }

    /**
     * Search for media files
     * @param {string} query - Search query
     * @param {string} path - Current directory path (optional)
     * @returns {Promise<Object>} Search results
     */
    async search(query, path = '/') {
        try {
            const url = new URL(`${this.baseURL}/api/search`);
            url.searchParams.set('q', query);
            if (path !== '/') {
                url.searchParams.set('path', path);
            }
            
            const response = await fetch(url);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            return data;
            
        } catch (error) {
            console.error('API: Failed to search:', error);
            throw error;
        }
    }

    /**
     * Upload files to a directory
     * @param {FileList} files - Files to upload
     * @param {string} path - Target directory path
     * @returns {Promise<Object>} Upload response
     */
    async uploadFiles(files, path = '/') {
        try {
            const formData = new FormData();
            
            for (let i = 0; i < files.length; i++) {
                formData.append('files', files[i]);
            }
            formData.append('path', path);
            
            const response = await fetch(`${this.baseURL}/api/upload`, {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            return data;
            
        } catch (error) {
            console.error('API: Failed to upload files:', error);
            throw error;
        }
    }

    /**
     * Create a new directory
     * @param {string} name - Directory name
     * @param {string} parentPath - Parent directory path
     * @returns {Promise<Object>} Create directory response
     */
    async createDirectory(name, parentPath = '/') {
        try {
            const response = await fetch(`${this.baseURL}/api/directory`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    name,
                    parentPath
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            return data;
            
        } catch (error) {
            console.error('API: Failed to create directory:', error);
            throw error;
        }
    }
}
