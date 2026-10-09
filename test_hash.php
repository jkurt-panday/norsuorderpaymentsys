<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Hash;
use App\Models\User;

$plain = 'troy123';
$hashedOnce = Hash::make($plain);

echo 'isHashed(plain troy123): ' . var_export(Hash::isHashed($plain), true) . PHP_EOL;
echo 'isHashed(hashedOnce): ' . var_export(Hash::isHashed($hashedOnce), true) . PHP_EOL;
echo 'check(troy123, hashedOnce): ' . var_export(Hash::check($plain, $hashedOnce), true) . PHP_EOL;

// Simulate the cast: assign plain to a model attribute
$u = new User();
$u->password = $plain;  // triggers castAttributeAsHashedString
echo 'After cast, password: ' . $u->password . PHP_EOL;
echo 'check(troy123, afterCast): ' . var_export(Hash::check($plain, $u->password), true) . PHP_EOL;

// Simulate assigning already-hashed value (as Hash::make + cast)
$u2 = new User();
$u2->password = Hash::make($plain);
echo 'After cast2, password: ' . $u2->password . PHP_EOL;
echo 'check(troy123, afterCast2): ' . var_export(Hash::check($plain, $u2->password), true) . PHP_EOL;