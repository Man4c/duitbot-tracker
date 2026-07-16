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
        # Free-tier: tak ada preDeployCommand berbayar, jadi migrasi dijalankan saat
        # start. Idempotent (migrasi yang sudah jalan di-skip). Free tier = 1 instance,
        # jadi tak ada risiko migrasi paralel.
        php artisan migrate --force
        # Direktori runtime nginx (pid) + jalankan nginx & php-fpm via supervisor.
        mkdir -p /run/nginx
        exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
        ;;
    worker)
        # (Tak dipakai di Render free-tier — queue=sync. Berguna untuk dev/docker lokal
        # atau bila nanti upgrade ke paid plan dengan worker terpisah.)
        exec php artisan queue:work --tries=3 --timeout=30 --sleep=3 --max-time=3600
        ;;
    scheduler)
        # (Tak dipakai di Render free-tier — scheduler dipicu via endpoint /cron/run.
        # Berguna untuk paid plan dengan Render cron / dev lokal.)
        exec php artisan schedule:run --verbose --no-interaction
        ;;
    *)
        # Peran tak dikenal: jalankan apa adanya (mis. `sh`, `php artisan ...`).
        exec "$@"
        ;;
esac
