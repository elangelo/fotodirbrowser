package api

import (
	"encoding/json" // For JSON encoding/decoding
	"fmt"
	"io/fs" // File system interfaces
	"net/http"
	"os"
	"path/filepath"
	"strings"
)

// DirectoryHandler handles /api/directory requests
// It needs to know where the media root directory is
type DirectoryHandler struct {
	MediaRoot string // Absolute path to media directory
}

// NewDirectoryHandler creates a new directory handler
// This is like a constructor in C#
func NewDirectoryHandler(mediaRoot string) *DirectoryHandler {
	return &DirectoryHandler{
		MediaRoot: mediaRoot,
	}
}

// ServeHTTP implements the http.Handler interface
// This method is called when a request comes to this handler
func (h *DirectoryHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	// Only allow GET requests
	if r.Method != http.MethodGet {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	// Get the "path" query parameter
	// r.URL.Query() returns a map of query parameters
	requestedPath := r.URL.Query().Get("path")

	// Build the full file system path
	// Join mediaRoot with requested path
	fullPath := filepath.Join(h.MediaRoot, requestedPath)

	// SECURITY: Make sure the path doesn't escape media root
	// filepath.Clean removes .. and other path tricks
	cleanPath := filepath.Clean(fullPath)
	if !strings.HasPrefix(cleanPath, h.MediaRoot) {
		http.Error(w, "Invalid path", http.StatusBadRequest)
		return
	}

	// Check if directory exists
	fileInfo, err := os.Stat(cleanPath)
	if err != nil {
		if os.IsNotExist(err) {
			http.Error(w, "Directory not found", http.StatusNotFound)
			return
		}
		// Some other error (permissions, etc.)
		http.Error(w, "Error reading directory", http.StatusInternalServerError)
		return
	}

	// Make sure it's actually a directory
	if !fileInfo.IsDir() {
		http.Error(w, "Not a directory", http.StatusBadRequest)
		return
	}

	// Read the directory contents
	response, err := h.readDirectory(cleanPath, requestedPath)
	if err != nil {
		http.Error(w, fmt.Sprintf("Error reading directory: %v", err), http.StatusInternalServerError)
		return
	}

	// Send JSON response
	w.Header().Set("Content-Type", "application/json")

	// Encode response to JSON and write to response
	// json.NewEncoder creates an encoder that writes to w
	if err := json.NewEncoder(w).Encode(response); err != nil {
		// If encoding fails, we've already started writing response
		// Can't change status code now, just log the error
		fmt.Printf("Error encoding JSON: %v\n", err)
	}
}

// readDirectory reads a directory and returns the response
func (h *DirectoryHandler) readDirectory(fullPath, relativePath string) (*DirectoryResponse, error) {
	// Read directory entries
	// os.ReadDir returns a slice of fs.DirEntry
	entries, err := os.ReadDir(fullPath)
	if err != nil {
		return nil, err
	}

	// Initialize response
	response := &DirectoryResponse{
		CurrentPath: relativePath,
		Folders:     []FolderItem{},
		Media:       []MediaItem{},
		Tags:        []string{},
	}

	// Process each entry
	for _, entry := range entries {
		name := entry.Name()

		// Skip hidden files (starting with .)
		if strings.HasPrefix(name, ".") {
			continue
		}

		if entry.IsDir() {
			// It's a folder
			folder := FolderItem{
				Name: name,
				Path: filepath.Join(relativePath, name),
			}
			response.Folders = append(response.Folders, folder)
		} else {
			// It's a file - check if it's media
			if isMediaFile(name) {
				media, err := h.createMediaItem(relativePath, entry)
				if err != nil {
					// Log error but continue processing other files
					fmt.Printf("Error processing %s: %v\n", name, err)
					continue
				}
				response.Media = append(response.Media, media)
			}
		}
	}

	// Read tags file if it exists
	response.Tags = h.readTags(fullPath)

	return response, nil
}

// createMediaItem creates a MediaItem from a directory entry
func (h *DirectoryHandler) createMediaItem(relativePath string, entry fs.DirEntry) (MediaItem, error) {
	name := entry.Name()
	// fullPath := filepath.Join(dirPath, name)

	// Get file info
	info, err := entry.Info()
	if err != nil {
		return MediaItem{}, err
	}

	// Determine media type
	mediaType := "image"
	if isVideoFile(name) {
		mediaType = "video"
	}

	// Create media item
	item := MediaItem{
		Type:      mediaType,
		Name:      name,
		Path:      filepath.Join(relativePath, name),
		Thumbnail: filepath.Join("/api/media", relativePath, name), // For now, same as full path
		Size:      info.Size(),
		Modified:  info.ModTime().Unix(),
	}

	return item, nil
}

// isMediaFile checks if a filename is a supported media file
func isMediaFile(filename string) bool {
	return isImageFile(filename) || isVideoFile(filename)
}

// isImageFile checks if a filename is an image
func isImageFile(filename string) bool {
	ext := strings.ToLower(filepath.Ext(filename))
	imageExts := []string{".jpg", ".jpeg", ".png", ".gif", ".webp", ".bmp"}

	for _, validExt := range imageExts {
		if ext == validExt {
			return true
		}
	}
	return false
}

// isVideoFile checks if a filename is a video
func isVideoFile(filename string) bool {
	ext := strings.ToLower(filepath.Ext(filename))
	videoExts := []string{".mp4", ".webm", ".mov", ".avi", ".mkv"}

	for _, validExt := range videoExts {
		if ext == validExt {
			return true
		}
	}
	return false
}

// readTags reads tags from a .tags file in the directory
func (h *DirectoryHandler) readTags(dirPath string) []string {
	tagsFile := filepath.Join(dirPath, ".tags")

	// Read the file
	content, err := os.ReadFile(tagsFile)
	if err != nil {
		// File doesn't exist or can't be read - return empty slice
		return []string{}
	}

	// Split by newlines and filter empty lines
	lines := strings.Split(string(content), "\n")
	tags := []string{}

	for _, line := range lines {
		line = strings.TrimSpace(line)
		if line != "" {
			tags = append(tags, line)
		}
	}

	return tags
}
