<?php

namespace ForgeMesh\PayPerCrawl\Laravel\Http\Controllers;

use ForgeMesh\PayPerCrawl\Discovery;
use ForgeMesh\PayPerCrawl\Gate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DiscoveryController
{
    public function __invoke(Request $request, Gate $gate, string $doc): Response
    {
        $settings = $gate->settings();
        $settings['llms_pages'] = $settings['llms_pages'] ?: [];
        $d = new Discovery($settings, $request->getSchemeAndHttpHost(), 'forgemesh-pay-per-crawl-laravel');
        $found = $d->resolve($doc);
        abort_if($found === null, 404);
        return new Response($found['body'], 200, Discovery::headers($found['type']));
    }
}
