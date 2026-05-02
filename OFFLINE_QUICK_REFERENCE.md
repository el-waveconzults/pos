# Offline Functionality - Quick Reference

## Status Check

Look for badge in **bottom-right corner**:

- 🟢 **✓ Ready** = Working
- 🟠 **initializing...** = Loading (wait a few seconds)
- 🔴 **✗ Failed** = Check F12 console

## How to Use

### Process Sale Offline

1. Go to **Point of Sale**
2. Add items normally
3. Process sale
4. System automatically detects if online/offline
5. If offline → "Sale saved offline! Will sync when online"
6. If online → "Sale completed! Invoice: #..."

### Check What's Pending

1. Visit: `http://localhost/pos/offline.html`
2. See pending sales, products, customers
3. Can force sync manually

## Debug/Verify

### Quick Check

1. Press **F12** (open Developer Tools)
2. Go to **Console**
3. Look for: `✓ Offline Manager Available - Ready: true`

### Full Diagnostic

1. Visit: `http://localhost/pos/debug-offline.php`
2. Check all features are green ✓
3. Click "Test Offline Manager"

### Test Offline Mode

1. Disconnect internet (or use DevTools)
2. Try to process a sale
3. Should work and show offline message
4. Reconnect internet
5. Sale should sync automatically

## Common Issues & Solutions

| Problem                               | Solution                                                              |
| ------------------------------------- | --------------------------------------------------------------------- |
| Status shows "✗ Failed"               | Check browser console (F12), clear cache, try different browser       |
| "Offline functionality not available" | Wait 2-3 seconds and try again, or clear browser cache                |
| Sales not syncing                     | Go to offline page, click "Force Sync", check internet connection     |
| Takes long to load offline            | Normal - first load initializes database, subsequent loads are faster |

## File Locations (for support)

| Feature         | File                    |
| --------------- | ----------------------- |
| Offline Manager | `js/offline-manager.js` |
| Service Worker  | `sw.js`                 |
| Offline UI      | `offline.html`          |
| Sync APIs       | `api/sync-*.php`        |
| Test Page       | `test-offline.php`      |
| Debug Page      | `debug-offline.php`     |

## Keyboard Shortcuts

| Action               | Keys                                  |
| -------------------- | ------------------------------------- |
| Open Developer Tools | F12 or Ctrl+Shift+I                   |
| Open Console         | F12 → Console tab                     |
| Clear Browser Cache  | Ctrl+Shift+Delete (choose "All time") |

## Browser Support

| Browser           | Support    | Notes                          |
| ----------------- | ---------- | ------------------------------ |
| Chrome            | ✅ Full    | Best experience                |
| Firefox           | ✅ Full    | Good experience                |
| Edge              | ✅ Full    | Good experience                |
| Safari            | ⚠️ Limited | Works but limited PWA features |
| Internet Explorer | ❌ No      | Use Chrome/Firefox instead     |

## Getting Help

1. **Check Status** → `debug-offline.php`
2. **Check Console** → F12 → Console tab
3. **Clear Cache** → Ctrl+Shift+Delete → "All time" → Clear
4. **Try Again** → Refresh page and test

## Features Available Offline

✅ Process sales
✅ Browse products
✅ View customers
✅ Search inventory
✅ Process transactions

## Features Requiring Internet

❌ Reports (generate server-side)
❌ Complex queries
❌ Multi-user sync (real-time)
❌ Backup/Export

---

**Status Badge Location:** Bottom-right corner of screen
**Debug Page:** `http://localhost/pos/debug-offline.php`
**Offline Page:** `http://localhost/pos/offline.html`
