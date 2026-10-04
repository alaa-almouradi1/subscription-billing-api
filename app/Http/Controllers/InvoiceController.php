<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Billing\InvoiceService;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

    /**
     * Newest first, with cursor pagination: each page is an index range scan
     * (customer_id, issued_at) instead of an OFFSET that gets slower the
     * deeper a client pages. Follow meta.next_cursor for the next page.
     */
    public function index(Request $request, Customer $customer): AnonymousResourceCollection
    {
        $perPage = max(1, min(100, $request->integer('per_page', 25)));

        return InvoiceResource::collection(
            $customer->invoices()->orderByDesc('issued_at')->orderByDesc('id')->cursorPaginate($perPage),
        );
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return InvoiceResource::make($invoice->load(['lines', 'payments']));
    }

    /**
     * Charge the invoice. A declined card is a valid outcome of the attempt,
     * so the response is 201 with the payment's status, not an error.
     */
    public function pay(PayInvoiceRequest $request, Invoice $invoice, PaymentService $payments): JsonResponse
    {
        $payment = $payments->pay($invoice, $request->validated('payment_method'));

        return PaymentResource::make($payment)->response()->setStatusCode(201);
    }

    public function void(Invoice $invoice): InvoiceResource
    {
        return InvoiceResource::make($this->invoices->void($invoice)->load('lines'));
    }

    public function markUncollectible(Invoice $invoice): InvoiceResource
    {
        return InvoiceResource::make($this->invoices->markUncollectible($invoice)->load('lines'));
    }
}
