<?php

namespace ForgeMesh\PayPerCrawl\Laravel\Http\Middleware;

use Closure;
use ForgeMesh\PayPerCrawl\Gate;
use ForgeMesh\PayPerCrawl\Request as GateRequest;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PayPerCrawl
{
    public function __construct(private Gate $gate)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $settings = $this->gate->settings();
        if (!empty($settings['skip_authenticated']) && $request->hasSession() && $request->user()) {
            return $next($request);
        }

        $result = $this->gate->handle(new GateRequest(
            $request->getMethod(),
            $request->getRequestUri(),
            $request->getSchemeAndHttpHost() . $request->getRequestUri(),
            array_map(static fn ($v) => is_array($v) ? (string) ($v[0] ?? '') : (string) $v, $request->headers->all()),
            (string) $request->ip()
        ));

        if ($result->respond) {
            // Symfony sends the headers first and the status line last, so the 402 survives
            // the SAPI rewriting the status to 401 when WWW-Authenticate is present.
            return new \Illuminate\Http\Response($result->body, $result->status, $result->headers);
        }

        $response = $next($request);
        foreach ($result->passHeaders as $name => $value) {
            $response->headers->set($name, $value);
        }
        return $response;
    }
}
