<?php
// =====================================================================
// CONFIGURACIÓN DE NUMERIA (Hostinger)
//
// 1. En el Administrador de archivos de Hostinger, duplica este fichero
//    y llama a la copia  config.php  (en esta misma carpeta /api).
// 2. Rellena en config.php los datos de la base de datos que creaste en
//    hPanel → Bases de datos → MySQL.
// 3. config.php NO se sube nunca a GitHub (está en .gitignore) y el
//    servidor no deja descargarlo (lo bloquea el .htaccess).
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
