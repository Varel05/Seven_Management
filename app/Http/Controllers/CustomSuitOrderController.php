<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CustomSuitOrder;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Material;
use App\Models\MaterialStockMovement;
use App\Models\Product;
use App\Services\SuitMaterialEstimatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomSuitOrderController extends Controller
{
    public function __construct(
        protected SuitMaterialEstimatorService $estimator
    ) {}

    /**
     * Menampilkan daftar pesanan jas custom, pipeline status produksi, dan metrik keuangan.
     */
    public function index(Request $request): View
    {
        $statusFilter = $request->query('status');
        $search = $request->query('q');

        $query = CustomSuitOrder::query();

        if ($statusFilter && array_key_exists($statusFilter, CustomSuitOrder::PRODUCTION_STATUSES)) {
            $query->where('production_status', $statusFilter);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        $orders = $query->latest('order_date')->paginate(10)->withQueryString();

        // Metrik statistik
        $activeOrdersCount = CustomSuitOrder::whereIn('production_status', [
            'consultation', 'cutting_sewing', 'fitting', 'finishing', 'ready',
        ])->count();

        $completedOrdersCount = CustomSuitOrder::where('production_status', 'completed')->count();

        $totalRevenue = CustomSuitOrder::where('production_status', '!=', 'cancelled')->sum('total_price');
        $totalCost = CustomSuitOrder::where('production_status', '!=', 'cancelled')->sum('total_cost');
        $totalProfit = $totalRevenue - $totalCost;

        $uncollectedReceivables = CustomSuitOrder::where('production_status', '!=', 'cancelled')
            ->where('payment_status', '!=', 'paid')
            ->selectRaw('SUM(total_price - down_payment) as remaining')
            ->value('remaining') ?? 0;

        $paymentAccounts = Account::where('type', 'asset')
            ->whereIn('code', ['1001', '1002'])
            ->get();
        if ($paymentAccounts->isEmpty()) {
            $paymentAccounts = Account::where('type', 'asset')->take(2)->get();
        }

        return view('custom-orders.index', [
            'orders' => $orders,
            'statuses' => CustomSuitOrder::PRODUCTION_STATUSES,
            'paymentStatuses' => CustomSuitOrder::PAYMENT_STATUSES,
            'selectedStatus' => $statusFilter,
            'search' => $search,
            'activeOrdersCount' => $activeOrdersCount,
            'completedOrdersCount' => $completedOrdersCount,
            'totalRevenue' => (float) $totalRevenue,
            'totalProfit' => (float) $totalProfit,
            'uncollectedReceivables' => (float) $uncollectedReceivables,
            'paymentAccounts' => $paymentAccounts,
        ]);
    }

    /**
     * Form pembuatan pesanan jas custom baru lengkap dengan kalkulator estimasi bahan AI.
     */
    public function create(): View
    {
        $paymentAccounts = Account::where('type', 'asset')
            ->whereIn('code', ['1001', '1002'])
            ->get();
        if ($paymentAccounts->isEmpty()) {
            $paymentAccounts = Account::where('type', 'asset')->take(2)->get();
        }

        $materials = Material::where('category', 'raw_material')->orderBy('name')->get();

        return view('custom-orders.create', [
            'categories' => Product::CATEGORIES,
            'paymentAccounts' => $paymentAccounts,
            'materials' => $materials,
        ]);
    }

    /**
     * API AJAX untuk mengirim ukuran custom, tipe kain, warna, foto & catatan ke AI n8n untuk dianalisis.
     */
    public function calculateEstimate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'suit_type' => ['required', 'string', Rule::in(array_keys(Product::CATEGORIES))],
            'height' => ['nullable', 'numeric'],
            'chest' => ['nullable', 'numeric'],
            'waist' => ['nullable', 'numeric'],
            'jacket_length' => ['nullable', 'numeric'],
            'sleeve_length' => ['nullable', 'numeric'],
            'trouser_length' => ['nullable', 'numeric'],
            'material_id' => ['nullable', 'exists:materials,id'],
            'fabric_type' => ['nullable', 'string', 'max:150'],
            'color' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'reference_image' => ['nullable', 'image', 'max:5120'],
            'fabric_price' => ['nullable', 'numeric'],
            'labor_cost' => ['nullable', 'numeric'],
            'target_margin' => ['nullable', 'numeric'],
        ]);

        $measurements = [
            'height' => $validated['height'] ?? 170,
            'chest' => $validated['chest'] ?? 96,
            'waist' => $validated['waist'] ?? 82,
            'jacket_length' => $validated['jacket_length'] ?? 74,
            'sleeve_length' => $validated['sleeve_length'] ?? 62,
            'trouser_length' => $validated['trouser_length'] ?? 100,
        ];

        $options = [
            'material_id' => $validated['material_id'] ?? null,
            'fabric_type' => $validated['fabric_type'] ?? null,
            'color' => $validated['color'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];

        if (! empty($validated['fabric_price'])) {
            $options['fabric_price_per_meter'] = $validated['fabric_price'];
        }
        if (! empty($validated['labor_cost'])) {
            $options['labor_cost'] = $validated['labor_cost'];
        }
        if (! empty($validated['target_margin'])) {
            $options['target_margin_percent'] = $validated['target_margin'];
        }

        if ($request->hasFile('reference_image')) {
            $file = $request->file('reference_image');
            $options['image_base64'] = base64_encode(file_get_contents($file->getRealPath()));
            $options['image_name'] = $file->getClientOriginalName();
        }

        // Query n8n FinanceAgent AI Webhook jika tersedia
        $aiVisionData = $this->queryN8nSuitAi($validated['suit_type'], $measurements, $options);

        $estimate = $this->estimator->estimate($validated['suit_type'], $measurements, $options, $aiVisionData);

        return response()->json([
            'success' => true,
            'n8n_processed' => ! empty($aiVisionData),
            'data' => $estimate,
        ]);
    }

    /**
     * Mengirim data pesanan ke webhook n8n FinanceAgent untuk dianalisis oleh AI Agent.
     */
    protected function queryN8nSuitAi(string $suitType, array $measurements, array $options = []): ?array
    {
        $webhookUrl = config('services.n8n.custom_suit_webhook_url');

        if (empty($webhookUrl)) {
            return null;
        }

        try {
            $payload = [
                'suit_type' => $suitType,
                'height' => $measurements['height'] ?? 170,
                'chest' => $measurements['chest'] ?? 96,
                'waist' => $measurements['waist'] ?? 82,
                'jacket_length' => $measurements['jacket_length'] ?? 74,
                'sleeve_length' => $measurements['sleeve_length'] ?? 62,
                'trouser_length' => $measurements['trouser_length'] ?? 100,
                'fabric_type' => $options['fabric_type'] ?? null,
                'color' => $options['color'] ?? null,
                'notes' => $options['notes'] ?? null,
                'has_image' => ! empty($options['image_base64']),
                'image_base64' => $options['image_base64'] ?? null,
                'image_name' => $options['image_name'] ?? null,
                'source' => 'web',
            ];

            $response = Http::timeout(5)->post($webhookUrl, $payload);

            if ($response->successful()) {
                $json = $response->json();

                return $json['ai_vision'] ?? $json;
            }
        } catch (\Throwable $e) {
            // Fallback gracefully jika n8n sedang offline atau webhook belum dieksekusi
            Log::warning('n8n custom suit webhook unreachable: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Menyimpan pesanan jas custom baru ke database & membukukan DP ke kasir/jurnal bila ada.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'order_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'suit_type' => ['required', 'string', Rule::in(array_keys(Product::CATEGORIES))],
            'material_id' => ['nullable', 'exists:materials,id'],
            'material_meters' => ['nullable', 'numeric', 'min:0'],
            'fabric_type' => ['nullable', 'string', 'max:150'],
            'color' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'reference_image' => ['nullable', 'image', 'max:5120'], // Max 5MB
            // Ukuran tubuh
            'height' => ['nullable', 'numeric'],
            'chest' => ['nullable', 'numeric'],
            'waist' => ['nullable', 'numeric'],
            'jacket_length' => ['nullable', 'numeric'],
            'sleeve_length' => ['nullable', 'numeric'],
            'trouser_length' => ['nullable', 'numeric'],
            // Keuangan & Biaya
            'material_cost' => ['nullable', 'numeric', 'min:0'],
            'labor_cost' => ['nullable', 'numeric', 'min:0'],
            'total_price' => ['required', 'numeric', 'min:0'],
            'down_payment' => ['nullable', 'numeric', 'min:0'],
            'account_id' => ['nullable', 'exists:accounts,id'],
        ]);

        $imagePath = null;
        if ($request->hasFile('reference_image')) {
            $imagePath = $request->file('reference_image')->store('custom_suits', 'public');
        }

        $measurements = [
            'height' => $validated['height'] ?? null,
            'chest' => $validated['chest'] ?? null,
            'waist' => $validated['waist'] ?? null,
            'jacket_length' => $validated['jacket_length'] ?? null,
            'sleeve_length' => $validated['sleeve_length'] ?? null,
            'trouser_length' => $validated['trouser_length'] ?? null,
        ];

        // Query n8n AI Vision & Styling
        $aiVisionData = $this->queryN8nSuitAi($validated['suit_type'], $measurements, [
            'fabric_type' => $validated['fabric_type'] ?? null,
        ]);

        // Hitung estimasi bahan dan biaya resmi
        $estimatorResult = $this->estimator->estimate(
            $validated['suit_type'],
            $measurements,
            [
                'material_id' => $validated['material_id'] ?? null,
                'fabric_price_per_meter' => $request->input('fabric_price_per_meter'),
                'labor_cost' => $validated['labor_cost'] ?? null,
            ],
            $aiVisionData
        );

        $materialCost = $validated['material_cost'] ?? $estimatorResult['materials']['total_material_cost'];
        $materialMeters = ! empty($validated['material_meters'])
            ? (float) $validated['material_meters']
            : (float) ($estimatorResult['materials']['main_fabric_meters'] ?? 0);

        $laborCost = $validated['labor_cost'] ?? $estimatorResult['labor']['labor_cost'];
        $totalCost = $materialCost + $laborCost;
        $totalPrice = (float) $validated['total_price'];
        $downPayment = (float) ($validated['down_payment'] ?? 0);

        $paymentStatus = 'unpaid';
        if ($downPayment >= $totalPrice && $totalPrice > 0) {
            $paymentStatus = 'paid';
        } elseif ($downPayment > 0) {
            $paymentStatus = 'partial_dp';
        }

        $orderNumber = 'CST-'.date('Ymd').'-'.strtoupper(Str::random(4));

        $order = DB::transaction(function () use (
            $validated,
            $orderNumber,
            $imagePath,
            $measurements,
            $estimatorResult,
            $materialCost,
            $materialMeters,
            $laborCost,
            $totalCost,
            $totalPrice,
            $downPayment,
            $paymentStatus
        ) {
            $customOrder = CustomSuitOrder::create([
                'order_number' => $orderNumber,
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'] ?? null,
                'order_date' => $validated['order_date'],
                'due_date' => $validated['due_date'] ?? null,
                'suit_type' => $validated['suit_type'],
                'material_id' => $validated['material_id'] ?? null,
                'fabric_type' => $validated['fabric_type'] ?? null,
                'color' => $validated['color'] ?? null,
                'body_measurements' => $measurements,
                'reference_image' => $imagePath,
                'ai_estimation' => $estimatorResult,
                'material_cost' => $materialCost,
                'material_meters' => $materialMeters,
                'is_material_cut' => false,
                'labor_cost' => $laborCost,
                'total_cost' => $totalCost,
                'total_price' => $totalPrice,
                'down_payment' => $downPayment,
                'payment_status' => $paymentStatus,
                'production_status' => 'consultation',
                'account_id' => $validated['account_id'] ?? null,
                'source' => 'web',
                'notes' => $validated['notes'] ?? null,
            ]);

            // Jika ada pembayaran uang muka (DP), bukukan langsung ke kasir akuntansi
            if ($downPayment > 0 && ! empty($validated['account_id'])) {
                $customRevenueAccount = Account::firstOrCreate(
                    ['code' => '4003'],
                    ['name' => 'Pendapatan Jasa Pembuatan Jas Custom', 'type' => 'revenue']
                );

                $journal = JournalEntry::create([
                    'reference' => $orderNumber,
                    'description' => "DP Pesanan Jas Custom {$customOrder->customer_name} ({$orderNumber})",
                    'date' => now(),
                    'source' => 'custom_suit',
                    'status' => 'verified',
                ]);

                JournalEntryLine::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $validated['account_id'],
                    'description' => "Penerimaan DP pesanan jas {$orderNumber}",
                    'debit' => $downPayment,
                    'credit' => 0,
                ]);

                JournalEntryLine::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $customRevenueAccount->id,
                    'description' => "Pendapatan jasa tailor {$orderNumber}",
                    'debit' => 0,
                    'credit' => $downPayment,
                ]);

                $customOrder->update(['journal_entry_id' => $journal->id]);
            }

            return $customOrder;
        });

        return redirect()->route('custom-orders.show', $order)
            ->with('success', "Pesanan pembuatan jas #{$order->order_number} berhasil dibuat dengan estimasi bahan & biaya lengkap.");
    }

    /**
     * Menampilkan detail lengkap pesanan jas, ukuran tubuh, foto referensi, dan rincian laba rugi.
     */
    public function show(CustomSuitOrder $order): View
    {
        $order->load(['account', 'journalEntry.lines.account']);

        $paymentAccounts = Account::where('type', 'asset')
            ->whereIn('code', ['1001', '1002'])
            ->get();
        if ($paymentAccounts->isEmpty()) {
            $paymentAccounts = Account::where('type', 'asset')->take(2)->get();
        }

        return view('custom-orders.show', [
            'order' => $order,
            'statuses' => CustomSuitOrder::PRODUCTION_STATUSES,
            'paymentStatuses' => CustomSuitOrder::PAYMENT_STATUSES,
            'paymentAccounts' => $paymentAccounts,
        ]);
    }

    /**
     * Memperbarui status tahap produksi (Pipeline pengerjaan jas).
     */
    public function updateStatus(Request $request, CustomSuitOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'production_status' => ['required', 'string', Rule::in(array_keys(CustomSuitOrder::PRODUCTION_STATUSES))],
        ]);

        $order->update(['production_status' => $validated['production_status']]);

        // Jika status berpindah ke proses potong/jahit dan bahan belum pernah dipotong
        if (in_array($validated['production_status'], ['cutting_sewing', 'fitting', 'finishing', 'ready', 'completed'], true)
            && ! $order->is_material_cut
            && $order->material_id
            && $order->material_meters > 0) {

            $material = Material::find($order->material_id);
            if ($material && $material->isPhysical()) {
                $material->decrement('stock', $order->material_meters);

                MaterialStockMovement::create([
                    'material_id' => $material->id,
                    'type' => 'out',
                    'quantity' => $order->material_meters,
                    'unit_cost' => $material->standard_cost,
                    'reference_type' => 'custom_order',
                    'reference_id' => $order->id,
                    'reference_number' => $order->order_number,
                    'notes' => "Pemotongan bahan kain {$order->material_meters}m untuk pesanan custom {$order->customer_name} (#{$order->order_number})",
                ]);

                $order->update(['is_material_cut' => true]);
            }
        }

        $statusLabel = CustomSuitOrder::PRODUCTION_STATUSES[$validated['production_status']] ?? $validated['production_status'];

        return back()->with('success', "Status produksi pesanan #{$order->order_number} berhasil diperbarui menjadi '{$statusLabel}'.");
    }

    /**
     * Aksi manual pemotongan bahan kain dari stok gudang untuk pesanan jas custom.
     */
    public function cutMaterial(CustomSuitOrder $order): RedirectResponse
    {
        if ($order->is_material_cut) {
            return back()->with('warning', 'Bahan untuk pesanan ini sudah pernah dipotong sebelumnya.');
        }

        if (! $order->material_id || $order->material_meters <= 0) {
            return back()->with('error', 'Pesanan ini belum memiliki tautan master bahan kain atau kuantitas meteran.');
        }

        $material = Material::findOrFail($order->material_id);
        if ($material->isPhysical()) {
            $material->decrement('stock', $order->material_meters);

            MaterialStockMovement::create([
                'material_id' => $material->id,
                'type' => 'out',
                'quantity' => $order->material_meters,
                'unit_cost' => $material->standard_cost,
                'reference_type' => 'custom_order',
                'reference_id' => $order->id,
                'reference_number' => $order->order_number,
                'notes' => "Pemotongan bahan kain {$order->material_meters}m untuk pesanan custom {$order->customer_name} (#{$order->order_number})",
            ]);

            $order->update(['is_material_cut' => true]);

            return back()->with('success', "Stok bahan '{$material->name}' berhasil dipotong sebanyak {$order->material_meters} {$material->unit}.");
        }

        return back()->with('error', 'Bahan yang dipilih bukan merupakan bahan fisik yang memiliki stok.');
    }

    /**
     * Mencatat pelunasan atau tambahan pembayaran dari pelanggan jas custom.
     */
    public function recordPayment(Request $request, CustomSuitOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'account_id' => ['required', 'exists:accounts,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $newTotalPaid = $order->down_payment + $validated['amount'];
        $newPaymentStatus = $newTotalPaid >= $order->total_price ? 'paid' : 'partial_dp';

        DB::transaction(function () use ($order, $validated, $newTotalPaid, $newPaymentStatus) {
            $customRevenueAccount = Account::firstOrCreate(
                ['code' => '4003'],
                ['name' => 'Pendapatan Jasa Pembuatan Jas Custom', 'type' => 'revenue']
            );

            // Bukukan pembayaran ke Jurnal Akuntansi
            $journal = JournalEntry::create([
                'reference' => 'PAY-'.$order->order_number.'-'.strtoupper(Str::random(3)),
                'description' => "Pembayaran Pesanan Jas {$order->customer_name} ({$order->order_number})",
                'date' => now(),
                'source' => 'custom_suit',
                'status' => 'verified',
            ]);

            JournalEntryLine::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $validated['account_id'],
                'description' => "Penerimaan kas/transfer pesanan jas {$order->order_number}",
                'debit' => $validated['amount'],
                'credit' => 0,
            ]);

            JournalEntryLine::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $customRevenueAccount->id,
                'description' => "Pendapatan jasa tailor {$order->order_number}",
                'debit' => 0,
                'credit' => $validated['amount'],
            ]);

            $order->update([
                'down_payment' => $newTotalPaid,
                'payment_status' => $newPaymentStatus,
                'account_id' => $validated['account_id'],
            ]);
        });

        return back()->with('success', 'Pembayaran sebesar Rp '.number_format($validated['amount'], 0, ',', '.').' berhasil dicatat dan dibukukan ke jurnal.');
    }
}
