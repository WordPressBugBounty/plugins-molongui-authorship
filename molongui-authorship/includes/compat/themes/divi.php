<?php

namespace Molongui\Authorship;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Divi
{
    public function __construct()
    {
        add_filter( 'molongui_authorship/add_author_box_to_content_outside_the_loop', array( $this, 'add_author_box_on_templates' ) );
        add_filter( 'molongui_authorship_do_filter_name', array( $this, 'filter_the_author_name' ), 10, 2 );
        add_filter( 'molongui_edit_main_query_only', array( $this, 'filter_archive_query' ), 10, 2 );
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
    public function filter_the_author_name( $leave, &$args )
    {
        if ( $leave )
        {
            return $leave;
        }
        if ( isset( $args['dbt'][3]['function'] ) and ( $args['dbt'][3]['function'] == 'get_the_author' ) and isset( $args['dbt'][4]['function'] ) and ( $args['dbt'][4]['function'] == 'et_builder_get_current_title' ) )
        {
            $args['display_name'] = Author_Filters::filter_the_archive_title( $args['display_name'] );
            return true;
        }
        return false;
    }
    public function filter_archive_query( $default, &$query )
    {
        if ( !$query->is_author() )
        {
            return $default;
        }
        $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 11 );
        if ( empty( $dbt ) )
        {
            return $default;
        }
        $fn    = 'render';
        $class = 'ET_Builder_Module_Blog';
        if ( $key = array_search( $fn, array_column( $dbt, 'function' ) ) and
             isset( $dbt[$key]['class'] ) and ( $dbt[$key]['class'] == $class ) )
        {
            return false;
        }
        return $default;
    }

} // class
new Divi;
