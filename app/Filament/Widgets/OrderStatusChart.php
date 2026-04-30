<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class OrderStatusChart extends ChartWidget
{
    protected ?string $heading = 'Status Pesanan (30 hari)';

    protected ?string $description = 'Distribusi status order bulan ini';

    protected static ?int $sort = 3;

    protected ?string $pollingInterval = '60s';

    public function getColumnSpan(): int|string|array
    {
        return [
            'default' => 'full',
            'md' => 1,
        ];
    }

    protected function getData(): array
    {
        $start = today()->subDays(29);

        $statuses = [
            Order::STATUS_PAID => ['label' => 'Sukses', 'color' => 'rgba(34,197,94,0.85)'],
            Order::STATUS_PENDING => ['label' => 'Menunggu', 'color' => 'rgba(234,179,8,0.85)'],
            Order::STATUS_EXPIRED => ['label' => 'Kadaluarsa', 'color' => 'rgba(148,163,184,0.85)'],
            Order::STATUS_CANCELLED => ['label' => 'Dibatalkan', 'color' => 'rgba(239,68,68,0.85)'],
            Order::STATUS_REFUNDED => ['label' => 'Refund', 'color' => 'rgba(168,85,247,0.85)'],
        ];

        $counts = Order::whereDate('created_at', '>=', $start)
            ->whereIn('status', array_keys($statuses))
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->toArray();

        $labels = [];
        $data = [];
        $colors = [];
        foreach ($statuses as $key => $meta) {
            $labels[] = $meta['label'];
            $data[] = (int) ($counts[$key] ?? 0);
            $colors[] = $meta['color'];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah',
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => ['boxWidth' => 12, 'padding' => 10],
                ],
            ],
            'cutout' => '62%',
        ];
    }
}
