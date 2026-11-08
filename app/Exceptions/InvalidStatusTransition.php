<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Perubahan status yang tidak diizinkan OrderStateMachine. Turunan InvalidArgumentException agar
 * controller yang sudah ada tetap menampilkannya sebagai pesan error biasa.
 */
class InvalidStatusTransition extends InvalidArgumentException {}
