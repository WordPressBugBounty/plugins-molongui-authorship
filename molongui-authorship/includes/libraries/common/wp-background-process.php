<?php

namespace Molongui\Authorship\Includes\Libraries\Common;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
if ( !class_exists( 'Molongui\Authorship\Includes\Libraries\Common\WP_Background_Process' ) )
{
    abstract class WP_Background_Process extends \Molongui\Authorship\Common\Libraries\WP_Background_Process
    {
        public function __construct()
        {
            parent::__construct();
        }
    }
}