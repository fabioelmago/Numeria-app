<?php
// Página de administración de licencias de Numeria.
// Se entra con la clave admin_clave de la configuración (no hay usuarios ni cookies:
// la clave viaja con cada acción). 8 fallos en 15 min bloquean esa IP un rato.
define('NUMERIA', 1);
require __DIR__ . '/_lib.php';

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';   // sin 0/O ni 1/I, para que no se confundan al teclear
function bloque() { $s = ''; for ($i = 0; $i < 4; $i++) $s .= ALFABETO[random_int(0, strlen(ALFABETO) - 1)]; return $s; }

// Hora española para mostrar (UTC+1 / +2 en verano)
function hora_es($utc) {
    if (!$utc) return '';
    try { $d = new DateTime($utc, new DateTimeZone('UTC')); $d->setTimezone(new DateTimeZone('Europe/Madrid')); return $d->format('d/m/y H:i'); }
    catch (Throwable $x) { return $utc; }
}

$FILTROS = ['todas' => 'Todas', 'activo' => 'Activas', 'sin_usar' => 'Sin usar', 'baja' => 'De baja', 'revisar' => 'Para revisar'];
$ESTADOS = ['sin_usar' => 'Sin usar', 'activo' => 'Activa', 'revisar' => 'Bloqueada', 'baja' => 'De baja'];

$mensaje = ''; $error = ''; $tokensNuevos = []; $confirmar = null;
$clave  = (string)($_POST['clave'] ?? '');
$accion = (string)($_POST['accion'] ?? '');
$filtro = array_key_exists($_POST['filtro'] ?? '', $FILTROS) ? $_POST['filtro'] : 'todas';
$busca  = mb_substr(trim((string)($_POST['q'] ?? '')), 0, 80);
$loteV  = substr(preg_replace('/[^A-Za-z0-9 _\-]/', '', (string)($_POST['lote_ver'] ?? '')), 0, 40);
$entrado = false; $falloBD = false;

// ---------- Autenticación ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ac = (string)cfg('admin_clave');
    $pdo = db();
    try {
        $q = $pdo->prepare("SELECT COUNT(*) FROM intentos WHERE ip = ? AND accion = 'admin_fallo' AND momento > ?");
        $q->execute([ip_cliente(), time() - 900]);
        $fallos = (int)$q->fetchColumn();
    } catch (Throwable $t) { $fallos = 0; $falloBD = true; }   // tablas aún sin crear

    if ($fallos >= 8) {
        $error = 'Demasiados intentos fallidos. Espera 15 minutos.';
    } elseif (strlen($ac) < 16 || $ac === 'CAMBIA_ESTO_POR_UNA_CLAVE_LARGA') {
        $error = 'Pon antes una admin_clave propia (16 caracteres o más) en la configuración.';
    } elseif (!hash_equals($ac, $clave)) {
        if (!$falloBD) $pdo->prepare("INSERT INTO intentos (ip, accion, momento) VALUES (?, 'admin_fallo', ?)")->execute([ip_cliente(), time()]);
        $error = 'Clave incorrecta.';
    } else {
        $entrado = true;
    }
}

// ---------- Acciones ----------
if ($entrado) {
    $pdo = db();
    try {
        if ($accion === 'instalar') {
            instalar_tablas(); asegurar_tablas_recuperacion(); $falloBD = false;
            $mensaje = 'Tablas listas.';
        } elseif ($falloBD) {
            $error = 'Primero pulsa «Instalar / comprobar tablas» (abajo del todo).';
        } elseif ($accion === 'crear') {
            $n = max(1, min(200, (int)($_POST['cantidad'] ?? 1)));
            $lote = substr(preg_replace('/[^A-Za-z0-9 _\-]/', '', (string)($_POST['lote'] ?? '')), 0, 40) ?: gmdate('Y-m-d');
            $ins = $pdo->prepare("INSERT INTO licencias (token, estado, lote, creado) VALUES (?, 'sin_usar', ?, ?)");
            while (count($tokensNuevos) < $n) {
                $t = 'NUM-' . bloque() . '-' . bloque();
                try { $ins->execute([$t, $lote, ahora()]); $tokensNuevos[] = $t; } catch (PDOException $x) { /* repetida: otra */ }
            }
            $mensaje = count($tokensNuevos) . ' licencia(s) creadas en el lote «' . $lote . '».';
            $loteV = $lote;
        } elseif (in_array($accion, ['liberar', 'desbloquear', 'pedir_baja', 'baja'], true)) {
            $t = token_limpio($_POST['token'] ?? '');
            $lic = obtener_licencia($t);
            if (!$lic) {
                $error = 'No existe la licencia ' . $t . '.';
            } elseif ($accion === 'pedir_baja') {
                $confirmar = ['tipo' => 'baja', 'token' => $t, 'lic' => $lic];
            } elseif ($accion === 'baja') {
                $motivo = substr(trim((string)($_POST['motivo'] ?? '')), 0, 190) ?: 'baja';
                $pdo->prepare("UPDATE licencias SET estado = 'baja', motivo = ? WHERE token = ?")->execute([$motivo, $t]);
                $mensaje = $t . ' dada de baja.';
            } elseif ($accion === 'liberar') {
                $pdo->prepare("UPDATE licencias SET estado = 'activo', huella = NULL, motivo = 'movil liberado' WHERE token = ?")->execute([$t]);
                $mensaje = $t . ': móvil liberado. El cliente puede registrarse otra vez con su clave en el móvil nuevo (el viejo queda fuera).';
            } else {
                $pdo->prepare("UPDATE licencias SET estado = 'activo', motivo = NULL WHERE token = ?")->execute([$t]);
                $mensaje = $t . ' desbloqueada (sigue atada al mismo móvil).';
            }
        } elseif ($accion === 'pedir_baja_lote' || $accion === 'baja_lote') {
            $q = $pdo->prepare("SELECT estado, COUNT(*) n FROM licencias WHERE lote = ? AND estado <> 'baja' GROUP BY estado");
            $q->execute([$loteV]);
            $cuenta = []; foreach ($q->fetchAll() as $r) $cuenta[$r['estado']] = (int)$r['n'];
            if (!array_sum($cuenta)) {
                $error = 'El lote «' . $loteV . '» no tiene licencias que dar de baja.';
            } elseif ($accion === 'pedir_baja_lote') {
                $confirmar = ['tipo' => 'lote', 'lote' => $loteV, 'cuenta' => $cuenta];
            } else {
                $motivo = substr(trim((string)($_POST['motivo'] ?? '')), 0, 190) ?: 'lote anulado';
                $u = $pdo->prepare("UPDATE licencias SET estado = 'baja', motivo = ? WHERE lote = ? AND estado <> 'baja'");
                $u->execute([$motivo, $loteV]);
                $mensaje = $u->rowCount() . ' licencia(s) del lote «' . $loteV . '» dadas de baja.';
            }
        }

        // ---------- Descargar Excel (CSV con ; y BOM: se abre bien en Excel/Numbers en español) ----------
        if ($accion === 'csv' && !$falloBD) {
            $rows = $pdo->query('SELECT l.token, l.lote, l.estado, c.nombre, c.email, l.fecha_activacion, l.ultima_vez, l.motivo, l.creado
                                 FROM licencias l LEFT JOIN clientes c ON c.id = l.cliente_id ORDER BY l.creado DESC, l.token')->fetchAll();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="numeria-licencias-' . gmdate('Y-m-d') . '.csv"');
            header('Cache-Control: no-store');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Clave', 'Lote', 'Estado', 'Nombre', 'Email', 'Activada', 'Última vez', 'Nota', 'Creada'], ';', '"', '');
            foreach ($rows as $r) {
                // evita que Excel interprete como fórmula algo que empiece por = + - @
                $limpio = array_map(fn($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'" . $v : $v, [
                    $r['token'], $r['lote'], $ESTADOS[$r['estado']] ?? $r['estado'], $r['nombre'], $r['email'],
                    hora_es($r['fecha_activacion']), hora_es($r['ultima_vez']), $r['motivo'], hora_es($r['creado'])]);
                fputcsv($out, $limpio, ';', '"', '');
            }
            exit;
        }
    } catch (Throwable $x) {
        error_log('Numeria admin: ' . $x->getMessage());
        $error = 'Error de la base de datos. ¿Has pulsado «Instalar / comprobar tablas»?';
    }
}

// ---------- Datos para la vista ----------
$cont = ['activo' => 0, 'sin_usar' => 0, 'baja' => 0, 'revisar' => 0]; $lotes = []; $filas = [];
if ($entrado && !$falloBD) {
    try {
        foreach (db()->query("SELECT estado, COUNT(*) n FROM licencias GROUP BY estado")->fetchAll() as $r) $cont[$r['estado']] = (int)$r['n'] + ($cont[$r['estado']] ?? 0);
        $cont['revisar'] = (int)db()->query("SELECT COUNT(*) FROM licencias WHERE estado = 'revisar' OR motivo LIKE 'REVISAR%'")->fetchColumn();
        $lotes = db()->query('SELECT lote, COUNT(*) n FROM licencias GROUP BY lote ORDER BY MAX(creado) DESC')->fetchAll();
        $w = []; $p = [];
        if ($loteV !== '') { $w[] = 'l.lote = ?'; $p[] = $loteV; }
        if ($filtro === 'revisar') $w[] = "(l.estado = 'revisar' OR l.motivo LIKE 'REVISAR%')";
        elseif ($filtro !== 'todas') { $w[] = 'l.estado = ?'; $p[] = $filtro; }
        if ($busca !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($busca)) . '%';
            $w[] = "(LOWER(l.token) LIKE ? ESCAPE '\\' OR LOWER(COALESCE(c.email,'')) LIKE ? ESCAPE '\\' OR LOWER(COALESCE(c.nombre,'')) LIKE ? ESCAPE '\\' OR LOWER(COALESCE(l.lote,'')) LIKE ? ESCAPE '\\')";
            array_push($p, $like, $like, $like, $like);
        }
        $sql = 'SELECT l.*, c.email, c.nombre FROM licencias l LEFT JOIN clientes c ON c.id = l.cliente_id'
             . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY l.creado DESC, l.token LIMIT 500';
        $q = db()->prepare($sql); $q->execute($p); $filas = $q->fetchAll();
    } catch (Throwable $x) { $falloBD = true; }
}

// Campos ocultos que mantienen la sesión y la vista actual en cada formulario
function estado_oculto($clave, $filtro, $busca, $loteV) {
    return '<input type="hidden" name="clave" value="' . e($clave) . '"><input type="hidden" name="filtro" value="' . e($filtro) . '">'
         . '<input type="hidden" name="q" value="' . e($busca) . '"><input type="hidden" name="lote_ver" value="' . e($loteV) . '">';
}
$oculto = estado_oculto($clave, $filtro, $busca, $loteV);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src 'self'; form-action 'self'");
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#07070B"><title>Numeria · Licencias</title>
<style>
:root{
  --bg:#07070B; --fg:#F2F2F7; --dim:rgba(235,235,245,.6); --dim2:rgba(235,235,245,.38);
  --glass:rgba(28,28,34,.55); --glass2:rgba(255,255,255,.07); --line:rgba(255,255,255,.1);
  --ok:#34C759; --bad:#FF453A; --warn:#FF9F0A; --gold:#FFD60A;
  --f:-apple-system,BlinkMacSystemFont,"SF Pro Text","Segoe UI",Roboto,Helvetica,Arial,sans-serif;
  --mono:ui-monospace,"SF Mono",Menlo,Consolas,monospace;
  color-scheme:dark;
}
*{box-sizing:border-box}
html{background:var(--bg)}
body{margin:0;min-height:100vh;color:var(--fg);font:15px/1.45 var(--f);-webkit-font-smoothing:antialiased;
  background:var(--bg) url("../fondo-menu.jpg") center top/cover fixed;padding:0 16px 60px}
body::before{content:"";position:fixed;inset:0;background:linear-gradient(180deg,rgba(7,7,11,.35),rgba(7,7,11,.82) 60%);pointer-events:none;z-index:0}
main{position:relative;z-index:1;max-width:980px;margin:0 auto;display:grid;gap:16px;padding-top:max(16px,env(safe-area-inset-top))}
.cab{border-radius:22px;overflow:hidden;border:1px solid var(--line);background:#07070B}
.cab img{display:block;width:100%;height:auto;max-height:180px;object-fit:cover}
.card{background:var(--glass);border:1px solid var(--line);border-radius:20px;padding:18px;
  -webkit-backdrop-filter:blur(24px) saturate(140%);backdrop-filter:blur(24px) saturate(140%)}
h2{margin:0 0 12px;font-size:12px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:var(--dim)}
label{display:block;font-size:13px;color:var(--dim);margin:10px 0 6px}
input,select{width:100%;padding:12px 14px;border-radius:12px;border:1px solid var(--line);background:rgba(0,0,0,.35);color:var(--fg);font:inherit;outline:none}
input:focus,select:focus{border-color:rgba(255,255,255,.35)}
.fila{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
button,.btn{appearance:none;border:1px solid var(--line);background:var(--glass2);color:var(--fg);font:600 14px/1 var(--f);
  padding:12px 16px;border-radius:999px;cursor:pointer;white-space:nowrap}
button:hover{background:rgba(255,255,255,.12)}
button.p{background:#F2F2F7;color:#0B0B0F;border-color:#F2F2F7}
button.r{background:rgba(255,69,58,.14);color:#FF8A80;border-color:rgba(255,69,58,.35)}
button.s{padding:8px 12px;font-size:12.5px}
.msg,.err{padding:13px 16px;border-radius:14px;border:1px solid}
.msg{background:rgba(52,199,89,.12);border-color:rgba(52,199,89,.35);color:#A6F0B7}
.err{background:rgba(255,69,58,.12);border-color:rgba(255,69,58,.35);color:#FFB4AE}
.tok{font:600 15px/1.6 var(--mono);letter-spacing:.04em;user-select:all;-webkit-user-select:all}
.cont{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.cont form{margin:0}
.cont button{width:100%;border-radius:16px;padding:14px 12px;text-align:left;display:grid;gap:4px;white-space:normal}
.cont b{font:700 26px/1 var(--f)}
.cont span{font-size:12px;color:var(--dim);font-weight:500}
.cont .on{border-color:rgba(255,255,255,.45);background:rgba(255,255,255,.12)}
.tabs{display:flex;gap:6px;flex-wrap:wrap}
.tabs button{padding:9px 14px;font-size:13px}
.tabs .on{background:#F2F2F7;color:#0B0B0F;border-color:#F2F2F7}
.busca{display:grid;grid-template-columns:1fr auto;gap:8px;margin-top:12px}
.dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:7px;vertical-align:1px}
.e-activo .dot{background:var(--ok)} .e-sin_usar .dot{background:#8E8E93} .e-baja .dot{background:var(--bad)} .e-revisar .dot{background:var(--warn)}
.flag{display:inline-block;margin-top:4px;padding:2px 8px;border-radius:999px;font-size:11.5px;background:rgba(255,159,10,.15);color:#FFC46B;border:1px solid rgba(255,159,10,.35)}
.nota{color:var(--dim2);font-size:12px}
.lista{display:grid;gap:0}
.cabl,.reg{display:grid;grid-template-columns:150px 120px minmax(0,1.4fr) 110px 110px minmax(0,1.6fr);gap:12px;align-items:center;padding:12px 4px;border-bottom:1px solid var(--line)}
.cabl{font-size:11.5px;letter-spacing:.08em;text-transform:uppercase;color:var(--dim2);padding-top:0}
.reg:last-child{border-bottom:0}
.reg .tok{font-size:13.5px}
.reg .cli{min-width:0;overflow:hidden;text-overflow:ellipsis}
.reg .cli small{display:block;color:var(--dim);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.reg .acc{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}
.reg .acc form{margin:0}
.reg .f{font-size:12.5px;color:var(--dim)}
.lbl{display:none}
.lotes{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}
.lotes form{margin:0}
.lotes button{padding:7px 12px;font-size:12.5px;font-weight:500}
.lotes .on{border-color:rgba(255,255,255,.5)}
.conf{border-color:rgba(255,69,58,.4)}
.pie{text-align:center;color:var(--dim2);font-size:12px}
@media (max-width:760px){
  .cont{grid-template-columns:repeat(2,1fr)}
  .cabl{display:none}
  .reg{grid-template-columns:1fr 1fr;gap:6px 12px;padding:14px 2px}
  .reg .tok{grid-column:1/-1;font-size:15px}
  .reg .cli{grid-column:1/-1}
  .reg .acc{grid-column:1/-1;justify-content:flex-start;margin-top:6px}
  .lbl{display:block;font-size:11px;color:var(--dim2);text-transform:uppercase;letter-spacing:.06em}
}
</style></head><body><main>

<div class="cab"><img src="../email/cabecera.jpg" alt="NUMERIA" width="1120" height="360"></div>

<?php if ($mensaje) echo '<div class="msg">' . e($mensaje) . '</div>'; ?>
<?php if ($error) echo '<div class="err">' . e($error) . '</div>'; ?>

<?php if (!$entrado): ?>
<form method="post" class="card">
  <h2>Acceso</h2>
  <label for="clave">Clave de administración</label>
  <input id="clave" name="clave" type="password" autocomplete="current-password" required autofocus>
  <div class="fila"><button class="p">Entrar</button></div>
</form>
<?php else: ?>

<?php if ($confirmar): ?>
<form method="post" class="card conf"><?= $oculto ?>
  <?php if ($confirmar['tipo'] === 'baja'): $L = $confirmar['lic']; ?>
    <h2>¿Dar de baja esta licencia?</h2>
    <div class="tok"><?= e($confirmar['token']) ?></div>
    <p class="nota"><?= e($ESTADOS[$L['estado']] ?? $L['estado']) ?><?= $L['ultima_vez'] ? ' · usada por última vez ' . e(hora_es($L['ultima_vez'])) : '' ?>.
      Si está activa, ese móvil dejará de poder buscar.</p>
    <input type="hidden" name="token" value="<?= e($confirmar['token']) ?>">
    <label for="motivo">Motivo</label><input id="motivo" name="motivo" placeholder="devolución, impago, prueba…">
    <div class="fila"><button class="r" name="accion" value="baja">Sí, dar de baja</button><button name="accion" value="lista">Cancelar</button></div>
  <?php else: $c = $confirmar['cuenta']; ?>
    <h2>¿Dar de baja el lote «<?= e($confirmar['lote']) ?>» entero?</h2>
    <p><?= array_sum($c) ?> licencia(s): <?= (int)($c['activo'] ?? 0) ?> activas, <?= (int)($c['sin_usar'] ?? 0) ?> sin usar<?= !empty($c['revisar']) ? ', ' . (int)$c['revisar'] . ' bloqueadas' : '' ?>.</p>
    <?php if (!empty($c['activo'])): ?><p class="err">Ojo: hay <?= (int)$c['activo'] ?> clientes usando licencias de este lote. Dejarán de poder buscar.</p><?php endif; ?>
    <label for="motivo">Motivo</label><input id="motivo" name="motivo" placeholder="lote anulado, tarjetas perdidas…">
    <div class="fila"><button class="r" name="accion" value="baja_lote">Sí, dar de baja el lote</button><button name="accion" value="lista">Cancelar</button></div>
  <?php endif; ?>
</form>
<?php endif; ?>

<?php if ($tokensNuevos): ?>
<section class="card"><h2>Claves nuevas</h2><div class="tok"><?php foreach ($tokensNuevos as $t) echo e($t) . '<br>'; ?></div>
<p class="nota">Cópialas ahora (también salen en la lista de abajo).</p></section>
<?php endif; ?>

<!-- Contadores: también sirven de filtro -->
<div class="cont">
<?php foreach (['activo' => 'Activas', 'sin_usar' => 'Sin usar', 'revisar' => 'Para revisar', 'baja' => 'De baja'] as $k => $txt): ?>
  <form method="post"><?= estado_oculto($clave, $k, '', '') ?><button name="accion" value="lista" class="<?= $filtro === $k && $loteV === '' && $busca === '' ? 'on' : '' ?>"><b><?= (int)$cont[$k] ?></b><span><?= e($txt) ?></span></button></form>
<?php endforeach; ?>
</div>

<!-- Lista de licencias -->
<section class="card">
  <form method="post"><?= estado_oculto($clave, $filtro, '', $loteV) ?>
    <div class="tabs">
    <?php foreach ($FILTROS as $k => $txt): ?>
      <button name="filtro" value="<?= e($k) ?>" class="<?= $filtro === $k ? 'on' : '' ?>"><?= e($txt) ?></button>
    <?php endforeach; ?>
    </div>
  </form>
  <form method="post" class="busca"><input type="hidden" name="clave" value="<?= e($clave) ?>"><input type="hidden" name="filtro" value="<?= e($filtro) ?>"><input type="hidden" name="lote_ver" value="<?= e($loteV) ?>">
    <input name="q" value="<?= e($busca) ?>" placeholder="Buscar por email, nombre, clave o lote" aria-label="Buscar">
    <button name="accion" value="lista">Buscar</button>
  </form>
  <?php if ($lotes): ?>
  <div class="lotes">
    <form method="post"><?= estado_oculto($clave, $filtro, $busca, '') ?><button name="accion" value="lista" class="<?= $loteV === '' ? 'on' : '' ?>">Todos los lotes</button></form>
    <?php foreach ($lotes as $l): ?>
      <form method="post"><?= estado_oculto($clave, $filtro, $busca, (string)$l['lote']) ?><button name="accion" value="lista" class="<?= $loteV === (string)$l['lote'] ? 'on' : '' ?>"><?= e($l['lote'] ?: '—') ?> · <?= (int)$l['n'] ?></button></form>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <p class="nota" style="margin:14px 0 4px"><?= count($filas) ?> licencia(s)<?= $loteV !== '' ? ' en el lote «' . e($loteV) . '»' : '' ?><?= count($filas) >= 500 ? ' (se muestran las 500 más recientes; usa el buscador)' : '' ?> · horas de España</p>
  <div class="lista">
    <div class="cabl"><div>Clave</div><div>Estado</div><div>Cliente</div><div>Activada</div><div>Última vez</div><div style="text-align:right">Acciones</div></div>
    <?php foreach ($filas as $f): $est = $f['estado']; $rev = strpos((string)$f['motivo'], 'REVISAR') === 0; ?>
    <div class="reg">
      <div class="tok"><?= e($f['token']) ?><div class="nota" style="font-family:var(--f);letter-spacing:0"><?= e($f['lote']) ?></div></div>
      <div class="e-<?= e($rev && $est === 'activo' ? 'revisar' : $est) ?>"><span class="lbl">Estado</span><span class="dot"></span><?= e($ESTADOS[$est] ?? $est) ?>
        <?php if ($rev): ?><br><span class="flag" title="<?= e($f['motivo']) ?>">Revisar: otro móvil</span>
        <?php elseif ($f['motivo'] && $est !== 'activo'): ?><div class="nota"><?= e($f['motivo']) ?></div><?php endif; ?></div>
      <div class="cli"><span class="lbl">Cliente</span><?= e($f['nombre'] ?: ($f['email'] ? '' : '—')) ?><small><?= e($f['email']) ?></small></div>
      <div class="f"><span class="lbl">Activada</span><?= e(hora_es($f['fecha_activacion'])) ?: '—' ?></div>
      <div class="f"><span class="lbl">Última vez</span><?= e(hora_es($f['ultima_vez'])) ?: '—' ?></div>
      <div class="acc">
        <?php if ($est === 'activo' && $f['huella']): ?>
          <form method="post"><?= $oculto ?><input type="hidden" name="token" value="<?= e($f['token']) ?>"><button class="s" name="accion" value="liberar">Cambió de móvil</button></form>
        <?php endif; ?>
        <?php if ($est === 'revisar' || ($rev && $est === 'activo')): ?>
          <form method="post"><?= $oculto ?><input type="hidden" name="token" value="<?= e($f['token']) ?>"><button class="s" name="accion" value="desbloquear"><?= $est === 'revisar' ? 'Desbloquear' : 'Quitar aviso' ?></button></form>
        <?php endif; ?>
        <?php if ($est !== 'baja'): ?>
          <form method="post"><?= $oculto ?><input type="hidden" name="token" value="<?= e($f['token']) ?>"><button class="s r" name="accion" value="pedir_baja">Dar de baja</button></form>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$filas): ?><p class="nota">No hay licencias con estos filtros.</p><?php endif; ?>
  </div>

  <div class="fila">
    <form method="post" style="margin:0"><?= $oculto ?><button name="accion" value="csv">Descargar Excel</button></form>
    <?php if ($loteV !== ''): ?>
      <form method="post" style="margin:0"><?= $oculto ?><button class="r" name="accion" value="pedir_baja_lote">Dar de baja el lote «<?= e($loteV) ?>»</button></form>
    <?php endif; ?>
  </div>
</section>

<!-- Crear licencias -->
<form method="post" class="card"><?= $oculto ?>
  <h2>Crear licencias</h2>
  <div class="busca" style="grid-template-columns:110px 1fr auto;margin-top:0;align-items:end">
    <div><label for="cantidad">Cuántas</label><input id="cantidad" name="cantidad" type="number" min="1" max="200" value="1"></div>
    <div><label for="lote">Lote</label><input id="lote" name="lote" maxlength="40" placeholder="p. ej. amigos, web, tarjetas-tienda-1"></div>
    <button class="p" name="accion" value="crear">Crear</button>
  </div>
</form>

<!-- Mantenimiento -->
<form method="post" class="card"><?= $oculto ?>
  <h2>Mantenimiento</h2>
  <p class="nota" style="margin:0">Solo hace falta la primera vez o si algo falla con la base de datos.</p>
  <div class="fila"><button name="accion" value="instalar">Instalar / comprobar tablas</button></div>
</form>
<p style="text-align:center;margin:0"><a class="btn" href="admin.php" style="text-decoration:none;display:inline-block">Salir</a></p>
<?php endif; ?>

<p class="pie">Numeria © 2026 · panel privado</p>
</main></body></html>
