<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Baris hanya boleh ditambah. Koreksi dicatat sebagai baris baru (mis. void_reversal), bukan update/delete.
 */
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(fn ($model) => throw new LogicException(class_basename($model) . ' bersifat append-only dan tidak boleh diubah.'));
        static::deleting(fn ($model) => throw new LogicException(class_basename($model) . ' bersifat append-only dan tidak boleh dihapus.'));
    }
}
