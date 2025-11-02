// Update status on popup load
async function updateStatus() {
    try {
        const result = await chrome.storage.local.get(['lastCookieSync']);
        const lastSync = result.lastCookieSync || 0;
        const lastSyncElement = document.getElementById("lastSync");
        const statusElement = document.getElementById("syncStatus");

        if (lastSync === 0) {
            lastSyncElement.textContent = "Never";
            statusElement.textContent = "Not synced";
        } else {
            const syncDate = new Date(lastSync);
            lastSyncElement.textContent = syncDate.toLocaleString();

            const timeSinceSync = Date.now() - lastSync;
            const minutesSinceSync = Math.floor(timeSinceSync / (1000 * 60));

            if (minutesSinceSync < 30) {
                statusElement.textContent = `Active (${minutesSinceSync}m ago)`;
            } else {
                statusElement.textContent = "Needs sync";
            }
        }
    } catch (error) {
        document.getElementById("syncStatus").textContent = "Error";
        console.error("Error updating status:", error);
    }
}

// Force auto-sync button
document.getElementById("forceSyncCookies").addEventListener("click", () => {
    chrome.runtime.sendMessage({ action: "autoSyncCookies" }, (response) => {
        if (response && response.success) {
            alert("Cookies synced successfully!");
            chrome.storage.local.set({ lastCookieSync: Date.now() });
            updateStatus();
        } else {
            alert("Sync failed: " + (response?.error || "Unknown error"));
        }
    });
});

document.getElementById("extractCookies").addEventListener("click", () => {
    chrome.runtime.sendMessage({ action: "getCookies" }, (response) => {
        if (response && response.cookies) {
            let cookies = response.cookies;
            let netscapeFormat = formatCookiesNetscape(cookies);
            document.getElementById("cookieText").value = netscapeFormat;
        } else {
            alert("No YouTube cookies found.");
        }
    });
});

document.getElementById("uploadCookies").addEventListener("click", async () => {
    let cookies = document.getElementById("cookieText").value;
    if (!cookies) {
        alert("No cookies to upload!");
        return;
    }

    try {
        let response = await fetch("http://video.appsolution4u.com/api/upload-cookies", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({ cookies }),
        });

        let result = await response.json();
        alert(result.message);
        // Update last sync time after manual upload
        chrome.storage.local.set({ lastCookieSync: Date.now() });
        updateStatus();
    } catch (error) {
        console.error("Upload error:", error);
        alert("Failed to upload cookies.");
    }
});

// Update status when popup opens
updateStatus();

function formatCookiesNetscape(cookies) {
    let netscapeHeader = "# Netscape HTTP Cookie File\n# Automatically generated\n\n";
    return netscapeHeader + cookies.map(cookie => {
        let domain = cookie.domain.startsWith('.') ? cookie.domain : '.' + cookie.domain;
        let path = cookie.path || '/';
        let secure = cookie.secure ? 'TRUE' : 'FALSE';
        let expiration = cookie.expirationDate ? cookie.expirationDate : '0';
        return `${domain}\tTRUE\t${path}\t${secure}\t${expiration}\t${cookie.name}\t${cookie.value}`;
    }).join("\n");
}
