@extends('layouts.app')
@section('title', 'Examen')
@section('content')
<h1>Examen · {{ \App\Support\Disciplines::name($exam->discipline) }}</h1>
<p class="muted">{{ $exam->answers->count() }} preguntas. Puedes guardar y continuar más tarde; al entregar se corrige y ya no se puede cambiar.</p>

<form method="post" action="{{ route('exams.submit', $exam) }}">
    @csrf
    @foreach($exam->answers as $a)
        @php($q = $a->question)
        <div class="card">
            <p><strong>{{ $a->position }}.</strong> {{ $q->statement }}</p>
            @if($q->isTest())
                @foreach($q->options as $i => $opt)
                    <label style="font-weight:400"><input type="radio" name="a[{{ $a->id }}]" value="{{ $i }}" style="width:auto" @checked((string) $a->selected_index === (string) $i)> {{ $opt }}</label>
                @endforeach
            @else
                <textarea name="a[{{ $a->id }}]" maxlength="3000" placeholder="Escribe tu respuesta">{{ $a->answer_text }}</textarea>
            @endif
        </div>
    @endforeach
    <p>
        <button type="submit" name="action" value="save" class="btn" style="background:var(--muted)">Guardar y seguir luego</button>
        <button type="submit" name="action" value="submit" onclick="return confirm('¿Entregar el examen? No podrás cambiar las respuestas.')">Entregar</button>
    </p>
</form>
@endsection
