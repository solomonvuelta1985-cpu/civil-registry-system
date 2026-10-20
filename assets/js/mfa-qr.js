(function () {
    'use strict';

    document.querySelectorAll('[data-mfa-qr]').forEach(function (container) {
        var uri = container.getAttribute('data-otpauth-uri') || '';
        if (!uri || typeof window.qrcode !== 'function') {
            container.classList.add('is-unavailable');
            return;
        }

        try {
            var qr = window.qrcode(0, 'M');
            qr.addData(uri, 'Byte');
            qr.make();
            container.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 4, scalable: true });

            var svg = container.querySelector('svg');
            if (!svg) throw new Error('QR SVG could not be rendered.');
            svg.setAttribute('aria-hidden', 'true');
            container.classList.add('is-rendered');
        } catch (error) {
            container.textContent = 'QR code unavailable. Use the setup key below.';
            container.classList.add('is-unavailable');
        }
    });
}());
