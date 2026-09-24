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
     * Menampilkan katalog retail, ringkasan stok, dan riwayat transaksi penjualan & penyewaan.
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

        // Performa penjualan & sewa bulan ini
        $now = now();
        $thisMonthSales = RetailSale::whereYear('sale_date', $now->year)
            ->whereMonth('sale_date', $now->month)
            ->get();

        $monthRevenue = $thisMonthSales->sum('total_amount');
        $monthHpp = $thisMonthSales->sum('total_cost');
        $monthProfit = $monthRevenue - $monthHpp;

        // Daftar penyewaan aktif (barang sedang dibawa pelanggan)
        $activeRentals = RetailSale::with(['items.product', 'account'])
            ->where('transaction_type', 'rental')
            ->where('rental_status', 'active')
            ->orderBy('rental_end_date')
            ->get();
        $activeRentalsCount = $activeRentals->count();

        // Riwayat 10 transaksi terakhir (jual & sewa)
        $recentSales = RetailSale::with(['items.product', 'account'])
            ->latest('sale_date')
            ->take(15)
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
            'activeRentals' => $activeRentals,
            'activeRentalsCount' => $activeRentalsCount,
            'recentSales' => $recentSales,
            'paymentAccounts' => $paymentAccounts,
            'availableProducts' => $availableProducts,
        ]);
    }

    /**
     * Menambah produk pakaian retail baru (bisa beli & sewa).
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
            'rental_price' => ['nullable', 'numeric', 'min:0'],
            'is_for_rent' => ['nullable', 'boolean'],
            'stock' => ['required', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [
            'code.unique' => 'Kode produk sudah terdaftar.',
            'category.in' => 'Kategori yang dipilih tidak valid.',
        ]);

        $validated['min_stock'] = $validated['min_stock'] ?? 3;
        if (! isset($validated['rental_price']) || $validated['rental_price'] === null || $validated['rental_price'] == 0) {
            $validated['rental_price'] = round(($validated['selling_price'] * 0.3) / 1000) * 1000;
        }
        $validated['is_for_rent'] = $request->has('is_for_rent') ? (bool) $request->input('is_for_rent') : true;

        $product = Product::create($validated);

        return back()->with('success', "Produk {$product->name} ({$product->code}) berhasil ditambahkan ke stok retail (bisa jual & sewa).");
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
            'rental_price' => ['nullable', 'numeric', 'min:0'],
            'is_for_rent' => ['nullable', 'boolean'],
            'stock' => ['required', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['min_stock'] = $validated['min_stock'] ?? 3;
        if (! isset($validated['rental_price']) || $validated['rental_price'] === null || $validated['rental_price'] == 0) {
            $validated['rental_price'] = round(($validated['selling_price'] * 0.3) / 1000) * 1000;
        }
        $validated['is_for_rent'] = $request->has('is_for_rent');

        $product->update($validated);

        return back()->with('success', "Data produk {$product->name} berhasil diperbarui.");
    }

    /**
     * Menghapus produk pakaian jika belum memiliki riwayat penjualan / sewa.
     */
    public function destroyProduct(Product $product): RedirectResponse
    {
        if ($product->saleItems()->exists()) {
            return back()->with('error', "Produk {$product->name} tidak dapat dihapus karena sudah memiliki riwayat transaksi penjualan/sewa. Anda dapat mengubah stoknya menjadi 0.");
        }

        $name = $product->name;
        $product->delete();

        return back()->with('success', "Produk {$name} berhasil dihapus dari inventaris.");
    }

    /**
     * Memproses transaksi penjualan atau penyewaan pakaian kasir & posting otomatis ke jurnal akuntansi.
     */
    public function storeSale(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'transaction_type' => ['nullable', 'string', Rule::in(['sale', 'rental'])],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['required', 'string', Rule::in(['cash', 'transfer'])],
            'account_id' => ['required', 'exists:accounts,id'],
            'rental_start_date' => ['nullable', 'date'],
            'rental_end_date' => ['nullable', 'date'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ], [
            'items.required' => 'Pilih setidaknya 1 produk.',
            'items.min' => 'Pilih setidaknya 1 produk.',
            'account_id.required' => 'Pilih akun kas atau bank penerima pembayaran.',
        ]);

        $isRental = ($validated['transaction_type'] ?? 'sale') === 'rental';
        $prefix = $isRental ? 'RNT-' : 'RTL-';
        $invoiceNumber = $prefix.date('Ymd').'-'.strtoupper(Str::random(4));

        $sale = DB::transaction(function () use ($validated, $isRental, $invoiceNumber) {
            $totalAmount = 0;
            $totalCost = 0;
            $deposit = (float) ($validated['deposit_amount'] ?? 0);

            // Buat record transaksi retail (beli atau sewa)
            $retailSale = RetailSale::create([
                'invoice_number' => $invoiceNumber,
                'transaction_type' => $isRental ? 'rental' : 'sale',
                'sale_date' => now(),
                'rental_start_date' => $isRental ? ($validated['rental_start_date'] ?? now()->toDateString()) : null,
                'rental_end_date' => $isRental ? ($validated['rental_end_date'] ?? now()->addDays(3)->toDateString()) : null,
                'rental_status' => $isRental ? 'active' : null,
                'deposit_amount' => $deposit,
                'customer_name' => $validated['customer_name'] ?? ($isRental ? 'Penyewa Jas' : 'Pelanggan Walk-In'),
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

                // Kurangi stok produk (karena dibeli ataupun disewa)
                $product->decrement('stock', $itemData['quantity']);

                $unitCost = (float) $product->cost_price;
                $unitSellingPrice = (float) $product->selling_price;
                $unitRentalPrice = (float) $product->effective_rental_price;

                if ($isRental) {
                    $unitPrice = $unitRentalPrice;
                    $subtotal = $unitRentalPrice * $itemData['quantity'];
                    $itemCost = 0; // Pada sewa, pakaian tetap aset perusahaan yang dipinjamkan
                } else {
                    $unitPrice = $unitSellingPrice;
                    $subtotal = $unitSellingPrice * $itemData['quantity'];
                    $itemCost = $unitCost * $itemData['quantity'];
                }

                $totalAmount += $subtotal;
                $totalCost += $itemCost;

                RetailSaleItem::create([
                    'retail_sale_id' => $retailSale->id,
                    'product_id' => $product->id,
                    'transaction_type' => $isRental ? 'rental' : 'sale',
                    'quantity' => $itemData['quantity'],
                    'unit_cost_price' => $unitCost,
                    'unit_selling_price' => $unitSellingPrice,
                    'unit_rental_price' => $unitRentalPrice,
                    'subtotal' => $subtotal,
                ]);
            }

            $retailSale->update([
                'total_amount' => $totalAmount,
                'total_cost' => $totalCost,
            ]);

            // Posting otomatis ke Jurnal Akuntansi (Double-Entry)
            if ($isRental) {
                $pendapatanSewaAccount = Account::firstOrCreate(
                    ['code' => '4004'],
                    ['name' => 'Pendapatan Sewa Pakaian / Jas', 'type' => 'revenue']
                );

                $totalDiterima = $totalAmount + $deposit;

                $journal = JournalEntry::create([
                    'reference' => $invoiceNumber,
                    'description' => "Penyewaan Pakaian {$retailSale->customer_name} ({$invoiceNumber})",
                    'date' => now(),
                    'source' => 'rental',
                    'status' => 'verified',
                ]);

                // Debit Kas/Bank sebesar uang sewa + deposit
                JournalEntryLine::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $validated['account_id'],
                    'description' => "Penerimaan biaya sewa & jaminan {$invoiceNumber}",
                    'debit' => $totalDiterima,
                    'credit' => 0,
                ]);

                // Kredit Pendapatan Sewa
                JournalEntryLine::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $pendapatanSewaAccount->id,
                    'description' => "Pendapatan sewa pakaian {$invoiceNumber}",
                    'debit' => 0,
                    'credit' => $totalAmount,
                ]);

                // Kredit Titipan Uang Jaminan jika ada
                if ($deposit > 0) {
                    $titipanJaminanAccount = Account::firstOrCreate(
                        ['code' => '2005'],
                        ['name' => 'Titipan Uang Jaminan Sewa', 'type' => 'liability']
                    );

                    JournalEntryLine::create([
                        'journal_entry_id' => $journal->id,
                        'account_id' => $titipanJaminanAccount->id,
                        'description' => "Titipan uang jaminan sewa {$invoiceNumber}",
                        'debit' => 0,
                        'credit' => $deposit,
                    ]);
                }

                $retailSale->update(['journal_entry_id' => $journal->id]);
            } else {
                // Standar Penjualan Retail (Beli)
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
            }

            return $retailSale;
        });

        $tipeLabel = $isRental ? 'Penyewaan pakaian' : 'Penjualan retail';
        return back()->with('success', "Transaksi {$tipeLabel} #{$sale->invoice_number} berhasil diproses. Total: Rp ".number_format($sale->total_amount, 0, ',', '.').' dan otomatis dibukukan ke jurnal akuntansi.');
    }

    /**
     * Memproses pengembalian pakaian sewa dan memulihkan stok inventaris.
     */
    public function returnRental(RetailSale $sale): RedirectResponse
    {
        if (! $sale->isRental()) {
            return back()->with('error', 'Transaksi ini bukan merupakan transaksi penyewaan.');
        }

        if ($sale->rental_status === 'returned') {
            return back()->with('info', 'Pakaian sewa ini sudah berstatus dikembalikan sebelumnya.');
        }

        DB::transaction(function () use ($sale) {
            // Pulihkan stok tiap produk yang disewa
            foreach ($sale->items as $item) {
                if ($item->product) {
                    $item->product->increment('stock', $item->quantity);
                }
            }

            $sale->update([
                'rental_status' => 'returned',
                'rental_return_date' => now(),
            ]);
        });

        return back()->with('success', "Pakaian sewa untuk invoice #{$sale->invoice_number} berhasil dikembalikan. Stok pakaian telah dipulihkan ke inventaris.");
    }
}
