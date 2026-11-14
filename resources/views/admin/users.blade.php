@extends('layouts.app')
@section('title', 'Usuarios')
@section('content')
<h1>Usuarios</h1>

@if(session('invite_link'))
<div class="flash">
    <strong>Invitación creada para {{ session('invite_email') }}.</strong> Copia y envía este enlace (solo se muestra ahora):<br>
    <a href="{{ session('invite_link') }}">{{ session('invite_link') }}</a>
</div>
@endif

<div class="card">
    <h2>Invitar a una persona</h2>
    <form method="post" action="{{ route('admin.users.invite') }}" class="row">
        @csrf
        <div><label for="email">Correo</label><input id="email" name="email" type="email" value="{{ old('email') }}" required></div>
        <div><label for="role">Rol</label>
            <select id="role" name="role"><option value="arbitro">Árbitro</option><option value="admin">Administrador</option></select></div>
        <div style="align-self:end"><button type="submit">Crear invitación</button></div>
    </form>
    @error('email')<p class="err">{{ $message }}</p>@enderror
</div>

@if($pending->count())
<div class="card">
    <h2>Invitaciones pendientes</h2>
    <ul class="hist">
    @foreach($pending as $p)
        <li>{{ $p->email }} <span class="muted">· {{ $p->role }} · caduca {{ $p->expires_at->format('d/m/Y') }}</span>
            <form method="post" action="{{ route('admin.invitations.revoke', $p) }}" style="display:inline">@csrf @method('DELETE')
                <button class="link" type="submit">anular</button></form></li>
    @endforeach
    </ul>
</div>
@endif

<div class="card">
    <h2>Cuentas</h2>
    <p class="muted">Consultas de este mes en total: {{ $monthTotal }} de {{ $globalLimit }} (tope global).</p>
    <div class="tablewrap"><table>
        <tr><th>Nombre</th><th>Rol</th><th>Activo</th><th>Cuota/mes</th><th>Este mes</th><th></th></tr>
        @foreach($users as $u)
        @php($use = $usage[$u->id] ?? null)
        <tr>
            <td>{{ $u->name }}<br><span class="muted">{{ $u->email }}</span></td>
            <td><select name="role" form="u{{ $u->id }}"><option value="arbitro" @selected($u->role === 'arbitro')>Árbitro</option><option value="admin" @selected($u->role === 'admin')>Admin</option></select></td>
            <td><input type="checkbox" name="is_active" value="1" form="u{{ $u->id }}" style="width:auto" @checked($u->is_active)></td>
            <td><input type="number" name="monthly_quota" form="u{{ $u->id }}" min="0" max="5000" value="{{ $u->monthly_quota }}"></td>
            <td>{{ $use->questions ?? 0 }}<br><span class="muted">{{ number_format(($use->tin ?? 0) + ($use->tout ?? 0), 0, ',', '.') }} tokens</span></td>
            <td><button type="submit" form="u{{ $u->id }}">Guardar</button></td>
        </tr>
        @endforeach
    </table></div>
    @foreach($users as $u)
        <form id="u{{ $u->id }}" method="post" action="{{ route('admin.users.update', $u) }}" hidden>@csrf @method('PATCH')</form>
    @endforeach
</div>
@endsection
