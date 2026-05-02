<?php
require_once 'config/config.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline Manager Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            padding: 20px;
        }

        .status {
            font-family: monospace;
        }

        .log-box {
            background: #1e1e1e;
            color: #00ff00;
            padding: 15px;
            border-radius: 5px;
            max-height: 400px;
            overflow-y: auto;
            font-size: 12px;
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header">
                <h4>Offline Manager Debug Console</h4>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6>Browser Features</h6>
                        <div id="features" class="status"></div>
                    </div>
                    <div class="col-md-6">
                        <h6>Connection Status</h6>
                        <div id="connection" class="status"></div>
                    </div>
                </div>

                <h6>Initialization Log</h6>
                <div id="log" class="log-box"></div>

                <div class="mt-3">
                    <button class="btn btn-primary" onclick="testOfflineManager()">Test Offline Manager</button>
                    <button class="btn btn-danger" onclick="clearLog()">Clear Log</button>
                </div>
            </div>
        </div>
    </div>

    <script src="js/offline-manager.js"></script>
    <script>
        const logBox = document.getElementById('log');
        const originalLog = console.log;
        const originalError = console.error;
        const originalWarn = console.warn;

        function addLog(message, type = 'log') {
            const timestamp = new Date().toLocaleTimeString();
            const color = type === 'error' ? 'red' : (type === 'warn' ? 'yellow' : 'lime');
            logBox.innerHTML += `<div style="color: ${color}">[${timestamp}] ${message}</div>`;
            logBox.scrollTop = logBox.scrollHeight;
        }

        console.log = function(...args) {
            originalLog.apply(console, args);
            addLog(args.join(' '), 'log');
        };

        console.error = function(...args) {
            originalError.apply(console, args);
            addLog(args.join(' '), 'error');
        };

        console.warn = function(...args) {
            originalWarn.apply(console, args);
            addLog(args.join(' '), 'warn');
        };

        function checkFeatures() {
            const features = {
                'Service Worker': 'serviceWorker' in navigator,
                'IndexedDB': 'indexedDB' in window,
                'Background Sync': 'serviceWorker' in navigator && 'sync' in ServiceWorkerRegistration.prototype,
                'Notifications': 'Notification' in window,
                'Online': navigator.onLine
            };

            let html = '';
            for (let [feature, supported] of Object.entries(features)) {
                const icon = supported ? '✓' : '✗';
                const color = supported ? 'green' : 'red';
                html += `<div style="color: ${color}">● ${feature}: ${icon}</div>`;
            }
            document.getElementById('features').innerHTML = html;
        }

        function updateConnection() {
            const status = navigator.onLine ? 'Online' : 'Offline';
            document.getElementById('connection').innerHTML = `<strong>${status}</strong>`;
        }

        async function testOfflineManager() {
            try {
                if (!window.posOffline) {
                    addLog('Waiting for offline manager...', 'log');
                    let attempts = 0;
                    while (!window.posOffline && attempts < 50) {
                        await new Promise(r => setTimeout(r, 100));
                        attempts++;
                    }
                }

                if (window.posOffline) {
                    addLog('Offline Manager Status:', 'log');
                    addLog(`- Ready: ${window.posOffline.ready}`, 'log');
                    addLog(`- Database: ${window.posOffline.db ? 'Connected' : 'Not connected'}`, 'log');
                    addLog(`- Online: ${window.posOffline.isOnline}`, 'log');

                    // Try to get sync status
                    const status = await window.posOffline.getSyncStatus();
                    addLog(`Sync Status: ${JSON.stringify(status)}`, 'log');
                } else {
                    addLog('ERROR: Offline Manager not initialized!', 'error');
                }
            } catch (error) {
                addLog(`Error testing offline manager: ${error.message}`, 'error');
            }
        }

        function clearLog() {
            logBox.innerHTML = '';
        }

        // Initial checks
        checkFeatures();
        updateConnection();
        window.addEventListener('online', updateConnection);
        window.addEventListener('offline', updateConnection);

        // Listen for offline manager ready event
        window.addEventListener('offlineManagerReady', () => {
            addLog('Offline Manager Ready Event Fired', 'log');
        });

        addLog('Page loaded - offline manager initializing...', 'log');
    </script>
</body>

</html>