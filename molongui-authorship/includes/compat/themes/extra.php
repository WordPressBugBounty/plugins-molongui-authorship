<?php

namespace Molongui\Authorship;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Extra
{
    public function __construct()
    {
        add_filter( 'molongui_authorship/add_author_box_to_content_outside_the_loop', array( $this, 'add_author_box_on_templates' ) );
        add_filter( 'authorship/pre_author_link', array( $this, 'filter_the_author_link' ), 10, 4 );
    }
    public function add_author_box_on_templates( $add )
    {
        $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
        $fn  = 'et_theme_builder_frontend_render_post_content';
        if ( array_search( $fn, array_column( $dbt, 'function' ) ) )
        {
            $add = true;
        }

        return $add;
    }
    public function filter_the_author_link( $link, $original_link, $author_id, $author_nicename )
    {
        $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
        $fn    = 'widget';
        $class = 'ET_Authors_Widget';

        if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) and
             isset( $dbt[$i]['class'] ) and ( $dbt[$i]['class'] == $class ) )
        {
            $link = $original_link;
        }

        return $link;
    }

} // class
new Extra;
