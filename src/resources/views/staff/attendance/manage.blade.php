{{-- resources/views/staff/attendance/manage.blade.php --}}
@extends('layouts.staff')

@section('title', 'S-06S 出席管理')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/attendance.css') }}">
@endsection

@section('content')
<!-- バージョン: v1.3.<?php echo time(); ?> - sticky列透け防止 + 当日ハイライト黄色(important追加) -->
<section class="toolbar">
    <select id="monthSelect" aria-label="月選択"></select>
    <div style="flex:1"></div>
    <button class="btn" onclick="exportCSV()">CSV出力</button>
</section>

<section class="stats">
    <div class="stat-card">
        <div class="label">計画コマ数</div>
        <div class="value" id="statPlanned">-</div>
    </div>
    <div class="stat-card">
        <div class="label">実績コマ数</div>
        <div class="value" id="statActual">-</div>
    </div>
    <div class="stat-card">
        <div class="label">達成率</div>
        <div class="value" id="statRate">-%</div>
    </div>
    <div class="stat-card">
        <div class="label">未入力件数</div>
        <div class="value" id="statMissing">-</div>
    </div>
</section>

<section class="table-container">
    <table class="attendance-table" id="attendanceTable">
        <thead>
            <tr id="tableHeader">
                <th class="name-col">利用者</th>
            </tr>
        </thead>
        <tbody id="tableBody">
            <tr><td colspan="32" class="loading">読み込み中...</td></tr>
        </tbody>
    </table>
</section>

<!-- 編集モーダル -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">出席情報編集</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>利用者</label>
                <input type="text" id="editUserName" readonly>
            </div>
            <div class="form-group">
                <label>日付</label>
                <input type="text" id="editDate" readonly>
            </div>
            <div class="form-group">
                <label>予定</label>
                <select id="editPlanType">
                    <option value="">なし</option>
                    <option value="onsite">通所</option>
                    <option value="remote">在宅</option>
                </select>
            </div>
            <div class="form-group">
                <label>時間帯</label>
                <select id="editPlanSlot">
                    <option value="full">終日</option>
                    <option value="am">午前</option>
                    <option value="pm">午後</option>
                </select>
            </div>
            <div class="form-group">
                <label>実績</label>
                <select id="editRecordType">
                    <option value="">なし</option>
                    <option value="onsite">通所</option>
                    <option value="remote">在宅</option>
                </select>
            </div>
            <div class="form-group">
                <label>備考</label>
                <textarea id="editNote" rows="3"></textarea>
            </div>
        </div>
        <div class="modal-actions">
            <button class="btn" onclick="closeModal()">キャンセル</button>
            <button class="btn danger" id="btnDelete" onclick="deleteData()" style="display:none">削除</button>
            <button class="btn primary" onclick="saveData()">保存</button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function(){
const $=id=>document.getElementById(id);
let currentMonth = '';
let attendanceData = {};
let editingCell = null;

// 月選択ドロップダウン初期化
const monthSelect = $('monthSelect');
const today = new Date();
for(let i=-6; i<=6; i++){
    const d = new Date(today.getFullYear(), today.getMonth()+i, 1);
    const ym = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;
    const opt = document.createElement('option');
    opt.value = ym;
    opt.textContent = `${d.getFullYear()}年${d.getMonth()+1}月`;
    if(i===0) opt.selected = true;
    monthSelect.appendChild(opt);
}

monthSelect.addEventListener('change', ()=> loadData());

async function loadData(){
    currentMonth = monthSelect.value;
    const [y,m] = currentMonth.split('-').map(Number);
    const daysInMonth = new Date(y, m, 0).getDate();

    // 日本時間（JST）で今日の日付を取得
    const now = new Date();
    const todayJST = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Tokyo' }));
    const todayStr = `${todayJST.getFullYear()}-${String(todayJST.getMonth()+1).padStart(2,'0')}-${String(todayJST.getDate()).padStart(2,'0')}`;

    console.log('=== loadData 開始 ===');
    console.log('本日(JST):', todayStr);
    console.log('表示月:', currentMonth);

    // 祝日取得
    let holidays = [];
    try{
        const holidayRes = await fetch(`http://localhost:8080/api/holidays?year=${y}&month=${m}`, {
            headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
        });
        if(holidayRes.ok){
            const holidayData = await holidayRes.json();
            // データが配列の場合とオブジェクトの場合の両方に対応
            if(Array.isArray(holidayData)){
                holidays = holidayData.map(h => h.date);
            }else if(holidayData.data && Array.isArray(holidayData.data)){
                holidays = holidayData.data.map(h => h.date);
            }
        }
    }catch(e){
        console.warn('祝日データの取得に失敗:', e);
    }

    // ヘッダー作成（土日を除く）
    const header = $('tableHeader');
    let headerHtml = '<th class="name-col">利用者</th>';
    const displayDates = [];
    console.log('ヘッダー作成開始。本日:', todayStr);

    for(let d=1; d<=daysInMonth; d++){
        const date = new Date(y, m-1, d);
        const dow = date.getDay();
        const dateStr = `${y}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const isWeekend = dow===0 || dow===6;
        const isHoliday = holidays.includes(dateStr);

        // 土日は祝日でなければスキップ
        if(isWeekend && !isHoliday) continue;

        displayDates.push({day: d, dow, dateStr, isHoliday});

        const dayNames = ['日','月','火','水','木','金','土'];
        const dayClass = dow===0?'sun':dow===6?'sat':'';
        const holidayMark = isHoliday ? '<br><span style="font-size:9px;color:#dc2626;">祝</span>' : '';
        const isToday = dateStr === todayStr;
        const todayClass = isToday ? ' today' : '';
        if(isToday) console.log(`✓ ヘッダー本日検出: ${dateStr}, class="${todayClass}"`);

        headerHtml += `<th class="date-col${todayClass}">
            <div class="date-header">
                <span class="date">${d}</span>
                <span class="day ${dayClass}">${dayNames[dow]}</span>${holidayMark}
            </div>
        </th>`;
    }
    header.innerHTML = headerHtml;

    // データ取得
    try{
        const res = await fetch(`http://localhost:8080/staff/attendance/monthly-overview?month=${currentMonth}`, {
            headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
        });
        if(!res.ok) throw new Error('データ取得失敗');
        const data = await res.json();

        console.log('取得データ:', data); // デバッグ用

        attendanceData = data;
        renderTable(data, displayDates, y, m);
        updateStats(data);
    }catch(e){
        console.error(e);
        $('tableBody').innerHTML = '<tr><td colspan="32" class="loading">データの読み込みに失敗しました</td></tr>';
        showNotification('データの読み込みに失敗しました', 'error');
    }
}

function renderTable(data, displayDates, year, month){
    const tbody = $('tableBody');
    let html = '';

    // 日本時間（JST）で今日の日付を取得
    const now = new Date();
    const todayJST = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Tokyo' }));
    const todayStr = `${todayJST.getFullYear()}-${String(todayJST.getMonth()+1).padStart(2,'0')}-${String(todayJST.getDate()).padStart(2,'0')}`;

    console.log('renderTable開始。本日(JST):', todayStr);

    if(!data.users || data.users.length === 0){
        html = `<tr><td colspan="${displayDates.length + 1}" class="loading">利用者データがありません</td></tr>`;
    }else{
        data.users.forEach(user => {
            html += `<tr><td class="name-col">${escapeHtml(user.name)}</td>`;

            displayDates.forEach(dateInfo => {
                const dateStr = dateInfo.dateStr;
                const isHoliday = dateInfo.isHoliday;
                const isToday = dateStr === todayStr;

                const plan = user.plans.find(p => p.plan_date === dateStr);
                const record = user.records.find(r => r.record_date === dateStr);

                // 予定の表示
                let planText = '-';
                if(plan && plan.plan_type === 'off'){
                    planText = '休';
                }else if(plan){
                    const typeLabel = plan.plan_type === 'onsite' ? '通' : '在';
                    const slotLabel = plan.plan_time_slot === 'full' ? '終' : plan.plan_time_slot === 'am' ? '前' : '後';
                    planText = `${typeLabel}・${slotLabel}`;
                }

                // 実績の表示
                let recordText = '-';
                if(record && record.attendance_type === 'absent'){
                    recordText = '欠';
                }else if(record){
                    const typeLabel = record.attendance_type === 'onsite' ? '通' : '在';
                    recordText = typeLabel;
                }

                const planHtml = plan
                    ? `<div class="cell-row plan ${plan.plan_type==='remote'?'remote':plan.plan_type==='off'?'off':''}">${planText}</div>`
                    : '<div class="cell-row empty">-</div>';

                const recordHtml = record
                    ? `<div class="cell-row record ${record.attendance_type==='remote'?'remote':record.attendance_type==='absent'?'absent':''}">${recordText}</div>`
                    : '<div class="cell-row empty">-</div>';

                const cellClass = (isHoliday ? 'holiday' : '') + (isToday ? ' today' : '');
                if(isToday && user.id === data.users[0].id) console.log(`✓ セル本日検出: ${dateStr}, class="${cellClass}"`);

                html += `<td class="attendance-cell ${cellClass}" onclick="openEditModal(${user.id},'${escapeHtml(user.name)}','${dateStr}')">
                    <div class="cell-content">${planHtml}${recordHtml}</div>
                </td>`;
            });

            html += '</tr>';
        });
    }

    tbody.innerHTML = html;
}

function updateStats(data){
    let plannedCount = 0;
    let actualCount = 0;
    let missingCount = 0;

    if(data.users){
        data.users.forEach(user => {
            plannedCount += user.plans.length;
            // 実績コマ数は通所（onsite）と在宅（remote）のみカウント（欠席は除外）
            actualCount += user.records.filter(r => r.attendance_type === 'onsite' || r.attendance_type === 'remote').length;
            missingCount += user.plans.filter(p => {
                return !user.records.find(r => r.record_date === p.plan_date);
            }).length;
        });
    }

    $('statPlanned').textContent = plannedCount;
    $('statActual').textContent = actualCount;
    $('statRate').textContent = plannedCount > 0 ? Math.round((actualCount/plannedCount)*100) + '%' : '-%';
    $('statMissing').textContent = missingCount;
}

window.openEditModal = function(userId, userName, dateStr){
    const user = attendanceData.users.find(u => u.id === userId);
    const plan = user?.plans.find(p => p.plan_date === dateStr);
    const record = user?.records.find(r => r.record_date === dateStr);

    editingCell = {
        userId,
        dateStr,
        planId: plan?.id || null,
        planTimeSlot: plan?.plan_time_slot || null,
        recordId: record?.id || null,
        recordTimeSlot: record?.record_time_slot || null
    };

    console.log('モーダルオープン:', { userId, dateStr, plan, record, editingCell });

    $('modalTitle').textContent = '出席情報編集';
    $('editUserName').value = userName;
    $('editDate').value = dateStr;
    $('editPlanType').value = plan?.plan_type || '';
    $('editPlanSlot').value = plan?.plan_time_slot || 'full';
    $('editRecordType').value = record?.attendance_type || '';
    $('editNote').value = record?.note || '';

    $('btnDelete').style.display = (plan || record) ? 'block' : 'none';
    $('editModal').classList.add('active');
};

window.closeModal = function(){
    $('editModal').classList.remove('active');
    editingCell = null;
};

window.saveData = async function(){
    if(!editingCell) return;

    const planType = $('editPlanType').value;
    const planSlot = $('editPlanSlot').value;
    const recordType = $('editRecordType').value;
    const note = $('editNote').value;

    try{
        // 予定の保存
        if(planType){
            await fetch('http://localhost:8080/api/plans', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    user_id: editingCell.userId,
                    month: currentMonth,
                    mode: 'merge',
                    items: [{
                        plan_date: editingCell.dateStr,
                        plan_type: planType,
                        plan_time_slot: planSlot,
                        note: null
                    }]
                })
            });
        }

        // 実績の保存（既存レコードがあれば更新、なければ新規作成）
        if(recordType){
            const recordId = editingCell.recordId;
            const url = recordId
                ? `http://localhost:8080/api/attendance/records/${recordId}`
                : 'http://localhost:8080/api/attendance/records';
            const method = recordId ? 'PATCH' : 'POST';

            const body = {
                attendance_type: recordType,
                note: note
            };

            // 新規作成時のみ必須パラメータを追加
            if(!recordId){
                body.user_id = editingCell.userId;
                body.record_date = editingCell.dateStr;
                body.record_time_slot = planSlot || 'full';
            }

            console.log('実績保存リクエスト:', { recordId, url, method, body });

            const res = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(body)
            });

            console.log('実績保存レスポンス:', { status: res.status, statusText: res.statusText });

            if(!res.ok){
                let errorMessage = '実績の保存に失敗しました';
                try {
                    const error = await res.json();
                    console.error('実績保存エラー (JSON):', error);
                    errorMessage = error.message || errorMessage;
                } catch(e) {
                    const errorText = await res.text();
                    console.error('実績保存エラー (Text):', errorText);
                }
                throw new Error(errorMessage);
            }
        }

        closeModal();
        await loadData();
        showNotification('保存しました', 'success');
    }catch(e){
        console.error(e);
        showNotification('保存に失敗しました', 'error');
    }
};

window.deleteData = async function(){
    if(!editingCell || !confirm('削除しますか？')) return;

    try{
        const user = attendanceData.users.find(u => u.id === editingCell.userId);
        const plan = user?.plans.find(p => p.plan_date === editingCell.dateStr);
        const record = user?.records.find(r => r.record_date === editingCell.dateStr);

        if(plan){
            await fetch(`http://localhost:8080/api/plans/${plan.id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
        }

        if(record){
            await fetch(`http://localhost:8080/api/attendance/records/${record.id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
        }

        closeModal();
        await loadData();
        showNotification('削除しました', 'success');
    }catch(e){
        console.error(e);
        showNotification('削除に失敗しました', 'error');
    }
};

window.exportCSV = function(){
    showNotification('CSV出力機能は実装予定です', 'info');
};

function escapeHtml(str){
    return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// 初期ロード
loadData();
})();
</script>
@endsection
