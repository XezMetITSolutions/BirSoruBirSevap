// Service Worker for Bir Soru Bir Sevap PWA

const CACHE_NAME = 'bir-soru-bir-sevap-v2';
const urlsToCache = [
    '/',
    '/manifest.json',
    '/logo.png',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap'
];

// Install event - cache dosyaları
self.addEventListener('install', (event) => {
    console.log('Service Worker installing...');
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('Opened cache');
                return cache.addAll(urlsToCache);
            })
            .catch((error) => {
                console.error('Cache add error:', error);
            })
    );
    self.skipWaiting();
});

// Activate event - eski cache'leri temizle
self.addEventListener('activate', (event) => {
    console.log('Service Worker activating...');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

// Fetch event - offline desteği
self.addEventListener('fetch', (event) => {
    // Sadece GET istekleri
    if (event.request.method !== 'GET') {
        return;
    }

    const reqUrl = new URL(event.request.url);
    // chrome-extension://, file:// vb. şemaları ve üçüncü taraf istekleri cache'leme
    const isHttp = reqUrl.protocol === 'http:' || reqUrl.protocol === 'https:';
    const isSameOrigin = reqUrl.origin === self.location.origin;
    if (!isHttp) {
        return;
    }

    // Dinamik PHP içerikleri ve yönetim panelleri cache'lenmesin (her zaman güncel veri)
    const isDynamicContent =
        reqUrl.pathname.endsWith('.php') ||
        reqUrl.pathname.includes('/admin/') ||
        reqUrl.pathname.includes('/teacher/') ||
        reqUrl.pathname.includes('/student/');

    if (isDynamicContent) {
        return;
    }

    event.respondWith(
        caches.match(event.request)
            .then((response) => {
                // Cache'de varsa return et
                if (response) {
                    return response;
                }

                // Cache'de yoksa fetch et
                return fetch(event.request).then((response) => {
                    // Geçersiz response kontrolü
                    if (!response || response.status !== 200 || response.type !== 'basic') {
                        return response;
                    }

                    // Response'u klonla (stream sadece bir kez okunabilir)
                    const responseToCache = response.clone();

                    // Sadece aynı origin istekleri cache'e yaz
                    if (isSameOrigin) {
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(event.request, responseToCache);
                        });
                    }

                    return response;
                }).catch(() => {
                    // Offline durumunda veya fetch hatası
                    console.log('Fetch failed, serving from cache or offline page');
                    
                    // Offline sayfası göster (eğer varsa)
                    if (event.request.mode === 'navigate') {
                        return caches.match('/offline.html') || 
                               new Response('Internet bağlantınızı kontrol edin.', {
                                   headers: { 'Content-Type': 'text/html' }
                               });
                    }
                });
            })
    );
});

// Push notification desteği (ileride eklenebilir)
self.addEventListener('push', (event) => {
    console.log('Push notification received:', event);
    // Notification gösterimi buraya eklenecek
});

// Notification click handler
self.addEventListener('notificationclick', (event) => {
    console.log('Notification clicked:', event);
    event.notification.close();
    event.waitUntil(
        clients.openWindow('/')
    );
});

