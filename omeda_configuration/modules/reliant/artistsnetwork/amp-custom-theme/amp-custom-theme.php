<?php
/*
Plugin Name: AMP Custom Theme
Plugin URI:
Description: Custom AMP Theme.
Version: 1.0
Author:  James
Author URI: http://midtc.com/
License:
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// Define the Folder of the theme.
define('AMPFORWP_CUSTOM_THEME', plugin_dir_path( __FILE__ )); 

// Remove old files
add_action('init','ampforwp_custom_theme_remove_old_files',11);
function ampforwp_custom_theme_remove_old_files(){
    remove_action('pre_amp_render_post','ampforwp_stylesheet_file_insertion', 12 );
	
	if ( is_single() ) {
		remove_filter( 'amp_post_template_file', 'ampforwp_custom_template', 10, 3 );
	}
	add_action('amp_post_template_head', function() {
		remove_action( 'amp_post_template_head', 'amp_post_template_add_fonts');
	}, 9);

	
}

// Register New Files
add_action('init','ampforwp_custom_theme_files_register', 10);
function ampforwp_custom_theme_files_register() {
	add_filter( 'amp_post_template_file', 'ampforwp_designing_custom_template', 10, 3 );
	add_filter( 'amp_post_template_file', 'ampforwp_custom_footer_file', 10, 2 );
}

add_action('wp_head', 'inject_custom_scroll');

function inject_custom_scroll() {
	?>
	<script>
		try {
			document.addEventListener('DOMContentLoaded', function() {
				let getElement = new URL(window.location).searchParams.get("j")
				if(getElement) {
					pushCustomScroll = setInterval(function() {
						if(document.readyState == "complete") {
							setTimeout(function() {
								var element = document.getElementById(getElement);
							    var headerOffset = 325;
							    var elementPosition = element.getBoundingClientRect().top;
							    var offsetPosition = elementPosition + window.pageYOffset - headerOffset;
							  
							    window.scrollTo({
							         top: offsetPosition,
							         behavior: "auto"
							    });
							}, 100);
							clearInterval(pushCustomScroll);
						}
					}, 1000);
				}
			});
		} catch(err) { console.log('[ERROR] Custom scroll error raised.'); }
	</script>
	<?php
}

// Custom Template Files
function ampforwp_designing_custom_template( $file, $type, $post ) { 
	$post_id = get_the_ID();

	if ($post_id !== false) {
	    if ((is_single() || is_page())) {
			if('single' === $type && !('product' === $post->post_type )) {
				$file = AMPFORWP_CUSTOM_THEME . '/template/single.php';
		 	}
		}
	    // Archive
		/*if ( is_archive() ) {
	        if ( 'single' === $type ) {
	            $file = AMPFORWP_CUSTOM_THEME . '/template/archive.php';
	        }
	    }
	    // Homepage
		if ( is_home() ) {
	        if ( 'single' === $type ) {
	            $file = AMPFORWP_CUSTOM_THEME . '/template/index.php';
	        }
	    }*/
	    
	 	return $file;
	 }
}

// Custom Footer
function ampforwp_custom_footer_file($file, $type ){
	if ( 'footer' === $type ) {
		$file = AMPFORWP_CUSTOM_THEME . '/template/footer.php';
	}
	return $file;
}
add_action( 'amp_post_template_head', 'amp_post_template_add_custom_google_font');


// Loading Custom Google Fonts in the theme
function amp_post_template_add_custom_google_font( $amp_template ) {
?>

	<link rel="apple-touch-icon" sizes="180x180" href="/wp-content/themes/buddyboss-theme-child/assets/favicon/apple-touch-icon.png.webp">
	<link rel="icon" type="image/png" sizes="32x32" href="/wp-content/themes/buddyboss-theme-child/assets/favicon/favicon-32x32.png.webp">
	<link rel="icon" type="image/png" sizes="16x16" href="/wp-content/themes/buddyboss-theme-child/assets/favicon/favicon-16x16.png.webp">
	<link rel="mask-icon" href="/wp-content/themes/buddyboss-theme-child/assets/favicon/safari-pinned-tab.svg" color="#f46c63">

	<link rel="icon" type="image/x-icon" href="/wp-content/themes/buddyboss-theme-child/assets/favicon/favicon.ico">
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto+Flex:opsz,wght@8..144,400;8..144,500;8..144,600;8..144,900&family=Roboto+Serif:opsz,wght@8..144,500;8..144,600;8..144,700;8..144,800;8..144,900&display=swap">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
	<amp-analytics type="googleanalytics" config="https://amp.analytics-debugger.com/ga4.json" data-credentials="include">
		<script type="application/json">
		{
			"vars": {
						"GA4_MEASUREMENT_ID": "G-ECDYD42WTJ",
						"GA4_ENDPOINT_HOSTNAME": "www.google-analytics.com",
						"DEFAULT_PAGEVIEW_ENABLED": true,    
						"GOOGLE_CONSENT_ENABLED": false,
						"WEBVITALS_TRACKING": false,
						"PERFORMANCE_TIMING_TRACKING": false,
						"SEND_DOUBLECLICK_BEACON": false
			}
		}
		</script>
	</amp-analytics> 
	<amp-analytics type="chartbeat">
		<script type="application/json">
			{
				"vars": {
					"uid": "66886",
					"domain": "artistsnetwork.com",
					"sections": "<?php if( function_exists( 'webgilde_get_categories') ) { echo implode( ",", webgilde_get_categories() ); }; ?>",
					"authors": "<?php if(is_single()){echo get_the_author_meta( 'display_name' , get_post_field ('post_author', get_the_ID())); } ?>"
				}
			}
		</script>
	</amp-analytics>
<?php }


// Loading Core Styles 
require_once( AMPFORWP_CUSTOM_THEME . '/template/style.php' );

// Add Scripts only when AMP Menu is Enabled
if( has_nav_menu( 'amp-menu' ) ) {
    if ( empty( $data['amp_component_scripts']['amp-accordion'] ) ) {
        $data['amp_component_scripts']['amp-accordion'] = 'https://cdn.ampproject.org/v0/amp-accordion-0.1.js';
    }
}