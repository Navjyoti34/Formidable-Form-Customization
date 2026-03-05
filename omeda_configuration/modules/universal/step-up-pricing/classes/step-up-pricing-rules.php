<?php

    class stepUpPricingRules {
        private $table_name;

        public function __construct() {
            global $wpdb;
            $this->table_name = 'midtc_step_up_pricing_rules';

            add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
            add_action('admin_menu', array($this, 'add_admin_menu'));
        }

        // Enqueue necessary scripts and styles
        public function enqueue_scripts() {
            if ( isset( $_GET['page'] ) && $_GET['page'] === 'step_up_pricing_rules' ) {
                wp_enqueue_style('step-up-pricing-admin-style', plugin_dir_url(__FILE__) . '../assets/css/step-up-pricing-admin.css?' . time());
                wp_enqueue_script('bootstrap', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js', array('popper'), false, true);
                wp_enqueue_script('popper', 'https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js', array('jquery'), false, true);
            }
        }

        // Create the admin page to manage rules
        public function step_up_pricing_rules_admin_page() {
            global $wpdb;

            // Process form submissions and database actions
            if (isset($_POST['submit_rule'])) {
                // Validate and sanitize form inputs
                $rule_title = sanitize_text_field($_POST['rule_title']);
                $rule = json_encode(str_replace(array("\r", "\n", "  ", "\t"), '', ($_POST['rule'])));
                $rule = ((($rule)));
                $products = sanitize_text_field($_POST['products']);
                $date = current_time('mysql');

                // Insert or update the rule in the database
                $existing_rule_id = isset($_POST['existing_rule']) ? absint($_POST['existing_rule']) : 0;

                if ($existing_rule_id > 0) {
                    // Update the existing rule
                    /*$wpdb->update(
                        $this->table_name,
                        array(
                            'rule_title' => $rule_title,
                            'rule' => $rule,
                            'products' => $products,
                            'date_updated' => $date
                        ),
                        array('id' => $existing_rule_id),
                        array('%s', '%s', '%s', '%s'),
                        array('%d')
                    );*/

                    echo '<div class="alert alert-success">Rule updated successfully!</div>';
                } else {
                    // Insert a new rule
                    /*
                    $wpdb->insert(
                        $this->table_name,
                        array(
                            'rule_title' => $rule_title,
                            'rule' => $rule,
                            'products' => $products,
                            'date_created' => $date,
                            'date_updated' => $date
                        ),
                        array('%s', '%s', '%s', '%s', '%s')
                    );
                    */

                    echo '<div class="alert alert-success">Rule added successfully!</div>';
                }
            }

            // Delete a rule
            if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['rule_id'])) {
                $rule_id = absint($_GET['rule_id']);
                $wpdb->delete($this->table_name, array('id' => $rule_id), array('%d'));
                echo '<div class="alert alert-success">Rule deleted successfully!</div>';
            }

            // Fetch existing rules
            $rules = $wpdb->get_results("SELECT * FROM $this->table_name");
            ?>
            <div class="wrap">
            <div class="row mb-3">
                <div class="col-md-11">
                    <h1>Step Up Pricing Rules</h1>
                </div>
                <div class="col-md-1 text-right">
                    <button type="button" id="addNewRuleBtn" class="btn btn-primary" data-toggle="collapse" data-target="#addRuleForm" aria-expanded="false">
                        Add New Rule
                    </button>
                </div>
            </div>

                <form id="addRuleForm" class="collapse mt-3" action="" method="post" style="display: none;">
                    <fieldset class="custom-fieldset">
                        <legend>Add New Rule</legend>
                        <div class="form-group">
                            <label for="rule_title">Rule Title:</label>
                        </div>
                        <div class="form-group">
                            <input type="text" class="form-control" name="rule_title" id="rule_title" required>
                        </div>
                        <div class="form-group">
                            <label for="rule">Rule (JSON format):</label>
                        </div>
                        <div class="form-group">
                            <textarea class="form-control" name="rule" id="rule" rows="10" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="products">Product IDs (comma-separated):</label>
                        </div>
                        <div class="form-group">
                            <input type="text" class="form-control" name="products" id="products" required>
                        </div>
                        <button type="submit" name="submit_rule" class="btn btn-success">Add</button>
                        <button type="button" class="btn btn-secondary" id="cancelAddBtn">Cancel</button>
                    </fieldset>
                </form>

                <?php if (count($rules) === 0) : // Show a message if no rules exist ?>
                    <div class="alert alert-warning mt-3">No rules found.</div>
                <?php else : ?>
                    <!-- Existing Rules Table -->
                    <fieldset class="custom-fieldset">
                    <legend>Existing Rules</legend>
                    <table class="table table-striped mt-3">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Rule Title</th>
                                <th>Rule</th>
                                <th>Product IDs</th>
                                <th>Date Created</th>
                                <th>Date Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rules as $rule) : ?>
                                <tr>
                                    <td><?php echo $rule->id; ?></td>
                                    <td><?php echo $rule->rule_title; ?></td>
                                    <td><?php echo stripslashes((($rule->rule))); ?></td>
                                    <td><?php echo $rule->products; ?></td>
                                    <td><?php echo $rule->date_created; ?></td>
                                    <td><?php echo $rule->date_updated; ?></td>
                                    <td class="col-md-2">
                                        <div class="col-md-6">
                                            <!-- Edit Button -->
                                            <button type="button" onclick="showEditForm(<?php echo $rule->id; ?>, '<?php echo esc_js($rule->rule_title); ?>', '<?php echo esc_js(stripslashes((($rule->rule)))); ?>', '<?php echo esc_js($rule->products); ?>')" class="btn btn-primary">
                                                <span class="dashicons dashicons-edit"></span> Edit
                                            </button>
                                        </div>
                                        <div class="col-md-6">
                                            <!-- Delete Button -->
                                            <form method="get" action="" onsubmit="return confirm('Are you sure you want to delete this rule?')" style="display: inline;">
                                                <input type="hidden" name="rule_id" value="<?php echo $rule->id; ?>">
                                                <input type="hidden" name="page" value="step_up_pricing_rules">
                                                <button type="submit" name="action" value="delete" class="btn btn-danger">
                                                    <span class="dashicons dashicons-trash"></span> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Edit Form Row (Initially Hidden) -->
                                <tr id="editFormRow<?php echo $rule->id; ?>" style="display: none;">
                                    <td colspan="7">
                                        <form action="" method="post">
                                            <fieldset class="custom-fieldset" style="background: #fbfbfb;">
                                                <legend>Edit Rule</legend>
                                                <div class="form-group">
                                                    <label for="edit_rule_title<?php echo $rule->id; ?>">Rule Title:</label>
                                                </div>
                                                <div class="form-group">
                                                    <input type="text" class="form-control" name="rule_title" id="edit_rule_title<?php echo $rule->id; ?>" value="<?php echo esc_attr($rule->rule_title); ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <label for="edit_rule<?php echo $rule->id; ?>">Rule (JSON format):</label>
                                                </div>
                                                <div class="form-group">
                                                    <textarea class="form-control" name="rule" id="edit_rule<?php echo $rule->id; ?>" rows="3" required><?php echo esc_textarea($rule->rule); ?></textarea>
                                                </div>
                                                <div class="form-group">
                                                    <label for="edit_products<?php echo $rule->id; ?>">Product IDs (comma-separated):</label>
                                                </div>
                                                <div class="form-group">
                                                    <input type="text" class="form-control" name="products" id="edit_products<?php echo $rule->id; ?>" value="<?php echo esc_attr($rule->products); ?>" required>
                                                </div>
                                                <input type="hidden" name="existing_rule" value="<?php echo $rule->id; ?>">
                                                <button type="submit" name="submit_rule" class="btn btn-success">Update</button>
                                            </fieldset>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </fieldset>
                <?php endif; ?>
            </div>
            <script>
                // JavaScript to show/hide the "Add New Rule" and "Edit" forms
                document.addEventListener('DOMContentLoaded', function () {
                    const addRuleForm = document.getElementById('addRuleForm');
                    const editButtons = document.querySelectorAll('.edit-rule-btn');
                    let isAddFormShown = false;

                    // Show/hide the "Add New Rule" form on button click
                    document.getElementById('addNewRuleBtn').addEventListener('click', function () {
                        if (isAddFormShown) {
                            addRuleForm.style.display = 'none';
                        } else {
                            addRuleForm.style.display = 'block';
                        }
                        isAddFormShown = !isAddFormShown;
                    });

                    // Show/hide the "Edit" form on button click
                    editButtons.forEach(function (button) {
                        button.addEventListener('click', function () {
                            const editForm = this.parentElement.nextElementSibling;
                            if (editForm.style.display === 'none') {
                                editForm.style.display = 'table-row';
                            } else {
                                editForm.style.display = 'none';
                            }
                        });
                    });

                    function removeButtonFocus() {
                        const buttons = document.querySelectorAll('button');
                        buttons.forEach((button) => {
                            button.blur();
                        });
                    }

                    document.getElementById('cancelAddBtn').addEventListener('click', function () {
                        addRuleForm.style.display = 'none';
                        addRuleForm.classList.remove('show');
                        isAddFormShown = false;
                    });
                });

                function showEditForm(id, title, rule, products) {
                    const editFormRow = document.getElementById("editFormRow" + id);
                    if (editFormRow.style.display === "none") {
                        // Show the edit form row
                        editFormRow.style.display = "table-row";
                        // Set the values of the edit form inputs
                        document.getElementById("edit_rule_title" + id).value = title;
                        document.getElementById("edit_rule" + id).value = rule;
                        document.getElementById("edit_products" + id).value = products;
                    } else {
                        // Hide the edit form row
                        editFormRow.style.display = "none";
                    }
                }

            </script>
            <?php
        }

        public function add_admin_menu() {
            add_submenu_page(
                'step_up_pricing_overview',
                'Rules',
                'Rules',
                'manage_options',
                'step_up_pricing_rules',
                array($this, 'step_up_pricing_rules_admin_page') // Callback to the plugin's admin page function
            );
        }
    }

    new stepUpPricingRules();
