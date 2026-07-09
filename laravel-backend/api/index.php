<?php

// On Vercel, only /tmp is writable. Copy SQLite DB there on cold start.
$tmpDb = '/tmp/database.sqlite';
if (!file_exists($tmpDb)) {
    $sourceDb = __DIR__ . '/../database/database.sqlite';
    if (file_exists($sourceDb) && filesize($sourceDb) > 0) {
        copy($sourceDb, $tmpDb);
    } else {
        touch($tmpDb);
    }
}

// Override DB path and other write-paths to /tmp
putenv("DB_DATABASE={$tmpDb}");
$_ENV['DB_DATABASE'] = $tmpDb;
$_SERVER['DB_DATABASE'] = $tmpDb;

// Use /tmp for compiled views
$viewPath = '/tmp/views';
if (!is_dir($viewPath)) {
    mkdir($viewPath, 0777, true);
}
putenv("VIEW_COMPILED_PATH={$viewPath}");
$_ENV['VIEW_COMPILED_PATH'] = $viewPath;

// Boot Laravel
require __DIR__ . '/../public/index.php';
