<!-- ========================================
     resources/views/layouts/partials/staff-tabs.blade.php
     職員用タブナビゲーション（2段階構造）
     ======================================== -->

{{-- 第1階層タブ --}}
<nav class="primary-tabs">
    <a href="{{ route('staff.home') }}"
       class="{{ request()->routeIs('staff.home') ? 'active' : '' }}"
       style="filter: grayscale(100%);">
        🏠
    </a>
    <a href="{{ route('staff.dashboards.organization') }}"
       class="{{ request()->routeIs('staff.dashboards.*') ? 'active' : '' }}">
        ダッシュボード
    </a>
    <a href="{{ route('staff.plans.monthly') }}"
       class="{{ request()->routeIs('staff.plans.*') || request()->routeIs('staff.attendance.*') || request()->routeIs('staff.reports.*') ? 'active' : '' }}">
        予定・実績
    </a>
    <a href="{{ route('staff.users.index') }}"
       class="{{ request()->routeIs('staff.users.*') || request()->routeIs('staff.interviews.*') ? 'active' : '' }}">
        利用者管理
    </a>
    <a href="{{ route('staff.export.csv') }}"
       class="{{ request()->routeIs('staff.export.*') ? 'active' : '' }}">
        CSV出力
    </a>
    @if(auth()->guard('staff')->check() && auth()->guard('staff')->user()->role === 'admin')
    <a href="{{ route('staff.settings.index') }}"
       class="{{ request()->routeIs('staff.settings.*') || request()->routeIs('staff.audit-logs.*') ? 'active' : '' }}">
        設定
    </a>
    @endif
</nav>

{{-- 第2階層タブ（ダッシュボード） --}}
@if(request()->routeIs('staff.dashboards.*'))
<nav class="secondary-tabs">
    <a href="{{ route('staff.dashboards.organization') }}"
       class="{{ request()->routeIs('staff.dashboards.organization') ? 'active' : '' }}">
        事業所ダッシュボード
    </a>
    <a href="{{ route('staff.dashboards.personal') }}"
       class="{{ request()->routeIs('staff.dashboards.personal') ? 'active' : '' }}">
        個人ダッシュボード
    </a>
</nav>
@endif

{{-- 第2階層タブ（予定・実績） --}}
@if(request()->routeIs('staff.plans.*') || request()->routeIs('staff.attendance.*') || request()->routeIs('staff.reports.*'))
<nav class="secondary-tabs">
    <a href="{{ route('staff.plans.monthly') }}"
       class="{{ request()->routeIs('staff.plans.*') ? 'active' : '' }}">
        月次予定
    </a>
    <a href="{{ route('staff.attendance.manage') }}"
       class="{{ request()->routeIs('staff.attendance.*') ? 'active' : '' }}">
        出席管理
    </a>
    <a href="{{ route('staff.reports.daily') }}"
       class="{{ request()->routeIs('staff.reports.*') ? 'active' : '' }}">
        日報
    </a>
</nav>
@endif

{{-- 第2階層タブ（利用者管理） --}}
@if(request()->routeIs('staff.users.*') || request()->routeIs('staff.interviews.*'))
<nav class="secondary-tabs">
    <a href="{{ route('staff.users.index') }}"
       class="{{ request()->routeIs('staff.users.*') ? 'active' : '' }}">
        利用者一覧
    </a>
    @if(config('app.features.interview', false))
    <a href="{{ route('staff.interviews.index') }}"
       class="{{ request()->routeIs('staff.interviews.*') ? 'active' : '' }}">
        面談記録
    </a>
    @endif
</nav>
@endif

{{-- 第2階層タブ（設定） --}}
@if((request()->routeIs('staff.settings.*') || request()->routeIs('staff.audit-logs.*')) && auth()->guard('staff')->check() && auth()->guard('staff')->user()->role === 'admin')
<nav class="secondary-tabs">
    <a href="{{ route('staff.settings.index') }}"
       class="{{ request()->routeIs('staff.settings.*') ? 'active' : '' }}">
        設定
    </a>
    <a href="{{ route('staff.audit-logs.index') }}"
       class="{{ request()->routeIs('staff.audit-logs.*') ? 'active' : '' }}">
        監査ログ
    </a>
</nav>
@endif