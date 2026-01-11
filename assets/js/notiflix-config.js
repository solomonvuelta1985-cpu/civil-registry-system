/**
 * Notiflix Global Configuration
 * Modern notification system for all confirmations, alerts, and loading states
 */

// Initialize Notiflix when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Notiflix !== 'undefined') {
        // Notify Configuration
        Notiflix.Notify.init({
            width: '320px',
            position: 'right-top',
            distance: '20px',
            opacity: 1,
            borderRadius: '8px',
            rtl: false,
            timeout: 4000,
            messageMaxLength: 300,
            backOverlay: false,
            plainText: false,
            showOnlyTheLastOne: false,
            clickToClose: true,
            pauseOnHover: true,
            ID: 'NotiflixNotify',
            className: 'notiflix-notify',
            zindex: 4001,
            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Inter", Roboto, sans-serif',
            fontSize: '14px',
            cssAnimationStyle: 'from-right',
            cssAnimationDuration: 300,
            success: {
                background: '#10B981',
                textColor: '#fff',
                childClassName: 'notiflix-notify-success',
                notiflixIconColor: 'rgba(255,255,255,0.2)',
                fontAwesomeClassName: 'fas fa-check-circle',
                fontAwesomeIconColor: 'rgba(255,255,255,0.2)',
                backOverlayColor: 'rgba(16,185,129,0.2)',
            },
            failure: {
                background: '#EF4444',
                textColor: '#fff',
                childClassName: 'notiflix-notify-failure',
                notiflixIconColor: 'rgba(255,255,255,0.2)',
                fontAwesomeClassName: 'fas fa-times-circle',
                fontAwesomeIconColor: 'rgba(255,255,255,0.2)',
                backOverlayColor: 'rgba(239,68,68,0.2)',
            },
            warning: {
                background: '#F59E0B',
                textColor: '#fff',
                childClassName: 'notiflix-notify-warning',
                notiflixIconColor: 'rgba(255,255,255,0.2)',
                fontAwesomeClassName: 'fas fa-exclamation-triangle',
                fontAwesomeIconColor: 'rgba(255,255,255,0.2)',
                backOverlayColor: 'rgba(245,158,11,0.2)',
            },
            info: {
                background: '#3B82F6',
                textColor: '#fff',
                childClassName: 'notiflix-notify-info',
                notiflixIconColor: 'rgba(255,255,255,0.2)',
                fontAwesomeClassName: 'fas fa-info-circle',
                fontAwesomeIconColor: 'rgba(255,255,255,0.2)',
                backOverlayColor: 'rgba(59,130,246,0.2)',
            },
        });

        // Confirm Dialog Configuration
        Notiflix.Confirm.init({
            className: 'notiflix-confirm',
            width: '320px',
            zindex: 4003,
            position: 'center',
            distance: '10px',
            backgroundColor: '#f8f8f8',
            borderRadius: '12px',
            backOverlay: true,
            backOverlayColor: 'rgba(0,0,0,0.5)',
            rtl: false,
            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Inter", Roboto, sans-serif',
            cssAnimation: true,
            cssAnimationDuration: 300,
            cssAnimationStyle: 'zoom',
            plainText: false,
            titleColor: '#111827',
            titleFontSize: '18px',
            titleMaxLength: 34,
            messageColor: '#6B7280',
            messageFontSize: '14px',
            messageMaxLength: 300,
            buttonsFontSize: '14px',
            buttonsMaxLength: 34,
            okButtonColor: '#fff',
            okButtonBackground: '#3B82F6',
            cancelButtonColor: '#6B7280',
            cancelButtonBackground: '#f1f5f9',
        });

        // Loading Indicator Configuration
        Notiflix.Loading.init({
            className: 'notiflix-loading',
            zindex: 4000,
            backgroundColor: 'rgba(0,0,0,0.8)',
            rtl: false,
            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Inter", Roboto, sans-serif',
            cssAnimation: true,
            cssAnimationDuration: 400,
            clickToClose: false,
            customSvgUrl: null,
            customSvgCode: null,
            svgSize: '80px',
            svgColor: '#3B82F6',
            messageID: 'NotiflixLoadingMessage',
            messageFontSize: '15px',
            messageMaxLength: 34,
            messageColor: '#dcdcdc',
        });

        // Report Configuration (for more detailed messages)
        Notiflix.Report.init({
            className: 'notiflix-report',
            width: '320px',
            backgroundColor: '#f8f8f8',
            borderRadius: '12px',
            rtl: false,
            zindex: 4002,
            backOverlay: true,
            backOverlayColor: 'rgba(0,0,0,0.5)',
            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Inter", Roboto, sans-serif',
            svgSize: '110px',
            plainText: false,
            titleFontSize: '18px',
            titleMaxLength: 34,
            messageFontSize: '14px',
            messageMaxLength: 400,
            buttonFontSize: '14px',
            buttonMaxLength: 34,
            cssAnimation: true,
            cssAnimationDuration: 300,
            cssAnimationStyle: 'zoom',
            success: {
                svgColor: '#10B981',
                titleColor: '#111827',
                messageColor: '#6B7280',
                buttonBackground: '#10B981',
                buttonColor: '#fff',
                backOverlayColor: 'rgba(16,185,129,0.2)',
            },
            failure: {
                svgColor: '#EF4444',
                titleColor: '#111827',
                messageColor: '#6B7280',
                buttonBackground: '#EF4444',
                buttonColor: '#fff',
                backOverlayColor: 'rgba(239,68,68,0.2)',
            },
            warning: {
                svgColor: '#F59E0B',
                titleColor: '#111827',
                messageColor: '#6B7280',
                buttonBackground: '#F59E0B',
                buttonColor: '#fff',
                backOverlayColor: 'rgba(245,158,11,0.2)',
            },
            info: {
                svgColor: '#3B82F6',
                titleColor: '#111827',
                messageColor: '#6B7280',
                buttonBackground: '#3B82F6',
                buttonColor: '#fff',
                backOverlayColor: 'rgba(59,130,246,0.2)',
            },
        });

        console.log('Notiflix initialized successfully');
    }
});

/**
 * Helper Functions for Common Notifications
 */

// Success notification
function notifySuccess(message, timeout = 4000) {
    if (typeof Notiflix !== 'undefined') {
        Notiflix.Notify.success(message, { timeout });
    }
}

// Error notification
function notifyError(message, timeout = 5000) {
    if (typeof Notiflix !== 'undefined') {
        Notiflix.Notify.failure(message, { timeout });
    }
}

// Warning notification
function notifyWarning(message, timeout = 4000) {
    if (typeof Notiflix !== 'undefined') {
        Notiflix.Notify.warning(message, { timeout });
    }
}

// Info notification
function notifyInfo(message, timeout = 4000) {
    if (typeof Notiflix !== 'undefined') {
        Notiflix.Notify.info(message, { timeout });
    }
}

// Confirm dialog with custom options
function confirmAction(title, message, okCallback, cancelCallback = null, options = {}) {
    if (typeof Notiflix !== 'undefined') {
        const defaultOptions = {
            width: '360px',
            borderRadius: '12px',
        };
        const finalOptions = { ...defaultOptions, ...options };

        Notiflix.Confirm.show(
            title,
            message,
            'Yes',
            'No',
            okCallback,
            cancelCallback || function() {},
            finalOptions
        );
    } else {
        // Fallback to native confirm
        if (confirm(message)) {
            okCallback();
        } else if (cancelCallback) {
            cancelCallback();
        }
    }
}

// Delete confirmation (specialized)
function confirmDelete(message, okCallback, cancelCallback = null) {
    confirmAction(
        'Confirm Deletion',
        message || 'Are you sure you want to delete this item? This action cannot be undone.',
        okCallback,
        cancelCallback,
        {
            titleColor: '#EF4444',
            okButtonBackground: '#EF4444',
        }
    );
}

// Show loading indicator
function showLoading(message = 'Please wait...') {
    if (typeof Notiflix !== 'undefined') {
        Notiflix.Loading.circle(message);
    }
}

// Hide loading indicator
function hideLoading() {
    if (typeof Notiflix !== 'undefined') {
        Notiflix.Loading.remove();
    }
}

// Show success report (for detailed success messages)
function reportSuccess(title, message, buttonText = 'OK', callback = null) {
    if (typeof Notiflix !== 'undefined') {
        Notiflix.Report.success(
            title,
            message,
            buttonText,
            callback || function() {}
        );
    }
}

// Show error report (for detailed error messages)
function reportError(title, message, buttonText = 'OK', callback = null) {
    if (typeof Notiflix !== 'undefined') {
        Notiflix.Report.failure(
            title,
            message,
            buttonText,
            callback || function() {}
        );
    }
}

// Show warning report
function reportWarning(title, message, buttonText = 'OK', callback = null) {
    if (typeof Notiflix !== 'undefined') {
        Notiflix.Report.warning(
            title,
            message,
            buttonText,
            callback || function() {}
        );
    }
}

// Show info report
function reportInfo(title, message, buttonText = 'OK', callback = null) {
    if (typeof Notiflix !== 'undefined') {
        Notiflix.Report.info(
            title,
            message,
            buttonText,
            callback || function() {}
        );
    }
}
