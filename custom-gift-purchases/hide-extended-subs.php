<?php

//Add settings to General Settings page
add_action( 'admin_init', function() {

    // Backend setting
    register_setting( 'general', 'hide_gift_expired_subscriptions_admin', [
        'type'              => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default'           => false,
    ] );

    add_settings_field(
        'hide_gift_expired_subscriptions_admin',
        __( 'Hide Gift-Expired Subscriptions (Admin)', 'your-textdomain' ),
        function() {
            $value = get_option( 'hide_gift_expired_subscriptions_admin', false );
            ?>
            <label>
                <input type="checkbox" name="hide_gift_expired_subscriptions_admin" value="1" <?php checked( $value, true ); ?>>
                <?php esc_html_e( 'Hide subscriptions expired due to gifting from admin listing', 'your-textdomain' ); ?>
            </label>
            <?php
        },
        'general'
    );

    // Frontend setting
    register_setting( 'general', 'hide_gift_expired_subscriptions_frontend', [
        'type'              => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default'           => false,
    ] );

    add_settings_field(
        'hide_gift_expired_subscriptions_frontend',
        __( 'Hide Gift-Expired Subscriptions (Frontend)', 'your-textdomain' ),
        function() {
            $value = get_option( 'hide_gift_expired_subscriptions_frontend', false );
            ?>
            <label>
                <input type="checkbox" name="hide_gift_expired_subscriptions_frontend" value="1" <?php checked( $value, true ); ?>>
                <?php esc_html_e( 'Hide subscriptions expired due to gifting from customer account page', 'your-textdomain' ); ?>
            </label>
            <?php
        },
        'general'
    );
} );


//Hide in Admin list if setting is ON
add_action( 'pre_get_posts', function( $query ) {
    if ( is_admin()
        && $query->is_main_query()
        && isset( $_GET['post_type'] )
        && 'shop_subscription' === $_GET['post_type']
        && get_option( 'hide_gift_expired_subscriptions_admin', false )
    ) {
        $meta_query = (array) $query->get( 'meta_query' );
        $meta_query[] = [
            'key'     => 'expired_subscription_due_to_gifting',
            'compare' => 'NOT EXISTS',
        ];
        $query->set( 'meta_query', $meta_query );
    }
} );


//Hide in Frontend My Account if setting is ON
add_filter( 'wcs_get_users_subscriptions', function( $subscriptions, $user_id ) {
    if ( get_option( 'hide_gift_expired_subscriptions_frontend', false ) ) {
        foreach ( $subscriptions as $subscription_id => $subscription ) {
            if ( get_post_meta( $subscription_id, 'expired_subscription_due_to_gifting', true ) !== '' ) {
                unset( $subscriptions[ $subscription_id ] );
            }
        }
    }
    return $subscriptions;
}, 10, 2 );


//Color highlight in Admin when not hidden
add_filter( 'post_class', function( $classes, $class, $post_id ) {
    if ( is_admin()
        && get_post_type( $post_id ) === 'shop_subscription'
        && ! get_option( 'hide_gift_expired_subscriptions_admin', false )
    ) {
        if ( get_post_meta( $post_id, 'expired_subscription_due_to_gifting', true ) !== '' ) {
            $classes[] = 'gift-expired-subscription';
        }
    }
    return $classes;
}, 10, 3 );

add_action( 'admin_head', function() {
    ?>
    <style>
    .gift-expired-subscription {
        background-color: #fff4e5 !important; /* Light orange highlight */
    }
    </style>
    <?php
});


//Color highlight in Frontend when not hidden
add_action( 'wp_footer', function() {
    if ( is_account_page()
        && ! get_option( 'hide_gift_expired_subscriptions_frontend', false )
    ) {
        global $wpdb;
        $ids = $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'expired_subscription_due_to_gifting'" );
        if ( empty( $ids ) ) {
            return;
        }
        ?>
        <script>
        jQuery(function($) {
            var giftedExpired = <?php echo wp_json_encode( array_map( 'intval', $ids ) ); ?>;
            $('.woocommerce-orders-table__row').each(function() {
                var $row = $(this);
                var subscriptionId = $row.find('a[href*="view-subscription"]').attr('href')?.match(/\/(\d+)\/?$/);
                if (subscriptionId && subscriptionId[1] && giftedExpired.includes(parseInt(subscriptionId[1]))) {
                    $row.css('background-color', '#ffe6e6'); // light red/pink
                }
            });
        });
        </script>
        <?php
    }
});
