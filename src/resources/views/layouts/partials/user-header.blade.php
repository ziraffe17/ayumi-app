<!-- ========================================
     resources/views/layouts/partials/user-header.blade.php
     利用者用ヘッダー
     ======================================== -->
<div class="header">
    <div class="header-left">
        <h1>あゆみ</h1>
    </div>
    
    <div class="header-center">
        <a href="{{ route('user.home') }}" class="home-icon" title="ホーム">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M3 9L12 2L21 9V20C21 20.5304 20.7893 21.0391 20.4142 21.4142C20.0391 21.7893 19.5304 22 19 22H5C4.46957 22 3.96086 21.7893 3.58579 21.4142C3.21071 21.0391 3 20.5304 3 20V9Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M9 22V12H15V22" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </a>
    </div>
    
    <div class="header-right">
        <div class="user-profile" onclick="toggleUserMenu()" style="cursor: pointer;">
            <div class="user-avatar">
                {{ mb_substr(Auth::user()->name ?? '利', 0, 1) }}
            </div>
            <div class="user-details">
                <span class="user-name">{{ Auth::user()->name ?? '利用者' }}さん</span>
                <span class="user-status">
                    @if(Auth::user()->is_active ?? true)
                        <span style="color: #16a34a;">● 利用中</span>
                    @else
                        <span style="color: #dc2626;">● 停止中</span>
                    @endif
                </span>
            </div>
        </div>
        
        <!-- ユーザーメニューポップアップ -->
        <div class="user-menu-popup" id="userMenuPopup" style="display: none;">
            <a href="{{ route('user.settings.index') }}" class="user-menu-item">
                設定
            </a>
            <form method="POST" action="{{ route('user.logout') }}" style="margin: 0;">
                @csrf
                <button type="submit" class="user-menu-item logout-item" 
                        onclick="return confirm('ログアウトしますか？')">
                    ログアウト
                </button>
            </form>
        </div>
    </div>
</div>
