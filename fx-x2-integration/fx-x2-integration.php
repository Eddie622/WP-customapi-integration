<?php

/**
 * 	Plugin Name:	FX X2 Integration
 * 	Plugin URI: 	https://heribertotorres.com
 * 	Description:	Integrates specific CF7 forms with Raise API, Twilio, & X2 CRM
 * 	Version: 		1.0.0
 * 	Author: 		Heriberto (Eddie) Torres
 * 	Author URI: 	https://heribertotorres.com
 */
 
 if ( ! defined('ABSPATH') ) exit;

final class FX_X2_Integration
{
    /**
     * @var FX_X2_Integration
     */
    protected static $instance = null;
    
    // TODO: UPDATE THIS FLAG TO true WHEN SITE GOES LIVE
    public const IS_LIVE = false;
    
    public $plugin_path;
    public $plugin_url;

    /**
     * Singleton instance
     *
     * @return  self
     */
	public static function instance() {
		if( null === self::$instance ) {
			self::$instance = new self();
        }
        return self::$instance;
	}

    /**
     * Constructor
     * 
     * @return  void
     */
    public function __construct() {
        $this->define_paths();
        spl_autoload_register( [$this, 'autoload'] );
        
        // Load Scripts
        add_action( 'wp_enqueue_scripts', ['FX_X2_Integration', 'load_plugin_scripts'] );
        
        // CF7 filters to perform custom validations & submit if no errors occur
        add_filter('wpcf7_validate_email*', ['X2', 'x2EmailValidation'], 20, 2 );
        add_filter('wpcf7_validate_text*', ['Raise', 'raiseValidation'], 20, 2 );
        add_filter('wpcf7_validate_tel*', ['Twilio', 'twilioPhoneValidation'], 20, 2 );
        add_action("wpcf7_submit", ['FX_X2_Integration', 'sendContact'], 10, 2); 
    }
    
    /**
     * Set required files
     * 
     * @return  void
     */
    
    private function define_paths() {
        $this->plugin_path = trailingslashit( plugin_dir_path( __FILE__ ) );
        $this->plugin_url  = trailingslashit( plugins_url( '/', __FILE__ ) );
        define( 'FX_X2_INTEGRATION_DIR', $this->plugin_path );
        define( 'FX_X2_INTEGRATION_URL', $this->plugin_url );
    }
    
    /**
     * Given the class, find the files based on type (Can be updated as needed - add folder under inc directory and add folder name to $types array)
     * 
     * @return  void
     */
    
    private static function autoload($class_name) {
        $base_path  = FX_X2_INTEGRATION_DIR . 'inc/';
        $class_name = str_replace( 'AS', 'As', $class_name );
        $class_name = strtolower( preg_replace( '/(?<=\\w)(?=[A-Z])/', '-$1', $class_name ) );
        $types      = ['class'];
        foreach ( $types as $type ) {
            $path_name = $base_path . $type . '/' . $class_name . '.php';
            if ( file_exists( $path_name ) ) {
                include $path_name;
                return;
            }
        }
    }
    
    /**
     * Enqueue plugin script
     * 
     * @return void
     */
     
    public static function load_plugin_scripts() {
        $version = filemtime(FX_X2_INTEGRATION_DIR . '/assets/js/app.js');
        wp_enqueue_script( 'app', FX_X2_INTEGRATION_URL . '/assets/js/app.js', array( 'jquery' ), $version, true );
    }
    
    /**
     * On form submit, checks that form is valid and if so, sends appropriate data as needed.
     * 
     * @return  void
     */
    
    public static function sendContact($form, $result){
        $formID = WPCF7_ContactForm::get_current()->id();
        
        // If there are no invalid fields, all APIs must have returned expected results (Only check Registrations & Newsletter/Ebook form)
        if(!array_key_exists('invalid_fields', $result) && $formID == 4 || $formID == 3261){
            
            // Grab form submission to build contact
            $submission = WPCF7_Submission::get_instance();
            $contact = $submission->get_posted_data();
            
            $response =  wp_remote_get(X2::x2Url("/Contacts/by:email={$submission->get_posted_data('email-address')};visibility=1.json?_useFirst=1"), x2::x2Options());
            $status = wp_remote_retrieve_response_code($response);
            
            // Registration form
            if($formID == 4){
                // Send structured data to raise
                $raiseResponse = Raise::raiseAddContact(Raise::raiseStructureData($contact));
                
                /* 
                 * Create/Update x2 based on whether or not they were added prior from newsletter/ebook form 
                 * i.e. their email is within x2 by submitting newsletter/ebook form, but they were not registered in Raise since the registration form was not submitted.
                 */
                 
                // Create New Contact (since no contact was found)
                if($status == '404'){
                    // Send structured data to x2, store response, and then transfer to PHP object
                    $x2Response = X2::x2AddContact(X2::x2StructureData($contact));
                    $newRegister = json_decode(wp_remote_retrieve_body($x2Response));
                }
                // Update Contact (since contact was found, but not yet registered)
                else if($status == '200'){
                    // Get the contact found
                    $foundContact = json_decode(wp_remote_retrieve_body($response));
                    
                    // Update the contact and save response (only phone should be updated, so it will be passed & formatted by itself)
                    $x2Response = X2::x2UpdateContact($foundContact, ['phone' => $contact['phone-number']]);
                    $newRegister = json_decode(wp_remote_retrieve_body($x2Response));
                }
                // Set tag to registered using contact info
                X2::x2AddContactTag($newRegister, '#registered');
            }
            // Newsletter/Ebook form
            else if ($formID == 3261){
                // If email is not already in x2, a new lead should be created. Otherwise, no event should occur.
                if($status == 404){
                    // Send structured data to x2
                    X2::x2AddContact(X2::x2StructureData($contact));
                }
            }
        }
        return $result;
    }
    
    /**
     * Sets an error message. Used when an unexpected response is collected from one of the APIs
     * 
     * @return 
     */
    
    public static function maintenanceResponse ($response, $result) {
        $response['message'] = 'An error has occured. Please try again at a later time.';
        return $response;
    }
}


/**
 * Returns main instance of FX_X2_Integration
 * 
 * @return  FX_X2_Integration
 */
function FX_X2_Integration() {
	return FX_X2_Integration::instance();
}


FX_X2_Integration();
