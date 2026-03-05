<?php
	define("CURRENT_TIME", time());

	define('SHIPPING_BILLING_FIELDS', array(
	    'shipping' => array(
	        'shipping_first_name',
	        'shipping_last_name',
	        'shipping_country',
	        'shipping_address_1',
	        'shipping_city',
	        'shipping_state',
	        'shipping_postcode'
	    ),
	    'billing' => array(
	        'billing_first_name',
	        'billing_last_name',
	        'billing_country',
	        'billing_address_1',
	        'billing_city',
	        'billing_state',
	        'billing_postcode',
	        'billing_email'
	    )
	));
?>