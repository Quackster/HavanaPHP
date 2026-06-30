# HavanaPHP

Standalone Laravel port of `Havana-Web`.

This app is designed to run with the existing Havana database. It does not replace the Java Havana game server, and it does not create a new Laravel schema for Havana tables.

## Repository Layout

- `/opt/git/HavanaPHP`: Laravel/PHP port.
- `resources/legacy/www-tpl`: committed legacy Twig-style templates and locale file.
- `resources/legacy/figuredata/figuredata.xml`: committed 2009 Minerva-compatible figure data.
- `public/web-gallery`, `public/housekeeping`, and `public/public`: committed legacy public/static asset tree.
- `/opt/git/Havana`: optional legacy Java checkout, only needed for route parity audits.

Legacy templates are committed under `resources/legacy/www-tpl`.

## Requirements

- PHP `8.3` or newer.
- Composer.
- MariaDB or MySQL containing the existing Havana schema/data.
- PHP extensions:
  - `pdo_mysql`
  - `pdo_sqlite`
  - `gd`
  - `mbstring`
  - `xml`
  - `curl`
  - `zip` or `zlib`

## Local Setup

From `/opt/git/HavanaPHP`:

```bash
cp .env.example .env
php composer.phar install
php artisan key:generate
```

Edit `.env` for the local Havana database if needed. The legacy templates, static assets, locale file, and 2009 figuredata are committed in this repository, so the default paths normally do not need changing:

```dotenv
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=havana
DB_USERNAME=havana
DB_PASSWORD=goldfish

HAVANA_BASE_PATH=/opt/git/HavanaPHP/resources/legacy
HAVANA_TEMPLATE_PATH="${HAVANA_BASE_PATH}/www-tpl"
HAVANA_TEMPLATE_NAME=default
HAVANA_PUBLIC_PATH=/opt/git/HavanaPHP/public
HAVANA_HOUSEKEEPING_PATH=allseeingeye/hk
```

Do not run Laravel migrations against the live Havana database. HavanaPHP uses the existing Havana tables.

## Minerva Imaging

HavanaPHP proxies `/habbo-imaging/*` requests to Minerva when the legacy site setting `site.imaging.endpoint` is present. The repository includes the 2009 `figuredata.xml` at `resources/legacy/figuredata/figuredata.xml`; use that same file for Minerva when matching the bundled legacy web assets. Missing imaging settings are inserted automatically with these defaults:

```text
site.imaging.endpoint=http://localhost:5000
site.imaging.endpoint.timeout=5000
```

Minerva listens on `http://localhost:5000` by default. To run it locally, install the .NET 8 runtime and download the latest Linux build from `https://github.com/Quackster/Minerva/releases/tag/latest`:

```bash
mkdir -p /opt/git/Minerva
cd /opt/git/Minerva
curl -L -o Minerva-linux-x64.zip https://github.com/Quackster/Minerva/releases/download/latest/Minerva-linux-x64.zip
unzip Minerva-linux-x64.zip
chmod +x ./Minerva
cp /opt/git/HavanaPHP/resources/legacy/figuredata/figuredata.xml ./figuredata/figuredata.xml
./Minerva
```

For source-based development, clone Minerva with its submodules:

```bash
git clone --recursive https://github.com/Quackster/Minerva.git /opt/git/Minerva
```

Keep Minerva running beside HavanaPHP before loading pages that render avatars or badges. If you use a different Minerva URL, update `site.imaging.endpoint` in the Havana `settings` table or through housekeeping site configuration.

## Web Server Deployment

<details>
<summary>Apache deployment at <code>/var/www/html</code></summary>

If deploying this app directly into Apache's default document root on Fedora:

```bash
rsync -a --exclude='.git' /opt/git/HavanaPHP/ /var/www/html/
cd /var/www/html
cp public/index.php index.php
cp public/.htaccess .htaccess
perl -0pi -e "s#__DIR__.'/../storage#__DIR__.'/storage#g; s#__DIR__.'/../vendor#__DIR__.'/vendor#g; s#__DIR__.'/../bootstrap#__DIR__.'/bootstrap#g" index.php
```

Set `.env` for the existing Havana database, for example:

```dotenv
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=havana
DB_USERNAME=root
DB_PASSWORD=verysecret

HAVANA_BASE_PATH=/var/www/html/resources/legacy
HAVANA_TEMPLATE_PATH=/var/www/html/resources/legacy/www-tpl
HAVANA_PUBLIC_PATH=/var/www/html/public
```

Then initialize Laravel runtime state:

```bash
php artisan key:generate --force
php artisan optimize:clear
chmod -R a+rwX storage bootstrap/cache
```

On Fedora with SELinux enforcing, Unix permissions are not enough. If Apache returns a Laravel 500 such as `tempnam(): file created in the system's temporary directory`, label Laravel's writable directories for Apache:

```bash
chcon -R -t httpd_sys_rw_content_t /var/www/html/storage /var/www/html/bootstrap/cache
php artisan optimize:clear
```

If Apache/PHP-FPM returns a database error such as `SQLSTATE[HY000] [2002] Permission denied` while connecting to MariaDB on `127.0.0.1:3306`, SELinux is blocking the web process from opening a database network connection. Enable the database connection boolean as root:

```bash
setsebool -P httpd_can_network_connect_db on
getsebool httpd_can_network_connect_db
```

If the app also needs other outbound TCP connections from PHP, use the broader boolean:

```bash
setsebool -P httpd_can_network_connect on
```

If `/` works but pretty URLs such as `/allseeingeye/hk/login` return Apache 404s, Apache is not applying Laravel's `.htaccess` rewrite rules. Add this as root:

```apache
# /etc/httpd/conf.d/havanaphp.conf
<Directory "/var/www/html">
    Options FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

DirectoryIndex index.php index.html
```

Then reload Apache:

```bash
apachectl graceful
```

Without that Apache config, routes can still be reached through `index.php`, for example `/index.php/allseeingeye/hk/login`, but legacy pretty URLs will not work.

</details>

<details>
<summary>Nginx deployment at <code>/var/www/html</code></summary>

Nginx does not read `.htaccess`, so the Laravel rewrite rule must be in the Nginx server block. The preferred setup is to keep the app in `/var/www/html` and point Nginx at Laravel's `public/` directory:

```nginx
server {
    listen 80;
    server_name 127.0.0.1 localhost;
    root /var/www/html/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_pass unix:/run/php-fpm/www.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

If the site must be served directly from `/var/www/html` instead of `/var/www/html/public`, use the root `index.php` setup shown in the Apache section and point Nginx at `/var/www/html`:

```nginx
server {
    listen 80;
    server_name 127.0.0.1 localhost;
    root /var/www/html;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_pass unix:/run/php-fpm/www.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

On some systems the PHP-FPM socket is different. Check it with:

```bash
find /run -type s -name '*.sock' 2>/dev/null | grep -E 'php|php-fpm'
```

After changing the Nginx config:

```bash
nginx -t
systemctl reload nginx
```

Use the same `.env`, Laravel initialization, permissions, and SELinux writable labels from the Apache section. On Fedora with SELinux enforcing, Nginx uses the same `httpd_sys_rw_content_t` label for Laravel writable directories:

```bash
chcon -R -t httpd_sys_rw_content_t /var/www/html/storage /var/www/html/bootstrap/cache
php artisan optimize:clear
```

If Nginx/PHP-FPM returns a database error such as `SQLSTATE[HY000] [2002] Permission denied` while connecting to MariaDB on `127.0.0.1:3306`, SELinux is blocking the PHP-FPM process from opening a database network connection. Enable the database connection boolean as root:

```bash
setsebool -P httpd_can_network_connect_db on
getsebool httpd_can_network_connect_db
```

If the app also needs other outbound TCP connections from PHP, use the broader boolean:

```bash
setsebool -P httpd_can_network_connect on
```

If `/` works but `/allseeingeye/hk/login` returns a 404, the `try_files $uri $uri/ /index.php?$query_string;` line is missing or the server block is not the one serving the request.

</details>
