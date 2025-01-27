<?php

namespace Molongui\Authorship;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class ReusableContentBlocks
{
    public function __construct()
    {
        add_filter( 'molongui_authorship/display_author_box', array( $this, 'prevent_author_box_in_block' ), 10, 1 );
    }
    public function prevent_author_box_in_block( $default )
    {
        $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
        $fn  = 'rcb_get_content_func';
        if ( array_search( $fn, array_column( $dbt, 'function' ) ) )
        {
            return false;
        }

        return $default;
    }

} // class
new ReusableContentBlocks;
