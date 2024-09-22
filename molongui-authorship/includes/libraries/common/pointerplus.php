<?php

namespace Molongui\Authorship\Includes\Libraries\Common;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
if ( !class_exists( 'Molongui\Authorship\Includes\Libraries\Common\PointerPlus' ) )
{
    class PointerPlus extends \Molongui\Authorship\Common\Modules\PointerPlus
    {
        public function __construct( $args = array() )
        {
            parent::__construct( $args );
        }
    }
}