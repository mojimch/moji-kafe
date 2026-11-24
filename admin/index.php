<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$destination = is_logged_in() ? '/dashboard.php' : '/login.php';
header('Location: ' . admin_base_url() . $destination, true, 302);
exit;
