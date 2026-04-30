<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Stock;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SalesStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $now = Carbon::now();
        $todayRevenue = Order::where('status', Order::STATUS_PAID)
            ->whereDate('paid_at', today())
            ->sum('total_payment');
        $yesterdayRevenue = Order::where('status', Order::STATUS_PAID)
            ->whereDate('paid_at', today()->subDay())
            ->sum('total_payment');
        $todayDelta = $this->growthPercent($todayRevenue, $yesterdayRevenue);

        $monthRevenue = Order::where('status', Order::STATUS_PAID)
            ->whereMonth('paid_at', $now->month)
            ->whereYear('paid_at', $now->year)
            ->sum('total_payment');
        $lastMonth = $now->copy()->subMonth();
        $lastMonthRevenue = Order::where('status', Order::STATUS_PAID)
            ->whereMonth('paid_at', $lastMonth->month)
            ->whereYear('paid_at', $lastMonth->year)
            ->sum('total_payment');
        $monthDelta = $this->growthPercent($monthRevenue, $lastMonthRevenue);

        $paidCount = Order::where('status', Order::STATUS_PAID)->count();
        $pendingCount = Order::where('status', Order::STATUS_PENDING)->count();
        $availableStock = Stock::where('is_sold', false)->count();

        $weeklyChart = $this->dailyRevenueSeries(7);
        $monthlyChart = $this->dailyRevenueSeries(30);
        $paidWeeklyChart = $this->dailyOrderCountSeries(7);

        return [
            Stat::make('Pendapatan Hari Ini', 'Rp '.number_format($todayRevenue, 0, ',', '.'))
                ->description($this->describeDelta($todayDelta, 'kemarin'))
                ->descriptionIcon($todayDelta >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart($weeklyChart)
                ->color($todayDelta >= 0 ? 'success' : 'danger'),

            Stat::make('Pendapatan Bulan Ini', 'Rp '.number_format($monthRevenue, 0, ',', '.'))
                ->description($this->describeDelta($monthDelta, 'bulan lalu'))
                ->descriptionIcon($monthDelta >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart($monthlyChart)
                ->color($monthDelta >= 0 ? 'success' : 'danger'),

            Stat::make('Pesanan Sukses', (string) $paidCount)
                ->description($pendingCount.' menunggu pembayaran')
                ->descriptionIcon('heroicon-m-clock')
                ->chart($paidWeeklyChart)
                ->color('primary'),

            Stat::make('Stok Tersedia', (string) $availableStock)
                ->description($availableStock < 10 ? 'Hampir habis!' : 'Stok aman')
                ->descriptionIcon($availableStock < 10 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-badge')
                ->chart([3, 4, 5, 5, 4, 6, max(1, min(10, $availableStock))])
                ->color($availableStock < 10 ? 'danger' : 'success'),
        ];
    }

    protected function growthPercent(float $current, float $previous): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    protected function describeDelta(float $delta, string $vs): string
    {
        $arrow = $delta >= 0 ? '+' : '';

        return $arrow.$delta.'% vs '.$vs;
    }

    /**
     * @return array<int>
     */
    protected function dailyRevenueSeries(int $days): array
    {
        $start = today()->subDays($days - 1);
        $rows = Order::where('status', Order::STATUS_PAID)
            ->whereDate('paid_at', '>=', $start)
            ->select(DB::raw('DATE(paid_at) as d'), DB::raw('SUM(total_payment) as t'))
            ->groupBy('d')
            ->pluck('t', 'd')
            ->toArray();

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $key = $start->copy()->addDays($i)->toDateString();
            $series[] = (int) ($rows[$key] ?? 0);
        }

        return $series;
    }

    /**
     * @return array<int>
     */
    protected function dailyOrderCountSeries(int $days): array
    {
        $start = today()->subDays($days - 1);
        $rows = Order::where('status', Order::STATUS_PAID)
            ->whereDate('paid_at', '>=', $start)
            ->select(DB::raw('DATE(paid_at) as d'), DB::raw('COUNT(*) as c'))
            ->groupBy('d')
            ->pluck('c', 'd')
            ->toArray();

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $key = $start->copy()->addDays($i)->toDateString();
            $series[] = (int) ($rows[$key] ?? 0);
        }

        return $series;
    }
}
