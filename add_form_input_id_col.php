<?php

require_once 'vendor/autoload.php';

$host = 'aws-0-ap-northeast-1.pooler.supabase.com';
$port = '6543';
$dbname = 'postgres';
$user = 'postgres.skwilxaeqflmmgbcqsni';
$password = 'kOZvIwV6RzDF2bbP';

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (\Exception $e) {
    die("Database Connection Error: " . $e->getMessage() . "\n");
}

// Add column form_input_id if not exists
try {
    $pdo->exec("ALTER TABLE graduate_ledgers ADD COLUMN IF NOT EXISTS form_input_id BIGINT NULL REFERENCES form_inputs(id) ON DELETE SET NULL;");
    echo "✅ Successfully ensured 'form_input_id' column exists in graduate_ledgers table!\n";
} catch (\Exception $e) {
    echo "Notice: " . $e->getMessage() . "\n";
}
