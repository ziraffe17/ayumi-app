@extends('layouts.user')

@section('title', '個人ダッシュボード')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
@endsection

@section('content')
        {{-- ツールバー --}}
        <div class="toolbar">
            <label for="periodSelect">表示期間:</label>
            <select class="form-control" id="periodSelect">
                <option value="current_month" selected>今月</option>
                <option value="recent_3months">直近3ヶ月</option>
                <option value="all">全期間</option>
                <option value="specific_month">特定の月</option>
            </select>
            <select class="form-control" id="monthSelect" style="display:none;">
                <!-- 動的に生成 -->
            </select>
            <button class="btn btn-refresh" id="refreshBtn">更新</button>
        </div>

        {{-- KPI --}}
        <div class="kpi-cards">
            <div class="kpi-row-1">
                <div class="kpi-card">
                    <h3>出席数</h3>
                    <div class="value" id="actualDays">--</div>
                </div>
                <div class="kpi-card">
                    <h3>欠席数</h3>
                    <div class="value" id="absences">--</div>
                </div>
                <div class="kpi-card">
                    <h3>予定日数</h3>
                    <div class="value" id="plannedDays">--</div>
                </div>
            </div>
            <div class="kpi-row-2">
                <div class="kpi-card">
                    <h3>出席率</h3>
                    <div class="value" id="attendanceRate">--%</div>
                </div>
                <div class="kpi-card">
                    <h3>日報入力率</h3>
                    <div class="value" id="reportRate">--%</div>
                </div>
            </div>
        </div>

        {{-- 出席カレンダー --}}
        <div class="attendance-calendar">
            <h3 class="calendar-title">出席カレンダー</h3>
            <div class="calendar-nav">
                <button class="btn" id="prevMonthBtn">← 前月</button>
                <span id="currentMonth">—年—月</span>
                <button class="btn" id="nextMonthBtn">来月 →</button>
            </div>

            <div class="calendar-grid">
                <div class="calendar-header-cell sunday">日</div>
                <div class="calendar-header-cell">月</div>
                <div class="calendar-header-cell">火</div>
                <div class="calendar-header-cell">水</div>
                <div class="calendar-header-cell">木</div>
                <div class="calendar-header-cell">金</div>
                <div class="calendar-header-cell saturday">土</div>
            </div>

            <div class="calendar-grid" id="calendarBody"></div>

            <div class="legend">
                <div class="legend-item">
                    <div class="status planned onsite" style="font-size:10px; padding:2px 4px;">通所</div>
                    <div class="status actual onsite" style="font-size:10px; padding:2px 4px;">通所</div>
                    <span style="margin-left:6px; font-size:10px; color:#6b7280; white-space:nowrap;">点線枠：予定　塗りつぶし：実績</span>
                </div>
            </div>
        </div>

        {{-- チャート（簡易 Canvas） --}}
        <div class="chart-section">
            <h3>出席率推移</h3>
            <canvas id="attendanceChart" width="800" height="300"></canvas>
        </div>

        {{-- 日報トレンド --}}
        <div class="report-trends">
            <div class="trend-card">
                <h4>体調トレンド</h4>
                <canvas id="healthTrendChart" width="300" height="200"></canvas>
                <div class="legend">
                    <div class="legend-item"><div class="legend-color" style="background:#16a34a;"></div><span>睡眠</span></div>
                    <div class="legend-item"><div class="legend-color" style="background:#3b82f6;"></div><span>ストレス</span></div>
                    <div class="legend-item"><div class="legend-color" style="background:#f59e0b;"></div><span>食事</span></div>
                </div>
            </div>
            <div class="trend-card">
                <h4>気分スコア推移</h4>
                <canvas id="moodChart" width="300" height="200"></canvas>
                <div class="legend">
                    <div class="legend-item"><div class="legend-color" style="background:#8b5cf6;"></div><span>気分スコア (1-10)</span></div>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('scripts')
<script>
    (() => {
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        let currentDate = new Date();
        let dashboardData = {};

        // 初期化
        document.addEventListener('DOMContentLoaded', () => {
            // 月選択ドロップダウンを生成
            initializeMonthSelect();

            // 画面イベント
            document.getElementById('prevMonthBtn').addEventListener('click', () => changeMonth(-1));
            document.getElementById('nextMonthBtn').addEventListener('click', () => changeMonth(1));
            document.getElementById('refreshBtn').addEventListener('click', () => { loadDashboardData(); });
            document.getElementById('periodSelect').addEventListener('change', handlePeriodChange);
            document.getElementById('monthSelect').addEventListener('change', () => { loadDashboardData(); });

            // 初回ロード（チャート描画はAPIデータ取得後に実行）
            loadDashboardData();
            
            // ウィンドウリサイズ時にチャートを再描画
            window.addEventListener('resize', () => {
                if (dashboardData && Object.keys(dashboardData).length > 0) {
                    drawCharts();
                }
            });
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

            loadDashboardData();
        }

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

            loadDashboardData();
        }

        // 実際のAPIから個人ダッシュボードデータを取得
        async function loadDashboardData() {
            try {
                const periodType = document.getElementById('periodSelect').value;
                let monthParam = currentDate.getFullYear() + '-' + String(currentDate.getMonth() + 1).padStart(2, '0');

                // 特定の月を選択した場合は、monthSelectの値を使用
                if (periodType === 'specific_month') {
                    monthParam = document.getElementById('monthSelect').value;
                }

                console.log(`=== Dashboard API Call: /api/me/dashboard?period=${periodType}&month=${monthParam} ===`);

                const response = await fetch(`/api/me/dashboard?period=${periodType}&month=${monthParam}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf
                    }
                });

                console.log('Dashboard API Response Status:', response.status);

                if (!response.ok) {
                    throw new Error('データの取得に失敗しました');
                }

                const result = await response.json();
                console.log('=== Dashboard API Response ===', JSON.stringify(result, null, 2));

                if (result.success) {
                    const data = result.data;

                    // KPIデータを設定（選択期間のデータを表示）
                    dashboardData = {
                        plannedDays: data.kpi.attendance.planned_days,
                        actualDays: data.kpi.attendance.attended_days,
                        absentDays: data.kpi.attendance.absent_days || 0,
                        attendanceRate: data.kpi.attendance.attendance_rate || 0,
                        difference: data.kpi.attendance.difference || 0,
                        reportRate: data.kpi.reports.report_rate || 0,

                        // チャート用データ
                        attendanceHistory: data.trends.attendance || [],
                        healthTrend: data.trends.reports || {},
                        moodTrend: data.trends.reports?.mood || [],
                        calendarData: data.current_month.calendar || []
                    };

                    console.log('=== Final dashboardData ===', dashboardData);
                    console.log('attendanceHistory:', dashboardData.attendanceHistory);
                    console.log('healthTrend:', dashboardData.healthTrend);
                    console.log('moodTrend:', dashboardData.moodTrend);

                    updateKPICards();
                    generateCalendar();
                    drawCharts();
                } else {
                    throw new Error(result.message || 'データの取得に失敗しました');
                }
            } catch (error) {
                console.error('Dashboard data load error:', error);
                // フォールバック：デモデータを使用
                dashboardData = {
                    plannedDays: 20,
                    actualDays: 17,
                    attendanceRate: 85.0,
                    difference: -3,
                    reportRate: 80.0,
                    attendanceHistory: [],
                    healthTrend: {},
                    moodTrend: [],
                    calendarData: []
                };
                updateKPICards();

                // エラー通知（オプション）
                if (typeof showNotification === 'function') {
                    showNotification('一部データの読み込みに失敗しました', 'warning');
                }
            }
        }

        function updateKPICards() {
            setText('plannedDays', dashboardData.plannedDays);
            setText('actualDays', dashboardData.actualDays);
            setText('attendanceRate', dashboardData.attendanceRate + '%');

            // 欠席数（APIから取得）
            const absences = dashboardData.absentDays || 0;
            const absEl = byId('absences');
            absEl.textContent = absences;
            absEl.className = absences > 0 ? 'value danger' : 'value';

            const reportEl = byId('reportRate');
            reportEl.textContent = dashboardData.reportRate + '%';
            reportEl.className = dashboardData.reportRate < 90 ? 'value warning' : 'value';
        }

        // カレンダー
        function generateCalendar() {
            const y = currentDate.getFullYear();
            const m = currentDate.getMonth();
            byId('currentMonth').textContent = `${y}年${m+1}月`;

            const first = new Date(y, m, 1);
            const start = new Date(first);
            start.setDate(start.getDate() - first.getDay());
            const body = byId('calendarBody');
            let html = '';
            let d = new Date(start);

            for (let week=0; week<6; week++) {
                for (let day=0; day<7; day++) {
                    const isCurrentMonth = d.getMonth() === m;
                    const isToday = sameDate(d, new Date());
                    const attendanceStatus = getAttendanceStatus(d);
                    const dayOfWeek = d.getDay(); // 0=日曜, 6=土曜

                    let cls = 'calendar-cell';
                    if (!isCurrentMonth) cls += ' other-month';
                    if (isToday) cls += ' today';
                    if (dayOfWeek === 0) cls += ' sunday weekend';
                    if (dayOfWeek === 6) cls += ' saturday weekend';

                    let statusHtml = '';
                    if (isCurrentMonth && attendanceStatus) {
                        // 予定と実績を併記
                        if (attendanceStatus.planned && attendanceStatus.actual) {
                            // 両方ある場合
                            let plannedClass = '';
                            if (attendanceStatus.planned.includes('通所')) {
                                plannedClass = 'onsite';
                            } else if (attendanceStatus.planned.includes('在宅')) {
                                plannedClass = 'remote';
                            } else if (attendanceStatus.planned.includes('休み')) {
                                plannedClass = 'off';
                            }
                            
                            let actualClass = '';
                            if (attendanceStatus.actual.includes('通所')) {
                                actualClass = 'onsite';
                            } else if (attendanceStatus.actual.includes('在宅')) {
                                actualClass = 'remote';
                            } else if (attendanceStatus.actual.includes('欠席')) {
                                actualClass = 'absent';
                            }
                            
                            statusHtml = `
                                <div class="status-container">
                                    <div class="status planned ${plannedClass}">${attendanceStatus.planned}</div>
                                    <div class="status actual ${actualClass}">${attendanceStatus.actual}</div>
                                </div>`;
                        } else if (attendanceStatus.planned) {
                            // 予定のみ
                            let plannedClass = '';
                            if (attendanceStatus.planned.includes('通所')) {
                                plannedClass = 'onsite';
                            } else if (attendanceStatus.planned.includes('在宅')) {
                                plannedClass = 'remote';
                            } else if (attendanceStatus.planned.includes('休み')) {
                                plannedClass = 'off';
                            }
                            
                            statusHtml = `<div class="status planned ${plannedClass}">${attendanceStatus.planned}</div>`;
                        } else if (attendanceStatus.actual) {
                            // 実績のみ
                            let actualClass = '';
                            if (attendanceStatus.actual.includes('通所')) {
                                actualClass = 'onsite';
                            } else if (attendanceStatus.actual.includes('在宅')) {
                                actualClass = 'remote';
                            } else if (attendanceStatus.actual.includes('欠席')) {
                                actualClass = 'absent';
                            }
                            
                            statusHtml = `<div class="status actual ${actualClass}">${attendanceStatus.actual}</div>`;
                        }
                    }

                    html += `<div class="${cls}">
                        <div class="day">${d.getDate()}</div>
                        ${statusHtml}
                    </div>`;
                    d.setDate(d.getDate() + 1);
                }
            }
            body.innerHTML = html;
        }

        async function changeMonth(dir) {
            currentDate.setMonth(currentDate.getMonth() + dir);

            // 月が変わったらAPIから該当月のデータを再取得
            const year = currentDate.getFullYear();
            const month = String(currentDate.getMonth() + 1).padStart(2, '0');
            const monthParam = `${year}-${month}`;

            try {
                const period = parseInt(document.getElementById('periodSelect').value);
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                const response = await fetch(`/api/me/dashboard?days=${period}&month=${monthParam}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf
                    }
                });

                if (response.ok) {
                    const result = await response.json();
                    if (result.success) {
                        dashboardData.calendarData = result.data.current_month.calendar || [];
                    }
                }
            } catch (error) {
                console.error('Calendar data fetch error:', error);
            }

            generateCalendar();
        }

        // 実際のデータから出席状況を取得（予定と実績を併記）
        function getAttendanceStatus(date) {
            if (!dashboardData.calendarData || dashboardData.calendarData.length === 0) {
                return null;
            }

            // ローカル日付をYYYY-MM-DD形式に変換（タイムゾーンずれを防ぐ）
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const dateString = `${year}-${month}-${day}`;
            
            // その日のデータを取得
            const dayData = dashboardData.calendarData.find(d => d.date === dateString);
            
            if (!dayData) return null;
            
            const statusInfo = {};
            
            // 新しいAPI構造を使用（planned_status, actual_status）
            if (dayData.planned_status) {
                statusInfo.planned = dayData.planned_status;
            }
            
            if (dayData.actual_status) {
                statusInfo.actual = dayData.actual_status;
            }
            
            // 新しい構造がない場合は従来の方法でフォールバック
            if (!statusInfo.planned && !statusInfo.actual && dayData.status) {
                // 実績の処理
                if (['onsite', 'remote', 'absent'].includes(dayData.status)) {
                    switch (dayData.status) {
                        case 'onsite':
                            statusInfo.actual = '通所';
                            break;
                        case 'remote':
                            statusInfo.actual = '在宅';
                            break;
                        case 'absent':
                            statusInfo.actual = '欠席';
                            break;
                    }
                }
                
                // 予定の処理（planned_onsite など）
                if (dayData.status.startsWith('planned_')) {
                    const plannedType = dayData.status.replace('planned_', '');
                    switch (plannedType) {
                        case 'onsite':
                            statusInfo.planned = '通所';
                            break;
                        case 'remote':
                            statusInfo.planned = '在宅';
                            break;
                        case 'off':
                            statusInfo.planned = '休み';
                            break;
                    }
                } else if (dayData.status === 'off') {
                    statusInfo.planned = '休み';
                }
            }
            
            // データがない場合は null を返す
            if (!statusInfo.planned && !statusInfo.actual) {
                return null;
            }
            
            return statusInfo;
        }

        // チャート
        function drawCharts() { drawAttendanceChart(); drawHealthTrendChart(); drawMoodChart(); }

        function drawAttendanceChart() {
            console.log('=== drawAttendanceChart called ===');
            const c = byId('attendanceChart');
            if (!c) {
                console.error('attendanceChart canvas not found');
                return;
            }

            // レスポンシブ対応：キャンバスサイズを動的に調整
            const container = c.parentElement;
            const containerWidth = container.clientWidth - 32; // padding考慮
            const isMobile = window.innerWidth <= 768;

            // 高解像度対応
            const dpr = window.devicePixelRatio || 1;
            const displayWidth = Math.min(containerWidth, isMobile ? 300 : 800);
            const displayHeight = isMobile ? 180 : 300;

            c.width = displayWidth * dpr;
            c.height = displayHeight * dpr;
            c.style.width = displayWidth + 'px';
            c.style.height = displayHeight + 'px';
            
            const ctx = c.getContext('2d');
            ctx.scale(dpr, dpr);
            ctx.clearRect(0,0,c.width,c.height);

            console.log('dashboardData:', dashboardData);
            console.log('attendanceHistory:', dashboardData?.attendanceHistory);

            // データ存在チェックを追加
            if (!dashboardData || !dashboardData.attendanceHistory || !Array.isArray(dashboardData.attendanceHistory)) {
                console.log('No attendance data available');
                ctx.fillStyle = '#6b7280';
                ctx.font = '16px system-ui';
                ctx.textAlign = 'center';
                ctx.fillText('データがありません', displayWidth / 2, displayHeight / 2);
                return;
            }

            const data = dashboardData.attendanceHistory.map(x => x.rate || 0);
            const dates = dashboardData.attendanceHistory.map(x => x.date || '');
            console.log('Attendance data for chart:', data);
            const maxY = 100;

            if (data.length === 0) {
                // データがない場合はメッセージを表示
                ctx.fillStyle = '#6b7280';
                ctx.font = '16px system-ui';
                ctx.textAlign = 'center';
                ctx.fillText('データがありません', displayWidth / 2, displayHeight / 2);
                return;
            }

            // X軸の目盛り数を、データ数に応じて設定（最大12ヶ月）
            const xTicks = Math.max(1, data.length - 1);
            drawAxes(ctx, displayWidth, displayHeight, maxY, 20, xTicks, dates);
            drawLineInArea(ctx, data, '#16a34a', displayWidth, displayHeight, maxY);
        }

        function drawHealthTrendChart() {
            const c = byId('healthTrendChart');
            if (!c) {
                console.error('healthTrendChart canvas not found');
                return;
            }

            // レスポンシブ対応：キャンバスサイズを動的に調整
            const container = c.parentElement;
            const containerWidth = container.clientWidth - 32; // padding考慮
            const isMobile = window.innerWidth <= 768;

            // 高解像度対応
            const dpr = window.devicePixelRatio || 1;
            const displayWidth = Math.min(containerWidth, isMobile ? 280 : 300);
            const displayHeight = isMobile ? 160 : 200;

            c.width = displayWidth * dpr;
            c.height = displayHeight * dpr;
            c.style.width = displayWidth + 'px';
            c.style.height = displayHeight + 'px';

            const ctx = c.getContext('2d');
            ctx.scale(dpr, dpr);
            ctx.clearRect(0,0,c.width,c.height);
            const maxY = 3;

            // 実際のAPIデータ構造: data.trends.reports.sleep/stress/meal
            if (!dashboardData || !dashboardData.healthTrend ||
                (!dashboardData.healthTrend.sleep && !dashboardData.healthTrend.stress && !dashboardData.healthTrend.meal)) {
                ctx.fillStyle = '#6b7280';
                ctx.font = '14px system-ui';
                ctx.textAlign = 'center';
                ctx.fillText('データがありません', displayWidth / 2, displayHeight / 2);
                return;
            }

            const dates = dashboardData.healthTrend.sleep?.map(x => x.date) || [];
            // X軸の目盛り数をデータ数に応じて設定（直近7日なので最大6）
            const xTicks = Math.max(1, dates.length - 1);
            drawAxes(ctx, displayWidth, displayHeight, maxY, 0.5, xTicks, dates);

            // APIから取得したデータ構造に合わせて描画
            if (dashboardData.healthTrend.sleep && dashboardData.healthTrend.sleep.length > 0) {
                drawLineInArea(ctx, dashboardData.healthTrend.sleep.map(x => x.value || 0), '#16a34a', displayWidth, displayHeight, maxY);
            }
            if (dashboardData.healthTrend.stress && dashboardData.healthTrend.stress.length > 0) {
                drawLineInArea(ctx, dashboardData.healthTrend.stress.map(x => x.value || 0), '#f59e0b', displayWidth, displayHeight, maxY);
            }
            if (dashboardData.healthTrend.meal && dashboardData.healthTrend.meal.length > 0) {
                drawLineInArea(ctx, dashboardData.healthTrend.meal.map(x => x.value || 0), '#3b82f6', displayWidth, displayHeight, maxY);
            }
        }

        function drawMoodChart() {
            const c = byId('moodChart');
            if (!c) {
                console.error('moodChart canvas not found');
                return;
            }

            // レスポンシブ対応：キャンバスサイズを動的に調整
            const container = c.parentElement;
            const containerWidth = container.clientWidth - 32; // padding考慮
            const isMobile = window.innerWidth <= 768;

            // 高解像度対応
            const dpr = window.devicePixelRatio || 1;
            const displayWidth = Math.min(containerWidth, isMobile ? 280 : 300);
            const displayHeight = isMobile ? 160 : 200;

            c.width = displayWidth * dpr;
            c.height = displayHeight * dpr;
            c.style.width = displayWidth + 'px';
            c.style.height = displayHeight + 'px';

            const ctx = c.getContext('2d');
            ctx.scale(dpr, dpr);
            ctx.clearRect(0,0,c.width,c.height);
            const maxY = 10;

            if (!dashboardData || !dashboardData.moodTrend || dashboardData.moodTrend.length === 0) {
                // データがない場合はメッセージを表示
                ctx.fillStyle = '#6b7280';
                ctx.font = '14px system-ui';
                ctx.textAlign = 'center';
                ctx.fillText('データがありません', displayWidth / 2, displayHeight / 2);
                return;
            }

            // APIデータ構造: data.trends.reports.mood = [{date: "2025-10-01", value: 6}, ...]
            const moodData = dashboardData.moodTrend.map(x => x.value || 0);
            const dates = dashboardData.moodTrend.map(x => x.date || '');

            // X軸の目盛り数をデータ数に応じて設定（直近7日なので最大6）
            const xTicks = Math.max(1, dates.length - 1);
            drawAxes(ctx, displayWidth, displayHeight, maxY, 2, xTicks, dates);
            drawLineInArea(ctx, moodData, '#8b5cf6', displayWidth, displayHeight, maxY);
        }

        function drawLine(ctx, data, color, canvas, max=3) {
            ctx.strokeStyle = color; ctx.lineWidth = 2;
            const stepX = canvas.width / (data.length - 1);
            ctx.beginPath();
            data.forEach((v,i)=>{
                const x = i * stepX;
                const y = canvas.height - (v/max) * canvas.height;
                if (i===0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
            });
            ctx.stroke();
        }

        // ダミーデータ生成
        function generateAttendanceHistory(days) {
            const out=[]; for(let i=days-1;i>=0;i--){ const d=new Date(); d.setDate(d.getDate()-i);
                out.push({date:d.toISOString().split('T')[0], rate: 70+Math.random()*30}); }
            return out;
        }
        function generateHealthTrend(days) {
            const out=[]; for(let i=days-1;i>=0;i--){ const d=new Date(); d.setDate(d.getDate()-i);
                out.push({date:d.toISOString().split('T')[0], sleep:1+Math.random()*2, stress:1+Math.random()*2, meal:1+Math.random()*2}); }
            return out;
        }
        function generateMoodTrend(days) {
            const out=[]; for(let i=days-1;i>=0;i--){ const d=new Date(); d.setDate(d.getDate()-i);
                out.push({date:d.toISOString().split('T')[0], mood:4+Math.random()*6}); }
            return out;
        }

        // utils
        const byId = id => document.getElementById(id);
        const setText = (id, val) => { const el=byId(id); if (el) el.textContent = val; };
        const sameDate = (a,b)=>a.getFullYear()===b.getFullYear() && a.getMonth()===b.getMonth() && a.getDate()===b.getDate();

    })();

    // === 軸描画ユーティリティ ===
// y軸: 0..maxY の範囲で目盛り、x軸: データ数に応じた簡易目盛り
function drawAxes(ctx, width, height, maxY = 100, yStep = 20, xCount = 8, dates = [], padding = 32) {
  ctx.save();
  ctx.strokeStyle = '#e5e7eb';
  ctx.fillStyle   = '#6b7280';
  ctx.lineWidth   = 1;
  ctx.font        = '11px system-ui, sans-serif';
  ctx.textAlign   = 'right';
  ctx.textBaseline= 'middle';

  const left   = padding;
  const bottom = height - padding;
  const right  = width - padding;
  const top    = padding;

  // 枠
  ctx.beginPath();
  ctx.moveTo(left, top);
  ctx.lineTo(left, bottom);
  ctx.lineTo(right, bottom);
  ctx.stroke();

  // y 目盛り
  for (let y=0; y<=maxY; y+=yStep) {
    const py = bottom - (y/maxY) * (bottom - top);
    // 補助線
    ctx.beginPath();
    ctx.moveTo(left, py);
    ctx.lineTo(right, py);
    ctx.stroke();

    // ラベル
    ctx.fillText(String(y), left - 6, py);
  }

  // x 目盛り（目盛り線とラベル）
  const xStep = (right - left) / xCount;
  ctx.textAlign = 'center';
  ctx.textBaseline = 'top';
  for (let i=0; i<=xCount; i++) {
    const px = left + i * xStep;
    ctx.beginPath();
    ctx.moveTo(px, bottom);
    ctx.lineTo(px, bottom + 4);
    ctx.stroke();

    // 日付ラベル表示
    if (dates && dates.length > 0) {
      const dataIndex = Math.floor((i / xCount) * (dates.length - 1));
      if (dataIndex < dates.length && dates[dataIndex]) {
        const dateStr = dates[dataIndex];
        const dateParts = dateStr.split('-');
        if (dateParts.length === 3) {
          // 出席率チャートは年/月、それ以外は月/日
          if (maxY === 100) {
            // 月ごとの表示：重複を避けるため、その位置での月を取得
            const prevIndex = i > 0 ? Math.floor(((i-1) / xCount) * (dates.length - 1)) : -1;
            const prevDate = prevIndex >= 0 && dates[prevIndex] ? dates[prevIndex].split('-')[1] : null;
            const currentMonth = dateParts[1];

            // 前の目盛りと月が異なる場合のみ表示
            if (prevDate !== currentMonth || i === 0) {
              const label = `${dateParts[0].slice(2)}/${dateParts[1]}`;
              ctx.fillText(label, px, bottom + 6);
            }
          } else {
            // 日ごとの表示（体調トレンド、気分スコア）
            // データ数が少ない場合は全て表示、多い場合は適度に間引く
            const shouldShow = dates.length <= 7 || i % Math.ceil(xCount / 6) === 0 || i === xCount;
            if (shouldShow) {
              const label = `${dateParts[1]}/${dateParts[2]}`;
              ctx.fillText(label, px, bottom + 6);
            }
          }
        }
      }
    }
  }

  ctx.restore();
}

// 折れ線をデータ領域に収めて描画
function drawLineInArea(ctx, data, color, width, height, maxY, padding = 32) {
  ctx.save();
  const left   = padding;
  const bottom = height - padding;
  const right  = width - padding;
  const top    = padding;

  ctx.strokeStyle = color;
  ctx.lineWidth   = 2;

  const n = data.length;
  const stepX = n > 1 ? (right - left) / (n - 1) : 0;

  ctx.beginPath();
  data.forEach((v, i) => {
    const x = left + i * stepX;
    const y = bottom - (v / maxY) * (bottom - top);
    if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
  });
  ctx.stroke();

  // ポイント
  ctx.fillStyle = color;
  data.forEach((v, i) => {
    const x = left + i * stepX;
    const y = bottom - (v / maxY) * (bottom - top);
    ctx.beginPath(); ctx.arc(x, y, 3, 0, 2*Math.PI); ctx.fill();
  });

  ctx.restore();
}

    </script>
@endsection
