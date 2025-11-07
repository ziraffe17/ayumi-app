{{-- resources/views/staff/home.blade.php --}}
@extends('layouts.staff')

@section('title', 'S-02S 職員ホーム')

@section('content')
<h2 style="margin:16px 0">概要</h2>

<div class="cards">
    <div class="card">
        <h3>登録利用者数</h3>
        <div class="value" id="userCount">-</div>
        <div class="desc">有効な利用者アカウント</div>
    </div>
    <div class="card">
        <h3>今月の平均出席率</h3>
        <div class="value" id="avgRate">-</div>
        <div class="desc">事業所全体の平均</div>
    </div>
    <div class="card">
        <h3>未入力日報</h3>
        <div class="value" id="pendingReports">-</div>
        <div class="desc">本日分の未入力件数</div>
    </div>
    <div class="card">
        <h3>未計画利用者</h3>
        <div class="value" id="noPlanUsers">-</div>
        <div class="desc">来月の予定未登録</div>
    </div>
</div>

<h3 style="margin:24px 0 12px">クイックアクション</h3>
<div style="display:flex;gap:12px;flex-wrap:wrap">
    <a href="{{ route('staff.dashboards.organization') }}" class="btn green">事業所ダッシュボード</a>
    <a href="{{ route('staff.plans.monthly') }}" class="btn">月次予定管理</a>
    <a href="{{ route('staff.attendance.manage') }}" class="btn">出席管理</a>
    <a href="{{ route('staff.export.csv') }}" class="btn">CSV出力</a>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', async () => {
    try {
        const res = await fetch('/api/staff/home-stats', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            }
        });

        if (!res.ok) {
            throw new Error(`HTTP ${res.status}: ${res.statusText}`);
        }

        const data = await res.json();

        document.getElementById('userCount').textContent = data.userCount;
        document.getElementById('avgRate').textContent = data.avgRate + '%';
        document.getElementById('pendingReports').textContent = data.pendingReports;
        document.getElementById('noPlanUsers').textContent = data.noPlanUsers;
    } catch (e) {
        console.error('統計情報の取得に失敗', e);
        document.getElementById('userCount').textContent = 'エラー';
        document.getElementById('avgRate').textContent = 'エラー';
        document.getElementById('pendingReports').textContent = 'エラー';
        document.getElementById('noPlanUsers').textContent = 'エラー';
    }
});
</script>
@endsection