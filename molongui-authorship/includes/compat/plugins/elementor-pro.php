<?php

use Molongui\Authorship\Common\Utils\Helpers;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
add_filter( '_authorship/get_user_by/aim', function( $aim, $user, $field, $value )
{
    $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
    $fn    = 'render';
    $class = 'ElementorPro\Modules\Posts\Skins\Skin_Base';
    if ( $key = array_search( $fn, array_column( $dbt, 'function' ) ) and
         isset( $dbt[$key]['class'] ) and ( $dbt[$key]['class'] == $class ) )
    {
        $aim = 'byline';
    }
    return $aim;
}, 10, 4 );
add_filter( 'authorship/byline_separator'     , array( Helpers::class, 'space_to_nbsp' ) );
add_filter( 'authorship/byline_last_separator', array( Helpers::class, 'space_to_nbsp' ) );