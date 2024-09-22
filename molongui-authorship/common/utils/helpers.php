<?php

namespace Molongui\Authorship\Common\Utils;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Helpers
{
    public static function array_sort( $array = array(), $order = 'ASC', $orderby = 'key' )
    {
        if ( empty( $array ) )
        {
            return $array;
        }
        switch ( $orderby )
        {
            case 'key':
                ksort( $array );
                break;

            default:
                uasort( $array , function ( $item1, $item2 ) use ( $orderby )
                {
                    if ( $item1[$orderby] == $item2[$orderby] ) return 0;
                    return $item1[$orderby] < $item2[$orderby] ? -1 : 1;
                });
                break;
        }
        if ( 'desc' === strtolower( $order ) )
        {
            $array = array_reverse( $array );
        }

        return $array;
    }
    public static function array_recursive_sort( array &$array )
    {
        foreach ( $array as &$value )
        {
            if ( is_array( $value ) )
            {
                self::array_recursive_sort( $value );
            }
        }
        sort( $array );
    }
    public static function array_match( $array1, $array2, $operator = '==' )
    {
        $match = false;

        self::array_recursive_sort( $array1 );
        self::array_recursive_sort( $array2 );

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
    public static function arrays_equal( $array1, $array2, $sort = false )
    {
        if ( $sort )
        {
            if ( !empty( $array1 ) ) array_multisort( $array1 );
            if ( !empty( $array2 ) ) array_multisort( $array2 );
        }

        return ( serialize( $array1 ) === serialize( $array2 ) );
    }
    public static function string_to_array( $string )
    {
        $no_whitespaces = preg_replace( '/\s*,\s*/', ',', filter_var( $string, FILTER_SANITIZE_STRING ) );
        $array = explode( ',', $no_whitespaces );
        return $array;
    }
    public static function space_to_nbsp( $string )
    {
        return str_replace( ' ', '&nbsp;', $string );
    }
    public static function is_bool( $var )
    {
        return ( '0' === $var or '1' === $var );
    }
    public static function rand( $length = 10 )
    {
        return substr( number_format( time() * wp_rand(), 0, '', '' ), 0, $length );
    }
    public static function let_to_num( $size )
    {
        $l   = substr( $size, - 1 );
        $ret = substr( $size, 0, - 1 );
        switch ( strtoupper( $l ) )
        {
            case 'P':
                $ret *= 1024;
            case 'T':
                $ret *= 1024;
            case 'G':
                $ret *= 1024;
            case 'M':
                $ret *= 1024;
            case 'K':
                $ret *= 1024;
        }

        return $ret;
    }
    public static function get_acronym ( $words, $length = 3 )
    {
        $acronym = '';
        foreach ( explode( ' ', $words ) as $word ) $acronym .= mb_substr( $word, 0, 1, 'utf-8' );

        return strtoupper( mb_substr( $acronym, 0, $length ) );
    }
    public static function ascii_encode( $input )
    {
        $output = '';
        for ( $i = 0; $i < strlen( $input ); $i++ )
        {
            $output .= '&#'.ord( $input[$i] ).';';
        }
        return $output;
    }
    public static function get_remote_response( $url )
    {
        $response = null;
        $args = array
        (
            'method'      => 'GET',
            'timeout'     => 30,
            'redirection' => 10,
            'httpversion' => '1.1',
            'sslverify'   => false,
        );
        $response = wp_remote_get( $url, $args );
        if ( is_wp_error( $response ) or !isset( $response ) or empty( $response ) )
        {

            $response = 0;
        }
        else
        {
            $response = unserialize( wp_remote_retrieve_body( $response ) );
        }
        return $response;
    }
    public static function clean( $var )
    {
        if ( is_array( $var ) )
        {
            return array_map( array( self::class, 'clean' ), $var );
        }
        else
        {
            return is_scalar( $var ) ? sanitize_text_field( $var ) : $var;
        }
    }
    public static function clean_php( $snippet, $method = 'tokenization' )
    {
        switch ( $method )
        {
            case 'regexp':
                $snippet = preg_replace('/\/\/[^\r\n]*/', '', $snippet );
                $snippet = preg_replace('/\/\*.*?\*\//s', '', $snippet );
                $snippet = preg_replace('/^\s*[\r\n]/m', '', $snippet );

                break;
            case 'tokenization':
            default:

                $cleaned_snippet = '';
                $tokens = token_get_all( $snippet );

                foreach ( $tokens as $token )
                {
                    if ( is_array( $token ) )
                    {
                        list( $id, $text ) = $token;
                        if ( in_array( $id, [T_COMMENT, T_DOC_COMMENT] ) )
                        {
                            continue;
                        }
                        if ( $id === T_WHITESPACE )
                        {
                            $text = ' ';
                        }
                        $cleaned_snippet .= $text;
                    }
                    else
                    {
                        $cleaned_snippet .= $token;
                    }
                }
                $snippet = preg_replace( '/^\s*[\r\n]/m', '', $cleaned_snippet );

                break;
        }

        return $snippet;
    }
    public static function minify_html( $input )
    {
        if (trim($input) === "") return $input;
        $input = preg_replace_callback('#<([^\/\s<>!]+)(?:\s+([^<>]*?)\s*|\s*)(\/?)>#s', function($matches)
        {
            return '<' . $matches[1] . preg_replace('#([^\s=]+)(\=([\'"]?)(.*?)\3)?(\s+|$)#s', ' $1$2', $matches[2]) . $matches[3] . '>';
        }, str_replace("\r", "", $input));
        if (strpos($input, ' style=') !== false)
        {
            $input = preg_replace_callback('#<([^<]+?)\s+style=([\'"])(.*?)\2(?=[\/\s>])#s', function($matches) {
                return '<' . $matches[1] . ' style=' . $matches[2] . self::minify_css($matches[3]) . $matches[2];
            }, $input);
        }
        if (strpos($input, '</style>') !== false)
        {
            $input = preg_replace_callback('#<style(.*?)>(.*?)</style>#is', function($matches) {
                return '<style' . $matches[1] .'>'. self::minify_css($matches[2]) . '</style>';
            }, $input);
        }
        if (strpos($input, '</script>') !== false)
        {
            $input = preg_replace_callback('#<script(.*?)>(.*?)</script>#is', function($matches) {
                return '<script' . $matches[1] .'>'. self::minify_js($matches[2]) . '</script>';
            }, $input);
        }

        return preg_replace
        (
            array
            (
                '#<(img|input)(>| .*?>)#s',
                '#(<!--.*?-->)|(>)(?:\n*|\s{2,})(<)|^\s*|\s*$#s',
                '#(<!--.*?-->)|(?<!\>)\s+(<\/.*?>)|(<[^\/]*?>)\s+(?!\<)#s', // t+c || o+t
                '#(<!--.*?-->)|(<[^\/]*?>)\s+(<[^\/]*?>)|(<\/.*?>)\s+(<\/.*?>)#s', // o+o || c+c
                '#(<!--.*?-->)|(<\/.*?>)\s+(\s)(?!\<)|(?<!\>)\s+(\s)(<[^\/]*?\/?>)|(<[^\/]*?\/?>)\s+(\s)(?!\<)#s', // c+t || t+o || o+t -- separated by long white-space(s)
                '#(<!--.*?-->)|(<[^\/]*?>)\s+(<\/.*?>)#s', // empty tag
                '#<(img|input)(>| .*?>)<\/\1>#s', // reset previous fix
                '#(&nbsp;)&nbsp;(?![<\s])#', // clean up ...
                '#(?<=\>)(&nbsp;)(?=\<)#', // --ibid
                '#\s*<!--(?!\[if\s).*?-->\s*|(?<!\>)\n+(?=\<[^!])#s'
            ),
            array
            (
                '<$1$2</$1>',
                '$1$2$3',
                '$1$2$3',
                '$1$2$3$4$5',
                '$1$2$3$4$5$6$7',
                '$1$2$3',
                '<$1$2',
                '$1 ',
                '$1',
                ""
            ),
            $input);
    }
    public static function minify_css( $input )
    {
        if(trim($input) === "") return $input;

        return preg_replace
        (
            array
            (
                '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')|\/\*(?!\!)(?>.*?\*\/)|^\s*|\s*$#s',
                '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/))|\s*+;\s*+(})\s*+|\s*+([*$~^|]?+=|[{};,>~]|\s(?![0-9\.])|!important\b)\s*+|([[(:])\s++|\s++([])])|\s++(:)\s*+(?!(?>[^{}"\']++|"(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')*+{)|^\s++|\s++\z|(\s)\s+#si',
                '#(?<=[\s:])(0)(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)#si',
                '#:(0\s+0|0\s+0\s+0\s+0)(?=[;\}]|\!important)#i',
                '#(background-position):0(?=[;\}])#si',
                '#(?<=[\s:,\-])0+\.(\d+)#s',
                '#(\/\*(?>.*?\*\/))|(?<!content\:)([\'"])([a-z_][a-z0-9\-_]*?)\2(?=[\s\{\}\];,])#si',
                '#(\/\*(?>.*?\*\/))|(\burl\()([\'"])([^\s]+?)\3(\))#si',
                '#(?<=[\s:,\-]\#)([a-f0-6]+)\1([a-f0-6]+)\2([a-f0-6]+)\3#i',
                '#(?<=[\{;])(border|outline):none(?=[;\}\!])#',
                '#(\/\*(?>.*?\*\/))|(^|[\{\}])(?:[^\s\{\}]+)\{\}#s'
            ),
            array
            (
                '$1',
                '$1$2$3$4$5$6$7',
                '$1',
                ':0',
                '$1:0 0',
                '.$1',
                '$1$3',
                '$1$2$4$5',
                '$1$2$3',
                '$1:0',
                '$1$2'
            ),
            $input);
    }
    public static function minify_js( $input )
    {
        if(trim($input) === "") return $input;

        return preg_replace
        (
            array
            (
                '#\s*("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')\s*|\s*\/\*(?!\!|@cc_on)(?>[\s\S]*?\*\/)\s*|\s*(?<![\:\=])\/\/.*(?=[\n\r]|$)|^\s*|\s*$#',
                '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/)|\/(?!\/)[^\n\r]*?\/(?=[\s.,;]|[gimuy]|$))|\s*([!%&*\(\)\-=+\[\]\{\}|;:,.<>?\/])\s*#s',
                '#;+\}#',
                '#([\{,])([\'])(\d+|[a-z_][a-z0-9_]*)\2(?=\:)#i',
                '#([a-z0-9_\)\]])\[([\'"])([a-z_][a-z0-9_]*)\2\]#i'
            ),
            array
            (
                '$1',
                '$1$2',
                '}',
                '$1$3',
                '$1.$3'
            ),
            $input);
    }
    public static function get_base64_svg( $svg, $base64 = true )
    {
        if ( $base64 )
        {
            return 'data:image/svg+xml;base64,' . base64_encode( $svg );
        }

        return $svg;
    }
    public static function get_language()
    {
        $language = '';
        if ( false )
        {

        }
        elseif ( function_exists( 'pll_current_language' ) )
        {
            $language = pll_current_language();
        }
        elseif ( defined( 'ICL_LANGUAGE_CODE' ) )
        {
            $language = ICL_LANGUAGE_CODE;
        }
        elseif ( has_filter( 'wpml_current_language' ) )
        {
            $language = apply_filters( 'wpml_current_language', NULL );
        }
        elseif ( array_key_exists( 'TRP_LANGUAGE', $GLOBALS ) )
        {
            $language = $GLOBALS['TRP_LANGUAGE'];
        }
        elseif ( function_exists( 'qtrans_getLanguage' ) )
        {
            $language = qtrans_getLanguage();
        }
        elseif ( array_key_exists( 'q_config', $GLOBALS ) )
        {
            if ( isset( $GLOBALS['q_config']['language'] ) )
            {
                $language = $GLOBALS['q_config']['language'];
            }
        }
        elseif ( function_exists( 'weglot_get_current_language' ) )
        {
            $language = weglot_get_current_language();
        }
        elseif ( has_filter( 'mlp_language_api' ) )
        {
            $language = apply_filters( 'mlp_language_api', NULL );
        }

        return $language;
    }
    public static function is_edit_mode()
    {
        return apply_filters( 'authorship/is_edit_mode', self::is_block_editor() or self::is_elementor_editor() );
    }
    public static function is_block_editor()
    {
        $edit_mode = false;
        if ( function_exists( 'is_gutenberg_page' ) and is_gutenberg_page() )
        {
            $edit_mode = true;
        }
        if ( function_exists( 'get_current_screen' ) )
        {
            $current_screen = get_current_screen();
            if ( !is_null( $current_screen ) and method_exists( $current_screen, 'is_block_editor' ) and $current_screen->is_block_editor() )
            {
                $edit_mode = true;
            }
        }

        return apply_filters( 'authorship/is_block_editor', $edit_mode );
    }
    public static function is_elementor_editor()
    {
        $edit_mode = false;

        if ( did_action( 'elementor/loaded' ) )
        {
            $edit_mode = \Elementor\Plugin::$instance->editor->is_edit_mode();
        }

        return apply_filters( 'authorship/is_elementor_editor', $edit_mode );
    }
    public static function load_tidio()
    {
        if ( apply_filters( 'authorship/load_tidio', true ) )
        {
            echo '<script src="//code.tidio.co/foioudbu7xqepgvwseufnvhcz6wkp7am.js" async></script>';
        }
    }
    public static function get_tidio_url()
    {
        return 'https://www.tidiochat.com/chat/foioudbu7xqepgvwseufnvhcz6wkp7am';
    }
    public static function return_list_true() // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionDoubleUnderscore,PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames.FunctionDoubleUnderscore
    {
        return array( true, null );
    }
    public static function return_list_false() // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionDoubleUnderscore,PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames.FunctionDoubleUnderscore
    {
        return array( false, null );
    }
    public static function bypass_filter( $null, $original_value )
    {
        return $original_value;
    }

} // class