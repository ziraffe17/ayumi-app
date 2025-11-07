<!-- ========================================
     resources/views/layouts/partials/staff-scripts.blade.php
     職員用JavaScript
     ======================================== -->
<script>
    // CSRF トークン設定
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    // グローバル通知関数
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert ${type}`;
        notification.style.cssText = 'position:fixed;top:20px;right:20px;z-index:1000;min-width:250px;box-shadow:0 4px 6px rgba(0,0,0,.1);animation:slideIn .3s ease';
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut .3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    // アニメーション定義
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
</script>