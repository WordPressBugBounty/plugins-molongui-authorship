<?php

namespace Molongui\Authorship\Includes\Libraries\Common;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
if ( !class_exists( 'Molongui\Authorship\Includes\Libraries\Common\WP_Async_Request' ) )
{
    class WP_Async_Request extends \Molongui\Authorship\Common\Libraries\WP_Async_Request
    {
        public function __construct()
        {
            parent::__construct();
        }
    }
}