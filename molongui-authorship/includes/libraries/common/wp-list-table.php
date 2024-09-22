<?php

namespace Molongui\Authorship\Includes\Libraries\Common;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
#[AllowDynamicProperties]
class WP_List_Table extends \Molongui\Authorship\Common\Libraries\WP_List_Table
{
    public function __construct( $args = array() )
    {
        parent::__construct( $args );
    }
}