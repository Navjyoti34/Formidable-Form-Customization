<?php
class FormBuilder {
    private $form_id;
    private $fields;
    
    public function __construct($form_id, $fields) {
        $this->form_id = $form_id;
        $this->fields = $fields;
    }
    
    public function build_form() {
        echo '<div id="' . $this->form_id . '" style="display:none;">';
        foreach ($this->fields as $field) {
            $field['id'] = uniqid();
            woocommerce_form_field($field['name'], $field);
        }
        echo '</div>';
    }
}
?>