<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use RuntimeException;

/** A finance rule refusal with a message staff can act on. */
final class FinanceException extends RuntimeException {}
