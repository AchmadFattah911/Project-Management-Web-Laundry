<?php

namespace App\Http\Controllers;

use App\Payments\Gateways\MidtransGateway;
use App\Payments\InvalidPaymentNotification;
use App\Services\MidtransNotificationService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * Endpoint notifikasi dari payment gateway: POST /payments/webhook/{gateway}
 * Dikecualikan dari CSRF (dipanggil server gateway), keamanan dijamin oleh verifikasi signature.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected MidtransNotificationService $midtrans,
    ) {}

    public function __invoke(Request $request, string $gateway): JsonResponse
    {
        // Midtrans: simpan → signature (403) → konfirmasi API status → terapkan (lihat MidtransNotificationService)
        if ($gateway === MidtransGateway::NAME) {
            $outcome = $this->midtrans->handleWebhook($request->getContent(), $request->ip());

            return response()->json(['message' => $outcome['result']], $outcome['http']);
        }

        $headers = collect($request->headers->all())
            ->mapWithKeys(fn ($values, $key) => [strtolower($key) => (string) ($values[0] ?? '')])
            ->all();

        try {
            $payment = $this->paymentService->handleNotification($gateway, $request->getContent(), $headers);
        } catch (InvalidPaymentNotification $e) {
            Log::warning('Notifikasi pembayaran ditolak', ['gateway' => $gateway, 'error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 400);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return response()->json(['message' => 'Gateway tidak tersedia.'], 404);
        }

        return response()->json(['message' => 'OK', 'status' => $payment->status]);
    }
}
