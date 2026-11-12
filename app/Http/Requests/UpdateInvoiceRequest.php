<?php

namespace App\Http\Requests;

class UpdateInvoiceRequest extends StoreInvoiceRequest
{
    // Same shape as creation: a draft invoice is replaced wholesale,
    // items included. Non-draft invoices reject updates in the controller.
}
