@extends('layouts.app')
@section('title', 'Entrar')
@section('content')
<div class="card" style="max-width:26rem;margin:2rem auto">
    <h1>Entrar</h1>
    <form method="post" action="{{ url('/entrar') }}">
        @csrf
        <label for="email">Correo</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        <label for="password">Contraseña</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
        @error('email')<p class="err">{{ $message }}</p>@enderror
        <p><label style="font-weight:400"><input type="checkbox" name="remember" value="1" style="width:auto"> Mantener la sesión</label></p>
        <button type="submit">Entrar</button>
    </form>
    <p class="muted">El acceso es solo por invitación del administrador.</p>
</div>
@endsection
