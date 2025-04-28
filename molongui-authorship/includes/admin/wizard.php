<?php

namespace Molongui\Authorship;

use Molongui\Authorship\Common\Utils\Request;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Wizard extends \Molongui\Authorship\Common\Modules\Setup_Wizard
{
    protected $settings_slug = 'admin.php?page=molongui-authorship';
    public function __construct()
    {
        add_filter( 'authorship/wizard_fallback', array( $this, 'get_fallback_url' ) );
        add_filter( 'authorship/wizard_settings', array( $this, 'parse_settings' ) );
        add_filter( 'authorship/wizard_steps', array( __CLASS__, 'get_step_count' ) );

        parent::__construct();
    }
    public function get_fallback_url()
    {
        return '/admin.php?page=molongui-authorship';
    }
    public function parse_settings( $wizard_settings )
    {
        $ignore = array
        (
            'action',
            'nonce',
        );

        foreach ( $_REQUEST as $key => $value )
        {
            if ( in_array( $key, $ignore ) )
            {
                continue;
            }
            $wizard_settings[$key] = sanitize_text_field( $value );
        }

        return $wizard_settings;
    }
    public static function get_step_count()
    {
        $max_steps = 5;

        return $max_steps - 2;
    }

} // class
new Wizard();
