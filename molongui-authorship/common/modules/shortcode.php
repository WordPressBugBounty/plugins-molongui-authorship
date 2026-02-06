<?php
/*!
 * Base class for plugin shortcodes.
 *
 * @author     Molongui
 * @package    Framework
 * @subpackage fw/core/modules
 * @since      3.4.0
 */

namespace Molongui\Authorship\Common\Modules;

use Molongui\Authorship\Common\Utils\Debug;
use Molongui\Authorship\Common\Utils\Helpers;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Shortcode
{
    protected $tag = '';
    protected $attributes;
    public function __construct()
    {
        add_action( 'init', array( $this, 'add_shortcode' ), PHP_INT_MAX );
    }
    public function add_shortcode()
    {
        add_shortcode( 'molongui_' . $this->tag, array( $this, 'do_shortcode' ) );
    }
    public function do_shortcode( $atts )
    {
        if ( $this->is_doing_shortcode() )
        {
            return '';
        }

        $this->start_doing_shortcode();
        Debug::console_log( $atts, "[molongui_{$this->tag}] provided attributes:" );
        $atts = $this->support_valueless_atts( $atts );
        $atts = $this->normalize_legacy_atts( $atts );
        $atts = $this->parse_attributes( $atts );

        Debug::console_log( $atts, "[molongui_{$this->tag}] parsed attributes:" );

        $this->attributes = $atts;

        $output = $this->shortcode( $atts );

        $this->stop_doing_shortcode();
        return Helpers::minify_html( $output );
    }
    protected function shortcode( $atts )
    {
        return '';
    }
    protected function support_valueless_atts( $atts )
    {
        if ( ! is_array( $atts ) )
        {
            return array();
        }

        $normalized = array();

        foreach ( $atts as $key => $value )
        {
            if ( is_int( $key ) )
            {
                if ( is_string( $value ) && $value !== '' )
                {
                    $normalized[ strtolower( $value ) ] = true;
                }
            }
            else
            {
                $normalized[ strtolower( $key ) ] = $value;
            }
        }

        return $normalized;
    }
    protected function normalize_legacy_atts( $atts )
    {
        return $atts;
    }
    protected function parse_attributes( $atts )
    {
        $shortcode_tag = property_exists( $this, 'tag' ) ? $this->tag : '';
        $raw_atts = is_array( $atts ) ? $atts : array();

        $defaults = array();
        return shortcode_atts( $defaults, $raw_atts, $this->tag );
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
    private function start_doing_shortcode()
    {
        add_filter( '_authorship/doing_shortcode', '__return_true' );
        if ( $this->tag )
        {
            add_filter( "_authorship/doing_shortcode/{$this->tag}", '__return_true' );
        }
    }
    private function stop_doing_shortcode()
    {
        remove_filter( '_authorship/doing_shortcode', '__return_true' );
        if ( $this->tag )
        {
            remove_filter( "_authorship/doing_shortcode/{$this->tag}", '__return_true' );
        }
    }
    public function is_doing_shortcode()
    {
        if ( $this->tag )
        {
            return (bool) has_filter( "_authorship/doing_shortcode/{$this->tag}", '__return_true' );
        }
        return (bool) has_filter( '_authorship/doing_shortcode', '__return_true' );
    }

} // class
