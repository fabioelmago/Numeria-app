<?php
// Paso 1 del registro: ¿existe la clave y está libre? No guarda nada
// (salvo, en fase de pruebas abiertas, crear la licencia si no existía).
define('NUMERIA', 1);
require __DIR__ . '/_lib.php';

solo_post();
limitar('validar', 20, 600);   // 20 claves cada 10 min por IP

$token = token_limpio(entrada()['token'] ?? '');
if (!preg_match(FORMATO_TOKEN, $token)) responder(['valido' => false, 'motivo' => 'no_existe']);

$lic = obtener_licencia($token);

if (!$lic && cfg('pruebas_abiertas') && $token !== TOKEN_PRUEBAS) {
    try {
        db()->prepare("INSERT INTO licencias (token, estado, lote, creado) VALUES (?, 'sin_usar', 'pruebas', ?)")
            ->execute([$token, ahora()]);
        $lic = obtener_licencia($token);
    } catch (Throwable $e) {
        error_log('Numeria alta pruebas: ' . $e->getMessage());
        responder(['valido' => false, 'motivo' => 'no_se_pudo_crear']);
    }
}

if (!$lic) responder(['valido' => false, 'motivo' => 'no_existe']);
// Libre: sin usar, o activa con el móvil liberado desde admin.php ("Cambió de móvil").
$liberada = $lic['estado'] === 'activo' && !$lic['huella'];
if ($lic['estado'] !== 'sin_usar' && !$liberada) responder(['valido' => false, 'motivo' => 'ya_activada']);
responder(['valido' => true]);
