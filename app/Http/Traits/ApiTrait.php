<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Validator;

trait ApiTrait
{
     protected $validationError = null;
     protected $timezone = 'Africa/Lagos';

     public function apiResonse(array $data = null, string $message, bool $status)
     {
          return $this->makeResponse($message, $status, $data);
     }

     public function validateApiData(array $data, array $rules, array $customMessage = null)
     {
          $validator = null;
          $validator = ($customMessage != null) ? Validator::make($data, $rules, $customMessage) : Validator::make($data, $rules);
          if($validator->fails()) {
               $this->validationError = $validator->errors();
               return false;
          } else {
               return true;
          }

     }

     public function getValidationErrors()
     {
          return $this->validationError;
     }
     
     public function validationErrorMessage()
     {    
          $status = false; $message = 'Something is not right';
          $errors = $this->getValidationErrors();
          $this->makeResponse($message, $status, $errors);
     } 

     protected function makeResponse(string $message, bool $status, $data)
     {
          $response = array(
               'success' => $status,
               'message' => $message,
               'data' => $data
          );
          return response()->json($response)->withHeaders([
               'Cache-Control' => 'nocache, no-store, max-age=0, must-revalidate',
               'Content-Type' => 'text/json; charset=UTF-8'
          ]);
     }

}
