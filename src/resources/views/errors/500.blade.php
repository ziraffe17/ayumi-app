<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 サーバーエラー - あゆみ</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Hiragino Sans', 'Yu Gothic UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            padding: 20px;
        }
        .container {
            text-align: center;
            max-width: 600px;
        }
        .error-code {
            font-size: 120px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 20px;
            opacity: 0.9;
        }
        h1 {
            font-size: 32px;
            margin-bottom: 16px;
            font-weight: 600;
        }
        p {
            font-size: 18px;
            margin-bottom: 32px;
            opacity: 0.9;
            line-height: 1.6;
        }
        .btn {
            display: inline-block;
            background: #fff;
            color: #f5576c;
            padding: 14px 32px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin: 0 8px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
        }
        .logo {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 40px;
            opacity: 0.95;
        }
        .help-text {
            font-size: 14px;
            margin-top: 24px;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">あゆみ</div>
        <div class="error-code">500</div>
        <h1>サーバーエラーが発生しました</h1>
        <p>
            申し訳ございません。サーバー側で問題が発生しました。<br>
            しばらく時間をおいてから再度お試しください。<br>
            問題が解決しない場合は、システム管理者にお問い合わせください。
        </p>
        <div>
            <a href="javascript:location.reload()" class="btn btn-secondary">再読み込み</a>
            <a href="{{ url('/') }}" class="btn">ホームに戻る</a>
        </div>
        <div class="help-text">
            エラーは自動的にログに記録されています
        </div>
    </div>
</body>
</html>
