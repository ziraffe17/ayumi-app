<!doctype html><html lang="ja"><head><meta charset="utf-8"><title>職員PWリセット</title></head>
<body>
  <h1>パスワードリセット</h1>
  @if (session('status')) <div style="color:green;">{{ session('status') }}</div> @endif
  @if ($errors->any()) <div style="color:red;">{{ $errors->first() }}</div> @endif
  <form method="POST" action="{{ route('password.email') }}"> @csrf
    <label>メール</label><input type="email" name="email" required autofocus>
    <button type="submit">リセットリンク送信</button>
  </form>
</body></html>
