<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
function authorship_is_edit_mode()
{
    return apply_filters( 'authorship/is_edit_mode', authorship_is_block_editor() or authorship_is_elementor_editor() );
}
function authorship_is_block_editor()
{
    $edit_mode = false;
    if ( function_exists( 'is_gutenberg_page' ) and is_gutenberg_page() )
    {
        $edit_mode = true;
    }
    if ( function_exists( 'get_current_screen' ) )
    {
        $current_screen = get_current_screen();
        if ( !is_null( $current_screen ) and method_exists( $current_screen, 'is_block_editor' ) and $current_screen->is_block_editor() )
        {
            $edit_mode = true;
        }
    }

    return apply_filters( 'authorship/is_block_editor', $edit_mode );
}
function authorship_is_elementor_editor()
{
    $edit_mode = false;

    if ( did_action( 'elementor/loaded' ) )
    {
        $edit_mode = \Elementor\Plugin::$instance->editor->is_edit_mode();
    }

    return apply_filters( 'authorship/is_elementor_editor', $edit_mode );
}