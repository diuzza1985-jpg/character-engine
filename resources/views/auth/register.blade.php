@extends('layouts.public')

@section('title', 'Registrati — Character Engine')

@section('content')
<div class="card">
    <p class="eyebrow">Ultimo passo</p>
    <h1>Crea il tuo account</h1>
    <p class="subtitle">Il tuo personaggio è già definito — questi dati servono solo a conservarlo e a farti tornare quando vuoi.</p>

    <form method="POST" action="{{ route('register.store') }}">
        @csrf

        <div class="field-label">Il tuo nome (o il nome del tuo brand)</div>
        <input type="text" name="name" class="text-input" value="{{ old('name') }}" required maxlength="255">
        @error('name') <p class="field-hint" style="color:#F58E4E;">{{ $message }}</p> @enderror

        <div class="field-label" style="margin-top:24px;">Email</div>
        <input type="email" name="email" class="text-input" value="{{ old('email') }}" required maxlength="255">
        @error('email') <p class="field-hint" style="color:#F58E4E;">{{ $message }}</p> @enderror

        <div class="field-label" style="margin-top:24px;">Password</div>
        <input type="password" name="password" class="text-input" required minlength="8">
        @error('password') <p class="field-hint" style="color:#F58E4E;">{{ $message }}</p> @enderror

        <div class="field-label" style="margin-top:24px;">Conferma password</div>
        <input type="password" name="password_confirmation" class="text-input" required minlength="8">

        <div class="actions">
            <a href="{{ route('character.create') }}" class="btn-ghost">← Torna al riepilogo</a>
            <button type="submit" class="btn-primary">Crea account →</button>
        </div>
    </form>
</div>
@endsection
