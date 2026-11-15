@extends('layouts.app')
@section('title', 'Banco de preguntas')
@section('content')
<h1>Banco de preguntas</h1>

<div class="card">
    <h2>Generar preguntas con IA</h2>
    <p class="muted">Se crean en borrador a partir de reglas reales; cada una guarda su regla y una cita literal que se comprueba. Revísalas antes de aprobarlas.</p>
    <form method="post" action="{{ route('admin.questions.generate') }}">
        @csrf
        <div class="row">
            <div><label for="g-discipline">Disciplina</label>
                <select id="g-discipline" name="discipline">@foreach(\App\Support\Disciplines::ALL as $c => $n)<option value="{{ $c }}" @selected(old('discipline') === $c)>{{ $n }}</option>@endforeach</select></div>
            <div><label for="g-section">Sección (opcional, p. ej. 10 o 10.5)</label><input id="g-section" name="section" value="{{ old('section') }}" maxlength="12"></div>
            <div><label for="g-count">Cuántas (1–10)</label><input id="g-count" name="count" type="number" min="1" max="10" value="{{ old('count', 5) }}"></div>
        </div>
        <div class="row">
            <div><label for="g-type">Tipo</label>
                <select id="g-type" name="type">@foreach(\App\Models\ExamQuestion::TYPES as $k => $v)<option value="{{ $k }}" @selected(old('type') === $k)>{{ $v }}</option>@endforeach</select></div>
            <div><label for="g-diff">Dificultad</label>
                <select id="g-diff" name="difficulty">@foreach(\App\Models\ExamQuestion::DIFFICULTIES as $k => $v)<option value="{{ $k }}" @selected(old('difficulty', 'media') === $k)>{{ $v }}</option>@endforeach</select></div>
            <div style="align-self:end"><button type="submit">Generar</button></div>
        </div>
        @error('count')<p class="err">{{ $message }}</p>@enderror
        @error('section')<p class="err">La sección debe ser un número como 10 o 10.5.</p>@enderror
    </form>
</div>

<p>
    @foreach(['draft' => 'Borradores', 'approved' => 'Aprobadas', 'rejected' => 'Rechazadas'] as $k => $label)
        <a href="{{ route('admin.questions', ['status' => $k]) }}" @if($status === $k) style="font-weight:700" @endif>{{ $label }} ({{ $counts[$k] ?? 0 }})</a>@unless($loop->last) · @endunless
    @endforeach
</p>

@forelse($questions as $q)
    <div class="card">
        <p class="muted">{{ \App\Support\Disciplines::name($q->discipline) }} · {{ \App\Models\ExamQuestion::TYPES[$q->type] }} · {{ \App\Models\ExamQuestion::DIFFICULTIES[$q->difficulty] ?? $q->difficulty }} · regla {{ $q->rule_ref }}</p>
        <p><strong>{{ $q->statement }}</strong></p>
        @if($q->isTest())
            @foreach($q->options as $i => $opt)<p style="margin:.15rem 0">{{ $i === (int) $q->correct_index ? '✔' : '·' }} {{ $opt }}</p>@endforeach
        @else
            <p class="muted" style="margin-bottom:.1rem">Puntos clave:</p>
            <ul style="margin-top:0">@foreach($q->key_points as $kp)<li>{{ $kp }}</li>@endforeach</ul>
            <p>{{ $q->model_answer }}</p>
        @endif
        <div class="cite ok"><blockquote lang="en">“{{ $q->rule_quote }}”</blockquote></div>
        <div class="row" style="align-items:center">
            <a href="{{ route('admin.questions.edit', $q) }}">Editar</a>
            @if($q->status !== 'approved')
                <form method="post" action="{{ route('admin.questions.status', $q) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="approved"><button type="submit">Aprobar</button></form>
            @endif
            @if($q->status !== 'rejected')
                <form method="post" action="{{ route('admin.questions.status', $q) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="rejected"><button type="submit" class="danger">Rechazar</button></form>
            @endif
            <form method="post" action="{{ route('admin.questions.destroy', $q) }}" onsubmit="return confirm('¿Eliminar para siempre?')">@csrf @method('DELETE')<button class="link" type="submit">Eliminar</button></form>
        </div>
    </div>
@empty
    <div class="card"><p class="muted">No hay preguntas en este estado.</p></div>
@endforelse
@endsection
