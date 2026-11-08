<?php

use App\Models\IdempotencyKey;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Bersihkan idempotency key pembayaran yang sudah lewat masa simpan
Schedule::command('model:prune', ['--model' => [IdempotencyKey::class]])->daily();

// Pembayaran Midtrans: jaring pengaman webhook & penutupan transaksi lewat batas bayar.
// withoutOverlapping: putaran berikutnya dilewati jika putaran sebelumnya belum selesai.
Schedule::command('payments:reconcile')->everyFiveMinutes()->withoutOverlapping(10);
Schedule::command('checkouts:expire')->everyFiveMinutes()->withoutOverlapping(10);
