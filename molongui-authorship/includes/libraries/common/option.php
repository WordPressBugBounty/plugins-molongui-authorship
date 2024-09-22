<?php

namespace Molongui\Authorship\Includes\Libraries\Common;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
if ( !class_exists( 'Molongui\Authorship\Includes\Libraries\Common\Option' ) )
{
    class Option extends \Molongui\Authorship\Common\Modules\Settings\Control
    {
        public function __construct( $data, $group = '', $key = '', $prefix = 'molongui' )
        {
            parent::__construct( $data, $group, $key, $prefix );
        }
    }
}