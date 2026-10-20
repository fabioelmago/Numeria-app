<?php
// Página de administración de licencias de Numeria.
// Se entra con la clave admin_clave de config.php (no hay usuarios ni cookies:
// la clave viaja con cada acción). 8 fallos en 15 min bloquean esa IP un rato.
define('NUMERIA', 1);
require __DIR__ . '/_lib.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'");

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';   // sin 0/O ni 1/I, para que no se confundan al teclear
function bloque() { $s = ''; for ($i = 0; $i < 4; $i++) $s .= ALFABETO[random_int(0, strlen(ALFABETO) - 1)]; return $s; }

$mensaje = ''; $error = ''; $tabla = null; $tokensNuevos = [];
$clave = (string)($_POST['clave'] ?? '');
$accion = (string)($_POST['accion'] ?? '');
$entrado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ac = (string)cfg('admin_clave');
    $pdo = db();
    $falloBD = false;
    try {
        $q = $pdo->prepare("SELECT COUNT(*) FROM intentos WHERE ip = ? AND accion = 'admin_fallo' AND momento > ?");
        $q->execute([ip_cliente(), time() - 900]);
        $fallos = (int)$q->fetchColumn();
    } catch (Throwable $t) { $fallos = 0; $falloBD = true; }   // tablas aún sin crear

    if ($fallos >= 8) {
        $error = 'Demasiados intentos fallidos. Espera 15 minutos.';
    } elseif (strlen($ac) < 16 || $ac === 'CAMBIA_ESTO_POR_UNA_CLAVE_LARGA') {
        $error = 'Pon antes una admin_clave propia (16 caracteres o más) en config.php.';
    } elseif (!hash_equals($ac, $clave)) {
        if (!$falloBD) $pdo->prepare("INSERT INTO intentos (ip, accion, momento) VALUES (?, 'admin_fallo', ?)")->execute([ip_cliente(), time()]);
        $error = 'Clave incorrecta.';
    } else {
        $entrado = true;
        try {
            if ($accion === 'instalar') {
                instalar_tablas();
                $mensaje = 'Tablas listas.';
            } elseif ($falloBD) {
                $error = 'Primero pulsa «Instalar / comprobar tablas».';
            } elseif ($accion === 'crear') {
                $n = max(1, min(200, (int)($_POST['cantidad'] ?? 1)));
                $lote = substr(preg_replace('/[^A-Za-z0-9 _\-]/', '', (string)($_POST['lote'] ?? '')), 0, 40) ?: gmdate('Y-m-d');
                $ins = $pdo->prepare("INSERT INTO licencias (token, estado, lote, creado) VALUES (?, 'sin_usar', ?, ?)");
                while (count($tokensNuevos) < $n) {
                    $t = 'NUM-' . bloque() . '-' . bloque();
                    try { $ins->execute([$t, $lote, ahora()]); $tokensNuevos[] = $t; } catch (PDOException $x) { /* repetida: otra */ }
                }
                $mensaje = count($tokensNuevos) . ' licencia(s) creadas en el lote «' . $lote . '».';
            } elseif (in_array($accion, ['ver', 'liberar', 'desbloquear', 'baja'], true)) {
                $t = token_limpio($_POST['token'] ?? '');
                $lic = obtener_licencia($t);
                if (!$lic) {
                    $error = 'No existe la licencia ' . $t . '.';
                } else {
                    if ($accion === 'liberar') {
                        $pdo->prepare("UPDATE licencias SET estado = 'activo', huella = NULL, motivo = 'movil liberado' WHERE token = ?")->execute([$t]);
                        $mensaje = 'Móvil liberado: el próximo móvil que abra la app con esta clave se la queda.';
                    } elseif ($accion === 'desbloquear') {
                        $pdo->prepare("UPDATE licencias SET estado = 'activo', motivo = NULL WHERE token = ?")->execute([$t]);
                        $mensaje = 'Desbloqueada (sigue atada al mismo móvil).';
                    } elseif ($accion === 'baja') {
                        $motivo = substr(trim((string)($_POST['motivo'] ?? 'baja')), 0, 190);
                        $pdo->prepare("UPDATE licencias SET estado = 'baja', motivo = ? WHERE token = ?")->execute([$motivo ?: 'baja', $t]);
                        $mensaje = 'Dada de baja.';
                    }
                    $q = $pdo->prepare('SELECT l.*, c.email, c.nombre FROM licencias l LEFT JOIN clientes c ON c.id = l.cliente_id WHERE l.token = ?');
                    $q->execute([$t]);
                    $tabla = [$q->fetch()];
                }
            } elseif ($accion === 'lote') {
                $lote = (string)($_POST['lote'] ?? '');
                $q = $pdo->prepare('SELECT l.*, c.email, c.nombre FROM licencias l LEFT JOIN clientes c ON c.id = l.cliente_id WHERE l.lote = ? ORDER BY l.creado, l.token');
                $q->execute([$lote]);
                $tabla = $q->fetchAll();
                $mensaje = count($tabla) . ' licencia(s) en el lote «' . $lote . '».';
            }
        } catch (Throwable $x) {
            error_log('Numeria admin: ' . $x->getMessage());
            $error = 'Error de la base de datos. ¿Has pulsado «Instalar / comprobar tablas»?';
        }
    }
}
$lotes = [];
if ($entrado) { try { $lotes = db()->query('SELECT lote, COUNT(*) n FROM licencias GROUP BY lote ORDER BY MAX(creado) DESC')->fetchAll(); } catch (Throwable $x) {} }
$ESTADOS = ['sin_usar' => 'Sin usar', 'activo' => 'Activa', 'revisar' => 'Bloqueada', 'baja' => 'De baja'];
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Numeria · Licencias</title>
<style>
:root{--bg:#0B0B0F;--card:#16161C;--line:#2A2A33;--fg:#F2F2F5;--dim:#8E8E99;--acc:#FFD60A;--ok:#34C759;--bad:#FF453A}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--fg);font:15px/1.45 -apple-system,system-ui,sans-serif;padding:20px 16px 60px}
main{max-width:640px;margin:0 auto;display:grid;gap:16px}h1{font-size:22px;margin:0}h2{font-size:15px;margin:0 0 10px;color:var(--dim);font-weight:600;text-transform:uppercase;letter-spacing:.06em}
section{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px}
label{display:block;font-size:13px;color:var(--dim);margin:8px 0 4px}input{width:100%;padding:11px 12px;border-radius:10px;border:1px solid var(--line);background:#0E0E13;color:var(--fg);font:inherit}
.fila{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}button{flex:1 1 auto;padding:11px 14px;border-radius:10px;border:0;background:#2A2A33;color:var(--fg);font:inherit;font-weight:600}
button.p{background:var(--acc);color:#111}button.r{background:#3A1515;color:#FF8A80}
.msg{padding:12px 14px;border-radius:10px;background:#12301B;color:#9BE7AE}.err{padding:12px 14px;border-radius:10px;background:#3A1515;color:#FF8A80}
.tok{font:600 16px/1.7 ui-monospace,Menlo,monospace;letter-spacing:.04em;user-select:all}
table{width:100%;border-collapse:collapse;font-size:13px}td,th{text-align:left;padding:6px 4px;border-bottom:1px solid var(--line);vertical-align:top}
.scroll{overflow-x:auto}.dim{color:var(--dim)}
</style></head><body><main>
<h1>Numeria · Licencias</h1>
<?php if ($mensaje) echo '<div class="msg">' . e($mensaje) . '</div>'; ?>
<?php if ($error) echo '<div class="err">' . e($error) . '</div>'; ?>

<?php if ($tokensNuevos): ?>
<section><h2>Claves nuevas</h2><div class="tok"><?php foreach ($tokensNuevos as $t) echo e($t) . '<br>'; ?></div>
<p class="dim">Cópialas ahora. También puedes verlas luego con «Ver lote».</p></section>
<?php endif; ?>

<?php if ($tabla): ?>
<section><h2>Resultado</h2><div class="scroll"><table>
<tr><th>Clave</th><th>Estado</th><th>Cliente</th><th>Activada</th><th>Última vez</th></tr>
<?php foreach ($tabla as $f): ?>
<tr><td class="tok" style="font-size:13px"><?= e($f['token']) ?></td>
<td><?= e($ESTADOS[$f['estado']] ?? $f['estado']) ?><?= $f['motivo'] ? '<br><span class="dim">' . e($f['motivo']) . '</span>' : '' ?></td>
<td><?= e($f['nombre'] ?? '') ?><br><span class="dim"><?= e($f['email'] ?? '') ?></span></td>
<td class="dim"><?= e($f['fecha_activacion'] ?? '') ?></td><td class="dim"><?= e($f['ultima_vez'] ?? '') ?></td></tr>
<?php endforeach; ?></table></div><p class="dim">Horas en UTC (España = UTC+1 en invierno, +2 en verano).</p></section>
<?php endif; ?>

<form method="post"><section>
<h2>Acceso</h2>
<label for="clave">Clave de administración</label>
<input id="clave" name="clave" type="password" autocomplete="current-password" value="<?= $entrado ? e($clave) : '' ?>" required>
<div class="fila"><button name="accion" value="instalar">Instalar / comprobar tablas</button></div>
</section>

<section><h2>Crear licencias</h2>
<label for="cantidad">Cuántas</label><input id="cantidad" name="cantidad" type="number" min="1" max="200" value="1">
<label for="lote">Lote (p. ej. «tarjetas-tienda-1» o «web»)</label><input id="lote" name="lote" maxlength="40" placeholder="<?= e(gmdate('Y-m-d')) ?>">
<div class="fila"><button class="p" name="accion" value="crear">Crear</button><button name="accion" value="lote">Ver lote</button></div>
<?php if ($lotes): ?><p class="dim">Lotes: <?php foreach ($lotes as $l) echo e($l['lote']) . ' (' . (int)$l['n'] . ') · '; ?></p><?php endif; ?>
</section>

<section><h2>Una licencia</h2>
<label for="token">Clave</label><input id="token" name="token" placeholder="NUM-XXXX-XXXX" autocapitalize="characters">
<div class="fila"><button name="accion" value="ver">Ver</button><button name="accion" value="liberar">Cambió de móvil</button><button name="accion" value="desbloquear">Desbloquear</button></div>
<label for="motivo">Motivo de la baja</label><input id="motivo" name="motivo" placeholder="devolución, impago…">
<div class="fila"><button class="r" name="accion" value="baja">Dar de baja</button></div>
</section></form>
</main></body></html>
