<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvoiceResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Billing\InvoiceService;
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
        return InvoiceResource::make($invoice->load('lines'));
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
