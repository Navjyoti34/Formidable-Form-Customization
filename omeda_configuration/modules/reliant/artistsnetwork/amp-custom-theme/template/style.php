<?php 

add_action('amp_post_template_css', 'ampforwp_custom_style', 11);

function ampforwp_custom_style($post_id) {
    global $redux_builder_amp;

    $get_customizer = new AMP_Post_Template($post_id);

    $css_files = array('bootstrap.min.css', 'qd-owl.css', 'qd-amp.css', 'custom.css');
    $custom_style = '';

    foreach ($css_files as $style) {
        $file_path = AMPFORWP_CUSTOM_THEME . '/template/css/' . $style;

        if (file_exists($file_path)) {
            $custom_style .= file_get_contents($file_path);
        }
    }

    echo $custom_style;
    if (isset($redux_builder_amp['css_editor'])) {
        echo $redux_builder_amp['css_editor'];
    }
}
