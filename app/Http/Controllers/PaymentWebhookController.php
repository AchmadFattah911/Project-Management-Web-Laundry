<?php

namespace App\Http\Controllers;

use App\Payments\InvalidPaymentNotification;
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
        protected PaymentService $paymentService
    ) {}

    public function __invoke(Request $request, string $gateway): JsonResponse
    {
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
