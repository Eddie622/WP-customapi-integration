<?php

if ( ! defined('ABSPATH') ) exit;

/*
 * Class X2
 */

class X2 {
    
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
    
    public static function x2Url( $endpoint = '' ) {
        $X2_URL = FX_X2_Integration::IS_LIVE ? 'https://crm.1031crowdfunding.com/index.php/api2' : 'https://1031crowdfunding.x2developer.com/index.php/api2' ;
        return $X2_URL . $endpoint;
    }
    
    /**
     * Set API configuration to send with HTTP request
     * 
     * @param data - optional data to be sent (normally used for POST/UPDATE)
     * 
     * @return array - request arguments to be set as second arg in wp_remote_get 
     * 
     * Reference: https://developer.wordpress.org/reference/functions/wp_remote_get/
     */

    public static function x2Options( $data = null, $method = null ) {
        $X2_USER = 'REDACTED';
        $X2_PASS = 'REDACTED';
        $args = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode( $X2_USER . ':' . $X2_PASS )
            ],
            'body' => ($data) ? json_encode($data) : null,
            'sslverify' => FX_X2_Integration::IS_LIVE,
        ];
        // Only establish method if passed explicitly
        if($method){
           $args['method'] = $method;
        }
        return $args;
    }
    
    /**
     * Given an array of data, reorganize to the API required structure
     * 
     * @param contact - contact information to be restructured
     * 
     * @return array - the modified array
     */
    
    public static function x2StructureData($contact) {
        $x2Contact = ([
            'firstName' => $contact['first-name'],
            'lastName' => $contact['last-name'],
            'email' => $contact['email-address'],
            'phone' => $contact['phone-number'],
            'leadSource' => isset($_COOKIE['leadsource']) ? $_COOKIE['leadsource'] : '',
            'c_searchKeyword' => isset($_COOKIE['leadsource']) ? $_COOKIE['searchKeyword'] : '',
            'c_TrueLead' => isset($_COOKIE['leadsource']) ? $_COOKIE['truelead'] : '',
            'visibility' => 1
        ]);
        return $x2Contact;
    }
    
    /* ==============================================================================
        Search & Validations
      ==============================================================================  */
    
    
    /**
     * Function used for CF7 filter. Grab submission, pass the email, and invalidate if email is found or code fell through all conditionals
     * 
     * @param result - instance of WPCF7_Validation
     * @param tag - associative array composed of given form-tag component
     * 
     * @return class instance
     * 
     * Reference: https://contactform7.com/2015/03/28/custom-validation/
     */
    
    public static function x2EmailValidation($result, $tag) {
        // Validation only for registration form (id = 4)
        if(WPCF7_ContactForm::get_current()->id() == 4){
            $submission = WPCF7_Submission::get_instance();
            $response =  wp_remote_get(self::x2Url("/Contacts/by:email={$submission->get_posted_data('email-address')};visibility=1.json?_useFirst=1"), self::x2Options());
            $status = wp_remote_retrieve_response_code($response);
            
            $isRegistered = json_decode(wp_remote_retrieve_body($response))->c_AccountOpened;
        
            if (is_array($response) && !is_wp_error($response)) {
                // Contact not found or not registered
                if ($status == '404' || ($status == '200' && !$isRegistered)) {
                    return $result;
                }
                // Contact is registered in Raise
                else if($status == '200' && $isRegistered){
                    $result->invalidate($tag, 'This email is already registered');
                    return $result;
                }
            }
            // If neither condition returned the result, something unexpected happened and should alert with an error message
            add_filter('wpcf7_feedback_response', ['FX_X2_Integration', 'maintenanceResponse'], 10, 2); 
            $result->invalidate($tag, 'An error has occured');
        }
        return $result;
    }
    
    /* ==============================================================================
        CRUD Functions
        
        @param data - form submission infromation to pass with HTTP Request
        @param contact - object containing contact information used determine correct URL
        @param tag - to be determined
        
        @return mixed (Returns the value encoded in json to appropriate PHP type)
        
        Reference: https://www.php.net/manual/en/function.json-decode.php
      ==============================================================================  */
    
    /**
     * Add contact to X2
     */
    
    public static function x2AddContact($data){
        $response = wp_remote_post(self::x2Url("/Contacts"), self::x2Options($data));
        return $response;
    }
    
    /**
     * Update Contact in X2
     */
    
    public static function x2UpdateContact($contact, $data){
        $response = wp_remote_request(self::x2Url("/Contacts/{$contact->id}.json"), self::x2Options($data, 'PATCH'));
        return $response;
    }
    
    /**
     * Add tags to existing contact in X2
     */
    
    public static function x2AddContactTag($contact, $tag){
        $response = wp_remote_post(self::x2Url("/Contacts/{$contact->id}/tags"), self::x2Options([$tag]));
        return $response;
    }
}
