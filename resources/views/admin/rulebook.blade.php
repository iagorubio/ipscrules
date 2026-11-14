@extends('layouts.app')
@section('title', 'Reglamento')
@section('content')
<h1>Reglamento cargado</h1>
<div class="card">
@if(! $book)
    <p>Todavía no hay reglamento. Cárgalo desde el servidor con:</p>
    <pre>php artisan reglamento:importar out</pre>
@else
    <p><strong>{{ $book->edition }}</strong> (solo inglés, texto oficial) · importado {{ $book->imported_at?->format('d/m/Y H:i') }}</p>
    <p class="muted">{{ $counts['rules'] }} reglas · {{ $counts['glossary'] }} términos del glosario · {{ $counts['appendices'] }} apéndices · {{ $counts['indexed'] }} fragmentos indexados</p>
    <h2>Reglas que aplican a cada disciplina</h2>
    <div class="tablewrap"><table>
        <tr><th>Disciplina</th><th>Reglas</th></tr>
        @foreach(\App\Support\Disciplines::ALL as $code => $name)
            <tr><td>{{ $name }}</td><td>{{ $coverage[$code] ?? 0 }}</td></tr>
        @endforeach
    </table></div>
    <p class="muted">Para actualizar el reglamento, genera de nuevo los JSON con <code>ingest/ingest.php</code> y ejecuta <code>php artisan reglamento:importar out</code> en el servidor.</p>
@endif
</div>
@endsection
