<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use Spatie\LaravelPdf\PdfServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    Spatie\LaravelPdf\PdfServiceProvider::class,
];
