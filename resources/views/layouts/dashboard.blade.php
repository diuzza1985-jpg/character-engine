<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title', 'Character Engine')</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
@include('layouts.partials.wizard-styles')
@livewireScripts
</head>
<body>

<div class="page-bg">
  <div class="blob blob1"></div>
  <div class="blob blob2"></div>
</div>

<div class="layout">

    <aside class="sidebar">
        <div class="brand">
            <div class="brand-name">CHARACTER <span class="e">ENGINE</span></div>
            <div class="brand-tag">AI content · personaggi coerenti</div>
        </div>

        <nav style="display:flex; flex-direction:column; gap:4px; margin-top:10px;">
            <a href="{{ route('character.panel') }}" class="nav-link @if(request()->routeIs('character.panel') || request()->routeIs('character.show')) active @endif">
                🧑‍🎤 Personaggi
            </a>
            <a href="{{ route('credits.index') }}" class="nav-link @if(request()->routeIs('credits.index')) active @endif">
                🪙 Acquista crediti
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-email">{{ auth()->user()->email }}</div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-ghost" style="width:100%; text-align:left; padding-left:14px;">↪ Esci</button>
            </form>
        </div>
    </aside>

    <main>
    <div class="content" style="max-width:640px;">
        @yield('content')
    </div>
    </main>
</div>

</body>
</html>
