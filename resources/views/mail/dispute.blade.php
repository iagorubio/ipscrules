<!doctype html>
<html lang="es">
<body style="font-family:system-ui,-apple-system,Segoe UI,sans-serif;line-height:1.5;color:#1c2230;max-width:40rem">
@php($log = $dispute->askLog)
<p><strong>{{ $dispute->user->name }}</strong> ({{ $dispute->user->email }}) no está de acuerdo con una respuesta. Puedes contestarle directamente a este correo.</p>
<p style="color:#5d6679">Disciplina: {{ \App\Support\Disciplines::name($log->discipline) }} · Consulta del {{ $log->created_at->timezone('Europe/Madrid')->format('d/m/Y H:i') }}</p>

<h3 style="margin-bottom:.2rem">Pregunta</h3>
<p style="margin-top:0">{{ $log->question }}</p>

<h3 style="margin-bottom:.2rem">Respuesta de la IA <span style="font-weight:400;color:#5d6679">({{ $log->status }})</span></h3>
<p style="margin-top:0;white-space:pre-wrap">{{ $log->answer }}</p>
@foreach($log->citations ?? [] as $c)
    <p style="margin:.2rem 0;color:#5d6679">{{ $c['verified'] ? '✔' : '✘' }} {{ $c['rule'] }}: “{{ $c['quote'] }}”</p>
@endforeach

<h3 style="margin-bottom:.2rem">Corrección del árbitro</h3>
<p style="margin-top:0;white-space:pre-wrap">{{ $dispute->comment }}</p>
@if($dispute->suggested_rule)<p>Regla que propone: <strong>{{ $dispute->suggested_rule }}</strong></p>@endif

<p><a href="{{ $adminUrl }}">Revisar y confirmar en el panel</a></p>
</body>
</html>
