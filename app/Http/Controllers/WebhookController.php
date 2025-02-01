<?php

namespace App\Http\Controllers;

use App\Services\AffiliateService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    /**
     * Pass the necessary data to the process order method
     * 
     * @param  Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        // TODO: Complete this method
        $validatedData = $request->validate([
            'order_id' => 'required|string',
            'subtotal_price' => 'required|numeric',
            'merchant_domain' => 'required|string',
            'discount_code' => 'nullable|string',
        ]);

        try {
            $this->orderService->processOrder($validatedData);

            return response()->json([
                'message' => 'Order processed successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error("Webhook processing failed: " . $e->getMessage());

            return response()->json([
                'message' => 'Failed to process order.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
