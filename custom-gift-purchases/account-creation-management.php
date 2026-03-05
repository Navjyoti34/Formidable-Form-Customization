<?php

// This will handle the creation of a holding user that takes over gifted subscriptions until the giftee logs in or is created.
// It also creates users when a giftee doesn't exist.

add_action('woocommerce_order_status_changed', 'checkGifterUser', 10, 0);

function checkGifterUser() {
    if (!get_option('gifter_user_made') || !get_userdata(get_option('gifter_user_made'))) {
        $username = 'the_gifter_' . uniqid();
        $password = wp_generate_password();
        $email = $username . '@goldenpeakmedia.com';

        $id = wp_create_user($username, $password, $email);

        if (is_wp_error($id)) {
            error_log('Failed to create gifter user: ' . $id->get_error_message());
            return;
        }

        update_option('gifter_user_made', $id);
    }
}

function createGiftee($email) {
	if(email_exists($email)) {
		return ['account', null];
	}

	$create_username_from_email = explode('@', $email)[0];
	$try_username = $format_created_username = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $create_username_from_email));
	$check_username_exists = username_exists($format_created_username);

	while($check_username_exists) {
		$try_username = $format_created_username . rand(10, 99);
		$check_username_exists = username_exists($try_username);
	}

	$password = substr(strval(md5(uniqid())), 0, 12);

	$id = wp_create_user($try_username, $password, $email);
	$user = new WP_User($id);

	if(!empty($user->ID)) {
		$user_id = $user->ID;
		$meta_key = 'gift_for_peak_can_change_username';
		$meta_value = 'true';

		update_user_meta($user_id, $meta_key, $meta_value);

		return(['freshly made account using the credentials below and logging into your account', $password, $try_username]);
	}
    
    return(['account', null]);
}