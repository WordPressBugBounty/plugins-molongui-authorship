<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
$author_box_display  = get_post_meta( $post->ID, '_molongui_author_box_display', true );
$author_box_position = get_post_meta( $post->ID, '_molongui_author_box_position', true );
if ( empty( $author_box_display ) )
{
    $author_box_display = 'default';
}
if ( empty( $author_box_position ) )
{
    $author_box_position = 'default';
}

?>

<div class="molongui-metabox">

    <!-- Author box display -->
    <div class="m-title"><?php esc_html_e( "Display", 'molongui-authorship' ); ?></div>
    <p class="m-description"><?php esc_html_e( "Select whether to show the author box on this post.", 'molongui-authorship' ); ?></p>
    <div class="m-field">
        <select name="_molongui_author_box_display">
            <option value="default" <?php selected( $author_box_display, 'default' ); ?>><?php esc_html_e( "Default", 'molongui-authorship' ); ?></option>
            <option value="show"    <?php selected( $author_box_display, 'show' );    ?>><?php esc_html_e( "Show", 'molongui-authorship' ); ?></option>
            <option value="hide"    <?php selected( $author_box_display, 'hide' );    ?>><?php esc_html_e( "Hide", 'molongui-authorship' ); ?></option>
        </select>
        <?php wp_nonce_field( "molongui_author_box_display", 'molongui_author_box_display_nonce' ); ?>
    </div>

    <!-- Author box position -->
    <div class="m-title <?php echo ( $author_box_display == 'hide' ? 'm-title-disabled' : '' ); ?>"><?php esc_html_e( "Position", 'molongui-authorship' ); ?></div>
    <p class="m-description <?php echo ( $author_box_display == 'hide' ? 'm-description-disabled' : '' ); ?>"><?php esc_html_e( "Select where to display the author box in the post.", 'molongui-authorship' ); ?></p>
    <div class="m-field">
        <select name="_molongui_author_box_position" <?php echo ( $author_box_display == 'hide' ? 'disabled' : '' ); ?>>
            <option value="default" <?php selected( $author_box_position, 'default' ); ?>><?php esc_html_e( "Default", 'molongui-authorship' ); ?></option>
            <option value="above"   <?php selected( $author_box_position, 'above' );   ?>><?php esc_html_e( "Above", 'molongui-authorship' ); ?></option>
            <option value="below"   <?php selected( $author_box_position, 'below' );   ?>><?php esc_html_e( "Below", 'molongui-authorship' ); ?></option>
            <option value="both"    <?php selected( $author_box_position, 'both'  );   ?>><?php esc_html_e( "Both", 'molongui-authorship' );  ?></option>
        </select>
        <?php wp_nonce_field( 'molongui_author_box_position', 'molongui_author_box_position_nonce' ); ?>
    </div>

    <!-- Author Box Position Styling -->
    <script type="text/javascript">
        jQuery(function($)
        {
            $('select[name="_molongui_author_box_display"]').on('change', function()
            {
                const author_box_position_select = $('select[name="_molongui_author_box_position"]');

                if ( $(this).val() === 'hide' )
                {
                    author_box_position_select.prop('disabled', 'disabled');
                    author_box_position_select.parent().prev().addClass('m-description-disabled');
                    author_box_position_select.parent().prev().prev().addClass('m-title-disabled');
                }
                else
                {
                    author_box_position_select.prop('disabled', false);
                    author_box_position_select.parent().prev().removeClass('m-description-disabled');
                    author_box_position_select.parent().prev().prev().removeClass('m-title-disabled');
                }
            });
        });
    </script>

</div>