<?php

namespace ForgeMesh\PayPerCrawl\Laravel\Console;

use ForgeMesh\PayPerCrawl\Laravel\Models\Hit;
use Illuminate\Console\Command;

class StatsCommand extends Command
{
    protected $signature = 'pay-per-crawl:stats {--days=7 : Look-back window in days} {--bots=15 : Rows in the per-bot table} {--json : Machine-readable output}';

    protected $description = 'Bot traffic and revenue from the Pay-per-Crawl hit log';

    private const SUMS = "COUNT(*) as hits,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
        SUM(CASE WHEN status = 'unpaid' THEN 1 ELSE 0 END) as unpaid,
        SUM(CASE WHEN status IN ('rejected','settle_failed') THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'monitor' THEN 1 ELSE 0 END) as monitor,
        SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked,
        SUM(CASE WHEN status = 'paid' THEN amount_units ELSE 0 END) as revenue_units,
        SUM(CASE WHEN status = 'monitor' THEN amount_units ELSE 0 END) as would_units";

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $since = gmdate('Y-m-d H:i:s', time() - $days * 86400);
        $bots = Hit::query()->where('ts', '>=', $since)->where('ua_class', '<>', 'human');

        $total = (clone $bots)->selectRaw(self::SUMS . ', COUNT(DISTINCT ip_hash) as sources')->first();
        $humans = Hit::query()->where('ts', '>=', $since)->where('ua_class', 'human')->count();
        $byBot = (clone $bots)->groupBy('bot_name', 'ua_class')->orderByDesc('hits')->limit((int) $this->option('bots'))
            ->selectRaw('bot_name, ua_class, ' . self::SUMS)->get();
        $settlements = Hit::query()->where('status', 'paid')->where('ts', '>=', $since)->orderByDesc('id')->limit(10)
            ->get(['ts', 'bot_name', 'path', 'rail', 'amount_units', 'tx_hash']);

        $usd = static fn ($u) => '$' . number_format(((int) $u) / 1_000_000, 4);

        if ($this->option('json')) {
            $this->line(json_encode([
                'days' => $days,
                'human_hits' => $humans,
                'bots' => $total?->toArray(),
                'by_bot' => $byBot->toArray(),
                'settlements' => $settlements->toArray(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $this->info("Pay-per-Crawl, last {$days} day(s) (UTC)");
        $this->line(sprintf(
            'Bot hits %d from %d sources | paid %d (%s) | 402 unpaid %d | rejected %d | blocked %d | monitor %d (would-be %s) | human page views %d',
            (int) $total?->hits, (int) $total?->sources, (int) $total?->paid, $usd($total?->revenue_units), (int) $total?->unpaid,
            (int) $total?->rejected, (int) $total?->blocked, (int) $total?->monitor, $usd($total?->would_units), $humans
        ));
        if ($byBot->isNotEmpty()) {
            $this->newLine();
            $this->table(
                ['Bot', 'Class', 'Hits', 'Paid', '402', 'Rejected', 'Monitor', 'Revenue'],
                $byBot->map(fn ($r) => [$r->bot_name ?: '(unnamed)', $r->ua_class, $r->hits, $r->paid, $r->unpaid, $r->rejected, $r->monitor, $usd($r->revenue_units)])->all()
            );
        }
        if ($settlements->isNotEmpty()) {
            $this->newLine();
            $this->table(
                ['When (UTC)', 'Bot', 'Path', 'Rail', 'Amount', 'Tx'],
                $settlements->map(fn ($r) => [$r->ts, $r->bot_name, $r->path, $r->rail, $usd($r->amount_units), $r->tx_hash])->all()
            );
        }
        return self::SUCCESS;
    }
}
