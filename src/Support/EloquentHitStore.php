<?php

namespace ForgeMesh\PayPerCrawl\Laravel\Support;

use ForgeMesh\PayPerCrawl\Contracts\HitStoreInterface;
use ForgeMesh\PayPerCrawl\Laravel\Models\Hit;

final class EloquentHitStore implements HitStoreInterface
{
    private const COLUMNS = ['ts', 'ua_class', 'bot_name', 'verified', 'method', 'path', 'status', 'rail', 'amount_units', 'tx_hash', 'payer', 'reason', 'ip_hash', 'ua'];

    public function record(array $row): int
    {
        $row = array_intersect_key($row, array_flip(self::COLUMNS)) + [
            'bot_name' => '', 'method' => 'GET', 'path' => '/', 'rail' => '', 'amount_units' => 0,
            'tx_hash' => '', 'payer' => '', 'reason' => '', 'ip_hash' => '', 'ua' => '',
        ];
        $row['bot_name'] = substr((string) $row['bot_name'], 0, 64);
        $row['path'] = substr((string) $row['path'], 0, 255);
        $row['ua'] = substr((string) $row['ua'], 0, 255);
        $row['reason'] = substr((string) $row['reason'], 0, 64);
        return (int) Hit::query()->create($row)->getKey();
    }

    public function dailyRollup(string $day): array
    {
        return Hit::query()
            ->whereBetween('ts', [$day . ' 00:00:00', $day . ' 23:59:59'])
            ->groupBy('bot_name', 'ua_class')
            ->orderByDesc('hits')
            ->limit(500)
            ->selectRaw(
                "bot_name, ua_class, COUNT(*) as hits,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
                SUM(CASE WHEN status = 'unpaid' THEN 1 ELSE 0 END) as unpaid,
                SUM(CASE WHEN status IN ('rejected','settle_failed') THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'monitor' THEN 1 ELSE 0 END) as monitor,
                SUM(CASE WHEN status = 'paid' THEN amount_units ELSE 0 END) as revenue_units,
                SUM(CASE WHEN status = 'monitor' THEN amount_units ELSE 0 END) as would_units,
                COUNT(DISTINCT ip_hash) as sources"
            )
            ->get()
            ->map(fn ($r) => [
                'bot_name' => (string) $r->bot_name,
                'ua_class' => (string) $r->ua_class,
                'hits' => (int) $r->hits,
                'paid' => (int) $r->paid,
                'unpaid' => (int) $r->unpaid,
                'rejected' => (int) $r->rejected,
                'monitor' => (int) $r->monitor,
                'revenue_units' => (int) $r->revenue_units,
                'would_units' => (int) $r->would_units,
                'sources' => (int) $r->sources,
            ])
            ->all();
    }

    public function settlementsAfter(int $afterId, int $limit): array
    {
        return Hit::query()
            ->where('status', 'paid')
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'ts', 'bot_name', 'ua_class', 'path', 'rail', 'amount_units', 'tx_hash', 'payer'])
            ->map(fn ($r) => $r->only(['id', 'ts', 'bot_name', 'ua_class', 'path', 'rail', 'amount_units', 'tx_hash', 'payer']))
            ->all();
    }
}
