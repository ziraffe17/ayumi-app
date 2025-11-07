@extends('layouts.user')

@section('title', '設定')

@section('styles')
<style>
    .settings-container {
        max-width: 800px;
        margin: 0 auto;
    }

    .settings-card {
        background: white;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .settings-card h3 {
        color: #111827;
        margin-bottom: 20px;
        font-size: 20px;
        border-bottom: 2px solid #16a34a;
        padding-bottom: 8px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #374151;
    }

    .form-control {
        width: 100%;
        padding: 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.2s;
    }

    .form-control:focus {
        outline: none;
        border-color: #16a34a;
        box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
    }

    .form-control.error {
        border-color: #dc2626;
    }

    .error-message {
        color: #dc2626;
        font-size: 13px;
        margin-top: 4px;
    }

    .help-text {
        color: #6b7280;
        font-size: 13px;
        margin-top: 4px;
    }

    .btn {
        padding: 12px 24px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
        text-decoration: none;
        display: inline-block;
        text-align: center;
    }

    .btn-primary {
        background: #16a34a;
        color: white;
    }

    .btn-primary:hover {
        background: #15803d;
    }

    .btn-secondary {
        background: #f3f4f6;
        color: #374151;
        border: 1px solid #d1d5db;
    }

    .btn-secondary:hover {
        background: #e5e7eb;
        border-color: #9ca3af;
    }

    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }

    .alert.success {
        background: #dcfce7;
        border: 1px solid #86efac;
        color: #166534;
    }

    .alert.error {
        background: #fee2e2;
        border: 1px solid #fca5a5;
        color: #dc2626;
    }

    .profile-summary {
        background: #f9fafb;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 20px;
    }

    .profile-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #e5e7eb;
    }

    .profile-item:last-child {
        border-bottom: none;
    }

    .profile-label {
        color: #6b7280;
        font-size: 14px;
        font-weight: 500;
    }

    .profile-value {
        color: #111827;
        font-size: 14px;
    }

    .password-requirements {
        background: #f0f9ff;
        border: 1px solid #bae6fd;
        border-radius: 6px;
        padding: 12px;
        margin-top: 8px;
    }

    .password-requirements h4 {
        color: #0c4a6e;
        font-size: 14px;
        margin-bottom: 8px;
    }

    .password-requirements ul {
        margin: 0;
        padding-left: 16px;
        color: #075985;
        font-size: 13px;
    }

    @media (max-width: 768px) {
        .settings-container {
            padding: 0 16px;
        }

        .settings-card {
            padding: 16px;
        }

        .profile-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
        }
    }
</style>
@endsection

@section('content')
<div class="settings-container">
    <h2 style="color: #111827; margin-bottom: 24px;">設定</h2>

    @if(session('success'))
    <div class="alert success">
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="alert error">
        <ul style="margin: 0; padding-left: 20px;">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- プロフィール情報 -->
    <div class="settings-card">
        <h3>🧑‍💼 プロフィール情報</h3>
        
        <div class="profile-summary">
            <div class="profile-item">
                <span class="profile-label">氏名</span>
                <span class="profile-value">{{ $user->name }}</span>
            </div>
            <div class="profile-item">
                <span class="profile-label">利用者番号</span>
                <span class="profile-value">{{ $user->user_number ?? 'U' . str_pad($user->id, 4, '0', STR_PAD_LEFT) }}</span>
            </div>
            <div class="profile-item">
                <span class="profile-label">登録日</span>
                <span class="profile-value">{{ $user->created_at->format('Y年n月j日') }}</span>
            </div>
            <div class="profile-item">
                <span class="profile-label">最終ログイン</span>
                <span class="profile-value">{{ $user->last_login_at ? $user->last_login_at->format('Y年n月j日 H:i') : '未記録' }}</span>
            </div>
        </div>

        <form method="POST" action="{{ route('user.settings.profile') }}">
            @csrf
            <div class="form-group">
                <label for="email">メールアドレス</label>
                <input type="email" id="email" name="email" class="form-control {{ $errors->has('email') ? 'error' : '' }}" 
                       value="{{ old('email', $user->email) }}" required>
                @error('email')
                <div class="error-message">{{ $message }}</div>
                @enderror
                <div class="help-text">緊急連絡やシステム通知に使用されます</div>
            </div>

            <div class="form-group">
                <label for="phone">電話番号</label>
                <input type="tel" id="phone" name="phone" class="form-control {{ $errors->has('phone') ? 'error' : '' }}" 
                       value="{{ old('phone', $user->phone) }}" placeholder="例: 090-1234-5678">
                @error('phone')
                <div class="error-message">{{ $message }}</div>
                @enderror
                <div class="help-text">緊急時の連絡先として使用されます</div>
            </div>

            <button type="submit" class="btn btn-primary">プロフィール更新</button>
        </form>
    </div>

    <!-- パスワード変更 -->
    <div class="settings-card">
        <h3>🔒 パスワード変更</h3>
        
        <form method="POST" action="{{ route('user.settings.password') }}">
            @csrf
            <div class="form-group">
                <label for="current_password">現在のパスワード</label>
                <input type="password" id="current_password" name="current_password" 
                       class="form-control {{ $errors->has('current_password') ? 'error' : '' }}" required>
                @error('current_password')
                <div class="error-message">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="password">新しいパスワード</label>
                <input type="password" id="password" name="password" 
                       class="form-control {{ $errors->has('password') ? 'error' : '' }}" required>
                @error('password')
                <div class="error-message">{{ $message }}</div>
                @enderror
                
                <div class="password-requirements">
                    <h4>パスワード要件</h4>
                    <ul>
                        <li>8文字以上</li>
                        <li>大文字と小文字を含む</li>
                        <li>数字を含む</li>
                        <li>記号を含む（推奨）</li>
                        <li>現在のパスワードと異なること</li>
                    </ul>
                </div>
            </div>

            <div class="form-group">
                <label for="password_confirmation">新しいパスワード（確認）</label>
                <input type="password" id="password_confirmation" name="password_confirmation" 
                       class="form-control" required>
                <div class="help-text">確認のため、新しいパスワードをもう一度入力してください</div>
            </div>

            <div style="display: flex; gap: 12px; align-items: center;">
                <button type="submit" class="btn btn-primary">パスワード変更</button>
                <span style="color: #6b7280; font-size: 13px;">
                    最終変更: {{ $user->password_changed_at ? $user->password_changed_at->format('Y年n月j日') : '初回設定時' }}
                </span>
            </div>
        </form>
    </div>

    <!-- セキュリティ情報 -->
    <div class="settings-card">
        <h3>🛡️ セキュリティ情報</h3>
        
        <div class="profile-summary">
            <div class="profile-item">
                <span class="profile-label">アカウント状態</span>
                <span class="profile-value" style="color: {{ $user->is_active ? '#16a34a' : '#dc2626' }}">
                    {{ $user->is_active ? '有効' : '無効' }}
                </span>
            </div>
            <div class="profile-item">
                <span class="profile-label">パスワード変更日</span>
                <span class="profile-value">{{ $user->password_changed_at ? $user->password_changed_at->format('Y年n月j日') : '初回設定時' }}</span>
            </div>
            <div class="profile-item">
                <span class="profile-label">最終ログイン</span>
                <span class="profile-value">{{ $user->last_login_at ? $user->last_login_at->format('Y年n月j日 H:i') : '未記録' }}</span>
            </div>
        </div>

        <div style="background: #fef7ed; border: 1px solid #fed7aa; border-radius: 6px; padding: 12px; margin-top: 16px;">
            <h4 style="color: #ea580c; font-size: 14px; margin-bottom: 8px;">セキュリティに関するご注意</h4>
            <ul style="margin: 0; padding-left: 16px; color: #c2410c; font-size: 13px;">
                <li>パスワードは定期的に変更してください</li>
                <li>他人とパスワードを共有しないでください</li>
                <li>使用後は必ずログアウトしてください</li>
                <li>不審なアクセスを発見した場合はすぐに職員にご報告ください</li>
            </ul>
        </div>
    </div>

    <!-- その他の設定 -->
    <div class="settings-card">
        <h3>⚙️ その他の設定</h3>
        
        <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 6px; padding: 16px;">
            <h4 style="color: #0c4a6e; margin-bottom: 12px;">設定変更について</h4>
            <p style="color: #075985; font-size: 14px; margin: 0; line-height: 1.5;">
                基本情報（氏名、生年月日等）やアカウント設定の変更をご希望の場合は、
                職員までお声がけください。安全のため、重要な設定変更は職員が対応いたします。
            </p>
        </div>

        <div style="margin-top: 20px; text-align: center;">
            <a href="{{ route('user.home') }}" class="btn btn-secondary">ホームに戻る</a>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// パスワード強度チェック
document.getElementById('password')?.addEventListener('input', function(e) {
    const password = e.target.value;
    const requirements = document.querySelector('.password-requirements');
    
    if (password.length === 0) {
        requirements.style.display = 'block';
        return;
    }
    
    const checks = {
        length: password.length >= 8,
        uppercase: /[A-Z]/.test(password),
        lowercase: /[a-z]/.test(password),
        number: /\d/.test(password),
        symbol: /[!@#$%^&*(),.?":{}|<>]/.test(password)
    };
    
    const allValid = Object.values(checks).every(Boolean);
    requirements.style.borderColor = allValid ? '#86efac' : '#fed7aa';
    requirements.style.backgroundColor = allValid ? '#dcfce7' : '#fef7ed';
});

// フォーム送信時の確認
document.querySelector('form[action*="password"]')?.addEventListener('submit', function(e) {
    if (!confirm('パスワードを変更してもよろしいですか？')) {
        e.preventDefault();
    }
});

// プロフィール更新時の確認
document.querySelector('form[action*="profile"]')?.addEventListener('submit', function(e) {
    if (!confirm('プロフィール情報を更新してもよろしいですか？')) {
        e.preventDefault();
    }
});
</script>
@endsection