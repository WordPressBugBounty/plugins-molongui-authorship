<?php
/*!
 * Compatibility module for seamless integration with Happy Elementor Addons Pro.
 *
 *   Package : Plugin
 *   Name    : Happy Elementor Addons Pro
 *   Author  : Leevio
 *   URI     : ttps://happyaddons.com/
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/compat/plugins
 * @since      5.0.xx
 */

namespace Molongui\Authorship;

use Molongui\Authorship\Common\Utils\Helpers;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class HappyElementorAddonsPro
{
    public function __construct()
    {
        add_filter( '_authorship/get_avatar_data/filter/author', array( $this, 'fix_post_grid_avatar' ), 10, 3 );
    }
    public function fix_post_grid_avatar( $author, $id_or_email, $dbt )
    {
        $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
        $fn    = 'new_render_avater';
        $class = 'Happy_Addons_Pro\Widget\Post_Grid_New';

        if ( $i = array_search( $fn, array_column( $dbt, 'function' ) )
             and
             isset( $dbt[$i]['class'] ) and ( $dbt[$i]['class'] === $class ) )
        {
            $post_id = Post::get_id();
            if ( !empty( $post_id ) )
            {
                $main_author = Post::get_main_author( $post_id );

                if ( !empty( $main_author ) )
                {
                    $author->id   = $main_author->id;
                    $author->type = $main_author->type;
                }
            }
        }

        return $author;
    }

} // class
new HappyElementorAddonsPro;
