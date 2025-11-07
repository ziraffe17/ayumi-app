{{-- resources/views/staff/plans/monthly.blade.php --}}
@extends('layouts.staff')

@section('title', 'S-05S 月次出席予定（職員用）')

@section('styles')
<style>
    /* カレンダー */
    .week{display:grid;grid-template-columns:repeat(7,1fr);text-align:center;font-weight:700;margin:12px 0 6px}
    .week div:first-child{color:#dc2626}
    .week div:last-child{color:#2563eb}
    .grid{display:grid;grid-template-columns:repeat(7,1fr);gap:6px}
    .cell{min-height:90px;background:var(--card);border:1px solid var(--line);border-radius:8px;padding:6px;position:relative;font-size:12px}
    .cell.muted{opacity:.45}
    .cell.weekend{background:#f3f4f6}
    .cell .day{font-weight:700;margin-bottom:4px}
    .cell.sunday .day{color:#dc2626}
    .cell.saturday .day{color:#2563eb}
    .plan{border:1px solid var(--line);border-radius:6px;padding:2px 6px;margin-top:4px;display:flex;justify-content:space-between;align-items:center}
    .add{position:absolute;right:6px;bottom:6px;font-size:11px;background:#16a34a;color:#fff;border:1px solid #16a34a;padding:2px 8px;border-radius:4px;cursor:pointer;font-weight:700;box-shadow:0 1px 2px rgba(0,0,0,.1)}
    .add:hover{background:#15803d;border-color:#15803d}

    /* サマリーカード */
    .cards{display:flex;gap:12px;margin-top:12px}
    .card{flex:1;background:#fff;border:1px solid var(--line);border-radius:8px;padding:10px;text-align:center}

    /* エディタ */
    .editor{margin-top:16px;background:#fff;border:1px solid var(--line);border-radius:8px;padding:12px}
    .row{display:grid;grid-template-columns:120px 1fr;gap:10px;margin:8px 0}
    .actions{display:flex;gap:8px;justify-content:flex-end}
    .btn.green{background:#16a34a;border-color:#16a34a;color:#fff}
    .btn.red{background:#dc2626;border-color:#dc2626;color:#fff}

    /* スケルトン */
    .skel{animation:pulse 1.2s ease-in-out infinite;background:#e5e7eb;}
    @keyframes pulse{0%{opacity:.6} 50%{opacity:1} 100%{opacity:.6}}
</style>
@endsection

@section('content')
<section class="toolbar" style="display:flex;gap:8px;align-items:center;margin:8px 0">
    <select id="userSelect" aria-label="利用者選択">
        <option value="">利用者選択</option>
        @foreach($users ?? [] as $user)
        <option value="{{ $user->id }}">{{ $user->name }}</option>
        @endforeach
    </select>
    <select id="ymSelect" aria-label="年月選択"></select>
    <button class="btn" id="wkBulk" disabled>平日一括入力</button>
    <button class="btn" id="copyPrev" disabled>前月コピー</button>
</section>

<section>
    <div class="week" id="weekHead"></div>
    <div class="grid" id="calGrid" aria-live="polite"></div>
</section>

<section class="cards">
    <div class="card">出席予定数<br><div id="plannedCount" style="font-size:24px;font-weight:700">0</div></div>
    <div class="card">今月出席数<br><div id="actualCount" style="font-size:24px;font-weight:700">0</div></div>
</section>

<section class="editor" id="editor" hidden>
    <h3 id="editorTitle">新規予定</h3>
    <div class="row"><label>日付</label><input type="text" id="edDate" readonly></div>
    <div class="row"><label>通所形態</label>
        <select id="edType">
            <option value="onsite">通所</option>
            <option value="remote">在宅</option>
        </select>
    </div>
    <div class="row"><label>時間</label>
        <select id="edSlot">
            <option value="full">終日</option>
            <option value="am">午前</option>
            <option value="pm">午後</option>
        </select>
    </div>
    <div class="row"><label>備考</label><textarea id="edRemarks" rows="3"></textarea></div>
    <div class="actions">
        <button class="btn" id="btnCancel">キャンセル</button>
        <button class="btn green" id="btnSave">保存</button>
        <button class="btn red" id="btnDelete" hidden>削除</button>
    </div>
</section>
@endsection

@section('scripts')
<script>
(function(){
    const $ = (id)=>document.getElementById(id);
    
    function esc(s){
        return String(s ?? '')
            .replaceAll('&','&amp;')
            .replaceAll('<','&lt;')
            .replaceAll('>','&gt;')
            .replaceAll('"','&quot;')
            .replaceAll("'",'&#39;');
    }
    
    function toLocalYmd(d){
        const y=d.getFullYear();
        const m=String(d.getMonth()+1).padStart(2,'0');
        const day=String(d.getDate()).padStart(2,'0');
        return `${y}-${m}-${day}`;
    }
    
    const debounce = (fn, ms=180) => { let t; return (...args)=>{ clearTimeout(t); t=setTimeout(()=>fn(...args), ms); }; };
    let fetchCtrl = null;
    
    async function api(path, opts={}){
        const token = document.querySelector('meta[name="csrf-token"]').content;
        const headers = {'Content-Type':'application/json','X-CSRF-TOKEN':token, ...(opts.headers||{})};
        const res = await fetch(path, { ...opts, headers, credentials:'same-origin' });
        if(!res.ok){
            let msg = res.statusText;
            try { msg = await res.text(); } catch {}
            throw new Error(`エラー (${res.status}): ${msg}`);
        }
        return res.status === 204 ? null : res.json();
    }
    
    const weekHead=$('weekHead');
    const grid=$('calGrid');
    const plannedCountEl=$('plannedCount');
    
    function getUid(){ return $('userSelect').value || ''; }
    function setActionsDisabled(flag){ $('wkBulk').disabled = flag; $('copyPrev').disabled = flag; }
    
    // 曜日ヘッダー
    const weekNames=['日','月','火','水','木','金','土'];
    weekHead.innerHTML = weekNames.map(n=>`<div>${n}</div>`).join('');
    
    // 年月プルダウン
    const ymSelect=$('ymSelect');
    const today=new Date();
    for(let i=-6;i<=6;i++){
        const d=new Date(today.getFullYear(), today.getMonth()+i, 1);
        const ym=`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;
        const opt=document.createElement('option'); opt.value=ym; opt.textContent=`${d.getFullYear()}年${d.getMonth()+1}月`;
        if(i===0) opt.selected=true; ymSelect.appendChild(opt);
    }
    
    const editor = {
        wrap:$('editor'), title:$('editorTitle'), date:$('edDate'),
        type:$('edType'), slot:$('edSlot'), remarks:$('edRemarks'),
        btnSave:$('btnSave'), btnDelete:$('btnDelete'), btnCancel:$('btnCancel')
    };
    let editing = { id:null, iso:null };
    
    async function buildGrid(ym){
        if (fetchCtrl) fetchCtrl.abort();
        fetchCtrl = new AbortController();
        
        grid.innerHTML='';
        for(let i=0;i<42;i++){
            const div=document.createElement('div');
            div.className='cell skel';
            grid.appendChild(div);
        }
        
        const [y,m] = ym.split('-').map(Number);
        const first = new Date(y, m-1, 1);
        const startDay = first.getDay();
        const daysInMonth = new Date(y, m, 0).getDate();
        
        const cells=[];
        const prevMonthLast = new Date(y, m-1, 0).getDate();
        for(let i=prevMonthLast-startDay+1;i<=prevMonthLast;i++){
            const d=new Date(y, m-2, i); cells.push({date:d,current:false,day:i,plans:[]});
        }
        for(let d=1; d<=daysInMonth; d++){
            cells.push({date:new Date(y,m-1,d), current:true, day:d, plans:[]});
        }
        let nextDay = 1;
        while(cells.length % 7 !== 0){
            const d = new Date(y, m, nextDay);
            cells.push({date:d,current:false,day:nextDay,plans:[]});
            nextDay++;
        }
        
        let planned=0;
        const uid = getUid();
        const byDate = new Map();
        setActionsDisabled(!uid);
        
        if (uid) {
            try{
                const res = await api(`/api/plans?user_id=${encodeURIComponent(uid)}&month=${ym}`);
                (res.items||[]).forEach(p=>{ const arr = byDate.get(p.plan_date) || []; arr.push(p); byDate.set(p.plan_date, arr); });
            }catch(e){
                if (e.name !== 'AbortError') alert('予定の取得に失敗しました。\n' + e.message);
            }
        }
        
        grid.innerHTML='';
        cells.forEach(c=>{
            c.iso = toLocalYmd(c.date);
            const items = byDate.get(c.iso) || [];
            const div=document.createElement('div');

            // 曜日判定（0=日曜, 6=土曜）
            const dayOfWeek = c.date.getDay();
            const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;

            div.className = 'cell'+(c.current?'':' muted')+(isWeekend?' weekend':'');
            if(dayOfWeek === 0) div.classList.add('sunday');
            if(dayOfWeek === 6) div.classList.add('saturday');

            const plansHtml = items.map(it => {
                planned++;
                const labelType = it.plan_type==='onsite'?'通所':'在宅';
                const labelSlot = it.plan_time_slot==='am'?'午前':it.plan_time_slot==='pm'?'午後':'終日';
                return `<div class="plan" data-id="${it.id}">
                    <span>${labelType}・${labelSlot}</span>
                    <span>
                        <button data-act="edit" data-id="${it.id}" data-type="${it.plan_type}" data-slot="${it.plan_time_slot}"
                                data-date="${it.plan_date}" data-note="${esc(it.note||'')}">編</button>
                        <button data-act="del" data-id="${it.id}">削</button>
                    </span>
                </div>`;
            }).join('');

            // 土日は＋ボタンを表示しない
            const addButton = isWeekend ? '' : '<button class="btn add" data-act="add" data-date="'+c.iso+'">＋</button>';
            div.innerHTML = `<div class="day">${c.day}</div>${plansHtml}${addButton}`;
            
            div.addEventListener('click', (e)=>{
                const act = e.target.getAttribute('data-act');
                if(!act) return;
                if(act==='add'){
                    openEditor({ id:null, plan_date:e.target.getAttribute('data-date'), plan_type:'onsite', plan_time_slot:'am', note:'' });
                }else if(act==='edit'){
                    openEditor({
                        id:e.target.getAttribute('data-id'),
                        plan_date:e.target.getAttribute('data-date'),
                        plan_type:e.target.getAttribute('data-type'),
                        plan_time_slot:e.target.getAttribute('data-slot'),
                        note:e.target.getAttribute('data-note')||''
                    });
                }else if(act==='del'){
                    deletePlan(e.target.getAttribute('data-id'));
                }
            });
            
            grid.appendChild(div);
        });
        
        plannedCountEl.textContent = planned;
    }
    
    function openEditor(p){
        editing = { id: p.id, iso: p.plan_date };
        editor.title.textContent = p.id ? '予定編集' : '新規予定';
        editor.date.value = p.plan_date;
        editor.type.value = p.plan_type || 'onsite';
        editor.slot.value = p.plan_time_slot || 'am';
        editor.remarks.value = p.note || '';
        editor.btnDelete.hidden = !p.id;
        editor.wrap.hidden = false;
    }
    
    async function savePlan(){
        const ym = ymSelect.value;
        const uid = getUid(); if(!uid){ alert('利用者を選択してください'); return; }
        const payload = { plan_date: editor.date.value, plan_type: editor.type.value, plan_time_slot: editor.slot.value, note: editor.remarks.value || null };
        try{
            setActionsDisabled(true);
            if (editing.id) {
                await api(`/api/plans/${editing.id}`, { method:'PUT', body: JSON.stringify(payload) });
            } else {
                await api(`/api/plans`, { method:'POST', body: JSON.stringify({ user_id:Number(uid), month: ym, mode:'merge', items:[payload] }) });
            }
            editor.wrap.hidden = true; await buildGrid(ym);
            showNotification('保存しました', 'success');
        }catch(e){ alert('保存に失敗: ' + e.message); }
        finally{ setActionsDisabled(false); }
    }
    
    async function deletePlan(id){
        const ym = ymSelect.value; if(!id) return; if(!confirm('削除しますか？')) return;
        try{
            setActionsDisabled(true);
            await api(`/api/plans/${id}`, { method:'DELETE' });
            await buildGrid(ym);
            showNotification('削除しました', 'success');
        }catch(e){ alert('削除に失敗: ' + e.message); }
        finally{ setActionsDisabled(false); }
    }
    
    $('copyPrev').addEventListener('click', async ()=>{
        const ym = ymSelect.value; const uid = getUid(); if(!uid){ alert('利用者を選択してください'); return; }
        try{
            setActionsDisabled(true);
            await api(`/api/plans/template/copy-previous`, { method:'POST', body: JSON.stringify({ user_id:Number(uid), month: ym, mode:'merge' }) });
            await buildGrid(ym);
            showNotification('前月をコピーしました', 'success');
        }catch(e){ alert('前月コピー失敗: '+e.message); }
        finally{ setActionsDisabled(false); }
    });
    
    $('wkBulk').addEventListener('click', async ()=>{
        const ym = ymSelect.value; const uid = getUid(); if(!uid){ alert('利用者を選択してください'); return; }
        try{
            setActionsDisabled(true);
            await api(`/api/plans/template/weekday-bulk`, { method:'POST', body: JSON.stringify({ user_id:Number(uid), month: ym, plan_time_slot:'am', weekdays:[1,2,3,4,5], mode:'merge', exclude_holidays:false }) });
            await buildGrid(ym);
            showNotification('平日に一括設定しました', 'success');
        }catch(e){ alert('平日一括失敗: '+e.message); }
        finally{ setActionsDisabled(false); }
    });
    
    editor.btnSave.addEventListener('click', savePlan);
    editor.btnCancel.addEventListener('click', ()=> editor.wrap.hidden=true);
    
    const debouncedBuild = debounce(()=> buildGrid(ymSelect.value), 180);
    ymSelect.addEventListener('change', debouncedBuild);
    $('userSelect').addEventListener('change', debouncedBuild);
    
    buildGrid(ymSelect.value);
})();
</script>
@endsection