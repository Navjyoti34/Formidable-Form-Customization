<?php

require_once(plugin_dir_path( __FILE__ ) . 'functions.php');


global $c; 
// set variable for define to use in-line
$c = 'constant';

$development_environment = strpos(get_site_url(), 'dev.') !== false;

// define our API KEY
define("API_KEY","BF3B6674-C62C-4451-B27D-BC820F0CF5D3");
// define brand abbreviation
define("BRAND_ABBREV","GPM");
// define client abbreviation
define("CLIENT_ABBREV","client_gpm");
// define encryption identification
define("INPUT_ID","3126C4678801A4C");
// is our environment in staging (dev) or not (production)?
define("STAGING", $development_environment);
// is our environment in staging (dev) or not (production)?
define("EMAIL_STAGING", $development_environment);
// is on-demand emailing in staging? This will need to stay false to actually send emails.
define("ON_DEMAND_EMAIL_STAGING", false);
// defining the staging url using the brand abbreviation
define("STAGING_URL", "https://ows.omedastaging.com/webservices/rest/brand/{$c('BRAND_ABBREV')}");
// defining the production url using the brand abbreviation
define("PRODUCTION_URL", "https://ows.omeda.com/webservices/rest/brand/{$c('BRAND_ABBREV')}");

// defining the staging url using the client abbreviation
define("EMAIL_STAGING_URL", "https://ows.omedastaging.com/webservices/rest/client/{$c('CLIENT_ABBREV')}");
// defining the production url using the client abbreviation
define("EMAIL_PRODUCTION_URL", "https://ows.omeda.com/webservices/rest/client/{$c('CLIENT_ABBREV')}");

// define directory end points of omeda
define("OMEDA_DIRECTORY", array(
    'brand_comprehensive_lookupservice' => '/comp/*',
    'save_customer_and_order_paid' => '/storecustomerandorder/*',
    'run_processor' => '/runprocessor/*',
    'send_email' => '/omail/deployemails/*',
    'create_deployment' => '/omail/deployment/*',
    'schedule_deployment' => '/omail/deployment/schedule/*',
    'email_optin_queue' => '/optinfilterqueue/*',
    'email_optin_optout_lookup' => '/filter/email/{email_address}/*',
    'customer_lookup_by_email' => '/customer/email/{email_address}/*'
));

// For Testing: https://ows.omedastaging.com/webservices/rest/brand/{brandAbbreviation}/omail/deployemails/*

// are we in staging? let's define the endpoint the staging url - if not switch to production
(STAGING ? define("ENDPOINT", STAGING_URL): define("ENDPOINT", PRODUCTION_URL));
// are we in staging? let's define the endpoint the staging url - if not switch to production
(EMAIL_STAGING ? define("EMAIL_ENDPOINT", EMAIL_STAGING_URL): define("EMAIL_ENDPOINT", EMAIL_PRODUCTION_URL));
// are we in staging? let's define the endpoint the staging url - if not switch to production
(ON_DEMAND_EMAIL_STAGING ? define("ON_DEMAND_ENDPOINT", STAGING_URL): define("ON_DEMAND_ENDPOINT", PRODUCTION_URL));