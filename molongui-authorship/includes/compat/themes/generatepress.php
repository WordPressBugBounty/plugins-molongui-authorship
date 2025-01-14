<?php

use Molongui\Authorship\Author_Filters;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
add_filter( 'molongui_authorship_do_filter_name', function( $leave, &$args )
{
    if ( $leave ) return $leave;
    if ( isset( $args['dbt'][4]['function'] ) and ( $args['dbt'][4]['function'] == 'generate_filter_the_archive_title' ) )
    {
        $args['display_name'] = Author_Filters::filter_the_archive_title( $args['display_name'] );
        return true;
    }
    return false;
}, 10, 2 );