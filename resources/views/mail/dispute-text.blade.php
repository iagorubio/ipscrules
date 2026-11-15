{{ $dispute->user->name }} ({{ $dispute->user->email }}) no está de acuerdo con una respuesta. Puedes contestarle directamente a este correo.

Disciplina: {{ \App\Support\Disciplines::name($dispute->askLog->discipline) }}
Consulta del {{ $dispute->askLog->created_at->timezone('Europe/Madrid')->format('d/m/Y H:i') }}

PREGUNTA
{{ $dispute->askLog->question }}

RESPUESTA DE LA IA ({{ $dispute->askLog->status }})
{{ $dispute->askLog->answer }}
@foreach($dispute->askLog->citations ?? [] as $c)
 {{ $c['verified'] ? '[verificada]' : '[no verificada]' }} {{ $c['rule'] }}: "{{ $c['quote'] }}"
@endforeach

CORRECCIÓN DEL ÁRBITRO
{{ $dispute->comment }}
@if($dispute->suggested_rule)
Regla que propone: {{ $dispute->suggested_rule }}
@endif

Revisar y confirmar en el panel: {{ $adminUrl }}
