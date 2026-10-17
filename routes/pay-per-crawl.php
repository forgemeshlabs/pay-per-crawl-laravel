<?php

use ForgeMesh\PayPerCrawl\Laravel\Http\Controllers\DiscoveryController;
use Illuminate\Support\Facades\Route;

Route::get('/.well-known/x402.json', DiscoveryController::class)->defaults('doc', '/.well-known/x402.json')->name('pay-per-crawl.manifest');
Route::get('/llms.txt', DiscoveryController::class)->defaults('doc', '/llms.txt')->name('pay-per-crawl.llms');
Route::get('/agents.txt', DiscoveryController::class)->defaults('doc', '/agents.txt')->name('pay-per-crawl.agents');
