<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Kejadian keuangan jatuh di periode yang sudah ditutup buku.
 */
class PeriodClosedException extends InvalidArgumentException {}
