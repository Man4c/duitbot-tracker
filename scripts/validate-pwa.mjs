import { readFile, stat } from 'node:fs/promises';
import { resolve } from 'node:path';

const root = resolve(import.meta.dirname, '..');
const manifestPath = resolve(root, 'public/build/manifest.webmanifest');
const manifest = JSON.parse(await readFile(manifestPath, 'utf8'));

const failures = [];
const required = [
    ['/icons/icon-192.png', '192x192', 'any'],
    ['/icons/icon-512.png', '512x512', 'any'],
    ['/icons/icon-maskable-512.png', '512x512', 'maskable'],
];

for (const [src, sizes, purpose] of required) {
    const icon = manifest.icons?.find((item) => item.src === src);

    if (!icon || icon.sizes !== sizes || icon.purpose !== purpose) {
        failures.push(`Manifest icon ${src} tidak valid.`);
        continue;
    }

    const file = resolve(root, 'public', src.slice(1));
    const buffer = await readFile(file);
    const width = buffer.readUInt32BE(16);
    const height = buffer.readUInt32BE(20);

    if (`${width}x${height}` !== sizes) {
        failures.push(`Dimensi aktual ${src} adalah ${width}x${height}.`);
    }
}

for (const key of ['name', 'short_name', 'start_url', 'scope', 'display', 'theme_color']) {
    if (!manifest[key]) {
failures.push(`Manifest tidak memiliki ${key}.`);
}
}

const sw = await readFile(resolve(root, 'public/build/sw.js'), 'utf8');

if (!sw.includes('offline.html')) {
failures.push('Service worker tidak memuat fallback offline.');
}

await stat(resolve(root, 'public/offline.html')).catch(() => failures.push('offline.html tidak tersedia.'));

if (failures.length) {
    console.error(failures.join('\n'));
    process.exit(1);
}

console.log('PWA valid: manifest, ikon 192/512, maskable icon, service worker, dan fallback offline tersedia.');
