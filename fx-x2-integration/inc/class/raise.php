<?php

if ( ! defined('ABSPATH') ) exit;

/*
 * Class Raise
 */

class Raise {
    
    /**
     * Prevent Instance of Class
     */

    private function __construct() {}
    
    /* ==============================================================================
        API Configurations
      ==============================================================================  */
      
    /**
     * Determine the API URL based on IS_LIVE flag
     * 
     * @param endpoint - optional extension added to the end of the URL
     * 
     * @return string - the full URL to use
     */
    
    public static function raiseUrl($endpoint = '') {
        $PORTAL_URL = FX_X2_Integration::IS_LIVE ? 'https://api.1031crowdfunding.com/token/' : 'https://api.staging.funds411.com/token/' ;
        $PORTAL_TOKEN = 'REDACTED';
        return $PORTAL_URL . $PORTAL_TOKEN . $endpoint;
    }
    
    /**
     * Set API configuration to send with HTTP request using CF7 form submission 
     * 
     * @param data - required data to post to server (should be register form submission)
     * 
     * @return array - request arguments to be set as second arg in wp_remote_get 
     * 
     * Reference: https://developer.wordpress.org/reference/functions/wp_remote_get/
     */

    public static function raiseOptions($data) {
        return [
            'headers' => [
                'Content-Type: application/json'
            ],
            'body' => $data,
            'sslverify' => FX_X2_Integration::IS_LIVE,
        ];
    }
    
    /**
     * Function used for CF7 filter. Grab submission and test server for maintenance
     * 
     * @param result - instance of WPCF7_Validation
     * @param tag - associative array composed of given form-tag component
     * 
     * @return class instance
     * 
     * Reference: https://contactform7.com/2015/03/28/custom-validation/
     */
    
    public static function raiseValidation($result, $tag) {
        // Validation only for registration form (id = 4)
        if(WPCF7_ContactForm::get_current()->id() == 4){
            $submission = WPCF7_Submission::get_instance();
            $response = wp_remote_post(self::raiseUrl("/register"), self::raiseOptions(''));
            $status = wp_remote_retrieve_response_code($response);
        
            if (is_array($response) && !is_wp_error($response)){
                // 422 = Unprocessable Entity. Test the response to check that it is functional before final submission, so it can be invalidated
                if($status != '422'){ 
                    // Add error messages
                    add_filter('wpcf7_feedback_response', ['FX_X2_Integration', 'maintenanceResponse'], 10, 2); 
                    $result->invalidate($tag, 'An error has occured');
                }
            }
        }
        return $result;
    }
    
    /**
     * Given an array of data, reorganize to the API required structure
     * 
     * @param contact - contact information to be restructured
     * 
     * @return array - the modified array
     */
    
    public static function raiseStructureData($contact) {
        $raiseContact = [
                'first' => $contact['first-name'],
                'last' => $contact['last-name'],
                'email' => $contact['email-address'],
                'phone' => $contact['phone-number'],
                'password' => $contact['password']
            ];
        return $raiseContact;
    }
    
    /* ==============================================================================
        CRUD Functions
        
        @return mixed (Returns the value encoded in json to appropriate PHP type)
        
        Reference: https://www.php.net/manual/en/function.json-decode.php
      ==============================================================================  */
    
    public static function raiseAddContact($data) {
        $response = wp_remote_post(self::raiseUrl("/register"), self::raiseOptions($data));
        return $response;
    }
}
