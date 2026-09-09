/**
 * Stable browser-profile key for iScan Device Lock.
 *
 * This is deliberately a persistent random token, rather than a canvas/WebGL
 * fingerprint. Browser versions, display settings, GPU drivers, privacy
 * modes, and browser updates can change hardware-style fingerprints over
 * time. A token stored in the browser profile stays stable until that profile
 * is cleared or the user switches browsers/origins.
 *
 * The existing DeviceFingerprint.get() API is retained so login and admin
 * pages do not need to know how the key is generated. The server stores only
 * the 64-character token value in the existing fingerprint_hash column.
 */

window.DeviceFingerprint = (function () {
    const STORAGE_KEY = 'iscan_device_token_v1';
    const COOKIE_KEY = 'iscan_device_token_v1';
    const MAX_AGE = 60 * 60 * 24 * 365 * 5;
    let cachedToken = null;

    function isValidToken(value) {
        return typeof value === 'string' && /^[a-f0-9]{64}$/i.test(value);
    }

    function readStorage() {
        try {
            const value = window.localStorage.getItem(STORAGE_KEY);
            if (isValidToken(value)) return value.toLowerCase();
        } catch (_) { /* privacy mode or blocked storage */ }
        return null;
    }

    function readCookie() {
        try {
            const prefix = COOKIE_KEY + '=';
            const match = document.cookie.split(';').map(v => v.trim()).find(v => v.indexOf(prefix) === 0);
            const value = match ? decodeURIComponent(match.slice(prefix.length)) : '';
            return isValidToken(value) ? value.toLowerCase() : null;
        } catch (_) {
            return null;
        }
    }

    function persist(value) {
        try {
            window.localStorage.setItem(STORAGE_KEY, value);
        } catch (_) { /* continue with the cookie fallback */ }

        try {
            const secure = window.location.protocol === 'https:' ? '; Secure' : '';
            document.cookie = `${COOKIE_KEY}=${encodeURIComponent(value)}; Max-Age=${MAX_AGE}; Path=/; SameSite=Lax${secure}`;
        } catch (_) { /* cookie may be blocked */ }
    }

    function randomToken() {
        const bytes = new Uint8Array(32);
        if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
            window.crypto.getRandomValues(bytes);
        } else {
            for (let i = 0; i < bytes.length; i += 1) {
                bytes[i] = Math.floor(Math.random() * 256);
            }
        }
        return Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
    }

    async function get() {
        if (cachedToken) return cachedToken;

        const existing = readStorage() || readCookie();
        if (existing) {
            cachedToken = existing;
            persist(cachedToken);
            return cachedToken;
        }

        cachedToken = randomToken();
        persist(cachedToken);
        return cachedToken;
    }

    return { get };
})();
