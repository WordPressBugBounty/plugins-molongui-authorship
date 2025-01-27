<?php
/*!
 * Base class for plugin shortcodes.
 *
 * @author     Molongui
 * @package    Framework
 * @subpackage fw/core/modules
 * @since      3.4.0
 * @version    3.4.0
 */

namespace Molongui\Authorship\Common\Modules;

use Molongui\Authorship\Common\Utils\Debug;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Shortcode
{
    protected $tag = '';
    public function __construct()
    {
        add_action( 'init', array( $this, 'add_shortcode' ), PHP_INT_MAX );
    }
    public function add_shortcode()
    {
        add_shortcode( 'molongui_' . $this->tag, array( $this, 'shortcode' ) );
    }
    public function shortcode( $atts )
    {
        if ( !empty( $atts ) and is_array( $atts ) )
        {
            $original_atts = $atts;
            $atts = $this->parse_attributes( $atts );
        }

        return '';
    }
    protected function parse_attributes( $atts )
    {
        $defaults = array();
        return shortcode_atts(
            $defaults,
            $atts,
            'shortcode' // Placeholder shortcode tag, child classes should specify this.
        );
    }
    protected function doing_it_wrong( $text, $link = '', $log = '', $log_value = '' )
    {
        if ( empty( $text ) )
        {
            return '';
        }
        if ( empty( $log ) )
        {
            $log = $text;
        }
        Debug::console_log( $log_value, $log );

        $warning = '';
        if ( is_user_logged_in() and current_user_can( 'edit_posts' ) )
        {
            $message = $text;
            if ( !empty( $link ) )
            {
                /*! // translators: %1$s and %2$s are HTML tags for a link. */
                $message .= ' ' . sprintf(
                    __( 'Learn more %1$shere%2$s.', 'molongui-authorship' ),
                    '<a href="' . $link . '" target="_blank" rel="noopener noreferrer">',
                    '</a>'
                );
            }

            $styles  = 'display: block; margin: 2em 0; padding: 10px; border: 2px dashed red; background: #ff000017; color: darkred;';
            $warning = '<code style="'.$styles.'">' . $message . '</code>';
        }

        return $warning;
    }

} // class
