<?php
/*!
 * Compatibility module for seamless integration with the Pro theme.
 *
 *   Package : Theme
 *   Name    : Pro
 *   Author  : Themeco
 *   URI     : https://theme.co
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/compat/themes
 * @since      5.2.0
 */

namespace Molongui\Authorship;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Pro
{
    public function __construct()
    {
        add_filter( 'molongui_authorship/add_author_box', array( $this, 'add_author_box_on_templates' ) );
    }
    public function add_author_box_on_templates( $add )
    {
        $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 20 );
        $fn  = 'x_element_render_the_content';
        if ( array_search( $fn, array_column( $dbt, 'function' ) ) )
        {
            if ( is_main_query() )
            {
                if ( is_single() or is_page() )
                {
                    $add = true;
                }
            }
        }

        return $add;
    }

} // class
new Pro;
