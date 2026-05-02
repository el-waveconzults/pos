# POS System - Offline/Online Support

## Overview

The POS System now supports both offline and online operation, allowing you to continue working even without internet connectivity. All data is automatically synchronized when the connection is restored.

## Features

### 🔄 Automatic Synchronization

- **Sales**: All transactions are saved locally when offline and synced when online
- **Products**: Product updates and inventory changes sync automatically
- **Customers**: Customer data changes are synchronized
- **Background Sync**: Uses service workers for automatic background synchronization

### 📱 Progressive Web App (PWA)

- **Installable**: Can be installed on mobile devices and desktops
- **Offline Access**: Core functionality works without internet
- **Native App Feel**: Responsive design with app-like experience

### 🔌 Connection Management

- **Status Indicator**: Shows current online/offline status
- **Automatic Detection**: Detects connection changes automatically
- **Offline Page**: Dedicated page when accessing while offline

## How It Works

### Online Mode (Default)

1. All operations work normally with real-time server communication
2. Data is immediately saved to the database
3. Full functionality available

### Offline Mode (Automatic Fallback)

1. When internet connection is lost, the system automatically switches to offline mode
2. Sales can still be processed and are stored locally
3. A notification appears indicating offline status
4. Data is queued for synchronization

### Synchronization

1. When connection is restored, pending data automatically syncs
2. Background sync ensures data is uploaded even if the app is closed
3. Conflict resolution handles any data conflicts
4. Success/failure notifications keep you informed

## Technical Implementation

### Service Worker (`sw.js`)

- Caches essential files for offline use
- Handles background synchronization
- Manages push notifications

### Offline Manager (`js/offline-manager.js`)

- Manages local data storage using IndexedDB
- Handles online/offline state detection
- Coordinates data synchronization

### Sync APIs

- `api/sync-sale.php`: Synchronizes sales data
- `api/sync-product.php`: Synchronizes product updates
- `api/sync-customer.php`: Synchronizes customer data

### Web App Manifest (`manifest.json`)

- Defines PWA properties
- Enables installation on devices
- Configures app appearance

## Usage Instructions

### For Users

1. **Normal Operation**: Use the system as usual - it automatically handles online/offline states
2. **Offline Indicator**: Watch for the connection status indicator in the top-right corner
3. **Continue Working**: Process sales normally when offline - they'll sync later
4. **Check Sync Status**: Visit the offline page to see pending synchronizations

### For Administrators

1. **Monitor Sync**: Check the offline page for sync status
2. **Force Sync**: Use the "Force Sync" button if needed
3. **Data Integrity**: All offline data is validated before syncing
4. **Conflict Resolution**: System handles data conflicts automatically

## Browser Support

### Recommended Browsers

- Chrome/Chromium (best support)
- Firefox (good support)
- Safari (limited PWA features)
- Edge (good support)

### PWA Features

- **Chrome/Edge**: Full PWA support with install prompt
- **Firefox**: PWA support with manual installation
- **Safari**: Limited PWA features, works as web app

## Installation

### Desktop Installation

1. Open the POS system in Chrome/Edge
2. Click the install icon in the address bar or use the menu
3. Follow the installation prompts

### Mobile Installation

1. Open in mobile browser (Chrome/Safari)
2. Use "Add to Home Screen" option
3. The app will appear as a native app icon

## Troubleshooting

### Common Issues

**Sync Not Working**

- Check internet connection
- Try "Force Sync" button
- Check browser console for errors

**Data Not Saving Offline**

- Ensure browser supports IndexedDB
- Check for sufficient storage space
- Clear browser data if needed

**PWA Not Installing**

- Use Chrome or Edge browser
- Ensure HTTPS connection (required for PWA)
- Check browser settings for PWA support

### Data Safety

- All offline data is stored locally in your browser
- Data persists until successfully synced
- Clearing browser data will remove unsynced transactions
- Always ensure important data is synced before clearing browser data

## Security Considerations

### Data Privacy

- Offline data is stored locally in the browser
- No data is sent to external servers without your knowledge
- All synchronization happens over secure HTTPS connections

### Access Control

- Same authentication requirements apply offline
- Session management continues to work offline
- Role-based access controls are maintained

## Future Enhancements

### Planned Features

- **Real-time Collaboration**: Multiple users working offline with conflict resolution
- **Advanced Sync**: Selective sync options and data prioritization
- **Offline Reports**: Generate reports from offline data
- **Data Compression**: Reduce storage requirements for large datasets
- **Sync Scheduling**: Configurable sync intervals and conditions

---

_This offline/online functionality makes the POS system robust and reliable for various network conditions, ensuring business continuity even in areas with poor internet connectivity._
