<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\User;
use App\Support\ImagePayload;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Ventes réalisées au studio, saisies à la main par le barbier. */
class SaleController extends Controller
{
    /**
     * Au-delà de ce nombre de produits, la grille de vignettes occuperait tout
     * l'écran sur mobile : la vue bascule alors sur une liste déroulante.
     */
    private const TILE_LIMIT = 8;

    public function index(Request $request)
    {
        $preset = $request->input('preset', 'month');
        [$from, $to] = $this->range($request, $preset);

        $sales = ProductSale::with(['product:id,name', 'user:id,name'])
            ->whereBetween('sold_at', [$from->toDateString(), $to->toDateString()])
            ->when($request->input('q'), fn ($q, $term) => $q->whereHas(
                'product',
                fn ($sub) => $sub->where('name', 'like', "%{$term}%"),
            ))
            ->orderByDesc('sold_at')->orderByDesc('id')
            ->get();

        $products = Product::published()->orderBy('position')->get();

        return Inertia::render('Admin/Sales', [
            'from'   => $from->toDateString(),
            'to'     => $to->toDateString(),
            'preset' => $preset,
            'search' => $request->input('q'),

            // Regroupement par jour : le barbier vérifie sa journée, pas une
            // liste continue où il faudrait additionner de tête.
            'days' => $sales->groupBy(fn ($sale) => $sale->sold_at->toDateString())
                ->map(fn ($group, $date) => [
                    'date'  => $date,
                    'total' => round((float) $group->sum('total_ttc'), 2),
                    'sales' => $group->map(fn ($sale) => [
                        'id'       => $sale->id,
                        'product'  => $sale->product?->name ?? 'Produit retiré',
                        'client'   => $sale->user?->name,
                        'quantity' => $sale->quantity,
                        'total'    => (float) $sale->total_ttc,
                    ])->values(),
                ])->values(),

            'periodTotal' => round((float) $sales->sum('total_ttc'), 2),
            'periodCount' => $sales->count(),

            // Répartition par produit : quel article se vend réellement
            'byProduct' => $sales->groupBy('product_id')
                ->map(fn ($group) => [
                    'name'     => $group->first()->product?->name ?? 'Produit retiré',
                    'quantity' => (int) $group->sum('quantity'),
                    'total'    => round((float) $group->sum('total_ttc'), 2),
                ])
                ->sortByDesc('total')
                ->values(),

            'products' => $products->map(fn ($p) => [
                'id'    => $p->id,
                'name'  => $p->name,
                'price' => (float) $p->price,
                'image' => ImagePayload::make($p->photo_path),
            ]),

            // La vue choisit son mode d'après le catalogue, pas l'inverse
            'useTiles' => $products->count() <= self::TILE_LIMIT,

            'clients' => User::clients()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Fenêtre de consultation.
     *
     * Mêmes raccourcis que les statistiques : le barbier passe d'un écran à
     * l'autre, des périodes différentes l'obligeraient à recalculer de tête.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(Request $request, ?string $preset): array
    {
        $today = CarbonImmutable::today();

        return match (true) {
            $preset === 'month'   => [$today->startOfMonth(), $today->endOfMonth()],
            $preset === 'quarter' => [$today->subMonths(2)->startOfMonth(), $today->endOfMonth()],
            $preset === 'year'    => [$today->startOfYear(), $today->endOfYear()],
            default => [
                CarbonImmutable::parse($request->input('from', $today->startOfMonth()))->startOfDay(),
                CarbonImmutable::parse($request->input('to', $today))->endOfDay(),
            ],
        };
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id'     => ['required', 'exists:products,id'],
            'user_id'        => ['nullable', 'exists:users,id'],
            'quantity'       => ['required', 'integer', 'min:1', 'max:99'],
            'unit_price_ttc' => ['nullable', 'numeric', 'min:0'],
            'sold_at'        => ['required', 'date'],
        ]);

        $product = Product::findOrFail($data['product_id']);

        // Prix modifiable à la vente (geste commercial), sinon celui du produit
        $ttc = (float) ($data['unit_price_ttc'] ?? $product->price);
        $ht  = round($ttc / (1 + (float) $product->vat_rate / 100), 2);

        ProductSale::create([
            'product_id'     => $product->id,
            'user_id'        => $data['user_id'] ?? null,
            'quantity'       => $data['quantity'],
            'unit_price_ht'  => $ht,
            'unit_price_ttc' => $ttc,
            'total_ht'       => round($ht * $data['quantity'], 2),
            'total_ttc'      => round($ttc * $data['quantity'], 2),
            'sold_at'        => $data['sold_at'],
        ]);

        return back()->with('success', "{$product->name} — vente enregistrée.");
    }

    public function destroy(ProductSale $sale)
    {
        $sale->delete();

        return back()->with('success', 'Vente supprimée.');
    }
}
