@extends('layouts.app')
@section('title', 'Exámenes')
@section('content')
<h1>Exámenes</h1>

<div class="card">
    <h2>Empezar un examen</h2>
    <form method="post" action="{{ route('exams.start') }}">
        @csrf
        <div class="row">
            <div><label for="discipline">Disciplina</label>
                <select id="discipline" name="discipline">
                    @foreach(\App\Support\Disciplines::ALL as $code => $name)
                        @php($n = $available->where('discipline', $code)->sum('n'))
                        <option value="{{ $code }}" @selected(old('discipline', $discipline) === $code)>{{ $name }} ({{ $n }} preguntas)</option>
                    @endforeach
                </select></div>
            <div><label for="total">Número de preguntas</label>
                <input id="total" name="total" type="number" min="5" max="30" value="{{ old('total', 10) }}"></div>
        </div>
        <p><label style="font-weight:400"><input type="checkbox" name="include_open" value="1" style="width:auto" @checked(old('include_open'))> Incluir algunas preguntas abiertas (las corrige la IA)</label></p>
        @error('total')<p class="err">{{ $message }}</p>@enderror
        <button type="submit">Empezar</button>
        <span class="muted"> Exámenes este mes: {{ $usedThisMonth }} de {{ $monthlyLimit }}</span>
    </form>
</div>

<div class="card">
    <h2>Tus exámenes</h2>
    @forelse($exams as $e)
        <p style="margin:.3rem 0">
            <a href="{{ route('exams.show', $e) }}">{{ \App\Support\Disciplines::name($e->discipline) }} · {{ $e->started_at->format('d/m/Y H:i') }}</a>
            @if($e->status === 'graded')<span class="badge {{ $e->score >= 5 ? 'ok' : 'bad' }}">{{ number_format($e->score, 2, ',', '') }} / 10</span>
            @elseif($e->status === 'submitted')<span class="badge warn">pendiente de corregir</span>
            @else<span class="badge warn">en curso</span>@endif
        </p>
    @empty
        <p class="muted">Todavía no has hecho ningún examen.</p>
    @endforelse
</div>
@endsection
