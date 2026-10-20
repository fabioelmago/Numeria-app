<?php
// Funciones comunes de la API de licencias de Numeria.
// Este fichero no se puede abrir desde el navegador (lo bloquea .htaccess
// y además corta aquí si alguien lo llama directamente).
if (!defined('NUMERIA')) { http_response_code(404); exit; }

const FORMATO_TOKEN = '/^NUM-[A-Z0-9]{4}-[A-Z0-9]{4}$/';
const TOKEN_PRUEBAS = 'NUM-TEST-0001';
const MINUTOS_GRACIA_HUELLA = 30;

function cfg($clave = null) {
    static $c = null;
    if ($c === null) {
        // La configuración vive FUERA de public_html (una carpeta por encima),
        // para que los despliegues desde GitHub —que vacían public_html— no la
        // borren nunca, y para que no se pueda abrir desde internet.
        //   public_html/api/_lib.php  →  ../../numeria-config.php
        $fuera = dirname(__DIR__, 2) . '/numeria-config.php';
        $dentro = __DIR__ . '/config.php';            // solo para pruebas locales
        $f = is_file($fuera) ? $fuera : (is_file($dentro) ? $dentro : null);
        if (!$f) responder(['error' => 'servidor sin configurar'], 503);
        $c = require $f;
    }
    return $clave === null ? $c : ($c[$clave] ?? null);
}

function db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = cfg();
    $dsn = $c['db_dsn'] ?? ('mysql:host=' . $c['db_host'] . ';dbname=' . $c['db_nombre'] . ';charset=utf8mb4');
    try {
        $pdo = new PDO($dsn, $c['db_usuario'] ?? null, $c['db_clave'] ?? null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $e) {
        error_log('Numeria BD: ' . $e->getMessage());
        responder(['error' => 'servidor no disponible'], 503);
    }
    return $pdo;
}

function ahora() { return gmdate('Y-m-d H:i:s'); }

function responder($datos, $codigo = 200) {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

// Lee el cuerpo JSON de la petición (máx. 4 KB: aquí nunca llega nada grande).
function entrada() {
    $raw = file_get_contents('php://input', false, null, 0, 4096);
    $d = json_decode($raw ?: '{}', true);
    return is_array($d) ? $d : [];
}

function solo_post() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') responder(['error' => 'metodo no permitido'], 405);
}

function token_limpio($t) { return strtoupper(trim((string)$t)); }

function ip_cliente() { return substr($_SERVER['REMOTE_ADDR'] ?? '0', 0, 64); }

// Límite de intentos por IP y acción (contra quien pruebe claves a lo loco).
function limitar($accion, $maximo, $segundos) {
    $pdo = db();
    $t = time();
    $pdo->prepare('DELETE FROM intentos WHERE momento < ?')->execute([$t - 3600]);
    $q = $pdo->prepare('SELECT COUNT(*) FROM intentos WHERE ip = ? AND accion = ? AND momento > ?');
    $q->execute([ip_cliente(), $accion, $t - $segundos]);
    if ((int)$q->fetchColumn() >= $maximo) responder(['error' => 'demasiados intentos, espera un momento'], 429);
    $pdo->prepare('INSERT INTO intentos (ip, accion, momento) VALUES (?, ?, ?)')->execute([ip_cliente(), $accion, $t]);
}

function obtener_licencia($token) {
    $q = db()->prepare('SELECT * FROM licencias WHERE token = ?');
    $q->execute([$token]);
    return $q->fetch() ?: null;
}

function dentro_del_margen($fecha, $minutos) {
    if (!$fecha) return false;
    $t = strtotime($fecha . ' UTC');
    return $t !== false && (time() - $t) < $minutos * 60;
}

function huella_valida($h) { return is_string($h) && preg_match('/^[A-Za-z0-9\-]{8,128}$/', $h); }

// Crea las tablas si no existen (lo lanza admin.php → "Instalar").
function instalar_tablas() {
    $pdo = db();
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $id = $mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $fin = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
    $pdo->exec("CREATE TABLE IF NOT EXISTS clientes (
        id $id,
        email VARCHAR(190) NOT NULL UNIQUE,
        nombre VARCHAR(120) NOT NULL DEFAULT '',
        creado DATETIME NOT NULL
    )$fin");
    $pdo->exec("CREATE TABLE IF NOT EXISTS licencias (
        token VARCHAR(20) NOT NULL PRIMARY KEY,
        estado VARCHAR(12) NOT NULL DEFAULT 'sin_usar',
        cliente_id INT NULL,
        huella VARCHAR(128) NULL,
        fecha_activacion DATETIME NULL,
        ultima_vez DATETIME NULL,
        motivo VARCHAR(190) NULL,
        lote VARCHAR(40) NULL,
        creado DATETIME NOT NULL
    )$fin");
    $pdo->exec("CREATE TABLE IF NOT EXISTS intentos (
        ip VARCHAR(64) NOT NULL,
        accion VARCHAR(20) NOT NULL,
        momento INT NOT NULL
    )$fin");
}

function obtener_o_crear_cliente($email, $nombre) {
    $pdo = db();
    $q = $pdo->prepare('SELECT * FROM clientes WHERE email = ?');
    $q->execute([$email]);
    if ($f = $q->fetch()) return $f;
    try {
        $pdo->prepare('INSERT INTO clientes (email, nombre, creado) VALUES (?, ?, ?)')->execute([$email, $nombre, ahora()]);
    } catch (PDOException $e) { /* otro alta a la vez con el mismo email: se relee abajo */ }
    $q->execute([$email]);
    return $q->fetch();
}

function mandar_email_bienvenida($destino, $nombre) {
    $de = cfg('email_remitente');
    if (!$de) return false;
    $enlace = cfg('enlace_app') ?: '/';
    $n = $nombre !== '' ? ', ' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') : '';
    $html = '<div style="font-family:-apple-system,sans-serif;max-width:480px;margin:0 auto;">'
        . '<h2 style="color:#1C1C1E;">¡Bienvenido a Numeria' . $n . '!</h2>'
        . '<p>Tu licencia ya está activada en tu dispositivo. Guarda este correo: es tu enlace de entrada.</p>'
        . '<p><a href="' . htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8') . '" style="color:#FFD60A;background:#1C1C1E;padding:12px 20px;border-radius:10px;text-decoration:none;display:inline-block;">Abrir Numeria</a></p>'
        . '</div>';
    $cab = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: Numeria <$de>\r\n";
    return @mail($destino, '=?UTF-8?B?' . base64_encode('Bienvenido a Numeria') . '?=', $html, $cab);
}
