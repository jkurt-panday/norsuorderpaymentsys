<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$courses = \App\Models\Course::orderBy('code')->get(['id', 'code']);
echo "Total courses in graduate_course table: " . $courses->count() . "\n\n";
foreach ($courses as $c) {
    echo "ID: {$c->id} | '{$c->code}'\n";
}
