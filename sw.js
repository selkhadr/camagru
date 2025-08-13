// Service Worker for Camagru PWA capabilities
const CACHE_NAME = 'camagru-v1';
const urlsToCache = [
    './',
    './css/style.css',
    './js/app.js',
    './js/auth.js',
    './js/webcam.js',
    './js/gallery.js',
    './js/utils.js',
    './images/overlays/',
    'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap'
];

// Install event - cache resources
self.addEventListener('install', (event) => {
    console.log('Service Worker installing...');
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('Opened cache');
                return cache.addAll(urlsToCache);
            })
            .catch((error) => {
                console.error('Failed to cache resources:', error);
            })
    );
});

// Activate event - clean up old caches
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
});

// Fetch event - serve from cache with network fallback
self.addEventListener('fetch', (event) => {
    // Skip non-GET requests
    if (event.request.method !== 'GET') {
        return;
    }
    
    // Skip API requests (let them go to network)
    if (event.request.url.includes('/api/')) {
        return fetch(event.request);
    }
    
    event.respondWith(
        caches.match(event.request)
            .then((response) => {
                // Return cached version if available
                if (response) {
                    return response;
                }
                
                // Otherwise fetch from network
                return fetch(event.request)
                    .then((response) => {
                        // Don't cache if not a valid response
                        if (!response || response.status !== 200 || response.type !== 'basic') {
                            return response;
                        }
                        
                        // Clone the response
                        const responseToCache = response.clone();
                        
                        // Add to cache
                        caches.open(CACHE_NAME)
                            .then((cache) => {
                                cache.put(event.request, responseToCache);
                            });
                        
                        return response;
                    })
                    .catch(() => {
                        // Return offline page for navigation requests
                        if (event.request.mode === 'navigate') {
                            return caches.match('./offline.html');
                        }
                    });
            })
    );
});

// Background sync for offline functionality
self.addEventListener('sync', (event) => {
    if (event.tag === 'upload-image') {
        event.waitUntil(
            // Handle offline image uploads
            handleOfflineUploads()
        );
    }
});

// Push notifications
self.addEventListener('push', (event) => {
    if (event.data) {
        const data = event.data.json();
        
        const options = {
            body: data.body,
            icon: './images/icon-192x192.png',
            badge: './images/badge-72x72.png',
            vibrate: [100, 50, 100],
            data: data.data || {},
            actions: [
                {
                    action: 'view',
                    title: 'View',
                    icon: './images/view-icon.png'
                },
                {
                    action: 'close',
                    title: 'Close',
                    icon: './images/close-icon.png'
                }
            ]
        };
        
        event.waitUntil(
            self.registration.showNotification(data.title, options)
        );
    }
});

// Handle notification clicks
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    
    if (event.action === 'view') {
        event.waitUntil(
            clients.openWindow('./')
        );
    }
});

// Helper function to handle offline uploads
async function handleOfflineUploads() {
    try {
        const cache = await caches.open('camagru-offline-uploads');
        const requests = await cache.keys();
        
        for (const request of requests) {
            try {
                const response = await cache.match(request);
                const data = await response.json();
                
                // Attempt to upload
                const uploadResponse = await fetch('./api/upload.php', {
                    method: 'POST',
                    body: data.formData
                });
                
                if (uploadResponse.ok) {
                    // Upload successful, remove from cache
                    await cache.delete(request);
                    
                    // Notify user
                    self.registration.showNotification('Upload Complete', {
                        body: 'Your photo has been uploaded successfully!',
                        icon: './images/icon-192x192.png'
                    });
                }
            } catch (error) {
                console.error('Failed to upload cached request:', error);
            }
        }
    } catch (error) {
        console.error('Error handling offline uploads:', error);
    }
}