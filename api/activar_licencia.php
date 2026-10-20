<?php
// Paso 2 del registro: token + nombre + email + huella del móvil.
// Si la clave está libre, queda activada y atada a ese móvil.
define('NUMERIA', 1);
require __DIR__ . '/_lib.php';

solo_post();
limitar('activar', 10, 600);

$d = entrada();
$token  = token_limpio($d['token'] ?? '');
$nombre = mb_substr(trim(preg_replace('/[\x00-\x1F<>]/u', '', (string)($d['nombre'] ?? ''))), 0, 120);
$email  = strtolower(trim((string)($d['email'] ?? '')));
$huella = (string)($d['huella'] ?? '');

if (!preg_match(FORMATO_TOKEN, $token) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190 || !huella_valida($huella)) {
    responder(['error' => 'faltan datos o no son validos'], 400);
}

$lic = obtener_licencia($token);
if (!$lic) responder(['error' => 'token no valido'], 404);

if ($lic['estado'] !== 'sin_usar') {
    // Reactivar justo después (mismo registro repetido) se permite unos minutos;
    // pasado ese margen, la clave ya es de otro móvil.
    $gracia = $lic['estado'] === 'activo' && dentro_del_margen($lic['fecha_activacion'], MINUTOS_GRACIA_HUELLA);
    if (!$gracia) responder(['error' => 'esta licencia ya fue activada en otro dispositivo'], 409);
}

$cli = obtener_o_crear_cliente($email, $nombre);
db()->prepare("UPDATE licencias SET cliente_id = ?, estado = 'activo', huella = ?, fecha_activacion = ?, ultima_vez = ? WHERE token = ?")
    ->execute([$cli['id'], $huella, ahora(), ahora(), $token]);

mandar_email_bienvenida($email, $nombre);
responder(['activado' => true, 'nombre' => $nombre, 'email' => $email]);
