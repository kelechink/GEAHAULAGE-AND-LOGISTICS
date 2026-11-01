<?php

declare(strict_types=1);

namespace App\Domain\Shipping;

use RuntimeException;

/** A business-rule refusal with a message staff can act on. */
final class ShippingException extends RuntimeException {}
