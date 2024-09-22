<?php

namespace Molongui\Authorship\Common\Utils;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Icon
{
    public static function get_svg( $icon )
    {
        $width  = apply_filters( 'authorship/svg_icon_width',  '18' );
        $height = apply_filters( 'authorship/svg_icon_height', '18' );

        $svg_offset_x = isset( $icon['svg_offset']['x'] ) ? $icon['svg_offset']['x'] : 0;
        $svg_offset_y = isset( $icon['svg_offset']['y'] ) ? $icon['svg_offset']['y'] : 0;
        $fill         = apply_filters( 'authorship/svg_icon_fill', !empty( $icon['color'] ) ? 'fill="'.$icon['color'].'"' : '' );
        $output       = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="' . $svg_offset_x . ' ' . $svg_offset_y . ' ' . absint( $icon['width'] ) . ' ' . absint( $icon['height'] ) . '">';
        foreach ( $icon['paths'] as $path )
        {
            $output .= '<path '. $fill. ' d="' . $path . '"></path>';
        }
        $output .= '</svg>';

        return $output;
    }

} // class