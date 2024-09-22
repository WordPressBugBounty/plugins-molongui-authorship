<?php
defined( 'ABSPATH' ) or exit;
add_filter( '_authorship/filter/the_author', function( $data, $args )
{
    list( $filter, $user ) = $data;
    $i    = 3;
    $file = '/themes/smart-mag/partials/author.php';
    if ( isset( $args['dbt'][$i]['function'] ) and $args['dbt'][$i]['function'] == 'get_the_author' and
         isset( $args['dbt'][$i+2]['function'] ) and $args['dbt'][$i+2]['function'] == 'the_author_posts_link' and
         isset( $args['dbt'][$i+2]['file'] ) and substr_compare( $args['dbt'][$i+2]['file'], $file, strlen( $args['dbt'][$i+2]['file'] )-strlen( $file ), strlen( $file ) ) === 0
    )
        $filter = false;
    return array( $filter, $user );
}, 10, 2 );
add_filter( 'authorship/pre_the_author_posts_link', function( $link, $original_link )
{
    $dbt  = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
    $fn   = 'the_author_posts_link';
    $file = '/themes/smart-mag/partials/author.php';

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) )
    {
        if ( isset( $dbt[$i]['file'] ) and substr_compare( $dbt[$i]['file'], $file, strlen( $dbt[$i]['file'] )-strlen( $file ), strlen( $file ) ) === 0 )
        {
            $link = $original_link;
        }
    }

    return $link;
}, 10, 2 );