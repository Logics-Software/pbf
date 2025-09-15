<?php
// Database configuration
// Adjust these values to your local MySQL setup
define('DB_HOST', 'localhost');
define('DB_NAME', 'pbf');
define('DB_USER', 'root');
define('DB_PASS', '051199');

// App settings
define('APP_NAME', 'Farmasi Online');
define('APP_BASE_URL', '/pbf'); // Set to the web path where this app is hosted

// Session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
?>
