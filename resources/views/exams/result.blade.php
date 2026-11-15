@extends('layouts.app')
@section('title', 'Resultado')
@section('content')
<h1>Resultado · {{ \App\Support\Disciplines::name($exam->discipline) }}</h1>
@if($admin)<p class="muted">Árbitro: {{ $exam->user->name }} ({{ $exam->user->email }})</p>@endif

<div class="card">
    @if($exam->status === 'graded')
        <p style="font-size:1.6rem;margin:0"><strong>{{ number_format($exam->score, 2, ',', '') }} / 10</strong>
            <span class="badge {{ $exam->score >= 5 ? 'ok' : 'bad' }}">{{ $exam->score >= 5 ? 'Aprobado' : 'Suspenso' }}</span></p>
    @else
        <p><span class="badge warn">Corrección pendiente</span> Las preguntas de test ya están corregidas, pero no se pudo corregir alguna pregunta abierta.</p>
        @unless($admin)
        <form method="post" action="{{ route('exams.regrade', $exam) }}">@csrf <button type="submit">Reintentar la corrección</button></form>
        @endunless
    @endif
</div>

@foreach($exam->answers as $a)
    @php($q = $a->question)
    <div class="card">
        <p><strong>{{ $a->position }}.</strong> {{ $q->statement }}
            @if($a->score !== null)
                <span class="badge {{ $a->isCorrect() ? 'ok' : ($a->score > 0 ? 'warn' : 'bad') }}">{{ rtrim(rtrim(number_format($a->score, 2, ',', ''), '0'), ',') ?: '0' }} / {{ rtrim(rtrim(number_format($a->max_score, 2, ',', ''), '0'), ',') }}</span>
            @else<span class="badge warn">sin corregir</span>@endif
        </p>

        @if($q->isTest())
            @foreach($q->options as $i => $opt)
                <p style="margin:.2rem 0" class="{{ $i === (int) $q->correct_index ? '' : 'muted' }}">
                    {{ $i === (int) $q->correct_index ? '✔' : ($a->selected_index !== null && $i === (int) $a->selected_index ? '✘' : '·') }}
                    {{ $opt }}
                    @if($a->selected_index !== null && $i === (int) $a->selected_index)<em>(tu respuesta)</em>@endif
                </p>
            @endforeach
        @else
            <p class="muted" style="margin-bottom:.2rem">Tu respuesta:</p>
            <p class="answer" style="margin-top:0">{{ $a->answer_text ?: '—' }}</p>
            <p class="muted" style="margin-bottom:.2rem">Respuesta modelo:</p>
            <p style="margin-top:0">{{ $q->model_answer }}</p>
        @endif

        @if($a->feedback)<p><em>{{ $a->feedback }}</em></p>@endif
        <div class="cite ok">
            <strong>Regla {{ $q->rule_ref }}</strong>
            <blockquote lang="en">“{{ $q->rule_quote }}”</blockquote>
        </div>

        @if($admin && ! $q->isTest())
            <form method="post" action="{{ route('admin.exams.override', [$exam, $a]) }}" class="row">
                @csrf @method('PATCH')
                <div><label>Nota (0 a {{ $a->max_score }})</label><input type="number" step="0.25" min="0" max="{{ $a->max_score }}" name="score" value="{{ $a->score }}" required></div>
                <div><label>Comentario</label><input name="feedback" maxlength="500" value="{{ $a->feedback }}"></div>
                <div style="align-self:end"><button type="submit">Cambiar nota</button></div>
            </form>
        @endif
    </div>
@endforeach
@endsection
