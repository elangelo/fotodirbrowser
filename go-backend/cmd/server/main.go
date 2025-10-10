package main

// Import statements (like C# using)
import (
	"fmt"           // Formatted I/O (like Console.WriteLine)
	"log"           // Logging
	"net/http"      // HTTP server/client
	"os"            // OS operations
	"path/filepath" // File path operations

	"github.com/go-chi/chi/v5"            // Router
	"github.com/go-chi/chi/v5/middleware" // Middleware
	"github.com/go-chi/cors"              // CORS handling

	"fotodirbrowser/internal/api" // Import our API package (adjust module name as needed)
)

// main() is the entry point (like C# Main())
func main() {
	// Get configuration from environment variables
	// os.Getenv() returns empty string if not set
	port := os.Getenv("PORT")
	if port == "" {
		port = "3000" // Default value
	}

	mediaDir := os.Getenv("MEDIA_DIR")
	if mediaDir == "" {
		mediaDir = "../docs/example"
	}

	// Get absolute path for media directory
	absMediaDir, err := filepath.Abs(mediaDir)
	if err != nil {
		log.Fatal("Invalid media directory:", err)
	}

	// Verify media directory exists
	if _, err := os.Stat(absMediaDir); os.IsNotExist(err) {
		log.Fatal("Media directory does not exist:", absMediaDir)
	}

	fmt.Printf("Starting server on port %s\n", port)
	fmt.Printf("Media directory: %s\n", absMediaDir)

	// Create router (like ASP.NET routing)
	router := setupRouter(absMediaDir)

	// Start HTTP server (blocking call)
	err = http.ListenAndServe(":"+port, router)
	if err != nil {
		// log.Fatal prints error and exits (like throw)
		log.Fatal("Server failed to start:", err)
	}
}

// setupRouter creates and configures the HTTP router
// This is like Startup.cs ConfigureServices in ASP.NET
func setupRouter(mediaDir string) *chi.Mux {
	// Create new chi router
	r := chi.NewRouter()

	// Add middleware (like ASP.NET middleware)
	r.Use(middleware.Logger)    // Log all requests
	r.Use(middleware.Recoverer) // Recover from panics

	// Configure CORS (like app.UseCors in ASP.NET)
	r.Use(cors.Handler(cors.Options{
		AllowedOrigins:   []string{"*"},
		AllowedMethods:   []string{"GET", "POST", "DELETE", "OPTIONS"},
		AllowedHeaders:   []string{"*"},
		AllowCredentials: true,
	}))

	// Define routes (like app.MapGet in ASP.NET)
	r.Get("/health", healthCheckHandler)

	// NEW: Register the directory API handler
	dirHandler := api.NewDirectoryHandler(mediaDir)
	r.Handle("/api/directory", dirHandler)

	mediaFileServer := http.FileServer(http.Dir(mediaDir))
	r.Handle("/api/media/*", http.StripPrefix("/api/media", mediaFileServer))

	frontendDistDir := "../frontend/dist"
	// Check if dist directory exists
	if _, err := os.Stat(frontendDistDir); os.IsNotExist(err) {
		log.Printf("WARNING: Frontend dist directory not found at %s", frontendDistDir)
		log.Printf("Run 'cd ../frontend && npm run build' to build the frontend")
	} else {
		fmt.Printf("Serving frontend from: %s\n", frontendDistDir)
	}

	frontendServer := http.FileServer(http.Dir(frontendDistDir))
	r.Handle("/*", frontendServer)
	// Return configured router
	return r
}

// HTTP handler function
// w = response writer (like HttpResponse)
// r = request (like HttpRequest)
func healthCheckHandler(w http.ResponseWriter, r *http.Request) {
	// Set response header
	w.Header().Set("Content-Type", "application/json")

	// Write response body
	// fmt.Fprintf writes formatted string to w
	fmt.Fprintf(w, `{"status": "ok", "message": "Server is running"}`)
}
