<?php

namespace App\Services;

use App\Jobs\PayoutOrderJob;
use App\Models\Affiliate;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class MerchantService
{
    /**
     * Register a new user and associated merchant.
     * Hint: Use the password field to store the API key.
     * Hint: Be sure to set the correct user type according to the constants in the User model.
     *
     * @param array{domain: string, name: string, email: string, api_key: string} $data
     * @return Merchant
     */
    public function register(array $data): Merchant
    {
        $user = User::create([
            'name'      => $data['name'],
            'type'      => User::TYPE_MERCHANT,
            'email'     => $data['email'],
            'password'  => $data['api_key']
        ]);

        $merchant = $user->merchant()->create([
            'domain'        => $data['domain'],
            'display_name'  => $data['name'],
        ]);

        return $merchant;
    }

    /**
     * Update the user
     *
     * @param array{domain: string, name: string, email: string, api_key: string} $data
     * @return void
     */
    public function updateMerchant(User $user, array $data)
    {
        $user->update([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => $data['api_key']
        ]);

        $user->merchant()->update(['domain' => $data['domain'], 'display_name' => $data['name']]);
    }

    /**
     * Find a merchant by their email.
     * Hint: You'll need to look up the user first.
     *
     * @param string $email
     * @return Merchant|null
     */
    public function findMerchantByEmail(string $email): ?Merchant
    {
        // TODO: Complete this method
        return User::where('email', $email)
            ->where('type', User::TYPE_MERCHANT)
            ->first()?->merchant;
    }

    /**
     * Pay out all of an affiliate's orders.
     * Hint: You'll need to dispatch the job for each unpaid order.
     *
     * @param Affiliate $affiliate
     * @return void
     */
    public function payout(Affiliate $affiliate)
    {
        $unpaidOrders = $affiliate->orders()->where("payout_status", Order::STATUS_UNPAID)->get();
        if ($unpaidOrders) {
            foreach ($unpaidOrders as $order) {
                PayoutOrderJob::dispatch($order);
            }
        }
    }

    public function orderStats(string $from, string $to): array
    {
        $orders = Order::whereBetween("created_at", [$from, $to]);
        $totalOrders = $orders->count();

        $unpaidCommissionsAmount = $orders->whereHas('affiliate')
            ->where('payout_status', Order::STATUS_UNPAID)
            ->sum('commission_owed');

        $revenue = Order::whereBetween("created_at", [$from, $to])->sum('subtotal');

        return [
            'count'             => $totalOrders,
            'commissions_owed'  => $unpaidCommissionsAmount,
            'revenue'           => $revenue
        ];
    }
}
