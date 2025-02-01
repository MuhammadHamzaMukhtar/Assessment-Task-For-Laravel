<?php

namespace App\Services;

use App\Exceptions\AffiliateCreateException;
use App\Mail\AffiliateCreated;
use App\Models\Affiliate;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class AffiliateService
{
    public function __construct(
        protected ApiService $apiService
    ) {}

    /**
     * Create a new affiliate for the merchant with the given commission rate.
     *
     * @param  Merchant $merchant
     * @param  string $email
     * @param  string $name
     * @param  float $commissionRate
     * @return Affiliate
     */
    public function register(Merchant $merchant, string $email, string $name, float $commissionRate): Affiliate
    {
        if (User::where(['email' => $email, 'type' => User::TYPE_MERCHANT])->exists()) {
            throw new AffiliateCreateException('Email is already in use as a merchant.');
        }

        if (Affiliate::where('user_id', $merchant->user->id)->exists()) {
            throw new AffiliateCreateException('Email is already in use as an affiliate.');
        }

        $user = User::create([
            'email' => $email,
            'name' => $name,
            'type' => User::TYPE_AFFILIATE
        ]);

        $discount = $this->apiService->createDiscountCode($merchant);

        $affiliate = $user->affiliate()->create([
            'merchant_id'       => $merchant->id,
            'commission_rate'   => $commissionRate,
            'discount_code'     => $discount['code'],
        ]);

        $this->sendAffiliateRegisteredEmail($affiliate, $email);

        return $affiliate;
    }

    protected function sendAffiliateRegisteredEmail(Affiliate $affiliate, string $email)
    {
        try {
            Mail::to($email)->send(new AffiliateCreated($affiliate));
        } catch (\Exception $e) {
            throw new AffiliateCreateException('Failed to send affiliate registration email');
        }
    }
}
