<?php

namespace App\Payments;

use RuntimeException;

/**
 * Dilempar jika notifikasi gateway tidak valid (signature salah, payload rusak, nominal tidak cocok).
 */
class InvalidPaymentNotification extends RuntimeException
{
}
