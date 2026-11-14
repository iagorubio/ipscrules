@extends('layouts.app')
@section('title', 'Invitación')
@section('content')
<div class="card" style="max-width:28rem;margin:2rem auto">
@if(! $invitation)
    <h1>Invitación no válida</h1>
    <p>Este enlace ha caducado, ya se usó o fue anulado. Pide una invitación nueva al administrador.</p>
@else
    <h1>Crear tu cuenta</h1>
    <p class="muted">Invitación para <strong>{{ $invitation->email }}</strong></p>
    <form method="post" action="{{ route('invitation.accept', $token) }}">
        @csrf
        <label for="name">Nombre y apellidos</label>
        <input id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
        @error('name')<p class="err">{{ $message }}</p>@enderror
        <label for="password">Contraseña (mínimo 10 caracteres)</label>
        <input id="password" name="password" type="password" required autocomplete="new-password">
        @error('password')<p class="err">{{ $message }}</p>@enderror
        <label for="password_confirmation">Repite la contraseña</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
        <p><button type="submit">Crear cuenta</button></p>
    </form>
@endif
</div>
@endsection
