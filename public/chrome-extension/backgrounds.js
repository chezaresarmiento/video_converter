chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
    if (request.action === "getCookies") {
        chrome.cookies.getAll({ domain: "youtube.com" }, (cookies) => {
            sendResponse({ cookies });
        });
    }
    return true; // Keep the message channel open
});