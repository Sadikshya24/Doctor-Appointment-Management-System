<?php $basePath = isset($isSubFolder) && $isSubFolder ? '../' : ''; ?>

<!-- Toast Container for Notifications -->
<div id="toast-container" class="toast-container"></div>

<!-- Custom Confirmation Modal -->
<div id="confirm-overlay" class="confirm-overlay">
    <div id="confirm-modal" class="confirm-modal">
        <i id="confirm-icon" class="fas fa-info-circle confirm-icon-info"></i>
        <h2 id="confirm-title">Are you sure?</h2>
        <p id="confirm-message">This action cannot be undone.</p>
        <div class="confirm-actions">
            <button id="confirm-btn-cancel" class="confirm-btn confirm-btn-cancel">Cancel</button>
            <button id="confirm-btn-action" class="confirm-btn confirm-btn-action">Confirm</button>
        </div>
    </div>
</div>

<script>
function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    let icon = 'info-circle';
    if (type === 'success') icon = 'check-circle';
    if (type === 'error') icon = 'exclamation-circle';
    if (type === 'warning') icon = 'exclamation-triangle';
    
    toast.innerHTML = `<i class="fas fa-${icon} toast-icon"></i> <span>${message}</span>`;
    container.appendChild(toast);
    
    setTimeout(() => toast.classList.add('active'), 10);
    
    setTimeout(() => {
        toast.classList.remove('active');
        setTimeout(() => toast.remove(), 400);
    }, 4000);
}

// Promise-based Custom Confirmation
function showConfirm(options = {}) {
    const {
        title = 'Are you sure?',
        message = 'This action cannot be undone.',
        confirmText = 'Confirm',
        cancelText = 'Cancel',
        type = 'info'
    } = options;

    return new Promise((resolve) => {
        const overlay = document.getElementById('confirm-overlay');
        const modal = document.getElementById('confirm-modal');
        const titleEl = document.getElementById('confirm-title');
        const messageEl = document.getElementById('confirm-message');
        const confirmBtn = document.getElementById('confirm-btn-action');
        const cancelBtn = document.getElementById('confirm-btn-cancel');
        const icon = document.getElementById('confirm-icon');

        titleEl.textContent = title;
        messageEl.textContent = message;
        confirmBtn.textContent = confirmText;
        cancelBtn.textContent = cancelText;

        icon.className = 'fas ' + (type === 'danger' ? 'fa-exclamation-triangle confirm-icon-danger' : 'fa-info-circle confirm-icon-info');
        confirmBtn.className = 'confirm-btn ' + (type === 'danger' ? 'confirm-btn-danger' : 'confirm-btn-action');

        overlay.classList.add('active');

        const handleEscape = (e) => { if(e.key === 'Escape') onCancel(); };
        window.addEventListener('keydown', handleEscape);

        const cleanup = (result) => {
            overlay.classList.remove('active');
            confirmBtn.onclick = null;
            cancelBtn.onclick = null;
            overlay.onclick = null;
            window.removeEventListener('keydown', handleEscape);
            resolve(result);
        };

        const onConfirm = () => cleanup(true);
        const onCancel = () => cleanup(false);

        confirmBtn.onclick = onConfirm;
        cancelBtn.onclick = onCancel;
        overlay.onclick = (e) => { if(e.target === overlay) onCancel(); };
    });
}

window.addEventListener('load', () => {
    <?php if (isset($_SESSION['toast_msg'])): ?>
        showToast("<?php echo addslashes($_SESSION['toast_msg']); ?>", "<?php echo $_SESSION['toast_type'] ?? 'info'; ?>");
        <?php unset($_SESSION['toast_msg'], $_SESSION['toast_type']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['auth_error'])): ?>
        showToast("<?php echo addslashes($_SESSION['auth_error']); ?>", "error");
        <?php unset($_SESSION['auth_error']); ?>
    <?php endif; ?>
    
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out'): ?>
        showToast("You have been logged out successfully.", "info");
    <?php endif; ?>
});
</script>

<script src="<?php echo $basePath; ?>assets/js/common/script.js"></script>
</body>
</html>