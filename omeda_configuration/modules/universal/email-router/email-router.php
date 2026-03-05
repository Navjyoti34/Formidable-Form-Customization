<?php

	if (!defined('ABSPATH')) {
	    exit('Direct access not allowed.');
	}

	require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

	function email_endpoint_logger($atts, $handler_response = ['handler' => 'Unknown', 'response' => 'Unknown', 'json' => ''], $uid) {
	    global $wpdb;

	    $table_name = $wpdb->prefix . 'midtc_email_endpoint_logger';

	    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
		   	if (!function_exists('dbDelta')) {
		        error_log('[EMAIL HANDLER] WordPress database functions not available.');
		        return;
		    }

			$sql = "CREATE TABLE $table_name (
				id INT AUTO_INCREMENT PRIMARY KEY,
				`to` VARCHAR(255) NOT NULL,
				`subject` VARCHAR(255) NOT NULL,
				`response` VARCHAR(255) NOT NULL,
				`endpoint` VARCHAR(255) NOT NULL,
				`json` TEXT DEFAULT NULL,
				`date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				`uid` VARCHAR(255) NOT NULL
			) ENGINE=InnoDB";

	        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	        dbDelta($sql);

	        if (!empty($wpdb->last_error)) {
	            error_log('[EMAIL HANDLER] Error creating table: ' . $wpdb->last_error);
	            return;
	        }
	    }

		$data = array(
		    'to' => sanitize_email($atts['to']),
		    'subject' => sanitize_text_field($atts['subject']),
		    'response' => sanitize_text_field($handler_response['response']),
		    'endpoint' => sanitize_text_field($handler_response['handler']),
		    'json' => isset($handler_response['json']) ? wp_json_encode($handler_response['json']) : '',
		    'date' => current_time('mysql', 1),
		    'uid' => $uid
		);

		$sql = "INSERT INTO {$table_name} 
		        (`to`, `subject`, `response`, `endpoint`, `json`, `date`, `uid`) 
		        VALUES 
		        ('" . esc_sql($data['to']) . "', 
		         '" . esc_sql($data['subject']) . "', 
		         '" . esc_sql($data['response']) . "', 
		         '" . esc_sql($data['endpoint']) . "', 
		         '" . esc_sql(stripslashes($data['json'])) . "', 
		         '" . esc_sql($data['date']) . "', 
		         '" . esc_sql($data['uid']) . "')";

		$result = $wpdb->query($sql);

	    if ($result === false) {
	        error_log('[EMAIL HANDLER] Error inserting data: ' . $wpdb->last_error);
	    }
	}

	function email_endpoint_send_out_email($email, $omeda_track_id, $email_subject, $message, $uid) {
		global $c;

		echo INCLUDE_OMEDA();

	   	if (!function_exists('INCLUDE_OMEDA')) {
	        email_endpoint_logger(['to' => $email, 'subject' => $email_subject], ['handler' => 'Omeda', 'response' => 'INCLUDE_OMEDA function not found. Defaulting to wp_mail.'], $uid);
	        return false;
	    }

		$pattern = '#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#';

		preg_match_all($pattern, $message, $matches);

		$linkCount = 0;

		foreach ($matches[0] as $url) {
			$linkCount++;

		    $parsedUrl = parse_url($url);

		    $scheme = $parsedUrl['scheme'] ?? null;
		    $host = $parsedUrl['host'] ?? null;
		    $path = $parsedUrl['path'] ?? null;
		    $query = $parsedUrl['query'] ?? null;
		    $altered_query = '';

		    if (!is_null($query)) {
		        $altered_query = '@{router_link_' . $linkCount . '}@';
		        $mergedVariables[0]["router_link_$linkCount"] = html_entity_decode($query);
		        $altered_query = '?' . $altered_query;
		    }

		    $message = str_replace($url, ("{$scheme}://{$host}{$path}{$altered_query}"), $message);
		}

	    $fields = [
	        'TrackId' => $omeda_track_id,
	        'EmailAddress' => $email,
	        'Subject' => $email_subject,
	        'HtmlContent' => $message,
	        'Preference' => 'HTML',
	        'MergeVariables' => $mergedVariables
	    ];

	    $send_payload_to_omeda = omedaCurl("{$c('ON_DEMAND_ENDPOINT')}{$c('OMEDA_DIRECTORY')['send_email']}", json_encode($fields));

	    $send_payload_to_omeda = array(
	    	'status' => $send_payload_to_omeda[1],
	    	'response' => $send_payload_to_omeda[0]
	    );

	    $response = $send_payload_to_omeda['response'] ?? 'Unknown error';

	    if ($send_payload_to_omeda['status'] !== 200) {
	        email_endpoint_logger(['to' => $email, 'subject' => $email_subject], ['handler' => 'Omeda', 'response' => "Defaulting to wp_mail. Error sending email. Status: {$send_payload_to_omeda['status']}", 'json' => $response], $uid);
	        error_log("[EMAIL HANDLER] Defaulting to wp_mail. Error sending email to OMEDA. Status: {$send_payload_to_omeda['status']}");

	        return false;
	    }

	    email_endpoint_logger(['to' => $email, 'subject' => $email_subject], ['handler' => 'Omeda', 'response' => "Success sending email. Status: {$send_payload_to_omeda['status']}", 'json' => $response], $uid);

	    return true;
	}

	function email_endpoint_send_email_through_wp_mail($to, $subject, $message, $headers, $uid) {
	    remove_filter('pre_wp_mail', 'email_endpoint_wp_mail_filter', 10, 1);
	   	
	    $emailSent = wp_mail($to, $subject, $message, $headers);

    	$responseMessage = $emailSent ? "Success sending email." : "Error sending email.";
		email_endpoint_logger(['to' => $to, 'subject' => $subject], ['handler' => 'WP', 'response' => $responseMessage], $uid);
	    
	    return $emailSent;
	}

	function email_endpoint_wp_mail_filter($null, $atts) {
		$uid = uniqid();

		$message = $atts['message'];
	    $to = $atts['to'];
	    $subject = $atts['subject'];
	    $headers = $atts['headers'];

	    $emailSent = false;

	    $sites_omeda_id = [
		    'artistsnetwork.com' => array(
		    	'Password Reset' => 'GPM240208025',
				'toward your next purchase' => 'GPM240319015',
				'Confirm Your ' . esc_html(get_bloginfo('name')) . ' Account!' => 'GPM240510031'
		    ),
		    'interweave.com' => array(
		    	'Password Reset' => 'GPM240208023',
		    	'Account Confirmation' => 'GPM240208021',
				'toward your next purchase' => 'GPM240319018',
				'Confirm Your ' . esc_html(get_bloginfo('name')) . ' Account!' => 'GPM240510030'
		    ),
		    'quiltingdaily.com' => array(
		    	'Password Reset' => 'GPM240208024',
				'toward your next purchase' => 'GPM240319016',
				'Confirm Your ' . esc_html(get_bloginfo('name')) . ' Account!' => 'GPM240510032'
		    ),
		    'sewdaily.com' => array(
		    	'Password Reset' => 'GPM240208022',
		    	'Account Confirmation' => 'GPM240208017',
				'toward your next purchase' => 'GPM240319017',
				'Confirm Your ' . esc_html(get_bloginfo('name')) . ' Account!' => 'GPM240510029'
		    )
		];

	    $currentHost = preg_replace('/^www\./', '', implode('.', array_slice(explode('.', $_SERVER['HTTP_HOST']), -2)));
	    $omedaIdForHost = $sites_omeda_id[$currentHost] ?? false;

		switch (true) {
	        case ($omedaIdForHost && stripos($subject, 'Password Reset') !== false):
	            $emailSent = email_endpoint_send_out_email($to, $omedaIdForHost['Password Reset'], $subject, $message, $uid);
	            break;
	        case ($omedaIdForHost && stripos($subject, 'Account Confirmation') !== false):
	        	$emailSent = email_endpoint_send_out_email($to, $omedaIdForHost['Account Confirmation'], $subject, $message, $uid);
	            break;
			case ($omedaIdForHost && stripos($subject, 'toward your next purchase') !== false):
	        	$emailSent = email_endpoint_send_out_email($to, $omedaIdForHost['toward your next purchase'], $subject, $message, $uid);
	            break;
	        case ($omedaIdForHost && strpos($subject, 'Confirm Your ' . esc_html(get_bloginfo('name')) . ' Account!') !== false):
	        	$emailSent = email_endpoint_send_out_email($to, $omedaIdForHost['Confirm Your ' . esc_html(get_bloginfo('name')) . ' Account!'], $subject, $message, $uid);
	            break;
	        default:
	            $emailSent = email_endpoint_send_email_through_wp_mail($to, $subject, $message, $headers, $uid);
	    }

	    if(!$emailSent) {
	    	email_endpoint_send_email_through_wp_mail($to, $subject, $message, $headers, $uid);
	    }

	    return true;
	}

	add_filter('pre_wp_mail', 'email_endpoint_wp_mail_filter', 10, 2);