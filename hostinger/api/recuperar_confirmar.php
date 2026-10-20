<?php
// "¿Cambiaste de móvil?" — paso 2: clave + código + huella del móvil nuevo.
// Si el código es bueno, la licencia pasa a este móvil (el viejo queda fuera).
// Respuestas: {ok:true, nombre, email} | {error, motivo:'codigo_mal'|'caducado'}
define('NUMERIA', 1);
require __DIR__ . '/_lib.php';

solo_post();
limitar('recup_conf', 12, 600);

$d = entrada();
$token  = token_limpio($d['token'] ?? '');
$codigo = preg_replace('/\D/', '', (string)($d['codigo'] ?? ''));
$huella = (string)($d['huella'] ?? '');
if (!preg_match(FORMATO_TOKEN, $token) || strlen($codigo) !== 6 || !huella_valida($huella)) {
    responder(['error' => 'codigo no valido', 'motivo' => 'codigo_mal'], 400);
}
asegurar_tablas_recuperacion();
$pdo = db();

$q = $pdo->prepare('SELECT * FROM recuperaciones WHERE token = ?');
$q->execute([$token]);
$r = $q->fetch();
if (!$r || (int)$r['expira'] < time() || (int)$r['intentos'] >= 5) {
    responder(['error' => 'codigo caducado', 'motivo' => 'caducado'], 410);
}
if (!hash_equals($r['codigo'], hash_codigo($token, $codigo))) {
    $pdo->prepare('UPDATE recuperaciones SET intentos = intentos + 1 WHERE token = ?')->execute([$token]);
    responder(['error' => 'codigo incorrecto', 'motivo' => 'codigo_mal'], 403);
}

$lic = obtener_licencia($token);
if (!$lic || $lic['estado'] !== 'activo') responder(['error' => 'codigo caducado', 'motivo' => 'caducado'], 410);

$pdo->prepare("UPDATE licencias SET huella = ?, ultima_vez = ?, motivo = 'cambio de movil (email)' WHERE token = ?")
    ->execute([$huella, ahora(), $token]);
$pdo->prepare('INSERT INTO cambios_movil (token, momento) VALUES (?, ?)')->execute([$token, ahora()]);
$pdo->prepare('DELETE FROM recuperaciones WHERE token = ?')->execute([$token]);

$q = $pdo->prepare('SELECT nombre, email FROM clientes WHERE id = ?');
$q->execute([$lic['cliente_id']]);
$c = $q->fetch() ?: ['nombre' => '', 'email' => ''];
responder(['ok' => true, 'nombre' => $c['nombre'], 'email' => $c['email']]);
