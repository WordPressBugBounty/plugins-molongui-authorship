<?php

use Molongui\Authorship\Admin\Author_Box_Editor;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

Author_Box_Editor::render_heading( __( "Custom CSS", 'molongui-authorship' ) );
Author_Box_Editor::render_textarea( 'author_box_custom_css', __( "Custom CSS", 'molongui-authorship' ), array( 'placeholder' => __( "Add your own custom CSS here...", 'molongui-authorship' ), 'default' => '', 'rows' => 20 ) );
Author_Box_Editor::render_separator();
Author_Box_Editor::render_input( 'author_box_custom_css_class', __( "CSS classes", 'molongui-authorship' ), array
    (
        'placeholder' => __( "my-class-a my-class-b", 'molongui-authorship' ),
        'info-title'  => __( "Custom CSS class for the author box.", 'molongui-authorship' ),
        'info-desc'   => __( "You can provide a CSS class name (including the dot) you want to be added to the author box. This is useful if you need to add some custom styling to the author box or overwrite a default value. Remember that CSS selectors are generally case-insensitive.", 'molongui-authorship' ),
        'info-tip'    => '',
        'info-more'   => '',
    )
);
