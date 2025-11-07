{{-- resources/views/staff/export/csv.blade.php --}}
@extends('layouts.staff')

@section('title', 'S-10 CSV出力')

@section('content')
<h2>CSV出力</h2>

<div style="background:#fff;border-radius:8px;padding:20px;margin:12px 0;border:1px solid var(--line)">
    <form method="GET" action="{{ route('staff.export.download') }}" id="exportForm">
        <div style="margin:16px 0">
            <label style="display:block;margin-bottom:6px;font-weight:600">出力種別</label>
            <select name="type" id="exportType" required style="width:100%;border:1px solid var(--line);padding:8px 12px;border-radius:6px">
                <option value="">選択してください</option>
                <option value="attendance">出席実績</option>
                <option value="reports">日報</option>
                <option value="users">利用者一覧</option>
                <option value="kpi">KPI集計</option>
            </select>
        </div>

        <div style="margin:16px 0">
            <label style="display:block;margin-bottom:6px;font-weight:600">対象期間</label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                    <input type="date" name="start_date" id="startDate" required style="width:100%;border:1px solid var(--line);padding:8px 12px;border-radius:6px">
                    <div style="font-size:12px;color:#6b7280;margin-top:4px">開始日</div>
                </div>
                <div>
                    <input type="date" name="end_date" id="endDate" required style="width:100%;border:1px solid var(--line);padding:8px 12px;border-radius:6px">
                    <div style="font-size:12px;color:#6b7280;margin-top:4px">終了日</div>
                </div>
            </div>
        </div>

        <div style="margin:16px 0">
            <label style="display:block;margin-bottom:6px;font-weight:600">対象利用者</label>
            <select name="user_filter" id="userFilter" style="width:100%;border:1px solid var(--line);padding:8px 12px;border-radius:6px">
                <option value="all">全員</option>
                <option value="active">有効のみ</option>
                <option value="specific">個別指定</option>
            </select>
        </div>

        <div style="margin:16px 0;display:none" id="specificUsersGroup">
            <label style="display:block;margin-bottom:6px;font-weight:600">利用者選択（複数可）</label>
            <div style="display:flex;flex-direction:column;gap:8px;margin-top:8px" id="userCheckboxes">
                @foreach($users ?? [] as $user)
                <div style="display:flex;align-items:center;gap:8px">
                    <input type="checkbox" name="user_ids[]" value="{{ $user->id }}" id="user_{{ $user->id }}" style="width:auto">
                    <label for="user_{{ $user->id }}" style="margin:0;font-weight:normal">{{ $user->name }}</label>
                </div>
                @endforeach
            </div>
        </div>

        <div style="margin:16px 0">
            <label style="display:block;margin-bottom:6px;font-weight:600">出力項目</label>
            <div style="display:flex;flex-direction:column;gap:8px;margin-top:8px" id="columnCheckboxes">
                <!-- JavaScript で動的生成 -->
            </div>
        </div>

        <div style="margin:16px 0">
            <label style="display:block;margin-bottom:6px;font-weight:600">文字コード</label>
            <select name="encoding" style="width:100%;border:1px solid var(--line);padding:8px 12px;border-radius:6px">
                <option value="UTF-8">UTF-8</option>
                <option value="SJIS">Shift-JIS (Excel互換)</option>
            </select>
        </div>

        <div style="display:flex;gap:12px;margin-top:24px">
            <button type="button" class="btn" onclick="preview()">プレビュー</button>
            <button type="submit" class="btn primary">ダウンロード</button>
        </div>
    </form>
</div>

<div style="background:#fff;border-radius:8px;padding:20px;margin:12px 0;border:1px solid var(--line);display:none" id="previewCard">
    <h3>プレビュー</h3>
    <div id="previewContent" style="overflow-x:auto;font-size:12px;font-family:monospace"></div>
</div>
@endsection

@section('scripts')
<script>
const columnSets = {
    attendance: [
        {id: 'date', label: '日付', checked: true},
        {id: 'user_name', label: '氏名', checked: true},
        {id: 'plan_type', label: '予定', checked: true},
        {id: 'attendance_type', label: '実績', checked: true},
        {id: 'note', label: '備考', checked: false}
    ],
    reports: [
        {id: 'date', label: '日付', checked: true},
        {id: 'user_name', label: '氏名', checked: true},
        {id: 'sleep_rating', label: '睡眠', checked: true},
        {id: 'stress_rating', label: 'ストレス', checked: true},
        {id: 'meal_rating', label: '食事', checked: true},
        {id: 'mood_score', label: '気分', checked: true},
        {id: 'training_summary', label: '訓練内容', checked: false}
    ],
    users: [
        {id: 'id', label: 'ID', checked: true},
        {id: 'name', label: '氏名', checked: true},
        {id: 'login_code', label: 'ログインコード', checked: true},
        {id: 'start_date', label: '開始日', checked: true},
        {id: 'is_active', label: '状態', checked: true}
    ],
    kpi: [
        {id: 'user_name', label: '氏名', checked: true},
        {id: 'planned_days', label: '計画日数', checked: true},
        {id: 'actual_days', label: '実出席', checked: true},
        {id: 'attendance_rate', label: '出席率%', checked: true},
        {id: 'report_input_rate', label: '日報入力率%', checked: true}
    ]
};

document.getElementById('exportType').addEventListener('change', (e) => {
    const type = e.target.value;
    const container = document.getElementById('columnCheckboxes');
    container.innerHTML = '';
    
    if (columnSets[type]) {
        columnSets[type].forEach(col => {
            const div = document.createElement('div');
            div.style.cssText = 'display:flex;align-items:center;gap:8px';
            div.innerHTML = `
                <input type="checkbox" name="columns[]" value="${col.id}" id="col_${col.id}" ${col.checked ? 'checked' : ''} style="width:auto">
                <label for="col_${col.id}" style="margin:0;font-weight:normal">${col.label}</label>
            `;
            container.appendChild(div);
        });
    }
});

document.getElementById('userFilter').addEventListener('change', (e) => {
    document.getElementById('specificUsersGroup').style.display = 
        e.target.value === 'specific' ? 'block' : 'none';
});

function preview() {
    const form = document.getElementById('exportForm');
    const formData = new FormData(form);
    
    const type = formData.get('type');
    const startDate = formData.get('start_date');
    const endDate = formData.get('end_date');
    
    if (!type || !startDate || !endDate) {
        alert('出力種別と期間を選択してください');
        return;
    }
    
    const previewCard = document.getElementById('previewCard');
    const previewContent = document.getElementById('previewContent');
    
    let preview = '<table style="border-collapse:collapse"><tr>';
    const columns = Array.from(formData.getAll('columns[]'));
    columns.forEach(col => {
        preview += `<th style="border:1px solid #e5e7eb;padding:8px">${col}</th>`;
    });
    preview += '</tr><tr>';
    columns.forEach(() => {
        preview += '<td style="border:1px solid #e5e7eb;padding:8px">サンプル</td>';
    });
    preview += '</tr></table>';
    
    previewContent.innerHTML = preview;
    previewCard.style.display = 'block';
}

document.addEventListener('DOMContentLoaded', () => {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    
    document.getElementById('startDate').value = firstDay.toISOString().split('T')[0];
    document.getElementById('endDate').value = lastDay.toISOString().split('T')[0];
});
</script>
@endsection