<!-- ========================================
     resources/views/layouts/partials/staff-sidebar.blade.php
     職員用サイドバー
     ======================================== -->
<aside class="aside">
    <h4>支援員メニュー</h4>
    
    <h4>ダッシュボード</h4>
    <ul>
        <li>
            <a href="{{ route('staff.dashboards.organization') }}" 
               class="{{ request()->routeIs('staff.dashboards.organization') ? 'active' : '' }}">
                ・事業所
            </a>
        </li>
        <li>
            <a href="{{ route('staff.dashboards.personal') }}" 
               class="{{ request()->routeIs('staff.dashboards.personal') ? 'active' : '' }}">
                ・個人
            </a>
        </li>
    </ul>
    
    <h4>予定・実績</h4>
    <ul>
        <li>
            <a href="{{ route('staff.plans.monthly') }}" 
               class="{{ request()->routeIs('staff.plans.*') ? 'active' : '' }}">
                ・月次予定
            </a>
        </li>
        <li>
            <a href="{{ route('staff.attendance.manage') }}" 
               class="{{ request()->routeIs('staff.attendance.*') ? 'active' : '' }}">
                ・出席管理
            </a>
        </li>
        <li>
            <a href="{{ route('staff.reports.daily') }}" 
               class="{{ request()->routeIs('staff.reports.*') ? 'active' : '' }}">
                ・日報
            </a>
        </li>
    </ul>
    
    <h4>利用者管理</h4>
    <ul>
        <li>
            <a href="{{ route('staff.users.index') }}" 
               class="{{ request()->routeIs('staff.users.*') ? 'active' : '' }}">
                ・一覧
            </a>
        </li>
        <li>
            <a href="{{ route('staff.interviews.index') }}" 
               class="{{ request()->routeIs('staff.interviews.*') ? 'active' : '' }}">
                ・面談記録
            </a>
        </li>
    </ul>
    
    <h4>CSV出力</h4>
    <ul>
        <li>
            <a href="{{ route('staff.export.csv') }}"
               class="{{ request()->routeIs('staff.export.*') ? 'active' : '' }}">
                ・出力
            </a>
        </li>
    </ul>

    @if(auth()->guard('staff')->check() && auth()->guard('staff')->user()->role === 'admin')
    <h4>管理</h4>
    <ul>
        <li>
            <a href="{{ route('staff.settings.index') }}"
               class="{{ request()->routeIs('staff.settings.*') ? 'active' : '' }}">
                ・設定
            </a>
        </li>
        <li>
            <a href="{{ route('staff.audit-logs.index') }}"
               class="{{ request()->routeIs('staff.audit-logs.*') ? 'active' : '' }}">
                ・監査ログ
            </a>
        </li>
    </ul>
    @endif
</aside>