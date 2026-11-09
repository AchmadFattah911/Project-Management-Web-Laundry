<?php

namespace App\Http\Controllers\Panel\Concerns;

use App\Exceptions\IdempotencyException;
use Closure;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

/**
 * Pelanggaran aturan bisnis dari service → kembali ke form dengan flash error yang jelas (bukan halaman 500).
 */
trait HandlesServiceErrors
{
    /**
     * @param  Closure(): RedirectResponse  $action
     */
    protected function attempt(Closure $action, ?string $modal = null): RedirectResponse
    {
        try {
            return $action();
        } catch (IdempotencyException $e) {
            $message = $e->reason === IdempotencyException::IN_PROGRESS
                ? 'Form ini sedang diproses. Tunggu sebentar lalu muat ulang halaman.'
                : 'Form ini sudah pernah dikirim dengan isi berbeda. Muat ulang halaman lalu isi kembali.';

            return back()->with('error', $message)->withInput(request()->except('submission_token'));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())
                ->with('active_modal', $modal)
                ->withInput(request()->except('submission_token'));
        }
    }
}
