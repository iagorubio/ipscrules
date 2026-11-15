@extends('layouts.app')
@section('title', 'Discrepancias')
@section('content')
<h1>Discrepancias de los árbitros</h1>
<p class="muted">Cuando confirmas que la IA se equivocó y pones la regla correcta, el caso entra en el conjunto de control
    (<code>php artisan reglamento:evaluar --discrepancias</code>) y puedes descargar los casos confirmados para compartirlos.
    <a href="{{ route('admin.disputes.export') }}">Descargar confirmadas (.jsonl)</a></p>
<p>
    @foreach(\App\Models\Dispute::STATUSES as $k => $label)
        <a href="{{ route('admin.disputes', ['status' => $k]) }}" @if($status === $k) style="font-weight:700" @endif>{{ $label }} ({{ $counts[$k] ?? 0 }})</a>@unless($loop->last) · @endunless
    @endforeach
</p>

@forelse($disputes as $d)
@php($log = $d->askLog)
<div class="card">
    <p class="muted">{{ $d->created_at->format('d/m/Y H:i') }} · {{ $d->user->name }} ({{ $d->user->email }}) · {{ \App\Support\Disciplines::name($log->discipline) }}
        @if($d->mailed_at)· correo enviado @endif</p>
    <p><strong>{{ $log->question }}</strong></p>
    <details><summary class="muted">Respuesta de la IA ({{ $log->status }})</summary>
        <p class="answer">{{ $log->answer }}</p>
        @foreach($log->citations ?? [] as $c)
            <p class="muted" style="margin:.2rem 0">{{ $c['verified'] ? '✔' : '✘' }} {{ $c['rule'] }}: “{{ \Illuminate\Support\Str::limit($c['quote'], 140) }}”</p>
        @endforeach
    </details>
    <div class="cite warn">
        <strong>Corrección del árbitro</strong>
        <p style="margin:.2rem 0" class="answer">{{ $d->comment }}</p>
        @if($d->suggested_rule)<p class="muted" style="margin:0">Propone la regla: {{ $d->suggested_rule }}</p>@endif
    </div>

    <form method="post" action="{{ route('admin.disputes.update', $d) }}" class="row">
        @csrf @method('PATCH')
        <div><label>Resultado</label>
            <select name="status">@foreach(\App\Models\Dispute::STATUSES as $k => $label)<option value="{{ $k }}" @selected($d->status === $k)>{{ $label }}</option>@endforeach</select></div>
        <div><label>Regla(s) correcta(s)</label><input name="correct_rule" maxlength="80" value="{{ $d->correct_rule ?? $d->suggested_rule }}" placeholder="10.4.9"></div>
        <div><label>Nota (la ve el árbitro)</label><input name="admin_note" maxlength="1000" value="{{ $d->admin_note }}"></div>
        <div style="align-self:end"><button type="submit">Guardar</button></div>
    </form>
    @error('correct_rule')<p class="err">{{ $message }}</p>@enderror
</div>
@empty
<div class="card"><p class="muted">No hay discrepancias en este estado.</p></div>
@endforelse

{{ $disputes->links() }}
@endsection
