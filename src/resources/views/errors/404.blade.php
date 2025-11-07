<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 ページが見つかりません - あゆみ</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Hiragino Sans', 'Yu Gothic UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            color: #667eea;
            padding: 14px 32px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        .logo {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 40px;
            opacity: 0.95;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">あゆみ</div>
        <div class="error-code">404</div>
        <h1>ページが見つかりません</h1>
        <p>
            お探しのページは存在しないか、移動または削除された可能性があります。<br>
            URLをご確認の上、再度お試しください。
        </p>
        <a href="{{ url('/') }}" class="btn">ホームに戻る</a>
    </div>
</body>
</html>
