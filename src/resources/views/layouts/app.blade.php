{{-- resources/views/user/auth/login.blade.php --}}
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>あゆみ - 利用者ログイン</title>
    <style>
        * { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
        }
        
        body {
            font-family: 'Hiragino Sans', 'Yu Gothic UI', system-ui, sans-serif;
            background: #f9fafb;
            color: #111827;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 40px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo h1 {
            font-size: 32px;
            color: #16a34a;
            text-decoration: underline;
            margin-bottom: 8px;
        }

        .logo p {
            color: #6b7280;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: #16a34a;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
        }

        .form-control.is-invalid {
            border-color: #dc2626;
        }

        .btn-primary {
            width: 100%;
            background: #16a34a;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .btn-primary:hover {
            background: #15803d;
        }

        .btn-primary:active {
            background: #14532d;
        }

        .forgot-password {
            text-align: center;
            margin-top: 20px;
        }

        .forgot-password a {
            color: #16a34a;
            text-decoration: none;
            font-size: 14px;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        .help-text {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }

        .error-message {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            color: #dc2626;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .invalid-feedback {
            color: #dc2626;
            font-size: 12px;
            margin-top: 4px;
        }

        @media (max-width: 480px) {
            .login-container {
                margin: 20px;
                padding: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>あゆみ</h1>
            <p>福祉事業所管理システム</p>
        </div>

        @if(session('error'))
        <div class="error-message">
            {{ session('error') }}
        </div>
        @endif

        @if($errors->any())
        <div class="error-message">
            @foreach($errors->all() as $error)
                {{ $error }}<br>
            @endforeach
        </div>
        @endif

        <form method="POST" action="{{ route('user.login') }}">
            @csrf
            
            <div class="form-group">
                <label for="login_code">ログインID</label>
                <input 
                    type="text" 
                    class="form-control @error('login_code') is-invalid @enderror" 
                    id="login_code" 
                    name="login_code"
                    value="{{ old('login_code') }}"
                    placeholder="例: u0001" 
                    required
                    autocomplete="username"
                    autofocus
                >
                <div class="help-text">施設から発行されたログインIDを入力してください</div>
                @error('login_code')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="password">パスワード</label>
                <input 
                    type="password" 
                    class="form-control @error('password') is-invalid @enderror" 
                    id="password" 
                    name="password"
                    required
                    autocomplete="current-password"
                >
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn-primary">ログイン</button>
        </form>

        <div class="forgot-password">
            <a href="#" onclick="alert('パスワードリセット機能は職員にお問い合わせください。'); return false;">
                パスワードを忘れた方はこちら
            </a>
        </div>
    </div>
</body>
</html>