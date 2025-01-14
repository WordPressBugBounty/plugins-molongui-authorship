<?php
/*!
 * Singleton Trait
 *
 * This file defines the Singleton trait, which enforces the Singleton design pattern to ensure that a class has only
 * one instance and provides a global point of access to it.
 *
 * @author     Molongui
 * @package    Framework
 * @subpackage fw/core/utils
 * @since      3.3.0
 */

namespace Molongui\Authorship\Common\Utils;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
trait Singleton
{
    private static $_instance = null;
    public function __clone()
    {
        _doing_it_wrong( __FUNCTION__, esc_html__( "Cloning instances of this class is forbidden.", 'molongui-authorship' ), '1.0.0' );
    }
    public function __wakeup()
    {
        _doing_it_wrong( __FUNCTION__, esc_html__( "Unserializing instances of this class is forbidden.", 'molongui-authorship' ), '1.0.0' );
    }
    public static function instance()
    {
        if ( is_null( self::$_instance ) )
        {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

} // trait