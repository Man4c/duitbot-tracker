# syntax=docker/dockerfile:1

# ---------- Stage 1: build (PHP + Node dalam satu lingkungan) ----------
# vite build memanggil `php artisan wayfinder:generate` (plugin Wayfinder), dan file
# hasilnya (resources/js/actions|routes|wayfinder) di-.gitignore → WAJIB di-generate
# saat build. Karena itu stage build butuh PHP + composer + Node sekaligus.
# PHP 8.4: composer.lock mengunci Symfony 8.1 yang butuh php >=8.4.1
# (walau composer.json menulis "php": "^8.3", dependensi terkunci menuntut 8.4+).
FROM php:8.4-cli-alpine AS build

# Composer dari image resmi.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node + npm + git (git dipakai beberapa tool build) dan ekstensi PHP untuk artisan.
RUN apk add --no-cache nodejs npm git
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql bcmath intl zip gd

WORKDIR /app

# 1) Dependency PHP dulu (butuh source penuh untuk package:discover → --no-scripts).
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --optimize-autoloader

# 2) Dependency Node.
COPY package.json package-lock.json ./
RUN npm ci

# 3) Source lengkap, lalu selesaikan langkah composer yang di-skip.
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && php artisan package:discover --ansi

# 4) Build aset. Plugin Wayfinder memanggil `php artisan wayfinder:generate` di sini —
#    berhasil karena PHP + artisan tersedia di stage ini.
RUN npm run build

# node_modules tak dibutuhkan di runtime (Laravel serve dari public/build) — buang
# agar COPY --from=build ke stage runtime tidak membawa ratusan MB sia-sia.
RUN rm -rf node_modules

# ---------- Stage 2: runtime PHP-FPM ----------
FROM php:8.4-fpm-alpine AS runtime

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    bcmath \
    intl \
    zip \
    gd \
    opcache

RUN apk add --no-cache nginx supervisor

WORKDIR /var/www/html

# Konfigurasi produksi
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# Source aplikasi + artefak build (vendor, aset Vite, file Wayfinder hasil generate)
# semuanya diambil dari stage build agar konsisten dan tak perlu build ulang.
COPY --from=build /app ./

# Izin direktori yang ditulis Laravel saat runtime
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Render mengirim traffic ke port ini (web service).
EXPOSE 8080

ENTRYPOINT ["entrypoint"]
# Default: web (nginx + php-fpm via supervisor). Worker & cron override via render.yaml.
CMD ["web"]
