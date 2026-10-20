<?php
// La app lo llama al abrirse: ¿esta licencia sigue activa EN ESTE móvil?
//   {ok:true}
//   {ok:false, motivo:'otro_movil'} → esta clave es de otro móvil (queda marcada para revisar)
//   {ok:false, motivo:'bloqueada'}  → bloqueada a mano (estado 'revisar')
//   {ok:false, motivo:'baja'}       → dada de baja (devolución, etc.)
//   {ok:false, motivo:'no_existe'}  → clave inexistente
define('NUMERIA', 1);
require __DIR__ . '/_lib.php';

solo_post();
limitar('comprobar', 30, 600);

$d = entrada();
[$ok, $motivo] = verificar_licencia(token_limpio($d['token'] ?? ''), (string)($d['huella'] ?? ''));
responder($ok ? ['ok' => true] : ['ok' => false, 'motivo' => $motivo]);
