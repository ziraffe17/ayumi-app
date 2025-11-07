<!-- ========================================
     resources/views/layouts/staff.blade.php
     職員用メインレイアウト
     ======================================== -->
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'あゆみ - 職員')</title>
    @include('layouts.partials.staff-styles')
</head>
<body>
    @include('layouts.partials.staff-header')
    @yield('styles')

    <div class="frame">
        @include('layouts.partials.staff-sidebar')

       <main class="main @yield('main_class')">
            @include('layouts.partials.staff-tabs')

            @if(session('success'))
            <div class="alert success">{{ session('success') }}</div>
            @endif

            @if(session('error'))
            <div class="alert warning">{{ session('error') }}</div>
            @endif

            @if(session('status'))
            <div class="alert info">{{ session('status') }}</div>
            @endif

            @yield('content')
        </main>
    </div>

    @include('layouts.partials.staff-scripts')
    @yield('scripts')
</body>
</html>