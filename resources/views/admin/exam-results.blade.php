@extends('layouts.app')
@section('title', 'Resultados')
@section('content')
<h1>Resultados de exámenes</h1>

<div class="card">
    <h2>Reglas en las que más se falla</h2>
    <p class="muted">Solo reglas con al menos 3 respuestas corregidas.</p>
    @if($stats->isEmpty())
        <p class="muted">Todavía no hay datos suficientes.</p>
    @else
    <div class="tablewrap"><table>
        <tr><th>Regla</th><th>Disciplina</th><th>Respuestas</th><th>Fallos</th></tr>
        @foreach($stats as $s)
            <tr><td>{{ $s->rule_ref }}</td><td>{{ \App\Support\Disciplines::name($s->discipline) }}</td><td>{{ $s->total }}</td>
                <td>{{ $s->fails }} ({{ round($s->fails / $s->total * 100) }} %)</td></tr>
        @endforeach
    </table></div>
    @endif
</div>

<div class="card">
    <h2>Exámenes entregados</h2>
    <div class="tablewrap"><table>
        <tr><th>Árbitro</th><th>Disciplina</th><th>Entregado</th><th>Nota</th></tr>
        @forelse($exams as $e)
            <tr><td><a href="{{ route('admin.exams.show', $e) }}">{{ $e->user->name }}</a></td>
                <td>{{ \App\Support\Disciplines::name($e->discipline) }}</td>
                <td>{{ $e->submitted_at?->format('d/m/Y H:i') }}</td>
                <td>@if($e->status === 'graded'){{ number_format($e->score, 2, ',', '') }}@else<span class="badge warn">pendiente</span>@endif</td></tr>
        @empty
            <tr><td colspan="4" class="muted">Todavía no hay exámenes entregados.</td></tr>
        @endforelse
    </table></div>
</div>
@endsection
