<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
add_filter( 'molongui_edit_main_query_only', function( $default, &$query )
{
    return false;
}, 10, 2 );