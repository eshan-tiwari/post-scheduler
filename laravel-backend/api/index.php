<?php

// Vercel's filesystem is read-only except /tmp.
// Set all write-paths to /tmp before Laravel boots.

// 1. SQLite database → /tmp
$tmpDb = '/tmp/database.sqlite';
if (!file_exists($tmpDb)) {
    $sourceDb = __DIR__ . '/../database/database.sqlite';
    if (file_exists($sourceDb) && filesize($sourceDb) > 0) {
        copy($sourceDb, $tmpDb);
    } else {
        touch($tmpDb);
    }
}
putenv("DB_DATABASE={$tmpDb}");
$_ENV['DB_DATABASE'] = $tmpDb;
$_SERVER['DB_DATABASE'] = $tmpDb;

// 2. Compiled views → /tmp/views
$viewPath = '/tmp/views';
if (!is_dir($viewPath)) mkdir($viewPath, 0777, true);
putenv("VIEW_COMPILED_PATH={$viewPath}");
$_ENV['VIEW_COMPILED_PATH'] = $viewPath;

// 3. Logging → stderr (no file writes)
putenv("LOG_CHANNEL=stderr");
$_ENV['LOG_CHANNEL'] = 'stderr';
$_SERVER['LOG_CHANNEL'] = 'stderr';

// 4. Sessions → cookie (no disk writes)
putenv("SESSION_DRIVER=cookie");
$_ENV['SESSION_DRIVER'] = 'cookie';
$_SERVER['SESSION_DRIVER'] = 'cookie';

// 5. Cache → array (in-memory)
putenv("CACHE_STORE=array");
$_ENV['CACHE_STORE'] = 'array';
$_SERVER['CACHE_STORE'] = 'array';

// 6. Queue → sync
putenv("QUEUE_CONNECTION=sync");
$_ENV['QUEUE_CONNECTION'] = 'sync';
$_SERVER['QUEUE_CONNECTION'] = 'sync';

// Boot Laravel
require __DIR__ . '/../public/index.php';

