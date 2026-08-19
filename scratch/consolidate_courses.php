<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\Course;
use Illuminate\Contracts\Console\Kernel;

// Official Master Course List based on NORSU Graduate Programs
$officialCourses = [
    // Doctor of Philosophy
    'Ph.D. in Educational Management',
    'Ph.D. in Mathematics Education',
    'Ph.D. Applied Linguistics',

    // Doctor of Education
    'Ed. D. in Educational Management',
    'Ed. D. in Instruction',
    'Ed. D. in Science Education',
    'Ed. D. in Filipino',
    'Ed. D. in Technology Management',

    // Doctor of Management
    'DM - Human Resource Management',
    'DM - Public Administration',

    // Master's Programs
    'MBA',
    'MPH',

    // Master of Arts
    'MAST',
    'MA Eng',
    'MA Fil',
    'MA Hist',
    'MA Psych',
    'MAMT',
    'MAECE',
    'MAEM',
    'MAPE',
    'MAVE',
    'MA Sped',

    // Master of Science
    'MSAg',
    'MSAg Agronomy',
    'MSAg Animal Science',
    'MSIT',

    // Master of Technological Education
    'MTE Automotive Technology',
    'MTE Civil Technology',
    'MTE Industrial Graphics',
    'MTE Electrical Technology',
    'MTE Electronics Technology',
    'MTE Mechanical Technology',

    // Master of Public Management
    'MPM HRM',
    'MPM LGA',
];

function normalizeCourseString(?string $raw): string
{
    if (! $raw) {
        return 'UNSPECIFIED';
    }
    $raw = trim($raw);
    $upper = strtoupper(preg_replace('/\s+/', ' ', $raw));

    // Doctor of Philosophy
    if (str_contains($upper, 'PH') && (str_contains($upper, 'EM') || str_contains($upper, 'EDUC'))) {
        return 'Ph.D. in Educational Management';
    }
    if (str_contains($upper, 'PH') && (str_contains($upper, 'MATH') || str_contains($upper, 'MAT'))) {
        return 'Ph.D. in Mathematics Education';
    }
    if (str_contains($upper, 'PH') && (str_contains($upper, 'LING') || str_contains($upper, 'A.L') || str_contains($upper, 'APLNG'))) {
        return 'Ph.D. Applied Linguistics';
    }
    if (str_starts_with($upper, 'PH') || str_contains($upper, 'PH.D') || str_contains($upper, 'PH D')) {
        return 'Ph.D. in Educational Management';
    }

    // Doctor of Education
    if (str_contains($upper, 'ED') && str_contains($upper, 'FIL')) {
        return 'Ed. D. in Filipino';
    }
    if (str_contains($upper, 'ED') && str_contains($upper, 'SCI')) {
        return 'Ed. D. in Science Education';
    }
    if (str_contains($upper, 'ED') && (str_contains($upper, 'TECH') || str_contains($upper, 'DMT'))) {
        return 'Ed. D. in Technology Management';
    }
    if (str_contains($upper, 'ED') && str_contains($upper, 'INST')) {
        return 'Ed. D. in Instruction';
    }
    if (str_contains($upper, 'ED. D') || str_contains($upper, 'ED.D') || str_contains($upper, 'EDD') || str_contains($upper, 'DOCTORAL')) {
        return 'Ed. D. in Educational Management';
    }

    // Doctor of Management
    if (str_contains($upper, 'DM') && (str_contains($upper, 'PA') || str_contains($upper, 'PUBLIC'))) {
        return 'DM - Public Administration';
    }
    if (str_contains($upper, 'DM') || str_contains($upper, 'D.M.')) {
        return 'DM - Human Resource Management';
    }

    // Master of Public Management
    if (str_contains($upper, 'MPM') && (str_contains($upper, 'LGA') || str_contains($upper, 'GOV'))) {
        return 'MPM LGA';
    }
    if (str_contains($upper, 'MPM')) {
        return 'MPM HRM';
    }

    // Master of Technological Education
    if (str_contains($upper, 'MTE') || str_contains($upper, 'MATVE')) {
        return 'MTE Automotive Technology';
    }

    // Master of Science
    if (str_contains($upper, 'MSIT') || str_contains($upper, 'MS IT')) {
        return 'MSIT';
    }
    if (str_contains($upper, 'AGRI') || str_contains($upper, 'AG') || str_contains($upper, 'MSA')) {
        return 'MSAg';
    }
    if (str_contains($upper, 'MS') && str_contains($upper, 'MATH')) {
        return 'MAMT';
    }

    // Master of Arts
    if (str_contains($upper, 'MAEM') || str_contains($upper, 'MA ED') || str_contains($upper, 'MAED')) {
        return 'MAEM';
    }
    if (str_contains($upper, 'MAECE') || str_contains($upper, 'MAECD') || str_contains($upper, 'ECED')) {
        return 'MAECE';
    }
    if (str_contains($upper, 'MAMT')) {
        return 'MAMT';
    }
    if (str_contains($upper, 'MAST')) {
        return 'MAST';
    }
    if (str_contains($upper, 'MAPEH') || str_contains($upper, 'MAPE')) {
        return 'MAPE';
    }
    if (str_contains($upper, 'MAVE')) {
        return 'MAVE';
    }
    if (str_contains($upper, 'SPED') || str_contains($upper, 'SPEES')) {
        return 'MA Sped';
    }
    if (str_contains($upper, 'ENG')) {
        return 'MA Eng';
    }
    if (str_contains($upper, 'FIL')) {
        return 'MA Fil';
    }
    if (str_contains($upper, 'HIST')) {
        return 'MA Hist';
    }
    if (str_contains($upper, 'PSYCH') || str_contains($upper, 'PYCH')) {
        return 'MA Psych';
    }
    if (str_contains($upper, 'SOCIO') || str_contains($upper, 'SOC')) {
        return 'MAEM';
    }

    // Business & Health
    if (str_contains($upper, 'MBA')) {
        return 'MBA';
    }
    if (str_contains($upper, 'MPH')) {
        return 'MPH';
    }
    if (str_contains($upper, 'MPA')) {
        return 'DM - Public Administration';
    }

    return 'MAEM'; // Default fallback
}

$allCourses = Course::all();
echo 'Processing '.$allCourses->count()." raw course entries...\n";

// Map each raw course entry to its official code
$mappings = [];
foreach ($allCourses as $course) {
    $normalized = normalizeCourseString($course->code);
    $mappings[$course->id] = [
        'old_id' => $course->id,
        'old_code' => $course->code,
        'new_code' => $normalized,
    ];
}

// Preview counts per official course
$counts = [];
foreach ($mappings as $m) {
    $code = $m['new_code'];
    $counts[$code] = ($counts[$code] ?? 0) + 1;
}

echo "\n--- PROPOSED CONSOLIDATION MAP (".count($counts)." official courses) ---\n";
foreach ($counts as $officialCode => $variantCount) {
    echo "  → '$officialCode': consolidates $variantCount variants\n";
}
