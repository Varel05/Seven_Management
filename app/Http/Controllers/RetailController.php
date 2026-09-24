<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Product;
use App\Models\RetailSale;
use App\Models\RetailSaleItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RetailController extends Controller
{
    /**
     * Menampilkan katalog retail, ringkasan stok, dan riwayat transaksi penjualan.
     */
    public function index(Request $request): View
    {
        $categoryFilter = $request->query('category');
        $search = $request->query('q');

        $query = Product::query();

        if ($categoryFilter && array_key_exists($categoryFilter, Product::CATEGORIES)) {
            $query->where('category', $categoryFilter);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('color', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('name')->paginate(12)->withQueryString();

        // Ringkasan metrik inventaris & penjualan
        $totalCostValue = Product::selectRaw('SUM(stock * cost_price) as val')->value('val') ?? 0;
        $totalRetailValue = Product::selectRaw('SUM(stock * selling_price) as val')->value('val') ?? 0;
        $totalUnits = (int) Product::sum('stock');
        $lowStockCount = Product::whereColumn('stock', '<=', 'min_stock')->count();

        // Performa penjualan bulan ini
        $now = now();
        $thisMonthSales = RetailSale::whereYear('sale_date', $now->year)
            ->whereMonth('sale_date', $now->month)
            ->get();

        $monthRevenue = $thisMonthSales->sum('total_amount');
        $monthHpp = $thisMonthSales->sum('total_cost');
        $monthProfit = $monthRevenue - $monthHpp;

        // Riwayat 10 transaksi terakhir
        $recentSales = RetailSale::with(['items.product', 'account'])
            ->latest('sale_date')
            ->take(10)
            ->get();

        // Akun kas / bank untuk pembayaran
        $paymentAccounts = Account::where('type', 'asset')
            ->whereIn('code', ['1001', '1002'])
            ->get();
        if ($paymentAccounts->isEmpty()) {
            $paymentAccounts = Account::where('type', 'asset')->take(2)->get();
        }

        // Semua produk dengan stok > 0 untuk modal kasir cepat
        $availableProducts = Product::where('stock', '>', 0)->orderBy('name')->get();

        return view('retail.index', [
            'products' => $products,
            'categories' => Product::CATEGORIES,
            'selectedCategory' => $categoryFilter,
            'search' => $search,
            'totalCostValue' => (float) $totalCostValue,
            'totalRetailValue' => (float) $totalRetailValue,
            'totalUnits' => $totalUnits,
            'lowStockCount' => $lowStockCount,
            'monthRevenue' => (float) $monthRevenue,
            'monthProfit' => (float) $monthProfit,
            'recentSales' => $recentSales,
            'paymentAccounts' => $paymentAccounts,
            'availableProducts' => $availableProducts,
        ]);
    }

    /**
     * Menambah produk pakaian retail baru.
     */
    public function storeProduct(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:products,code'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(array_keys(Product::CATEGORIES))],
            'size' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:100'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [
            'code.unique' => 'Kode produk sudah terdaftar.',
            'category.in' => 'Kategori yang dipilih tidak valid.',
        ]);

        $validated['min_stock'] = $validated['min_stock'] ?? 3;

        $product = Product::create($validated);

        return back()->with('success', "Produk {$product->name} ({$product->code}) berhasil ditambahkan ke stok retail.");
    }

    /**
     * Memperbarui informasi produk pakaian retail.
     */
    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('products', 'code')->ignore($product->id)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(array_keys(Product::CATEGORIES))],
            'size' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:100'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['min_stock'] = $validated['min_stock'] ?? 3;

        $product->update($validated);

        return back()->with('success', "Data produk {$product->name} berhasil diperbarui.");
    }

    /**
     * Menghapus produk pakaian jika belum memiliki riwayat penjualan.
     */
    public function destroyProduct(Product $product): RedirectResponse
    {
        if ($product->saleItems()->exists()) {
            return back()->with('error', "Produk {$product->name} tidak dapat dihapus karena sudah memiliki riwayat transaksi penjualan. Anda dapat mengubah stoknya menjadi 0.");
        }

        $name = $product->name;
        $product->delete();

        return back()->with('success', "Produk {$name} berhasil dihapus dari inventaris.");
    }

    /**
     * Memproses transaksi penjualan retail kasir & posting otomatis ke jurnal akuntansi.
     */
    public function storeSale(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['required', 'string', Rule::in(['cash', 'transfer'])],
            'account_id' => ['required', 'exists:accounts,id'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ], [
            'items.required' => 'Pilih setidaknya 1 produk yang dibeli.',
            'items.min' => 'Pilih setidaknya 1 produk yang dibeli.',
            'account_id.required' => 'Pilih akun kas atau bank penerima pembayaran.',
        ]);

        $sale = DB::transaction(function () use ($validated) {
            $invoiceNumber = 'RTL-'.date('Ymd').'-'.strtoupper(Str::random(4));
            $totalAmount = 0;
            $totalCost = 0;

            // Buat record penjualan retail
            $retailSale = RetailSale::create([
                'invoice_number' => $invoiceNumber,
                'sale_date' => now(),
                'customer_name' => $validated['customer_name'] ?? 'Pelanggan Walk-In',
                'customer_phone' => $validated['customer_phone'] ?? null,
                'payment_method' => $validated['payment_method'],
                'account_id' => $validated['account_id'],
                'total_amount' => 0,
                'total_cost' => 0,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $itemData) {
                $product = Product::lockForUpdate()->find($itemData['product_id']);

                if ($product->stock < $itemData['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => "Stok produk '{$product->name}' tidak mencukupi (sisa: {$product->stock}, diminta: {$itemData['quantity']}).",
                    ]);
                }

                // Kurangi stok produk
                $product->decrement('stock', $itemData['quantity']);

                $unitCost = (float) $product->cost_price;
                $unitPrice = (float) $product->selling_price;
                $subtotal = $unitPrice * $itemData['quantity'];
                $itemCost = $unitCost * $itemData['quantity'];

                $totalAmount += $subtotal;
                $totalCost += $itemCost;

                RetailSaleItem::create([
                    'retail_sale_id' => $retailSale->id,
                    'product_id' => $product->id,
                    'quantity' => $itemData['quantity'],
                    'unit_cost_price' => $unitCost,
                    'unit_selling_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);
            }

            $retailSale->update([
                'total_amount' => $totalAmount,
                'total_cost' => $totalCost,
            ]);

            // Posting otomatis ke Jurnal Akuntansi (Double-Entry Perpetual)
            $pendapatanRetailAccount = Account::firstOrCreate(
                ['code' => '4002'],
                ['name' => 'Pendapatan Penjualan Retail', 'type' => 'revenue']
            );

            $persediaanAccount = Account::firstOrCreate(
                ['code' => '1003'],
                ['name' => 'Persediaan Barang Dagang (Retail)', 'type' => 'asset']
            );

            $hppAccount = Account::firstOrCreate(
                ['code' => '5004'],
                ['name' => 'Beban Pokok Penjualan (HPP) Retail', 'type' => 'expense']
            );

            $journal = JournalEntry::create([
                'reference' => $invoiceNumber,
                'description' => "Penjualan Retail {$retailSale->customer_name} ({$invoiceNumber})",
                'date' => now(),
                'source' => 'retail',
                'status' => 'verified',
            ]);

            // 1. Debit Kas/Bank, Kredit Pendapatan Retail
            JournalEntryLine::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $validated['account_id'],
                'description' => "Penerimaan kas/transfer penjualan retail {$invoiceNumber}",
                'debit' => $totalAmount,
                'credit' => 0,
            ]);

            JournalEntryLine::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $pendapatanRetailAccount->id,
                'description' => "Pendapatan penjualan retail {$invoiceNumber}",
                'debit' => 0,
                'credit' => $totalAmount,
            ]);

            // 2. Debit HPP, Kredit Persediaan Barang Dagang
            if ($totalCost > 0) {
                JournalEntryLine::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $hppAccount->id,
                    'description' => "HPP penjualan retail {$invoiceNumber}",
                    'debit' => $totalCost,
                    'credit' => 0,
                ]);

                JournalEntryLine::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $persediaanAccount->id,
                    'description' => "Pengurangan persediaan stok {$invoiceNumber}",
                    'debit' => 0,
                    'credit' => $totalCost,
                ]);
            }

            $retailSale->update(['journal_entry_id' => $journal->id]);

            return $retailSale;
        });

        return back()->with('success', "Transaksi penjualan retail #{$sale->invoice_number} berhasil diproses. Total: Rp ".number_format($sale->total_amount, 0, ',', '.').' dan otomatis dibukukan ke jurnal akuntansi.');
    }
}
