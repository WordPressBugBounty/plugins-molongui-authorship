<?php

use Molongui\Authorship\Common\Modules\Settings;
use Molongui\Authorship\Common\Utils\Debug;
use Molongui\Authorship\Common\Utils\Helpers;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
add_action( 'plugins_loaded', function()
{
    if ( is_admin() and apply_filters( 'authorship/disable_custom_php_on_admin', '__return_true' ) )
    {
        return;
    }

    $custom_php = Settings::get( 'custom_php' );
    $custom_php = Helpers::clean_php( $custom_php );

    if ( !empty( $custom_php ) )
    {
        $custom_php = trim( $custom_php );
        $tag = '<?php';
        if ( 0 === strpos( $custom_php, $tag ) )
        {
            $custom_php = substr( $custom_php, strlen( $tag ) );
        }

        if ( !empty( $custom_php ) )
        {
            eval( $custom_php ); // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found
            Debug::console_log( null, __( "Custom PHP snippets loaded." ) );
        }
    }
}, PHP_INT_MAX );