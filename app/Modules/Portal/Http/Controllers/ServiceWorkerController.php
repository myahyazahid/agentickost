<?php

namespace App\Modules\Portal\Http\Controllers;

use Illuminate\Http\Response;

/**
 * The portal's service worker, served from inside the portal path so its
 * scope is this tenant's portal (FR-PRT-06). It keeps only the offline
 * page: bills and payments are private and always come from the server.
 */
final class ServiceWorkerController
{
    public function __invoke(): Response
    {
        $offline = json_encode(route('portal.offline', absolute: false), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $script = <<<JS
            const CACHE = 'portal-offline-v1';
            const OFFLINE_URL = {$offline};

            self.addEventListener('install', (event) => {
                event.waitUntil(caches.open(CACHE).then((cache) => cache.add(new Request(OFFLINE_URL, { cache: 'reload' }))));
                self.skipWaiting();
            });

            self.addEventListener('activate', (event) => {
                event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key)))));
                self.clients.claim();
            });

            self.addEventListener('fetch', (event) => {
                if (event.request.mode !== 'navigate') {
                    return;
                }

                event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE_URL)));
            });
            JS;

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
