{{-- resources/views/auth/login.blade.php --}}
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>あゆみ - 職員ログイン</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Hiragino Sans','Yu Gothic UI',system-ui,sans-serif;background:#f9fafb;color:#111827;line-height:1.6;min-height:100vh;display:flex;align-items:center;justify-content:center}
    .login-container{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:40px;max-width:450px;width:90%;box-shadow:0 4px 6px rgba(0,0,0,.1)}
    .logo{text-align:center;margin-bottom:32px}
    .logo h1{font-size:32px;color:#16a34a;text-decoration:underline;margin-bottom:8px}
    .logo p{color:#6b7280;font-size:14px}
    .staff-badge{background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff;padding:8px 16px;border-radius:20px;font-size:12px;font-weight:600;margin:16px auto;display:inline-block}
    .form-group{margin-bottom:20px}
    .form-group label{display:block;margin-bottom:8px;font-weight:500;color:#374151}
    .form-control{width:100%;padding:12px 16px;border:1px solid #d1d5db;border-radius:8px;font-size:16px;transition:border-color .2s,box-shadow .2s}
    .form-control:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
    .password-container{position:relative}
    .password-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#6b7280;font-size:12px;padding:4px 8px;user-select:none}
    .btn-primary{width:100%;background:#3b82f6;color:#fff;border:none;padding:14px;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;transition:background-color .2s}
    .btn-primary:hover{background:#2563eb}
    .btn-primary:active{background:#1d4ed8}
    .forgot-password{text-align:center;margin-top:20px}
    .forgot-password a{color:#3b82f6;text-decoration:none;font-size:14px}
    .forgot-password a:hover{text-decoration:underline}
    .help-text{font-size:12px;color:#6b7280;margin-top:4px}
    .alert{padding:12px;border-radius:8px;margin-bottom:20px;font-size:14px}
    .alert-error{background:#fef2f2;border:1px solid #fca5a5;color:#dc2626}
    .alert-success{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46}
    .user-login-link{text-align:center;margin-top:24px;padding-top:24px;border-top:1px solid #e5e7eb}
    .user-login-link a{color:#6b7280;text-decoration:none;font-size:14px}
    .user-login-link a:hover{color:#16a34a;text-decoration:underline}
    .security-info{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:24px;font-size:13px;color:#475569}
    .security-info h4{color:#334155;margin-bottom:8px;font-size:14px}
    .security-info ul{margin-left:16px}
    .security-info li{margin:4px 0}
    @media (max-width:480px){.login-container{margin:20px;padding:24px}}
  </style>
</head>
<body>
  <div class="login-container">
    <div class="logo">
      <h1>あゆみ</h1>
      <p>福祉事業所管理システム</p>
      <div class="staff-badge">職員ログイン</div>
    </div>

    <div class="security-info">
      <h4>🔒 セキュリティについて</h4>
      <ul>
        <li>職員アカウントはログイン後に<strong>メールによる2段階認証</strong>を求められます</li>
        <li>メール受信ができる端末をご用意ください</li>
      </ul>
    </div>

    {{-- 成功メッセージ --}}
    @if (session('status'))
      <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    {{-- Fortifyのログイン（職員もここにPOST） --}}
    <form method="POST" action="{{ route('login') }}">
      @csrf

      {{-- エラーメッセージ --}}
      @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
      @endif

      <div class="form-group">
        <label for="email">メールアドレス</label>
        <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="staff@example.com">
        <div class="help-text">職員用メールアドレスを入力してください</div>
      </div>

      <div class="form-group">
        <label for="password">パスワード</label>
        <div class="password-container">
          <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
          <button type="button" class="password-toggle" onclick="togglePassword()">表示</button>
        </div>
      </div>

      <button type="submit" class="btn-primary">ログイン</button>
    </form>

    <div class="forgot-password">
      <a href="{{ route('password.request') }}">パスワードをお忘れですか？</a>
    </div>

    <div class="user-login-link">
      <a href="{{ route('user.login') }}">利用者の方はこちら</a>
    </div>
  </div>

  <script>
    function togglePassword(){
      const f = document.getElementById('password');
      const btn = document.querySelector('.password-toggle');
      if(f.type==='password'){ f.type='text'; btn.textContent='非表示'; } else { f.type='password'; btn.textContent='表示'; }
    }
  </script>
</body>
</html>
