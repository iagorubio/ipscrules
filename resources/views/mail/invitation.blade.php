<!doctype html>
<html lang="es">
<body style="font-family:system-ui,-apple-system,Segoe UI,sans-serif;line-height:1.5;color:#1c2230;max-width:34rem">
<p>Hola,</p>
<p>{{ $inviter ? $inviter.' te ha invitado' : 'Te han invitado' }} a <strong>{{ config('app.name') }}</strong> como {{ $roleLabel }}: una herramienta para consultar el reglamento IPSC citando la regla y practicar con exámenes.</p>
<p><a href="{{ $link }}" style="display:inline-block;padding:.6rem 1.1rem;background:#1f4e8c;color:#fff;border-radius:8px;text-decoration:none;font-weight:600">Crear mi cuenta</a></p>
<p>Si el botón no funciona, copia esta dirección en el navegador:<br><span style="word-break:break-all">{{ $link }}</span></p>
<p>El enlace es personal, solo sirve una vez y caduca el {{ $expires->timezone('Europe/Madrid')->format('d/m/Y') }}. En la página elegirás tu contraseña.</p>
<p style="color:#5d6679;font-size:.9rem">Si no esperabas esta invitación, ignora este mensaje: no se creará ninguna cuenta.</p>
</body>
</html>
