<?php

use Molongui\Authorship\Common\Modules\Settings;
use Molongui\Authorship\Common\Utils\Helpers;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

?>

<div id="molongui-options">

    <?php do_action( 'authorship/options/before_masthead' ); ?>

    <?php include MOLONGUI_AUTHORSHIP_DIR . 'common/modules/settings/views/partials/html-part-header.php'; ?>

    <?php do_action( 'authorship/options/after_masthead' ); ?>

    <!-- Page Content -->
    <div class="m-page-content">

        <!-- Nav -->
        <div id="m-navigation" class="m-navigation">
            <div class="m-section-nav <?php echo ( empty( $tabs ) ? 'is-empty' : 'has-pinned-items' ); ?>">

                <div class="m-section-nav__mobile-header" role="button" tabindex="0">
                    <?php echo esc_html( $tabs[$current_tab]['name'] ); ?>
                </div>

                <div class="m-section-nav__panel">
                    <div class="m-section-nav-group">
                        <div class="m-section-nav-tabs">
                            <ul class="m-section-nav-tabs__list" role="menu">
                                <?php echo $nav_items; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </ul>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Tabs -->
        <?php echo $div_contents; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

        <!-- Other stuff -->
        <?php wp_nonce_field( 'mfw_save_options_nonce', 'mfw_save_options_nonce' ); ?>

    </div><!-- !m-page-content -->

    <?php do_action( 'authorship/options/before_footer' ); ?>

    <?php include MOLONGUI_AUTHORSHIP_DIR . 'common/modules/settings/views/partials/html-part-footer.php'; ?>

    <?php do_action( 'authorship/options/after_footer' ); ?>

    <?php Settings::enqueue_scripts(); ?>
    <?php Settings::enqueue_styles();  ?>
    <?php Helpers::load_tidio(); ?>

</div> <!-- #molongui-options -->

<div id="m-options-saving"><div class="m-loader"><div></div><div></div><div></div><div></div></div></div>
<div id="m-options-saved"><span class="dashicons dashicons-yes"></span><strong><?php esc_html_e( 'Saved', 'molongui-authorship' ); ?></strong></div>
<div id="m-options-error"><span class="dashicons dashicons-no"></span><strong><?php esc_html_e( 'Error', 'molongui-authorship' ); ?></strong></div>