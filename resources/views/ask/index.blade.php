@extends('layouts.app')
@section('title', 'Consultas')
@section('content')
<h1>Consulta al reglamento</h1>

<div class="card">
    <form method="post" action="{{ route('ask.store') }}">
        @csrf
        <label for="discipline">Disciplina</label>
        <select id="discipline" name="discipline">
            @foreach(\App\Support\Disciplines::ALL as $code => $name)
                <option value="{{ $code }}" @selected(old('discipline', $discipline) === $code)>{{ $name }}</option>
            @endforeach
        </select>
        <label for="question">Tu pregunta</label>
        <textarea id="question" name="question" maxlength="600" required placeholder="Por ejemplo: ¿Qué pasa si un competidor deja caer el arma durante el recorrido?">{{ old('question') }}</textarea>
        @error('question')<p class="err">{{ $message }}</p>@enderror
        @error('discipline')<p class="err">{{ $message }}</p>@enderror
        <p><button type="submit">Preguntar</button>
        <span class="muted"> Preguntas este mes: {{ $used }} de {{ $quota }}</span></p>
    </form>
</div>

@if($current)
<div class="card">
    <p class="muted">{{ \App\Support\Disciplines::name($current->discipline) }} · {{ $current->question }}</p>
    @if($current->status === 'ok')
        <span class="badge ok">Respuesta con citas verificadas</span>
    @elseif($current->status === 'unverified')
        <span class="badge warn">No se ha podido verificar ninguna cita: comprueba el reglamento</span>
    @elseif($current->status === 'no_info')
        <span class="badge warn">Sin información en el reglamento</span>
    @else
        <span class="badge bad">Error</span>
    @endif
    <p class="answer">{{ $current->answer }}</p>

    @foreach($current->citations ?? [] as $c)
        <div class="cite {{ $c['verified'] ? 'ok' : 'warn' }}">
            <strong>{{ $c['rule'] }}</strong>
            @if($c['verified'])<span class="badge ok">cita comprobada</span>@else<span class="badge warn">cita no comprobada</span>@endif
            <blockquote lang="en">“{{ $c['quote'] }}”</blockquote>
            @if(! empty($c['text']))
                <details><summary>Ver el texto oficial en inglés</summary>
                    <p class="muted">{{ $c['heading'] }}</p>
                    <p lang="en">{{ $c['text'] }}</p>
                </details>
            @endif
        </div>
    @endforeach
</div>
@endif

@if($history->count())
<div class="card">
    <h2>Tus últimas preguntas</h2>
    <ul class="hist">
        @foreach($history as $h)
            <li><a href="{{ route('ask', ['r' => $h->id]) }}">{{ \Illuminate\Support\Str::limit($h->question, 90) }}</a>
                <span class="muted">· {{ $h->discipline }} · {{ $h->created_at->format('d/m H:i') }}</span></li>
        @endforeach
    </ul>
</div>
@endif
@endsection
