# The php-fpm image is built on Alpine Linux

The php-fpm image was Debian-based and 3.05 GB on disk. The VPS has a 20 GB SSD,
so the image was one of the largest consumers. We rebuilt it on
`php:8.4-fpm-alpine` (about 1.4 GB, roughly half the size) and kept the runtime
tools: ffmpeg, Chromium (yt-dlp cookies), aspell (the grammar check), supervisor
and yt-dlp (via pip, so `make yt-dlp-update` still gets the latest version).

The Docker PHP and Alpine's `php84-*` packages are the same PHP 8.4.27, so the
image installs the Alpine extension packages and copies their modules into the
Docker PHP extension directory instead of compiling them. This avoids the PECL
network (`pecl.php.net` timed out during the build) and the build toolchain, and
keeps the image small. `igbinary` and `msgpack` are enabled before `redis`
because the Alpine redis build references `igbinary_serialize` and
`php_msgpack_serialize`; `musl-locales` provides the `locale` command that
aspell/peck expects. `zip` is not copied: the Alpine
build links against a pcre2 symbol the Docker PHP does not export, and Composer
falls back to the `unzip` binary, which is installed. `mbstring` is already
built into the base image, and the non-PDO `pgsql` extension is not needed
because the application uses `pdo_pgsql`.

## Considered Options

- **Debian slim / multi-stage** — safe, but still around 1.5 GB and keeps the
  Debian package split.
- **Compile the extensions on Alpine with PECL** — the canonical path, but the
  PECL download timed out and the `intl` compile alone took about 18 minutes.
- **Use the Alpine PHP extension packages (chosen)** — fast, no PECL, same PHP
  8.4.27, about half the image size; the trade-off is that the Docker PHP and
  the Alpine packages must stay on the same 8.4.x.

## Consequences

- The image must keep `php:8.4-fpm-alpine` and the `php84-*` package versions on
  the same 8.4.x; a PHP bump means bumping both.
- `zip` is absent; Composer uses `unzip`. `mbstring` comes from the base image;
  the non-PDO `pgsql` extension is not installed.
- The supervisor config includes `/etc/supervisor/conf.d/*.conf`, matching the
  compose mount; `www-data` is uid/gid 82 on Alpine and the entrypoint chowns
  the writable directories.
- Chromium, ffmpeg and the cookie profile work on Alpine; the full PHPUnit suite
  in CI runs in this image and is the ABI check.
