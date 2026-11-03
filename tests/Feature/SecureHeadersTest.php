<?php

namespace Tests\Feature;

use App\Http\Middleware\SecureHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SecureHeadersTest extends TestCase
{
    public function test_it_sets_security_headers_directly(): void
    {
        $middleware = new SecureHeaders;
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
    }

    public function test_web_middleware_group_applies_security_headers(): void
    {
        Route::middleware('web')->get('/_test_security_headers', function () {
            return response('OK');
        });

        $response = $this->get('/_test_security_headers');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
