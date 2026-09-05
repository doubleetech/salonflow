<?php
/**
 * SalonFlow Configuration
 * Edit the values below to match your local XAMPP / MariaDB setup.
 */

// --- Database ---
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3307');           // your MariaDB is on 3307, not the default 3306
define('DB_NAME', 'salonflow');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// --- App ---
define('APP_NAME', 'Now Barbershop');
define('APP_ENV', 'development');    // 'development' | 'production'
define('APP_URL', 'http://localhost/salonflow/public');
define('APP_TIMEZONE', 'Africa/Lagos');

// Environment: 'development' or 'production'
define('ENV', 'development'); // Will Change to 'production' when live

// --- Asset cache-busting ---
// Bump this any time style.css or app.js changes. Browsers cache static
// files aggressively; without a changing version tag on the URL, a user's
// browser can keep serving an OLD copy of these files indefinitely, even
// after you've updated them on the server. Appending ?v=X forces the
// browser to treat it as a new file the moment X changes.
define('ASSET_VERSION', '8');

// --- Session ---
define('SESSION_NAME', 'salonflow_session');
define('SESSION_LIFETIME', 60 * 60 * 8); // 8 hours

// --- Email Configuration (using Gmail SMTP) ---
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'teeboss017@gmail.com');
define('SMTP_PASSWORD', 'nkga yarr qdwk kudj'); // Replace with your app password
define('SMTP_FROM_EMAIL', 'teeboss017@gmail.com');
define('SMTP_FROM_NAME', 'Now Barbershop');

date_default_timezone_set(APP_TIMEZONE);

// Show errors only in development
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}