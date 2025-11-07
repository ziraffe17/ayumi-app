{{-- resources/views/staff/users/index.blade.php --}}
@extends('layouts.staff')

@section('title', 'S-08 利用者一覧')

@section('styles')
<style>
    .toolbar{display:flex;gap:8px;margin:12px 0;align-items:center}
    input,select{border:1px solid var(--line);padding:6px 10px;border-radius:6px}
    table{width:100%;border-collapse:collapse;background:#fff}
    th,td{padding:12px;text-align:left;border-bottom:1px solid var(--line)}
    th{background:#f3f4f6;font-weight:600}
    tr:hover{background:#f9fafb}
    .badge{padding:2px 8px;border-radius:4px;font-size:12px}
    .badge.active{background:#dcfce7;color:#166534}
    .badge.inactive{background:#fee2e2;color:#dc2626}
</style>
@endsection

@section('content')
<h2 style="margin:0 0 16px">利用者一覧</h2>

<div class="toolbar">
    <input type="text" id="searchInput" placeholder="氏名で検索" style="width:240px">
    <select id="statusFilter">
        <option value="">全て</option>
        <option value="1">有効</option>
        <option value="0">無効</option>
    </select>
    <button class="btn" onclick="search()">検索</button>
    <button class="btn primary" onclick="location.href='{{ route('staff.users.create') }}'">新規登録</button>
</div>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>氏名</th>
            <th>ログインコード</th>
            <th>利用開始日</th>
            <th>利用終了日</th>
            <th>状態</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody id="userTable">
        @forelse($users ?? [] as $user)
        <tr>
            <td>{{ $user->id }}</td>
            <td>{{ $user->name }}</td>
            <td>{{ $user->login_code }}</td>
            <td>{{ $user->start_date?->format('Y-m-d') ?? '-' }}</td>
            <td>{{ $user->end_date?->format('Y-m-d') ?? '-' }}</td>
            <td>
                <span class="badge {{ $user->is_active ? 'active' : 'inactive' }}">
                    {{ $user->is_active ? '有効' : '無効' }}
                </span>
            </td>
            <td>
                <a href="{{ route('staff.users.show', $user->id) }}" class="btn">詳細</a>
                <a href="{{ route('staff.dashboards.personal', ['user_id' => $user->id]) }}" class="btn">ダッシュ</a>
                @if($user->is_active)
                <form method="POST" action="{{ route('staff.users.destroy', $user) }}"
                      onsubmit="return confirm('利用者「{{ $user->name }}」を無効化しますか？')"
                      style="display:inline;margin:0">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn" style="background:#dc2626;color:white;border:1px solid #dc2626;padding:4px 12px;font-size:13px">
                        無効化
                    </button>
                </form>
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;color:#6b7280">利用者が登録されていません</td></tr>
        @endforelse
    </tbody>
</table>

<div style="margin-top:16px">
    {{ $users->links() ?? '' }}
</div>
@endsection

@section('scripts')
<script>
function search() {
    const q = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    let url = '{{ route('staff.users.index') }}?';
    if (q) url += `q=${encodeURIComponent(q)}&`;
    if (status) url += `status=${status}`;
    location.href = url;
}

document.getElementById('searchInput').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') search();
});
</script>
@endsection