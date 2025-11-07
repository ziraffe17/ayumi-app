<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>二段階認証（メールコード）</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root { --fg:#111; --muted:#666; --bg:#fafafa; --card:#fff; --border:#e5e7eb; --ok:#16a34a; --err:#dc2626; --pri:#2563eb; }
    * { box-sizing: border-box; }
    body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans JP", sans-serif; color:var(--fg); background:var(--bg); }
    .wrap { max-width: 480px; margin: 48px auto; padding: 0 16px; }
    .card { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:24px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
    h1 { font-size:18px; margin:0 0 16px; }
    .muted { color:var(--muted); font-size: 13px; margin-bottom: 16px;}
    label { display:block; font-size:14px; margin-bottom:8px; }
    input[type="text"] { width:100%; padding:12px; border:1px solid var(--border); border-radius:10px; font-size:16px; }
    .actions { display:flex; gap:8px; margin-top:16px; align-items:center; flex-wrap:wrap; }
    .btn { border:1px solid var(--border); background:#fff; padding:10px 14px; border-radius:10px; cursor:pointer; font-size:14px; }
    .btn-primary { background:var(--pri); color:#fff; border-color:var(--pri); }
    .btn-ghost { background:transparent; color:var(--muted); border:none; }
    .alert { padding:10px 12px; border-radius:10px; margin-bottom:12px; font-size:14px; }
    .alert-ok { background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; }
    .alert-err { background:#fef2f2; color:#7f1d1d; border:1px solid #fecaca; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>二段階認証（メールコード）</h1>
      @if (!empty($locked))
        <div class="alert alert-err">現在ロック中です。しばらくしてから再度お試しください。</div>
      @endif

      <p class="muted">届いた6桁コードを入力してください。コードは数分で期限切れになります。</p>

      @if (session('status'))
        <div class="alert alert-ok">{{ session('status') }}</div>
      @endif

      @if ($errors->has('code'))
        <div class="alert alert-err">{{ $errors->first('code') }}</div>
      @endif

      <form method="POST" action="{{ route('staff.2fa.email.verify') }}">
        @csrf
        <label for="code">6桁コード</label>
        <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]*"
              maxlength="6" autocomplete="one-time-code" required autofocus>
        
        <label style="display:flex;gap:8px;align-items:center;margin-top:10px;font-size:14px;">
          <input type="checkbox" name="remember_device" value="1">
          この端末を30日間スキップする
        </label>

        <div class="actions">
          {{-- 検証（バリデーションあり） --}}
          <button class="btn btn-primary" type="submit">認証する</button>

          {{-- ★再送（入力チェックをスキップ） --}}
          <button class="btn" type="submit"
                  formaction="{{ route('staff.2fa.email.resend') }}"
                  formmethod="POST"
                  formnovalidate>
            コードを再送
          </button>

          {{-- ★キャンセル（入力チェックをスキップ） --}}
          <button class="btn btn-ghost" type="submit"
                  formaction="{{ route('staff.2fa.email.cancel') }}"
                  formmethod="POST"
                  formnovalidate>
            キャンセル（ログアウト）
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // 6桁に限定（数字以外を除去）
    const code = document.getElementById('code');
    code?.addEventListener('input', () => {
      code.value = code.value.replace(/\D/g, '').slice(0, 6);
    });
  </script>
</body>
</html>
