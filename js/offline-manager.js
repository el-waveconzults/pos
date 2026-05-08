// Offline Support Utilities for POS System
class POSOfflineManager {
    constructor() {
        this.dbName = 'pos-offline';
        this.dbVersion = 4;
        this.isOnline = navigator.onLine;
        this.db = null;
        this.ready = false;
        this.initPromise = this.init();
    }

    async init() {
        try {
            console.log('Offline manager: Starting initialization...');
            await this.openDB();
            
            // If online and there are pending sales older than 1 hour, clear them (likely test data)
            if (this.isOnline) {
                const sales = await this.getOfflineSales();
                const oneHourAgo = Date.now() - (60 * 60 * 1000);
                for (const sale of sales) {
                    const saleTime = new Date(sale.timestamp).getTime();
                    if (saleTime < oneHourAgo && !sale.synced) {
                        console.log('Clearing stale offline sale:', sale.id);
                        await this.deleteData('sales', sale.id);
                    }
                }
                // If still online after 2 seconds, sync any remaining pending data
                setTimeout(() => {
                    if (this.isOnline) {
                        this.syncAllData().catch(e => console.warn('Sync error:', e));
                    }
                }, 2000);
            }
            
            if ('serviceWorker' in navigator) {
                let basePath = window.location.pathname.replace(/\/pages\/.*$/, '');
                if (basePath === window.location.pathname) {
                    basePath = window.location.pathname.replace(/\/[^\/]*$/, '');
                }
                if (!basePath) {
                    basePath = '/';
                }
                const scope = basePath.endsWith('/') ? basePath : `${basePath}/`;
                const swUrl = `${basePath.replace(/\/$/, '')}/sw.js`;
                navigator.serviceWorker.register(swUrl, { scope }).catch((error) => {
                    console.warn('Service worker registration failed:', error);
                });
            }
            window.addEventListener('online', () => this.handleOnline());
            window.addEventListener('offline', () => this.handleOffline());
            this.ready = true;
            console.log('Offline manager: Initialization complete, ready =', this.ready);
        } catch (error) {
            console.error('Offline manager: Initialization failed:', error);
            this.ready = false;
        }
    }

    async openDB() {
        return new Promise((resolve, reject) => {
            if (!('indexedDB' in window)) {
                reject(new Error('IndexedDB not supported'));
                return;
            }
            const request = indexedDB.open(this.dbName, this.dbVersion);
            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                this.db = request.result;
                resolve(this.db);
            };
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                if (!db.objectStoreNames.contains('sales')) {
                    db.createObjectStore('sales', { keyPath: 'id' });
                }
                if (!db.objectStoreNames.contains('products')) {
                    db.createObjectStore('products', { keyPath: 'id' });
                }
                if (!db.objectStoreNames.contains('customers')) {
                    db.createObjectStore('customers', { keyPath: 'id' });
                }
                if (!db.objectStoreNames.contains('settings')) {
                    db.createObjectStore('settings', { keyPath: 'key' });
                }
            };
        });
    }

    async handleOnline() {
        this.isOnline = true;
        await this.syncAllData();
    }

    async handleOffline() {
        this.isOnline = false;
    }

    async showNotification(title, body) {}

    // Sales operations
    async saveSaleOffline(saleData) {
        const saleId = Date.now();
        const sale = { id: saleId, ...saleData, timestamp: new Date().toISOString(), synced: false };
        return new Promise((resolve, reject) => {
            if (!this.db) { reject(new Error('Database not ready')); return; }
            const transaction = this.db.transaction(['sales'], 'readwrite');
            const store = transaction.objectStore('sales');
            const request = store.put(sale);
            request.onsuccess = () => resolve(saleId);
            request.onerror = () => reject(request.error);
        });
    }

    async getOfflineSales() {
        return await this.getAllData('sales');
    }

    async syncSale(sale) {
        try {
            const response = await fetch('/api/sync-sale.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(sale)
            });

            if (response.ok) {
                sale.synced = true;
                await this.storeData('sales', sale);
                console.log('Sale synced:', sale.id);
                return true;
            }
        } catch (error) {
            console.error('Failed to sync sale:', error);
        }
        return false;
    }

    // Product operations
    async saveProductOffline(productData) {
        const product = {
            ...productData,
            timestamp: new Date().toISOString(),
            synced: false
        };

        await this.storeData('products', product);

        if (this.isOnline) {
            await this.syncProduct(product);
        }

        return product.id;
    }

    async getOfflineProducts() {
        return await this.getAllData('products');
    }

    async syncProduct(product) {
        try {
            const response = await fetch('/api/sync-product.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(product)
            });

            if (response.ok) {
                product.synced = true;
                await this.storeData('products', product);
                console.log('Product synced:', product.id);
                return true;
            }
        } catch (error) {
            console.error('Failed to sync product:', error);
        }
        return false;
    }

    // Customer operations
    async saveCustomerOffline(customerData) {
        const customer = {
            ...customerData,
            timestamp: new Date().toISOString(),
            synced: false
        };

        await this.storeData('customers', customer);

        if (this.isOnline) {
            await this.syncCustomer(customer);
        }

        return customer.id;
    }

    async syncCustomer(customer) {
        try {
            const response = await fetch('/api/sync-customer.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(customer)
            });

            if (response.ok) {
                customer.synced = true;
                await this.storeData('customers', customer);
                console.log('Customer synced:', customer.id);
                return true;
            }
        } catch (error) {
            console.error('Failed to sync customer:', error);
        }
        return false;
    }

    // Generic data operations
    async storeData(storeName, data) {
        return new Promise((resolve, reject) => {
            if (!this.db) { reject(new Error('Database not initialized')); return; }
            if (!this.db.objectStoreNames.contains(storeName)) { reject(new Error('Store not found: ' + storeName)); return; }
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.put(data);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async getAllData(storeName) {
        return new Promise((resolve, reject) => {
            if (!this.db) { resolve([]); return; }
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async getDataById(storeName, id) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.get(id);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async deleteData(storeName, id) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.delete(id);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async removeOfflineSale(id) {
        return await this.deleteData('sales', id);
    }

    async syncAllData() {
        if (!this.isOnline) return;
        const sales = await this.getOfflineSales();
        for (const sale of sales.filter(s => !s.synced)) {
            const synced = await this.syncSale(sale);
            if (synced) {
                await this.deleteData('sales', sale.id);
            }
        }
    }

    async checkAndSync() {
        if (this.isOnline) { await this.syncAllData(); }
    }

    async getSyncStatus() {
        const sales = await this.getOfflineSales();
        return { sales: sales.filter(s => !s.synced).length, total: sales.length };
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => { 
        window.posOffline = new POSOfflineManager();
        window.posOfflineManager = window.posOffline;
    });
} else {
    window.posOffline = new POSOfflineManager();
    window.posOfflineManager = window.posOffline;
}