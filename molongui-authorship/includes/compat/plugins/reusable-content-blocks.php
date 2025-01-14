<?php

namespace Molongui\Authorship;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class ReusableContentBlocks
{
    public function __construct()
    {
        add_filter( 'molongui_authorship/add_author_box_to_content', array( $this, 'prevent_author_box_in_block' ), 10, 1 );
    }
    public function prevent_author_box_in_block( $add )
    {
        $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
        $fn  = 'rcb_get_content_func';
        if ( array_search( $fn, array_column( $dbt, 'function' ) ) )
        {
            $add = false;
        }

        return $add;
    }

} // class
new ReusableContentBlocks;