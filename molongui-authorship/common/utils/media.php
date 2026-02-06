<?php

namespace Molongui\Authorship\Common\Utils;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

class Media
{
    public static function normalize_avatar_size( array $atts, $default_size = 96 )
    {
        $atts = wp_parse_args(
            $atts,
            array
            (
                'size'   => '',
                'width'  => '',
                'height' => '',
            )
        );

        $default_size = max( 1, absint( $default_size ) );
        $resolve = function( $value )
        {
            if ( $value === null )
            {
                return null;
            }

            if ( is_int( $value ) )
            {
                $v = absint( $value );
                return $v > 0 ? $v : null;
            }

            if ( is_string( $value ) )
            {
                $value = trim( $value );
                if ( $value === '' )
                {
                    return null;
                }
                if ( ctype_digit( $value ) )
                {
                    $v = absint( $value );
                    return $v > 0 ? $v : null;
                }
                $size_name = strtolower( $value );
                if ( $size_name === 'full' )
                {
                    return null;
                }

                $additional = wp_get_additional_image_sizes();
                if ( isset( $additional[ $size_name ]['width'], $additional[ $size_name ]['height'] ) )
                {
                    return array( absint( $additional[ $size_name ]['width'] ), absint( $additional[ $size_name ]['height'] ) );
                }
                $w = absint( get_option( "{$size_name}_size_w" ) );
                $h = absint( get_option( "{$size_name}_size_h" ) );

                if ( $w > 0 && $h > 0 )
                {
                    return array( $w, $h );
                }

                return null;
            }

            return null;
        };

        $width_raw  = $resolve( $atts['width'] );
        $height_raw = $resolve( $atts['height'] );
        if ( $width_raw !== null || $height_raw !== null )
        {
            if ( is_array( $width_raw ) )
            {
                $width_raw = $width_raw[0];
            }
            if ( is_array( $height_raw ) )
            {
                $height_raw = $height_raw[1];
            }
            if ( $width_raw !== null && $height_raw === null )
            {
                $height_raw = $width_raw;
            }
            elseif ( $width_raw === null && $height_raw !== null )
            {
                $width_raw = $height_raw;
            }

            $width  = max( 1, absint( $width_raw ) );
            $height = max( 1, absint( $height_raw ) );
            $atts['width']  = $width;
            $atts['height'] = $height;
            $atts['size']   = max( $width, $height );

            return $atts;
        }
        $size_raw = $resolve( $atts['size'] );

        if ( is_array( $size_raw ) )
        {
            $width  = max( 1, absint( $size_raw[0] ) );
            $height = max( 1, absint( $size_raw[1] ) );
            $size   = max( $width, $height );
        }
        elseif ( is_int( $size_raw ) )
        {
            $size   = max( 1, absint( $size_raw ) );
            $width  = $size;
            $height = $size;
        }
        else
        {
            $size   = $default_size;
            $width  = $default_size;
            $height = $default_size;
        }

        $atts['width']  = $width;
        $atts['height'] = $height;
        $atts['size']   = $size;

        return $atts;
    }

} // class
