<?php

use App\Models\Course;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$courses = Course::orderBy('code')->get(['id', 'code']);
echo 'Total courses in graduate_course table: '.$courses->count()."\n\n";
foreach ($courses as $c) {
    echo "ID: {$c->id} | '{$c->code}'\n";
}
