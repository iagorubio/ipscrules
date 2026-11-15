Hola,

{{ $inviter ? $inviter.' te ha invitado' : 'Te han invitado' }} a {{ config('app.name') }} como {{ $roleLabel }}: una herramienta para consultar el reglamento IPSC citando la regla y practicar con exámenes.

Crea tu cuenta aquí:
{{ $link }}

El enlace es personal, solo sirve una vez y caduca el {{ $expires->timezone('Europe/Madrid')->format('d/m/Y') }}. En la página elegirás tu contraseña.

Si no esperabas esta invitación, ignora este mensaje: no se creará ninguna cuenta.
