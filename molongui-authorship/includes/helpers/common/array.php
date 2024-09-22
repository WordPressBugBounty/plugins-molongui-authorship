<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
function authorship_array_recursive_sort( array &$array )
{
    foreach ( $array as &$value )
    {
        if ( is_array( $value ) )
        {
            authorship_array_recursive_sort( $value );
        }
    }
    sort( $array );
}
function authorship_array_match( $array1, $array2, $operator = '==' )
{
    $match = false;

    authorship_array_recursive_sort( $array1 );
    authorship_array_recursive_sort( $array2 );

    switch ( $operator )
    {
        case '==':
            if ( $array1 == $array2 )
            {
                $match = true;
            }
            break;

        case '===':
            if ( $array1 === $array2 )
            {
                $match = true;
            }
            break;
    }

    return $match;
}