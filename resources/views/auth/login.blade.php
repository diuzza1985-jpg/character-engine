@extends('layouts.public')

@section('title', 'Accedi — Character Engine')

@section('content')
<div class="card">
    <p class="eyebrow">Bentornato</p>
    <h1>Accedi</h1>
    <p class="subtitle">Torna al tuo personaggio quando vuoi.</p>

    @if(session('save_requires_auth'))
        <div class="serious-note">
            <span>🔒</span>
            <div>Per salvare il personaggio serve un account: accedi con quello che hai già, oppure registrane uno nuovo.</div>
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}">
        @csrf

        <div class="field-label">Email</div>
        <input type="email" name="email" class="text-input" value="{{ old('email') }}" required>
        @error('email') <p class="field-hint" style="color:#F58E4E;">{{ $message }}</p> @enderror

        <div class="field-label" style="margin-top:24px;">Password</div>
        <input type="password" name="password" class="text-input" required>

        <div class="actions">
            <span></span>
            <button type="submit" class="btn-primary">Accedi →</button>
        </div>
    </form>

    <p class="note">Non hai ancora un account? <a href="{{ route('register') }}" style="color:#B39BFF;">Registrati</a></p>
</div>
@endsection
