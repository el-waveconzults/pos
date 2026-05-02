// Service Worker for POS System Offline Support
const CACHE_NAME = 'pos-v1.0.0';
const OFFLINE_URL = '/offline.html';

// Files to cache for offline use
const STATIC_CACHE_URLS = [
  '/',
  '/index.php',
  '/login.php',
  '/manifest.json',
  '/offline.html',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
  'https://code.jquery.com/jquery-3.6.0.min.js',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
  'https://cdn.jsdelivr.net/npm/chart.js'
];

// Install event - cache static assets
self.addEventListener('install', (event) => {
  console.log('Service Worker installing.');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('Caching static assets');
        return cache.addAll(STATIC_CACHE_URLS);
      })
      .catch((error) => {
        console.error('Failed to cache static assets:', error);
      })
  );
  self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
  console.log('Service Worker activating.');
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

// Fetch event - serve from cache when offline
self.addEventListener('fetch', (event) => {
  // Handle API requests differently
  if (event.request.url.includes('/api/') || event.request.url.includes('config/')) {
    event.respondWith(
      fetch(event.request)
        .catch(() => {
          // Return offline response for API calls
          return new Response(JSON.stringify({
            error: 'Offline',
            message: 'This feature requires an internet connection'
          }), {
            headers: { 'Content-Type': 'application/json' }
          });
        })
    );
    return;
  }

  // For other requests, try network first, then cache
  event.respondWith(
    fetch(event.request)
      .then((response) => {
        // Cache successful responses
        if (response.status === 200) {
          const responseClone = response.clone();
          caches.open(CACHE_NAME)
            .then((cache) => {
              cache.put(event.request, responseClone);
            });
        }
        return response;
      })
      .catch(() => {
        // Return cached version or offline page
        return caches.match(event.request)
          .then((cachedResponse) => {
            if (cachedResponse) {
              return cachedResponse;
            }
            // Return offline page for navigation requests
            if (event.request.mode === 'navigate') {
              return caches.match(OFFLINE_URL);
            }
            return new Response('', { status: 404 });
          });
      })
  );
});

// Background sync for offline transactions
self.addEventListener('sync', (event) => {
  console.log('Background sync triggered:', event.tag);

  if (event.tag === 'sync-sales') {
    event.waitUntil(syncOfflineSales());
  }

  if (event.tag === 'sync-products') {
    event.waitUntil(syncOfflineProducts());
  }
});

// Push notifications (optional)
self.addEventListener('push', (event) => {
  if (event.data) {
    const data = event.data.json();
    const options = {
      body: data.body,
      icon: '/icon-192x192.png',
      badge: '/icon-192x192.png',
      vibrate: [100, 50, 100],
      data: data.data
    };

    event.waitUntil(
      self.registration.showNotification(data.title, options)
    );
  }
});

// Sync offline sales data
async function syncOfflineSales() {
  try {
    const db = await openIndexedDB();
    const sales = await getOfflineSales(db);

    for (const sale of sales) {
      try {
        const response = await fetch('/api/sync-sale.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(sale)
        });

        if (response.ok) {
          await removeOfflineSale(db, sale.id);
        }
      } catch (error) {
        console.error('Failed to sync sale:', sale.id, error);
      }
    }
  } catch (error) {
    console.error('Sync failed:', error);
  }
}

// Sync offline product updates
async function syncOfflineProducts() {
  try {
    const db = await openIndexedDB();
    const products = await getOfflineProducts(db);

    for (const product of products) {
      try {
        const response = await fetch('/api/sync-product.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(product)
        });

        if (response.ok) {
          await removeOfflineProduct(db, product.id);
        }
      } catch (error) {
        console.error('Failed to sync product:', product.id, error);
      }
    }
  } catch (error) {
    console.error('Product sync failed:', error);
  }
}

// IndexedDB helper functions
function openIndexedDB() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('pos-offline', 1);

    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve(request.result);

    request.onupgradeneeded = (event) => {
      const db = event.target.result;

      // Sales store
      if (!db.objectStoreNames.contains('sales')) {
        const salesStore = db.createObjectStore('sales', { keyPath: 'id' });
        salesStore.createIndex('timestamp', 'timestamp', { unique: false });
      }

      // Products store
      if (!db.objectStoreNames.contains('products')) {
        const productsStore = db.createObjectStore('products', { keyPath: 'id' });
        productsStore.createIndex('timestamp', 'timestamp', { unique: false });
      }

      // Customers store
      if (!db.objectStoreNames.contains('customers')) {
        db.createObjectStore('customers', { keyPath: 'id' });
      }
    };
  });
}

async function getOfflineSales(db) {
  return new Promise((resolve, reject) => {
    const transaction = db.transaction(['sales'], 'readonly');
    const store = transaction.objectStore('sales');
    const request = store.getAll();

    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve(request.result);
  });
}

async function removeOfflineSale(db, id) {
  return new Promise((resolve, reject) => {
    const transaction = db.transaction(['sales'], 'readwrite');
    const store = transaction.objectStore('sales');
    const request = store.delete(id);

    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve(request.result);
  });
}

async function getOfflineProducts(db) {
  return new Promise((resolve, reject) => {
    const transaction = db.transaction(['products'], 'readonly');
    const store = transaction.objectStore('products');
    const request = store.getAll();

    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve(request.result);
  });
}

async function removeOfflineProduct(db, id) {
  return new Promise((resolve, reject) => {
    const transaction = db.transaction(['products'], 'readwrite');
    const store = transaction.objectStore('products');
    const request = store.delete(id);

    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve(request.result);
  });
}