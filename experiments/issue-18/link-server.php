<?php

declare(strict_types=1);

// Local-only fixture for the recheck CLI regression tests. No remote traffic.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
file_put_contents('requests.txt', $path . "\n", FILE_APPEND);
http_response_code($path === '/gone' ? 404 : 200);
