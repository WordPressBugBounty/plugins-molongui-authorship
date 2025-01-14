<?php

use Molongui\Authorship\Author_Filters;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
add_filter( 'molongui_authorship_do_filter_name', function( $leave, &$args )
{
    if ( $leave ) return $leave;
    if ( isset( $args['dbt'][3]['function'] ) and ( $args['dbt'][3]['function'] == 'get_the_author' )
        and
        isset( $args['dbt'][3]['file'] ) and substr_compare( $args['dbt'][3]['file'], '/themes/colormag/archive.php', strlen( $args['dbt'][3]['file'] )-strlen( '/themes/colormag/archive.php' ), strlen( '/themes/colormag/archive.php' ) ) === 0
    )
    {
        $args['display_name'] = Author_Filters::filter_the_archive_title( $args['display_name'] );
        return true;
    }
    return false;
}, 10, 2 );