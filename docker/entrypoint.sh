#!/bin/sh
set -e

role="${1:-web}"

# Cache konfigurasi & view untuk performa (hanya proses long-running: web/worker).
# Scheduler (cron) jalan tiap menit → tak perlu re-cache berulang, hanya menambah latensi.
# route:cache SENGAJA tidak dipakai: routes/web.php memakai closure routes
# (mis. `fn () => inertia(...)`) yang tak bisa diserialisasi → route:cache akan gagal.
if [ "$role" = "web" ] || [ "$role" = "worker" ]; then
    php artisan config:cache
    php artisan view:cache
fi

case "$role" in
    web)
        # Direktori runtime nginx (pid) + jalankan nginx & php-fpm via supervisor.
        mkdir -p /run/nginx
        exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
        ;;
    worker)
        # Queue worker. --max-time membatasi umur proses agar memory fresh; Render me-restart.
        exec php artisan queue:work --tries=3 --timeout=30 --sleep=3 --max-time=3600
        ;;
    scheduler)
        # Dipanggil Render cron tiap menit: jalankan due tasks lalu keluar.
        exec php artisan schedule:run --verbose --no-interaction
        ;;
    *)
        # Peran tak dikenal: jalankan apa adanya (mis. `sh`, `php artisan ...`).
        exec "$@"
        ;;
esac
