<?php

namespace App\Listeners;

use App\Events\PromoCodeStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\PromoCode;
use Carbon\Carbon;
use DateTimeZone;
use App\Http\Traits\ApiTrait;

class UpdatePromoCodeStatus
{
    use ApiTrait;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\PromoCodeStatus  $event
     * @return void
     */
    public function handle(PromoCodeStatus $event)
    {
        $PromoCodes = PromoCode::where('status', 'Active')->findOrFail();
        $today = Carbon::now(new DateTimeZone($this->timezone))->toDateTimeString();
        foreach($PromoCodes as $code) {
            if($code->expiry == $today) {
                $PromoCodes::where('promo_code', $code->promo_code)->update(['status' => 'Expired']);
            }
        }
    }
}
