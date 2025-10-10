# Photo Browser - Modern Node.js Edition

A modern, responsive photo and video browser built with vanilla JavaScript and Node.js. This is a complete rewrite of the original PHP version, maintaining all core functionality while modernizing the technology stack.

## Features

### 🖼️ Media Browsing
- **Responsive Mosaic Layout**: Dynamic grid that adapts to different screen sizes
- **Smart Thumbnail Generation**: Automatic thumbnails for photos and videos
- **Directory Navigation**: Hierarchical folder browsing with breadcrumbs
- **Tag Support**: Visual tag clouds for organized content

### 🔍 User Interface
- **Modal Lightbox**: Full-screen media viewing with slideshow
- **Keyboard Navigation**: Complete keyboard shortcuts for power users
- **Touch/Swipe Support**: Mobile-friendly gesture navigation
- **Side Panel Actions**: File operations (download, delete, info)

### 🎯 Modern Features
- **Vanilla JavaScript**: No framework dependencies, modern ES6+ modules
- **Responsive Design**: Works seamlessly on desktop, tablet, and mobile
- **RESTful API**: Clean separation between frontend and backend
- **Progressive Enhancement**: Works with or without JavaScript

## Technology Stack

### Backend
- **Node.js**: Server runtime
- **Express.js**: Web application framework
- **File System APIs**: Direct file system access (no database required)

### Frontend
- **Vanilla JavaScript**: Modern ES6+ modules
- **CSS Grid/Flexbox**: Responsive layouts
- **HTML5**: Semantic markup
- **Web APIs**: Modern browser features

## Quick Start

### Prerequisites
- Node.js 18+ 
- npm or yarn

### Installation

1. **Clone and install dependencies:**
   ```bash
   git clone <repository-url>
   cd ng
   npm install
   ```

2. **Set environment variables:**
   ```bash
   export MEDIA_DIR="/path/to/your/photos"
   export PORT=3000
   ```

3. **Start the server:**
   ```bash
   npm start
   ```

4. **Open your browser:**
   ```
   http://localhost:3000
   ```

### Development Mode

For development with auto-restart:
```bash
npm run dev
```

## Configuration

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `MEDIA_DIR` | Path to your media directory | `../docs/example` |
| `PORT` | Server port | `3000` |
| `NODE_ENV` | Environment (development/production) | `development` |

### Media Directory Structure

The application expects a directory structure like:
```
media/
├── 2023/
│   ├── 2023-01-01/
│   │   ├── photo1.jpg
│   │   ├── video1.mp4
│   │   └── tags (optional)
│   └── 2023-02-15/
└── 2024/
```

### Tag Files

Create a `tags` file in any directory with semicolon-separated tags:
```
vacation;beach;summer;family
```

## API Endpoints

### Directory Operations
- `GET /api/directory?path=/path` - Get directory contents
- `GET /api/breadcrumb?path=/path` - Get navigation breadcrumbs
- `POST /api/directory` - Create new directory

### Media Operations
- `GET /api/media/*` - Serve media files (with optional `?size=` parameter)
- `DELETE /api/media/*` - Delete media file
- `GET /api/info/*` - Get media file information

### Utility
- `GET /api/search?q=query` - Search media files
- `POST /api/upload` - Upload files

## Keyboard Shortcuts

### Modal Navigation
- `←/→` - Navigate between media
- `Space` - Play/pause videos
- `Esc` - Close modal
- `I` - Show file information

### Directory Navigation
- `Alt + ←/→` - Previous/next directory
- `Alt + ↑` - Parent directory
- `Alt + Home` - Home directory

### File Operations
- `Delete` - Delete current file
- `Ctrl + S` - Download current file

### Gallery
- `1/2/3` - Switch layout modes
- `Home/End` - Select first/last item
- `F1` or `?` - Show help

## Architecture

### Frontend Modules

```
js/
├── main.js           # Application entry point
├── api.js            # Server communication
├── gallery.js        # Media grid rendering
├── breadcrumb.js     # Navigation breadcrumbs
├── modal.js          # Lightbox/slideshow
├── sidebar.js        # File actions panel
├── mosaic.js         # Layout management
├── keyboard.js       # Keyboard shortcuts
└── loading.js        # Loading states
```

### CSS Modules

```
css/
├── main.css          # Base styles and layout
├── breadcrumb.css    # Navigation styling
├── mosaic.css        # Grid layouts
├── modal.css         # Lightbox styling
└── sidebar.css       # Side panel styling
```

## Browser Support

- **Modern Browsers**: Chrome 88+, Firefox 85+, Safari 14+, Edge 88+
- **Features Used**: ES6 Modules, CSS Grid, Fetch API, ResizeObserver
- **Mobile**: iOS Safari 14+, Chrome Mobile 88+

## Performance

### Optimizations
- **Lazy Loading**: Images load as they come into view
- **Thumbnail Caching**: Server-side thumbnail generation and caching
- **Efficient Layouts**: CSS Grid and Flexbox for optimal rendering
- **Minimal Dependencies**: No heavy frameworks or libraries

### Recommended Limits
- **Directory Size**: Up to 1000 items per directory
- **Image Size**: Up to 50MB per file
- **Video Size**: Up to 500MB per file

## Customization

### Themes
Modify CSS custom properties in `css/main.css`:
```css
:root {
  --primary-color: #4CAF50;
  --background-color: #000;
  --text-color: #fff;
}
```

### Layout Options
- Grid layout: Uniform grid
- Masonry layout: Pinterest-style
- Flex layout: Responsive mosaic

### Adding Features
The modular architecture makes it easy to add new features:
1. Create new module in `js/`
2. Import in `main.js`
3. Add corresponding CSS if needed

## Migration from PHP Version

This Node.js version maintains feature parity with the original PHP application:

### ✅ Preserved Features
- Directory browsing and navigation
- Mosaic grid layouts
- Modal lightbox with slideshow
- Tag management system
- File operations (download, delete)
- Breadcrumb navigation
- Keyboard shortcuts

### 🚀 New Features
- Modern responsive design
- Touch/gesture support
- Progressive web app capabilities
- Better performance and caching
- Modular architecture

### 📝 Migration Steps
1. Copy your media files to the new directory structure
2. Update any existing `tags` files (format remains the same)
3. Configure `MEDIA_DIR` environment variable
4. Start the new Node.js server

## Troubleshooting

### Common Issues

**Server won't start:**
```bash
# Check Node.js version
node --version  # Should be 18+

# Check permissions
ls -la /path/to/media/directory
```

**Images not loading:**
- Verify `MEDIA_DIR` path is correct
- Check file permissions
- Ensure supported formats (jpg, png, mp4, webm)

**Layout issues:**
- Clear browser cache
- Check console for JavaScript errors
- Verify CSS files are loading

### Debug Mode
```bash
NODE_ENV=development npm start
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## License

MIT License - see LICENSE file for details.

## Acknowledgments

- Original PHP version inspiration
- Modern web standards and best practices
- Open source community contributions
