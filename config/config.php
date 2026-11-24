<?php

declare(strict_types=1);

define('APP_NAME', getenv('APP_NAME') ?: 'Moji Kafe');
define('BASE_URL', rtrim(getenv('BASE_URL') ?: 'http://localhost/moji-kafe/public', '/'));
define('CAFE_ADDRESS', getenv('CAFE_ADDRESS') ?: 'Informasi lokasi belum diatur.');
define('CAFE_PHONE', getenv('CAFE_PHONE') ?: '');
define('CAFE_OPENING_HOURS', getenv('CAFE_OPENING_HOURS') ?: 'Hubungi kafe untuk informasi jam operasional.');

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'moji_kafe');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
