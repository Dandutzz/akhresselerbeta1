<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class RevenueChart extends ChartWidget
{
    protected ?string $heading = 'Pendapatan 30 Hari Terakhir';

    protected ?string $description = 'Total order PAID per hari (Rupiah)';

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '60s';

    public ?string $filter = '30';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    protected function getFilters(): ?array
    {
        return [
            '7' => '7 hari',
            '30' => '30 hari',
            '90' => '90 hari',
        ];
    }

    protected function getData(): array
    {
        $days = max(1, (int) ($this->filter ?? 30));
        $start = today()->subDays($days - 1);

        $rows = Order::where('status', Order::STATUS_PAID)
            ->whereDate('paid_at', '>=', $start)
            ->select(DB::raw('DATE(paid_at) as d'), DB::raw('SUM(total_payment) as t'))
            ->groupBy('d')
            ->pluck('t', 'd')
            ->toArray();

        $labels = [];
        $values = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $labels[] = $date->format('d M');
            $values[] = (int) ($rows[$date->toDateString()] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Pendapatan',
                    'data' => $values,
                    'fill' => true,
                    'tension' => 0.35,
                    'borderColor' => 'rgb(139, 92, 246)',
                    'backgroundColor' => 'rgba(139, 92, 246, 0.18)',
                    'pointBackgroundColor' => 'rgb(139, 92, 246)',
                    'pointRadius' => 3,
                    'pointHoverRadius' => 5,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => 'function(v){return "Rp "+(v/1000).toLocaleString("id-ID")+"k";}',
                    ],
                ],
            ],
        ];
    }
}
