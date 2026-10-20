<?php
// La app lo llama al abrirse: ¿esta licencia sigue activa EN ESTE móvil?
//   {ok:true}                      → todo bien
//   {ok:false, motivo:'otro_movil'} → esta clave es de otro móvil (queda marcada para revisar)
//   {ok:false, motivo:'bloqueada'} → bloqueada a mano / por versiones antiguas (estado 'revisar')
//   {ok:false, motivo:'baja'}      → dada de baja (devolución, etc.)
//   {ok:false, motivo:'no_existe'} → clave inexistente
define('NUMERIA', 1);
require __DIR__ . '/_lib.php';

solo_post();
limitar('comprobar', 30, 600);

$d = entrada();
$token  = token_limpio($d['token'] ?? '');
$huella = (string)($d['huella'] ?? '');
if (!preg_match(FORMATO_TOKEN, $token) || !huella_valida($huella)) responder(['ok' => false, 'motivo' => 'no_existe']);

$lic = obtener_licencia($token);
if (!$lic || $lic['estado'] === 'sin_usar') responder(['ok' => false, 'motivo' => 'no_existe']);
if ($lic['estado'] === 'revisar') responder(['ok' => false, 'motivo' => 'bloqueada']);
if ($lic['estado'] !== 'activo') responder(['ok' => false, 'motivo' => 'baja']);

$pdo = db();
if (!$lic['huella']) {
    // Móvil liberado desde admin.php ("cambió de móvil"): el primero que entra se queda la licencia.
    $pdo->prepare('UPDATE licencias SET huella = ?, ultima_vez = ? WHERE token = ?')->execute([$huella, ahora(), $token]);
    responder(['ok' => true]);
}
if (!hash_equals($lic['huella'], $huella)) {
    if (dentro_del_margen($lic['fecha_activacion'], MINUTOS_GRACIA_HUELLA)) {
        // Recién activada: iOS a veces no comparte almacenamiento entre Safari y la app instalada.
        $pdo->prepare('UPDATE licencias SET huella = ?, ultima_vez = ? WHERE token = ?')->execute([$huella, ahora(), $token]);
        responder(['ok' => true]);
    }
    // Otro móvil con la misma clave: ese móvil se queda fuera y la licencia queda
    // MARCADA para revisar (se ve en admin.php), pero el dueño legítimo sigue
    // funcionando. Bloquear la licencia entera dejaría sin app al dueño cada vez
    // que alguien pruebe su clave (o su móvil viejo siga instalado).
    $pdo->prepare('UPDATE licencias SET motivo = ? WHERE token = ?')
        ->execute(['REVISAR: intento desde otro movil (' . ahora() . ' UTC)', $token]);
    responder(['ok' => false, 'motivo' => 'otro_movil']);
}
$pdo->prepare('UPDATE licencias SET ultima_vez = ? WHERE token = ?')->execute([ahora(), $token]);
responder(['ok' => true]);
