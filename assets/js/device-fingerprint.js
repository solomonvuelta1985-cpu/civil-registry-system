/**
 * Device Fingerprint Generator
 * iScan Civil Registry Records Management System
 *
 * Generates a SHA-256 hash from multiple browser/hardware signals.
 * This fingerprint is unique to each physical device and browser combination.
 *
 * Signals used:
 *   - User-Agent (browser name + version + OS)
 *   - Navigator language and platform
 *   - Screen resolution and color depth
 *   - Hardware CPU logical core count
 *   - System timezone
 *   - Canvas rendering (GPU-unique pixel output)
 *   - WebGL renderer string (GPU model)
 *
 * Usage:
 *   const hash = await window.DeviceFingerprint.get();
 *   // Returns: "a3f9b2c1d4e5f6a7..." (64-char hex string)
 */

window.DeviceFingerprint = (function () {

    let _cachedHash = null;

    /**
     * Generate canvas fingerprint.
     * Different GPU drivers render text/shapes slightly differently,
     * making this unique per physical machine.
     */
    function _getCanvasFingerprint() {
        try {
            const canvas = document.createElement('canvas');
            canvas.width = 280;
            canvas.height = 60;
            const ctx = canvas.getContext('2d');

            // Background
            ctx.fillStyle = '#f0f0f0';
            ctx.fillRect(0, 0, 280, 60);

            // Text with specific font rendering
            ctx.fillStyle = '#1a1a2e';
            ctx.font = 'bold 16px Arial, sans-serif';
            ctx.fillText('iScan DeviceID \u2764 \u03c0', 10, 30);

            // Colored shapes (GPU renders these uniquely)
            ctx.beginPath();
            ctx.arc(240, 30, 18, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(220, 50, 50, 0.7)';
            ctx.fill();

            ctx.beginPath();
            ctx.moveTo(200, 10);
            ctx.lineTo(225, 50);
            ctx.lineTo(175, 50);
            ctx.closePath();
            ctx.fillStyle = 'rgba(50, 100, 220, 0.6)';
            ctx.fill();

            return canvas.toDataURL();
        } catch (e) {
            return 'canvas_unavailable';
        }
    }

    /**
     * Get WebGL renderer string (identifies GPU model and driver).
     */
    function _getWebGLFingerprint() {
        try {
            const canvas = document.createElement('canvas');
            const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            if (!gl) return 'webgl_unavailable';

            const ext = gl.getExtension('WEBGL_debug_renderer_info');
            if (ext) {
                const vendor = gl.getParameter(ext.UNMASKED_VENDOR_WEBGL);
                const renderer = gl.getParameter(ext.UNMASKED_RENDERER_WEBGL);
                return vendor + '|' + renderer;
            }
            return gl.getParameter(gl.RENDERER) || 'webgl_no_info';
        } catch (e) {
            return 'webgl_error';
        }
    }

    /**
     * Convert ArrayBuffer to hex string.
     */
    function _bufferToHex(buffer) {
        return Array.from(new Uint8Array(buffer))
            .map(b => b.toString(16).padStart(2, '0'))
            .join('');
    }

    /**
     * Collect all fingerprint signals and hash them.
     * @returns {Promise<string>} 64-character SHA-256 hex string
     */
    async function get() {
        if (_cachedHash) return _cachedHash;

        const signals = [
            navigator.userAgent        || 'ua_unknown',
            navigator.language         || 'lang_unknown',
            navigator.platform         || 'platform_unknown',
            String(navigator.hardwareConcurrency || 0),
            String(screen.width)       + 'x' + String(screen.height),
            String(screen.colorDepth)  || '0',
            String(screen.pixelDepth)  || '0',
            Intl.DateTimeFormat().resolvedOptions().timeZone || 'tz_unknown',
            String(navigator.maxTouchPoints || 0),
            _getCanvasFingerprint(),
            _getWebGLFingerprint(),
        ];

        const raw = signals.join('||ISCAN||');

        try {
            const encoded = new TextEncoder().encode(raw);
            const hashBuffer = await crypto.subtle.digest('SHA-256', encoded);
            _cachedHash = _bufferToHex(hashBuffer);
        } catch (e) {
            // Fallback: simple hash if SubtleCrypto unavailable (HTTP non-secure)
            let hash = 0;
            for (let i = 0; i < raw.length; i++) {
                hash = ((hash << 5) - hash) + raw.charCodeAt(i);
                hash |= 0;
            }
            _cachedHash = Math.abs(hash).toString(16).padStart(8, '0').repeat(8);
        }

        return _cachedHash;
    }

    return { get };

})();
