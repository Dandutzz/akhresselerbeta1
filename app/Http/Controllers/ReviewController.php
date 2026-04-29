<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReviewController extends Controller
{
    /** GET /akun/orders/{order_code}/review */
    public function create(string $orderCode): View|RedirectResponse
    {
        $order = Order::with(['product', 'variant'])
            ->where('order_code', $orderCode)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (! $order->isPaid()) {
            return redirect()->route('account.orders.index')
                ->with('error', 'Review hanya bisa diberikan untuk order yang sudah lunas.');
        }

        if ($order->review()->exists()) {
            return redirect()->route('account.orders.index')
                ->with('warning', 'Kamu sudah memberi review untuk order ini.');
        }

        return view('reviews.create', compact('order'));
    }

    /** POST /akun/orders/{order_code}/review */
    public function store(Request $request, string $orderCode): RedirectResponse
    {
        $order = Order::where('order_code', $orderCode)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (! $order->isPaid()) {
            return back()->with('error', 'Order belum lunas, tidak bisa direview.');
        }

        if ($order->review()->exists()) {
            return redirect()->route('account.orders.index')
                ->with('warning', 'Kamu sudah memberi review untuk order ini.');
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        Review::create([
            'product_id' => $order->product_id,
            'product_variant_id' => $order->product_variant_id,
            'order_id' => $order->id,
            'user_id' => Auth::id(),
            'reviewer_name' => Auth::user()->name,
            'reviewer_email' => Auth::user()->email,
            'rating' => (int) $data['rating'],
            'comment' => $data['comment'] ?? null,
            'is_approved' => false, // butuh moderasi admin
        ]);

        return redirect()->route('account.orders.index')
            ->with('success', 'Terima kasih! Review kamu sudah dikirim & menunggu moderasi admin.');
    }
}
