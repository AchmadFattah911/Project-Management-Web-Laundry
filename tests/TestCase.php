<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tidak ada request jaringan sungguhan dari test (Midtrans selalu lewat Http::fake),
        // juga saat suite dijalankan dengan PAYMENT_ENABLED=true
        Http::preventStrayRequests();
    }
}
