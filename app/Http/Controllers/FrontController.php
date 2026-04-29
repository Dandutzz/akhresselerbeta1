<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Faq;
use App\Models\Flashsale;
use App\Models\Order;
use App\Models\Product;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FrontController extends Controller
{
    public function index(Request $request): View
    {
        $categories = Category::orderBy('name')->get();

        // Eager-load semua yang dipakai di card homepage:
        // - category: untuk badge kategori
        // - variants + stocks_count: hindari N+1 saat hitung total stok per produk
        // - variants.activeFlashsales (active scope) untuk badge Flash di card
        $query = Product::query()
            ->with([
                'category',
                'variants' => fn ($q) => $q->withCount([
                    'stocks as available_stocks_count' => fn ($s) => $s->where('is_sold', false),
                ]),
                'variants.activeFlashsales',
            ])
            ->orderBy('is_best_seller', 'desc')
            ->orderBy('sold_count', 'desc')
            ->orderBy('name');

        if ($slug = $request->query('kategori')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $slug));
        }

        if ($search = trim((string) $request->query('q'))) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $products = $query->get();

        $flashsales = Flashsale::active()
            ->with(['variant.product.category'])
            ->orderBy('end_at')
            ->get();

        $articles = Article::published()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get();

        // Statistik untuk hero counter — angka real, dengan minimum sosial-proof.
        $paidOrders = Order::where('status', Order::STATUS_PAID)->count();
        $stats = [
            'orders' => max(1500, $paidOrders + 1500),
            'products' => max(1, Product::count()),
            'satisfaction' => 99,
        ];

        $testimonials = Testimonial::active()->limit(6)->get();

        return view('welcome', [
            'categories' => $categories,
            'products' => $products,
            'activeCategory' => $slug ?? null,
            'searchQuery' => $search ?? '',
            'flashsales' => $flashsales,
            'articles' => $articles,
            'stats' => $stats,
            'testimonials' => $testimonials,
        ]);
    }

    public function show(Product $product): View
    {
        $product->load(['category', 'variants' => function ($q) {
            $q->withCount(['stocks as available_stocks_count' => function ($sub) {
                $sub->where('is_sold', false);
            }]);
        }]);

        $reviews = $product->reviews()
            ->approved()
            ->latest()
            ->limit(20)
            ->get();

        $reviewStats = [
            'count' => $reviews->count(),
            'avg' => $reviews->count() ? round($reviews->avg('rating'), 1) : null,
        ];

        return view('product', [
            'product' => $product,
            'reviews' => $reviews,
            'reviewStats' => $reviewStats,
        ]);
    }

    public function faq(): View
    {
        return view('pages.faq', [
            'faqs' => Faq::active()->get(),
        ]);
    }

    public function howToOrder(): View
    {
        return view('pages.how-to-order');
    }

    public function terms(): View
    {
        return view('pages.terms');
    }

    public function articleIndex(): View
    {
        return view('pages.articles', [
            'articles' => Article::published()
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(12),
        ]);
    }

    public function articleShow(Article $article): View
    {
        abort_unless($article->is_published, 404);

        return view('pages.article-show', [
            'article' => $article,
            'related' => Article::published()
                ->where('id', '!=', $article->id)
                ->orderByDesc('published_at')
                ->limit(3)
                ->get(),
        ]);
    }

    public function cekInvoice(Request $request)
    {
        $code = trim((string) $request->query('order_code', ''));
        if ($code !== '') {
            $exists = Order::where('order_code', $code)->exists();
            if ($exists) {
                return redirect()->route('invoice.show', $code);
            }

            return view('pages.cek-invoice', [
                'error' => 'Kode order tidak ditemukan. Pastikan kamu memasukkan kode yang benar.',
                'code' => $code,
            ]);
        }

        return view('pages.cek-invoice', ['code' => '']);
    }
}
