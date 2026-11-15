@extends('layouts.app')
@section('title', 'Editar pregunta')
@section('content')
<h1>Editar pregunta</h1>
<div class="card">
    <p class="muted">{{ \App\Support\Disciplines::name($q->discipline) }} · regla {{ $q->rule_ref }}</p>
    <div class="cite ok"><blockquote lang="en">“{{ $q->rule_quote }}”</blockquote></div>
    <form method="post" action="{{ route('admin.questions.update', $q) }}">
        @csrf @method('PUT')
        <label for="statement">Enunciado</label>
        <textarea id="statement" name="statement" required>{{ old('statement', $q->statement) }}</textarea>
        @error('statement')<p class="err">{{ $message }}</p>@enderror

        @if($q->isTest())
            <label>Opciones (marca la correcta)</label>
            @foreach($q->options as $i => $opt)
                <div style="display:flex;gap:.5rem;align-items:center;margin:.3rem 0">
                    <input type="radio" name="correct_index" value="{{ $i }}" style="width:auto" @checked((int) old('correct_index', $q->correct_index) === $i)>
                    <input name="options[{{ $i }}]" value="{{ old("options.$i", $opt) }}" required maxlength="400">
                </div>
            @endforeach
            @error('options.*')<p class="err">{{ $message }}</p>@enderror
        @else
            <label for="key_points">Puntos clave (uno por línea)</label>
            <textarea id="key_points" name="key_points" required>{{ old('key_points', implode("\n", $q->key_points ?? [])) }}</textarea>
            <label for="model_answer">Respuesta modelo</label>
            <textarea id="model_answer" name="model_answer" required>{{ old('model_answer', $q->model_answer) }}</textarea>
        @endif

        <label for="explanation">Explicación (se muestra al corregir)</label>
        <textarea id="explanation" name="explanation">{{ old('explanation', $q->explanation) }}</textarea>
        <p><button type="submit">Guardar</button> <a href="{{ route('admin.questions', ['status' => $q->status]) }}">Cancelar</a></p>
    </form>
</div>
@endsection
