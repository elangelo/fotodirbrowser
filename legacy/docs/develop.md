# Setup development environment

install dependencies
```bash
pacman -S lighttpd php xdebug php-cgi php-gd
```

fix configuration:
- `includes.inc` contains some directories that need to be configured,
- `docs/debug/lighttpd.conf` contains start path of site.

start lighttpd server in the root directory of this project:
```bash
~/sources/fotodirbrowser $ lighttpd -D -f ./docs/debug/lighttpd.conf
```