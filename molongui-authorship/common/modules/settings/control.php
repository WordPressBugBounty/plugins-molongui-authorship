<?php

namespace Molongui\Authorship\Common\Modules\Settings;

use Molongui\Authorship\Common\Utils\Debug;
use Molongui\Authorship\Common\Utils\WP;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Control
{
    public $_saved;
    public $_tab;
    public $_group;
    public $_data;
    public $_type;
    public $_id;
    public $_default;
    public $_value;
    public $_class;
    public $_desc;
    public $_step;
    public $_name;
    public $_option_cls;
    public $_options;
    public $_min;
    public $_max;
    public $_args;
    public $_prefix;
    public $_suffix;
    public $_source;
    public $_placeholder;
    public $_editor;
    public $_height;
    public $_link;
    public $_callback;
    public $_upload_title;
    public $_upload_button;
    public $_options_prefix;

    public $allowed_html;
    public function __construct( $data, $group = '', $key = '', $prefix = 'molongui' )
    {
        if ( empty( $data ) )
        {
            return;
        }
        if ( empty( $key ) ) $key = MOLONGUI_AUTHORSHIP_PREFIX.'_options';
        $this->_saved = (array) get_option( $key, array() );
        $this->_group			= $group;
        $this->_data 			= $data;
        $this->_type			= $data['type'];

        $this->_id				= ( isset( $data['id'] ) ) ? $data['id'] : null;
        $this->_default 		= ( isset( $data['default'] ) ) ? $data['default'] : null;
        $this->_value 			= ( isset( $this->_id ) and isset( $this->_saved[$this->_id] ) ) ? $this->_saved[$this->_id] : $this->_default;

        $this->_class 			= ( isset( $data['class'] ) ) ? $data['class'] : null;
        $this->_desc 			= ( isset( $data['desc'] ) ) ? '<span class="description">'.$data['desc'].'</span>' : null;
        $this->_desc 		   .= ( isset( $data['alert'] ) ) ? '<span class="description alert">'.$data['alert'].'</span>' : null;
        $this->_step 		    = ( isset( $data['step'] ) ) ? $data['step'] : 1;
        $this->_name 			= ( isset( $data['name'] ) ) ? esc_html( $data['name'] ) : null;
        $this->_option_cls 		= ( isset( $data['option_cls'] ) ) ? $data['option_cls'] : null;
        $this->_options 		= ( isset( $data['options'] ) ) ? $data['options'] : null;
        $this->_min 			= ( isset( $data['min'] ) ) ? $data['min'] : null;
        $this->_max 			= ( isset( $data['max'] ) ) ? $data['max'] : null;
        $this->_args			= ( isset( $data['args'] ) ) ? $data['args'] : array();
        $this->_prefix 			= ( isset( $data['prefix'] ) ) ? $data['prefix'] : null;
        $this->_suffix 			= ( isset( $data['suffix'] ) ) ? $data['suffix'] : null;
        $this->_source 			= ( isset( $data['source'] ) ) ? $data['source'] : null;
        $this->_placeholder 	= ( isset( $data['placeholder'] ) ) ? $data['placeholder'] : null;
        $this->_editor 			= ( isset( $data['editor'] ) ) ? $data['editor_settings'] : null;
        $this->_height 			= ( isset( $data['height'] ) ) ? $data['height'] : null;
        $this->_link            = ( isset( $data['link'] ) ) ? $data['link'] : '';
        $this->_callback        = ( isset( $data['callback'] ) ) ? $data['callback'] : '';
        $this->_upload_title 	= __( "Insert ", 'molongui-authorship' ) . $this->_name;
        $this->_upload_button	= __( "Choose as ", 'molongui-authorship' ) . $this->_name;
        $this->_options_prefix	= $prefix;

        $this->allowed_html = array
        (
            'div'    => array
            (
                'style' => array(),
                'class' => array(),
            ),
            'span'   => array
            (
                'style' => array(),
                'class' => array(),
            ),
            'p'      => array
            (
                'style' => array(),
                'class' => array(),
            ),
            'ol'   => array
            (
                'style' => array(),
                'class' => array(),
            ),
            'ul'   => array
            (
                'style' => array(),
                'class' => array(),
            ),
            'li'   => array
            (
                'style' => array(),
                'class' => array(),
            ),
            'code'   => array
            (
                'style' => array(),
                'class' => array(),
            ),
            'br'     => array(),
            'i'      => array(),
            'em'     => array(),
            'strong' => array(),
            'b'      => array(),
            'a'      => array
            (
                'href'  => array(),
                'title' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'input'  => array
            (
                'type'        => array(),
                'id'          => array(),
                'name'        => array(),
                'value'       => array(),
                'style'       => array(),
                'class'       => array(),
                'placeholder' => array(),
            ),
        );
    }
    private function _help()
    {
        $help = '';

        if ( !empty( $this->_data['help'] ) )
        {
            if ( isset( $this->_data['help']['link'] ) and is_array( $this->_data['help']['link'] ) )
            {
                $label  = empty( $this->_data['help']['link']['label']  ) ? __( "Learn more", 'molongui-authorship' ) : $this->_data['help']['link']['label'];
                $target = empty( $this->_data['help']['link']['target'] ) ? 'internal' : $this->_data['help']['link']['target'];
                $url    = empty( $this->_data['help']['link']['url']    ) ? '' : $this->_data['help']['link']['url'];

                if ( 'external' === $target )
                {
                    $url .= '?source=settings-'.str_replace( '_', '-', $this->_data['id'] ).'&amp;site='.WP::get_domain();
                }
            }
            elseif ( isset( $this->_data['help']['link'] ) )
            {
                $label = __( "Learn more", 'molongui-authorship' );
                $url   = empty( $this->_data['help']['link'] ) ? '' : $this->_data['help']['link'] . '?source=settings-'.str_replace( '_', '-', $this->_data['id'] ).'&amp;site='.WP::get_domain();
            }

            ob_start();
            ?>
            <div class="m-support-info">
                <button class="m-info-popup m-info-popup-button">
                    <svg class="gridicon gridicons-info-outline needs-offset" height="18" width="18" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <g><path d="M13 9h-2V7h2v2zm0 2h-2v6h2v-6zm-1-7c-4.41 0-8 3.59-8 8s3.59 8 8 8 8-3.59 8-8-3.59-8-8-8m0-2c5.523 0 10 4.477 10 10s-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2z"></path></g>
                    </svg>
                    <span class="screen-reader-text"><?php echo !empty( $label ) ? wp_kses( $label, $this->allowed_html ) : wp_kses( __( "Option help", 'molongui-authorship' ), $this->allowed_html ); ?></span>
                </button>
            </div>
            <div class="ui popup mini left center transition">
                <div class="m-support-info-description">
                    <?php echo wp_kses( ( is_array( $this->_data['help'] ) ? $this->_data['help']['text'] : $this->_data['help'] ), $this->allowed_html ); ?>
                </div>
                <?php if ( !empty( $url ) ) : ?>
                    <div class="m-support-info-link">
                        <a href="<?php echo esc_url( $url ); ?>" target="_blank">
                            <?php echo wp_kses( $label, $this->allowed_html ); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <?php
            $help .= ob_get_clean();
        }

        return $help;
    }
    private function prepend()
    {
        $help  = $this->_help();
        $group = empty( $this->_group ) ? '' : ' data-m-group="'.$this->_group.'"';
        $deps  = empty( $this->_data['deps'] ) ? ' data-m-deps="1"' : ' data-deps="'.$this->_data['deps'].'" data-m-deps="1"';
        $hide  = empty( $this->_data['advanced'] ) ? '' : ' data-m-option="advanced" data-m-hide="1" style="display: none;"';

        $html  = '<div class="m-card '. ( empty( $this->_data['class'] ) ? '' : $this->_data['class'] ).'"'. $group . $deps . $hide . ' >';
        $html .= $help;
        $html .= !empty( $this->_data['title'] ) ? '<div class="m-option-title">'.wp_kses( $this->_data['title'], $this->allowed_html ).'</div>'  : '';
        $html .= !empty( $this->_data['desc']  ) ? '<p class="m-option-description">'.wp_kses( $this->_data['desc'], $this->allowed_html ).'</p>' : '';

        $class = empty( $this->_data['notice'] ) ? '' : ' has-notice';
        $html .= '<div class="m-option' . $class . '">';

        return $html;
    }
    private function append()
    {
        $html  = '';
        $html .= empty( $this->_data['notice'] ) ? '' : '<div class="m-option-notice">'.wp_kses( $this->_data['notice'], $this->allowed_html ).'</div>';
        $html .= '</div>'; // Close .m-option
        $html .= '</div>'; // Close .m-card

        return $html;
    }
    public function __toString()
    {
        switch ( $this->_type )
        {
            case 'notice':          return $this->notice();          break;
            case 'title':           return $this->title();           break;
            case 'text':            return $this->text();            break;
            case 'inline-text':     return $this->inline_text();     break;
            case 'color':           return $this->color();           break;
            case 'textarea':        return $this->textarea();        break;
            case 'radio':           return $this->radio();           break;
            case 'radio-text':      return $this->radio_text();      break;
            case 'number':          return $this->number();          break;
            case 'inline-number':   return $this->inline_number();   break;
            case 'button':          return $this->button();          break;
            case 'export':          return $this->export();          break;
            case 'header':          return $this->header();          break;
            case 'link':            return $this->link();            break;
            case 'toggle':          return $this->toggle();          break;
            case 'toggle-group':    return $this->toggle_group();    break;
            case 'dropdown':        return $this->dropdown();        break;
            case 'inline-dropdown': return $this->inline_dropdown(); break;
            case 'banner':          return $this->banner();          break;
            case 'callback':        return $this->callback();        break;
            case 'select_wp_page':  return $this->select_wp_page();  break;
            case 'unveil':          return $this->unveil();          break;
            default	:               return '';
        }
    }
    private function title()
    {
        return '<h2 class="m-section-title">' . esc_html( $this->_data['label'] ) . '</h2>';
    }
    private function header()
    {
        $output = '';

        ob_start();
        ?>
        <div class="m-card m-card-header <?php echo ( empty( $this->_data['class'] ) ? '' : esc_attr( $this->_data['class'] ) ); ?>" <?php echo empty( $this->_data['id'] ) ? '' : 'id="'.esc_attr( $this->_data['id'] ).'"'; ?> <?php echo ( empty ( $this->_data['deps'] ) ? '' : 'data-deps="'.esc_attr( $this->_data['deps'] ).'"' ); ?>>
            <div class="m-card-header__label">
                <span class="m-card-header__label-text">
                    <?php echo esc_html( $this->_data['label'] ); ?>
                </span>
            </div>
            <?php if ( !empty( $this->_data['buttons'] ) ) : ?>
                <div class="m-card-header__actions">
                    <?php foreach ( $this->_data['buttons'] as $button ) :
                        if ( !$button['display'] ) continue;
                        switch ( $button['type'] ) :
                            case 'input': ?>
                                <input type="file" <?php echo empty( $button['id'] ) ? '' : 'id="'.esc_attr( $button['id'] ).'" name="'.esc_attr( $button['id'] ).'"'; ?> class="m-file-upload" accept="<?php echo esc_attr( $button['accept'] ); ?>" data-multiple-caption="{count} files selected" <?php echo ( $button['multi'] ? 'multiple' : '' ); ?> />
                                <label for="<?php echo empty( $button['id'] ) ? '' : esc_attr( $button['id'] ); ?>" class="m-button is-compact <?php echo esc_attr( $button['class'] ); ?>">
                                    <?php echo esc_html( $button['label'] ); ?>
                                </label>
                                <?php break; ?>
                            <?php case 'download': ?>
                            <button type="submit" <?php echo empty( $button['id'] ) ? '' : 'id="'.esc_attr( $button['id'] ).'"'; ?> <?php echo empty( $button['disabled'] ) ? '' : 'disabled=""'; ?> class="m-button is-compact <?php echo esc_attr( $button['class'] ); ?>" title="<?php echo esc_attr( $button['title'] ); ?>">
                                <?php echo esc_html( $button['label'] ); ?>
                            </button>
                            <?php break; ?>
                        <?php case 'action': ?>
                            <button type="submit" <?php echo empty( $button['id'] ) ? '' : 'id="'.esc_attr( $button['id'] ).'"'; ?> <?php echo empty( $button['disabled'] ) ? '' : 'disabled=""'; ?> class="m-button is-compact <?php echo esc_attr( $button['class'] ); ?>" title="<?php echo esc_attr( $button['title'] ); ?>">
                                <?php echo esc_html( $button['label'] ); ?>
                            </button>
                            <?php break; ?>
                        <?php case 'link': ?>
                            <a <?php echo empty( $button['id'] ) ? '' : 'id="'.esc_attr( $button['id'] ).'"'; ?> class="m-button is-secondary is-compact <?php echo esc_attr( $button['class'] ); ?>" href="<?php echo esc_url( $button['href'] ); ?>" target="<?php echo empty( $button['target'] ) ? '_self' : esc_attr( $button['target'] ); ?>" title="<?php echo esc_attr( $button['title'] ); ?>" <?php echo empty( $button['disabled'] ) ? '' : 'disabled=""'; ?>>
                                <?php echo esc_html( $button['label'] ); ?>
                            </a>
                            <?php break; ?>
                        <?php case 'advanced': ?>
                            <button type="" <?php echo empty( $button['id'] ) ? '' : 'id="'.esc_attr( $button['id'] ).'"'; ?> <?php echo empty( $button['disabled'] ) ? '' : 'disabled=""'; ?> class="m-button m-button-advanced is-secondary is-compact <?php echo esc_attr( $button['class'] ); ?>" title="<?php echo esc_attr( $button['title'] ); ?>" data-m-target="<?php echo esc_attr( str_replace( '_header', '', $this->_data['id'] ) ); ?>" data-m-state="hidding">
                                <?php echo esc_html( $button['label'] ); ?>
                            </button>
                            <?php break; ?>
                        <?php case 'save':default: ?>
                            <button type="submit" <?php echo empty( $button['id'] ) ? '' : 'id="'.esc_attr( $button['id'] ).'"'; ?> <?php echo empty( $button['disabled'] ) ? '' : 'disabled=""'; ?> class="m-button m-button-save is-compact <?php echo esc_attr( $button['class'] ); ?>" title="<?php echo esc_attr( $button['title'] ); ?>">
                                <?php echo esc_html( $button['label'] ); ?>
                            </button>
                            <?php break; ?>
                        <?php endswitch; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        $output .= ob_get_clean();

        return $output;
    }
    private function link()
    {
        $output = '';
        ob_start();
        ?>
        <a class="m-card is-card-link is-compact <?php echo ( empty( $this->_data['class'] ) ? '' : esc_attr( $this->_data['class'] ) ); ?>" <?php echo empty( $this->_data['id'] ) ? '' : 'id="'.esc_attr( $this->_data['id'] ).'"'; ?> href="<?php echo esc_url( $this->_data['href'] ); ?>" target="<?php echo esc_attr( $this->_data['target'] ); ?>" title="<?php echo ( empty( $this->_data['help'] ) ? '' : esc_attr( $this->_data['help'] ) ); ?>" <?php echo ( empty ( $this->_data['deps'] ) ? '' : 'data-deps="'.esc_attr( $this->_data['deps'] ).'"' ); ?>>
            <svg class="gridicon gridicons-external m-card__link-indicator" height="24" width="24" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><g><path d="M19 13v6c0 1.105-.895 2-2 2H5c-1.105 0-2-.895-2-2V7c0-1.105.895-2 2-2h6v2H5v12h12v-6h2zM13 3v2h4.586l-7.793 7.793 1.414 1.414L19 6.414V11h2V3h-8z"></path></g></svg>
            <?php echo esc_html( $this->_data['label'] ); ?>
        </a>
        <?php
        $output .= ob_get_clean();

        return $output;
    }
    private function banner()
    {
        $output = '';
        $group  = empty( $this->_group )            ? '' : ' data-m-group="'.$this->_group.'"';
        $deps   = empty( $this->_data['deps'] )     ? ' data-m-deps="1"' : ' data-deps="'.$this->_data['deps'].'" data-m-deps="1"';
        $hide   = empty( $this->_data['advanced'] ) ? '' : ' data-m-option="advanced" data-m-hide="1" style="display: none;"';
        $title  = empty( $this->_data['title'] )    ? $this->_data['label'] : $this->_data['title'];
        $desc   = empty( $this->_data['desc'] )     ? false : true;
        $button = empty( $this->_data['button'] )   ? false : true;
        $badge  = empty( $this->_data['badge'] )    ? __( "PRO", 'molongui-authorship' ) : $this->_data['badge'];

        ob_start();
        ?>
        <div class="m-card m-banner <?php echo ( empty( $this->_data['class'] ) ? '' : esc_attr( $this->_data['class'] ) ); ?>"
             id="<?php echo esc_attr( $this->_data['id'].'_ad' ); ?>"
            <?php echo esc_attr( $group ) . esc_attr( $deps ) . esc_attr( $hide ); ?>>
            <div class="m-banner__icon-plan">
                <div class="m-plan-icon">
                    <div class="m-plan-icon__text">
                        <?php echo esc_html( $badge ); ?>
                    </div>
                </div>
            </div>
            <div class="m-banner__content">
                <div class="m-banner__info">
                    <div class="m-banner__title">
                        <?php echo wp_kses( $title, $this->allowed_html ); ?>
                    </div>
                    <?php if ( $desc ) : ?>
                        <div class="m-banner__description">
                            <?php echo wp_kses( $this->_data['desc'], $this->allowed_html ); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ( $button ) : ?>
                    <div class="m-banner__action">
                        <a href="<?php echo esc_url( $this->_data['button']['href'] ); ?>?source=<?php echo esc_attr( 'settings-'.str_replace( '_', '-', $this->_data['id'] ) ); ?>&amp;site=<?php echo esc_url( WP::get_domain() ); ?>" target="<?php echo esc_attr( $this->_data['button']['target'] ); ?>" type="button" class="m-button is-compact is-primary <?php echo esc_attr( $this->_data['button']['class'] ); ?>" <?php echo !empty( $this->_data['button']['title'] ) ? 'title="'.esc_attr( $this->_data['button']['title'] ).'"' : ''; ?>>
                            <?php echo esc_html( $this->_data['button']['label'] ); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        $output .= ob_get_clean();

        return $output;
    }
    private function callback()
    {
        $output = $this->prepend();
        ob_start();
        echo call_user_func( $this->_callback );
        $output .= ob_get_clean();
        $output .= $this->append();
        return $output;
    }
    private function notice()
    {
        $title = empty( $this->_data['title'] ) ? '' : $this->_data['title'];
        $title = empty( $this->_data['link']  ) ? $title : '<a href="'.esc_url( $this->_data['link'] ).'" target="_blank">'.$this->_data['title'].'</a>';
        $help  = $this->_help();

        $output  = '';
        $output .= '<div class="m-card '. ( empty( $this->_data['class'] ) ? '' : esc_attr( $this->_data['class'] ) ) . '"' . ( empty( $this->_data['id'] ) ? '' : 'id="'.esc_attr( $this->_data['id'] ).'"' ) . '>';
        $output .= $help;
        $output .= !empty( $this->_data['title'] ) ? '<div class="m-option-title">'.wp_kses( $title, $this->allowed_html ).'</div>'  : '';
        $output .= !empty( $this->_data['desc']  ) ? '<p class="m-option-description">'.wp_kses( $this->_data['desc'], $this->allowed_html ).'</p>' : '';
        $output .= '</div>';

        return $output;
    }
    private function toggle()
    {
        $output  = $this->prepend();

        $output .= '<label for="'.esc_attr( $this->_id ).'" class="custom-switch m-toggle '.esc_attr( $this->_data['class'] ).'">';
        $output .= '<input type="checkbox" class="custom-switch-input" id="'.esc_attr( $this->_id ).'" name="'.esc_attr( $this->_id ).'" '.checked( $this->_value, true, false ).'>';
        $output .= '<span class="custom-switch-indicator"></span>';
        $output .= '<span class="custom-switch-description">'.wp_kses( $this->_data['label'], $this->allowed_html ).'</span>';
        $output .= '</label>';

        $output .= $this->append();

        return $output;
    }
    private function toggle_group()
    {
        $output  = $this->prepend();
        $output .= '<div class="m-toggle-group">';

        foreach( $this->_data['toggles'] as $toggle )
        {
            $value = ( ( isset( $toggle['id'] ) and isset( $this->_saved[$toggle['id']] ) ) ? $this->_saved[$toggle['id']] : $toggle['default'] );

            $output .= '<label for="'.esc_attr( $toggle['id'] ).'" class="custom-switch m-toggle">';
            $output .= '<input type="checkbox" class="custom-switch-input" id="'.esc_attr( $toggle['id'] ).'" name="'.esc_attr( $toggle['id'] ).'" '.checked( $value, true, false ).'>';
            $output .= '<span class="custom-switch-indicator"></span>';
            $output .= '<span class="custom-switch-description">'.wp_kses( $this->_data['label'], $this->allowed_html ).'</span>';
            $output .= '</label>';
        }

        $output .= '</div>';
        $output .= $this->append();

        return $output;
    }
    private function dropdown()
    {
        $value  = $this->_value;
        $multi  = empty( $this->_data['atts']['multi']  ) ? '' : 'multiple';
        $search = empty( $this->_data['atts']['search'] ) ? '' : 'search';

        $output = $this->prepend();
        ob_start();
        ?>
        <div class="ui dropdown selection fluid <?php echo esc_attr( $multi ); ?> <?php echo esc_attr( $search ); ?> <?php echo esc_attr( $this->_data['class'] ); ?>">
            <input type="hidden" id="<?php echo esc_attr( $this->_id ); ?>" name="<?php echo esc_attr( $this->_id ); ?>" value="<?php echo esc_attr( $value ); ?>">
            <i class="dropdown icon"></i>
            <div class="text default"><?php echo wp_kses( $this->_data['default'], $this->allowed_html ); ?></div>
            <div class="menu">
                <!--
                <div class="ui icon search input">
                    <i class="search icon"></i>
                    <input type="text" placeholder="Search tags...">
                </div>
                <div class="divider"></div>-->
                <?php foreach( $this->_data['options'] as $option ) : ?>
                    <div class="item <?php echo ( !empty( $option['disabled'] ) ? 'disabled' : '' ); ?>" data-value="<?php echo esc_attr( $option['id'] ); ?>">
                        <?php if ( !empty( $option['icon'] ) ) : ?><i class="<?php echo esc_html( $option['icon'] ); ?>"></i><?php endif; ?>
                        <?php if ( !empty( $option['disabled'] ) ) : ?>
                            <span class="description is-pro"><?php esc_html_e( "PRO", 'molongui-authorship' ); ?></span>
                            <span class="text"><?php echo wp_kses( $option['label'], $this->allowed_html ); ?></span>
                        <?php else : ?>
                            <?php echo wp_kses( $option['label'], $this->allowed_html ); ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        $output .= ob_get_clean();
        $output .= $this->append();

        return $output;
    }
    private function inline_dropdown()
    {
        $saved = $this->_value;
        if ( !empty( $saved ) and !empty( $this->_data['options'][$saved]['label'] ) )
        {
            $value = $this->_data['options'][$saved]['label'];
        }
        else
        {
            if ( !empty( $this->_data['default'] ) and isset( $this->_data['options'][$this->_data['default']] ) )
            {
                $value = $this->_data['options'][$this->_data['default']]['label'];
            }
            else
            {
                $value = $this->_data['options'][array_keys( $this->_data['options'] )[0]]['label'];
            }
        }
        $tmp = explode( '{input}', $this->_data['label'] );
        foreach ( $tmp as $key => $part ) if ( !empty( $part ) ) $tmp[$key] = '<label class="label-inline-dropdown" for="'.esc_attr( $this->_id ).'">'.wp_kses( $part, $this->allowed_html ).'</label>';
        $label = $tmp[0].'{input}'.$tmp[1];

        ob_start();
        ?>
        <div class="ui dropdown inline <?php echo esc_attr( $this->_data['class'] ); ?>">
            <input type="text" id="<?php echo esc_attr( $this->_id ); ?>" name="<?php echo esc_attr( $this->_id ); ?>" value="<?php echo esc_attr( $saved ); ?>">
            <div class="text"><?php echo wp_kses( $value, $this->allowed_html ); ?></div>
            <i class="dropdown icon"></i>
            <div class="menu transition hidden">
                <?php foreach( $this->_data['options'] as $id => $option ) : ?>
                    <div class="item <?php echo ( $saved === $id ? 'active' : '' ); ?> <?php echo ( !empty( $option['disabled'] ) ? 'disabled' : '' ); ?>" data-text="<?php echo esc_attr( $option['label'] ); ?>" data-value="<?php echo esc_attr( $id ); ?>">
                        <?php if ( !empty( $option['icon'] ) ) : ?><i class="<?php echo esc_attr( $option['icon'] ); ?>"></i><?php endif; ?>
                        <?php if ( !empty( $option['disabled'] ) ) : ?>
                            <span class="description is-pro"><?php esc_html_e( "PRO", 'molongui-authorship' ); ?></span>
                            <span class="text"><?php echo wp_kses( $option['label'], $this->allowed_html ); ?></span>
                        <?php else : ?>
                            <?php echo wp_kses( $option['label'], $this->allowed_html ); ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php

        $inline  = ob_get_clean();
        $output  = $this->prepend();
        $output .= str_replace( '{input}', $inline, $label );
        $output .= $this->append();

        return $output;
    }
    private function select_wp_page()
    {
        $args = array
        (
            'child_of'    => 0,
            'sort_order'  => 'ASC',
            'sort_column' => 'post_title',
            'authors'     => '',
            'parent'      => -1,
            'number'      => 0,
            'offset'      => 0,
            'post_type'   => 'page',
            'post_status' => 'publish',
        );
        $wp_pages = get_pages( $args );
        $options = array();
        foreach ( $wp_pages as $wp_page )
        {
            $options[$wp_page->ID]['label'] = $wp_page->post_title;
        }
        $this->_data['options'] = $options;
        $this->_data['class']   = 'search'; // Add a scroll if more than 8 items to list.
        return $this->inline_dropdown();
    }
    private function radio()
    {
        $output = $this->prepend();
        ob_start();
        ?>
        <div class="custom-controls-stacked">
            <?php foreach( $this->_data['options'] as $key => $option ) : ?>
                <label class="custom-control custom-radio">
                    <input type="radio" class="custom-control-input" id="<?php echo esc_attr( $this->_id . '_' . $key ); ?>" name="<?php echo esc_attr( $this->_id ); ?>" data-id="<?php echo esc_attr( $this->_id ); ?>" value="<?php echo esc_attr( $option['value'] ); ?>" <?php checked( $this->_value, $option['value'], true ); ?>>
                    <div class="custom-control-label"><?php echo wp_kses( $option['label'], $this->allowed_html ); ?></div>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
        $output .= ob_get_clean();
        $output .= $this->append();

        return $output;
    }
    private function radio_text()
    {
        $output = $this->prepend();

        ob_start();
        ?>
        <div class="selectgroup <?php echo esc_attr( $this->_id ); ?>">
            <?php foreach( $this->_data['options'] as $value => $label ) : ?>
                <label class="selectgroup-item" for="<?php echo esc_attr( $this->_id.'_'.$value ); ?>">
                    <input type="radio" id="<?php echo esc_attr( $this->_id.'_'.$value ); ?>" name="<?php echo esc_attr( $this->_id ); ?>" data-id="<?php echo esc_attr( $this->_id ); ?>" value="<?php echo esc_attr( $value ); ?>" class="selectgroup-input" <?php checked( $this->_value, $value, true ); ?>>
                    <span class="selectgroup-button"><?php echo wp_kses( $label, $this->allowed_html ); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
        $output .= ob_get_clean();
        $output .= $this->append();

        return $output;
    }
    private function number()
    {
        $output = $this->prepend();
        ob_start();
        ?>
        <div class="m-number <?php echo ( empty ( $this->_data['class'] ) ? '' : esc_attr( $this->_data['class'] ) ); ?>">
            <label class="" for="<?php echo esc_attr( $this->_id ); ?>">
                <?php echo wp_kses( $this->_data['label'], $this->allowed_html ); ?>
            </label>
            <input type="number" id="<?php echo esc_attr( $this->_id ); ?>" name="<?php echo esc_attr( $this->_id ); ?>" value="<?php echo esc_attr( $this->_value ); ?>" class="" min="<?php echo esc_attr( $this->_min ); ?>" max="<?php echo esc_attr( $this->_max ); ?>" placeholder="<?php echo esc_attr( $this->_placeholder ); ?>">
        </div>
        <?php
        $output .= ob_get_clean();
        $output .= $this->append();

        return $output;
    }
    private function inline_number()
    {
        $output = $this->prepend();

        $input  = '<input type="number" id="'. $this->_id .'" name="'. $this->_id .'" value="'. esc_attr( $this->_value ) .'" class="" placeholder="'.$this->_placeholder.'">';
        $inline = str_replace( '{input}', $input, $this->_data['label'] );

        ob_start();
        ?>
        <div class="m-inline-number <?php echo ( empty ( $this->_data['class'] ) ? '' : esc_attr( $this->_data['class'] ) ); ?>">
            <label class="" for="<?php echo esc_attr( $this->_id ); ?>">
                <?php echo wp_kses( $inline, $this->allowed_html ); ?>
            </label>
        </div>
        <?php
        $output .= ob_get_clean();
        $output .= $this->append();

        return $output;
    }
    private function text()
    {
        $output = $this->prepend();
        ob_start();
        ?>
        <div class="m-text">
            <label class="" for="<?php echo esc_attr( $this->_id ); ?>">
                <?php echo wp_kses( $this->_data['label'], $this->allowed_html ); ?>
            </label>
            <input type="text" id="<?php echo esc_attr( $this->_id ); ?>" name="<?php echo esc_attr( $this->_id ); ?>" value="<?php echo esc_attr( $this->_value ); ?>" class="" placeholder="<?php echo esc_attr( $this->_placeholder ); ?>">

        </div>
        <?php
        $output .= ob_get_clean();
        $output .= $this->append();

        return $output;
    }
    private function inline_text()
    {
        $output = $this->prepend();

        $input  = '<input type="text" id="'. $this->_id .'" name="'. $this->_id .'" value="'. esc_attr( $this->_value ) .'" class="" placeholder="'.$this->_placeholder.'">';
        $inline = str_replace( '{input}', $input, $this->_data['label'] );

        ob_start();
        ?>
        <div class="m-inline-text <?php echo ( empty ( $this->_data['class'] ) ? '' : esc_attr( $this->_data['class'] ) ); ?>">
            <label class="" for="<?php echo esc_attr( $this->_id ); ?>">
                <?php echo wp_kses( $inline, $this->allowed_html ); ?>
            </label>
        </div>
        <?php
        $output .= ob_get_clean();
        $output .= $this->append();

        return $output;
    }
    private function color()
    {
        $output = $this->prepend();
        ob_start();
        ?>
        <div class="m-color <?php echo ( empty ( $this->_data['class'] ) ? '' : esc_attr( $this->_data['class'] ) ); ?>">
            <label class="" for="<?php echo esc_attr( $this->_id ); ?>">
                <?php echo wp_kses( $this->_data['label'], $this->allowed_html ); ?>
            </label>
            <input type="text" class="colorpicker" id="<?php echo esc_attr( $this->_id ); ?>" name="<?php echo esc_attr( $this->_id ); ?>" value="<?php echo esc_attr( $this->_value ); ?>">

        </div>
        <?php
        $output .= ob_get_clean();
        $output .= $this->append();

        return $output;
    }
    private function textarea()
    {
        $output = $this->prepend();
        ob_start();
        ?>
        <div class="m-textarea <?php echo ( empty ( $this->_data['class'] ) ? '' : esc_attr( $this->_data['class'] ) ); ?>">
            <label class="" for="<?php echo esc_attr( $this->_id ); ?>">
                <?php echo wp_kses( $this->_data['label'], $this->allowed_html ); ?>
            </label>
            <textarea id="<?php echo esc_attr( $this->_id ); ?>" name="<?php echo esc_attr( $this->_id ); ?>" rows="<?php echo esc_attr( $this->_data['rows'] ); ?>" placeholder="<?php echo esc_attr( $this->_placeholder ); ?>"><?php echo wp_kses( $this->_value, $this->allowed_html ); ?></textarea>
        </div>
        <?php
        $output .= ob_get_clean();
        $output .= $this->append();

        $output = apply_filters( 'authorship/option/textarea', $output, $this );

        return $output;
    }
    private function button()
    {
        $output = '';
        $button = ( empty( $this->_data['button'] ) or !$this->_data['button']['display'] ) ? false : true;

        ob_start();
        ?>
        <div class="m-card m-card-button <?php echo ( empty ( $this->_data['class'] ) ? '' : esc_attr( $this->_data['class'] ) ); ?>" <?php echo empty( $this->_data['id'] ) ? '' : 'id="'.esc_attr( $this->_data['id'] ).'"'; ?> <?php echo ( empty ( $this->_data['deps'] ) ? '' : 'data-deps="'.esc_attr( $this->_data['deps'] ).'"' ); ?>>
            <div class="m-card-button__label">
                <span class="m-card-button__label-text">
                    <?php echo wp_kses( $this->_data['label'], $this->allowed_html ); ?>
                </span>
            </div>
            <?php if ( $button ) : ?>
                <div class="m-card-button__actions">
                    <?php switch ( $this->_data['button']['type'] ) :
                        case 'input': ?>
                            <input type="file" <?php echo empty( $this->_data['button']['id'] ) ? '' : 'id="'.esc_attr( $this->_data['button']['id'] ).'" name="'.esc_attr( $this->_data['button']['id'] ).'"'; ?> class="m-file-upload" accept="<?php echo esc_attr( $this->_data['button']['accept'] ); ?>" data-multiple-caption="{count} files selected" <?php echo ( $this->_data['button']['multi'] ? 'multiple' : '' ); ?> />
                            <label for="<?php echo empty( $this->_data['button']['id'] ) ? '' : esc_attr( $this->_data['button']['id'] ); ?>" class="m-button is-compact <?php echo esc_attr( $this->_data['button']['class'] ); ?>">
                                <?php echo esc_html( $this->_data['button']['label'] ); ?>
                            </label>
                            <?php break; ?>
                        <?php case 'download': ?>
                            <button type="submit" <?php echo empty( $this->_data['button']['id'] ) ? '' : 'id="'.esc_attr( $this->_data['button']['id'] ).'"'; ?> <?php echo empty( $this->_data['button']['disabled'] ) ? '' : 'disabled=""'; ?> class="m-button is-compact <?php echo esc_attr( $this->_data['button']['class'] ); ?>" title="<?php echo esc_attr( $this->_data['button']['title'] ); ?>">
                                <?php echo esc_html( $this->_data['button']['label'] ); ?>
                            </button>
                            <?php break; ?>
                        <?php case 'action': ?>
                            <button type="submit" <?php echo empty( $this->_data['button']['id'] ) ? '' : 'id="'.esc_attr( $this->_data['button']['id'] ).'"'; ?> <?php echo empty( $this->_data['button']['disabled'] ) ? '' : 'disabled=""'; ?> class="m-button is-compact <?php echo esc_attr( $this->_data['button']['class'] ); ?>" title="<?php echo esc_attr( $this->_data['button']['title'] ); ?>">
                                <?php echo esc_html( $this->_data['button']['label'] ); ?>
                            </button>
                            <?php break; ?>
                        <?php case 'link': ?>
                            <a href="<?php echo esc_url( $this->_data['button']['href'] ); ?>" <?php echo empty( $this->_data['button']['id'] ) ? '' : 'id="'.esc_attr( $this->_data['button']['id'] ).'"'; ?> <?php echo empty( $this->_data['button']['disabled'] ) ? '' : 'disabled=""'; ?> class="m-button is-compact <?php echo esc_attr( $this->_data['button']['class'] ); ?>" title="<?php echo esc_attr( $this->_data['button']['title'] ); ?>">
                                <?php echo esc_html( $this->_data['button']['label'] ); ?>
                            </a>
                            <?php break; ?>
                        <?php case 'save':default: ?>
                            <button type="submit" <?php echo empty( $this->_data['button']['id'] ) ? '' : 'id="'.esc_attr( $this->_data['button']['id'] ).'"'; ?> <?php echo empty( $this->_data['button']['disabled'] ) ? '' : 'disabled=""'; ?> class="m-button m-button-save is-compact <?php echo esc_attr( $this->_data['button']['class'] ); ?>" title="<?php echo esc_attr( $this->_data['button']['title'] ); ?>">
                                <?php echo esc_html( $this->_data['button']['label'] ); ?>
                            </button>
                            <?php break; ?>
                        <?php endswitch; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        $output .= ob_get_clean();

        return $output;
    }
    private function export()
    {
        $output = '';
        $button = ( empty( $this->_data['button'] ) or !$this->_data['button']['display'] ) ? false : true;
        $all_options = wp_load_alloptions();
        $options_data = array();
        foreach( $all_options as $option_name => $this->_value )
        {
            if ( substr( $option_name, 0, strlen( $this->_options_prefix ) ) === $this->_options_prefix ) $options_data[$option_name] = $this->_value;
        }

        ob_start();
        ?>
        <div class="m-card m-card-button <?php echo esc_attr( $this->_data['class'] ); ?>">
            <div class="m-card-button__label">
                <span class="m-card-button__label-text">
                    <?php echo wp_kses( $this->_data['label'], $this->allowed_html ); ?>
                </span>
            </div>
            <?php if ( $button ) : ?>
                <div class="m-card-button__actions">
                    <textarea readonly id="export_field" name="export_field"><?php echo esc_html( base64_encode( wp_json_encode( $options_data ) ) ); ?></textarea>
                    <label id="<?php echo empty( $this->_data['button']['id'] ) ? '' : esc_attr( $this->_data['button']['id'] ); ?>" for="<?php echo empty( $this->_data['button']['id'] ) ? '' : esc_attr( $this->_data['button']['id'] ); ?>" class="m-button is-compact <?php echo esc_attr( $this->_data['button']['class'] ); ?>">
                        <?php echo esc_html( $this->_data['button']['label'] ); ?>
                    </label>
                    <?php wp_nonce_field( 'mfw_export_options_nonce', 'mfw_export_options_nonce' ); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        $output .= ob_get_clean();

        return $output;
    }
    private function unveil()
    {
        $output = '';

        ob_start();
        ?>
        <div id="<?php echo esc_attr( $this->_id ); ?>" class="m-unveil <?php echo ( empty ( $this->_data['class'] ) ? '' : esc_attr( $this->_data['class'] ) ); ?>"
             data-show="<?php echo esc_attr( $this->_data['label']['show'] ); ?>" data-hide="<?php echo esc_attr( $this->_data['label']['hide'] ); ?>">
            <span>
                <?php echo esc_html( $this->_data['label']['show'] ); ?>
            </span>
        </div>
        <?php
        $output .= ob_get_clean();

        return $output;
    }

} // class