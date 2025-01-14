<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
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
            'tip'     => __( "Check out all the plugin features", 'molongui-authorship' ),
            'prefix'  => '<span class="m-page-footer__version">',
            'suffix'  => '</span>',
            'href'    => $plugin_url,
            'target'  => '_blank',
            'display' => did_action( 'authorship_pro/loaded' ),
        ),
        array
        (
            'label'   => __( "Free", 'molongui-authorship' ) . " " . MOLONGUI_AUTHORSHIP_VERSION,
            'prefix'  => '<span class="m-page-footer__version">',
            'suffix'  => '</span>',
            'href'    => $plugin_url,
            'target'  => '_blank',
            'display' => true,
        ),
        array
        (
            'label'   => __( "Changelog", 'molongui-authorship' ),
            'prefix'  => '',
            'suffix'  => '',
            'href'    => $changelog_url,
            'target'  => '_blank',
            'display' => true,
        ),
        array
        (
            'label'   => __( "Docs", 'molongui-authorship' ),
            'prefix'  => '',
            'suffix'  => '',
            'href'    => $help_url . MOLONGUI_AUTHORSHIP_ID,
            'target'  => '_blank',
            'display' => true,
        ),
        array
        (
            'label'   => __( "Support", 'molongui-authorship' ),
            'prefix'  => '',
            'suffix'  => '',
            'href'    => $support_url,
            'target'  => '_blank',
            'display' => true,
        ),
        array
        (
            'label'   => __( "Try Pro", 'molongui-authorship' ),
            'prefix'  => '',
            'suffix'  => '',
            'href'    => $demo_url,
            'target'  => '_blank',
            'display' => !did_action( 'authorship_pro/loaded' ),
        ),
        array
        (
            'label'   => __( "Upgrade", 'molongui-authorship' ),
            'prefix'  => '',
            'suffix'  => '',
            'href'    => $plugin_url.'pricing/',
            'target'  => '_blank',
            'display' => !did_action( 'authorship_pro/loaded' ),
        ),
    ),
);
?>

<!-- Page Footer -->
<div class="m-page-footer">

    <div class="m-page-footer__a8c-attr-container">
        <a href="<?php echo esc_url( MOLONGUI_AUTHORSHIP_WEB ); ?>">
            <img src="<?php echo esc_url( MOLONGUI_AUTHORSHIP_URL . 'common/modules/settings/assets/img/footer_logo.png' ); ?>" alt="Molongui" width="152" height="32">
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
