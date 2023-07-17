<?php

namespace MIorder\Driver\Process;

use Admin\Models\Locations_model;
use Igniter\Flame\Cart\CartCondition;
use Igniter\Flame\Cart\Facades\Cart;
use Igniter\Local\Facades\Location;
use GuzzleHttp\Client;
use Igniter\Flame\Geolite\Model\Coordinates;
use Carbon\Carbon;
use Exception;

class Delivery extends CartCondition
{
    public $priority = 100;

    protected $deliveryCharge = 0;

    protected $minimumOrder = 10;
  
    
  
    public function GetToken(){
      $url = "https://api.stuart.com/oauth/token";
      $client = new Client();

      $response = $client->post($url, [
         'form_params' => [
           'client_id' => '',
           'client_secret' => '',
           'scope' => 'api',
           'grant_type' => 'client_credentials'
         ] 
      ]);
  
      $Data = $response->getBody()->getContents();

       
       $result = json_decode($Data, true);
      
      return $result['access_token'];

    }
  
    public function stuartPrice($pickup, $address, $token){
          
          $requestData =   array(
                'job' => array(
                    'transport_type' => null,
                    'assignment_code' => 'Asdas',
                    'pickups' => array(
                        array(
                            'address' => '1250 London Rd Alvaston Derby DE24 8QP Derbyshire',
                            'comment' => '1250',
                            'contact' => array(
                                'firstname' => 'Steves Fish Bar',
                                'lastname' => 'Steves Fish Bar',
                                'phone' => '01256368985',
                                'email' => 'james@mi-order.co.uk',
                                'company' => 'Steves Fish BAr Limited'
                            )
                        )
                    ),
                    'dropoffs' => array(
                        array(
                            'address' => $address, 
                            'comment' => 'number1',
                            'client_reference' =>'test123',
                            'contact' => array(
                                'firstname' => 'Steves Fish Bar',
                                'lastname' => 'Steves Fish Bar',
                                'phone' => '01256368985',
                                'email' => 'james@mi-order.co.uk',
                                'company' => 'Steves Fish Bar Limited'
                            ),
                            'package_type' => 'medium',
                            'package_description' => 'fish and chips',
                            'client_reference' => 'test',
                            'end_customer_time_window_start' => null,
                            'end_customer_time_window_end' => null
                        )
                    )
                )
            );
      
     $url = "https://api.stuart.com/v2/jobs/pricing";
     $client = new Client();
     $response = $client->request('POST', $url, [
    'headers' => [
      'Content-Type' => 'application/json',
      'Authorization' => 'Bearer ' . $token, 
    ],
    'json' => $requestData,
]);


      $Data = $response->getBody()->getContents();

      $decodePrice = json_decode($Data, true);
      return $decodePrice['amount'];
      $costPrice = $decodePrice['amount']  * 0.01;
      $totalPrice = $costPrice + $decodePrice['amount'];
      return $totalPrice;

        }

            

    
      
    public function getPrice(){

      
       if (Location::getSession('searchQuery') !== null)  {
               $token = $this->GetToken();
      //dd($token);
      $address = Location::getSession('searchQuery');
      $pickup = Carbon::now()->addMinutes(25);
            
      $price  = $this->stuartPrice($pickup, $address, $token);
      return $price;
 
        
    }
    }
      
    public function beforeApply()
    {
        // Do not apply condition when orderType is not delivery
        if (Location::orderType() != Locations_model::DELIVERY)
            return FALSE;
        $coveredArea = Location::coveredArea();
        $cartSubtotal = Cart::subtotal();
        $this->deliveryCharge = $coveredArea->deliveryAmount($cartSubtotal);
        $this->minimumOrder = (float)$coveredArea->minimumOrderTotal($cartSubtotal);
      //dd($coveredArea);
    }

    public function getRules()
    {
        return [
            "{$this->deliveryCharge} >= 0",
            "subtotal >= {$this->minimumOrder}",
        ];
    }

    public function getActions()
    {
        return [
            ['value' => "+{$this->getPrice()}"],
        ];
    }

    public function getValue()
    {
        return $this->calculatedValue;
    }

    public function whenInValid()
    {
        if (!Cart::subtotal() OR !$this->minimumOrder)
            return;

        flash()->warning(sprintf(
            lang('igniter.cart::default.alert_min_delivery_order_total'),
            currency_format($this->minimumOrder)
        ))->now();
    }

  
    }



