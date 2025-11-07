@extends('layouts.staff')

@section('title', '利用者新規登録')

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
    @media(max-width:768px){.form-grid{grid-template-columns:1fr}.actions{flex-direction:column}}
</style>
@endsection

@section('content')
<div class="form-container">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <h2 style="margin:0">利用者新規登録</h2>
        <a href="{{ route('staff.users.index') }}" class="btn">一覧に戻る</a>
    </div>

    @if($errors->any())
    <div class="alert error" style="background:#fee2e2;border:1px solid #dc2626;color:#dc2626;padding:12px;border-radius:6px;margin-bottom:16px">
        <ul style="margin:0;padding-left:20px">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('staff.users.store') }}">
        @csrf

        <!-- 基本情報 -->
        <div class="card">
            <h3>基本情報</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="name">氏名 <span class="required">*</span></label>
                    <input type="text" class="form-control @error('name') error @enderror" 
                           id="name" name="name" value="{{ old('name') }}" required maxlength="100">
                    @error('name')
                    <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="name_kana">氏名カナ</label>
                    <input type="text" class="form-control @error('name_kana') error @enderror" 
                           id="name_kana" name="name_kana" value="{{ old('name_kana') }}" maxlength="100">
                    <div class="help-text">フリガナ（カタカナ）</div>
                    @error('name_kana')
                    <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="login_code">ログインコード <span class="required">*</span></label>
                    <div style="display:flex;align-items:center;gap:8px">
                        <span style="font-family:monospace;font-size:16px;font-weight:600;color:#374151">u</span>
                        <input type="text" class="form-control @error('login_code') error @enderror"
                               id="login_code_number" name="login_code_number" value="{{ old('login_code_number') }}"
                               required maxlength="4" pattern="\d{4}" placeholder="{{ $nextLoginCode ?? '0001' }}"
                               style="font-family:monospace;flex:0 0 100px"
                               oninput="this.value = this.value.replace(/[^0-9]/g, '').padStart(4, '0').slice(0, 4)">
                        <input type="hidden" id="login_code" name="login_code" value="{{ old('login_code') }}">
                        <button type="button" class="btn" onclick="suggestNextCode()" style="padding:6px 12px">次のIDを使用</button>
                    </div>
                    <div class="help-text">4桁の数字を入力（自動で u が付きます）。次の利用可能なID: <strong>u{{ $nextLoginCode ?? '0001' }}</strong></div>
                    @error('login_code')
                    <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="password">初期パスワード <span class="required">*</span></label>
                    <input type="password" class="form-control @error('password') error @enderror" 
                           id="password" name="password" required minlength="6">
                    <div class="help-text">6文字以上</div>
                    @error('password')
                    <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="email">メールアドレス</label>
                    <input type="email" class="form-control @error('email') error @enderror" 
                           id="email" name="email" value="{{ old('email') }}" maxlength="255">
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
                           id="start_date" name="start_date" value="{{ old('start_date') }}" required>
                    @error('start_date')
                    <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="end_date">利用終了日</label>
                    <input type="date" class="form-control @error('end_date') error @enderror" 
                           id="end_date" name="end_date" value="{{ old('end_date') }}">
                    <div class="help-text">期限がない場合は空欄</div>
                    @error('end_date')
                    <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="is_active">状態</label>
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_active" name="is_active" value="1" 
                               {{ old('is_active', true) ? 'checked' : '' }}>
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
                          id="care_notes_enc" name="care_notes_enc" rows="4" maxlength="2000">{{ old('care_notes_enc') }}</textarea>
                <div class="help-text">個人に関する重要な情報・配慮事項（自動暗号化されます）</div>
                @error('care_notes_enc')
                <div class="error-message">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="actions">
            <a href="{{ route('staff.users.index') }}" class="btn">キャンセル</a>
            <button type="submit" class="btn primary">登録する</button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
const nextLoginCode = '{{ $nextLoginCode ?? "0001" }}';

// 次のIDを使用ボタン
function suggestNextCode() {
    const numberInput = document.getElementById('login_code_number');
    numberInput.value = nextLoginCode;
    updateLoginCode();
}

// ログインコードの更新
function updateLoginCode() {
    const numberInput = document.getElementById('login_code_number');
    const hiddenInput = document.getElementById('login_code');
    const number = numberInput.value.padStart(4, '0');
    hiddenInput.value = 'u' + number;
}

// 入力時に自動更新
document.getElementById('login_code_number').addEventListener('input', updateLoginCode);
document.getElementById('login_code_number').addEventListener('change', updateLoginCode);

// フォーム送信時に確認
document.querySelector('form').addEventListener('submit', function(e) {
    updateLoginCode();
    const loginCode = document.getElementById('login_code').value;
    if (!loginCode.match(/^u\d{4}$/)) {
        e.preventDefault();
        alert('ログインコードは4桁の数字を入力してください');
        return false;
    }
});

// 利用終了日の制約
document.getElementById('start_date').addEventListener('change', function() {
    const endDateInput = document.getElementById('end_date');
    if (this.value) {
        endDateInput.min = this.value;
    }
});

// 初期化
updateLoginCode();
</script>
@endsection