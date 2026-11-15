@extends('layouts.app')
@section('title', 'Registro de consultas')
@section('content')
<h1>Registro de consultas</h1>
<p class="muted">Últimos 30 días:
    @foreach(['ok' => 'correctas', 'no_info' => 'sin información', 'unverified' => 'sin cita verificada', 'error' => 'con error'] as $k => $label)
        {{ $counts[$k] ?? 0 }} {{ $label }}@unless($loop->last) · @endunless
    @endforeach
</p>
<p>
    @foreach($statuses as $k => $label)
        <a href="{{ route('admin.asklogs', ['status' => $k]) }}" @if($status === $k) style="font-weight:700" @endif>{{ $label }}</a>@unless($loop->last) · @endunless
    @endforeach
</p>

@forelse($logs as $l)
<div class="card">
    <p class="muted">{{ $l->created_at->format('d/m/Y H:i') }} · {{ $l->user->name }} · {{ \App\Support\Disciplines::name($l->discipline) }}
        <span class="badge {{ $l->status === 'ok' ? 'ok' : ($l->status === 'error' ? 'bad' : 'warn') }}">{{ $statuses[$l->status] ?? $l->status }}</span></p>
    <p><strong>{{ $l->question }}</strong></p>
    <details><summary class="muted">Ver respuesta y citas</summary>
        <p class="answer">{{ $l->answer }}</p>
        @foreach($l->citations ?? [] as $c)
            <p class="muted" style="margin:.2rem 0">{{ $c['verified'] ? '✔' : '✘' }} {{ $c['rule'] }}: “{{ \Illuminate\Support\Str::limit($c['quote'], 140) }}”</p>
        @endforeach
    </details>
</div>
@empty
<div class="card"><p class="muted">No hay consultas en este estado.</p></div>
@endforelse

{{ $logs->links() }}
@endsection
