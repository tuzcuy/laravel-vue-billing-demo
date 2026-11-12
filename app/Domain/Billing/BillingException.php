<?php

namespace App\Domain\Billing;

use RuntimeException;

/**
 * A business-rule violation (invalid transition, overpayment, ...).
 * API controllers map this to a 422 response.
 */
class BillingException extends RuntimeException
{
}
