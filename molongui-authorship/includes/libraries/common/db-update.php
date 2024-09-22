<?php

namespace Molongui\Authorship\Includes\Libraries\Common;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
if ( !class_exists( 'Molongui\Authorship\Includes\Libraries\Common\DB_Update' ) )
{
    class DB_Update extends \Molongui\Authorship\Common\Modules\DB_Update
    {
        public function __construct( $target, $db_key, $namespace )
        {
            parent::__construct( $target, $db_key, $namespace );
        }
    }
}