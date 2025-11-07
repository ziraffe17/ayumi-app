<!doctype html><html lang="ja"><head>
  <meta charset="utf-8">
  <title>新パスワード設定</title>
</head>
<body>
  <h1>新パスワード設定</h1>

  @if ($errors->any())
    <div style="color:red;">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ route('password.update') }}">
    @csrf
    <input type="hidden" name="token" value="{{ request()->route('token') }}">

    <label>メール</label>
    <input type="email" name="email" value="{{ old('email', request('email')) }}" required>

    <label>新パスワード</label>
    <input type="password" name="password" required>

    <label>確認</label>
    <input type="password" name="password_confirmation" required>

    <button type="submit">更新</button>
  </form>
</body></html>
