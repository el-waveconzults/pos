<?php
require_once 'config/config.php';
$conn = getDB();

// Test database connection
$testQuery = $conn->query("SELECT COUNT(*) as count FROM users");
$userCount = $testQuery->fetch_assoc()['count'];

// Test offline functionality
$offlineTest = [
    'database_connected' => true,
    'user_count' => $userCount,
    'offline_files_exist' => [
        'manifest.json' => file_exists('manifest.json'),
        'sw.js' => file_exists('sw.js'),
        'offline.html' => file_exists('offline.html'),
        'js/offline-manager.js' => file_exists('js/offline-manager.js')
    ],
    'api_endpoints' => [
        'sync-sale.php' => file_exists('api/sync-sale.php'),
        'sync-product.php' => file_exists('api/sync-product.php'),
        'sync-customer.php' => file_exists('api/sync-customer.php')
    ]
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Offline Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .test-result {
            margin: 10px 0;
            padding: 10px;
            border-radius: 5px;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-flask text-primary"></i> POS Offline Functionality Test</h3>
                    </div>
                    <div class="card-body">

                        <h5>Database Connection</h5>
                        <div class="test-result <?= $offlineTest['database_connected'] ? 'success' : 'error' ?>">
                            <i class="fas fa-<?= $offlineTest['database_connected'] ? 'check' : 'times' ?>"></i>
                            Database: <?= $offlineTest['database_connected'] ? 'Connected (' . $offlineTest['user_count'] . ' users)' : 'Failed' ?>
                        </div>

                        <h5 class="mt-4">Offline Files</h5>
                        <?php foreach ($offlineTest['offline_files_exist'] as $file => $exists): ?>
                            <div class="test-result <?= $exists ? 'success' : 'error' ?>">
                                <i class="fas fa-<?= $exists ? 'check' : 'times' ?>"></i>
                                <?= $file ?>: <?= $exists ? 'Found' : 'Missing' ?>
                            </div>
                        <?php endforeach; ?>

                        <h5 class="mt-4">API Endpoints</h5>
                        <?php foreach ($offlineTest['api_endpoints'] as $file => $exists): ?>
                            <div class="test-result <?= $exists ? 'success' : 'error' ?>">
                                <i class="fas fa-<?= $exists ? 'check' : 'times' ?>"></i>
                                api/<?= $file ?>: <?= $exists ? 'Found' : 'Missing' ?>
                            </div>
                        <?php endforeach; ?>

                        <h5 class="mt-4">Browser Compatibility</h5>
                        <div class="test-result" id="sw-support">
                            <i class="fas fa-spinner fa-spin"></i> Checking Service Worker support...
                        </div>
                        <div class="test-result" id="idb-support">
                            <i class="fas fa-spinner fa-spin"></i> Checking IndexedDB support...
                        </div>
                        <div class="test-result" id="sync-support">
                            <i class="fas fa-spinner fa-spin"></i> Checking Background Sync support...
                        </div>

                        <h5 class="mt-4">Connection Status</h5>
                        <div class="test-result" id="connection-status">
                            <i class="fas fa-spinner fa-spin"></i> Checking connection...
                        </div>

                        <div class="mt-4">
                            <button class="btn btn-primary" onclick="testOfflineStorage()">
                                <i class="fas fa-database"></i> Test Offline Storage
                            </button>
                            <button class="btn btn-success" onclick="testServiceWorker()">
                                <i class="fas fa-cog"></i> Test Service Worker
                            </button>
                            <button class="btn btn-info" onclick="location.href='offline.html'">
                                <i class="fas fa-eye"></i> View Offline Page
                            </button>
                        </div>

                        <div class="mt-3">
                            <div class="alert alert-info">
                                <strong>Next Steps:</strong>
                                <ol class="mb-0 mt-2">
                                    <li>Test the POS system by going offline (disconnect internet)</li>
                                    <li>Process a sale - it should work and show offline notification</li>
                                    <li>Reconnect internet - data should sync automatically</li>
                                    <li>Check the offline page for sync status</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Offline alert modal -->
    <div class="modal fade" id="offlineAlertModal" tabindex="-1" aria-labelledby="offlineAlertModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="offlineAlertModalLabel">Notice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="offlineAlertModalMessage"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showOfflineModal(message, title = 'Notice', type = 'info') {
            const titleEl = document.getElementById('offlineAlertModalLabel');
            const messageEl = document.getElementById('offlineAlertModalMessage');
            const modalEl = document.getElementById('offlineAlertModal');
            titleEl.textContent = title;
            messageEl.innerHTML = `<div class="alert alert-${type} mb-0">${message}</div>`;
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        }

        // Check browser features
        function checkBrowserSupport() {
            // Service Worker
            const swSupport = 'serviceWorker' in navigator;
            document.getElementById('sw-support').className = 'test-result ' + (swSupport ? 'success' : 'error');
            document.getElementById('sw-support').innerHTML = `<i class="fas fa-${swSupport ? 'check' : 'times'}"></i> Service Worker: ${swSupport ? 'Supported' : 'Not Supported'}`;

            // IndexedDB
            const idbSupport = 'indexedDB' in window;
            document.getElementById('idb-support').className = 'test-result ' + (idbSupport ? 'success' : 'error');
            document.getElementById('idb-support').innerHTML = `<i class="fas fa-${idbSupport ? 'check' : 'times'}"></i> IndexedDB: ${idbSupport ? 'Supported' : 'Not Supported'}`;

            // Background Sync
            const syncSupport = 'serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype;
            document.getElementById('sync-support').className = 'test-result ' + (syncSupport ? 'success' : 'warning');
            document.getElementById('sync-support').innerHTML = `<i class="fas fa-${syncSupport ? 'check' : 'exclamation-triangle'}"></i> Background Sync: ${syncSupport ? 'Supported' : 'Not Supported (manual sync only)'}`;

            // Connection
            const isOnline = navigator.onLine;
            document.getElementById('connection-status').className = 'test-result ' + (isOnline ? 'success' : 'warning');
            document.getElementById('connection-status').innerHTML = `<i class="fas fa-${isOnline ? 'wifi' : 'wifi-slash'}"></i> Connection: ${isOnline ? 'Online' : 'Offline'}`;
        }

        async function testOfflineStorage() {
            if (!('indexedDB' in window)) {
                showOfflineModal('IndexedDB not supported in this browser', 'Compatibility Error', 'danger');
                return;
            }

            try {
                // Test basic IndexedDB operation
                const db = await openTestDB();
                await storeTestData(db);
                const data = await getTestData(db);
                db.close();

                showOfflineModal('Offline storage test successful! Data stored and retrieved.', 'Offline Storage', 'success');
                console.log('Test data:', data);
            } catch (error) {
                console.error('Offline storage test failed:', error);
                showOfflineModal('Offline storage test failed. Check console for details.', 'Offline Storage', 'danger');
            }
        }

        function openTestDB() {
            return new Promise((resolve, reject) => {
                const request = indexedDB.open('pos-test', 1);
                request.onupgradeneeded = (event) => {
                    const db = event.target.result;
                    if (!db.objectStoreNames.contains('test')) {
                        db.createObjectStore('test', {
                            keyPath: 'id'
                        });
                    }
                };
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
            });
        }

        async function storeTestData(db) {
            return new Promise((resolve, reject) => {
                const transaction = db.transaction(['test'], 'readwrite');
                const store = transaction.objectStore('test');
                const request = store.put({
                    id: 1,
                    data: 'test data',
                    timestamp: new Date().toISOString()
                });
                request.onsuccess = () => resolve();
                request.onerror = () => reject(request.error);
            });
        }

        async function getTestData(db) {
            return new Promise((resolve, reject) => {
                const transaction = db.transaction(['test'], 'readonly');
                const store = transaction.objectStore('test');
                const request = store.get(1);
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
            });
        }

        async function testServiceWorker() {
            if (!('serviceWorker' in navigator)) {
                showOfflineModal('Service Worker not supported in this browser', 'Compatibility Error', 'danger');
                return;
            }

            try {
                const registration = await navigator.serviceWorker.register('/sw.js');
                showOfflineModal('Service Worker registered successfully!', 'Service Worker', 'success');
                console.log('SW registered:', registration);
            } catch (error) {
                console.error('Service Worker registration failed:', error);
                showOfflineModal('Service Worker registration failed. Check console for details.', 'Service Worker', 'danger');
            }
        }

        // Run checks on load
        checkBrowserSupport();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>