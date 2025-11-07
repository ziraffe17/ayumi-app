@extends('layouts.staff')
@section('title', 'S-07S 日報確認')
@section('content')
<style>
.toolbar{display:flex;gap:12px;align-items:center;margin-bottom:24px;flex-wrap:wrap;background:white;padding:16px;border-radius:12px;border:1px solid #e5e7eb}
.toolbar select,.toolbar input{padding:10px 14px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px}
.search-box{flex:1;min-width:200px;max-width:300px}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px}
.stat-card{background:white;border:1px solid #e5e7eb;border-radius:12px;padding:20px;text-align:center}
.stat-card .label{color:#6b7280;font-size:13px;margin-bottom:8px}
.stat-card .value{font-size:28px;font-weight:700;color:#16a34a}
.report-list{background:white;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}
.report-item{border-bottom:1px solid #e5e7eb;padding:20px 24px;cursor:pointer;transition:background .2s}
.report-item:hover{background:#f9fafb}
.report-item.expanded{background:#f0fdf4}
.report-header{display:flex;justify-content:space-between;align-items:center}
.report-user{display:flex;align-items:center;gap:12px;flex:1}
.user-avatar{width:40px;height:40px;background:#86efac;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#16a34a;font-weight:700}
.user-info h4{margin:0;font-size:16px;font-weight:600}
.user-info .date{font-size:13px;color:#6b7280;margin-top:2px}
.status-badge{padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600}
.status-badge.complete{background:#dcfce7;color:#166534}
.status-badge.partial{background:#fef3c7;color:#92400e}
.status-badge.none{background:#fee2e2;color:#dc2626}
.expand-icon{color:#6b7280;font-size:20px;transition:transform .2s}
.report-item.expanded .expand-icon{transform:rotate(180deg)}
.report-detail{display:none;margin-top:20px;padding-top:20px;border-top:1px solid #e5e7eb}
.report-item.expanded .report-detail{display:block}
.detail-tabs{display:flex;gap:8px;margin-bottom:20px;border-bottom:1px solid #e5e7eb}
.detail-tab{padding:10px 20px;border:none;background:none;cursor:pointer;border-bottom:2px solid transparent}
.detail-tab.active{color:#16a34a;border-bottom-color:#16a34a}
.detail-content{display:none}
.detail-content.active{display:block}
.detail-section{margin-bottom:24px}
.detail-section h5{font-size:14px;font-weight:600;margin:0 0 12px 0}
.detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px}
.detail-item{background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px}
.detail-item .label{font-size:12px;color:#6b7280;margin-bottom:4px}
.detail-item .value{font-size:16px;font-weight:600}
.rating{font-size:20px;font-weight:700}
.rating.good{color:#16a34a}
.rating.fair{color:#f59e0b}
.rating.poor{color:#dc2626}
.detail-text{background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:16px;font-size:14px;white-space:pre-wrap}
.detail-text.empty{color:#6b7280;font-style:italic}
.loading{text-align:center;padding:40px;color:#6b7280}
.empty-state{text-align:center;padding:60px 20px;color:#6b7280}
</style>
<section class="toolbar">
    <select id="filterStatus">
        <option value="">すべて</option>
        <option value="complete">完了</option>
        <option value="partial">部分入力</option>
        <option value="none">未入力</option>
    </select>
    <input type="date" id="filterDate">
    <input type="text" class="search-box" id="searchUser" placeholder="利用者名で検索...">
    <button class="btn green" onclick="loadReports()">表示</button>
    <button class="btn" onclick="exportCSV()">CSV出力</button>
</section>
<section class="stats-grid">
    <div class="stat-card">
        <div class="label">完了</div>
        <div class="value" id="statComplete">0</div>
    </div>
    <div class="stat-card">
        <div class="label">部分入力</div>
        <div class="value" id="statPartial">0</div>
    </div>
    <div class="stat-card">
        <div class="label">未入力</div>
        <div class="value" id="statNone">0</div>
    </div>
    <div class="stat-card">
        <div class="label">入力率</div>
        <div class="value" id="statRate">0%</div>
    </div>
</section>
<section class="report-list" id="reportList">
    <div class="loading">読み込み中...</div>
</section>
@endsection
@section('scripts')
<script>
(function(){
const $=id=>document.getElementById(id);
let allReports=[];

$('filterDate').value=new Date().toISOString().split('T')[0];

window.loadReports=async function(){
    const date=$('filterDate').value;
    if(!date){showNotification('日付を選択してください','warning');return}
    
    try{
        $('reportList').innerHTML='<div class="loading">読み込み中...</div>';
        const res=await fetch(`/api/reports/list?start_date=${date}&end_date=${date}`,{
            headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
        });
        
        if(!res.ok)throw new Error('データ取得失敗');
        const data=await res.json();
        
        if(data.success){
            allReports=processReports(data.data.reports||[]);
            renderReports();
        }else{
            throw new Error(data.message||'エラー');
        }
    }catch(e){
        console.error(e);
        $('reportList').innerHTML='<div class="empty-state"><h3>エラーが発生しました</h3><p>'+e.message+'</p></div>';
        showNotification('データの読み込みに失敗しました','error');
    }
};

function processReports(reports){
    const userMap={};
    reports.forEach(r=>{
        const key=`${r.user_id}_${r.report_date}`;
        if(!userMap[key]){
            userMap[key]={
                user_id:r.user_id,
                user_name:r.user_name,
                date:r.report_date,
                morning:null,
                evening:null
            };
        }
        if(r.type==='morning')userMap[key].morning=r;
        if(r.type==='evening')userMap[key].evening=r;
    });
    return Object.values(userMap);
}

function renderReports(){
    const filter=$('filterStatus').value;
    const search=$('searchUser').value.toLowerCase();
    
    let filtered=allReports.filter(u=>{
        if(search&&!u.user_name.toLowerCase().includes(search))return false;
        if(filter){
            const status=getStatus(u);
            if(status!==filter)return false;
        }
        return true;
    });
    
    if(filtered.length===0){
        $('reportList').innerHTML='<div class="empty-state"><h3>該当する日報がありません</h3></div>';
        updateStats([]);
        return;
    }
    
    $('reportList').innerHTML=filtered.map(u=>{
        const status=getStatus(u);
        const label=status==='complete'?'完了':status==='partial'?'部分入力':'未入力';
        return`<div class="report-item" data-user-id="${u.user_id}">
            <div class="report-header" onclick="toggleReport(${u.user_id})">
                <div class="report-user">
                    <div class="user-avatar">${u.user_name.charAt(0)}</div>
                    <div class="user-info">
                        <h4>${u.user_name}</h4>
                        <div class="date">${u.date}</div>
                    </div>
                </div>
                <div class="report-status">
                    <span class="status-badge ${status}">${label}</span>
                    <span class="expand-icon">▼</span>
                </div>
            </div>
            <div class="report-detail">
                <div class="detail-tabs">
                    <button class="detail-tab active" onclick="switchTab(event,${u.user_id},'morning')">通所日報</button>
                    <button class="detail-tab" onclick="switchTab(event,${u.user_id},'evening')">退所日報</button>
                </div>
                <div class="detail-content active" id="detail-${u.user_id}-morning">${renderMorning(u.morning)}</div>
                <div class="detail-content" id="detail-${u.user_id}-evening">${renderEvening(u.evening)}</div>
            </div>
        </div>`;
    }).join('');
    
    updateStats(filtered);
}

function getStatus(u){
    if(u.morning&&u.evening)return'complete';
    if(u.morning||u.evening)return'partial';
    return'none';
}

function renderMorning(m){
    if(!m)return'<div class="detail-text empty">通所日報が未入力です</div>';
    const d=m.data;
    const rMap={3:'◯',2:'△',1:'✕'};
    const rClass={3:'good',2:'fair',1:'poor'};
    return`<div class="detail-section"><h5>基本評価</h5><div class="detail-grid">
        <div class="detail-item"><div class="label">睡眠</div><div class="value rating ${rClass[d.sleep_rating]}">${rMap[d.sleep_rating]}</div></div>
        <div class="detail-item"><div class="label">ストレス</div><div class="value rating ${rClass[d.stress_rating]}">${rMap[d.stress_rating]}</div></div>
        <div class="detail-item"><div class="label">食事</div><div class="value rating ${rClass[d.meal_rating]}">${rMap[d.meal_rating]}</div></div>
        <div class="detail-item"><div class="label">気分</div><div class="value">${d.mood_score}/10</div></div>
    </div></div>
    <div class="detail-section"><h5>睡眠</h5><div class="detail-grid">
        <div class="detail-item"><div class="label">就寝</div><div class="value">${d.bed_time_local||'-'}</div></div>
        <div class="detail-item"><div class="label">起床</div><div class="value">${d.wake_time_local||'-'}</div></div>
        <div class="detail-item"><div class="label">睡眠時間</div><div class="value">${d.sleep_hours_display||'-'}</div></div>
    </div></div>
    ${d.note?`<div class="detail-section"><h5>相談・連絡</h5><div class="detail-text">${d.note}</div></div>`:''}`;
}

function renderEvening(e){
    if(!e)return'<div class="detail-text empty">退所日報が未入力です</div>';
    const d=e.data;
    return`${d.training_summary?`<div class="detail-section"><h5>訓練内容</h5><div class="detail-text">${d.training_summary}</div></div>`:''}
    ${d.training_reflection?`<div class="detail-section"><h5>訓練の振り返り</h5><div class="detail-text">${d.training_reflection}</div></div>`:''}
    ${d.condition_note?`<div class="detail-section"><h5>体調</h5><div class="detail-text">${d.condition_note}</div></div>`:''}
    ${d.other_note?`<div class="detail-section"><h5>その他</h5><div class="detail-text">${d.other_note}</div></div>`:''}`;
}

function updateStats(filtered){
    let complete=0,partial=0,none=0;
    filtered.forEach(u=>{
        const s=getStatus(u);
        if(s==='complete')complete++;
        else if(s==='partial')partial++;
        else none++;
    });
    $('statComplete').textContent=complete;
    $('statPartial').textContent=partial;
    $('statNone').textContent=none;
    $('statRate').textContent=filtered.length>0?Math.round((complete/filtered.length)*100)+'%':'0%';
}

window.toggleReport=function(userId){
    document.querySelector(`.report-item[data-user-id="${userId}"]`).classList.toggle('expanded');
};

window.switchTab=function(e,userId,type){
    e.stopPropagation();
    const item=document.querySelector(`.report-item[data-user-id="${userId}"]`);
    item.querySelectorAll('.detail-tab').forEach(t=>t.classList.remove('active'));
    item.querySelectorAll('.detail-content').forEach(c=>c.classList.remove('active'));
    e.target.classList.add('active');
    item.querySelector(`#detail-${userId}-${type}`).classList.add('active');
};

window.exportCSV=function(){
    showNotification('CSV出力機能は実装予定です','info');
};

$('filterStatus').addEventListener('change',renderReports);
$('searchUser').addEventListener('input',renderReports);

loadReports();
})();
</script>
@endsection