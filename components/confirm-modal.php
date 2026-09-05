<?php
/**
 * Shared Confirmation Modal Component
 * Replaces native browser confirm() dialogs with an accessible, themed in-DOM modal.
 * Automatically binds to any form or button with data-confirm="Are you sure?"
 */
declare(strict_types=1);
?>
<div id="globalConfirmModal" class="admin-modal-overlay" style="display: none;">
    <div class="admin-modal-card" style="max-width: 440px;">
        <div class="admin-modal-header" style="border-bottom: 1px solid var(--color-border);">
            <h3 id="globalConfirmTitle" style="display: flex; align-items: center; gap: 8px; font-size: 1.1rem;">
                <span class="material-symbols-outlined" style="color: var(--color-error);">warning</span>
                <span>Confirm Action</span>
            </h3>
            <button type="button" class="admin-modal-close" id="globalConfirmCloseBtn">&times;</button>
        </div>
        <div class="admin-modal-body" style="padding: 20px;">
            <p id="globalConfirmMessage" style="color: var(--color-text); margin: 0; font-size: 0.95rem; line-height: 1.5;">
                Are you sure you want to proceed with this action?
            </p>
        </div>
        <div class="admin-modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; padding: 14px 20px; background: var(--color-bg); border-top: 1px solid var(--color-border);">
            <button type="button" class="btn btn-secondary" id="globalConfirmCancelBtn">Cancel</button>
            <button type="button" class="btn btn-primary" id="globalConfirmProceedBtn" style="background: var(--color-error); border-color: var(--color-error); color: white;">Confirm</button>
        </div>
    </div>
</div>

<script>
(function() {
    let pendingAction = null;

    const modal = document.getElementById('globalConfirmModal');
    const titleEl = document.getElementById('globalConfirmTitle');
    const msgEl = document.getElementById('globalConfirmMessage');
    const closeBtn = document.getElementById('globalConfirmCloseBtn');
    const cancelBtn = document.getElementById('globalConfirmCancelBtn');
    const proceedBtn = document.getElementById('globalConfirmProceedBtn');

    window.showConfirmDialog = function(options) {
        if (!modal) return;
        const title = options.title || 'Confirm Action';
        const message = options.message || 'Are you sure you want to proceed?';
        const confirmText = options.confirmText || 'Confirm';
        const isDanger = options.danger !== false;

        if (titleEl) {
            titleEl.innerHTML = `<span class="material-symbols-outlined" style="color: ${isDanger ? 'var(--color-error)' : 'var(--color-primary)'};">${isDanger ? 'warning' : 'help'}</span> <span>${title}</span>`;
        }
        if (msgEl) msgEl.textContent = message;
        if (proceedBtn) {
            proceedBtn.textContent = confirmText;
            if (isDanger) {
                proceedBtn.style.background = 'var(--color-error)';
                proceedBtn.style.borderColor = 'var(--color-error)';
            } else {
                proceedBtn.style.background = 'var(--color-primary)';
                proceedBtn.style.borderColor = 'var(--color-primary)';
            }
        }

        pendingAction = options.onConfirm || null;
        modal.style.display = 'flex';
    };

    function hideModal() {
        if (modal) modal.style.display = 'none';
        pendingAction = null;
    }

    if (closeBtn) closeBtn.addEventListener('click', hideModal);
    if (cancelBtn) cancelBtn.addEventListener('click', hideModal);
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) hideModal();
        });
    }

    if (proceedBtn) {
        proceedBtn.addEventListener('click', function() {
            const action = pendingAction;
            hideModal();
            if (typeof action === 'function') {
                action();
            }
        });
    }

    // Intercept forms or buttons with data-confirm
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (!form || !form.hasAttribute('data-confirm')) return;
        if (form.dataset.confirmed === 'true') {
            form.removeAttribute('data-confirmed');
            return;
        }

        e.preventDefault();
        window.showConfirmDialog({
            title: form.getAttribute('data-confirm-title') || 'Confirm Action',
            message: form.getAttribute('data-confirm'),
            confirmText: form.getAttribute('data-confirm-btn') || 'Yes, Proceed',
            danger: form.getAttribute('data-confirm-danger') !== 'false',
            onConfirm: function() {
                form.dataset.confirmed = 'true';
                // Trigger submission with original submit button if available
                const submitBtn = form.querySelector('[type="submit"][name]');
                if (submitBtn) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = submitBtn.name;
                    hidden.value = submitBtn.value || '1';
                    form.appendChild(hidden);
                }
                form.submit();
            }
        });
    });
})();
</script>
