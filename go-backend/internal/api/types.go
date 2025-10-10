package api

// MediaItem represents a single media file (image or video)
// The struct tags (json:"...") tell Go how to convert to/from JSON
type MediaItem struct {
	Type      string `json:"type"`      // "image" or "video"
	Name      string `json:"name"`      // Filename
	Path      string `json:"path"`      // Relative path from media root
	Thumbnail string `json:"thumbnail"` // Thumbnail URL
	Size      int64  `json:"size"`      // File size in bytes
	Modified  int64  `json:"modified"`  // Unix timestamp
	Width     int    `json:"width"`     // Image/video width in pixels
	Height    int    `json:"height"`    // Image/video height in pixels
}

// FolderItem represents a subdirectory
type FolderItem struct {
	Name string `json:"name"` // Folder name
	Path string `json:"path"` // Relative path from media root
}

// DirectoryResponse is what we return from /api/directory
type DirectoryResponse struct {
	CurrentPath string       `json:"currentPath"` // Current directory path
	Folders     []FolderItem `json:"folders"`     // Subdirectories
	Media       []MediaItem  `json:"media"`       // Media files
	Tags        []string     `json:"tags"`        // Tags from .tags file
}
