# YouTube Cookie Auto-Sync Extension

## Features

### Automatic Cookie Synchronization
- **Auto-detects** when you visit YouTube and automatically syncs cookies
- **Smart timing**: Only syncs every 30 minutes to avoid unnecessary requests
- **Consent detection**: Waits for cookie consent before attempting sync
- **Background operation**: Works silently without user intervention

### Manual Controls (Fallback)
- Extract cookies manually if needed
- Force immediate sync via popup
- View sync status and last sync time

## How It Works

1. **Content Script**: Runs on all YouTube pages and detects:
   - Cookie consent dialogs
   - When consent is given/dismissed
   - Page navigation within YouTube

2. **Automatic Sync**: Triggers when:
   - User visits YouTube after consent
   - 30+ minutes have passed since last sync
   - User manually forces sync

3. **Background Process**: Handles:
   - Cookie extraction from browser
   - Formatting to Netscape format
   - Upload to video converter server

## Installation

1. Open Chrome Extensions (chrome://extensions/)
2. Enable "Developer mode"
3. Click "Load unpacked"
4. Select the chrome-extension folder

## Status Indicators

- **Active**: Cookies synced within last 30 minutes
- **Needs sync**: More than 30 minutes since last sync
- **Not synced**: Never synced or error occurred

## Technical Details

- **Sync Interval**: 30 minutes
- **Storage**: Uses Chrome's local storage for sync timestamps
- **Permissions**: Cookies, storage, YouTube access
- **Compatibility**: Chrome Manifest V3

The extension eliminates the need for manual cookie management while maintaining the existing server-side video conversion functionality.