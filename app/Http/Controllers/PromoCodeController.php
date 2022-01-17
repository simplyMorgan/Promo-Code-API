<?php

namespace App\Http\Controllers;

use App\Events\PromoCodeStatus;
use App\Models\PromoCode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Traits\ApiTrait;
use DateTimeZone;

class PromoCodeController extends Controller
{
    protected $status = false;
    protected $message = null;
    protected $data = null;

    use ApiTrait;

    /**
     * Display a listing of all resources.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            event(new PromoCodeStatus);
            $this->message = 'No data';
            $promo_codes_count = PromoCode::all()->count();
            if($promo_codes_count > 0) {
                $this->status = true;
                $this->message = 'Showing results for all promo codes';
                $data = array();
                foreach(PromoCode::all() as $row) {
                    array_push($data, $row);
                }
                $this->data = $data;
            }

            return $this->apiResonse($this->data, $this->message, $this->status);

        } catch(\Exception $e) {
            return $this->apiResonse($this->data, $e->getMessage(), $this->status);
        }
    }

    /**
     * Generates a new resource.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        try  {
            $rules = [
                'amount' => 'required|numeric',
                'radius' => 'nullable|numeric|between:1.00,100.0',
                'event_id' => 'required|integer'
            ];
            $customMessages = [
                'amount.required' => 'Promo code amount is required',
                'amount.numeric' => 'Invalid value supplied for promo code amount',
                'radius.numeric' => 'Invalid value supplied for promo code raduis',
                'radius.between' => 'Promo code radius must be between 1.0 and 100.0 km',
                'event_id.required' => 'Promo code Event ID is required',
                'event_id.integer' => 'Event ID is not valid',
            ];
            $passed_data = $request->only('amount', 'radius', 'event_id');
            $valid = $this->validateApiData($passed_data, $rules, $customMessages);
            if($valid) {
                $this->message = 'Unable to generate promo code';    // sets default message
                $allowed_chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                $promo_code = substr(str_shuffle($allowed_chars), 0, 8); 
                if($this->isUnique($promo_code)) {
                    $today = Carbon::now(new DateTimeZone($this->timezone));
                    $expiry = $today->addDays(7);       // set promo code to expire in 7 days
                    $amount = number_format($passed_data['amount']);
                    $radius = (empty($passed_data['radius']) || is_null($passed_data['radius']) || !isset($passed_data['radius'])) ? number_format(5, 2) : number_format($passed_data['radius'], 2);      // sets a default radius of 5km
                    $data = array(
                        'promo_code' => $promo_code,
                        'expiry' => $expiry->toDateTimeString(),
                        'amount' => $amount,
                        'currency' => 'NGN',
                        'status' => 'Active',
                        'radius' => $radius,
                        'event_id' => $passed_data['event_id'],
                        'created_at' => Carbon::now(new DateTimeZone($this->timezone))->toDateTimeString()
                    );
                    if($this->store($data)) {
                        $this->status = true; $this->message = 'Promo code has been generated successfully';
                        $this->data = $data;
                    } 
    
                    return $this->apiResonse($this->data, $this->message, $this->status);
    
                }
    
                return $this->create($request);
            } 
            
            return $this->validationErrorMessage();

        } catch(\Exception $e) {
            return $this->apiResonse($this->data, $e->getMessage(), $this->status);
        }
               
    }

     /**
     * Checks if another instance of the generated resource already exists in storage.
     *
     * @param  string  $promo_code
     * @return bool
     */
    protected function isUnique($promo_code)
    {
        $exits = PromoCode::where('promo_code', $promo_code)->exists();
        if($exits) 
            return false;
        else
            return true;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  array  $data
     * @return bool
     */
    public function store(array $data)
    {
        $data = (object) $data;
        $promoCode =  new PromoCode;
        $promoCode->promo_code = $data->promo_code;
        $promoCode->expiry = $data->expiry;
        $promoCode->amount = $data->amount;
        $promoCode->currency = $data->currency;
        $promoCode->radius = $data->radius;
        $promoCode->event_id = $data->event_id;
        if($promoCode->save())
            return true;
        
        return false;
    }
    
    /**
     * Display a specified resource.
     *
     * @param  \App\Models\PromoCode  $PromoCode
     * @return \Illuminate\Http\JsonResponse
     */
    public function showActivePromoCodes(PromoCode $PromoCode)
    {
        try {
            event(new PromoCodeStatus);
            $this->message = 'No data';
            $promo_codes_count = $PromoCode::all()->count();
            if($promo_codes_count > 0) {
                $this->status = true;
                $this->message = 'Showing results for active promo codes';
                $data = array();
                foreach($PromoCode::where('status', 'Active')->get() as $row) {
                    array_push($data, $row);
                }
                $this->data = $data;
            }
            
            return $this->apiResonse($this->data, $this->message, $this->status);

        } catch(\Exception $e) {
            return $this->apiResonse($this->data, $e->getMessage(), $this->status);
        }
        
    }

    public function checkPromoCodeValidity(Request $request)
    {
        try {
            $rules = [
                'origin' => 'required|array:lat,lng',
                'destination' => 'required|array:lat,lng',
                'promo_code' => 'required|alpha_num|max:8'
            ];
            $customMessages = [
                'origin.required' => 'Origin coordinates is required',
                'destination.required' => 'Destination coordinates is required',
                'promo_code.required' => 'Promo code is required',
                'promo_code.alpha_num' => 'Promo code is not valid',
                'promo_code.max' => 'Promo code is incorrect',
            ];
            $passed_data = $request->only('origin', 'destination', 'promo_code');
            $valid = $this->validateApiData($passed_data, $rules, $customMessages);
            if($valid) {
                if($this->promoCodeIsActive($passed_data['promo_code'])) {
                    $this->message = 'Promo code is not valid';     // set a default message
                    $promo_code = $this->getPromoCodeData($passed_data['promo_code']);
                    $event_venue = $this->getEventVenue($promo_code->event_id);
        
                    $origin = (object) $passed_data['origin'];
                    $destination = (object) $passed_data['destination'];
                    $event = (object) $event_venue;
        
                    $distanceFromOrigin      = $this->getDistance($origin, $event);
                    $distanceFromDestination = $this->getDistance($destination, $event);
        
                    if(($distanceFromOrigin <= $promo_code->raduis) || ($distanceFromDestination <= $promo_code->raduis)) {
                        $this->status = true;
                        $this->message = 'Promo code is valid';
                        $data = array(
                            'promo_code' => $promo_code->promo_code,
                            'expiry'     => $promo_code->expiry,
                            'amount'     => $promo_code->amount,
                            'currency'   => $promo_code->currency,
                            'status'     => $promo_code->status,
                            'radius'     => $promo_code->radius,
                            'event_id'   => $promo_code->event_id,
                            'created_at' => $promo_code->created_at,
                            'polyline'   => $this->drawPolyline($origin, $destination)
                        );
                    }
        
                    return $this->apiResonse($this->data, $this->message, $this->status);
                }
                $this->message = 'Promo code has expired or has been deactivated';
                return $this->apiResonse($this->data, $this->message, $this->status);
            }
    
            return $this->validationErrorMessage();

        } catch(\Exception $e) {
            return $this->apiResonse($this->data, $e->getMessage(), $this->status);
        }

    }

    protected function promoCodeIsActive($code)
    {
        $promocode = PromoCode::where('promo_code', $code)->get();
        if($promocode->first()->status == 'Active') 
            return true;
        
        return false;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\PromoCode  $PromoCode
     * @return \Illuminate\Http\Response
     */
    public function deactivatePromoCode(PromoCode $PromoCode, $code)
    {
        try {
            $data = array('code' => $code);
            $rules = [
                'code' => 'required|alpha_num|max:8'
            ];
            $customMessages = [
                'required' => 'Promo code is required',
                'alpha_num' => 'Promo code is invalid',
                'max' => 'Promo code is incorrect'
            ];
            $this->message = 'Unable to deactivate promo code. Please try again';
            $validated = $this->validateApiData($data, $rules, $customMessages);
            if($validated) {
                $updated = $PromoCode::where('promo_code', $data['code'])->update(['status' => 'Inactive']);
                if($updated) {
                    $this->status = true;
                    $this->message = 'Promo code has been deactivated successfully';
                }
    
                return $this->apiResonse($this->data, $this->message, $this->status);
            }
    
            return $this->validationErrorMessage();

        } catch(\Exception $e) {
            return $this->apiResonse($this->data, $e->getMessage(), $this->status);
        }
        
    }

    protected function getCoordinates($address)
    {
        $address = ucwords(strtolower($address));
        $address = str_replace(',', ' ', $address);
        $formattedAddress = str_replace(' ', '+', $address);
        $apiKey = '';
        $geocode = file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?address='.$formattedAddress.'&sensor=false&key='.$apiKey);
        $output = json_decode($geocode);
        if(!empty($output->error_message)) {
            return $this->apiResonse($this->data, $output->error_message, $this->status);
        }

        $latitude = $output->results[0]->geometry->location->lat;
        $longitude = $output->results[0]->geometry->location->lng;
        $response = array('lat' => $latitude, 'lng' => $longitude);
        return (object) $response;
    }

    protected function getDistance(object $location, object $event)
    {
        $lngFrom = $location->lng;
        $latFrom = $location->lat;
        $lngTo   = $event->lng;
        $latTo   = $event->lat;

        $det = $lngFrom - $lngTo;
        $dist = sin(deg2rad($latFrom)) * sin(deg2rad($latTo)) + cos(deg2rad($latFrom)) * cos(deg2rad($latTo)) * cos(deg2rad($det));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        $km = round($miles * 1.609344, 2);
        return $km;
    }


    protected function getPromoCodeData($promo_code)
    {
        $data = PromoCode::where('promo_code', $promo_code)->get()->first();
        return $data;
    }

    protected function getEventVenue($event_id)
    {
        return '7.4189195393176135, 3.9136517575203715';      // set a temporary event location for all promo codes regardless of their event id
    }

    protected function drawPolyline($origin, $destination)
    {
        # code...
    }


}
