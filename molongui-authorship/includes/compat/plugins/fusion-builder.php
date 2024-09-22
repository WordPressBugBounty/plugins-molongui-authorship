<?php

namespace Molongui\Authorship;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class AvadaBuilder
{
    public function __construct()
    {
        add_filter( 'authorship/render_box', array( $this, 'hide_author_box' ), 10, 4 );
    }
    public function hide_author_box( $default )
    {
        $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
        $class = 'Fusion_Template_Builder';
        $fn    = 'render_content';
        if ( in_the_loop() )
        {
            if ( $i = array_search( $fn, array_column( $dbt, 'function' ) )
                and
                isset( $dbt[$i]['class'] ) and ( $dbt[$i]['class'] === $class ) )
            {
                return false;
            }
        }
        return $default;
    }

} // class
new AvadaBuilder;