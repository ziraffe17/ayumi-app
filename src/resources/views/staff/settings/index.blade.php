{{-- resources/views/staff/settings/index.blade.php --}}
@extends('layouts.staff')

@section('title', 'S-11 設定')

@section('styles')
<style>
    .card{background:#fff;border-radius:8px;padding:24px;margin:16px 0;border:1px solid var(--line)}
    .card h3{margin:0 0 16px;color:var(--deep);font-size:18px}
    .form-group{margin:16px 0}
    .form-group label{display:block;margin-bottom:6px;font-weight:600;color:#374151}
    .help-text{font-size:12px;color:#6b7280;margin-top:4px}
    input,select,textarea{width:100%;border:1px solid var(--line);padding:8px 12px;border-radius:6px;font-size:14px}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{padding:10px;text-align:left;border-bottom:1px solid var(--line)}
    th{background:#f3f4f6;font-weight:600}
    .alert{padding:12px;border-radius:6px;margin:12px 0;font-size:14px}
    .alert.success{background:#dcfce7;border:1px solid #86efac;color:#166534}
    .alert.error{background:#fee2e2;border:1px solid #fca5a5;color:#dc2626}
</style>
@endsection

@section('content')
<h2>設定</h2>

@if(session('success'))
<div class="alert success" id="successAlert">{{ session('success') }}</div>
@endif

@if(session('error'))
<div class="alert error" id="errorAlert">{{ session('error') }}</div>
@endif

<!-- 事業所情報 -->
<div class="card">
    <h3>🏢 事業所情報</h3>
    <form method="POST" action="{{ route('staff.settings.update-organization') }}">
        @csrf
        <div class="form-group">
            <label>事業所名</label>
            <input type="text" name="org_name" value="{{ $settings['org_name'] ?? '' }}" required>
        </div>
        <div class="form-group">
            <label>郵便番号</label>
            <input type="text" name="org_postal_code" value="{{ $settings['org_postal_code'] ?? '' }}" placeholder="000-0000">
        </div>
        <div class="form-group">
            <label>住所</label>
            <input type="text" name="org_address" value="{{ $settings['org_address'] ?? '' }}">
        </div>
        <div class="form-group">
            <label>電話番号</label>
            <input type="text" name="org_phone" value="{{ $settings['org_phone'] ?? '' }}" placeholder="00-0000-0000">
        </div>
        <button type="submit" class="btn primary">保存</button>
    </form>
</div>

<!-- システム設定 -->
<div class="card">
    <h3>⚙️ システム設定</h3>
    <form method="POST" action="{{ route('staff.settings.update-system') }}">
        @csrf
        <div class="form-group">
            <label>定員数</label>
            <select name="facility_capacity">
                <option value="20" {{ ($facilityCapacity ?? 20) == 20 ? 'selected' : '' }}>20名</option>
                <option value="25" {{ ($facilityCapacity ?? 20) == 25 ? 'selected' : '' }}>25名</option>
            </select>
            <div class="help-text">事業所の定員数を設定します。稼働率の計算に使用されます。</div>
        </div>

        <div class="form-group">
            <label>出席率計算基準</label>
            <select name="attendance_base">
                <option value="plan" {{ ($settings['attendance_base'] ?? 'plan') == 'plan' ? 'selected' : '' }}>
                    計画日ベース（予定を登録した日のみ）
                </option>
                <option value="all_weekdays" {{ ($settings['attendance_base'] ?? 'plan') == 'all_weekdays' ? 'selected' : '' }}>
                    全平日ベース（祝日除く月～金）
                </option>
            </select>
            <div class="help-text">現在: 計画日ベース（祝日も予定があれば対象）</div>
        </div>

        <div class="form-group">
            <label>日報入力期限</label>
            <select name="report_deadline_days">
                <option value="0" {{ ($settings['report_deadline_days'] ?? 3) == 0 ? 'selected' : '' }}>当日のみ</option>
                <option value="3" {{ ($settings['report_deadline_days'] ?? 3) == 3 ? 'selected' : '' }}>3日以内</option>
                <option value="7" {{ ($settings['report_deadline_days'] ?? 3) == 7 ? 'selected' : '' }}>7日以内</option>
            </select>
            <div class="help-text">過去何日前まで入力可能か</div>
        </div>

        <div class="form-group">
            <label>ログ保持期間</label>
            <select name="log_retention_days">
                <option value="90" {{ ($settings['log_retention_days'] ?? 365) == 90 ? 'selected' : '' }}>90日</option>
                <option value="180" {{ ($settings['log_retention_days'] ?? 365) == 180 ? 'selected' : '' }}>180日</option>
                <option value="365" {{ ($settings['log_retention_days'] ?? 365) == 365 ? 'selected' : '' }}>365日</option>
                <option value="730" {{ ($settings['log_retention_days'] ?? 365) == 730 ? 'selected' : '' }}>2年</option>
            </select>
        </div>

        <button type="submit" class="btn primary">保存</button>
    </form>
</div>

<!-- 祝日設定 -->
<div class="card">
    <h3>🗓️ 祝日設定</h3>
    <p style="color:#6b7280;margin-bottom:16px">
        祝日カレンダーの取り込み・管理ができます。予定登録時に祝日名が自動表示されます。
    </p>

    <!-- 祝日統計 -->
    <div style="background:#f9fafb;padding:12px;border-radius:6px;margin-bottom:16px;display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;">
        <div style="text-align:center">
            <div style="font-size:20px;font-weight:700;color:var(--deep)">{{ $holidayStats['total'] ?? 0 }}</div>
            <div style="font-size:12px;color:#6b7280">総件数</div>
        </div>
        <div style="text-align:center">
            <div style="font-size:20px;font-weight:700;color:var(--deep)">{{ $holidayStats['current_year'] ?? 0 }}</div>
            <div style="font-size:12px;color:#6b7280">今年</div>
        </div>
        <div style="text-align:center">
            <div style="font-size:20px;font-weight:700;color:var(--deep)">{{ $holidayStats['next_year'] ?? 0 }}</div>
            <div style="font-size:12px;color:#6b7280">来年</div>
        </div>
        <div style="text-align:center">
            <div style="font-size:12px;color:#6b7280">最終更新</div>
            <div style="font-size:11px;color:#6b7280">{{ $holidayStats['last_import'] ? \Carbon\Carbon::parse($holidayStats['last_import'])->format('m/d H:i') : '未実行' }}</div>
        </div>
    </div>

    <!-- 政府APIから取り込み -->
    <div style="background:#e0f2fe;padding:16px;border-radius:6px;margin-bottom:16px;">
        <h4 style="margin:0 0 12px;color:#0277bd">🌐 政府API自動取り込み</h4>
        <p style="margin:0 0 12px;font-size:13px;color:#0277bd">内閣府の公式祝日データから最新情報を取得します</p>
        <form method="POST" action="{{ route('staff.settings.import-from-api') }}" style="display:flex;gap:8px;align-items:end">
            @csrf
            <div style="flex:1">
                <label style="font-size:12px;color:#0277bd">対象年（空欄で今年・来年）</label>
                <input type="number" name="year" min="2020" max="2030" placeholder="例: 2025" style="width:100px">
            </div>
            <button type="submit" class="btn primary">API取り込み</button>
        </form>
    </div>

    <!-- 個別手動入力 -->
    <div style="background:#f0fdf4;padding:16px;border-radius:6px;margin-bottom:16px;">
        <h4 style="margin:0 0 12px;color:#16a34a">✏️ 個別手動入力</h4>
        <p style="margin:0 0 12px;font-size:13px;color:#16a34a">祝日を1件ずつ手動で登録します</p>
        <form method="POST" action="{{ route('staff.settings.add-holiday') }}" style="display:grid;grid-template-columns:auto auto 1fr auto;gap:8px;align-items:end">
            @csrf
            <div>
                <label style="font-size:12px;color:#16a34a">日付</label>
                <input type="date" name="holiday_date" required style="width:140px">
            </div>
            <div>
                <label style="font-size:12px;color:#16a34a">祝日名</label>
                <input type="text" name="holiday_name" required placeholder="例: 元日" style="width:160px">
            </div>
            <div></div>
            <button type="submit" class="btn primary">追加</button>
        </form>
    </div>

    <!-- CSVファイル取り込み -->
    <div style="background:#fef3c7;padding:16px;border-radius:6px;margin-bottom:16px;">
        <h4 style="margin:0 0 12px;color:#d97706">📄 CSV一括取り込み</h4>
        <p style="margin:0 0 12px;font-size:13px;color:#d97706">複数の祝日をCSVファイルから一括登録します</p>
        <form method="POST" action="{{ route('staff.settings.import-holidays') }}" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:end">
            @csrf
            <div style="flex:1">
                <label style="font-size:12px;color:#d97706">CSVファイル</label>
                <input type="file" name="holiday_csv" accept=".csv" required>
                <div style="font-size:11px;color:#d97706;margin-top:4px">
                    形式: 日付(YYYY-MM-DD),祝日名（例: 2025-01-01,元日）
                </div>
            </div>
            <button type="submit" class="btn">CSV取り込み</button>
        </form>
    </div>

    <!-- データ管理 -->
    <div style="background:#fef7ed;padding:16px;border-radius:6px;margin:16px 0;">
        <h4 style="margin:0 0 12px;color:#ea580c">🗑️ データ管理</h4>
        <p style="margin:0 0 12px;font-size:13px;color:#ea580c">古い祝日データを削除してデータベースを最適化できます</p>
        <form method="POST" action="{{ route('staff.settings.cleanup-holidays') }}" style="display:flex;gap:8px;align-items:end">
            @csrf
            <div style="flex:1">
                <label style="font-size:12px;color:#ea580c">保持期間</label>
                <select name="keep_years" style="width:120px">
                    <option value="2">2年</option>
                    <option value="3" selected>3年</option>
                    <option value="5">5年</option>
                </select>
            </div>
            <button type="submit" class="btn" style="background:#ea580c;color:white;border-color:#ea580c"
                    onclick="return confirm('古い祝日データを削除しますか？')">データ削除</button>
        </form>
    </div>

    <h4 style="margin:24px 0 12px">登録済み祝日（直近50件）</h4>
    <div style="max-height:300px;overflow-y:auto">
        <table>
            <thead>
                <tr>
                    <th>日付</th>
                    <th>祝日名</th>
                    <th>登録元</th>
                    <th>取込日時</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse($holidays ?? [] as $holiday)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($holiday->holiday_date)->format('Y-m-d') }}</td>
                    <td>{{ $holiday->name }}</td>
                    <td>
                        @switch($holiday->source)
                            @case('government_api')
                                <span style="color:#0277bd">🌐 政府API</span>
                                @break
                            @case('csv_import')
                                <span style="color:#16a34a">📄 CSV</span>
                                @break
                            @case('basic')
                                <span style="color:#f59e0b">⚙️ 基本</span>
                                @break
                            @default
                                <span style="color:#6b7280">📝 手動</span>
                        @endswitch
                    </td>
                    <td style="font-size:11px;color:#6b7280">
                        {{ $holiday->imported_at ? $holiday->imported_at->format('m/d H:i') : '-' }}
                    </td>
                    <td>
                        <form method="POST" action="{{ route('staff.settings.delete-holiday', $holiday->holiday_date) }}" style="display:inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn" style="font-size:11px;padding:4px 8px"
                                    onclick="return confirm('削除しますか？')">削除</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" style="text-align:center;color:#6b7280">祝日が登録されていません</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- バックアップ（実装予定） -->
<!--
<div class="card">
    <h3>💾 バックアップ</h3>
    <p style="color:#6b7280;margin-bottom:16px">
        データベースの手動バックアップを実行できます。
    </p>
    <form method="POST" action="{{ route('staff.settings.backup') }}">
        @csrf
        <button type="submit" class="btn primary">バックアップ実行</button>
    </form>
    <div class="help-text" style="margin-top:12px">
        最終バックアップ: {{ $lastBackup ?? '未実行' }}
    </div>
</div>
-->
@endsection

@section('scripts')
<script>
// アラートを3秒後に自動的にフェードアウト
document.addEventListener('DOMContentLoaded', () => {
    const successAlert = document.getElementById('successAlert');
    const errorAlert = document.getElementById('errorAlert');

    if (successAlert) {
        setTimeout(() => {
            successAlert.style.transition = 'opacity 0.5s';
            successAlert.style.opacity = '0';
            setTimeout(() => successAlert.remove(), 500);
        }, 3000);
    }

    if (errorAlert) {
        setTimeout(() => {
            errorAlert.style.transition = 'opacity 0.5s';
            errorAlert.style.opacity = '0';
            setTimeout(() => errorAlert.remove(), 500);
        }, 5000); // エラーは5秒表示
    }
});
</script>
@endsection
