<!-- ========================================
     resources/views/layouts/user.blade.php
     利用者用メインレイアウト
     ======================================== -->
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'あゆみ')</title>
    @include('layouts.partials.user-styles')
    @yield('styles')
</head>
<body>
    @include('layouts.partials.user-header')

    @include('layouts.partials.user-tabs')

    <div class="main-content">
        @if(session('success'))
        <div class="alert success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
        <div class="alert warning">{{ session('error') }}</div>
        @endif

        @yield('content')
    </div>

    @include('layouts.partials.user-scripts')
    @yield('scripts')
</body>
</html>