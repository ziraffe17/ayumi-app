@extends('layouts.user')

@section('title', 'ホーム')

@section('styles')
<style>
    /* ウェルカムセクション */
    .welcome-section {
        background: white;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: opacity 0.5s ease-out;
    }

    .welcome-section.fade-out {
        opacity: 0;
    }

    .welcome-section.hidden {
        display: none;
    }

    .welcome-section h2 {
        color: #16a34a;
        margin-bottom: 12px;
        font-size: 24px;
    }

    .welcome-section p {
        color: #6b7280;
        line-height: 1.6;
    }

    /* 出勤管理カード */
    .attendance-control {
        background: white;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        text-align: center;
    }

    .attendance-status {
        margin-bottom: 20px;
    }

    .status-label {
        font-size: 14px;
        color: #6b7280;
        margin-bottom: 8px;
    }

    .status-time {
        font-size: 32px;
        font-weight: bold;
        color: #111827;
        margin-bottom: 4px;
    }

    .work-duration {
        font-size: 14px;
        color: #16a34a;
        margin-top: 8px;
    }

    .attendance-buttons {
        display: flex;
        gap: 16px;
        justify-content: center;
        margin-top: 20px;
    }

    .btn-attendance {
        padding: 12px 32px;
        border-radius: 8px;
        font-size: 18px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
        min-width: 120px;
    }

    .btn-checkin {
        background: #16a34a;
        color: white;
    }

    .btn-checkin:hover:not(:disabled) {
        background: #15803d;
    }

    .btn-checkout {
        background: #ef4444;
        color: white;
    }

    .btn-checkout:hover:not(:disabled) {
        background: #dc2626;
    }

    .btn-attendance:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background: #9ca3af;
    }

    /* ダッシュボードカード */
    .dashboard-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 32px;
    }

    .dashboard-card {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .dashboard-card h3 {
        font-size: 14px;
        color: #6b7280;
        margin-bottom: 12px;
        font-weight: 500;
    }

    .dashboard-card .value {
        font-size: 28px;
        font-weight: bold;
        color: #16a34a;
        margin-bottom: 8px;
    }

    .dashboard-card .value.warning {
        color: #f59e0b;
    }

    .dashboard-card .description {
        font-size: 13px;
        color: #9ca3af;
        margin-bottom: 12px;
    }

    .report-status-container {
        margin: 12px 0;
    }

    .report-status-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #f3f4f6;
    }

    .report-status-item:last-child {
        border-bottom: none;
    }

    .report-label {
        color: #6b7280;
        font-size: 14px;
    }

    .report-value {
        font-weight: 600;
        font-size: 16px;
    }

    .report-value.completed {
        color: #16a34a;
    }

    .report-value.pending {
        color: #f59e0b;
    }

    .report-value.not-required {
        color: #9ca3af;
    }

    /* プログレスバー */
    .progress-bar {
        width: 100%;
        height: 8px;
        background: #e5e7eb;
        border-radius: 4px;
        overflow: hidden;
        margin: 12px 0;
    }

    .progress-fill {
        height: 100%;
        background: #16a34a;
        transition: width 0.3s ease;
    }


    @media (max-width: 768px) {
        .dashboard-cards {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection

@section('content')
<div class="welcome-section">
    <h2>おかえりなさい！</h2>
    <p>今日も一日頑張りましょう。何かご不明な点がございましたら、いつでもスタッフにお声がけください。</p>
</div>

<!-- 出勤管理 -->
<div class="attendance-control">
    <div style="text-align: center; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 2px solid #e5e7eb;">
        <div style="font-size: 24px; font-weight: 600; color: #111827;" id="currentDate">
            読み込み中...
        </div>
    </div>
    <div class="attendance-status">
        <div style="display: flex; justify-content: space-around; margin-bottom: 20px;">
            <div style="text-align: center;">
                <div class="status-label">今日の予定</div>
                <div class="status-time" id="todayPlan" style="font-size: 20px; color: #6b7280;">読み込み中...</div>
            </div>
            <div style="text-align: center;">
                <div class="status-label">現在の状態</div>
                <div class="status-time" id="currentStatus">未出勤</div>
            </div>
        </div>
        <div class="work-duration" id="workDuration" style="display: none;">
            在所時間: <span id="durationTime">0:00</span>
        </div>
    </div>

    <div class="attendance-buttons">
        <button class="btn-attendance btn-checkin" id="checkinBtn" onclick="checkIn()">
            出勤
        </button>
        <button class="btn-attendance btn-checkout" id="checkoutBtn" onclick="checkOut()" disabled>
            退勤
        </button>
    </div>
    <div id="weekendMessage" style="display: none; color: #6b7280; margin-top: 12px; font-size: 14px;">
        土日は出退勤ボタンを使用できません
    </div>
</div>

<div class="dashboard-cards">
    <div class="dashboard-card">
        <h3>今月の出席率</h3>
        <div class="value" id="monthlyRate">--%</div>
        <div class="progress-bar">
            <div class="progress-fill" id="rateProgress" style="width:0%"></div>
        </div>
        <div class="description">目標: 90%以上</div>
    </div>

    <div class="dashboard-card">
        <h3>今月の出席日数</h3>
        <div class="value" id="attendanceDays">--/--</div>
        <div class="description" id="attendanceDescription">予定: --日 / 実績: --日</div>
    </div>

    <div class="dashboard-card">
        <h3>日報入力状況</h3>
        <div class="report-status-container">
            <div class="report-status-item">
                <span class="report-label">通所日報：</span>
                <span class="report-value" id="morningReportStatus">未確認</span>
            </div>
            <div class="report-status-item">
                <span class="report-label">退所日報：</span>
                <span class="report-value" id="eveningReportStatus">未確認</span>
            </div>
        </div>
        <a href="{{ route('user.reports.daily') }}" class="btn btn-primary" style="margin-top:12px;width:100%;padding:10px;background:#16a34a;color:white;text-decoration:none;display:block;text-align:center;border-radius:6px;">
            日報入力
        </a>
    </div>
</div>

@endsection

@section('scripts')
<script>
let checkInTime = null;
let durationInterval = null;

document.addEventListener('DOMContentLoaded', function() {
    updateCurrentDate();
    handleWelcomeMessage();
    loadTodayPlan();
    checkWeekendStatus();
    loadAttendanceStatus();
    loadDashboardData();
    setInterval(loadDashboardData, 5 * 60 * 1000);
});

function updateCurrentDate() {
    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth() + 1;
    const day = now.getDate();
    const weekdays = ['日', '月', '火', '水', '木', '金', '土'];
    const weekday = weekdays[now.getDay()];

    document.getElementById('currentDate').textContent =
        `${year}年${month}月${day}日 (${weekday})`;
}

function handleWelcomeMessage() {
    const today = new Date().toISOString().split('T')[0];
    const welcomeShown = localStorage.getItem('welcomeShownDate');
    const welcomeSection = document.querySelector('.welcome-section');

    if (welcomeShown === today) {
        // 今日既に表示済みなら即座に非表示
        welcomeSection.classList.add('hidden');
    } else {
        // 5秒後にフェードアウト
        setTimeout(() => {
            welcomeSection.classList.add('fade-out');
            setTimeout(() => {
                welcomeSection.classList.add('hidden');
                localStorage.setItem('welcomeShownDate', today);
            }, 500); // フェードアウトアニメーション完了後に非表示
        }, 5000);
    }
}

async function loadTodayPlan() {
    try {
        const today = getTodayJST();
        const todayDate = new Date();
        const dayOfWeek = todayDate.getDay(); // 0=日曜, 6=土曜

        // 土日は休日をデフォルト表示
        if (dayOfWeek === 0 || dayOfWeek === 6) {
            document.getElementById('todayPlan').innerHTML = '休日';
            document.getElementById('todayPlan').style.color = '#9ca3af';
            return;
        }

        const thisMonth = today.substring(0, 7); // YYYY-MM

        const response = await fetch(`/api/me/plans?month=${thisMonth}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            }
        });

        if (response.ok) {
            const result = await response.json();
            if (result.items && result.items.length >= 0) {
                const todayPlan = result.items.find(plan => plan.plan_date === today);

                if (todayPlan) {
                    if (todayPlan.plan_type === 'off') {
                        // 休みの場合は「休日」のみ表示
                        document.getElementById('todayPlan').innerHTML = '休日';
                        document.getElementById('todayPlan').style.color = '#9ca3af';
                    } else {
                        // 通所・在宅の場合は2行表示（種別 / 時間帯）
                        let planType = '';
                        let timeSlot = '';

                        if (todayPlan.plan_type === 'onsite') {
                            planType = '通所';
                        } else if (todayPlan.plan_type === 'remote') {
                            planType = '在宅';
                        }

                        if (todayPlan.plan_time_slot === 'am') {
                            timeSlot = '午前';
                        } else if (todayPlan.plan_time_slot === 'pm') {
                            timeSlot = '午後';
                        } else {
                            timeSlot = '終日';
                        }

                        document.getElementById('todayPlan').innerHTML = `${planType}<br><span style="font-size: 16px;">${timeSlot}</span>`;
                        document.getElementById('todayPlan').style.color = '#16a34a';
                    }
                } else {
                    document.getElementById('todayPlan').innerHTML = '予定なし';
                    document.getElementById('todayPlan').style.color = '#9ca3af';
                }
            }
        }
    } catch (error) {
        console.error('Today plan load error:', error);
        document.getElementById('todayPlan').innerHTML = '予定の取得に失敗';
        document.getElementById('todayPlan').style.color = '#ef4444';
    }
}

function checkWeekendStatus() {
    const today = new Date();
    const dayOfWeek = today.getDay(); // 0=日曜, 6=土曜

    if (dayOfWeek === 0 || dayOfWeek === 6) {
        // 土日の場合、ボタンを無効化
        document.getElementById('checkinBtn').disabled = true;
        document.getElementById('checkoutBtn').disabled = true;
        document.getElementById('weekendMessage').style.display = 'block';
    }
}

function getTodayJST() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

async function loadAttendanceStatus() {
    try {
        const today = new Date().toISOString().split('T')[0];
        
        // LocalStorageから本日の出勤状態を確認
        const checkedInToday = localStorage.getItem('checkedInToday');
        if (checkedInToday === today) {
            setCheckedInState();
            const savedCheckInTime = localStorage.getItem('checkInTime');
            if (savedCheckInTime) {
                checkInTime = new Date(savedCheckInTime);
                startDurationTimer();
            }
            return;
        }
        
        const response = await fetch(`/api/me/attendance/records?start_date=${today}&end_date=${today}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            }
        });

        if (response.ok) {
            const result = await response.json();
            console.log('Attendance status:', result);
            
            if (result.data && result.data.attendance_records && result.data.attendance_records.length > 0) {
                const todayRecord = result.data.attendance_records[0];
                if (todayRecord.slots && todayRecord.slots.length > 0) {
                    const slot = todayRecord.slots.find(s => s.record && s.record.type === 'onsite');
                    if (slot) {
                        // 既に出勤済み
                        setCheckedInState();
                        localStorage.setItem('checkedInToday', today);
                        // 出勤時刻を記録から推定（朝の記録時刻を使用）
                        const morningSlot = todayRecord.slots.find(s => s.time_slot === 'am' || s.time_slot === 'full');
                        if (morningSlot && morningSlot.record) {
                            checkInTime = new Date();
                            checkInTime.setHours(9, 0, 0, 0); // デフォルト9:00
                            localStorage.setItem('checkInTime', checkInTime.toISOString());
                            startDurationTimer();
                        }
                    }
                }
            }
        }
    } catch (error) {
        console.error('Attendance status load error:', error);
    }
}

async function checkIn() {
    // ボタンを即座に無効化（二重クリック防止）
    document.getElementById('checkinBtn').disabled = true;

    try {
        const today = new Date().toISOString().split('T')[0];
        const userId = {{ Auth::id() ?? 'null' }};

        if (!userId) {
            throw new Error('ユーザーが認証されていません');
        }

        const response = await fetch('/api/me/attendance/records', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                user_id: userId,
                record_date: today,
                record_time_slot: 'full',
                attendance_type: 'onsite',
                source: 'self',
                note: '出勤時刻: ' + new Date().toLocaleTimeString('ja-JP')
            })
        });

        const result = await response.json();
        console.log('Check-in response:', result);
        
        if (response.ok && result.success) {
            checkInTime = new Date();
            localStorage.setItem('checkInTime', checkInTime.toISOString());
            localStorage.setItem('checkedInToday', today);
            setCheckedInState();
            startDurationTimer();
            showNotification('出勤を記録しました', 'success');
            loadDashboardData();
        } else {
            if (result.message && result.message.includes('既に登録されています')) {
                showNotification('本日は既に出勤済みです', 'info');
                setCheckedInState();
                // 既に記録がある場合も状態を保存
                localStorage.setItem('checkedInToday', today);
            } else {
                // エラーの場合はボタンを有効に戻す
                document.getElementById('checkinBtn').disabled = false;
                throw new Error(result.message || '出勤記録に失敗しました');
            }
        }
    } catch (error) {
        console.error('Check-in error:', error);
        document.getElementById('checkinBtn').disabled = false;
        showNotification('出勤記録に失敗しました', 'error');
    }
}

async function checkOut() {
    if (!confirm('退勤してもよろしいですか？')) return;
    
    try {
        const duration = calculateDuration();
        showNotification(`退勤を記録しました。本日の在所時間: ${duration}`, 'success');
        
        // 状態をリセット
        localStorage.removeItem('checkInTime');
        checkInTime = null;
        stopDurationTimer();
        
        document.getElementById('currentStatus').textContent = '退勤済み';
        document.getElementById('checkinBtn').disabled = true;
        document.getElementById('checkoutBtn').disabled = true;
        document.getElementById('workDuration').style.display = 'none';
        
    } catch (error) {
        console.error('Check-out error:', error);
        showNotification('退勤記録に失敗しました', 'error');
    }
}

function setCheckedInState() {
    console.log('=== setCheckedInState called ===');
    document.getElementById('currentStatus').textContent = '出勤中';
    document.getElementById('currentStatus').style.color = '#16a34a';

    const checkinBtn = document.getElementById('checkinBtn');
    const checkoutBtn = document.getElementById('checkoutBtn');

    checkinBtn.disabled = true;
    checkoutBtn.disabled = false;

    console.log('Button states after update:', {
        checkinDisabled: checkinBtn.disabled,
        checkoutDisabled: checkoutBtn.disabled
    });

    // 土日でも出勤済みなら退勤ボタンは有効にする
    const today = new Date();
    const dayOfWeek = today.getDay();
    const isWeekend = (dayOfWeek === 0 || dayOfWeek === 6);

    document.getElementById('workDuration').style.display = 'block';

    // 土日メッセージを非表示（出勤済みの場合）
    if (isWeekend) {
        document.getElementById('weekendMessage').style.display = 'none';
    }

    // LocalStorageから出勤時刻を復元
    const savedCheckInTime = localStorage.getItem('checkInTime');
    if (savedCheckInTime) {
        checkInTime = new Date(savedCheckInTime);
        startDurationTimer();
    }
}

function startDurationTimer() {
    if (durationInterval) clearInterval(durationInterval);
    
    const updateDuration = () => {
        const duration = calculateDuration();
        document.getElementById('durationTime').textContent = duration;
    };
    
    updateDuration();
    durationInterval = setInterval(updateDuration, 60000); // 1分ごとに更新
}

function stopDurationTimer() {
    if (durationInterval) {
        clearInterval(durationInterval);
        durationInterval = null;
    }
}

function calculateDuration() {
    if (!checkInTime) return '0:00';
    
    const now = new Date();
    const diff = now - checkInTime;
    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    
    return `${hours}:${minutes.toString().padStart(2, '0')}`;
}

async function loadDashboardData() {
    try {
        const userId = {{ Auth::id() ?? 'null' }};
        
        if (!userId) {
            console.error('User not authenticated');
            return;
        }
        
        const response = await fetch(`/api/me/dashboard?user_id=${userId}&days=30`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            }
        });

        if (!response.ok) throw new Error('データの取得に失敗しました');
        
        const result = await response.json();
        
        if (result.success) {
            updateDashboard(result.data);
        }
        
    } catch (error) {
        console.error('Dashboard data fetch error:', error);
    }
}

function updateDashboard(data) {
    const rate = data.current_month?.kpi?.attendance?.attendance_rate || 0;
    const rateDisplay = data.current_month?.kpi?.attendance?.attendance_rate_display || '0%';
    document.getElementById('monthlyRate').textContent = rateDisplay;
    document.getElementById('rateProgress').style.width = rate + '%';

    // 実出席数と当月全体の予定日数
    const actual = data.current_month?.kpi?.attendance?.attended_days || 0;
    const totalPlanned = data.current_month?.kpi?.attendance?.total_planned_days || 0;

    document.getElementById('attendanceDays').textContent = `${actual}/${totalPlanned}`;
    document.getElementById('attendanceDescription').textContent = `予定: ${totalPlanned}日 / 実績: ${actual}日`;

    updateTodayReportStatus();
}

async function updateTodayReportStatus() {
    const today = new Date().toISOString().split('T')[0];

    try {
        // まず今日の予定または実績を確認
        const shouldReport = await checkShouldReportToday(today);

        if (!shouldReport) {
            setReportStatus('morningReportStatus', '対象外', 'not-required');
            setReportStatus('eveningReportStatus', '対象外', 'not-required');
            return;
        }

        // 予定または実績がある場合は日報状態をチェック
        await checkReportStatus('morning', today);
        await checkReportStatus('evening', today);

    } catch (error) {
        console.error('Report status check error:', error);
        setReportStatus('morningReportStatus', 'エラー', 'pending');
        setReportStatus('eveningReportStatus', 'エラー', 'pending');
    }
}

async function checkShouldReportToday(today) {
    try {
        // LocalStorageをまずチェック
        const checkedInToday = localStorage.getItem('checkedInToday');
        if (checkedInToday === today) {
            return true;
        }

        // 1. 予定（plan）をチェック
        const thisMonth = today.substring(0, 7); // YYYY-MM
        const planResponse = await fetch(`/api/me/plans?month=${thisMonth}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            }
        });

        if (planResponse.ok) {
            const planResult = await planResponse.json();
            if (planResult.items) {
                const todayPlan = planResult.items.find(plan => plan.plan_date === today);
                if (todayPlan && ['onsite', 'remote'].includes(todayPlan.plan_type)) {
                    return true; // 予定がある場合は日報対象
                }
            }
        }

        // 2. 実績（record）をチェック
        const recordResponse = await fetch(`/api/me/attendance/records?start_date=${today}&end_date=${today}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            }
        });

        if (recordResponse.ok) {
            const recordData = await recordResponse.json();

            if (recordData.success && recordData.data && recordData.data.attendance_records) {
                const todayRecord = recordData.data.attendance_records.find(record =>
                    record.record_date === today
                );

                if (todayRecord && todayRecord.slots && todayRecord.slots.length > 0) {
                    const hasAttendance = todayRecord.slots.some(slot =>
                        slot.record && ['onsite', 'remote'].includes(slot.record.type)
                    );

                    if (hasAttendance) {
                        localStorage.setItem('checkedInToday', today);
                        return true; // 実績がある場合は日報対象
                    }
                }
            }
        }

        return false; // 予定も実績もない場合は対象外

    } catch (error) {
        console.error('Should report check error:', error);
        // エラーの場合はLocalStorageの情報を使用
        return localStorage.getItem('checkedInToday') === today;
    }
}

async function checkReportStatus(reportType, today) {
    try {
        console.log(`=== DEBUG: Checking ${reportType} report for ${today} ===`);
        console.log(`API URL: /api/me/reports/${reportType}?start_date=${today}&end_date=${today}`);
        console.log(`CSRF Token: ${csrfToken}`);
        
        const response = await fetch(`/api/me/reports/${reportType}?start_date=${today}&end_date=${today}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            }
        });
        
        console.log(`Response status: ${response.status}`);
        console.log(`Response headers:`, Object.fromEntries(response.headers.entries()));
        
        if (!response.ok) {
            console.warn(`${reportType} report API returned ${response.status}`);
            const errorText = await response.text();
            console.error(`Error response body:`, errorText);
            setReportStatus(`${reportType}ReportStatus`, 'エラー', 'pending');
            return;
        }
        
        const data = await response.json();
        console.log(`=== FULL ${reportType} API response ===`, JSON.stringify(data, null, 2));
        
        // APIレスポンスの形式を確認
        let hasReport = false;
        
        if (data.success && data.data && data.data.reports) {
            console.log(`${reportType} reports array:`, data.data.reports);
            console.log(`Reports count: ${data.data.reports.length}`);
            
            // 今日の日付に一致する日報があるかチェック（ISO形式 -> YYYY-MM-DD形式に変換して比較）
            hasReport = data.data.reports.some(report => {
                const reportDate = report.report_date.split('T')[0]; // ISO形式から日付部分を抽出
                console.log(`Checking ${reportType} report:`, {
                    id: report.id,
                    original_report_date: report.report_date,
                    extracted_report_date: reportDate,
                    today: today,
                    matches: reportDate === today
                });
                return reportDate === today;
            });
            
            console.log(`${reportType} report exists for today: ${hasReport}`);
        } else {
            console.log(`${reportType} API response format unexpected:`, {
                success: data.success,
                hasData: !!data.data,
                hasReports: !!(data.data && data.data.reports),
                fullData: data
            });
        }
        
        const statusText = hasReport ? '提出済み' : '要入力';
        const statusClass = hasReport ? 'completed' : 'pending';
        setReportStatus(`${reportType}ReportStatus`, statusText, statusClass);
        
        console.log(`=== ${reportType} status set to: ${statusText} ===`);
        
    } catch (error) {
        console.error(`${reportType} report check error:`, error);
        setReportStatus(`${reportType}ReportStatus`, 'エラー', 'pending');
    }
}

function setReportStatus(elementId, text, statusClass) {
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = text;
        element.className = `report-value ${statusClass}`;
    }
}

</script>
@endsection