<?php
// "¿Cambiaste de móvil?" — paso 1: clave + email de la ficha → código por email.
// Respuestas: {enviado:true} | {error, motivo:'no_coincide'|'limite'|'sin_email'|'envio'|'espera'}
define('NUMERIA', 1);
require __DIR__ . '/_lib.php';

solo_post();
limitar('recuperar', 6, 3600);   // 6 peticiones por hora y IP

$d = entrada();
$token  = token_limpio($d['token'] ?? '');
$email  = strtolower(trim((string)($d['email'] ?? '')));
$idioma = in_array($d['idioma'] ?? '', ['es', 'en', 'fr', 'it', 'de'], true) ? $d['idioma'] : 'es';
$soporte = (string)cfg('email_soporte');

if (!preg_match(FORMATO_TOKEN, $token) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responder(['error' => 'faltan datos o no son validos', 'motivo' => 'no_coincide'], 400);
}
asegurar_tablas_recuperacion();
$pdo = db();

$q = $pdo->prepare('SELECT l.estado, c.email FROM licencias l LEFT JOIN clientes c ON c.id = l.cliente_id WHERE l.token = ?');
$q->execute([$token]);
$f = $q->fetch();
// Misma respuesta si la clave no existe o el email no es el suyo (no se dan pistas).
if (!$f || $f['estado'] !== 'activo' || !$f['email'] || !hash_equals(strtolower($f['email']), $email)) {
    responder(['error' => 'no coincide', 'motivo' => 'no_coincide'], 404);
}
if (cambios_ultimo_anio($token) >= MAX_CAMBIOS_MOVIL_ANIO) {
    responder(['error' => 'limite de cambios', 'motivo' => 'limite', 'soporte' => $soporte], 429);
}
if (!cfg('email_remitente')) {
    responder(['error' => 'email no configurado', 'motivo' => 'sin_email', 'soporte' => $soporte], 503);
}

// Máximo 3 códigos por clave cada hora (reenvíos incluidos).
$q = $pdo->prepare('SELECT envios, primer_envio FROM recuperaciones WHERE token = ?');
$q->execute([$token]);
$r = $q->fetch();
$envios = 1; $primer = time();
if ($r && time() - (int)$r['primer_envio'] < 3600) {
    if ((int)$r['envios'] >= 3) responder(['error' => 'espera', 'motivo' => 'espera'], 429);
    $envios = (int)$r['envios'] + 1; $primer = (int)$r['primer_envio'];
}

$codigo = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$pdo->prepare('DELETE FROM recuperaciones WHERE token = ?')->execute([$token]);
$pdo->prepare('INSERT INTO recuperaciones (token, codigo, expira, intentos, envios, primer_envio) VALUES (?, ?, ?, 0, ?, ?)')
    ->execute([$token, hash_codigo($token, $codigo), time() + MINUTOS_CODIGO * 60, $envios, $primer]);

if (!mandar_email_codigo($email, $codigo, $idioma)) {
    error_log('Numeria recuperar: fallo al enviar el email');
    responder(['error' => 'no se pudo enviar', 'motivo' => 'envio', 'soporte' => $soporte], 503);
}
responder(['enviado' => true]);
