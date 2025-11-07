@extends('layouts.user')
@section('title', 'S-07U 日報入力')
@section('styles')
<style>
    /* タブ */
    .report-tabs {
        display: flex;
        gap: 0;
        margin-bottom: 24px;
        border-bottom: 2px solid var(--line);
        background: transparent;
    }
    .tab-button {
        padding: 12px 24px;
        border: none;
        background: transparent;
        cursor: pointer;
        font-size: 16px;
        font-weight: 500;
        color: var(--muted);
        border-bottom: 3px solid transparent;
        transition: all 0.2s;
        position: relative;
        top: 2px;
    }
    .tab-button.active {
        background: transparent;
        color: var(--brand-deep);
        border-bottom-color: var(--brand-deep);
    }
    .tab-button:hover:not(.active) {
        color: var(--ink);
    }
    
    /* 状態表示 */
    .status-indicator {
        display: inline-block;
        font-size: 11px;
        font-weight: normal;
        padding: 2px 6px;
        border-radius: 4px;
        margin-left: 8px;
        background: #f3f4f6;
        color: #6b7280;
    }
    
    .status-indicator.submitted {
        background: #dcfce7;
        color: #166534;
    }
    
    .status-indicator.pending {
        background: #fef3c7;
        color: #92400e;
    }
    
    .status-indicator.checking {
        background: #e0f2fe;
        color: #0277bd;
    }

    /* カード */
    .report-card {
        background: white;
        border-radius: 0 0 12px 12px;
        padding: 32px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 24px;
    }
    .report-card h2 {
        color: var(--brand-deep);
        margin-bottom: 24px;
        font-size: 20px;
    }

    /* フォーム */
    .form-section {
        margin-bottom: 32px;
    }
    .form-section h3 {
        color: var(--ink);
        margin-bottom: 16px;
        font-size: 18px;
        padding-bottom: 8px;
        border-bottom: 2px solid #f3f4f6;
    }
    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 24px;
        margin-bottom: 24px;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--ink);
    }
    .form-control {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid var(--line);
        border-radius: 8px;
        font-size: 16px;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .form-control:focus {
        outline: none;
        border-color: var(--brand-deep);
        box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
    }
    .form-control.error {
        border-color: #dc2626;
    }

    /* 評価ボタン */
    .rating-group {
        display: flex;
        gap: 12px;
        margin-top: 8px;
    }
    .rating-button {
        flex: 1;
        padding: 16px 24px;
        border: 2px solid #e5e7eb;
        background: white;
        border-radius: 8px;
        cursor: pointer;
        font-size: 24px;
        font-weight: bold;
        transition: all 0.2s;
        text-align: center;
    }
    .rating-button:hover {
        border-color: #d1d5db;
        background: #f9fafb;
    }
    .rating-button.selected {
        border-width: 3px;
        transform: scale(1.05);
    }
    .rating-button.good { color: var(--brand-deep); }
    .rating-button.good.selected { 
        background: #f0fdf4; 
        border-color: var(--brand-deep);
    }
    .rating-button.fair { color: #f59e0b; }
    .rating-button.fair.selected { 
        background: #fffbeb; 
        border-color: #f59e0b;
    }
    .rating-button.poor { color: #dc2626; }
    .rating-button.poor.selected { 
        background: #fef2f2; 
        border-color: #dc2626;
    }
    .rating-group.error .rating-button {
        border-color: #dc2626;
    }

    /* エラーメッセージ */
    .error-message {
        color: #dc2626;
        font-size: 14px;
        margin-top: 4px;
        display: none;
    }
    .error-message.show {
        display: block;
    }

    /* スライダー */
    .slider-container {
        margin-top: 8px;
    }
    .slider {
        width: 100%;
        height: 8px;
        border-radius: 4px;
        background: linear-gradient(to right, #16a34a 44%, #e5e7eb 44%);
        outline: none;
        margin-bottom: 8px;
        -webkit-appearance: none;
        appearance: none;
    }
    .slider::-webkit-slider-thumb {
        -webkit-appearance: none;
        appearance: none;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #16a34a;
        cursor: pointer;
    }
    .slider::-moz-range-thumb {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #16a34a;
        cursor: pointer;
        border: none;
    }
    .slider-value {
        text-align: center;
        font-weight: bold;
        font-size: 18px;
        color: var(--brand-deep);
    }

    /* チェックボックス */
    .checkbox-group {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-top: 8px;
    }
    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .checkbox-item input[type="checkbox"] {
        width: 18px;
        height: 18px;
        margin: 0;
        cursor: pointer;
    }

    /* 時間入力 */
    .time-input {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .time-input input {
        width: 140px;
    }
    .sleep-duration {
        color: var(--muted);
        font-size: 14px;
    }

    /* ボタン */
    .form-actions {
        display: flex;
        gap: 16px;
        justify-content: flex-end;
        margin-top: 32px;
        padding-top: 24px;
        border-top: 1px solid var(--line);
    }

    @media (max-width: 768px) {
        .report-card { padding: 20px; }
        .form-row { grid-template-columns: 1fr; gap: 16px; }
        .rating-group { gap: 8px; }
        .rating-button { padding: 12px 16px; font-size: 20px; }
        .form-actions { flex-direction: column; }
        .tab-button { padding: 12px 20px; font-size: 14px; }
    }
</style>
@endsection

@section('content')
<div class="alert info" style="margin-bottom: 24px;">
    <strong>本日（<span id="currentDate"></span>）の日報入力</strong><br>
    通所日報は通所時に、退所日報は退所時に入力してください。
</div>

<!-- タブ -->
<div class="report-tabs">
    <button class="tab-button active" id="morningTab" onclick="switchTab('morning')">
        通所日報
        <span class="status-indicator" id="morningStatus">確認中</span>
    </button>
    <button class="tab-button" id="eveningTab" onclick="switchTab('evening')">
        退所日報
        <span class="status-indicator" id="eveningStatus">確認中</span>
    </button>
</div>

<!-- 通所日報 -->
<div class="report-card" id="morningReport">
    <div class="alert success" id="morningSubmittedAlert" style="display: none;">
        <strong>✓ 通所日報は既に提出済みです</strong><br>
        内容を確認・編集できますが、変更する場合は再度保存してください。
    </div>
    <h2>通所日報</h2>
    <form id="morningForm">
        <!-- オフタイムコントロール -->
        <div class="form-section">
            <h3>オフタイムコントロール</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>睡眠 <span style="color:red">*</span></label>
                    <div class="rating-group" id="sleepRatingGroup">
                        <button type="button" class="rating-button good" data-name="sleep_rating" data-value="3">
                            ◯<br><span style="font-size:14px;font-weight:normal">良い</span>
                        </button>
                        <button type="button" class="rating-button fair" data-name="sleep_rating" data-value="2">
                            △<br><span style="font-size:14px;font-weight:normal">ふつう</span>
                        </button>
                        <button type="button" class="rating-button poor" data-name="sleep_rating" data-value="1">
                            ✕<br><span style="font-size:14px;font-weight:normal">悪い</span>
                        </button>
                    </div>
                    <div class="error-message" id="sleepRatingError"></div>
                </div>
                
                <div class="form-group">
                    <label>ストレス <span style="color:red">*</span></label>
                    <div class="rating-group" id="stressRatingGroup">
                        <button type="button" class="rating-button good" data-name="stress_rating" data-value="3">
                            ◯<br><span style="font-size:14px;font-weight:normal">良い</span>
                        </button>
                        <button type="button" class="rating-button fair" data-name="stress_rating" data-value="2">
                            △<br><span style="font-size:14px;font-weight:normal">ふつう</span>
                        </button>
                        <button type="button" class="rating-button poor" data-name="stress_rating" data-value="1">
                            ✕<br><span style="font-size:14px;font-weight:normal">悪い</span>
                        </button>
                    </div>
                    <div class="error-message" id="stressRatingError"></div>
                </div>

                <div class="form-group">
                    <label>食事 <span style="color:red">*</span></label>
                    <div class="rating-group" id="mealRatingGroup">
                        <button type="button" class="rating-button good" data-name="meal_rating" data-value="3">
                            ◯<br><span style="font-size:14px;font-weight:normal">良い</span>
                        </button>
                        <button type="button" class="rating-button fair" data-name="meal_rating" data-value="2">
                            △<br><span style="font-size:14px;font-weight:normal">ふつう</span>
                        </button>
                        <button type="button" class="rating-button poor" data-name="meal_rating" data-value="1">
                            ✕<br><span style="font-size:14px;font-weight:normal">悪い</span>
                        </button>
                    </div>
                    <div class="error-message" id="mealRatingError"></div>
                </div>

                <div class="form-group">
                    <label>起床時の気分 (1-10) <span style="color:red">*</span></label>
                    <div class="slider-container">
                        <input type="range" class="slider" min="1" max="10" value="5" id="moodScore">
                        <div class="slider-value" id="moodValue">5</div>
                    </div>
                    <div class="error-message" id="moodScoreError"></div>
                </div>
            </div>
        </div>

        <!-- 睡眠について -->
        <div class="form-section">
            <h3>睡眠について</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>就寝時刻 <span style="color:red">*</span></label>
                    <div class="time-input">
                        <input type="time" class="form-control" id="bedTime">
                    </div>
                    <div class="error-message" id="bedTimeError"></div>
                </div>

                <div class="form-group">
                    <label>起床時刻 <span style="color:red">*</span></label>
                    <div class="time-input">
                        <input type="time" class="form-control" id="wakeTime">
                        <div class="sleep-duration" id="sleepDuration">睡眠時間: --</div>
                    </div>
                    <div class="error-message" id="wakeTimeError"></div>
                </div>
            </div>
        </div>

        <!-- 生活習慣 -->
        <div class="form-section">
            <h3>生活習慣</h3>
            <div class="checkbox-group">
                <div class="checkbox-item">
                    <input type="checkbox" id="breakfastDone">
                    <label for="breakfastDone">朝食を摂った</label>
                </div>
                <div class="checkbox-item">
                    <input type="checkbox" id="bathingDone">
                    <label for="bathingDone">入浴を済ませた</label>
                </div>
                <div class="checkbox-item">
                    <input type="checkbox" id="medicationTaken">
                    <label for="medicationTaken">服薬した（該当する方のみ）</label>
                </div>
            </div>
        </div>

        <!-- 相談・連絡 -->
        <div class="form-section">
            <div class="form-group">
                <label>相談・連絡事項</label>
                <textarea class="form-control" id="morningNote" rows="4" 
                    placeholder="体調のことや気になることがあれば記入してください"></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn" onclick="clearForm('morning')">クリア</button>
            <button type="submit" class="btn green">保存</button>
        </div>
    </form>
</div>

<!-- 退所日報 -->
<div class="report-card" id="eveningReport" style="display: none;">
    <div class="alert success" id="eveningSubmittedAlert" style="display: none;">
        <strong>✓ 退所日報は既に提出済みです</strong><br>
        内容を確認・編集できますが、変更する場合は再度保存してください。
    </div>
    <h2>退所日報</h2>
    <form id="eveningForm">
        <!-- 訓練について -->
        <div class="form-section">
            <h3>訓練について</h3>
            <div class="form-group">
                <label>今日の訓練内容 <span style="color:red">*</span></label>
                <textarea class="form-control" id="trainingSummary" rows="4" 
                    placeholder="今日行った訓練や活動内容を記入してください"></textarea>
                <div class="error-message" id="trainingSummaryError"></div>
            </div>
            <div class="form-group">
                <label>訓練の振り返り <span style="color:red">*</span></label>
                <textarea class="form-control" id="trainingReflection" rows="4" 
                    placeholder="訓練で学んだことや感想を記入してください"></textarea>
                <div class="error-message" id="trainingReflectionError"></div>
            </div>
        </div>

        <!-- 体調について -->
        <div class="form-section">
            <h3>体調について</h3>
            <div class="form-group">
                <label>体調の変化や気づいたこと <span style="color:red">*</span></label>
                <textarea class="form-control" id="conditionNote" rows="4" 
                    placeholder="一日を通しての体調の変化や気づいたことを記入してください"></textarea>
                <div class="error-message" id="conditionNoteError"></div>
            </div>
        </div>

        <!-- その他 -->
        <div class="form-section">
            <div class="form-group">
                <label>その他（連絡事項など）</label>
                <textarea class="form-control" id="otherNote" rows="4" 
                    placeholder="職員に伝えたいことや明日の予定などがあれば記入してください"></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn" onclick="clearForm('evening')">クリア</button>
            <button type="submit" class="btn green">保存</button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
(function() {
    const $ = (id) => document.getElementById(id);
    let currentReportType = 'morning';
    let existingMorningId = null;
    let existingEveningId = null;
    
    // JST（日本時間）で今日の日付を取得
    function getTodayJST() {
        const now = new Date();
        // ローカル時刻でYYYY-MM-DD形式を作成（ブラウザのタイムゾーンを使用）
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const localDate = `${year}-${month}-${day}`;
        console.log(`getTodayJST: ${localDate} (browser local time)`);
        return localDate;
    }

    // 初期化
    document.addEventListener('DOMContentLoaded', function() {
        // 現在日付表示
        const today = new Date();
        $('currentDate').textContent = `${today.getFullYear()}年${today.getMonth() + 1}月${today.getDate()}日`;
        
        // 既存の日報データを読み込み
        loadExistingData();
        
        // 評価ボタンのイベント設定
        document.querySelectorAll('.rating-button').forEach(button => {
            button.addEventListener('click', function() {
                const name = this.dataset.name;
                
                // 同じグループの他のボタンの選択を解除
                document.querySelectorAll(`.rating-button[data-name="${name}"]`).forEach(btn => {
                    btn.classList.remove('selected');
                });
                
                // このボタンを選択状態に
                this.classList.add('selected');
                
                // エラーをクリア
                const groupId = name.replace('_rating', 'Rating') + 'Group';
                const errorId = name.replace('_rating', 'Rating') + 'Error';
                const group = document.getElementById(groupId);
                const error = document.getElementById(errorId);
                if (group) group.classList.remove('error');
                if (error) {
                    error.textContent = '';
                    error.classList.remove('show');
                }
            });
        });
        
        // 気分スコアスライダー
        $('moodScore').addEventListener('input', function() {
            $('moodValue').textContent = this.value;
            const percent = ((this.value - 1) / 9) * 100;
            this.style.background = `linear-gradient(to right, #16a34a ${percent}%, #e5e7eb ${percent}%)`;
        });
        
        // 初期値の色設定
        const initialPercent = ((5 - 1) / 9) * 100;
        $('moodScore').style.background = `linear-gradient(to right, #16a34a ${initialPercent}%, #e5e7eb ${initialPercent}%)`;
        
        // 睡眠時間計算
        $('bedTime').addEventListener('change', calculateSleepDuration);
        $('wakeTime').addEventListener('change', calculateSleepDuration);
        
        // 時刻入力時のエラークリア
        $('bedTime').addEventListener('change', function() {
            clearFieldError('bedTime');
        });
        $('wakeTime').addEventListener('change', function() {
            clearFieldError('wakeTime');
        });
        
        // テキストエリアのエラークリア（退所日報）
        ['trainingSummary', 'trainingReflection', 'conditionNote'].forEach(id => {
            const element = $(id);
            if (element) {
                element.addEventListener('input', function() {
                    if (this.value.trim() !== '') {
                        clearFieldError(id);
                    }
                });
            }
        });
        
        // フォーム送信
        $('morningForm').addEventListener('submit', (e) => {
            e.preventDefault();
            saveReport('morning');
        });
        
        $('eveningForm').addEventListener('submit', (e) => {
            e.preventDefault();
            saveReport('evening');
        });
        
        // 既存データ読み込み
        loadExistingData();
    });

    // タブ切り替え
    window.switchTab = function(type) {
        currentReportType = type;
        $('morningTab').classList.toggle('active', type === 'morning');
        $('eveningTab').classList.toggle('active', type === 'evening');
        $('morningReport').style.display = type === 'morning' ? 'block' : 'none';
        $('eveningReport').style.display = type === 'evening' ? 'block' : 'none';
    };

    // エラー表示関数
    function showError(fieldId, message) {
        const errorElement = document.getElementById(fieldId + 'Error');
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.classList.add('show');
        }
        
        const field = document.getElementById(fieldId);
        if (field) {
            field.classList.add('error');
        }
        
        const group = document.getElementById(fieldId + 'Group');
        if (group) {
            group.classList.add('error');
        }
    }

    function clearFieldError(fieldId) {
        const errorElement = document.getElementById(fieldId + 'Error');
        if (errorElement) {
            errorElement.textContent = '';
            errorElement.classList.remove('show');
        }
        
        const field = document.getElementById(fieldId);
        if (field) {
            field.classList.remove('error');
        }
        
        const group = document.getElementById(fieldId + 'Group');
        if (group) {
            group.classList.remove('error');
        }
    }

    function clearAllErrors() {
        document.querySelectorAll('.error-message').forEach(el => {
            el.textContent = '';
            el.classList.remove('show');
        });
        document.querySelectorAll('.error').forEach(el => {
            el.classList.remove('error');
        });
    }

    // 既存データ読み込み
    async function loadExistingData() {
        const today = getTodayJST();
        const yearMonth = today.substring(0, 7);

        console.log(`=== loadExistingData: Loading data for ${today} (JST) ===`);
        
        try {
            // 通所日報
            const morningUrl = `/api/me/reports/morning?year_month=${yearMonth}&start_date=${today}&end_date=${today}`;
            console.log(`Morning API URL: ${morningUrl}`);
            
            const morningRes = await fetch(morningUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            
            console.log(`Morning API response status: ${morningRes.status}`);
            
            if (morningRes.ok) {
                const morningResult = await morningRes.json();
                console.log('Morning API result:', JSON.stringify(morningResult, null, 2));
                
                if (morningResult.success && morningResult.data?.reports?.length > 0) {
                    console.log(`Morning reports found: ${morningResult.data.reports.length}`);
                    
                    // 日付の比較を修正（ISO形式 -> YYYY-MM-DD形式に変換）
                    const todayReport = morningResult.data.reports.find(r => {
                        const reportDate = r.report_date.split('T')[0]; // "2025-10-04T00:00:00.000000Z" -> "2025-10-04"
                        console.log(`Comparing: reportDate="${reportDate}" vs today="${today}"`);
                        return reportDate === today;
                    });
                    console.log('Today morning report:', todayReport);
                    
                    if (todayReport) {
                        existingMorningId = todayReport.id;
                        console.log(`Set existingMorningId to: ${existingMorningId}`);
                        populateMorningForm(todayReport.data);
                        updateReportStatus('morning', 'submitted');
                    } else {
                        console.log('No morning report found for today');
                        updateReportStatus('morning', 'pending');
                    }
                } else {
                    console.log('No morning reports in response or invalid format');
                    updateReportStatus('morning', 'pending');
                }
            } else {
                const errorText = await morningRes.text();
                console.error(`Morning API failed: ${morningRes.status} - ${errorText}`);
                updateReportStatus('morning', 'pending');
            }
            
            // 退所日報
            const eveningRes = await fetch(`/api/me/reports/evening?year_month=${yearMonth}&start_date=${today}&end_date=${today}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            
            if (eveningRes.ok) {
                const eveningResult = await eveningRes.json();
                
                if (eveningResult.success && eveningResult.data?.reports?.length > 0) {
                    // 日付の比較を修正（ISO形式 -> YYYY-MM-DD形式に変換）
                    const todayReport = eveningResult.data.reports.find(r => {
                        const reportDate = r.report_date.split('T')[0];
                        return reportDate === today;
                    });
                    if (todayReport) {
                        existingEveningId = todayReport.id;
                        populateEveningForm(todayReport.data);
                        updateReportStatus('evening', 'submitted');
                    } else {
                        updateReportStatus('evening', 'pending');
                    }
                } else {
                    updateReportStatus('evening', 'pending');
                }
            }
        } catch (error) {
            console.error('既存データの読み込みに失敗:', error);
            updateReportStatus('morning', 'pending');
            updateReportStatus('evening', 'pending');
        }
    }
    
    // 日報状態更新
    function updateReportStatus(type, status) {
        const statusElement = document.getElementById(`${type}Status`);
        const alertElement = document.getElementById(`${type}SubmittedAlert`);
        
        if (!statusElement) return;
        
        // 既存のクラスを削除
        statusElement.classList.remove('submitted', 'pending', 'checking');
        
        switch (status) {
            case 'submitted':
                statusElement.textContent = '提出済み';
                statusElement.classList.add('submitted');
                if (alertElement) {
                    alertElement.style.display = 'block';
                }
                break;
            case 'pending':
                statusElement.textContent = '未提出';
                statusElement.classList.add('pending');
                if (alertElement) {
                    alertElement.style.display = 'none';
                }
                break;
            case 'checking':
                statusElement.textContent = '確認中';
                statusElement.classList.add('checking');
                if (alertElement) {
                    alertElement.style.display = 'none';
                }
                break;
            default:
                statusElement.textContent = '不明';
                if (alertElement) {
                    alertElement.style.display = 'none';
                }
        }
    }

    // 朝のフォームにデータ入力
    function populateMorningForm(data) {
        if (data.sleep_rating) {
            const sleepBtn = document.querySelector(`.rating-button[data-name="sleep_rating"][data-value="${data.sleep_rating}"]`);
            if (sleepBtn) sleepBtn.classList.add('selected');
        }
        if (data.stress_rating) {
            const stressBtn = document.querySelector(`.rating-button[data-name="stress_rating"][data-value="${data.stress_rating}"]`);
            if (stressBtn) stressBtn.classList.add('selected');
        }
        if (data.meal_rating) {
            const mealBtn = document.querySelector(`.rating-button[data-name="meal_rating"][data-value="${data.meal_rating}"]`);
            if (mealBtn) mealBtn.classList.add('selected');
        }
        if (data.mood_score) {
            $('moodScore').value = data.mood_score;
            $('moodValue').textContent = data.mood_score;
            const percent = ((data.mood_score - 1) / 9) * 100;
            $('moodScore').style.background = `linear-gradient(to right, #16a34a ${percent}%, #e5e7eb ${percent}%)`;
        }
        if (data.bed_time_local) {
            // TIME型データ "HH:MM:SS" -> "HH:MM"
            $('bedTime').value = data.bed_time_local.substring(0, 5);
        }
        if (data.wake_time_local) {
            // TIME型データ "HH:MM:SS" -> "HH:MM"
            $('wakeTime').value = data.wake_time_local.substring(0, 5);
        }
        $('breakfastDone').checked = data.is_breakfast_done || false;
        $('bathingDone').checked = data.is_bathing_done || false;
        $('medicationTaken').checked = data.is_medication_taken || false;
        if (data.note) {
            $('morningNote').value = data.note;
        }
        
        calculateSleepDuration();
    }

    // 夕方のフォームにデータ入力
    function populateEveningForm(data) {
        if (data.training_summary) $('trainingSummary').value = data.training_summary;
        if (data.training_reflection) $('trainingReflection').value = data.training_reflection;
        if (data.condition_note) $('conditionNote').value = data.condition_note;
        if (data.other_note) $('otherNote').value = data.other_note;
    }

    // 睡眠時間計算
    function calculateSleepDuration() {
        const bedTime = $('bedTime').value;
        const wakeTime = $('wakeTime').value;
        
        if (bedTime && wakeTime) {
            const [bedHour, bedMin] = bedTime.split(':').map(Number);
            const [wakeHour, wakeMin] = wakeTime.split(':').map(Number);
            
            let bedMinutes = bedHour * 60 + bedMin;
            let wakeMinutes = wakeHour * 60 + wakeMin;
            
            if (wakeMinutes < bedMinutes) {
                wakeMinutes += 24 * 60;
            }
            
            const sleepMinutes = wakeMinutes - bedMinutes;
            const sleepHours = Math.floor(sleepMinutes / 60);
            const remainMinutes = sleepMinutes % 60;
            
            $('sleepDuration').textContent = `睡眠時間: ${sleepHours}時間${remainMinutes}分`;
        } else {
            $('sleepDuration').textContent = '睡眠時間: --';
        }
    }

    // 日報保存
    async function saveReport(type) {
        clearAllErrors();

        const today = getTodayJST();
        let data = { report_date: today };
        let existingId = null;
        let hasError = false;
        
        if (type === 'morning') {
            const sleepRating = getSelectedRating('sleep_rating');
            const stressRating = getSelectedRating('stress_rating');
            const mealRating = getSelectedRating('meal_rating');
            const bedTime = $('bedTime').value;
            const wakeTime = $('wakeTime').value;
            
            // バリデーション
            if (!sleepRating) {
                showError('sleepRating', '睡眠の評価を選択してください');
                hasError = true;
            }
            if (!stressRating) {
                showError('stressRating', 'ストレスの評価を選択してください');
                hasError = true;
            }
            if (!mealRating) {
                showError('mealRating', '食事の評価を選択してください');
                hasError = true;
            }
            if (!bedTime) {
                showError('bedTime', '就寝時刻を入力してください');
                hasError = true;
            }
            if (!wakeTime) {
                showError('wakeTime', '起床時刻を入力してください');
                hasError = true;
            }
            
            if (hasError) {
                return;
            }

            // noteフィールドは空の場合は送信しない
            const noteValue = $('morningNote').value;

            data = {
                report_date: today,
                sleep_rating: sleepRating,
                stress_rating: stressRating,
                meal_rating: mealRating,
                mood_score: parseInt($('moodScore').value),
                bed_time_local: bedTime,
                wake_time_local: wakeTime,
                is_breakfast_done: $('breakfastDone').checked,
                is_bathing_done: $('bathingDone').checked,
                is_medication_taken: $('medicationTaken').checked
            };
            
            // noteは値がある場合のみ追加
            if (noteValue && noteValue.trim() !== '') {
                data.note = noteValue.trim();
            }
            
            existingId = existingMorningId;
            
        } else if (type === 'evening') {
            // 各フィールドの値を取得
            const trainingSummary = $('trainingSummary').value;
            const trainingReflection = $('trainingReflection').value;
            const conditionNote = $('conditionNote').value;
            const otherNote = $('otherNote').value;
            
            // 必須フィールドのバリデーション（最初の3つ）
            if (!trainingSummary || trainingSummary.trim() === '') {
                showError('trainingSummary', '訓練内容を入力してください');
                hasError = true;
            }
            if (!trainingReflection || trainingReflection.trim() === '') {
                showError('trainingReflection', '訓練の振り返りを入力してください');
                hasError = true;
            }
            if (!conditionNote || conditionNote.trim() === '') {
                showError('conditionNote', '体調について入力してください');
                hasError = true;
            }
            
            if (hasError) {
                return;
            }
            
            data = {
                report_date: today,
                training_summary: trainingSummary.trim(),
                training_reflection: trainingReflection.trim(),
                condition_note: conditionNote.trim()
            };
            
            // その他は任意なので、値がある場合のみ追加
            if (otherNote && otherNote.trim() !== '') {
                data.other_note = otherNote.trim();
            }
            
            existingId = existingEveningId;
        }
        
        try {
            let url = `/api/me/reports/${type}`;
            let method = 'POST';
            
            console.log(`=== saveReport(${type}) ===`);
            console.log(`existingId: ${existingId}`);
            console.log(`existingMorningId: ${existingMorningId}`);
            console.log(`existingEveningId: ${existingEveningId}`);
            
            if (existingId) {
                url = `/api/me/reports/${type}/${existingId}`;
                method = 'PUT';
                console.log(`Using UPDATE mode: ${method} ${url}`);
            } else {
                console.log(`Using CREATE mode: ${method} ${url}`);
            }
            
            console.log('Data being sent:', JSON.stringify(data, null, 2));
            
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();

            if (response.ok && result.success) {
                // 成功時の処理
                if (typeof showNotification === 'function') {
                    showNotification(`${type === 'morning' ? '通所日報' : '退所日報'}を保存しました`, 'success');
                } else {
                    alert(`✓ ${type === 'morning' ? '通所日報' : '退所日報'}を保存しました`);
                }
                
                // IDを保存
                if (result.data?.id) {
                    if (type === 'morning') {
                        existingMorningId = result.data.id;
                    } else {
                        existingEveningId = result.data.id;
                    }
                }
                
                // 状態を「提出済み」に更新
                updateReportStatus(type, 'submitted');
            } else {
                // エラー処理
                if (result.errors) {
                    const fieldIdMap = {
                        'sleep_rating': 'sleepRating',
                        'stress_rating': 'stressRating',
                        'meal_rating': 'mealRating',
                        'mood_score': 'moodScore',
                        'bed_time_local': 'bedTime',
                        'wake_time_local': 'wakeTime',
                        'note': 'morningNote',
                        'training_summary': 'trainingSummary',
                        'training_reflection': 'trainingReflection',
                        'condition_note': 'conditionNote',
                        'other_note': 'otherNote'
                    };
                    
                    for (const [field, messages] of Object.entries(result.errors)) {
                        const fieldId = fieldIdMap[field] || field;
                        const message = Array.isArray(messages) ? messages[0] : messages;
                        showError(fieldId, message);
                    }
                } else if (result.message) {
                    if (typeof showNotification === 'function') {
                        showNotification('エラー: ' + result.message, 'error');
                    } else {
                        alert('エラー: ' + result.message);
                    }
                }
            }
            
        } catch (error) {
            console.error('保存エラー:', error);
            if (typeof showNotification === 'function') {
                showNotification('保存中にエラーが発生しました', 'error');
            } else {
                alert('保存中にエラーが発生しました。');
            }
        }
    }

    // フォームクリア
    window.clearForm = function(type) {
        if (!confirm('入力内容をクリアしますか？')) return;
        
        if (type === 'morning') {
            $('morningForm').reset();
            $('moodValue').textContent = '5';
            $('sleepDuration').textContent = '睡眠時間: --';
            document.querySelectorAll('.rating-button').forEach(btn => {
                btn.classList.remove('selected');
            });
            const initialPercent = 44;
            $('moodScore').style.background = `linear-gradient(to right, #16a34a ${initialPercent}%, #e5e7eb ${initialPercent}%)`;
        } else {
            $('eveningForm').reset();
        }
        
        clearAllErrors();
    };

    // 評価ボタンの選択値取得
    function getSelectedRating(name) {
        const selected = document.querySelector(`.rating-button[data-name="${name}"].selected`);
        return selected ? parseInt(selected.dataset.value) : null;
    }
})();
</script>
@endsection