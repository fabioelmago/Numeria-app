<?php
// =====================================================================
// CONFIGURACIÓN DE NUMERIA (Hostinger)
//
// Este fichero es solo la PLANTILLA. El de verdad se llama
//   numeria-config.php
// y va FUERA de public_html: en la carpeta de encima, la del dominio
// (donde está el aviso DO_NOT_UPLOAD_HERE). Ahí los despliegues desde
// GitHub no lo borran y nadie puede abrirlo desde internet.
// Copia este texto en ese fichero y rellena los datos de la base de datos
// (hPanel → Bases de datos → MySQL) tal cual aparecen, con mayúsculas.
// =====================================================================

return [
    // --- Base de datos MySQL (hPanel → Bases de datos) ---
    // En Hostinger el host casi siempre es "localhost".
    'db_host'     => 'localhost',
    'db_nombre'   => 'u000000000_numeria',   // nombre completo, con el prefijo u...
    'db_usuario'  => 'u000000000_numeria',
    'db_clave'    => 'CAMBIA_ESTO',

    // --- Clave de la página de administración (/api/admin.php) ---
    // Pon una larga e inventada (mínimo 16 caracteres). Con ella se crean
    // licencias y se desbloquean móviles. No la compartas con nadie.
    'admin_clave' => 'CAMBIA_ESTO_POR_UNA_CLAVE_LARGA',

    // --- Fase de pruebas ---
    // true  = una clave con formato válido que no existe se crea sola al
    //         escribirla en el registro (como PRUEBAS_ABIERTAS=1 en Render).
    // false = solo funcionan las licencias creadas desde admin.php.
    // ANTES DE VENDER: false.
    'pruebas_abiertas' => false,

    // --- Email de bienvenida (opcional) ---
    // Déjalo vacío para no mandar email. Cuando tengas un correo de tu
    // dominio en Hostinger (p. ej. hola@tudominio.com), ponlo aquí.
    'email_remitente' => '',

    // Enlace que va en el email de bienvenida (la dirección de la app).
    'enlace_app' => 'https://TU-DOMINIO/',
];
