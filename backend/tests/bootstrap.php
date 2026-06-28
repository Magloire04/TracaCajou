<?php

/**
 * Bootstrap PHPUnit.
 *
 * Sur Windows, PHP OpenSSL a besoin de trouver openssl.cnf avant
 * l'initialisation de l'extension. On positionne OPENSSL_CONF ici,
 * avant le chargement de l'autoloader, pour que openssl_pkey_new()
 * puisse générer des clés EC (secp384r1) dans les tests unitaires.
 */
if (PHP_OS_FAMILY === 'Windows' && ! getenv('OPENSSL_CONF')) {
    $candidates = [
        'C:\\php\\extras\\ssl\\openssl.cnf',
        'C:\\wamp64\\bin\\apache\\apache2.4.65\\conf\\openssl.cnf',
        'C:\\wamp64\\bin\\php\\php8.5.0\\extras\\ssl\\openssl.cnf',
    ];
    foreach ($candidates as $path) {
        if (file_exists($path)) {
            putenv('OPENSSL_CONF=' . $path);
            $_ENV['OPENSSL_CONF'] = $path;
            break;
        }
    }
}

require __DIR__ . '/../vendor/autoload.php';
