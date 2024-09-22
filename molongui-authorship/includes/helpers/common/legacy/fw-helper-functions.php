<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
if ( !function_exists( 'molongui_get_constant' ) )
{
    function molongui_get_constant( $plugin_id, $constant )
    {
        $const = strtoupper( strtr( $plugin_id, array( ' ' => '_', '-' => '_' ) ) . "_" . $constant );
	    $value = defined( $const ) ? constant( $const ) : false;
	    return $value;
    }
}
if ( !function_exists( 'molongui_get_support' ) )
{
    function molongui_get_support()
    {
        return admin_url('/admin.php?page=molongui-support');
    }
}
if ( !function_exists( 'molongui_curl' ) )
{
    function molongui_curl($url)
    {
        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_USERAGENT, '');
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($curl);
        if (0 !== curl_errno($curl) || 200 !== curl_getinfo($curl, CURLINFO_HTTP_CODE))
        {
            $response = null;
        }
        curl_close($curl);

        return $response;
    }
}
if ( !function_exists( 'molongui_post_categories' ) )
{
    function molongui_post_categories($setting = false, $hide_empty = true)
    {
        $categories = $options = array();
        $post_categories = get_categories(array
        (
            'orderby'    => 'name',
            'order'      => 'ASC',
            'hide_empty' => $hide_empty,
        ));
        foreach ($post_categories as $category)
        {
            $categories[] = $category->name;
            $premium      = true;//in_array( $category->name, array( 'post', 'page' ) ) ? false : true;
            $options[]    = array('id' => $category->cat_ID, 'label' => $category->name, 'premium' => $premium);
        }
        return ($setting ? $options : $categories);
    }
}
if ( !function_exists( 'molongui_get_domain' ) )
{
    function molongui_get_domain()
    {
        $scheme    = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] . '://' : '';
        $host      = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $subfolder = isset($_SERVER['DOCUMENT_URI']) ? explode('wp-admin', $_SERVER['DOCUMENT_URI']) : '';
        $subfolder = is_array($subfolder) ? $subfolder[0] : '';

        return $scheme . $host . $subfolder;
    }
}
if ( !function_exists( 'molongui_get_admin_color' ) )
{
    function molongui_get_admin_color()
    {
        $css = $scheme = '';
        global $_wp_admin_css_colors;

        if ($_wp_admin_css_colors)
        {
            $colors = $_wp_admin_css_colors[get_user_option('admin_color')]->colors;

            if (!empty($colors))
            {
                foreach ($colors as $key => $color)
                {
                    $scheme .= '--m-admin-color-' . $key . ':' . $color . ';';
                }
                $css .= ":root{ " . $scheme . " }";
            }
        }

        return !empty($css) ? $css : '';
    }
}
if ( !function_exists( 'molongui_is_rest_api_request' ) )
{
    function molongui_is_rest_api_request()
    {
        if ( empty( $_SERVER['REQUEST_URI'] ) ) return false;

        $rest_prefix         = trailingslashit( rest_get_url_prefix() );
        $is_rest_api_request = ( false !== strpos( $_SERVER['REQUEST_URI'], $rest_prefix ) ); // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        return apply_filters( 'molongui_is_rest_api_request', $is_rest_api_request );
    }
}
if ( !function_exists( 'molongui_is_request' ) )
{
    function molongui_is_request($type)
    {
        switch ($type)
        {
            case 'admin':
            case 'backend':
                return (is_admin() and !wp_doing_ajax());
            case 'ajax':
                return (is_admin() and wp_doing_ajax());
            case 'api':
                return molongui_is_rest_api_request();
            case 'cron':
                return defined('DOING_CRON');
            case 'customizer':
                return (is_customize_preview());
            case 'front':
            case 'frontend':
                return (!is_admin() or defined('DOING_AJAX')) and !molongui_is_rest_api_request() and !defined('DOING_CRON');
        }
    }
}
if ( !function_exists( 'molongui_is_bool' ) )
{
    function molongui_is_bool($var)
    {
        if ('0' === $var or '1' === $var) return true;

        return false;
    }
}
if ( !function_exists( 'molongui_parse_array_attribute' ) )
{
    function molongui_parse_array_attribute($string)
    {
        $no_whitespaces = preg_replace('/\s*,\s*/', ',', filter_var($string, FILTER_SANITIZE_STRING));
        $array = explode(',', $no_whitespaces);
        return $array;
    }
}
if ( !function_exists( 'molongui_sort_array' ) )
{
    function molongui_sort_array($array = array(), $order = 'ASC', $orderby = 'key')
    {
        if (empty($array)) return $array;
        switch ($orderby)
        {
            case 'key':
                ksort($array);
                break;

            default:
                uasort($array, function($item1, $item2) use ($orderby)
                {
                    if ($item1[$orderby] == $item2[$orderby]) return 0;
                    return $item1[$orderby] < $item2[$orderby] ? -1 : 1;
                });
                break;
        }
        if ('desc' === strtolower($order)) $array = array_reverse($array);
        return $array;
    }
}
if ( !function_exists( 'molongui_are_arrays_equal' ) )
{
    function molongui_are_arrays_equal($array1, $array2, $sort = false)
    {
        if ($sort)
        {
            if (!empty($array1)) array_multisort($array1);
            if (!empty($array2)) array_multisort($array2);
        }

        return (serialize($array1) === serialize($array2));
    }
}
if ( !function_exists( 'molongui_restart_plugin' ) )
{
    function molongui_restart_plugin($plugin)
    {
        deactivate_plugins($plugin, false, false);
        $r = activate_plugins($plugin);

        return $r;
    }
}
if ( !function_exists( 'molongui_show_current_query' ) )
{
    function molongui_show_current_query()
    {
        global $wp_query;
        if (!isset($_GET['molongui_show_query'])) return;

        echo '<textarea cols="200" rows="10">';
        print_r($wp_query);
        echo '</textarea>';
    }
}
if ( !function_exists( 'molongui_get_post_types' ) )
{
    function molongui_get_post_types($type = 'all', $output = 'names', $setting = false)
    {
        $wp_post_types     = (($type == 'wp' or $type == 'all') ? get_post_types(array('public' => true, '_builtin' => true), $output) : array());
        $custom_post_types = (($type == 'cpt' or $type == 'all') ? get_post_types(array('public' => true, '_builtin' => false), $output) : array());
        $post_types = array_merge($wp_post_types, $custom_post_types);
        if ($setting)
        {
            $options = array();

            foreach ($post_types as $post_type)
            {
                $options[] = array('id' => $post_type->name, 'label' => $post_type->labels->name);
            }

            return $options;
        }
        return $post_types;
    }
}
if ( !function_exists( 'molongui_supported_post_types' ) )
{
    function molongui_supported_post_types($plugin_id, $type = 'all', $select = false)
    {
        $post_types = $options = array();
        $settings = authorship_get_options();
        if (!isset($settings['post_types'])) return ($select ? $options : $post_types);
        foreach (molongui_get_post_types($type, 'objects', false) as $post_type_name => $post_type_object)
        {
            if (in_array($post_type_name, explode(",", $settings['post_types'])))
            {
                $post_types[] = $post_type_name;
                $options[]    = array('id' => $post_type_name, 'label' => $post_type_object->labels->name, 'singular' => $post_type_object->labels->singular_name);
            }
        }
        return ($select ? $options : $post_types);
    }
}
if ( !function_exists( 'molongui_enabled_post_screens' ) )
{
    function molongui_enabled_post_screens( $plugin_id, $type = 'all')
    {
        $screens = molongui_supported_post_types($plugin_id, $type);
        foreach ($screens as $screen) $screens[] = 'edit-' . $screen;
        return $screens;
    }
}
if ( !function_exists( 'molongui_rand' ) )
{
    function molongui_rand()
    {
        return substr(number_format(time() * wp_rand(), 0, '', ''), 0, 10);
    }
}
if ( !function_exists( 'molongui_let_to_num' ) )
{
    function molongui_let_to_num( $size)
    {
        $l   = substr($size, -1);
        $ret = substr($size, 0, -1);
        switch (strtoupper($l))
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
}
if ( !function_exists( 'molongui_ascii_encode' ) )
{
    function molongui_ascii_encode($input)
    {
        $output = '';
        for ($i = 0; $i < strlen($input); $i++) $output .= '&#' . ord($input[$i]) . ';';
        return $output;
    }
}
if ( !function_exists( 'molongui_get_acronym' ) )
{
    function molongui_get_acronym($words, $length = 3)
    {
        $acronym = '';
        foreach (explode(' ', $words) as $word) $acronym .= mb_substr($word, 0, 1, 'utf-8');

        return strtoupper(mb_substr($acronym, 0, $length));
    }
}
if ( !function_exists( 'molongui_get_base64_svg' ) )
{
    function molongui_get_base64_svg($svg, $base64 = true)
    {
        if ($base64)
        {
            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        }

        return $svg;
    }
}
if ( !function_exists( 'molongui_get_image_sizes' ) )
{
    function molongui_get_image_sizes($type = 'all')
    {
        $image_sizes = array();
        $type = in_array($type, array('all', 'default', 'additional')) ? $type : 'all';
        if (in_array($type, array('all', 'default')))
        {
            $default_image_sizes = get_intermediate_image_sizes();

            foreach ($default_image_sizes as $size)
            {
                $image_sizes[$size]['width']  = intval(get_option("{$size}_size_w"));
                $image_sizes[$size]['height'] = intval(get_option("{$size}_size_h"));
                $image_sizes[$size]['crop']   = get_option("{$size}_crop") ? get_option("{$size}_crop") : false;
            }
        }
        if (in_array($type, array('all', 'additional')))
        {
            global $_wp_additional_image_sizes;

            if (isset($_wp_additional_image_sizes) and count($_wp_additional_image_sizes))
            {
                $image_sizes = array_merge($image_sizes, $_wp_additional_image_sizes);
            }
        }
        return $image_sizes;
    }
}
if ( !function_exists( 'molongui_clean' ) )
{
    function molongui_clean($var)
    {
        if (is_array($var)) return array_map('molongui_clean', $var);
        else return is_scalar($var) ? sanitize_text_field($var) : $var;
    }
}
if ( !function_exists( 'molongui_is_post_type_enabled' ) )
{
    function molongui_is_post_type_enabled($plugin, $post_type = null, $post_types = null)
    {
        if (!$post_type)
        {
            if (is_admin())
            {
                $post_type = authorship_get_post_type();
            } else
            {
                $post_type = get_post_type();
            }
        }
        if (!$post_types)
        {
            $post_types = molongui_supported_post_types($plugin, 'all');
        }

        return (bool)in_array($post_type, $post_types);
    }
}
if ( !function_exists( 'molongui_request_data' ) )
{
    function molongui_request_data($url)
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
        $response = wp_remote_get($url, $args);
        if (is_wp_error($response) or !isset($response) or empty($response))
        {

            $response = 0;
        } // If wp_remote_get worked, convert data before returning it.
        else
        {
            $response = unserialize(wp_remote_retrieve_body($response));
        }
        return $response;
    }
}