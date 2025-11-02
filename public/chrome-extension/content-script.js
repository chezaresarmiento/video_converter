// Content script that runs on YouTube pages to automatically sync cookies
// This script detects when the user is on YouTube and triggers cookie sync

let lastSyncTime = 0;
const SYNC_INTERVAL = 30 * 60 * 1000; // 30 minutes in milliseconds
const STORAGE_KEY = 'lastCookieSync';

// Check if we need to sync cookies
async function shouldSyncCookies() {
    try {
        const result = await chrome.storage.local.get([STORAGE_KEY]);
        const lastSync = result[STORAGE_KEY] || 0;
        const timeSinceLastSync = Date.now() - lastSync;

        return timeSinceLastSync > SYNC_INTERVAL;
    } catch (error) {
        console.log('Error checking sync time:', error);
        return true; // Sync if we can't determine last sync time
    }
}

// Trigger automatic cookie sync
async function autoSyncCookies() {
    try {
        // Check if we should sync
        if (!(await shouldSyncCookies())) {
            console.log('Cookie sync not needed yet');
            return;
        }

        console.log('Auto-syncing YouTube cookies...');

        // Send message to background script to get and upload cookies
        chrome.runtime.sendMessage({
            action: "autoSyncCookies"
        }, (response) => {
            if (response && response.success) {
                console.log('Cookies auto-synced successfully');
                // Update last sync time
                chrome.storage.local.set({
                    [STORAGE_KEY]: Date.now()
                });
            } else {
                console.log('Auto-sync failed:', response?.error || 'Unknown error');
            }
        });
    } catch (error) {
        console.error('Error during auto-sync:', error);
    }
}

// Function to detect if user has accepted YouTube cookies
function detectCookieConsent() {
    // Look for common YouTube cookie consent indicators
    const consentIndicators = [
        '[data-testid="consent-bump-v2"]',
        '.consent-bump',
        '[aria-label*="cookie"]',
        '[aria-label*="consent"]'
    ];

    const hasConsentDialog = consentIndicators.some(selector =>
        document.querySelector(selector) !== null
    );

    if (!hasConsentDialog) {
        // No consent dialog visible, likely cookies are already accepted
        autoSyncCookies();
    } else {
        console.log('Cookie consent dialog detected, waiting for user action...');
        // Set up observer to watch for consent dialog dismissal
        observeConsentDismissal();
    }
}

// Observer to watch for consent dialog dismissal
function observeConsentDismissal() {
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'childList') {
                mutation.removedNodes.forEach((node) => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        // Check if a consent-related element was removed
                        if (node.matches && (
                            node.matches('[data-testid="consent-bump-v2"]') ||
                            node.matches('.consent-bump') ||
                            node.querySelector('[data-testid="consent-bump-v2"]') ||
                            node.querySelector('.consent-bump')
                        )) {
                            console.log('Consent dialog dismissed, syncing cookies...');
                            setTimeout(autoSyncCookies, 2000); // Wait 2 seconds for cookies to be set
                            observer.disconnect();
                        }
                    }
                });
            }
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

    // Disconnect observer after 5 minutes to avoid memory leaks
    setTimeout(() => observer.disconnect(), 5 * 60 * 1000);
}

// Auto-sync on page load if already consented
function initializeAutoSync() {
    // Wait for page to be fully loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', detectCookieConsent);
    } else {
        detectCookieConsent();
    }
}

// Initialize when script loads
initializeAutoSync();

// Also sync when user navigates within YouTube (SPA navigation)
let currentUrl = location.href;
new MutationObserver(() => {
    if (location.href !== currentUrl) {
        currentUrl = location.href;
        setTimeout(detectCookieConsent, 1000); // Wait for new page to load
    }
}).observe(document, { subtree: true, childList: true });