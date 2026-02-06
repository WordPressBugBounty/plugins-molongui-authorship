<?php
/*!
 * Compatibility module for seamless integration with Elementor Pro.
 *
 *   Package : Plugin
 *   Name    : Elementor Pro
 *   Author  : Elementor.com
 *   URI     : https://elementor.com/
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/compat/plugins
 * @since      4.2.4
 */

namespace Molongui\Authorship;

use Molongui\Authorship\Common\Utils\Helpers;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class ElementorPro
{
    public function __construct()
    {
        add_filter( '_authorship/get_user_by/aim', array( $this, 'filter_byline_aim' ), 10, 4 );
        add_filter( '_authorship/posts_where', array( $this, 'display_coauthored_in_loop_grid' ) );
        add_filter( 'elementor/query/query_args', array( $this, 'fix_posts_by_author_for_posts_widget' ), 10, 2 );
        add_filter( 'molongui_authorship/co_authors_separator'     , array( Helpers::class, 'space_to_nbsp' ) );
        add_filter( 'molongui_authorship/co_authors_last_separator', array( Helpers::class, 'space_to_nbsp' ) );
    }
    public function filter_byline_aim( $aim, $user, $field, $value )
    {
        $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
        $class = 'ElementorPro\Modules\Posts\Skins\Skin_Base';
        $fn    = 'render';

        if ( $key = array_search( $fn, array_column( $dbt, 'function' ) )
             and
             isset( $dbt[$key]['class'] ) and ( $dbt[$key]['class'] == $class ) )
        {
            $aim = 'byline';
        }

        return $aim;
    }
    public function display_coauthored_in_loop_grid( $default )
    {
        $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 14 );
        $class = 'ElementorPro\Modules\LoopBuilder\Widgets\Base';
        $fn    = 'query_posts';

        foreach ( $dbt as $trace )
        {
            if ( isset( $trace['class'], $trace['function'] )
                 and
                 $trace['class'] === $class
                 and
                 $trace['function'] === $fn )
            {
                return true;
            }
        }

        return $default;
    }
    public function fix_posts_by_author_for_posts_widget( $query_args, $widget )
    {
        if ( 'posts' === $widget->get_name() )
        {
            if ( !empty( $query_args['author__in'] ) and is_array( $query_args['author__in'] ) and !empty( $query_args['author__in'][0] ) )
            {
                $author_id = $query_args['author__in'][0];
                $query_args['author__in'] = '';
                $query_args['meta_query'] = array
                (
                    array
                    (
                        'key'     => '_molongui_author',
                        'value'   => 'user-'.$author_id,
                        'compare' => '==',
                    ),
                );
            }
        }

        return $query_args;
    }

} // class
new ElementorPro;
