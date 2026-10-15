(function () {
    'use strict';

    function initPasswordDialog() {
        const modal = document.getElementById('changePasswordModal');
        const form = document.getElementById('changePasswordForm');
        const message = document.getElementById('changePasswordMessage');

        if (!modal || !form || !message) return;

        // Keep the fixed dialog outside transformed/scrolling app containers so
        // it can always layer above the mobile sidebar and its backdrop.
        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }

        const currentPassword = document.getElementById('currentPassword');
        const submitButton = form.querySelector('[type="submit"]');
        const closeButtons = modal.querySelectorAll('[data-change-password-close]');
        let previousFocus = null;
        let submitting = false;

        function setMessage(text, state) {
            message.textContent = text || '';
            message.dataset.state = state || '';
        }

        function setExpanded(activeButton) {
            document.querySelectorAll('[data-change-password-open]').forEach(function (button) {
                button.setAttribute('aria-expanded', button === activeButton ? 'true' : 'false');
            });
        }

        function openModal(trigger) {
            const profileButton = document.getElementById('userProfileBtn');
            const dropdown = document.getElementById('userDropdownMenu');
            const mobileTrigger = trigger.closest('.sidebar-mobile-account');
            previousFocus = mobileTrigger ? trigger : (profileButton || trigger);

            if (!mobileTrigger) {
                if (profileButton) profileButton.classList.remove('active');
                if (dropdown) dropdown.classList.remove('show');
            }

            setExpanded(trigger);
            form.reset();
            setMessage('', '');
            modal.hidden = false;
            document.body.classList.add('change-password-modal-open');
            requestAnimationFrame(function () {
                if (currentPassword) currentPassword.focus();
            });
        }

        function closeModal() {
            if (submitting) return;
            modal.hidden = true;
            setExpanded(null);
            document.body.classList.remove('change-password-modal-open');
            form.reset();
            setMessage('', '');
            if (previousFocus && typeof previousFocus.focus === 'function') previousFocus.focus();
        }

        // Delegate the trigger so it works regardless of whether the shared
        // navbar or sidebar was included first, or a responsive control is
        // rendered after this script initializes.
        document.addEventListener('click', function (event) {
            const trigger = event.target.closest('[data-change-password-open]');
            if (!trigger) return;
            event.preventDefault();
            openModal(trigger);
        });

        closeButtons.forEach(function (button) {
            button.addEventListener('click', closeModal);
        });

        modal.addEventListener('click', function (event) {
            if (event.target === modal) closeModal();
        });

        modal.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeModal();
                return;
            }

            if (event.key !== 'Tab') return;
            const focusable = Array.from(modal.querySelectorAll('button:not([disabled]), input:not([disabled])'))
                .filter(function (element) { return element.offsetParent !== null; });
            if (!focusable.length) return;

            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (submitting) return;

            submitting = true;
            if (submitButton) submitButton.disabled = true;
            form.setAttribute('aria-busy', 'true');
            setMessage('Updating your password...', 'pending');

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const result = await response.json();

                if (!response.ok || !result.success) {
                    setMessage(result.message || 'The password could not be updated. Please try again.', 'error');
                    if (currentPassword) {
                        currentPassword.value = '';
                        currentPassword.focus();
                    }
                    return;
                }

                form.reset();
                setMessage(result.message || 'Password updated successfully.', 'success');
            } catch (error) {
                setMessage('Could not reach the server. Check your connection and try again.', 'error');
            } finally {
                submitting = false;
                if (submitButton) submitButton.disabled = false;
                form.removeAttribute('aria-busy');
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPasswordDialog, { once: true });
    } else {
        initPasswordDialog();
    }
})();
