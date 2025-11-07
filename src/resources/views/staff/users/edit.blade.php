@extends('layouts.staff')

@section('title', '利用者編集 - ' . ($user->name ?? ''))

@section('styles')
<style>
    .form-container{max-width:800px;margin:0 auto}
    .card{background:#fff;border-radius:8px;padding:24px;margin:16px 0;border:1px solid var(--line)}
    .card h3{margin:0 0 20px;font-size:18px;color:#374151;border-bottom:2px solid var(--line);padding-bottom:8px}
    .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px}
    .form-group{margin-bottom:20px}
    .form-group label{display:block;margin-bottom:6px;font-weight:600;color:#374151}
    .form-control{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:6px;font-size:14px;transition:border-color 0.2s}
    .form-control:focus{outline:none;border-color:var(--deep);box-shadow:0 0 0 3px rgba(22,163,74,0.1)}
    .form-control.error{border-color:#dc2626}
    .help-text{font-size:12px;color:#6b7280;margin-top:4px}
    .required{color:#dc2626}
    .checkbox-group{display:flex;align-items:center;gap:8px}
    .error-message{color:#dc2626;font-size:13px;margin-top:4px}
    .actions{display:flex;gap:12px;justify-content:flex-end;margin-top:24px;padding-top:20px;border-top:1px solid var(--line)}
    .danger-zone{background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:16px;margin-top:24px}
    .danger-zone h4{color:#dc2626;margin:0 0 12px;font-size:16px}
    .danger-actions{display:flex;gap:8px;margin-top:12px}
    @media(max-width:768px){.form-grid{grid-template-columns:1fr}.actions{flex-direction:column}}
</style>
@endsection

@section('content')
<div class="form-container">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <h2 style="margin:0">利用者編集: {{ $user->name }}</h2>
        <div style="display:flex;gap:8px">
            <a href="{{ route('staff.users.show', $user) }}" class="btn">詳細に戻る</a>
            <a href="{{ route('staff.users.index') }}" class="btn">一覧に戻る</a>
        </div>
    </div>

    @if(session('success'))
    <div class="alert success" style="background:#dcfce7;border:1px solid #16a34a;color:#166534;padding:12px;border-radius:6px;margin-bottom:16px">
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="alert error" style="background:#fee2e2;border:1px solid #dc2626;color:#dc2626;padding:12px;border-radius:6px;margin-bottom:16px">
        <ul style="margin:0;padding-left:20px">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('staff.users.update', $user) }}">
        @csrf
        @method('PUT')

        <!-- 基本情報 -->
        <div class="card">
            <h3>基本情報</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="name">氏名 <span class="required">*</span></label>
                    <input type="text" class="form-control @error('name') error @enderror" 
                           id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="100">
                    @error('name')
                    <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="name_kana">氏名カナ</label>
                    <input type="text" class="form-control @error('name_kana') error @enderror" 
                           id="name_kana" name="name_kana" value="{{ old('name_kana', $user->name_kana) }}" maxlength="100">
                    <div class="help-text">フリガナ（カタカナ）</div>
                    @error('name_kana')
                    <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="login_code">ログインコード <span class="required">*</span></label>
                    <input type="text" class="form-control @error('login_code') error @enderror"
                           id="login_code" name="login_code" value="{{ old('login_code', $user->login_code) }}"
                           required maxlength="5" pattern="u\d{4}" placeholder="u0001" style="font-family:monospace">
                    <div class="help-text">u + 4桁の数字（例: u0001）</div>
                    @error('login_code')
                    <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="password">パスワード変更</label>
                    <input type="password" class="form-control @error('password') error @enderror" 
                           id="password" name="password" minlength="6">
                    <div class="help-text">変更する場合のみ入力（6文字以上）</div>
                    @error('password')
                    <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="email">メールアドレス</label>
                    <input type="email" class="form-control @error('email') error @enderror" 
                           id="email" name="email" value="{{ old('email', $user->email) }}" maxlength="255">
                    <div class="help-text">任意</div>
                    @error('email')
                    <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <!-- 利用期間 -->
        <div class="card">
            <h3>利用期間</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="start_date">利用開始日 <span class="required">*</span></label>
                    <input type="date" class="form-control @error('start_date') error @enderror"
                           id="start_date" name="start_date" value="{{ old('start_date', $user->start_date?->format('Y-m-d')) }}" required>
                    @error('start_date')
                    <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="end_date">利用終了日</label>
                    <input type="date" class="form-control @error('end_date') error @enderror"
                           id="end_date" name="end_date" value="{{ old('end_date', $user->end_date?->format('Y-m-d')) }}">
                    <div class="help-text">期限がない場合は空欄</div>
                    @error('end_date')
                    <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="is_active">状態</label>
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_active" name="is_active" value="1" 
                               {{ old('is_active', $user->is_active) ? 'checked' : '' }}>
                        <label for="is_active" style="margin:0">有効</label>
                    </div>
                    <div class="help-text">チェックを外すと無効状態になります</div>
                    @error('is_active')
                    <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <!-- 配慮事項 -->
        <div class="card">
            <h3>配慮事項</h3>
            <div class="form-group">
                <label for="care_notes_enc">配慮事項（暗号化保存）</label>
                <textarea class="form-control @error('care_notes_enc') error @enderror" 
                          id="care_notes_enc" name="care_notes_enc" rows="4" maxlength="2000">{{ old('care_notes_enc', $user->care_notes_enc) }}</textarea>
                <div class="help-text">個人に関する重要な情報・配慮事項（自動暗号化されます）</div>
                @error('care_notes_enc')
                <div class="error-message">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="actions">
            <a href="{{ route('staff.users.show', $user) }}" class="btn">キャンセル</a>
            <button type="submit" class="btn primary">更新する</button>
        </div>
    </form>

    <!-- 危険な操作 -->
    <div class="danger-zone">
        <h4>危険な操作</h4>
        <p style="color:#7f1d1d;margin:0 0 12px;font-size:14px">
            以下の操作は取り消しができません。慎重に実行してください。
        </p>
        <div class="danger-actions">
            <form method="POST" action="{{ route('staff.users.reset-password', $user) }}" 
                  onsubmit="return confirm('パスワードをリセットしますか？新しいパスワードが表示されます。')" style="display:inline">
                @csrf
                <button type="submit" class="btn" style="background:#f59e0b;color:white;border-color:#f59e0b">
                    パスワードリセット
                </button>
            </form>
            
            <form method="POST" action="{{ route('staff.users.destroy', $user) }}" 
                  onsubmit="return confirm('利用者を無効化しますか？この操作は取り消しできません。')" style="display:inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn" style="background:#dc2626;color:white;border-color:#dc2626">
                    利用者無効化
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// ログインコードの自動フォーマット
document.getElementById('login_code').addEventListener('input', function(e) {
    e.target.value = e.target.value.replace(/[^0-9]/g, '').substring(0, 6);
});

// 利用終了日の制約
document.getElementById('start_date').addEventListener('change', function() {
    const endDateInput = document.getElementById('end_date');
    if (this.value) {
        endDateInput.min = this.value;
    }
});
</script>
@endsection