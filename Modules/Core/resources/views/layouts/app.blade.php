@php
    $rtl = \Modules\Core\Http\Middleware\SetLocale::isRtl();
    $menu = app(\Modules\Core\Menu\Menu::class)->for(auth()->user());
@endphp
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
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">
    <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="{{ __('core::ui.toggle_menu') }}">
                        <i class="bi bi-list"></i>
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <form method="POST" action="{{ route('core.locale.update') }}">
                        @csrf
                        <input type="hidden" name="locale" value="{{ $rtl ? 'en' : 'ar' }}">
                        <button type="submit" class="nav-link btn btn-link">{{ $rtl ? 'English' : 'العربية' }}</button>
                    </form>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button">
                        <i class="bi bi-person-circle"></i> {{ auth()->user()->name }}
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <form method="POST" action="{{ route('core.logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right"></i> {{ __('core::auth.logout') }}</button>
                        </form>
                    </div>
                </li>
            </ul>
        </div>
    </nav>

    <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <div class="sidebar-brand">
            <a href="{{ route('core.dashboard') }}" class="brand-link">
                <span class="brand-text fw-light">{{ $companyName ?? config('app.name') }}</span>
            </a>
        </div>
        <div class="sidebar-wrapper">
            <nav class="mt-2">
                <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation" data-accordion="false">
                    <li class="nav-item">
                        <a href="{{ route('core.dashboard') }}" @class(['nav-link', 'active' => request()->routeIs('core.dashboard')])>
                            <i class="nav-icon bi bi-speedometer2"></i>
                            <p>{{ __('core::menu.dashboard') }}</p>
                        </a>
                    </li>
                    @foreach ($menu as $group)
                        @php $groupActive = collect($group['items'])->contains(fn ($item) => request()->routeIs($item->route.'*')); @endphp
                        <li @class(['nav-item', 'menu-open' => $groupActive])>
                            <a href="#" @class(['nav-link', 'active' => $groupActive])>
                                <i class="nav-icon bi {{ $group['icon'] }}"></i>
                                <p>{{ __($group['label']) }} <i class="nav-arrow bi bi-chevron-right"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                @foreach ($group['items'] as $item)
                                    <li class="nav-item">
                                        <a href="{{ route($item->route) }}" @class(['nav-link', 'active' => request()->routeIs($item->route.'*')])>
                                            <i class="nav-icon bi {{ $item->icon }}"></i>
                                            <p>{{ __($item->label) }}</p>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </div>
    </aside>

    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                <h1>{{ $title ?? '' }}</h1>
            </div>
        </div>
        <div class="app-content">
            <div class="container-fluid">
                @if (session('status'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                {{ $slot }}
            </div>
        </div>
    </main>
</div>
@livewireScripts
</body>
</html>
