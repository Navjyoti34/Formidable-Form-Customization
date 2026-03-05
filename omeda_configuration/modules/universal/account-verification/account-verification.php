<?php
    
    defined('ABSPATH') ?: exit;

    add_action('wp_logout', 'clear_user_session_data_on_logout');

    function clear_user_session_data_on_logout() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['user_data'])) {
            unset($_SESSION['user_data']);
        }
    }

    add_action('user_register', 'email_verification_user_registration_action', 0, 1);
    add_action('template_redirect', 'email_verification_redirect_function');
    add_action('email_verification_reminder_event', 'email_verification_template', 10, 2);
    add_action('init', 'email_verification_reminder_check_scheduling_event');
    add_action('email_verification_reminder_check_hook', 'email_verification_reminder_check');

    add_action('personal_options', 'add_email_verification_field');

    add_action('personal_options_update', 'save_email_verification_field');
    add_action('edit_user_profile_update', 'save_email_verification_field');

    function add_email_verification_field($user) {
        if (!metadata_exists('user', $user->ID, 'midtc_email_verification')) {
            return;
        }

        $email_verified = get_user_meta($user->ID, 'midtc_email_verification', true);
        $is_verified = isset($email_verified['verified']) ? $email_verified['verified'] : 0;
        ?>
        <table class="form-table">
            <tr>
                <th><label for="email_verified">Email Verified</label></th>
                <td>
                    <input type="checkbox" name="email_verified" id="email_verified" value="1" <?php checked(1, $is_verified); ?> />
                    <span class="description">Check this box if the user's email is verified.</span>
                </td>
            </tr>
        </table>
        <?php
    }

    function save_email_verification_field($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        $email_verified = isset($_POST['email_verified']) ? true : false;
        $midtc_email_verification = get_user_meta($user_id, 'midtc_email_verification', true);

        if (!is_array($midtc_email_verification)) {
            $midtc_email_verification = array();
        }

        $midtc_email_verification['verified'] = $email_verified;
        $midtc_email_verification['reminder'] = false;

        update_user_meta($user_id, 'midtc_email_verification', $midtc_email_verification);
        update_user_meta($user_id, 'midtc_clear_email_verification_session_data', true);
    }

    function email_verification_generate_UUID() {
        return strtoupper(str_replace('-', '', sprintf(
            '%04x%04x%04x%04x%04x%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            
            mt_rand(0, 0xffff),
            
            mt_rand(0, 0x0fff) | 0x4000,
            
            mt_rand(0, 0x3fff) | 0x8000,
            
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        )));
    }

    function email_verification_reminder_check_scheduling_event() {
        if(!wp_next_scheduled('email_verification_reminder_check_hook')) {
            wp_schedule_event(time(), '15min', 'email_verification_reminder_check_hook');
        }
    }

    function email_verification_reminder_check($meta_key = 'midtc_email_verification_reminder') {
        global $wpdb;

        $query = "
            SELECT user_id, meta_value 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = '{$meta_key}'
            LIMIT 5
        ";

        $results = $wpdb->get_results($query);

        if (!empty($results)) {
            foreach ($results as $result) {
                $user_id = $result->user_id;
                $time_sent = $result->meta_value;
                $time_left = time() - $time_sent;

                if(($time_left) > 3600) {
                    $user_verification_data = get_user_meta($user_id, 'midtc_email_verification', true);

                    isset($user_verification_data['verified']) ? ($user_verification_data['verified'] !== true ? email_verification_user_registration_action($user_id, 'reminder_email', false) : delete_user_meta($user_id, $meta_key)) : delete_user_meta($user_id, $meta_key);
                }
            }
        }
    }

    function schedule_email_verification_events($user_id) {
        wp_schedule_single_event(time() + 3600, 'email_verification_reminder_event', array($user_id, 'verification'));

        $next_event_timestamp = wp_next_scheduled('email_verification_reminder_event');

        if ($next_event_timestamp) {
            wp_unschedule_event($next_event_timestamp, 'email_verification_reminder_event');
        }

        wp_schedule_single_event(time() + 3600, 'email_verification_reminder_event', array($user_id, 'reminder_email'));
    }

    function email_verification_template($uuid, $template_selection = 'verification', $user_id) {
        $user = get_userdata($user_id);
        $greeting = "Hi " . (($user ? $user->get('first_name') : '') ?: ($user ? $user->display_name : '') ?: "User");
        $escaped_site_name = esc_html(get_bloginfo('name'));
        $escaped_site_url = esc_html(home_url());

        $default_template  = "
            <p>%{greeting}%,</p>
            <p>Thank you for registering for an account with <strong>%{site_name}%</strong> to gain access to our articles, products, and events. There's one more step – verify your email.</p>
            <p>Please <a href='%{site_url}%?verification=true&uuid=%{uuid}%'>click here</a> to verify your email address.</p>
            <p>Thanks,</p>
            <p>The %{site_name}% Team</p>";

        $reminder_template = "
            <p>%{greeting}%,</p>
            <p>Your email address has not been verified, please <a href='%{site_url}%?verification=true&uuid=%{uuid}%'>click here</a> to begin the verification.</p>
            <p>Thanks,</p>
            <p>The %{site_name}% Team</p>";

        $account_page_url = wc_get_account_endpoint_url('');

        $template = ($template_selection == 'reminder_email') ? $reminder_template : $default_template;

        $template = str_replace(
            ['%{greeting}%', '%{site_name}%', '%{site_url}%', '%{uuid}%'],
            [$greeting, $escaped_site_name, trailingslashit($account_page_url), $uuid],
            $template
        );

        return $template;
    }

    function email_verification_user_registration_action($current_user_id = null, $template_selection = 'verification', $reminder = true) {
        $current_user_id = is_null($current_user_id) ? get_current_user_id() : $current_user_id;
        if (apply_filters('pre_user_email_verification_skip', false)) {
            return;
        }
        $time = time();

        $midtc_email_verification = array(
            'verified' => false,
            'uuid' => email_verification_generate_UUID(),
            'time' => $time,
            'reminder' => $reminder
        );

        update_user_meta($current_user_id, 'midtc_email_verification', $midtc_email_verification);

        (!$reminder) ? update_user_meta($current_user_id, 'midtc_email_verification_reminder', $time) : delete_user_meta($current_user_id, 'midtc_email_verification_reminder');

        wp_mail(get_userdata($current_user_id)->user_email, 'Confirm Your ' . esc_html(get_bloginfo('name')) . ' Account!', email_verification_template($midtc_email_verification['uuid'], $template_selection, $current_user_id), array('Content-Type: text/html; charset=UTF-8'));

        return is_null($current_user_id) ? null : $midtc_email_verification;
    }

    function email_verification_redirect_function() {
        if(!is_user_logged_in()) {
            return;
        }

        if (!session_id()) {
            session_start();
        }

        $user_id = get_current_user_id();
        $clear_user_session_data = get_user_meta($user_id, 'midtc_clear_email_verification_session_data', true);

        if (!isset($_SESSION['user_data']) || $clear_user_session_data) {
            $email_verification = get_user_meta($user_id, 'midtc_email_verification', true);

            if (is_array($email_verification)) {
                $is_verified = isset($email_verification) ? $email_verification : null;
            } else {
                $is_verified = null;
            }

            $current_user = wp_get_current_user();
            $_SESSION['user_data'] = array(
                'user_id' => $user_id,
                'username' => $current_user->user_login,
                'email' => $current_user->user_email,
                'verification' => $is_verified
            );

            if ($clear_user_session_data) {
                update_user_meta($user_id, 'midtc_clear_email_verification_session_data', false);
            }
        }

        if(!is_null($_SESSION['user_data']['verification']['verified']) && $_SESSION['user_data']['verification']['verified'] != true) {
            $account_page_url = wc_get_account_endpoint_url('');
            $verify_page_url = trailingslashit($account_page_url);

            $current_post_type = get_post_type();

            if (!is_account_page() && !($current_post_type === 'post')) {
                wp_redirect($verify_page_url);
                exit;
            } else {
                $email_verification_data = array(
                    'msg' => 'A confirmation email has been dispatched to authenticate your account. Require another one? <a href="#" data-id="resend-verification">Click here</a> to have it resent!',
                    'type' => 'info',
                    'email' => $_SESSION['email']
                );

                if(is_account_page() && $_GET['verification'] === 'true' && $_GET['request'] == 'resend') {
                    $_SESSION['user_data']['verification'] = get_user_meta($_SESSION['user_data']['user_id'], 'midtc_email_verification', true);

                    $output = array('error' => false, 'msg' => 'An unforeseen error has occurred. Kindly reach out to our support team for assistance.');

                    header('Content-Type: application/json');

                    if(isset($_SESSION['user_data']['verification']['verified']) && !is_null($_SESSION['user_data']['verification']['verified'])) {
                        $time_sent = $_SESSION['user_data']['verification']['time'];
                        $time_left = time() - $time_sent;

                        if(($time_left) < 60) {
                            $output['error'] = !$output['error'];
                            $output['msg'] = 'Kindly wait for an additional <span data-id="time-left" style="font-weight:800;">' . 60 - $time_left . ' seconds</span> before requesting another verification email.';

                            die(json_encode($output));
                        }
                    }

                    $resend_verification_email = email_verification_user_registration_action($_SESSION['user_data']['user_id']);

                    if(isset($resend_verification_email) && !empty($resend_verification_email)) {
                       $_SESSION['user_data']['verification'] = $resend_verification_email;
                    }

                    $output['msg'] = 'Your verification email has been dispatched to ' . $_SESSION['user_data']['email'] . '!';

                    die(json_encode($output));
                }

                if(is_account_page() && $_GET['verification'] === 'true' && isset($_GET['uuid'])) {
                    $current_user_id = $_SESSION['user_data']['user_id'];
                    $midtc_email_verification = get_user_meta($current_user_id, 'midtc_email_verification', true);

                    if(isset($midtc_email_verification['verified']) && (is_null($midtc_email_verification['verified']) || !$midtc_email_verification['verified']) && isset($midtc_email_verification['uuid']) && $_GET['uuid'] === $midtc_email_verification['uuid']) {
                        $email_verification_data = array(
                            'msg' => 'Your account verification was successful!',
                            'type' => 'success'
                        );

                        $midtc_email_verification['verified'] = true;
                        $midtc_email_verification['reminder'] = false;
                        $midtc_email_verification['uuid'] = email_verification_generate_UUID();

                        update_user_meta($current_user_id, 'midtc_email_verification', $midtc_email_verification);

                        $_SESSION['user_data']['verification'] = $midtc_email_verification;
                    }
                }

                if(is_account_page()) {
                    if (!wp_script_is('sweetalert2', 'enqueued')) {
                        wp_enqueue_script( 'sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), '11.0.19', true );
                    }

                    wp_enqueue_script('email-verification-script', plugins_url('assets/js/script.js?cache=' . time(), __FILE__), array(), '1.0', true);
                    wp_localize_script('email-verification-script', 'emailVerification', $email_verification_data);
                    wp_enqueue_style('email-verification-style', plugins_url('assets/css/style.css?cache=' . time(), __FILE__), array(), '1.0');
                }
            }
        }
    }
?>