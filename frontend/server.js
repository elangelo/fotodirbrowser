const express = require('express');
const path = require('path');
const fs = require('fs').promises;
const mime = require('mime-types');

const app = express();
const PORT = process.env.PORT || 3000;
const MEDIA_DIR = process.env.MEDIA_DIR || path.join(__dirname, '../docs/example');

// Middleware
app.use(express.json());
app.use(express.static('public'));

// Utility functions
const isImageFile = (filename) => {
  const ext = path.extname(filename).toLowerCase();
  return ['.jpg', '.jpeg', '.png', '.gif', '.webp'].includes(ext);
};

const isVideoFile = (filename) => {
  const ext = path.extname(filename).toLowerCase();
  return ['.mp4', '.webm', '.ogg', '.mov', '.avi'].includes(ext);
};

const getFileStats = async (filePath) => {
  try {
    const stats = await fs.stat(filePath);
    return {
      size: stats.size,
      creationTime: stats.birthtime.getTime(),
      modificationTime: stats.mtime.getTime(),
      isDirectory: stats.isDirectory()
    };
  } catch (error) {
    return null;
  }
};

// Routes

// Get directory contents
app.get('/api/directory', async (req, res) => {
  try {
    const requestedPath = req.query.path || '/';
    const fullPath = path.join(MEDIA_DIR, requestedPath);
    
    // Security check - ensure path is within MEDIA_DIR
    if (!fullPath.startsWith(path.resolve(MEDIA_DIR))) {
      return res.status(403).json({ error: 'Access denied' });
    }

    const items = await fs.readdir(fullPath);
    const mediaItems = [];
    const folders = [];

    for (const item of items) {
      const itemPath = path.join(fullPath, item);
      const stats = await getFileStats(itemPath);
      
      if (!stats) continue;

      const relativePath = path.join(requestedPath, item).replace(/\\/g, '/');
      
      if (stats.isDirectory) {
        // Check for tags file
        let tags = [];
        try {
          const tagsPath = path.join(itemPath, 'tags');
          const tagsContent = await fs.readFile(tagsPath, 'utf8');
          tags = tagsContent.split(';').filter(tag => tag.trim());
        } catch (error) {
          // No tags file or couldn't read it
        }

        folders.push({
          type: 'folder',
          name: item,
          relativePath,
          tags,
          stats
        });
      } else if (isImageFile(item) || isVideoFile(item)) {
        const mediaType = isImageFile(item) ? 'image' : 'video';
        
        mediaItems.push({
          type: mediaType,
          name: item,
          relativePath,
          extension: path.extname(item).toLowerCase(),
          stats
        });
      }
    }

    // Sort items by creation time (newest first)
    const sortByCreationTime = (a, b) => b.stats.creationTime - a.stats.creationTime;
    mediaItems.sort(sortByCreationTime);
    folders.sort((a, b) => a.name.localeCompare(b.name));

    res.json({
      currentPath: requestedPath,
      folders,
      media: mediaItems,
      totalItems: folders.length + mediaItems.length
    });

  } catch (error) {
    console.error('Error reading directory:', error);
    res.status(500).json({ error: 'Failed to read directory' });
  }
});

// Get breadcrumb navigation
app.get('/api/breadcrumb', async (req, res) => {
  try {
    const currentPath = req.query.path || '/';
    const parts = currentPath.split('/').filter(part => part);
    
    const breadcrumbs = [{ name: 'Home', path: '/' }];
    let buildPath = '';
    
    for (const part of parts) {
      buildPath += '/' + part;
      breadcrumbs.push({
        name: part,
        path: buildPath
      });
    }

    // Get sibling directories for previous/next navigation
    const parentPath = path.dirname(currentPath === '/' ? '/' : currentPath);
    const parentFullPath = path.join(MEDIA_DIR, parentPath);
    
    let siblings = [];
    let previousDir = null;
    let nextDir = null;
    
    try {
      const parentItems = await fs.readdir(parentFullPath);
      const directories = [];
      
      for (const item of parentItems) {
        const itemPath = path.join(parentFullPath, item);
        const stats = await getFileStats(itemPath);
        if (stats && stats.isDirectory) {
          const siblingPath = parentPath === '/' ? '/' + item : parentPath + '/' + item;
          directories.push(siblingPath);
        }
      }
      
      directories.sort();
      const currentIndex = directories.indexOf(currentPath);
      
      if (currentIndex > 0) {
        previousDir = directories[currentIndex - 1];
      }
      if (currentIndex < directories.length - 1) {
        nextDir = directories[currentIndex + 1];
      }
    } catch (error) {
      // Parent directory doesn't exist or can't be read
    }

    res.json({
      breadcrumbs,
      navigation: {
        previous: previousDir,
        next: nextDir
      }
    });

  } catch (error) {
    console.error('Error generating breadcrumb:', error);
    res.status(500).json({ error: 'Failed to generate breadcrumb' });
  }
});

// Serve media files with thumbnail support
app.get('/api/media/*', async (req, res) => {
  try {
    const mediaPath = req.params[0];
    const fullPath = path.join(MEDIA_DIR, mediaPath);
    const size = req.query.size || 'original';
    
    // Security check
    if (!fullPath.startsWith(path.resolve(MEDIA_DIR))) {
      return res.status(403).json({ error: 'Access denied' });
    }

    const stats = await getFileStats(fullPath);
    if (!stats || stats.isDirectory) {
      return res.status(404).json({ error: 'File not found' });
    }

    const mimeType = mime.lookup(fullPath);
    
    // For now, serve original files (thumbnail generation can be added later)
    res.setHeader('Content-Type', mimeType);
    res.setHeader('Cache-Control', 'public, max-age=31536000');
    
    const fileBuffer = await fs.readFile(fullPath);
    res.send(fileBuffer);

  } catch (error) {
    console.error('Error serving media:', error);
    res.status(500).json({ error: 'Failed to serve media file' });
  }
});

// Delete media file
app.delete('/api/media/*', async (req, res) => {
  try {
    const mediaPath = req.params[0];
    const fullPath = path.join(MEDIA_DIR, mediaPath);
    
    // Security check
    if (!fullPath.startsWith(path.resolve(MEDIA_DIR))) {
      return res.status(403).json({ error: 'Access denied' });
    }

    await fs.unlink(fullPath);
    res.json({ success: true, message: 'File deleted successfully' });

  } catch (error) {
    console.error('Error deleting file:', error);
    res.status(500).json({ error: 'Failed to delete file' });
  }
});

// Serve main application
app.get('*', (req, res) => {
  res.sendFile(path.join(__dirname, 'public', 'index.html'));
});

// Start server
app.listen(PORT, () => {
  console.log(`Photo browser server running on http://localhost:${PORT}`);
  console.log(`Media directory: ${MEDIA_DIR}`);
});

module.exports = app;
