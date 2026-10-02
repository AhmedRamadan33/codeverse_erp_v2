@php($rtl = \Modules\Core\Http\Middleware\SetLocale::isRtl())
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' | ' : '' }}{{ $companyName ?? config('app.name') }}</title>
    @vite([$rtl ? 'resources/css/app-rtl.css' : 'resources/css/app-ltr.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="login-page bg-body-secondary d-flex align-items-center justify-content-center">
<div class="login-box">
    <div class="text-center mb-3">
        <h2 class="fw-light">{{ $companyName ?? config('app.name') }}</h2>
    </div>
    {{ $slot }}
    <form method="POST" action="{{ route('core.locale.update') }}" class="text-center mt-3">
        @csrf
        <input type="hidden" name="locale" value="{{ $rtl ? 'en' : 'ar' }}">
        <button type="submit" class="btn btn-link">{{ $rtl ? 'English' : 'العربية' }}</button>
    </form>
</div>
@livewireScripts
</body>
</html>
