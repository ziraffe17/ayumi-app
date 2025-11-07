<!-- ========================================
     resources/views/layouts/partials/staff-header.blade.php
     職員用ヘッダー
     ======================================== -->
<div class="topbar">
    <a class="title" href="{{ route('staff.home') }}">あゆみ</a>
    <div class="user">
        {{ Auth::guard('staff')->user()->name ?? '職員' }}
        <form method="POST" action="{{ route('staff.logout') }}" style="display:inline">
            @csrf
            <button type="submit" class="logout">ログアウト</button>
        </form>
    </div>
</div>