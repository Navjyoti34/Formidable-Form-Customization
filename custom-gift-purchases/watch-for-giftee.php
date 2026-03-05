<?php

// This watches for accounts that may have a gift.

add_action('user_register', 'check_new_account_has_gift');
add_action('wpmu_new_user', 'check_new_account_has_gift', 10, 1);

function check_new_account_has_gift($user_id) {
    global $wpdb;

    $user_email = get_userdata($user_id)->user_email;
    $findGifts = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key LIKE %s AND meta_value LIKE %s",
            '_gft_sd_%',
            '%' . $user_email . '%'
        )
    );

    foreach ($findGifts as $gift) {
        gfp_process_learndash_gifts($gift->post_id, false);
        extends_update_status($gift->post_id, null, null, true);
    }
}