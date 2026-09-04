<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Only Sayajin Store : gammes et produits de la vitrine. */
class ProductController extends Controller
{
    public function __construct(private ImageUploadService $images) {}

    public function index()
    {
        return Inertia::render('Admin/Products/Index', [
            'categories' => ProductCategory::orderBy('position')->get(['id', 'name', 'price']),
            'products'   => Product::with('category:id,name')->withCount('sales')->orderBy('position')->get()
                ->map(fn ($p) => $p->only(['id', 'name', 'price', 'is_published', 'position']) + [
                    'category'    => $p->category?->name,
                    'sales_count' => $p->sales_count,
                    'photo'       => $p->photo_url,
                ]),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Products/Form', [
            'product'    => null,
            'categories' => ProductCategory::orderBy('position')->get(['id', 'name']),
        ]);
    }

    public function edit(Product $product)
    {
        return Inertia::render('Admin/Products/Form', [
            'product'    => $product->only([
                'id', 'category_id', 'name', 'slug', 'description',
                'price', 'vat_rate', 'is_published', 'position',
            ]) + ['photo' => $product->photo_url],
            'categories' => ProductCategory::orderBy('position')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $this->images->store($request->file('photo'), 'products', 'product');
        }

        $product = Product::create($data);

        return redirect()->route('admin.products.index')
            ->with('success', "« {$product->name} » créé.");
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request, $product);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $this->images->replace(
                $request->file('photo'), $product->photo_path, 'products', 'product',
            );
        }

        $product->update($data);

        return redirect()->route('admin.products.index')
            ->with('success', "« {$product->name} » enregistré.");
    }

    public function destroy(Product $product)
    {
        // Un produit vendu reste en base : le chiffre d'affaires en dépend
        if ($product->sales()->exists()) {
            $product->update(['is_published' => false]);

            return back()->with('success', 'Produit masqué (des ventes y sont liées).');
        }

        $this->images->delete($product->photo_path);
        $product->delete();

        return back()->with('success', 'Produit supprimé.');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'category_id'  => ['nullable', 'exists:product_categories,id'],
            'name'         => ['required', 'string', 'max:120'],
            'slug'         => ['nullable', 'string', 'max:120', Rule::unique('products', 'slug')->ignore($product)],
            'description'  => ['nullable', 'string', 'max:1000'],
            'price'        => ['required', 'numeric', 'min:0'],
            'vat_rate'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_published' => ['boolean'],
            'position'     => ['nullable', 'integer', 'min:0'],
            'photo'        => ImageUploadService::rules(),
        ]);
    }
}
