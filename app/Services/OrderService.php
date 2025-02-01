<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use Faker\Factory as Faker;

class OrderService
{
    public function __construct(
        protected AffiliateService $affiliateService,
        protected MerchantService $merchantService,
    ) {}

    /**
     * Process an order and log any commissions.
     * This should create a new affiliate if the customer_email is not already associated with one.
     * This method should also ignore duplicates based on order_id.
     *
     * @param  array{order_id: string, subtotal_price: float, merchant_domain: string, discount_code: string, customer_email: string, customer_name: string} $data
     * @return void
     */
    public function processOrder(array $data)
    {
        $faker = Faker::create();

        $merchant = Merchant::where('domain', $data['merchant_domain'])->first();

        if (is_null($merchant)) {
            $merchantData = [
                'domain' => $data['merchant_domain'],
                'name' => $faker->name(),
                'email' => $faker->unique()->email(),
                'api_key' => $data['api_key'] ?? $faker->password(),
            ];
            $merchant = $this->merchantService->register($merchantData);
        }

        $affiliate = Affiliate::where('discount_code', $data['discount_code'])->first();

        $user = null;
        if (isset($data['customer_email'])) {
            $user = User::where('type', User::TYPE_AFFILIATE)->where('email', $data['customer_email'])->first();
        }

        if ((isset($data['customer_email']) && isset($data['customer_name']) && !$user) || !isset($data['customer_email'])) {
            $customerName = $data['customer_name'] ?? $faker->name();
            $customerEmail = $data['customer_email'] ?? $faker->unique()->email();

            $this->affiliateService->register($merchant, $customerEmail, $customerName, 0.1);

            $user = User::with('affiliate')->where('email', $customerEmail)->first();

            if (!$affiliate && $user)
                $affiliate = $user?->affiliate;
        }

        $order = Order::where('external_order_id', $data['order_id'])->first();

        if ($order) return 'order found';

        $commissionOwed = $data['subtotal_price'] * $affiliate->commission_rate;

        $orderData = [
            'subtotal' => $data['subtotal_price'],
            'affiliate_id' => $affiliate->id,
            'merchant_id' => $merchant->id,
            'commission_owed' => $commissionOwed,
            'external_order_id' => $data['order_id']
        ];

        Order::create($orderData);
    }
}
