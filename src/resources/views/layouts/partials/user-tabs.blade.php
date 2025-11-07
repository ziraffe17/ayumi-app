<!-- ========================================
     resources/views/layouts/partials/user-tabs.blade.php
     利用者用タブナビゲーション
     ======================================== -->
<nav class="nav-tabs">
    <a href="{{ route('user.dashboard') }}" 
       class="{{ request()->routeIs('user.dashboard') ? 'active' : '' }}">
        ダッシュボード
    </a>
    <a href="{{ route('user.plans.monthly') }}" 
       class="{{ request()->routeIs('user.plans.*') ? 'active' : '' }}">
        出席予定
    </a>
    <a href="{{ route('user.reports.daily') }}" 
       class="{{ request()->routeIs('user.reports.*') ? 'active' : '' }}">
        日報入力
    </a>
</nav>