<?php
/**
 * Digital Student Clearance System
 * Configuration File
 */

// Timezone Setting
date_default_timezone_set('Africa/Lagos');

// SQLite Local Database Configuration
define('DB_FILE', __DIR__ . '/database.sqlite');

// SMTP Credentials for PHPMailer
define('SMTP_HOST', 'live.smtp.mailtrap.io');
define('SMTP_PORT', 587);
define('SMTP_USER', 'smtp@mailtrap.io');
define('SMTP_PASS', 'e22232dda7bd68b4f89186aade9d3937');
define('SMTP_FROM', 'clearance@calebuniversity.edu.ng');

// Session Initialization
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
