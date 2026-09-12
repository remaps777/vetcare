<?php

namespace App\Http\Controllers;

use App\Models\ClinicSetting;
use App\Models\Owner;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Services\SalesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class StoreController extends Controller
{
    public function showcase(): View
    {
        return view('store.showcase', ['products' => $this->storeProducts()]);
    }

    public function cashier(Request $request): View
    {
        return view('store.cashier', [
            'products' => $this->storeProducts(),
            'owners' => Owner::where('is_active', true)->orderBy('first_name')->orderBy('last_name')->get(),
            'clinic' => ClinicSetting::first(),
            'sale' => $request->filled('sale') ? app(SalesService::class)->getSale(Sale::findOrFail($request->integer('sale'))) : null,
        ]);
    }

    public function store(Request $request, SalesService $sales): RedirectResponse
    {
        $data = $request->validate([
            'owner_id' => ['nullable', 'integer', 'exists:owners,id'],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);

        try {
            $sale = $sales->createSale(
                $request->user(),
                ! empty($data['owner_id']) ? Owner::findOrFail($data['owner_id']) : null,
                $data['items'],
                $data['payment_method'] ?? null,
            );
        } catch (\RuntimeException|\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['items' => $exception->getMessage()]);
        }

        return redirect()->route('store.cashier', ['sale' => $sale->id])->with('success', 'Venta registrada correctamente.');
    }

    public function sales(Request $request): View
    {
        return view('store.sales', ['sales' => app(SalesService::class)->getSales($request->user()), 'clinic' => ClinicSetting::first()]);
    }

    public function pdf(Request $request, Sale $sale, SalesService $sales): Response
    {
        abort_unless($sale->seller_user_id === $request->user()->id, 403);

        $sale = $sales->getSale($sale);

        return Pdf::loadView('store.receipt-pdf', [
            'sale' => $sale,
            'clinic' => ClinicSetting::first(),
        ])->download($sale->sale_number.'.pdf');
    }

    private function storeProducts()
    {
        $store = Warehouse::where('code', 'STORE')->first();

        return Product::query()
            ->where('products.is_active', true)
            ->join('warehouse_stocks', function ($join) use ($store): void {
                $join->on('warehouse_stocks.item_id', '=', 'products.id')
                    ->where('warehouse_stocks.item_type', 'product')
                    ->where('warehouse_stocks.warehouse_id', $store?->id);
            })
            ->where('warehouse_stocks.quantity', '>', 0)
            ->select('products.*', 'warehouse_stocks.quantity as store_quantity')
            ->orderBy('products.name')->get();
    }
}
