<?php
/**
 * Plugin Name: Formidable Order Mapping
 * Description: Map Formidable fields to order fields with WP-style list UI.
 * Version: 1.3
 */

if (!defined('ABSPATH')) exit;

define('FWC_DB_VERSION', '1.3');
register_activation_hook( __FILE__, 'fwc_activate_plugin' );
function fwc_activate_plugin( $network_wide ) {
    if ( is_multisite() && $network_wide ) {
        $sites = get_sites( [ 'fields' => 'ids' ] );
        foreach ( $sites as $blog_id ) {
            switch_to_blog( $blog_id );
            fwc_run_migrations( $blog_id );
            update_option( 'fwc_db_version', FWC_DB_VERSION ); // per-site
            restore_current_blog();
        }
    } else {
        fwc_run_migrations( get_current_blog_id() );
        update_option( 'fwc_db_version', FWC_DB_VERSION ); // per-site
    }
}

    /**
     * -------------------------------------------------------
     * AUTO UPGRADE DB WHEN VERSION CHANGES
     * -------------------------------------------------------
     */
    add_action('plugins_loaded', function () {
        $installed = get_option('fwc_db_version');
        if ( $installed !== FWC_DB_VERSION ) {
            fwc_run_migrations( get_current_blog_id() );
            update_option('fwc_db_version', FWC_DB_VERSION);
        }
    });
    /**
     * -------------------------------------------------------
     * MAIN DB MIGRATION FUNCTION
     * -------------------------------------------------------
     */




function fwc_run_migrations() {
        global $wpdb;

        $blog_id = get_current_blog_id();
        $table   = $wpdb->base_prefix . $blog_id . '_fwc_post_order_mapping';
        $charset = $wpdb->get_charset_collate();

        // Check if table already exists
        if ( $wpdb->get_var( $wpdb->prepare(
            "SHOW TABLES LIKE %s", $table
        ) ) === $table ) {
            return; // Table already exists, no need to create
        }

        $sql = "CREATE TABLE {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                form_id BIGINT UNSIGNED NOT NULL,
                order_id VARCHAR(100),
                order_total VARCHAR(100),
                payment_method VARCHAR(100),
                payment_status VARCHAR(100),
                cart_status VARCHAR(100),
                category VARCHAR(100),
                surcharge VARCHAR(100),
                first_name VARCHAR(100),
                last_name VARCHAR(100),
                email VARCHAR(100),
                phone VARCHAR(100),
                PRIMARY KEY (id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    function fwc_alter_table() {
        global $wpdb;
        $blog_id = get_current_blog_id();
        $table   = $wpdb->base_prefix . $blog_id . '_fwc_post_order_mapping';
        $exists = $wpdb->get_var(
            "SHOW COLUMNS FROM {$table} LIKE %s 'payment_status'"
        );
        if ( ! $exists ) {
            $wpdb->query(
                "ALTER TABLE {$table}
                ADD COLUMN payment_status VARCHAR(100) NULL AFTER payment_type"
            );
        }
        $exists_cart = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$table} LIKE %s",
                'cart_status'
            )
        );
        if ( ! $exists_cart ) {
            $wpdb->query(
                "ALTER TABLE `{$table}` 
                ADD COLUMN `cart_status` VARCHAR(100) NULL AFTER `payment_status`"
            );
        }
    }
    add_action('admin_init', 'fwc_alter_table');
    /* =========================================================
    WP LIST TABLE CLASS
    ========================================================= */

    if (!class_exists('FWC_Table')) {

        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

        class FWC_Table extends WP_List_Table {

            private $table;

            function __construct($table) {
                parent::__construct([
                    'singular' => 'mapping',
                    'plural'   => 'mappings',
                    'ajax'     => false
                ]);

                $this->table = $table;
            }

            function get_columns() {
                return [
                    'cb'             => '<input type="checkbox" />',
                    'id'             => 'ID',
                    'form_id'        => 'Form ID',
                    'order_id'       => 'Order ID',
                    'order_total'    => 'Order Total',
                    'payment_method' => 'Payment Method',
                    'payment_status' => 'Payment Status',
                    'cart_status'    => 'Cart Status',
                    'category'       => 'Category',
                    'first_name'     => 'First Name',
                    'email'          => 'Email'
                ];
            }

            function get_sortable_columns() {
                return [
                    'id'         => ['id', true],
                    'email'      => ['email', false],
                    'first_name' => ['first_name', false],
                ];
            }

            function column_cb($item) {
                return '<input type="checkbox" name="id[]" value="'.$item->id.'"/>';
            }

            function column_id($item) {
                $edit = admin_url('admin.php?page=fwc-mapping-edit&id=' . $item->id);

                $actions = [
                    'edit' => '<a href="'.$edit.'">Edit</a>',
                ];

                return sprintf(
                    '<strong><a href="%s">%s</a></strong> %s',
                    esc_url($edit),
                    esc_html($item->id),
                    $this->row_actions($actions)
                );
            }

            function column_default($item, $column_name) {
                return isset($item->$column_name) ? esc_html($item->$column_name) : '';
            }

            function no_items() {
                _e('No mappings found.');
            }

            function prepare_items() {
                global $wpdb;

                $per_page = $this->get_items_per_page('fwc_per_page', 20);
                $search   = isset($_GET['s']) ? trim($_GET['s']) : '';

                $sql = "FROM {$this->table} WHERE 1=1";

                if ($search) {
                    $sql .= $wpdb->prepare(
                        " AND (email LIKE %s OR first_name LIKE %s)",
                        "%$search%", "%$search%"
                    );
                }

                $total_items = (int) $wpdb->get_var("SELECT COUNT(*) $sql");

                $paged  = max(1, (int) ($_GET['paged'] ?? 1));
                $offset = ($paged - 1) * $per_page;

                $order   = (!empty($_GET['order']) && $_GET['order'] === 'asc') ? 'ASC' : 'DESC';
                $orderby = (!empty($_GET['orderby'])) ? esc_sql($_GET['orderby']) : 'id';

                $this->items = $wpdb->get_results("
                    SELECT * 
                    $sql
                    ORDER BY $orderby $order
                    LIMIT $offset, $per_page
                ");

                $columns  = $this->get_columns();
                $hidden   = [];
                $sortable = $this->get_sortable_columns();

                $this->_column_headers = [$columns, $hidden, $sortable];

                $this->set_pagination_args([
                    'total_items' => $total_items,
                    'per_page'    => $per_page
                ]);
            }
        }
    }


    /* =========================================================
    MAIN PLUGIN CLASS
    ========================================================= */

    class FWC_Order_Mapping {

        private $table;

        public function __construct() {
            global $wpdb;
            $this->table = $wpdb->get_blog_prefix( get_current_blog_id() ) . 'fwc_post_order_mapping';
            add_action('admin_menu', [$this, 'menu']);
            add_filter('set-screen-option', [$this,'screen'], 10, 3);
            add_action('admin_enqueue_scripts', [$this, 'scripts']);
            add_action('wp_ajax_fwc_get_fields', [$this, 'ajax_get_fields']);

            if (is_multisite()) {
                add_action('wpmu_new_blog', [$this, 'create_table_for_blog'], 10, 1);
            }
        }

        public function screen($status, $option, $value) {
            return ($option === 'fwc_per_page') ? (int) $value : $status;
        }

        public function create_table_for_blog($blog_id) {
            switch_to_blog($blog_id);
            fwc_run_migrations();
            restore_current_blog();
        }

        public function menu() {
            $hook = add_submenu_page(
                'formidable',
                'Order Mapping',
                'Order Mapping',
                'manage_options',
                'fwc-mapping',
                [$this,'listing']
            );

            add_action("load-$hook", [$this,'screen_options']);

            add_submenu_page(
                null,
                'Edit Mapping',
                'Edit Mapping',
                'manage_options',
                'fwc-mapping-edit',
                [$this,'edit']
            );
        }

        public function screen_options() {
            add_screen_option('per_page', [
                'label'   => 'Items per page',
                'default' => 20,
                'option'  => 'fwc_per_page'
            ]);
        }

        public function scripts($hook) {
            if (strpos($hook, 'fwc-mapping') === false) return;

            $js_file = plugin_dir_path( dirname( __FILE__ ) ) . 'js/fwc.js';
            $js_url  = plugin_dir_url( dirname( __FILE__ ) ) . 'js/fwc.js';

            wp_enqueue_script(
                'fwc-admin',
                $js_url,
                array( 'jquery' ),
                file_exists( $js_file ) ? filemtime( $js_file ) : '1.3',
                true
            );

            wp_localize_script('fwc-admin', 'FWC', [
                'ajax'  => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('fwc')
            ]);
        }

        public function ajax_get_fields() {
            check_ajax_referer('fwc');

            if (!class_exists('FrmField'))
                wp_send_json_error('Formidable not active');

            $form_id = absint($_POST['form_id']);
            $fields  = FrmField::get_all_for_form($form_id);

            $out = [];
            foreach ($fields as $f) {
                $out[] = [
                    'key'   => $f->field_key,
                    'label' => $f->name,
                    'id'    => $f->id,
                ];
            }

            wp_send_json_success($out);
        }

        public function listing() {
            if (!class_exists('WP_List_Table')) {
                require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
            }

            $table = new FWC_Table($this->table);
            $table->prepare_items();

            echo '<div class="wrap"><h1 class="wp-heading-inline">Order Mapping</h1>
            <a href="admin.php?page=fwc-mapping-edit" class="page-title-action">Add New</a>
            <hr class="wp-header-end">';

            echo '<form method="get">
                <input type="hidden" name="page" value="fwc-mapping">';
                $table->search_box('Search','fwc');
            echo '</form>';

            echo '<form method="post">';
                $table->display();
            echo '</form></div>';
        }

        public function edit() {
            global $wpdb;

            $id = absint($_GET['id'] ?? 0);

            $item = [
                'form_id'        => '',
                'order_id'       => '',
                'order_total'    => '',
                'payment_method' => '',
                'payment_status' => '',
                'cart_status'    => '',
                'category'       => '',
                'surcharge'      => '',
                'first_name'     => '',
                'last_name'      => '',
                'email'          => '',
                'phone'          => ''
            ];

            if ($id) {
                $row = $wpdb->get_row(
                    $wpdb->prepare("SELECT * FROM {$this->table} WHERE id=%d", $id),
                    ARRAY_A
                );
                if ($row) {
                    $item = array_merge($item, $row);
                }
            }

            if ($_POST) {
                check_admin_referer('fwc-save');

                $data = [
                    'form_id'        => absint($_POST['form_id']),
                    'order_id'       => sanitize_text_field($_POST['order_id']),
                    'order_total'    => sanitize_text_field($_POST['order_total']),
                    'payment_method' => sanitize_text_field($_POST['payment_method']),
                    'payment_status' => sanitize_text_field($_POST['payment_status']),
                    'cart_status'   => sanitize_text_field($_POST['cart_status']),
                    'category'       => sanitize_text_field($_POST['category']),
                    'surcharge'      => sanitize_text_field($_POST['surcharge']),
                    'first_name'     => sanitize_text_field($_POST['first_name']),
                    'last_name'      => sanitize_text_field($_POST['last_name']),
                    'email'          => sanitize_text_field($_POST['email']),
                    'phone'          => sanitize_text_field($_POST['phone']),
                ];

                if ($id) {
                    $wpdb->update($this->table, $data, ['id' => $id]);
                } else {
                    $result = $wpdb->insert($this->table, $data);
                    if ($result === false) {
                        wp_die('DB Insert Failed: ' . esc_html($wpdb->last_error));
                    }
                }

                wp_redirect('admin.php?page=fwc-mapping');
                exit;
            }

            $forms = class_exists('FrmForm') ? FrmForm::getAll() : [];

            ?>
            <div class="wrap">
            <h1><?php echo $id ? 'Edit' : 'Add'; ?> Mapping</h1>

            <form method="post">
            <?php wp_nonce_field('fwc-save'); ?>

            <table class="form-table">
                <tr>
                    <th><label for="fwc_form_id">Formidable Form</label></th>
                    <td>
                        <select name="form_id" id="fwc_form_id">
                            <option value="">— Select Form —</option>
                            <?php foreach ($forms as $f): ?>
                                <option value="<?php echo esc_attr($f->id); ?>"
                                    <?php selected($item['form_id'], $f->id); ?>>
                                    <?php echo esc_html($f->name . ' — (' . $f->id . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <hr>
            <h2>Post Purchase Fields Mapping</h2>

            <table class="form-table">
            <?php
            $post_purchase_fields = [
                'order_id'       => 'Order ID Field',
                'payment_method' => 'Payment Method Field',
                'payment_status' => 'Payment Status Field',
                'cart_status'    => 'Cart Status Field',
                'category'       => 'Category Field',
                'order_total'    => 'Order Total Field',
                'surcharge'      => 'Surcharge Field',
            ];

            foreach ($post_purchase_fields as $k => $label): ?>
                <tr>
                    <th><?php echo esc_html($label); ?></th>
                    <td>
                        <select name="<?php echo esc_attr($k); ?>"
                                class="fwc-field"
                                data-selected="<?php echo esc_attr($item[$k]); ?>">
                            <option value="">— Select Field —</option>
                        </select>
                    </td>
                </tr>
            <?php endforeach; ?>
            </table>

            <hr>
            <h2>Checkout Billing Fields Mapping</h2>

            <table class="form-table">
            <?php
            $checkout_fields = [
                'first_name' => 'First Name Field',
                'last_name'  => 'Last Name Field',
                'email'      => 'Email Field',
                'phone'      => 'Phone Field',
            ];

            foreach ($checkout_fields as $k => $label): ?>
                <tr>
                    <th><?php echo esc_html($label); ?></th>
                    <td>
                        <select name="<?php echo esc_attr($k); ?>"
                                class="fwc-field"
                                data-selected="<?php echo esc_attr($item[$k]); ?>">
                            <option value="">— Select Field —</option>
                        </select>
                    </td>
                </tr>
            <?php endforeach; ?>
            </table>

            <?php submit_button(); ?>
            </form>
            </div>
            <?php
        }
    }

    new FWC_Order_Mapping();
