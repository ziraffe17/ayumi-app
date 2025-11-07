{{-- resources/views/staff/dashboards/personal.blade.php --}}
@extends('layouts.staff')

@section('title', 'S-03S 個人ダッシュボード')

@section('styles')
<style>
.toolbar{display:flex;gap:12px;align-items:center;margin-bottom:24px;background:white;padding:16px;border-radius:12px;border:1px solid var(--line)}
.toolbar select{padding:8px 12px;border:1px solid var(--line);border-radius:8px;font-size:14px;min-width:200px}
.kpi-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px}
.kpi-card{background:white;border:1px solid var(--line);border-radius:12px;padding:20px;text-align:center}
.kpi-card h3{font-size:14px;color:var(--muted);margin:0 0 10px;font-weight:500}
.kpi-card .value{font-size:32px;font-weight:700;color:var(--brand-deep);margin:8px 0}
.kpi-card .value.warning{color:#f59e0b}
.kpi-card .value.danger{color:#dc2626}
.chart-section{background:white;border-radius:12px;padding:24px;margin-bottom:24px;border:1px solid var(--line)}
.chart-section h3{margin:0 0 20px;font-size:18px;font-weight:600}
.loading{text-align:center;padding:60px;color:var(--muted)}
.empty-state{text-align:center;padding:80px 20px;color:var(--muted)}
@media(max-width:768px){.kpi-cards{grid-template-columns:repeat(2,1fr)}}
</style>
@endsection

@section('content')
<div class="toolbar">
    <label>利用者:</label>
    <select id="userSelect">
        <option value="">選択してください</option>
        @foreach($users as $user)
        <option value="{{ $user->id }}" {{ $selectedUserId == $user->id ? 'selected' : '' }}>
            {{ $user->name }}
        </option>
        @endforeach
    </select>
    <label>表示期間:</label>
    <select id="periodSelect">
        <option value="current_month" selected>今月</option>
        <option value="recent_3months">直近3ヶ月</option>
        <option value="all">全期間</option>
        <option value="specific_month">特定の月</option>
    </select>
    <select id="monthSelect" style="display:none;">
        <!-- 動的に生成 -->
    </select>
    <button class="btn green" onclick="loadDashboard()">表示</button>
    <button class="btn" onclick="exportData()">CSV出力</button>
</div>

<div id="dashboardContent" style="display:none">
    <div class="kpi-cards">
        <div class="kpi-card">
            <h3>計画日数</h3>
            <div class="value" id="plannedDays">-</div>
        </div>
        <div class="kpi-card">
            <h3>実出席日数</h3>
            <div class="value" id="actualDays">-</div>
        </div>
        <div class="kpi-card">
            <h3>出席率</h3>
            <div class="value" id="attendanceRate">-%</div>
        </div>
        <div class="kpi-card">
            <h3>欠席日数</h3>
            <div class="value" id="absences">-</div>
        </div>
        <div class="kpi-card">
            <h3>日報入力率</h3>
            <div class="value" id="reportRate">-%</div>
        </div>
    </div>

    <div class="chart-section">
        <h3>出席率推移（全期間）</h3>
        <canvas id="attendanceChart" width="800" height="300"></canvas>
    </div>

    <div class="chart-section">
        <h3>日報トレンド（表示期間）</h3>
        <canvas id="reportChart" width="800" height="250"></canvas>
        <div style="display:flex;justify-content:center;gap:20px;margin-top:12px;font-size:12px;">
            <div style="display:flex;align-items:center;gap:6px;">
                <div style="width:20px;height:3px;background:#16a34a;"></div>
                <span>睡眠</span>
            </div>
            <div style="display:flex;align-items:center;gap:6px;">
                <div style="width:20px;height:3px;background:#3b82f6;"></div>
                <span>ストレス</span>
            </div>
            <div style="display:flex;align-items:center;gap:6px;">
                <div style="width:20px;height:3px;background:#f59e0b;"></div>
                <span>食事</span>
            </div>
        </div>
    </div>
</div>

<div id="emptyState" class="empty-state">
    <h3>利用者を選択してください</h3>
    <p>上部のドロップダウンから利用者を選択し、「表示」ボタンをクリックしてください</p>
</div>

<div id="loadingState" class="loading" style="display:none">
    <div>読み込み中...</div>
</div>
@endsection

@section('scripts')
<script>
let selectedUserId = {{ $selectedUserId ?? 'null' }};
let dashboardData = null;

// 初期化
document.addEventListener('DOMContentLoaded', () => {
    initializeMonthSelect();
    document.getElementById('periodSelect').addEventListener('change', handlePeriodChange);
});

document.getElementById('userSelect').addEventListener('change', (e) => {
    selectedUserId = e.target.value;
});

// 月選択ドロップダウンの初期化（過去24ヶ月分）
function initializeMonthSelect() {
    const monthSelect = document.getElementById('monthSelect');
    const now = new Date();
    const options = [];

    for (let i = 0; i < 24; i++) {
        const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
        const yearMonth = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
        const label = d.getFullYear() + '年' + (d.getMonth() + 1) + '月';
        options.push(`<option value="${yearMonth}">${label}</option>`);
    }
    monthSelect.innerHTML = options.join('');
}

// 期間選択変更時のハンドラー
function handlePeriodChange() {
    const periodType = document.getElementById('periodSelect').value;
    const monthSelect = document.getElementById('monthSelect');

    if (periodType === 'specific_month') {
        monthSelect.style.display = 'block';
    } else {
        monthSelect.style.display = 'none';
    }
}

async function loadDashboard() {
    if (!selectedUserId) {
        showNotification('利用者を選択してください', 'warning');
        return;
    }

    const periodType = document.getElementById('periodSelect').value;
    let monthParam = new Date().getFullYear() + '-' + String(new Date().getMonth() + 1).padStart(2, '0');

    // 特定の月を選択した場合は、monthSelectの値を使用
    if (periodType === 'specific_month') {
        monthParam = document.getElementById('monthSelect').value;
    }

    document.getElementById('emptyState').style.display = 'none';
    document.getElementById('dashboardContent').style.display = 'none';
    document.getElementById('loadingState').style.display = 'block';

    try {
        const res = await fetch(`/api/dashboard/personal?user_id=${selectedUserId}&period=${periodType}&month=${monthParam}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!res.ok) throw new Error('データ取得失敗');
        
        const result = await res.json();
        
        if (result.success) {
            dashboardData = result.data;
            updateKPI();

            // 出席率推移は全期間で別途取得
            await loadAttendanceTrend();

            // 日報トレンドは選択期間のデータを使用
            drawReportChart();

            document.getElementById('dashboardContent').style.display = 'block';
        } else {
            throw new Error(result.message || 'エラー');
        }
    } catch (e) {
        console.error(e);
        showNotification('データの読み込みに失敗しました', 'error');
        document.getElementById('emptyState').style.display = 'block';
    } finally {
        document.getElementById('loadingState').style.display = 'none';
    }
}

function updateKPI() {
    // 選択された期間のKPIを使用（current_monthではなく、選択期間のkpi）
    const kpi = dashboardData.kpi;

    if (!kpi || !kpi.attendance) {
        // データがない場合
        document.getElementById('plannedDays').textContent = '-';
        document.getElementById('actualDays').textContent = '-';
        document.getElementById('attendanceRate').textContent = '-%';
        document.getElementById('absences').textContent = '-';
        document.getElementById('reportRate').textContent = '-%';
        return;
    }

    document.getElementById('plannedDays').textContent = kpi.attendance.planned_days || 0;
    document.getElementById('actualDays').textContent = kpi.attendance.attended_days || 0;
    document.getElementById('attendanceRate').textContent = kpi.attendance.attendance_rate_display || '0%';

    const absences = kpi.attendance.absent_days || 0;
    const absEl = document.getElementById('absences');
    absEl.textContent = absences;
    absEl.className = absences > 0 ? 'value danger' : 'value';

    const reportRate = kpi.reports?.report_rate;
    const reportEl = document.getElementById('reportRate');
    reportEl.textContent = kpi.reports?.report_rate_display || '-%';
    reportEl.className = reportRate !== null && reportRate < 90 ? 'value warning' : 'value';
}

async function loadAttendanceTrend() {
    try {
        const res = await fetch(`/api/dashboard/personal?user_id=${selectedUserId}&period=all&month=${new Date().getFullYear()}-${String(new Date().getMonth() + 1).padStart(2, '0')}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!res.ok) throw new Error('出席率データ取得失敗');

        const result = await res.json();

        if (result.success) {
            // 出席率推移データのみを更新
            dashboardData.allPeriodAttendance = result.data.trends.attendance;
            drawAttendanceChart();
        }
    } catch (e) {
        console.error('出席率推移データの読み込みに失敗:', e);
    }
}

function drawCharts() {
    drawAttendanceChart();
    drawReportChart();
}

function drawAttendanceChart() {
    const canvas = document.getElementById('attendanceChart');
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // 全期間のデータを使用（別途取得したもの、なければ現在のもの）
    const trend = dashboardData.allPeriodAttendance || dashboardData.trends.attendance;

    if (trend && trend.length > 0) {
        const data = trend.map(t => t.rate);
        const dates = trend.map(t => t.date);

        // X軸の目盛り数をデータ数に応じて設定
        const xTicks = Math.max(1, data.length - 1);
        drawAxes(ctx, canvas.width, canvas.height, 100, 20, xTicks, dates);
        drawLineInArea(ctx, data, '#16a34a', canvas.width, canvas.height, 100);
    } else {
        // データがない場合
        ctx.fillStyle = '#6b7280';
        ctx.font = '16px system-ui';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('データがありません', canvas.width / 2, canvas.height / 2);
    }
}

function drawReportChart() {
    const canvas = document.getElementById('reportChart');
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    const trend = dashboardData.trends.reports;

    if (trend && trend.sleep && trend.sleep.length > 0) {
        const sleepData = trend.sleep.map(t => t.value);
        const stressData = trend.stress.map(t => t.value);
        const mealData = trend.meal.map(t => t.value);
        const dates = trend.sleep.map(t => t.date);

        // X軸の目盛り数をデータ数に応じて設定
        const xTicks = Math.max(1, sleepData.length - 1);
        drawAxes(ctx, canvas.width, canvas.height, 3, 1, xTicks, dates);
        drawLineInArea(ctx, sleepData, '#16a34a', canvas.width, canvas.height, 3);
        drawLineInArea(ctx, stressData, '#3b82f6', canvas.width, canvas.height, 3);
        drawLineInArea(ctx, mealData, '#f59e0b', canvas.width, canvas.height, 3);
    } else {
        // データがない場合
        ctx.fillStyle = '#6b7280';
        ctx.font = '16px system-ui';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('データがありません', canvas.width / 2, canvas.height / 2);
    }
}

function drawAxes(ctx, width, height, maxY, yStep, xCount, dates = [], padding = 40) {
    ctx.save();
    ctx.strokeStyle = '#e5e7eb';
    ctx.fillStyle = '#6b7280';
    ctx.lineWidth = 1;
    ctx.font = '11px system-ui';
    ctx.textAlign = 'right';
    ctx.textBaseline = 'middle';

    const left = padding;
    const bottom = height - padding;
    const right = width - padding;
    const top = padding;

    ctx.beginPath();
    ctx.moveTo(left, top);
    ctx.lineTo(left, bottom);
    ctx.lineTo(right, bottom);
    ctx.stroke();

    // Y軸目盛り
    for (let y = 0; y <= maxY; y += yStep) {
        const py = bottom - (y / maxY) * (bottom - top);
        ctx.beginPath();
        ctx.moveTo(left, py);
        ctx.lineTo(right, py);
        ctx.stroke();
        ctx.fillText(String(y), left - 6, py);
    }

    // X軸目盛りと日付ラベル
    const xStep = (right - left) / xCount;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';
    for (let i = 0; i <= xCount; i++) {
        const px = left + i * xStep;
        ctx.beginPath();
        ctx.moveTo(px, bottom);
        ctx.lineTo(px, bottom + 4);
        ctx.stroke();

        // 日付ラベル表示
        if (dates && dates.length > 0) {
            const dataIndex = Math.floor((i / xCount) * (dates.length - 1));
            if (dates[dataIndex]) {
                const dateStr = dates[dataIndex];
                const dateParts = dateStr.split('-');
                if (dateParts.length === 3) {
                    // 月ごとの表示か日ごとの表示かを判定
                    if (maxY === 100) {
                        // 出席率チャート（月ごと）: 年/月
                        const label = `${dateParts[0].slice(2)}/${dateParts[1]}`;
                        ctx.fillText(label, px, bottom + 6);
                    } else {
                        // 日報トレンド（日ごと）: 月/日
                        const label = `${dateParts[1]}/${dateParts[2]}`;
                        ctx.fillText(label, px, bottom + 6);
                    }
                }
            }
        }
    }

    ctx.restore();
}

function drawLineInArea(ctx, data, color, width, height, maxY, padding = 40) {
    ctx.save();
    const left = padding;
    const bottom = height - padding;
    const right = width - padding;
    const top = padding;

    ctx.strokeStyle = color;
    ctx.lineWidth = 2;

    const n = data.length;
    const stepX = n > 1 ? (right - left) / (n - 1) : 0;

    ctx.beginPath();
    data.forEach((v, i) => {
        const x = left + i * stepX;
        const y = bottom - (v / maxY) * (bottom - top);
        if (i === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
    });
    ctx.stroke();

    ctx.fillStyle = color;
    data.forEach((v, i) => {
        const x = left + i * stepX;
        const y = bottom - (v / maxY) * (bottom - top);
        ctx.beginPath();
        ctx.arc(x, y, 3, 0, 2 * Math.PI);
        ctx.fill();
    });

    ctx.restore();
}

function exportData() {
    if (!selectedUserId) {
        showNotification('利用者を選択してください', 'warning');
        return;
    }
    window.location.href = `/staff/export/csv?user_id=${selectedUserId}`;
}

if (selectedUserId) {
    loadDashboard();
}
</script>
@endsection