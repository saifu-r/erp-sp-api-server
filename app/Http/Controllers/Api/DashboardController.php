<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Accounts\Account;
use App\Models\Accounts\JournalEntryLine;
use App\Models\Manufacture\Item;
use App\Models\Manufacture\RawMaterial;
use App\Models\Purchase\Supplier;
use App\Models\Sales\Customer;
use App\Models\Sales\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->endOfMonth()->toDateString());

        return response()->json([
            'kpis' => $this->kpis($from, $to),
            'collections' => $this->collectionsList($from, $to),
            'counts' => $this->counts(),
            'expense_breakdown' => $this->expenseBreakdown($from, $to),
            'recent_transactions' => $this->recentTransactions(),
            'top_customers' => $this->topCustomers($from, $to),
            'top_products' => $this->topProducts($from, $to),
            'cash_bank' => $this->cashBank(),
            'inventory_value' => $this->inventoryValue(),
        ]);
    }

    // private function kpis(string $from, string $to): array
    // {
    //     $totalSales = Transaction::where('type', 'invoice')->whereBetween('date', [$from, $to])->sum('total_amount');
    //     $totalPurchase = Transaction::where('type', 'purchase')->whereBetween('date', [$from, $to])->sum('total_amount');

    //     // Payable/Receivable are ALWAYS current balances — not scoped to the date range
    //     $payable = Transaction::where('type', 'purchase')
    //         ->whereIn('payment_status', [1, 2])
    //         ->get()
    //         ->sum(fn($t) => $t->total_amount - $t->paid_amount);

    //     $receivable = Transaction::where('type', 'invoice')
    //         ->whereIn('payment_status', [1, 2])
    //         ->get()
    //         ->sum(fn($t) => $t->total_amount - $t->paid_amount - $t->write_off_amount);

    //     return [
    //         'total_sales' => (float) $totalSales,
    //         'total_purchase' => (float) $totalPurchase,
    //         'accounts_payable' => (float) $payable,
    //         'accounts_receivable' => (float) $receivable,
    //     ];
    // }

    private function kpis(string $from, string $to): array
    {
        $totalSales = Transaction::where('type', 'invoice')->whereBetween('date', [$from, $to])->sum('total_amount');
        $totalPurchase = Transaction::where('type', 'purchase')->whereBetween('date', [$from, $to])->sum('total_amount');

        $accountsPayableId = Account::where('code', '2100')->value('id');
        $accountsReceivableId = Account::where('code', '1300')->value('id');

        $payable = JournalEntryLine::where('account_id', $accountsPayableId)
            ->selectRaw('SUM(credit) - SUM(debit) as balance')->value('balance') ?? 0;

        $receivable = JournalEntryLine::where('account_id', $accountsReceivableId)
            ->selectRaw('SUM(debit) - SUM(credit) as balance')->value('balance') ?? 0;

        return [
            'total_sales' => (float) $totalSales,
            'total_purchase' => (float) $totalPurchase,
            'accounts_payable' => (float) $payable,
            'accounts_receivable' => (float) $receivable,
        ];
    }

    private function counts(): array
    {
        return [
            'suppliers' => Supplier::where('status', 1)->count(),
            'customers' => Customer::where('status', 1)->count(),
            'products' => Product::where('status', 1)->count(),
            'orders' => Transaction::where('type', 'order')->count(),
        ];
    }

    // private function salesVsPurchaseTrend(string $from, string $to): array
    // {
    //     $sales = Transaction::where('type', 'invoice')
    //         ->whereBetween('date', [$from, $to])
    //         ->selectRaw('date, SUM(total_amount) as total')
    //         ->groupBy('date')->orderBy('date')->pluck('total', 'date');

    //     $purchases = Transaction::where('type', 'purchase')
    //         ->whereBetween('date', [$from, $to])
    //         ->selectRaw('date, SUM(total_amount) as total')
    //         ->groupBy('date')->orderBy('date')->pluck('total', 'date');

    //     $period = Carbon::parse($from)->daysUntil(Carbon::parse($to));
    //     $rows = [];
    //     foreach ($period as $day) {
    //         $key = $day->toDateString();
    //         $rows[] = [
    //             'date' => $key,
    //             'sales' => (float) ($sales[$key] ?? 0),
    //             'purchase' => (float) ($purchases[$key] ?? 0),
    //         ];
    //     }
    //     return $rows;
    // }


    // private function expenseBreakdown(string $from, string $to): array
    // {
    //     return \App\Models\Accounts\Expense::with('expenseType')
    //         ->whereBetween('date', [$from, $to])
    //         ->selectRaw('expense_type_id, SUM(amount) as total')
    //         ->groupBy('expense_type_id')
    //         ->orderByDesc('total')
    //         ->get()
    //         ->map(fn($e) => [
    //             'name' => $e->expenseType->name ?? '—',
    //             'total' => (float) $e->total,
    //         ])
    //         ->toArray();
    // }

    private function expenseBreakdown(string $from, string $to): array
    {
        return \App\Models\Accounts\JournalEntryLine::join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('accounts.type', 'expense')
            ->whereNotIn('accounts.code', ['5100', '5150']) // exclude COGS and Discount Allowed — not operating expenses
            ->whereBetween('journal_entries.date', [$from, $to])
            ->groupBy('accounts.id', 'accounts.name')
            ->selectRaw('accounts.name, SUM(journal_entry_lines.debit) - SUM(journal_entry_lines.credit) as total')
            ->having('total', '>', 0)
            ->orderByDesc('total')
            ->get()
            ->map(fn($row) => ['name' => $row->name, 'total' => (float) $row->total])
            ->toArray();
    }
    private function recentTransactions(): array
    {
        $feed = [];

        // Sales & Purchases
        foreach (
            Transaction::with(['customer', 'supplier'])->whereIn('type', ['invoice', 'purchase'])
                ->latest('date')->latest('id')->limit(15)->get() as $t
        ) {
            $feed[] = [
                'type' => $t->type === 'invoice' ? 'Sale' : 'Purchase',
                'party' => $t->type === 'invoice' ? ($t->customer->name ?? '—') : ($t->supplier->name ?? '—'),
                'reference_no' => $t->reference_no,
                'amount' => (float) $t->total_amount,
                'date' => $t->date,
                'sort' => $t->created_at,
            ];
        }

        // Payments — both sale-side and purchase-side
        foreach (
            \App\Models\TransactionPayment::with(['transaction.customer', 'transaction.supplier'])
                ->latest('created_at')->limit(15)->get() as $p
        ) {
            $tx = $p->transaction;
            $isSale = $tx->type === 'invoice';
            $feed[] = [
                'type' => $isSale ? 'Payment Received' : 'Payment Made',
                'party' => $isSale ? ($tx->customer->name ?? '—') : ($tx->supplier->name ?? '—'),
                'reference_no' => $tx->reference_no,
                'amount' => (float) $p->amount,
                'date' => $p->date,
                'sort' => $p->created_at,
            ];
        }

        // Write-offs — no dedicated table (write_off_amount is cumulative on the invoice),
        // so the Journal is the only reliable per-event log of when each write-off happened
        foreach (
            \App\Models\Accounts\JournalEntry::where('reference_type', 'invoice_write_off')
                ->with('lines')->latest('created_at')->limit(10)->get() as $j
        ) {
            $invoice = Transaction::with('customer')->find($j->reference_id);
            $amount = $j->lines->max('debit'); // the Discount Allowed debit line = the write-off amount
            $feed[] = [
                'type' => 'Write-off',
                'party' => $invoice->customer->name ?? '—',
                'reference_no' => $invoice->reference_no ?? '',
                'amount' => (float) $amount,
                'date' => $j->date,
                'sort' => $j->created_at,
            ];
        }

        // Expenses
        foreach (\App\Models\Accounts\Expense::with('expenseType')->latest('created_at')->limit(10)->get() as $e) {
            $feed[] = [
                'type' => 'Expense',
                'party' => $e->expenseType->name ?? '—',
                'reference_no' => 'EXP-' . $e->id,
                'amount' => (float) $e->amount,
                'date' => $e->date,
                'sort' => $e->created_at,
            ];
        }

        usort($feed, fn($a, $b) => $b['sort'] <=> $a['sort']);

        return array_slice(array_map(fn($f) => [
            'type' => $f['type'],
            'party' => $f['party'],
            'reference_no' => $f['reference_no'],
            'amount' => $f['amount'],
            'date' => $f['date'],
        ], $feed), 0, 10);
    }

    private function topCustomers(string $from, string $to): array
    {
        return Transaction::where('type', 'invoice')
            ->whereBetween('date', [$from, $to])
            ->with('customer')
            ->selectRaw('customer_id, SUM(total_amount) as total')
            ->groupBy('customer_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn($t) => ['name' => $t->customer->name ?? '—', 'total' => (float) $t->total])
            ->toArray();
    }

    private function topProducts(string $from, string $to): array
    {
        return TransactionItem::whereHas('transaction', function ($q) use ($from, $to) {
            $q->where('type', 'invoice')->whereBetween('date', [$from, $to]);
        })
            ->with('product')
            ->selectRaw('product_id, SUM(quantity) as qty, SUM(subtotal) as revenue')
            ->groupBy('product_id')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(fn($i) => ['name' => $i->product->name ?? '—', 'quantity' => (float) $i->qty, 'revenue' => (float) $i->revenue])
            ->toArray();
    }

    private function cashBank(): float
    {
        $accountIds = Account::whereIn('code', ['1100', '1200'])->pluck('id');
        $balance = JournalEntryLine::whereIn('account_id', $accountIds)
            ->selectRaw('SUM(debit) - SUM(credit) as balance')
            ->value('balance');
        return (float) ($balance ?? 0);
    }

    private function inventoryValue(): float
    {
        $rawMaterialValue = \App\Models\Manufacture\RawMaterialBatch::selectRaw('SUM(quantity_remaining * cost_per_unit) as value')->value('value') ?? 0;
        $itemValue = Item::selectRaw('SUM(stock_quantity * avg_cost_per_unit) as value')->value('value') ?? 0;
        return (float) ($rawMaterialValue + $itemValue);
    }

    private function collectionsList(string $from, string $to): array
    {
        $payments = \App\Models\TransactionPayment::with('transaction.customer')
            ->whereHas('transaction', fn($q) => $q->where('type', 'invoice'))
            ->whereBetween('date', [$from, $to]) // filter by the PAYMENT's own date, not the invoice's date
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $total = (float) $payments->sum('amount');

        $recent = $payments->take(10)->map(fn($p) => [
            'customer_name' => $p->transaction->customer->name ?? '—',
            'date' => $p->date,
            'invoice_reference_no' => $p->transaction->reference_no,
            'amount' => (float) $p->amount,
        ])->values()->toArray();

        return ['total' => $total, 'items' => $recent];
    }
}
