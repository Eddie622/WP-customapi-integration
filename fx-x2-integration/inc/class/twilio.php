<?php

if ( ! defined('ABSPATH') ) exit;

/*
 * Class Twilio
 */

class Twilio {
    
    /**
     * Prevent Instance of Class
     */
    
    private function __construct() {}
    
    /* ==============================================================================
        API Configurations
      ==============================================================================  */
      
    /**
     * Set API configuration to send with HTTP request
     * 
     * @return array - request arguments to be set as second arg in wp_remote_get 
     * 
     * Reference: https://developer.wordpress.org/reference/functions/wp_remote_get/
     */
	
	public static function twilioOptions()
    {
        $twilioUSER = 'REDACTED';
        $twilioPASS = 'REDACTED';
        
        return [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode( $twilioUSER . ':' . $twilioPASS )
            ],
        ];
    }
    
    /**
     * Determine the API URL
     * 
     * @param endpoint - optinal extension added to the end of the URL
     * 
     * @return string - the full URL to use
     */
    
    public static function twilioUrl($endpoint = '')
    {
        $T_URL = 'https://lookups.twilio.com/v1/PhoneNumbers/';
        return $T_URL . $endpoint;
    }
    
    /* ==============================================================================
        Search & Validations
      ==============================================================================  */
      
    /**
     * Find if the phone exists within associated Twilio account
     * 
     * @param phone - the phone number to be searched for
     * 
     * @return bool
     */
    
    public static function twilioFindPhone($phone) {
        $response =  wp_remote_get(self::twilioUrl("{$phone}?Type=carrier&CountryCode=US"), self::twilioOptions());
        if (is_array($response) && !is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response));
            
            // If the response was not 404 status, the carrier property exists, there are no error codes, and the carrier is not empty. (Segmented for clarity)
            if(wp_remote_retrieve_response_code($response) != '404' && property_exists($body, 'carrier')){
                if(!$body->carrier->error_code > 0 && $body->carrier->name != ''){
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * Function used for CF7 filter. Grab submission, pass the phone number, and invalidate if phone is not found
     * 
     * @param result - instance of WPCF7_Validation
     * @param tag - associative array composed of given form-tag component
     * 
     * @return class instance
     * 
     * Reference: https://contactform7.com/2015/03/28/custom-validation/
     */
    
    public static function twilioPhoneValidation($result, $tag) {
        $submission = WPCF7_Submission::get_instance();
        $phone = $submission->get_posted_data('phone-number');
        
        if($phone && !self::twilioFindPhone($phone)){
            $result->invalidate($tag, 'Please enter a valid phone number');
        }
        return $result;
    }

}