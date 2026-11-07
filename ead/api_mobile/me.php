<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
$user = auth_user($pdo);
json_out(true, ['user' => normalize_user($pdo, $user)]);
