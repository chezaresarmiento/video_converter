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
    } catch (error) {
        console.error("Upload error:", error);
        alert("Failed to upload cookies.");
    }
});

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
