<?php
class stepUpPricingEmail {
	return;

    public function __construct() {
    	add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    public function add_admin_menu() {
        add_submenu_page(
            'step_up_pricing_overview',        // Parent slug
            'Email Settings',                  // Page title
            'Email Settings',                  // Menu title
            'manage_options',                  // Capability required
            'step_up_pricing_email_settings',  // Menu slug
            array($this, 'step_up_pricing_email_settings') // Callback function
        );
    }

    public function step_up_pricing_email_settings() {
        ?>
        <div class="wrap">
            <div class="row mb-3">
                <div class="col-md-11">
                    <h1>Step Up Pricing Email Settings</h1>
                </div>
            </div>
            <fieldset class="custom-fieldset">
            	<legend>Settings</legend>
            	
            </fieldset>
        </div>
        <?php
    }
}