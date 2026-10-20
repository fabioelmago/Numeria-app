<?php
// Entrega el motor de Telegram SOLO a un móvil con licencia válida.
// Cada copia lleva dentro (no se puede ver desde fuera sin desmontarla):
//   - las claves de la app de Telegram,
//   - la licencia a la que pertenece (marca de agua),
//   - su caducidad (DIAS_VALIDEZ_MOTOR) y el dominio donde puede funcionar.
// El fichero del motor está en api/_motor/, que no se puede abrir desde internet.
define('NUMERIA', 1);
require __DIR__ . '/_lib.php';

solo_post();
limitar('motor', 40, 3600);   // 40 descargas por hora y IP (un mago normal: 1-3 al día)

$d = entrada();
$token  = token_limpio($d['token'] ?? '');
$huella = (string)($d['huella'] ?? '');
[$ok, $motivo] = verificar_licencia($token, $huella);
if (!$ok) responder(['error' => 'licencia no valida', 'motivo' => $motivo], 403);

$f = __DIR__ . '/_motor/engine.js';
$js = is_file($f) ? file_get_contents($f) : '';
$marca = '"__NUMERIA_CFG__"';
if ($js === '' || substr_count($js, $marca) !== 1) {
    error_log('Numeria motor: fichero ausente o marcador incorrecto');
    responder(['error' => 'servidor no disponible'], 503);
}

$cfg = [
    'i' => (int)(cfg('tg_api_id') ?: TG_API_ID),
    'k' => (string)(cfg('tg_api_hash') ?: TG_API_HASH),
    't' => $token,
    'e' => time() + DIAS_VALIDEZ_MOTOR * 86400,
    'd' => dominio_app() ?: '*',
];
$js = str_replace($marca, json_encode(json_encode($cfg)), $js);
$cab = "/*! Numeria © 2026 Fabio Pérez de Lazárraga y Marroyo (Fabio el Mago). Todos los derechos reservados.\n"
     . "    Copia de uso personal de la licencia $token. Prohibida su copia o distribución. */\n";

if (!headers_sent() && extension_loaded('zlib') && !ini_get('zlib.output_compression')
    && strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
    ob_start('ob_gzhandler');
}
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo $cab, $js;
