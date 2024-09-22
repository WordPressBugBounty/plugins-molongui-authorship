<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
spl_autoload_register( 'authorship_autoload' );
if ( !function_exists( 'authorship_autoload' ) )
{
    function authorship_autoload( $class_name )
    {
        $namespace_prefix = 'Molongui\Authorship';
        if ( false === strpos( $class_name, $namespace_prefix.'\\' ) ) return;
        if ( false !== strpos( $class_name, $namespace_prefix.'\Pro\\' ) ) return;
        $file_parts = explode( '\\', $class_name );
        $part      = 1;
        $namespace = '';
        for ( $i = count( $file_parts ) - 1; $i > $part; $i-- )
        {
            if ( 'Includes' === $file_parts[$i] ) { continue; }
            $current = strtolower( $file_parts[$i] );
            $current = str_ireplace( '_', '-', $current );
            if ( count( $file_parts ) - 1 === $i )
            {
                $file_name = $current.'.php';
            }
            else $namespace = $current . '/' . $namespace;
        }
        $filepath  = MOLONGUI_AUTHORSHIP_DIR . 'includes/' . $namespace;
        $filepath .= $file_name;
        if ( file_exists( $filepath ) )
        {
            require_once $filepath;
        }
        else
        {

            wp_die( esc_html( "The file attempting to be loaded at $filepath does not exist." ) );
        }
    }
}