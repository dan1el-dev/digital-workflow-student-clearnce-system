<?php
/**
 * Custom Autoloader for PHPMailer and TCPDF
 * (Used when Composer is not used)
 */

spl_autoload_register(function ($class) {
    // PHPMailer namespace mapping
    if (strpos($class, 'PHPMailer\\PHPMailer\\') === 0) {
        $relative_class = substr($class, strlen('PHPMailer\\PHPMailer\\'));
        $file = __DIR__ . '/phpmailer/src/' . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

    // TCPDF class mapping
    if ($class === 'TCPDF') {
        $file = __DIR__ . '/tcpdf/tcpdf.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
