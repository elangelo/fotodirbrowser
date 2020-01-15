# Setup development environment

install dependencies
```bash
pacman -S lighttpd php xdebug php-cgi php-gd
```

start lighttpd server in the root directory of this project:
```bash
~/sources/fotodirbrowser $ lighttpd -D -f ./docs/debug/lighttpd.conf
```