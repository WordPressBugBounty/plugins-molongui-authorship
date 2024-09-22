<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

?>

<div id="molongui-options">

    <?php do_action( 'authorship/options/before_masthead' ); ?>

    <?php
        $args = array
        (
            'logo'   => MOLONGUI_AUTHORSHIP_URL . 'assets/img/plugin_logo.png',
            'link'   => MOLONGUI_AUTHORSHIP_WEB,
            'button' => array
            (
                'id'    => 'm-button-save',
                'class' => 'm-button-save',
                'label' => __( "Save Settings", 'molongui-authorship' ),
            ),
        );
        include 'parts/html-part-masthead.php';

    ?>

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
                                <?php echo $nav_items; ?>
                            </ul>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Tabs -->
        <?php echo $div_contents; ?>

        <!-- Other stuff -->
        <?php echo wp_nonce_field( 'mfw_save_options_nonce', 'mfw_save_options_nonce', true, false ); ?>

    </div><!-- !m-page-content -->

    <?php do_action( 'authorship/options/before_footer' ); ?>

    <?php
        $plugin_url    = MOLONGUI_AUTHORSHIP_WEB;
        $help_url      = 'https://www.molongui.com/help/';
        $support_url   = $help_url . 'support/';
        $docs_url      = $help_url . 'docs/';
        $changelog_url = $help_url . MOLONGUI_AUTHORSHIP_NAME . ( did_action( 'authorship_pro/loaded' ) ? '-pro' : '' ) . '-changelog/';
        $demo_url      = MOLONGUI_AUTHORSHIP_DEMO;

        $args = array
        (
            'links' => array
            (
                array
                (
                    'label'   => __( "Pro", 'molongui-authorship' ) . " " . ( defined( 'MOLONGUI_AUTHORSHIP_PRO_VERSION' ) ? MOLONGUI_AUTHORSHIP_PRO_VERSION : '0.0.0' ),
                    'prefix'  => '<span class="m-page-footer__version">',
                    'suffix'  => '</span>',
                    'href'    => $plugin_url,
                    'display' => did_action( 'authorship_pro/loaded' ),
                ),
                array
                (
                    'label'   => __( "Free", 'molongui-authorship' ) . " " . MOLONGUI_AUTHORSHIP_VERSION,
                    'prefix'  => '<span class="m-page-footer__version">',
                    'suffix'  => '</span>',
                    'href'    => $plugin_url,
                    'display' => true,
                ),
                array
                (
                    'label'   => __( "Changelog", 'molongui-authorship' ),
                    'prefix'  => '',
                    'suffix'  => '',
                    'href'    => $changelog_url,
                    'display' => true,
                ),
                array
                (
                    'label'   => __( "Docs", 'molongui-authorship' ),
                    'prefix'  => '',
                    'suffix'  => '',
                    'href'    => $help_url . MOLONGUI_AUTHORSHIP_ID,
                    'display' => true,
                ),
                array
                (
                    'label'   => __( "Support", 'molongui-authorship' ),
                    'prefix'  => '',
                    'suffix'  => '',
                    'href'    => $support_url,
                    'display' => true,
                ),
                array
                (
                    'label'   => __( "Try Pro", 'molongui-authorship' ),
                    'prefix'  => '',
                    'suffix'  => '',
                    'href'    => $demo_url,
                    'display' => !did_action( 'authorship_pro/loaded' ),
                ),
                array
                (
                    'label'   => __( "Upgrade", 'molongui-authorship' ),
                    'prefix'  => '',
                    'suffix'  => '',
                    'href'    => $plugin_url.'pricing/',
                    'display' => !did_action( 'authorship_pro/loaded' ),
                ),
            ),
        );
        include 'parts/html-part-footer.php';

    ?>

    <?php authorship_enqueue_options_scripts(); ?>
    <?php authorship_enqueue_common_options_styles();  ?>
    <?php do_action( 'authorship/options/after_footer' ); ?>

</div> <!-- #molongui-options -->

<div id="m-options-saving"><div class="m-loader"><div></div><div></div><div></div><div></div></div></div>
<div id="m-options-saved"><span class="dashicons dashicons-yes"></span><strong><?php esc_html_e( 'Saved', 'molongui-authorship' ); ?></strong></div>
<div id="m-options-error"><span class="dashicons dashicons-no"></span><strong><?php esc_html_e( 'Error', 'molongui-authorship' ); ?></strong></div>