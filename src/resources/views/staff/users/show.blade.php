{{-- resources/views/staff/users/show.blade.php --}}
@extends('layouts.staff')

@section('title', '利用者詳細 - ' . ($user->name ?? ''))

@section('styles')
<style>
    .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
    .header h2{margin:0;color:var(--deep)}
    .card{background:#fff;border-radius:8px;padding:20px;margin:12px 0;border:1px solid var(--line)}
    .card h3{margin:0 0 16px;font-size:16px;color:#374151;border-bottom:2px solid var(--line);padding-bottom:8px}
    .info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px}
    .info-item{padding:8px 0}
    .info-label{font-size:12px;color:#6b7280;margin-bottom:4px}
    .info-value{font-size:15px;font-weight:600;color:#111}
    .badge{padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600}
    .badge.active{background:#dcfce7;color:#166534}
    .badge.inactive{background:#fee2e2;color:#dc2626}
    .timeline{margin-top:12px}
    .timeline-item{border-left:2px solid var(--line);padding-left:16px;margin-bottom:16px;position:relative}
    .timeline-item::before{content:'';position:absolute;left:-5px;top:0;width:8px;height:8px;border-radius:50%;background:var(--deep)}
    .timeline-date{font-size:12px;color:#6b7280;margin-bottom:4px}
    .timeline-content{font-size:14px;color:#111}
    .alert{padding:12px;border-radius:6px;margin:12px 0;font-size:14px}
    .alert.warning{background:#fef3c7;border:1px solid #f59e0b;color:#92400e}
</style>
@endsection

@section('content')
<div class="header">
    <h2>{{ $user->name ?? '' }}</h2>
    <div style="display:flex;gap:8px">
        <a href="{{ route('staff.dashboards.personal', ['user_id' => $user->id]) }}" class="btn primary">ダッシュボード</a>
        <a href="{{ route('staff.users.edit', $user->id) }}" class="btn">編集</a>
        <a href="{{ route('staff.users.index') }}" class="btn">一覧に戻る</a>
        @if($user->is_active)
        <form method="POST" action="{{ route('staff.users.destroy', $user) }}"
              onsubmit="return confirm('利用者「{{ $user->name }}」を無効化しますか？\nこの操作により、利用終了日が今日に設定され、ログインできなくなります。')"
              style="display:inline;margin:0">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn" style="background:#dc2626;color:white;border-color:#dc2626">
                利用者無効化
            </button>
        </form>
        @endif
    </div>
</div>

@if($user->end_date && $user->end_date < now())
<div class="alert warning">
    この利用者は {{ $user->end_date->format('Y-m-d') }} に利用終了しています
</div>
@endif

<!-- 基本情報 -->
<div class="card">
    <h3>基本情報</h3>
    <div class="info-grid">
        <div class="info-item">
            <div class="info-label">ID</div>
            <div class="info-value">{{ $user->id }}</div>
        </div>
        <div class="info-item">
            <div class="info-label">氏名</div>
            <div class="info-value">{{ $user->name }}</div>
        </div>
        <div class="info-item">
            <div class="info-label">氏名カナ</div>
            <div class="info-value">{{ $user->name_kana ?? '-' }}</div>
        </div>
        <div class="info-item">
            <div class="info-label">ログインコード</div>
            <div class="info-value" style="font-family:monospace">{{ $user->login_code }}</div>
        </div>
        <div class="info-item">
            <div class="info-label">メールアドレス</div>
            <div class="info-value">{{ $user->email ?? '未登録' }}</div>
        </div>
        <div class="info-item">
            <div class="info-label">利用開始日</div>
            <div class="info-value">{{ $user->start_date?->format('Y-m-d') ?? '-' }}</div>
        </div>
        <div class="info-item">
            <div class="info-label">利用終了日</div>
            <div class="info-value">{{ $user->end_date?->format('Y-m-d') ?? '未設定' }}</div>
        </div>
        <div class="info-item">
            <div class="info-label">状態</div>
            <div class="info-value">
                <span class="badge {{ $user->is_active ? 'active' : 'inactive' }}">
                    {{ $user->is_active ? '有効' : '無効' }}
                </span>
            </div>
        </div>
        <div class="info-item">
            <div class="info-label">最終ログイン</div>
            <div class="info-value">{{ $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i') : '未ログイン' }}</div>
        </div>
    </div>

    @if($user->care_notes_enc)
    <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--line)">
        <div class="info-label">配慮事項（暗号化保存）</div>
        <div style="background:#f9fafb;padding:12px;border-radius:6px;margin-top:8px">
            {{ $user->care_notes_enc }}
        </div>
    </div>
    @endif
</div>

<!-- 今月の状況 -->
<div class="card">
    <h3>今月の状況</h3>
    <div class="info-grid">
        <div class="info-item">
            <div class="info-label">計画日数</div>
            <div class="info-value">{{ $currentMonth['planned'] ?? 0 }} 日</div>
        </div>
        <div class="info-item">
            <div class="info-label">実出席日数</div>
            <div class="info-value">{{ $currentMonth['actual'] ?? 0 }} 日</div>
        </div>
        <div class="info-item">
            <div class="info-label">出席率</div>
            <div class="info-value">{{ $currentMonth['rate'] ?? '-' }}</div>
        </div>
        <div class="info-item">
            <div class="info-label">日報入力率</div>
            <div class="info-value">{{ $currentMonth['report_rate'] ?? '-' }}</div>
        </div>
    </div>
</div>

<!-- 最近の面談記録 -->
<div class="card">
    <h3>最近の面談記録</h3>
    @forelse($recentInterviews ?? [] as $interview)
    <div class="timeline">
        <div class="timeline-item">
            <div class="timeline-date">{{ $interview->interview_at?->format('Y-m-d H:i') ?? $interview->interview_at }} - 担当: {{ $interview->staff->name ?? '-' }}</div>
            <div class="timeline-content">{{ Str::limit($interview->summary_enc, 150) }}</div>
            @if($interview->next_action)
            <div style="margin-top:6px;color:#6b7280;font-size:13px">
                📌 次回: {{ Str::limit($interview->next_action, 80) }}
            </div>
            @endif
        </div>
    </div>
    @empty
    <div style="text-align:center;padding:20px;color:#6b7280">面談記録がありません</div>
    @endforelse
    <div style="margin-top:12px">
        <a href="{{ route('staff.interviews.index', ['user_id' => $user->id]) }}" class="btn">面談記録を全て見る</a>
    </div>
</div>

<!-- 操作履歴 -->
<div class="card">
    <h3>最近の更新履歴</h3>
    <div class="info-grid">
        <div class="info-item">
            <div class="info-label">作成日時</div>
            <div class="info-value">{{ $user->created_at->format('Y-m-d H:i') }}</div>
        </div>
        <div class="info-item">
            <div class="info-label">作成者</div>
            <div class="info-value">{{ optional($user->creator)->name ?? '-' }}</div>
        </div>
        <div class="info-item">
            <div class="info-label">最終更新</div>
            <div class="info-value">{{ $user->updated_at->format('Y-m-d H:i') }}</div>
        </div>
        <div class="info-item">
            <div class="info-label">更新者</div>
            <div class="info-value">{{ optional($user->updater)->name ?? '-' }}</div>
        </div>
    </div>
</div>
@endsection