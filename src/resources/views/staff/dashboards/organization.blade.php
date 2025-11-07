{{-- resources/views/staff/dashboards/organization.blade.php --}}
@extends('layouts.staff')

@section('title', 'S-04S 事業所ダッシュボード')

@section('styles')
<style>
    /* ツールバー */
    .toolbar{display:flex;gap:12px;align-items:center;margin:12px 0;flex-wrap:wrap}
    select,input{border:1px solid var(--line);padding:6px 10px;border-radius:6px;font-size:14px}
    .btn{border:1px solid var(--line);background:#fff;border-radius:6px;padding:6px 12px;cursor:pointer;font-size:14px;text-decoration:none;display:inline-block}
    .btn.primary{background:var(--brand-deep);color:#fff;border-color:var(--brand-deep)}
    .btn:disabled{opacity:.5;cursor:not-allowed}

    /* サマリーカード */
    .summary-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin:16px 0}
    .summary-card{background:#fff;border:1px solid var(--line);border-radius:8px;padding:20px;text-align:center;box-shadow:0 1px 2px rgba(0,0,0,.04)}
    .summary-card h3{font-size:13px;color:var(--muted);margin:0 0 10px;font-weight:500}
    .summary-card .value{font-size:32px;font-weight:700;color:var(--brand-deep);margin:8px 0}
    .summary-card .value.warning{color:#f59e0b}
    .summary-card .value.danger{color:#dc2626}
    .summary-card .sub{font-size:12px;color:var(--muted);margin-top:6px}

    /* アラート */
    .alerts{background:#fff;border-radius:8px;padding:16px;margin:16px 0;border:1px solid var(--line)}
    .alerts h3{margin:0 0 12px;font-size:16px;color:#374151}
    .alert-item{padding:10px;margin:6px 0;border-radius:6px;display:flex;justify-content:space-between;align-items:center}
    .alert-item.warning{background:#fef3c7;border:1px solid #f59e0b}
    .alert-item.info{background:#dbeafe;border:1px solid #3b82f6}
    .alert-item .label{font-weight:600;color:#111}
    .alert-item .count{font-size:18px;font-weight:700}

    /* ユーザーテーブル */
    .user-table-wrap{background:#fff;border-radius:8px;padding:16px;margin:16px 0;border:1px solid var(--line);overflow-x:auto}
    table{width:100%;border-collapse:collapse;font-size:14px}
    th,td{padding:10px 8px;text-align:left;border-bottom:1px solid var(--line)}
    th{background:#f3f4f6;font-weight:600;position:sticky;top:0;white-space:nowrap}
    tr:hover{background:#f9fafb}
    .rank{width:40px;text-align:center;font-weight:700;color:var(--brand-deep)}
    .user-name{font-weight:600;color:#111}
    .rate{font-weight:700}
    .rate.good{color:#16a34a}
    .rate.fair{color:#f59e0b}
    .rate.poor{color:#dc2626}
    .diff{font-weight:600}
    .diff.positive{color:#16a34a}
    .diff.negative{color:#dc2626}

    /* ソート */
    .sortable{cursor:pointer;user-select:none}
    .sortable:hover{background:#e5e7eb}
    .sort-icon{font-size:10px;margin-left:4px;color:var(--muted)}

    /* チャート */
    .chart-card{background:#fff;border-radius:8px;padding:20px;border:1px solid var(--line);box-shadow:0 1px 2px rgba(0,0,0,.04)}
    .chart-card h3{margin:0 0 16px;font-size:16px;color:#374151;font-weight:600}

    /* ページネーション */
    .pagination{display:flex;gap:8px;justify-content:center;margin-top:16px}
    .pagination button{border:1px solid var(--line);background:#fff;padding:6px 12px;border-radius:6px;cursor:pointer}
    .pagination button.active{background:var(--brand-deep);color:#fff;border-color:var(--brand-deep)}
    .pagination button:disabled{opacity:.5;cursor:not-allowed}

    /* ローディング */
    .loading{text-align:center;padding:40px;color:var(--muted)}
    .spinner{display:inline-block;width:30px;height:30px;border:3px solid var(--line);border-top-color:var(--brand-deep);border-radius:50%;animation:spin 1s linear infinite}
    @keyframes spin{to{transform:rotate(360deg)}}

    /* レスポンシブ */
    @media(max-width:768px){
      .summary-cards{grid-template-columns:repeat(2,1fr);gap:12px}
      .toolbar{flex-direction:column;align-items:stretch}
      div[style*="grid-template-columns:1fr 1fr"]{grid-template-columns:1fr !important}
    }
</style>
@endsection

@section('content')
<!-- ツールバー -->
<div class="toolbar">
    <label>対象期間:</label>
    <select id="periodSelect">
        <option value="current">今月</option>
        <option value="last">先月</option>
        <option value="custom">カスタム</option>
    </select>
    <input type="date" id="startDate" style="display:none">
    <input type="date" id="endDate" style="display:none">

    <label>状態:</label>
    <select id="statusFilter">
        <option value="all">全員</option>
        <option value="active">有効のみ</option>
    </select>

    <button class="btn primary" onclick="loadData()">更新</button>
    <button class="btn" onclick="exportCSV()">CSV出力</button>
</div>

<!-- サマリーカード -->
<div class="summary-cards">
    <!-- 1段目: 全体出席率、実績コマ数、予定コマ数、予測コマ数 -->
    <div class="summary-card">
        <h3>全体出席率</h3>
        <div class="value" id="avgRate">-%</div>
        <div class="sub" id="avgRateSub">期間内の実績</div>
    </div>
    <div class="summary-card">
        <h3>実績コマ数</h3>
        <div class="value" id="actualSlots">-</div>
        <div class="sub" id="actualSlotsSub">期間内の実績</div>
    </div>
    <div class="summary-card">
        <h3>予定コマ数</h3>
        <div class="value" id="plannedSlots">-</div>
        <div class="sub" id="plannedSlotsSub">期間内の全予定数</div>
    </div>
    <div class="summary-card">
        <h3>予測コマ数</h3>
        <div class="value" id="forecastSlots">-</div>
        <div class="sub" id="forecastSlotsSub">期間末の予測実績</div>
    </div>
    <!-- 2段目: 総利用者数、未入力日報、未計画利用者、予測稼働率 -->
    <div class="summary-card">
        <h3>総利用者数</h3>
        <div class="value" id="totalUsers">-</div>
        <div class="sub">有効: <span id="activeUsers">-</span>名</div>
    </div>
    <div class="summary-card">
        <h3>未入力日報</h3>
        <div class="value warning" id="pendingReports">-</div>
        <div class="sub">本日分の未入力</div>
    </div>
    <div class="summary-card">
        <h3>未計画利用者</h3>
        <div class="value danger" id="noPlanUsers">-</div>
        <div class="sub">来月の予定未登録</div>
    </div>
    <div class="summary-card">
        <h3>予測稼働率</h3>
        <div class="value" id="forecastUtilization">-%</div>
        <div class="sub">月末時点の予測稼働率</div>
    </div>
</div>

<!-- アラート -->
<div class="alerts">
    <h3>要注意事項</h3>
    <div id="alertList">
        <!-- 動的生成 -->
    </div>
</div>

<!-- グラフセクション -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:16px 0">
    <div class="chart-card">
        <h3>出席率分布</h3>
        <canvas id="attendanceDistChart" width="400" height="250"></canvas>
    </div>
    <div class="chart-card">
        <h3>日報入力状況</h3>
        <canvas id="reportStatusChart" width="400" height="250"></canvas>
    </div>
</div>

<!-- 利用者別KPIテーブル -->
<div class="user-table-wrap">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h3 style="margin:0">利用者別出席状況</h3>
        <div>
            <input type="text" id="searchInput" placeholder="氏名で検索" style="width:200px">
        </div>
    </div>

    <div id="loadingIndicator" class="loading" style="display:none">
        <div class="spinner"></div>
        <div>読み込み中...</div>
    </div>

    <table id="userTable">
        <thead>
            <tr>
                <th class="sortable" data-sort="rank">順位 <span class="sort-icon">▼</span></th>
                <th class="sortable" data-sort="name">氏名 <span class="sort-icon">▼</span></th>
                <th class="sortable" data-sort="planned">計画日数 <span class="sort-icon">▼</span></th>
                <th class="sortable" data-sort="actual">実出席 <span class="sort-icon">▼</span></th>
                <th class="sortable" data-sort="rate">出席率 <span class="sort-icon">▼</span></th>
                <th class="sortable" data-sort="diff">差分 <span class="sort-icon">▼</span></th>
                <th class="sortable" data-sort="report_rate">日報率 <span class="sort-icon">▼</span></th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody id="tableBody">
            <!-- 動的生成 -->
        </tbody>
    </table>

    <div class="pagination" id="pagination">
        <!-- 動的生成 -->
    </div>
</div>
@endsection

@section('scripts')
<script>
let userData = [];
let filteredData = [];
let currentSort = {field: 'rate', order: 'desc'};
let currentPage = 1;
const itemsPerPage = 20;

document.addEventListener('DOMContentLoaded', () => {
  loadData();
  setupEventListeners();
});

function setupEventListeners() {
  document.getElementById('periodSelect').addEventListener('change', (e) => {
    const custom = e.target.value === 'custom';
    document.getElementById('startDate').style.display = custom ? 'inline-block' : 'none';
    document.getElementById('endDate').style.display = custom ? 'inline-block' : 'none';
  });

  document.getElementById('searchInput').addEventListener('input', (e) => {
    filterData(e.target.value);
  });

  document.querySelectorAll('.sortable').forEach(th => {
    th.addEventListener('click', () => {
      const field = th.dataset.sort;
      if (currentSort.field === field) {
        currentSort.order = currentSort.order === 'asc' ? 'desc' : 'asc';
      } else {
        currentSort.field = field;
        currentSort.order = 'desc';
      }
      sortData();
      renderTable();
    });
  });
}

async function loadData() {
  const loading = document.getElementById('loadingIndicator');
  loading.style.display = 'block';

  try {
    const period = document.getElementById('periodSelect').value;
    const status = document.getElementById('statusFilter').value;

    let params = new URLSearchParams({
      period: period,
      status: status
    });

    if (period === 'custom') {
      const startDate = document.getElementById('startDate').value;
      const endDate = document.getElementById('endDate').value;

      if (!startDate || !endDate) {
        alert('開始日と終了日を入力してください');
        loading.style.display = 'none';
        return;
      }

      params.append('start_date', startDate);
      params.append('end_date', endDate);
    }

    const res = await fetch(`/api/dashboard/facility?${params.toString()}`, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    });

    if (!res.ok) throw new Error('データ取得失敗: ' + res.status);

    const result = await res.json();

    if (!result.success) throw new Error(result.message || 'エラー');

    const data = result.data;

    updateSummary(data.summary);
    updateAlerts(data.alerts);
    userData = data.users;
    filteredData = [...userData];
    sortData();
    renderTable();
    renderCharts();

  } catch (e) {
    console.error('データ取得失敗', e);
    alert('データの取得に失敗しました: ' + e.message);
  } finally {
    loading.style.display = 'none';
  }
}

function updateSummary(summary) {
  // 期間情報を取得
  const period = document.getElementById('periodSelect').value;
  let periodText = '';

  if (period === 'current') {
    periodText = '当月の今日までの';
  } else if (period === 'last') {
    periodText = '先月の';
  } else if (period === 'custom') {
    periodText = '選択期間の';
  }

  // 1段目: 全体出席率、実績コマ数、予定コマ数、予測コマ数
  document.getElementById('avgRate').textContent = summary.avg_attendance_rate_display || '—';
  document.getElementById('avgRateSub').textContent = periodText + '実績';

  document.getElementById('actualSlots').textContent = summary.actual_slots || 0;
  document.getElementById('actualSlotsSub').textContent = periodText + '実績コマ数';

  document.getElementById('plannedSlots').textContent = summary.planned_slots || 0;
  document.getElementById('plannedSlotsSub').textContent = periodText + '予定コマ数';

  document.getElementById('forecastSlots').textContent = summary.forecast_slots || 0;
  document.getElementById('forecastSlotsSub').textContent = '期間末の予測実績';

  // 2段目: 総利用者数、未入力日報、未計画利用者、予測稼働率
  document.getElementById('totalUsers').textContent = summary.total_users || 0;
  document.getElementById('activeUsers').textContent = summary.active_users || 0;
  document.getElementById('pendingReports').textContent = summary.pending_reports || 0;
  document.getElementById('noPlanUsers').textContent = summary.no_plan_users || 0;
  document.getElementById('forecastUtilization').textContent = summary.forecast_utilization_display || '—';
}

function updateAlerts(alerts) {
  const list = document.getElementById('alertList');

  if (!alerts || alerts.length === 0) {
    list.innerHTML = '<div class="alert-item info"><span class="label">現在アラートはありません</span></div>';
    return;
  }

  const grouped = {};
  alerts.forEach(a => {
    const key = a.message || 'その他';
    grouped[key] = (grouped[key] || 0) + 1;
  });

  const summary = Object.entries(grouped).map(([message, count]) => {
    let label = message;
    if (message.includes('出席率')) {
      label = '出席率70%未満';
    } else if (message.includes('日報')) {
      label = '日報入力率50%未満';
    }

    return `
      <div class="alert-item warning">
        <span class="label">${label}</span>
        <span class="count">${count}件</span>
      </div>
    `;
  });

  list.innerHTML = summary.join('');
}

function filterData(query) {
  filteredData = query ? userData.filter(u => u.name.includes(query)) : [...userData];
  currentPage = 1;
  renderTable();
}

function sortData() {
  filteredData.sort((a, b) => {
    let aVal = a[currentSort.field], bVal = b[currentSort.field];
    if (currentSort.field === 'name') {
      return currentSort.order === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
    }
    if (aVal === null) return 1;
    if (bVal === null) return -1;
    return currentSort.order === 'asc' ? aVal - bVal : bVal - aVal;
  });
  filteredData.forEach((u, i) => u.rank = i + 1);
}

function renderTable() {
  const start = (currentPage - 1) * itemsPerPage;
  const end = start + itemsPerPage;
  const pageData = filteredData.slice(start, end);

  const tbody = document.getElementById('tableBody');
  tbody.innerHTML = pageData.map(u => {
    const rate = u.rate !== null ? u.rate : 0;
    const rateDisplay = u.rate_display || (rate + '%');
    const rateClass = rate >= 90 ? 'good' : rate >= 70 ? 'fair' : 'poor';
    const diffClass = u.diff >= 0 ? 'positive' : 'negative';
    const diffSign = u.diff >= 0 ? '+' : '';
    const reportRate = u.report_rate !== null ? u.report_rate : 0;
    const reportDisplay = u.report_rate_display || (reportRate + '%');

    return `
      <tr>
        <td class="rank">${u.rank}</td>
        <td class="user-name">${u.name}</td>
        <td>${u.planned}</td>
        <td>${u.actual}</td>
        <td class="rate ${rateClass}">${rateDisplay}</td>
        <td class="diff ${diffClass}">${diffSign}${u.diff}</td>
        <td>${reportDisplay}</td>
        <td>
          <a href="{{ route('staff.dashboards.personal') }}?user_id=${u.id}" class="btn" style="padding:4px 8px;font-size:12px">詳細</a>
        </td>
      </tr>
    `;
  }).join('');

  renderPagination();
}

function renderPagination() {
  const totalPages = Math.ceil(filteredData.length / itemsPerPage);
  const pagination = document.getElementById('pagination');

  let html = `<button onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>前へ</button>`;
  for (let i = 1; i <= totalPages; i++) {
    if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
      html += `<button class="${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">${i}</button>`;
    } else if (i === currentPage - 3 || i === currentPage + 3) {
      html += '<span>...</span>';
    }
  }
  html += `<button onclick="changePage(${currentPage + 1})" ${currentPage === totalPages || totalPages === 0 ? 'disabled' : ''}>次へ</button>`;
  pagination.innerHTML = html;
}

function changePage(page) {
  const totalPages = Math.ceil(filteredData.length / itemsPerPage);
  if (page < 1 || page > totalPages) return;
  currentPage = page;
  renderTable();
}

function renderCharts() {
  renderAttendanceDistChart();
  renderReportStatusChart();
}

function renderAttendanceDistChart() {
  const canvas = document.getElementById('attendanceDistChart');
  const ctx = canvas.getContext('2d');
  ctx.clearRect(0, 0, canvas.width, canvas.height);

  if (!userData || userData.length === 0) {
    ctx.fillStyle = '#6b7280';
    ctx.font = '14px system-ui';
    ctx.textAlign = 'center';
    ctx.fillText('データがありません', canvas.width / 2, canvas.height / 2);
    return;
  }

  const ranges = {
    '90%以上': 0,
    '80-89%': 0,
    '70-79%': 0,
    '60-69%': 0,
    '60%未満': 0
  };

  userData.forEach(user => {
    const rate = user.rate !== null ? user.rate : 0;
    if (rate >= 90) ranges['90%以上']++;
    else if (rate >= 80) ranges['80-89%']++;
    else if (rate >= 70) ranges['70-79%']++;
    else if (rate >= 60) ranges['60-69%']++;
    else ranges['60%未満']++;
  });

  const colors = ['#16a34a', '#65a30d', '#eab308', '#f59e0b', '#dc2626'];
  const labels = Object.keys(ranges);
  const values = Object.values(ranges);

  drawBarChart(ctx, canvas, labels, values, colors);
}

function renderReportStatusChart() {
  const canvas = document.getElementById('reportStatusChart');
  const ctx = canvas.getContext('2d');
  ctx.clearRect(0, 0, canvas.width, canvas.height);

  if (!userData || userData.length === 0) {
    ctx.fillStyle = '#6b7280';
    ctx.font = '14px system-ui';
    ctx.textAlign = 'center';
    ctx.fillText('データがありません', canvas.width / 2, canvas.height / 2);
    return;
  }

  const ranges = {
    '90%以上': 0,
    '70-89%': 0,
    '50-69%': 0,
    '50%未満': 0
  };

  userData.forEach(user => {
    const rate = user.report_rate !== null ? user.report_rate : 0;
    if (rate >= 90) ranges['90%以上']++;
    else if (rate >= 70) ranges['70-89%']++;
    else if (rate >= 50) ranges['50-69%']++;
    else ranges['50%未満']++;
  });

  const colors = ['#16a34a', '#65a30d', '#f59e0b', '#dc2626'];
  const labels = Object.keys(ranges);
  const values = Object.values(ranges);

  drawBarChart(ctx, canvas, labels, values, colors);
}

function drawBarChart(ctx, canvas, labels, values, colors) {
  const padding = 40;
  const chartWidth = canvas.width - 2 * padding;
  const chartHeight = canvas.height - 2 * padding - 30;
  const barWidth = chartWidth / labels.length * 0.8;
  const barSpacing = chartWidth / labels.length * 0.2;
  const maxValue = Math.max(...values);

  if (maxValue === 0) {
    ctx.fillStyle = '#6b7280';
    ctx.font = '14px system-ui';
    ctx.textAlign = 'center';
    ctx.fillText('データがありません', canvas.width / 2, canvas.height / 2);
    return;
  }

  ctx.strokeStyle = '#e5e7eb';
  ctx.lineWidth = 1;
  ctx.beginPath();
  ctx.moveTo(padding, padding);
  ctx.lineTo(padding, padding + chartHeight);
  ctx.lineTo(padding + chartWidth, padding + chartHeight);
  ctx.stroke();

  labels.forEach((label, i) => {
    const value = values[i];
    const barHeight = (value / maxValue) * chartHeight;
    const x = padding + i * (barWidth + barSpacing) + barSpacing / 2;
    const y = padding + chartHeight - barHeight;

    ctx.fillStyle = colors[i % colors.length];
    ctx.fillRect(x, y, barWidth, barHeight);

    if (value > 0) {
      ctx.fillStyle = '#374151';
      ctx.font = '12px system-ui';
      ctx.textAlign = 'center';
      ctx.fillText(value + '人', x + barWidth / 2, y - 5);
    }

    ctx.fillStyle = '#6b7280';
    ctx.font = '11px system-ui';
    ctx.textAlign = 'center';
    ctx.fillText(label, x + barWidth / 2, padding + chartHeight + 20);
  });

  ctx.fillStyle = '#6b7280';
  ctx.font = '10px system-ui';
  ctx.textAlign = 'right';
  for (let i = 0; i <= maxValue; i += Math.ceil(maxValue / 5)) {
    const y = padding + chartHeight - (i / maxValue) * chartHeight;
    ctx.fillText(i, padding - 8, y + 3);

    if (i > 0) {
      ctx.strokeStyle = '#f3f4f6';
      ctx.beginPath();
      ctx.moveTo(padding, y);
      ctx.lineTo(padding + chartWidth, y);
      ctx.stroke();
    }
  }
}

function exportCSV() {
  const period = document.getElementById('periodSelect').value;
  window.location.href = '{{ route("staff.export.csv") }}?type=organization&period=' + encodeURIComponent(period);
}
</script>
@endsection
