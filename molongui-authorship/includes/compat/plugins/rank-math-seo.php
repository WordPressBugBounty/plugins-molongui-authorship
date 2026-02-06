<?php
/*!
 * Compatibility module for seamless integration with Rank Math SEO.
 *
 *   Package : Plugin
 *   Name    : Rank Math SEO
 *   Author  : Rank Math
 *   URI     : https://rankmath.com/
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/compat/plugins
 * @since      4.2.17
 */

namespace Molongui\Authorship;

use Molongui\Authorship\Common\Utils\Debug;
use Molongui\Authorship\Compat\Schema;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class RankMath
{
    use Schema;
    public function __construct()
    {
        add_filter( 'rank_math/json_ld', array( $this, 'override_author_schema_on_posts' ), 99, 2 );
        add_filter( 'rank_math/json_ld', array( $this, 'override_author_schema_on_archives' ), 99, 2 );
    }

public function override_author_archive_title( $title )
{
    if ( is_author() )
    {
        $user_id = get_query_var( 'author' );
        $author  = new Author( (int) $user_id, 'user' );

        if ( ! $author instanceof Author )
        {
            return $title;
        }
        $title = $author->get_display_name() . ', Author at ' . get_bloginfo( 'name' );
    }

    return $title;
}
    public function override_author_schema_on_posts( $data, $jsonld )
    {
        if ( ! is_singular( 'post' ) ) // TODO: añadir otros post_type activos!!!
        {
            return $data;
        }

        $post_id = get_the_ID();
        $authors = $this->get_post_authors_for_schema( $post_id );
        if ( ! $this->should_override_schema_author_data( $authors ) )
        {
            return $data;
        }
        $schema_authors = $this->normalize_authors_for_schema( $authors );

        if ( empty( $schema_authors ) )
        {
            return $data;
        }
        $data = $this->apply_schema_authors_to_graph( $data, $schema_authors );

        return $data;
    }
    public function override_author_schema_on_archives( $data, $jsonld )
    {
        if ( function_exists( 'molongui_is_guest_author' ) && ! molongui_is_guest_author() && is_author() )
        {
            $user_id = get_query_var( 'author' );
            $author  = new Author( (int) $user_id, 'user' );

            if ( ! $author instanceof Author )
            {
                return $data;
            }

            $author_data = $this->build_author_data_from_object( $author );

            if ( empty( $author_data ) )
            {
                return $data;
            }

            $schema_authors = $this->normalize_authors_for_schema( array( $author_data ) );

            if ( empty( $schema_authors ) )
            {
                return $data;
            }

            $data = $this->apply_schema_authors_to_graph( $data, $schema_authors );
        }

        return $data;
    }
    public function fix_author_page_title( $aim, $user, $field, $value )
    {
        if ( is_author() or molongui_is_guest_author() )
        {
            $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
            $fn = 'generate_postdata';
            if ( $key = array_search( $fn, array_column( $dbt, 'function' ) ) )
            {
                $aim = 'info';
            }
        }

        return $aim;
    }
    public function fix_disabled_link( $url, $args )
    {
        if ( '#molongui-disabled-link' !== $args['url'] )
        {
            return $url;
        }
        $fn_1  = 'get_author_posts_url';
        $fn_2  = 'process';
        $class = 'RankMath\Schema\Author';
        $file  = 'seo-by-rank-math/includes/modules/schema/snippets/class-author.php';
        $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );

        if ( empty( $dbt ) )
        {
            return $url;
        }
        if ( $j = array_search( $fn_1, array_column( $dbt, 'function' ) ) )
        {
            if ( $i = array_search( $fn_2, array_column( $dbt, 'function' ) ) )
            {
                if ( isset( $dbt[$i]['class'] ) and $dbt[$i]['class'] == $class )
                {
                    $url = '';
                }
            }
        }

        return $url;
    }
    public function fix_paper_author_url( $link, $original_link, $author_id, $author_nicename )
    {
        $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
        $fn_1  = 'get_author_posts_url';
        $fn_2  = 'canonical';
        $class = 'RankMath\Paper\Author';

        if ( !molongui_is_guest_author() )
        {
            if ( $j = array_search( $fn_1, array_column( $dbt, 'function' ) ) )
            {
                if ( $i = array_search( $fn_2, array_column( $dbt, 'function' ) ) )
                {
                    if ( isset( $dbt[$i]['class'] ) and $dbt[$i]['class'] == $class )
                    {
                        $link = $original_link;
                    }
                }
            }
        }

        return $link;
    }
    public function fix_schema_author_url( $link, $original_link, $author_id, $author_nicename )
    {
        $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
        $fn_1  = 'get_author_posts_url';
        $fn_2  = 'process';
        $class = 'RankMath\Schema\Author';

        if ( !molongui_is_guest_author() )
        {
            if ( $j = array_search( $fn_1, array_column( $dbt, 'function' ) ) )
            {
                if ( $i = array_search( $fn_2, array_column( $dbt, 'function' ) ) )
                {
                    if ( isset( $dbt[$i]['class'] ) and $dbt[$i]['class'] == $class )
                    {
                        $link = $original_link;
                    }
                }
            }
        }

        return $link;
    }

} // class
new RankMath;
