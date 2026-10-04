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
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function index(Customer $customer): AnonymousResourceCollection
    {
        return InvoiceResource::collection(
            $customer->invoices()->latest('issued_at')->latest('number')->paginate(25),
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
