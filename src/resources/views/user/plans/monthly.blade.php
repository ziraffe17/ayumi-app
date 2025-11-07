{{-- resources/views/user/plans/monthly.blade.php --}}
@extends('layouts.user')

@section('title', 'S-04U 月次出席予定')

@section('styles')
<style>
    /* ツールバー */
    .toolbar {
        display: flex;
        gap: 16px;
        align-items: center;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }

    .btn {
        padding: 8px 16px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        background: white;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.2s;
    }

    .btn:hover {
        background: #f3f4f6;
    }

    .btn-primary {
        background: #16a34a;
        color: white;
        border-color: #16a34a;
    }

    .btn-primary:hover {
        background: #15803d;
    }

    .btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* カレンダー */
    .calendar-container {
        background: white;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 24px;
    }

    .calendar-title {
        margin-bottom: 16px;
        color: #111827;
        font-size: 18px;
        font-weight: 600;
    }

    .calendar-nav {
        display: flex;
        gap: 12px;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
    }

    .calendar-nav #currentMonth {
        font-weight: 600;
        min-width: 100px;
        text-align: center;
    }

    .calendar-nav .btn {
        padding: 6px 12px;
        font-size: 13px;
        white-space: nowrap;
    }

    .calendar-month {
        font-size: 20px;
        font-weight: bold;
        color: #16a34a;
    }

    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 1px;
        margin-bottom: 16px;
        width: 100%;
        overflow-x: auto;
    }

    .calendar-header-cell {
        background: #f3f4f6;
        padding: 6px 2px;
        text-align: center;
        font-weight: 600;
        font-size: 11px;
        color: #374151;
    }

    .calendar-header-cell.sunday {
        color: #dc2626;
    }

    .calendar-header-cell.saturday {
        color: #2563eb;
    }

    .calendar-cell {
        background: white;
        border: 1px solid #e5e7eb;
        min-height: 90px;
        padding: 4px 2px;
        position: relative;
        font-size: 10px;
        cursor: pointer;
        transition: background-color 0.2s;
        overflow: hidden;
    }

    .calendar-cell:hover {
        background: #f9fafb;
    }

    .calendar-cell.other-month {
        opacity: 0.4;
    }

    .calendar-cell.today {
        background: #fef3c7;
        border-color: #f59e0b;
    }

    .calendar-cell.weekend {
        background: #f9fafb;
        cursor: default;
    }

    .calendar-cell.weekend:hover {
        background: #f9fafb;
    }

    .calendar-day {
        font-weight: bold;
        margin-bottom: 2px;
        font-size: 11px;
        text-align: center;
    }

    .calendar-day.saturday {
        color: #2563eb;
    }

    .calendar-day.sunday {
        color: #dc2626;
    }

    .holiday-name {
        font-size: 8px;
        color: #dc2626;
        margin-bottom: 2px;
        text-align: center;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* 予定表示（ダッシュボード準拠） */
    .plan-item {
        border-radius: 3px;
        padding: 3px 4px;
        margin: 2px 0;
        font-size: 9px;
        text-align: center;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-weight: 500;
    }

    /* 通所 */
    .plan-item.onsite { background: #dcfce7; color: #166534; }
    .plan-item.onsite.full { background: #16a34a; color: #fff; }
    .plan-item.onsite.am { background: #86efac; color: #166534; }
    .plan-item.onsite.pm { background: #bbf7d0; color: #166534; }

    /* 在宅 */
    .plan-item.remote { background: #dbeafe; color: #1d4ed8; }
    .plan-item.remote.full { background: #3b82f6; color: #fff; }
    .plan-item.remote.am { background: #93c5fd; color: #1d4ed8; }
    .plan-item.remote.pm { background: #bfdbfe; color: #1d4ed8; }

    /* 休日 */
    .plan-item.off { background: #f3f4f6; color: #6b7280; }

    .plan-selector {
        margin-top: 4px;
    }

    .plan-select {
        width: 100%;
        padding: 2px;
        font-size: 9px;
        border: 1px solid #d1d5db;
        border-radius: 3px;
        background: white;
    }

    .plan-edit-container {
        display: flex;
        flex-direction: column;
        gap: 2px;
        margin-top: 4px;
    }

    .plan-type-select,
    .plan-slot-select {
        width: 100%;
        padding: 2px 1px;
        font-size: 8px;
        border: 1px solid #d1d5db;
        border-radius: 3px;
        background: white;
        font-weight: 600;
    }

    /* プルダウンの色分け（選択後の閉じた状態のみ） */
    .plan-type-select[data-selected="onsite"] { background:#dcfce7; color:#166534; border-color:#16a34a; }
    .plan-type-select[data-selected="remote"] { background:#dbeafe; color:#1d4ed8; border-color:#3b82f6; }
    .plan-type-select[data-selected="off"] { background:#f3f4f6; color:#6b7280; border-color:#9ca3af; }

    .plan-slot-select[data-parent="onsite"][data-selected="full"] { background:#16a34a; color:#fff; }
    .plan-slot-select[data-parent="onsite"][data-selected="am"] { background:#86efac; color:#166534; }
    .plan-slot-select[data-parent="onsite"][data-selected="pm"] { background:#bbf7d0; color:#166534; }
    .plan-slot-select[data-parent="remote"][data-selected="full"] { background:#3b82f6; color:#fff; }
    .plan-slot-select[data-parent="remote"][data-selected="am"] { background:#93c5fd; color:#1d4ed8; }
    .plan-slot-select[data-parent="remote"][data-selected="pm"] { background:#bfdbfe; color:#1d4ed8; }

    /* 選択肢（option）は常に白背景 */
    .plan-type-select option,
    .plan-slot-select option {
        background: white;
        color: #111827;
    }

    .plan-delete-btn {
        width: 100%;
        padding: 2px;
        font-size: 9px;
        background: #fee2e2;
        color: #dc2626;
        border: 1px solid #fca5a5;
        border-radius: 3px;
        cursor: pointer;
        font-weight: bold;
    }

    .plan-delete-btn:hover {
        background: #fecaca;
    }

    .plan-actions {
        display: flex;
        gap: 2px;
    }

    .plan-btn {
        background: none;
        border: none;
        cursor: pointer;
        padding: 2px 4px;
        border-radius: 2px;
        font-size: 10px;
        transition: background-color 0.2s;
    }

    .plan-btn:hover {
        background: rgba(0,0,0,0.1);
    }

    .add-plan-btn {
        position: absolute;
        bottom: 4px;
        right: 4px;
        background: #f3f4f6;
        border: 1px solid #d1d5db;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .add-plan-btn:hover {
        background: #16a34a;
        color: white;
        border-color: #16a34a;
    }

    /* サマリーカード */
    .summary-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-top: 24px;
    }

    .summary-card {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
        text-align: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .summary-card h3 {
        font-size: 14px;
        color: #6b7280;
        margin-bottom: 8px;
    }

    .summary-card .value {
        font-size: 24px;
        font-weight: bold;
        color: #16a34a;
    }


    @media (max-width: 768px) {
        .calendar-container {
            padding: 16px 8px;
        }

        .calendar-grid {
            gap: 0px;
        }

        .calendar-header-cell {
            padding: 4px 1px;
            font-size: 10px;
        }

        .calendar-cell {
            min-height: 75px;
            padding: 2px 1px;
            font-size: 9px;
        }

        .calendar-day {
            font-size: 10px;
            margin-bottom: 1px;
        }

        .holiday-name {
            font-size: 7px;
        }

        .plan-item {
            font-size: 8px;
            padding: 2px 2px;
            margin: 1px 0;
        }

        .plan-select {
            font-size: 8px;
            padding: 1px;
        }

        .toolbar {
            flex-direction: column;
            align-items: stretch;
        }

        .calendar-nav .btn {
            padding: 5px 8px;
            font-size: 12px;
        }
    }

    @media (max-width: 480px) {
        .calendar-container {
            padding: 12px 4px;
        }

        .calendar-cell {
            min-height: 70px;
            padding: 2px 1px;
        }

        .calendar-day {
            font-size: 9px;
        }

        .holiday-name {
            font-size: 6px;
        }

        .plan-item {
            font-size: 7px;
            padding: 1px 2px;
        }

        .plan-select {
            font-size: 7px;
        }

        .calendar-nav .btn {
            padding: 5px 8px;
            font-size: 12px;
        }

        .calendar-nav #currentMonth {
            min-width: 80px;
            font-size: 14px;
        }
    }
</style>
@endsection

@section('content')
<div class="calendar-container">
    <h3 class="calendar-title">出席予定</h3>
    <div class="calendar-nav">
        <a href="{{ route('user.plans.monthly', ['month' => \Carbon\Carbon::createFromFormat('Y-m', $month)->subMonth()->format('Y-m')]) }}"
           class="btn">← 前月</a>
        <span id="currentMonth">{{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('Y年n月') }}</span>
        <a href="{{ route('user.plans.monthly', ['month' => \Carbon\Carbon::createFromFormat('Y-m', $month)->addMonth()->format('Y-m')]) }}"
           class="btn">翌月 →</a>
    </div>

    @if(session('success'))
    <div class="alert success" style="padding:12px;background:#dcfce7;border:1px solid #86efac;color:#166534;border-radius:6px;margin:12px 0;">
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="alert error" style="padding:12px;background:#fee2e2;border:1px solid #fca5a5;color:#dc2626;border-radius:6px;margin:12px 0;">
        @foreach($errors->all() as $error)
        <div>{{ $error }}</div>
        @endforeach
    </div>
    @endif

    @if($canEdit)
    <div class="bulk-actions" style="margin-bottom:16px;padding:12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;">
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <span style="font-weight:600;color:#374151;">一括入力:</span>
            <select id="bulkPlanType" style="padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
                <option value="onsite" selected>通所</option>
                <option value="remote">在宅</option>
                <option value="off">休日</option>
            </select>
            <select id="bulkTimeSlot" style="padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
                <option value="full">終日</option>
                <option value="am">午前</option>
                <option value="pm">午後</option>
            </select>
            <button onclick="applyBulkWeekday()" class="btn btn-primary" style="padding:6px 16px;background:#16a34a;color:white;border:none;border-radius:6px;cursor:pointer;font-size:14px;">適用</button>
        </div>
    </div>
    @endif

    <!-- カレンダーグリッド -->
    <div class="calendar-grid">
        <!-- 曜日ヘッダー -->
        <div class="calendar-header-cell sunday">日</div>
        <div class="calendar-header-cell">月</div>
        <div class="calendar-header-cell">火</div>
        <div class="calendar-header-cell">水</div>
        <div class="calendar-header-cell">木</div>
        <div class="calendar-header-cell">金</div>
        <div class="calendar-header-cell saturday">土</div>

        <!-- カレンダーセル -->
        @foreach($calendar as $day)
        <div class="calendar-cell 
                    {{ !$day['is_current_month'] ? 'other-month' : '' }}
                    {{ $day['is_weekend'] ? 'weekend' : '' }}
                    {{ $day['is_holiday'] ? 'holiday' : '' }}
                    {{ $day['is_today'] ? 'today' : '' }}"
             data-date="{{ $day['date'] }}">
            
            <div class="calendar-day {{ $day['is_holiday'] || $day['day_of_week'] === 0 ? 'sunday holiday' : ($day['day_of_week'] === 6 ? 'saturday' : '') }}">
                {{ $day['day'] }}
            </div>
            
            @if($day['is_holiday'])
            <div class="holiday-name">{{ $day['holiday_name'] }}</div>
            @endif

            {{-- 土日以外で予定がある場合は表示（過去月も含む） --}}
            @if(!$day['is_weekend'])
                @if($day['existing_plan'])
                @php
                    $planLabel = '';
                    if ($day['plan_type'] === 'onsite') {
                        $planLabel = '通所';
                    } elseif ($day['plan_type'] === 'remote') {
                        $planLabel = '在宅';
                    } elseif ($day['plan_type'] === 'off') {
                        $planLabel = '休日';
                    }

                    // 時間枠の表示を追加
                    if (isset($day['time_slot'])) {
                        if ($day['time_slot'] === 'am') {
                            $planLabel .= '(午前)';
                        } elseif ($day['time_slot'] === 'pm') {
                            $planLabel .= '(午後)';
                        }
                    }
                @endphp
                @if($canEdit && $day['is_current_month'])
                {{-- 当月のみ編集可能 --}}
                <div class="plan-edit-container" data-date="{{ $day['date'] }}" data-is-current-month="{{ $day['is_current_month'] ? '1' : '0' }}">
                    <select class="plan-type-select" data-date="{{ $day['date'] }}" data-original="{{ $day['plan_type'] }}" data-selected="{{ $day['plan_type'] }}">
                        <option value="onsite" {{ $day['plan_type'] === 'onsite' ? 'selected' : '' }}>通所</option>
                        <option value="remote" {{ $day['plan_type'] === 'remote' ? 'selected' : '' }}>在宅</option>
                        <option value="off" {{ $day['plan_type'] === 'off' ? 'selected' : '' }}>休日</option>
                    </select>
                    <select class="plan-slot-select" data-date="{{ $day['date'] }}" data-original="{{ $day['time_slot'] ?? 'full' }}" data-parent="{{ $day['plan_type'] }}" data-selected="{{ $day['time_slot'] ?? 'full' }}" {{ $day['plan_type'] === 'off' ? 'disabled' : '' }}>
                        <option value="full" {{ ($day['time_slot'] ?? 'full') === 'full' ? 'selected' : '' }}>終日</option>
                        <option value="am" {{ ($day['time_slot'] ?? 'full') === 'am' ? 'selected' : '' }}>午前</option>
                        <option value="pm" {{ ($day['time_slot'] ?? 'full') === 'pm' ? 'selected' : '' }}>午後</option>
                    </select>
                    <button class="plan-delete-btn" onclick="event.preventDefault();deleteSinglePlan('{{ $day['date'] }}')">×</button>
                </div>
                @else
                {{-- 過去月または編集不可の場合は閲覧のみ --}}
                @php
                    $timeSlotClass = $day['time_slot'] ?? 'full';
                @endphp
                <div class="plan-item {{ $day['plan_type'] }} {{ $timeSlotClass }}">
                    <span>{{ $planLabel }}</span>
                </div>
                @endif
                @elseif($canEdit && $day['is_current_month'])
                <div class="plan-selector">
                    <select class="plan-select"
                            name="plans[{{ $day['date'] }}]"
                            data-date="{{ $day['date'] }}"
                            onchange="handleNewPlan('{{ $day['date'] }}', this.value)">
                        <option value="">選択</option>
                        <option value="onsite">通所</option>
                        <option value="remote">在宅</option>
                        <option value="off">休日</option>
                    </select>
                </div>
                @endif
            @endif
        </div>
        @endforeach
    </div>

    @if($canEdit)
    <div class="save-actions" style="margin-top:20px;display:flex;gap:12px;justify-content:center;">
        <button onclick="saveAllChanges()" class="btn btn-primary" style="padding:10px 24px;background:#16a34a;color:white;border:none;border-radius:6px;cursor:pointer;font-size:14px;font-weight:600;">月予定を提出</button>
        <button onclick="deleteAllPlans()" class="btn btn-danger" style="padding:10px 24px;background:#dc2626;color:white;border:none;border-radius:6px;cursor:pointer;font-size:14px;font-weight:600;">一括削除</button>
    </div>
    @endif
</div>

<!-- サマリーカード -->
<div class="summary-cards">
    <div class="summary-card">
        <h3>通所予定日数</h3>
        <div class="value" id="onsiteCount">0</div>
    </div>
    <div class="summary-card">
        <h3>在宅予定日数</h3>
        <div class="value" id="remoteCount">0</div>
    </div>
    <div class="summary-card">
        <h3>休み予定日数</h3>
        <div class="value" id="offCount">0</div>
    </div>
    <div class="summary-card">
        <h3>合計予定日数</h3>
        <div class="value" id="totalCount">0</div>
    </div>
</div>

@endsection

@section('scripts')
<script>
const currentMonth = '{{ $month }}';
const canEdit = {{ $canEdit ? 'true' : 'false' }};

// サマリー更新（先に定義）
// 集計データをキャッシュ（初回のみ計算）
let cachedSummary = null;

function initSummaryCache() {
    if (cachedSummary) return cachedSummary;

    let onsiteCount = 0, remoteCount = 0, offCount = 0;

    // 既存の予定（編集UI）をカウント
    document.querySelectorAll('.plan-type-select').forEach(select => {
        const planType = select.value;
        if (planType === 'onsite') onsiteCount++;
        else if (planType === 'remote') remoteCount++;
        else if (planType === 'off') offCount++;
    });

    // 閲覧専用の予定をカウント
    document.querySelectorAll('.plan-item').forEach(item => {
        const text = item.textContent;
        if (text.includes('通所')) onsiteCount++;
        else if (text.includes('在宅')) remoteCount++;
        else if (text.includes('休日')) offCount++;
    });

    cachedSummary = { onsite: onsiteCount, remote: remoteCount, off: offCount };
    return cachedSummary;
}

function updateSummaryIncremental() {
    if (!cachedSummary) {
        cachedSummary = initSummaryCache();
    }

    // キャッシュから更新（高速）
    const onsiteEl = document.getElementById('onsiteCount');
    const remoteEl = document.getElementById('remoteCount');
    const offEl = document.getElementById('offCount');
    const totalEl = document.getElementById('totalCount');

    if (onsiteEl) onsiteEl.textContent = cachedSummary.onsite;
    if (remoteEl) remoteEl.textContent = cachedSummary.remote;
    if (offEl) offEl.textContent = cachedSummary.off;
    if (totalEl) totalEl.textContent = cachedSummary.onsite + cachedSummary.remote + cachedSummary.off;
}

function updateSummary() {
    // 後方互換性のため残す
    cachedSummary = null;
    initSummaryCache();
    updateSummaryIncremental();
}

function updatePlanUIState(typeSelect, slotSelect) {
    if (!typeSelect || !slotSelect) return;

    // 休日の場合は時間枠を終日固定
    if (typeSelect.value === 'off') {
        slotSelect.value = 'full';
        slotSelect.disabled = true;
    } else {
        slotSelect.disabled = false;
    }

    // 色の更新
    updateSelectColors(typeSelect, slotSelect);

    // 集計を再計算（変更があった場合のみ）
    cachedSummary = null;
}

// 通知表示（先に定義）
function showNotification(message, type = 'info') {
    const alertClass = type === 'success' ? 'success' : type === 'error' ? 'error' : 'info';
    const bgColor = type === 'success' ? '#dcfce7' : type === 'error' ? '#fee2e2' : '#dbeafe';
    const borderColor = type === 'success' ? '#86efac' : type === 'error' ? '#fca5a5' : '#93c5fd';
    const textColor = type === 'success' ? '#166534' : type === 'error' ? '#dc2626' : '#1e40af';

    const alertHtml = `<div class="alert ${alertClass}" style="position:fixed;top:20px;right:20px;z-index:1000;min-width:300px;padding:12px;background:${bgColor};border:1px solid ${borderColor};color:${textColor};border-radius:6px;">${message}</div>`;

    // 既存の通知を削除
    document.querySelectorAll('.alert[style*="position:fixed"]').forEach(el => el.remove());

    // 新しい通知を追加
    document.body.insertAdjacentHTML('beforeend', alertHtml);

    // 3秒後に自動削除
    setTimeout(() => {
        document.querySelectorAll('.alert[style*="position:fixed"]').forEach(el => el.remove());
    }, 3000);
}

// プルダウンの色を更新
function updateSelectColors(typeSelect, slotSelect) {
    const planType = typeSelect.value;
    const timeSlot = slotSelect.value;

    // 種別セレクトの色
    typeSelect.setAttribute('data-selected', planType);

    // 時間枠セレクトの色
    slotSelect.setAttribute('data-parent', planType);
    slotSelect.setAttribute('data-selected', timeSlot);
}

document.addEventListener('DOMContentLoaded', function() {
    // 初回ロード時にサマリーカードを更新
    updateSummary();

    // リロード後の通知を表示
    const notification = localStorage.getItem('planNotification');
    if (notification) {
        try {
            const data = JSON.parse(notification);
            showNotification(data.message, data.type);
            localStorage.removeItem('planNotification');
        } catch (e) {
            console.error('Failed to parse notification:', e);
        }
    }

    // イベント委譲を使用（高速化）
    const calendarGrid = document.querySelector('.calendar-grid');
    if (calendarGrid) {
        calendarGrid.addEventListener('change', function(e) {
            const target = e.target;

            if (target.classList.contains('plan-type-select')) {
                const container = target.closest('.plan-edit-container');
                if (container) {
                    const slotSelect = container.querySelector('.plan-slot-select');
                    updatePlanUIState(target, slotSelect);
                    updateSummaryIncremental();
                }
            } else if (target.classList.contains('plan-slot-select')) {
                const container = target.closest('.plan-edit-container');
                if (container) {
                    const typeSelect = container.querySelector('.plan-type-select');
                    updatePlanUIState(typeSelect, target);
                    updateSummaryIncremental();
                }
            }
        });
    }

    // 初期状態の設定（最適化版 - requestAnimationFrameで非同期化）
    requestAnimationFrame(() => {
        document.querySelectorAll('.plan-edit-container').forEach(container => {
            const typeSelect = container.querySelector('.plan-type-select');
            const slotSelect = container.querySelector('.plan-slot-select');

            if (typeSelect && slotSelect) {
                // 初期表示時の色を設定
                updateSelectColors(typeSelect, slotSelect);
            }
        });
    });

    // 平日一括入力の時間枠選択の制御
    const bulkPlanType = document.getElementById('bulkPlanType');
    const bulkTimeSlot = document.getElementById('bulkTimeSlot');
    if (bulkPlanType && bulkTimeSlot) {
        bulkPlanType.addEventListener('change', function() {
            if (this.value === 'off') {
                bulkTimeSlot.value = 'full';
                bulkTimeSlot.disabled = true;
            } else {
                bulkTimeSlot.disabled = false;
            }
        });
    }
});

// 新規予定追加（キャッシュのみ）
function handleNewPlan(date, planType) {
    if (!canEdit || !planType) return;

    const timeSlot = planType === 'off' ? 'full' : 'full'; // デフォルトは終日

    // UI即座更新: セレクトボックスを編集UIに置き換え
    const cell = document.querySelector(`.calendar-cell[data-date="${date}"]`);
    const selector = cell.querySelector('.plan-selector');

    if (selector) {
        const editUI = `
            <div class="plan-edit-container" data-date="${date}" data-is-current-month="1">
                <select class="plan-type-select" data-date="${date}" data-selected="${planType}">
                    <option value="onsite" ${planType === 'onsite' ? 'selected' : ''}>通所</option>
                    <option value="remote" ${planType === 'remote' ? 'selected' : ''}>在宅</option>
                    <option value="off" ${planType === 'off' ? 'selected' : ''}>休日</option>
                </select>
                <select class="plan-slot-select" data-date="${date}" data-parent="${planType}" data-selected="${timeSlot}" ${planType === 'off' ? 'disabled' : ''}>
                    <option value="full" selected>終日</option>
                    <option value="am">午前</option>
                    <option value="pm">午後</option>
                </select>
                <button class="plan-delete-btn" onclick="event.preventDefault();cancelNewPlan('${date}')">×</button>
            </div>
        `;
        selector.outerHTML = editUI;

        // 種別変更時のハンドラ
        const typeSelect = cell.querySelector('.plan-type-select');
        const slotSelect = cell.querySelector('.plan-slot-select');

        typeSelect.addEventListener('change', function() {
            if (this.value === 'off') {
                slotSelect.value = 'full';
                slotSelect.disabled = true;
            } else {
                slotSelect.disabled = false;
            }
            updateSelectColors(typeSelect, slotSelect);
            updateSummary();
        });

        // 時間枠変更時のハンドラ
        slotSelect.addEventListener('change', function() {
            updateSelectColors(typeSelect, slotSelect);
            updateSummary();
        });

        updateSummary();
    }
}

// 新規予定キャンセル
function cancelNewPlan(date) {
    const cell = document.querySelector(`.calendar-cell[data-date="${date}"]`);
    const container = cell.querySelector('.plan-edit-container');

    if (container) {
        const selector = `
            <div class="plan-selector">
                <select class="plan-select" name="plans[${date}]" data-date="${date}" onchange="handleNewPlan('${date}', this.value)">
                    <option value="">選択</option>
                    <option value="onsite">通所</option>
                    <option value="remote">在宅</option>
                    <option value="off">休日</option>
                </select>
            </div>
        `;
        container.outerHTML = selector;
        updateSummary();
    }
}

// 予定削除（キャッシュのみ、削除マーク）
function deleteSinglePlan(date) {
    if (!canEdit) {
        showNotification('この月の予定は編集できません', 'error');
        return;
    }

    if (!confirm('この予定を削除しますか？\n※実際の削除は「一括削除」ボタンを押したときに実行されます')) {
        return;
    }

    // UIから削除
    const cell = document.querySelector(`.calendar-cell[data-date="${date}"]`);
    const container = cell.querySelector('.plan-edit-container');

    if (container) {
        const selector = `
            <div class="plan-selector">
                <select class="plan-select" name="plans[${date}]" data-date="${date}" onchange="handleNewPlan('${date}', this.value)">
                    <option value="">選択</option>
                    <option value="onsite">通所</option>
                    <option value="remote">在宅</option>
                    <option value="off">休日</option>
                </select>
            </div>
        `;
        container.outerHTML = selector;
        updateSummary();
        showNotification('削除をマークしました。「月予定を提出」で確定します', 'info');
    }
}

// 平日一括入力
function applyBulkWeekday() {
    const planType = document.getElementById('bulkPlanType').value;
    const timeSlot = document.getElementById('bulkTimeSlot').value;

    // 当月の平日（月〜金）のセルを取得
    const cells = document.querySelectorAll('.calendar-cell');
    let count = 0;

    cells.forEach(cell => {
        const date = cell.dataset.date;
        const dayOfWeek = new Date(date).getDay();

        // 月曜〜金曜（1-5）かつ当月のセル
        if (dayOfWeek >= 1 && dayOfWeek <= 5) {
            // 既存予定がある場合は編集UIを更新
            const editContainer = cell.querySelector('.plan-edit-container');
            if (editContainer && editContainer.dataset.isCurrentMonth === '1') {
                const typeSelect = editContainer.querySelector('.plan-type-select');
                const slotSelect = editContainer.querySelector('.plan-slot-select');

                typeSelect.value = planType;
                if (planType === 'off') {
                    slotSelect.value = 'full';
                    slotSelect.disabled = true;
                } else {
                    slotSelect.value = timeSlot;
                    slotSelect.disabled = false;
                }
                updateSelectColors(typeSelect, slotSelect);
                count++;
            }
            // 新規予定の場合
            else if (cell.querySelector('.plan-selector')) {
                handleNewPlan(date, planType);
                count++;
            }
        }
    });

    updateSummary();
    showNotification(`${count}件の平日予定を入力しました`, 'success');
}

// 全変更を保存
async function saveAllChanges() {
    if (!canEdit) {
        showNotification('この月の予定は編集できません', 'error');
        return;
    }

    const plans = {};
    let count = 0;

    // 既存予定の変更を収集
    document.querySelectorAll('.plan-edit-container').forEach(container => {
        const date = container.dataset.date;
        const typeSelect = container.querySelector('.plan-type-select');
        const slotSelect = container.querySelector('.plan-slot-select');

        if (typeSelect && slotSelect) {
            plans[date] = {
                plan_type: typeSelect.value,
                time_slot: slotSelect.value
            };
            count++;
        }
    });

    // 新規予定を収集
    document.querySelectorAll('.plan-selector').forEach(selector => {
        const select = selector.querySelector('.plan-select');
        if (select && select.value) {
            const date = select.dataset.date;
            plans[date] = {
                plan_type: select.value,
                time_slot: 'full'
            };
            count++;
        }
    });

    if (count === 0) {
        showNotification('保存する予定がありません', 'error');
        return;
    }

    try {
        const response = await fetch('{{ route("user.plans.monthly") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                month: currentMonth,
                plans: plans
            })
        });

        const result = await response.json();

        if (result.success || response.ok) {
            // リロード後に通知を表示するためlocalStorageに保存
            localStorage.setItem('planNotification', JSON.stringify({
                message: result.message || `${count}件の予定を保存しました`,
                type: 'success'
            }));
            window.location.reload();
        } else {
            throw new Error(result.message || '保存に失敗しました');
        }

    } catch (error) {
        console.error('Bulk save error:', error);
        showNotification('一括保存に失敗しました', 'error');
    }
}

// 月の予定を全削除
async function deleteAllPlans() {
    // 画面上の予定数をカウント
    const displayedCount = document.querySelectorAll('.plan-edit-container').length;

    if (displayedCount === 0) {
        showNotification('削除する予定がありません', 'info');
        return;
    }

    if (!confirm(`この月の全ての予定（${displayedCount}件）を削除しますか？\n未保存の予定も含めて削除されます。この操作は取り消せません。`)) {
        return;
    }

    try {
        // データベースの予定を削除
        const response = await fetch('{{ route("user.plans.monthly") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                month: currentMonth,
                plans: {}, // 空のオブジェクト = 全削除
                delete_all: true
            })
        });

        const result = await response.json();

        if (response.ok) {
            // リロード後に通知を表示するためlocalStorageに保存
            const message = displayedCount > 0
                ? `${displayedCount}件の予定を削除しました`
                : result.message;
            localStorage.setItem('planNotification', JSON.stringify({
                message: message,
                type: 'success'
            }));
            window.location.reload();
        } else {
            throw new Error('削除に失敗しました');
        }

    } catch (error) {
        console.error('Delete all error:', error);
        showNotification('削除に失敗しました', 'error');
    }
}

</script>
@endsection