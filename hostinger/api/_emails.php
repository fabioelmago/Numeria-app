<?php
// Emails de Numeria: plantilla única (cabecera negra con el logo, tarjeta blanca,
// pie con soporte) en 5 idiomas, con versión HTML + texto plano.
// Hecha con tablas y estilos en línea porque Gmail/Outlook ignoran casi todo lo demás.
if (!defined('NUMERIA')) { http_response_code(404); exit; }

const EMAIL_TEXTOS = [
    'es' => [
        'cod_asunto' => 'Tu código de Numeria', 'cod_pre' => 'Tu código para pasar tu licencia a tu móvil nuevo',
        'hola' => 'Hola, %s', 'hola0' => 'Hola',
        'cod_p' => 'Has pedido pasar tu licencia de Numeria a un móvil nuevo. Escribe este código en la app:',
        'cod_nota' => 'Caduca en %d minutos.',
        'cod_seg' => 'Si no lo has pedido tú, ignora este correo: tu licencia sigue segura en tu móvil.',
        'bv_asunto' => 'Te damos la bienvenida a Numeria', 'bv_pre' => 'Tu licencia ya está activada',
        'bv_tit' => 'Te damos la bienvenida a Numeria, %s', 'bv_tit0' => 'Te damos la bienvenida a Numeria',
        'bv_p1' => 'Tu licencia ya está activada en tu móvil. Guarda este correo: aquí tienes tu clave por si algún día la necesitas.',
        'bv_clave' => 'Tu clave',
        'bv_p2' => 'Te falta un último paso dentro de la app: conectar tu cuenta de Telegram.',
        'bv_boton' => 'Abrir Numeria',
        'bv_p3' => '¿Cambias de móvil? Abre Numeria en el nuevo, escribe tu clave y pulsa «¿Cambiaste de móvil? Recupera tu licencia».',
        'pie' => 'Dudas o problemas: %s',
    ],
    'en' => [
        'cod_asunto' => 'Your Numeria code', 'cod_pre' => 'Your code to move your licence to your new phone',
        'hola' => 'Hi %s', 'hola0' => 'Hi',
        'cod_p' => 'You asked to move your Numeria licence to a new phone. Enter this code in the app:',
        'cod_nota' => 'It expires in %d minutes.',
        'cod_seg' => 'If you didn’t request it, ignore this email: your licence is still safe on your phone.',
        'bv_asunto' => 'Welcome to Numeria', 'bv_pre' => 'Your licence is now active',
        'bv_tit' => 'Welcome to Numeria, %s', 'bv_tit0' => 'Welcome to Numeria',
        'bv_p1' => 'Your licence is now active on your phone. Keep this email: here is your key in case you ever need it.',
        'bv_clave' => 'Your key',
        'bv_p2' => 'One last step inside the app: connect your Telegram account.',
        'bv_boton' => 'Open Numeria',
        'bv_p3' => 'Changing phone? Open Numeria on the new one, enter your key and tap “New phone? Recover your licence”.',
        'pie' => 'Questions or problems: %s',
    ],
    'fr' => [
        'cod_asunto' => 'Ton code Numeria', 'cod_pre' => 'Ton code pour transférer ta licence sur ton nouveau téléphone',
        'hola' => 'Bonjour %s', 'hola0' => 'Bonjour',
        'cod_p' => 'Tu as demandé à transférer ta licence Numeria sur un nouveau téléphone. Saisis ce code dans l’app :',
        'cod_nota' => 'Il expire dans %d minutes.',
        'cod_seg' => 'Si tu ne l’as pas demandé, ignore cet e-mail : ta licence reste en sécurité sur ton téléphone.',
        'bv_asunto' => 'Bienvenue sur Numeria', 'bv_pre' => 'Ta licence est activée',
        'bv_tit' => 'Bienvenue sur Numeria, %s', 'bv_tit0' => 'Bienvenue sur Numeria',
        'bv_p1' => 'Ta licence est maintenant activée sur ton téléphone. Garde cet e-mail : voici ta clé si tu en as besoin un jour.',
        'bv_clave' => 'Ta clé',
        'bv_p2' => 'Il te reste une dernière étape dans l’app : connecter ton compte Telegram.',
        'bv_boton' => 'Ouvrir Numeria',
        'bv_p3' => 'Tu changes de téléphone ? Ouvre Numeria sur le nouveau, saisis ta clé et touche « Nouveau téléphone ? Récupère ta licence ».',
        'pie' => 'Questions ou problèmes : %s',
    ],
    'it' => [
        'cod_asunto' => 'Il tuo codice Numeria', 'cod_pre' => 'Il tuo codice per spostare la licenza sul nuovo telefono',
        'hola' => 'Ciao %s', 'hola0' => 'Ciao',
        'cod_p' => 'Hai chiesto di spostare la tua licenza Numeria su un nuovo telefono. Inserisci questo codice nell’app:',
        'cod_nota' => 'Scade tra %d minuti.',
        'cod_seg' => 'Se non l’hai richiesto tu, ignora questa email: la tua licenza resta al sicuro sul tuo telefono.',
        'bv_asunto' => 'Ti diamo il benvenuto in Numeria', 'bv_pre' => 'La tua licenza è attiva',
        'bv_tit' => 'Ti diamo il benvenuto in Numeria, %s', 'bv_tit0' => 'Ti diamo il benvenuto in Numeria',
        'bv_p1' => 'La tua licenza è ora attiva sul tuo telefono. Conserva questa email: ecco la tua chiave, nel caso ti serva.',
        'bv_clave' => 'La tua chiave',
        'bv_p2' => 'Ti manca un ultimo passo nell’app: collegare il tuo account Telegram.',
        'bv_boton' => 'Apri Numeria',
        'bv_p3' => 'Cambi telefono? Apri Numeria sul nuovo, inserisci la chiave e tocca «Hai cambiato telefono? Recupera la tua licenza».',
        'pie' => 'Dubbi o problemi: %s',
    ],
    'de' => [
        'cod_asunto' => 'Dein Numeria-Code', 'cod_pre' => 'Dein Code, um deine Lizenz auf dein neues Handy zu übertragen',
        'hola' => 'Hallo %s', 'hola0' => 'Hallo',
        'cod_p' => 'Du möchtest deine Numeria-Lizenz auf ein neues Handy übertragen. Gib diesen Code in der App ein:',
        'cod_nota' => 'Er läuft in %d Minuten ab.',
        'cod_seg' => 'Wenn du das nicht angefordert hast, ignoriere diese E-Mail: Deine Lizenz bleibt sicher auf deinem Handy.',
        'bv_asunto' => 'Willkommen bei Numeria', 'bv_pre' => 'Deine Lizenz ist aktiviert',
        'bv_tit' => 'Willkommen bei Numeria, %s', 'bv_tit0' => 'Willkommen bei Numeria',
        'bv_p1' => 'Deine Lizenz ist jetzt auf deinem Handy aktiviert. Bewahre diese E-Mail auf: Hier ist dein Schlüssel, falls du ihn einmal brauchst.',
        'bv_clave' => 'Dein Schlüssel',
        'bv_p2' => 'Ein letzter Schritt in der App: Verbinde dein Telegram-Konto.',
        'bv_boton' => 'Numeria öffnen',
        'bv_p3' => 'Neues Handy? Öffne Numeria auf dem neuen, gib deinen Schlüssel ein und tippe auf „Neues Handy? Hol dir deine Lizenz zurück“.',
        'pie' => 'Fragen oder Probleme: %s',
    ],
];

function email_t($idioma) { return EMAIL_TEXTOS[$idioma] ?? EMAIL_TEXTOS['es']; }
function eh($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Construye el HTML del email. $b = bloques en orden:
//   ['h', texto]            título
//   ['p', texto]            párrafo
//   ['caja', texto, etiq]   recuadro grande (código o clave), con etiqueta opcional encima
//   ['nota', texto]         texto pequeño gris
//   ['boton', texto, url]   botón negro
function plantilla_email($pre, $b) {
    $base = rtrim((string)(cfg('enlace_app') ?: '/'), '/') . '/';
    // Cabecera: aurora real de la app con el logo plata encima (una sola imagen,
    // porque los degradados por código no los respetan Gmail ni Outlook).
    $cab = $base . 'email/cabecera.jpg';
    $sop = (string)(cfg('email_soporte') ?: cfg('email_remitente'));
    $F = "font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;";
    $c = '';
    foreach ($b as $x) {
        switch ($x[0]) {
            case 'h': $c .= '<h1 style="' . $F . 'margin:0 0 16px;font-size:22px;line-height:1.3;font-weight:700;color:#1C1C1E;">' . eh($x[1]) . '</h1>'; break;
            case 'p': $c .= '<p style="' . $F . 'margin:0 0 18px;font-size:16px;line-height:1.55;color:#3A3A3C;">' . eh($x[1]) . '</p>'; break;
            case 'nota': $c .= '<p style="' . $F . 'margin:0 0 18px;font-size:13px;line-height:1.5;color:#8E8E93;">' . eh($x[1]) . '</p>'; break;
            case 'caja':
                $c .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:6px auto 20px;"><tr><td align="center" style="background:#F2F2F5;border:1px solid #E5E5EA;border-radius:14px;padding:16px 26px;">'
                    . (isset($x[2]) ? '<div style="' . $F . 'font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8E8E93;margin-bottom:6px;">' . eh($x[2]) . '</div>' : '')
                    . '<div style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:' . (isset($x[2]) ? '24' : '34') . 'px;font-weight:700;letter-spacing:' . (isset($x[2]) ? '3' : '8') . 'px;color:#1C1C1E;white-space:nowrap;">' . eh($x[1]) . '</div></td></tr></table>';
                break;
            case 'boton':
                $c .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:4px auto 22px;"><tr><td align="center" bgcolor="#07070B" style="border-radius:12px;">'
                    . '<a href="' . eh($x[2]) . '" style="' . $F . 'display:inline-block;padding:14px 30px;font-size:16px;font-weight:600;color:#FFFFFF;text-decoration:none;border-radius:12px;">' . eh($x[1]) . '</a></td></tr></table>';
                break;
        }
    }
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><title>Numeria</title></head>'
        . '<body style="margin:0;padding:0;background:#F2F2F5;">'
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . eh($pre) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F2F2F5;"><tr><td align="center" style="padding:28px 12px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;background:#FFFFFF;border-radius:18px;overflow:hidden;">'
        . '<tr><td align="center" bgcolor="#07070B" style="background:#07070B;padding:0;line-height:0;font-size:0;">'
        . '<img src="' . eh($cab) . '" width="560" alt="NUMERIA" style="display:block;width:100%;max-width:560px;height:auto;border:0;color:#D1D1D6;font:600 22px/60px Helvetica,Arial,sans-serif;letter-spacing:6px;text-align:center;"></td></tr>'
        . '<tr><td style="padding:34px 32px 14px;">' . $c . '</td></tr>'
        . ($sop ? '<tr><td style="padding:18px 32px 26px;border-top:1px solid #EFEFF2;' . $F . 'font-size:13px;line-height:1.5;color:#8E8E93;">'
            . eh(sprintf(email_t($GLOBALS['__email_idioma'] ?? 'es')['pie'], '')) . '<a href="mailto:' . eh($sop) . '" style="color:#1C1C1E;text-decoration:underline;">' . eh($sop) . '</a></td></tr>' : '')
        . '</table>'
        . '<p style="' . $F . 'margin:16px 0 0;font-size:12px;color:#AEAEB2;">© 2026 Numeria · ' . eh(parse_url($base, PHP_URL_HOST) ?: 'numeriaapp.com') . '</p>'
        . '</td></tr></table></body></html>';
}

// Envía el email como multipart (texto plano + HTML): mejor entrega y se lee en cualquier programa.
function enviar_email($destino, $asunto, $html, $texto) {
    $de = cfg('email_remitente');
    if (!$de) return false;
    $lim = 'nmr_' . bin2hex(random_bytes(8));
    $cab = "MIME-Version: 1.0\r\n"
         . "From: Numeria <$de>\r\n"
         . "Reply-To: " . (cfg('email_soporte') ?: $de) . "\r\n"
         . "Content-Type: multipart/alternative; boundary=\"$lim\"\r\n";
    $cuerpo = "--$lim\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($texto))
            . "--$lim\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . "--$lim--\r\n";
    return @mail($destino, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $cuerpo, $cab);
}

function pie_texto($t) {
    $sop = (string)(cfg('email_soporte') ?: cfg('email_remitente'));
    return ($sop ? "\n\n—\n" . sprintf($t['pie'], $sop) : '') . "\n© 2026 Numeria";
}

// Email con el código para pasar la licencia a un móvil nuevo.
// Devuelve [asunto, html, texto]; mandar_email_codigo() lo envía.
function email_codigo($codigo, $idioma, $nombre) {
    $t = email_t($idioma); $GLOBALS['__email_idioma'] = $idioma;
    $hola = $nombre !== '' ? sprintf($t['hola'], $nombre) : $t['hola0'];
    $visible = substr($codigo, 0, 3) . ' ' . substr($codigo, 3);
    $html = plantilla_email($t['cod_pre'], [
        ['h', $hola],
        ['p', $t['cod_p']],
        ['caja', $visible],
        ['nota', sprintf($t['cod_nota'], MINUTOS_CODIGO) . ' ' . $t['cod_seg']],
    ]);
    $texto = "$hola\n\n{$t['cod_p']}\n\n    $visible\n\n" . sprintf($t['cod_nota'], MINUTOS_CODIGO) . ' ' . $t['cod_seg'] . pie_texto($t);
    return [$t['cod_asunto'], $html, $texto];
}

function email_bienvenida($nombre, $token, $idioma) {
    $t = email_t($idioma); $GLOBALS['__email_idioma'] = $idioma;
    $tit = $nombre !== '' ? sprintf($t['bv_tit'], $nombre) : $t['bv_tit0'];
    $url = rtrim((string)(cfg('enlace_app') ?: '/'), '/') . '/';
    $html = plantilla_email($t['bv_pre'], [
        ['h', $tit],
        ['p', $t['bv_p1']],
        ['caja', $token, $t['bv_clave']],
        ['p', $t['bv_p2']],
        ['boton', $t['bv_boton'], $url],
        ['nota', $t['bv_p3']],
    ]);
    $texto = "$tit\n\n{$t['bv_p1']}\n\n{$t['bv_clave']}: $token\n\n{$t['bv_p2']}\n{$t['bv_boton']}: $url\n\n{$t['bv_p3']}" . pie_texto($t);
    return [$t['bv_asunto'], $html, $texto];
}

function mandar_email_codigo($destino, $codigo, $idioma, $nombre = '') {
    [$a, $h, $x] = email_codigo($codigo, $idioma, $nombre);
    return enviar_email($destino, $a, $h, $x);
}

function mandar_email_bienvenida($destino, $nombre, $token = '', $idioma = 'es') {
    [$a, $h, $x] = email_bienvenida($nombre, $token, $idioma);
    return enviar_email($destino, $a, $h, $x);
}
