<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

$logo = file_exists( MOLONGUI_AUTHORSHIP_DIR . 'assets/img/plugin_logo.png' ) ? MOLONGUI_AUTHORSHIP_URL . 'assets/img/plugin_logo.png' : MOLONGUI_AUTHORSHIP_URL . 'common/modules/settings/assets/img/masthead_logo.png';

?>

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