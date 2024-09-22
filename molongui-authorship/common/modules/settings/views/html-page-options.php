<?php

use Molongui\Authorship\Common\Modules\Settings;
use Molongui\Authorship\Common\Utils\Helpers;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

$logo = file_exists( MOLONGUI_AUTHORSHIP_DIR . 'assets/img/plugin_logo.png' ) ? MOLONGUI_AUTHORSHIP_URL . 'assets/img/plugin_logo.png' : MOLONGUI_AUTHORSHIP_URL . 'assets/img/common/masthead_logo.png';

?>

<div id="molongui-options">

    <?php do_action( 'authorship/options/before_masthead' ); ?>

    <!-- Page Header -->
    <div class="m-page-masthead">
        <div class="m-page-masthead__inside_container">
            <div class="m-page-masthead__logo-container">
                <a class="m-page-masthead__logo-link" href="<?php echo esc_url( MOLONGUI_AUTHORSHIP_WEB ); ?>">
                    <img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( MOLONGUI_AUTHORSHIP_TITLE ); ?>" height="32">
                </a>
            </div>
            <div class="m-page-masthead__nav">
            <span class="m-buttons">
                <a id="m-button-save" class="m-button m-button-save is-compact is-primary" type="button"><?php echo esc_html__( "Save Settings", 'molongui-authorship' ); ?></a>
            </span>
            </div>
        </div><!-- !m-page-masthead -->
    </div><!-- !m-page-masthead -->

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
    ?>

    <!-- Page Footer -->
    <div class="m-page-footer">

        <div class="m-page-footer__a8c-attr-container">
            <a href="<?php echo esc_url( MOLONGUI_AUTHORSHIP_WEB ); ?>">
                <img src="<?php echo esc_url( MOLONGUI_AUTHORSHIP_URL . 'common/assets/img/footer_logo.png' ); ?>" alt="Molongui" width="152" height="32">
            </a>
        </div>

        <?php if ( !empty( $args['links'] ) ) : ?>
            <ul class="m-page-footer__links">
                <?php foreach( $args['links'] as $link ) : ?>
                    <?php if ( $link['display'] ) : ?>
                        <li class="m-page-footer__link-item">
                            <a rel="noopener noreferrer" class="m-page-footer__link"
                               target="<?php echo empty( $link['target'] ) ? '_blank' : esc_attr( $link['target'] ); ?>"
                               title="<?php echo empty( $link['tip'] ) ? '' : esc_attr( $link['tip'] ); ?>"
                               href="<?php echo esc_url( $link['href'] ); ?>">
                                <?php echo wp_kses_post( $link['prefix'] ); ?>
                                <?php echo esc_html( $link['label'] ); ?>
                                <?php echo wp_kses_post( $link['suffix'] ); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

    </div><!-- !m-page-footer -->

    <?php Settings::enqueue_scripts(); ?>
    <?php Settings::enqueue_styles();  ?>
    <?php Helpers::load_tidio(); ?>
    <?php do_action( 'authorship/options/after_footer' ); ?>

</div> <!-- #molongui-options -->

<div id="m-options-saving"><div class="m-loader"><div></div><div></div><div></div><div></div></div></div>
<div id="m-options-saved"><span class="dashicons dashicons-yes"></span><strong><?php esc_html_e( 'Saved', 'molongui-authorship' ); ?></strong></div>
<div id="m-options-error"><span class="dashicons dashicons-no"></span><strong><?php esc_html_e( 'Error', 'molongui-authorship' ); ?></strong></div>