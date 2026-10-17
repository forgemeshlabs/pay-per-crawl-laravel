<?php

namespace ForgeMesh\PayPerCrawl\Laravel\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One classified request. status: passed | monitor | unpaid | rejected | settle_failed | paid | blocked.
 *
 * @property int $id
 * @property string $ts UTC
 * @property string $ua_class
 * @property string $bot_name
 * @property string $status
 * @property int $amount_units USDC base units (6 decimals)
 */
class Hit extends Model
{
    protected $table = 'pay_per_crawl_hits';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['verified' => 'boolean', 'amount_units' => 'integer'];
}
