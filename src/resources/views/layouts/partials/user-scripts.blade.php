<!-- ========================================
     resources/views/layouts/partials/user-scripts.blade.php
     利用者用JavaScript
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

    // 現在時刻表示
    function updateCurrentTime() {
        const now = new Date();
        const timeString = now.toLocaleString('ja-JP', {
            year: 'numeric',
            month: 'numeric',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        
        const timeElement = document.getElementById('currentTime');
        if (timeElement) {
            timeElement.textContent = timeString;
        }
    }

    // ユーザーメニューの表示/非表示切り替え
    function toggleUserMenu() {
        const popup = document.getElementById('userMenuPopup');
        if (popup) {
            popup.style.display = popup.style.display === 'none' ? 'block' : 'none';
        }
    }

    // 1秒ごとに時刻を更新
    document.addEventListener('DOMContentLoaded', function() {
        updateCurrentTime();
        setInterval(updateCurrentTime, 1000);
        
        // ページアクセス時間の記録
        if (typeof performance !== 'undefined' && performance.now) {
            console.log(`ページ読み込み時間: ${Math.round(performance.now())}ms`);
        }
        
        // ポップアップメニューの外側クリックで閉じる
        document.addEventListener('click', function(e) {
            const popup = document.getElementById('userMenuPopup');
            const userProfile = document.querySelector('.user-profile');
            
            if (popup && userProfile && !userProfile.contains(e.target) && !popup.contains(e.target)) {
                popup.style.display = 'none';
            }
        });
    });

    // フォーカス管理（アクセシビリティ向上）
    document.addEventListener('keydown', function(e) {
        // Escキーでモーダルを閉じる
        if (e.key === 'Escape') {
            const modals = document.querySelectorAll('.modal.show');
            modals.forEach(modal => {
                if (typeof closeModal === 'function') {
                    closeModal();
                }
            });
        }
        
        // Alt + H でホームに移動
        if (e.altKey && e.key === 'h') {
            e.preventDefault();
            window.location.href = '{{ route("user.home") }}';
        }
        
        // Alt + S で設定画面に移動
        if (e.altKey && e.key === 's') {
            e.preventDefault();
            window.location.href = '{{ route("user.settings.index") }}';
        }
    });

    // オフライン状態の検知
    window.addEventListener('online', function() {
        showNotification('インターネット接続が復旧しました', 'success');
    });
    
    window.addEventListener('offline', function() {
        showNotification('インターネット接続が切断されました', 'warning');
    });
</script>