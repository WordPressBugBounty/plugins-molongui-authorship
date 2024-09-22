<?php

namespace Molongui\Authorship\Common\Modules;

use Molongui\Authorship\Common\Utils\Helpers;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Setup_Wizard
{
    private $slug;
    private $css;
    private $markup;
    private $steps;
    public function __construct()
    {
        $this->slug   = apply_filters( 'authorship/wizard_slug', MOLONGUI_AUTHORSHIP_NAME . '-setup-wizard' );
        $this->markup = apply_filters( 'authorship/wizard_markup', MOLONGUI_AUTHORSHIP_DIR . 'views/admin/html-setup-wizard.php' );
        $this->steps  = apply_filters( 'authorship/wizard_steps', 0 );

        $this->css    = is_rtl() ? 'common/modules/wizard/assets/css/styles-rtl.adb4.min.css' : 'common/modules/wizard/assets/css/styles.eff3.min.css';

        add_action( 'admin_init', array( $this, 'maybe_load_wizard' ) );
        add_action( 'admin_init', array( $this, 'maybe_redirect_after_activation' ), PHP_INT_MAX );
        add_action( 'admin_menu', array( $this, 'add_dashboard_page' ), 20 );

        add_action( 'wp_ajax_save_wizard_settings', array( $this, 'save_wizard_settings' ) );
    }
    public function maybe_load_wizard()
    {
        if ( wp_doing_ajax() )
        {
            return;
        }
        if ( !current_user_can( 'manage_options' ) )
        {
            return;
        }
        if ( !isset( $_GET['page'] ) or $this->slug !== sanitize_key( $_GET['page'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        {
            return;
        }
        if ( !$this->should_setup_wizard_load() )
        {
            return;
        }
        if ( !file_exists( MOLONGUI_AUTHORSHIP_DIR . $this->css ) )
        {

            $fallback = apply_filters( 'authorship/wizard_fallback', '' );
            wp_safe_redirect( admin_url( $fallback ) );
            exit;
        }

        set_current_screen();

        $this->load_setup_wizard();
    }
    public function should_setup_wizard_load()
    {
        return (bool) apply_filters( 'authorship/load_setup_wizard', true );
    }
    private function load_setup_wizard()
    {
        do_action( 'authorship/before_wizard_load', $this );

        $this->setup_wizard_header();
        $this->setup_wizard_content();
        $this->setup_wizard_footer();
        do_action( 'authorship/after_wizard_load', $this );

        exit;
    }
    public function setup_wizard_header()
    {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
            <head>
                <meta name="viewport" content="width=device-width"/>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
                <title>
                    <?php
                    /*! translators: %s: The plugin name. */
                    printf( esc_html__( "%s &rsaquo; Setup Wizard", 'molongui-authorship' ), esc_html( MOLONGUI_AUTHORSHIP_TITLE ) );
                    ?>
                </title>
                <link rel="stylesheet" id="<?php echo esc_attr( MOLONGUI_AUTHORSHIP_NAME ); ?>-setup-wizard-css" href="<?php echo esc_url( MOLONGUI_AUTHORSHIP_URL . $this->css . '?ver='.MOLONGUI_AUTHORSHIP_VERSION ); ?>" media="all">
            </head>
            <body class="<?php echo esc_attr( $this->slug ); ?> molongui-setup-wizard-welcome">
        <?php
    }
    public function setup_wizard_content()
    {
        ?>
        <?php ob_start(); ?>
        <style>

            .molongui-setup-wizard
            {
                display: flex;
                justify-content: center;
                border-top: 4px solid #2e3758;
            }

            .molongui-setup-wizard-wrap
            {
                width: 670px;
                margin: 50px 0;
                padding: 0 10px;
                display: flex;
                flex-direction: column;
                row-gap: 3em;
            }

            .molongui-setup-wizard__header
            {

            }
            .molongui-setup-wizard__logo
            {
                width: 260px;/*320px;*/
                margin: 0 auto;
            }
            .molongui-setup-wizard__logo img
            {
                width: 100%;
                height: 100%;
            }

            .molongui-setup-wizard__progress
            {

            }

            .molongui-setup-wizard__content
            {
                padding: 30px 45px 40px;
                background: white;
                border: 3px solid black;
                border-radius: 3px;
            }
            .molongui-setup-wizard__step
            {
                display: none;
            }
            .molongui-setup-wizard__step.active
            {
                display: block;
            }
            .molongui-setup-wizard__step h2
            {
                margin: 6px 0 24px;
                font-size: 24px;
                color: #222;
            }
            .molongui-setup-wizard__track
            {
                font-size: 12px;
                color: #b6b6b6;
            }

            .molongui-setup-wizard__description
            {
                display: flex;
                justify-content: space-between;
                column-gap: 1em;
            }
            .molongui-setup-wizard__description p,
            .molongui-setup-wizard__description li
            {
                margin: 1em 0;
                line-height: 1.5;
                font-size: 16px;
                color: #777;
            }
            .molongui-setup-wizard__description p:first-of-type
            {
                margin-top: 0;
            }
            .molongui-setup-wizard__description p:last-of-type
            {
                margin-bottom: 0;
            }
            .molongui-setup-wizard__description ol,
            .molongui-setup-wizard__description ul
            {
                margin: 0 0 1em;
                padding-left: 1.5em;
            }
            .molongui-setup-wizard__description ol li
            {
                margin: 0;
                padding: 6px 0 6px 3px;
            }
            .molongui-setup-wizard__description ul li
            {
                margin: 0;
                padding: 10px;
            }
            .molongui-setup-wizard__description li:first-of-type
            {
                padding-top: 0;
            }
            .molongui-setup-wizard__description ol ::marker,
            .molongui-setup-wizard__description ul ::marker
            {
                font-weight: bold;
                color: #00b7a8;
            }
            .molongui-setup-wizard__description ol ::marker
            {
                content: counter(list-item) "/ "; /*"Step " counter(list-item) ": ";*/
            }
            .molongui-setup-wizard__description ul ::marker
            {
                content: '\0276F';
            }
            .molongui-setup-wizard__description ul.premium-features ::marker
            {
                content: '\02714';
            }

            .molongui-setup-wizard__nav
            {
                display: flex;
                justify-content: space-between;
                margin-top: 2em;
                padding-top: 2em;
                border-top: 1px solid #e6e6e6;
            }

            .molongui-setup-wizard__button
            {
                margin-right: auto; /* To align buttons to the left no matter the number of items/buttons */
                padding: 1em 2em;
                background-color: #f1f1f1;
                border: 0;
                border-radius: 3px;
                font-size: 16px;
                cursor: pointer;
            }
            .molongui-setup-wizard__button:hover
            {
                background-color: #d8d8d8;
            }
            .molongui-setup-wizard__button.primary
            {
                background-color: #2e3758;
                color: white;
                margin-left: auto; /* To align the action button on the right no matter the number of items/buttons */
                margin-right: 0;   /* To align the action button on the right no matter the number of items/buttons */
            }
            .molongui-setup-wizard__button.primary:hover
            {
                background-color: black;
            }
            .molongui-setup-wizard__button.back:before
            {
                content: ' \02039'; /*' \02190';*/
                font-weight: bold;
            }
            .molongui-setup-wizard__button.next:after
            {
                content: ' \0203A'; /*' \02192';*/
                font-weight: bold;
            }

            .toggle
            {
                display: flex;
                align-items: center;
                margin: 1em 0 0;
                border: 1px dotted #b5b5b5;
                border-left: 0;
                border-right: 0;
            }
            .toggle:first-of-type
            {
                margin-top: 2em;
            }
            .toggle .knobs, .toggle .layer
            {
                position: absolute;
                top: 0;
                right: 0;
                bottom: 0;
                left: 0;
            }

            .toggle .button
            {
                position: relative;
                width: 74px;
                height: 36px;
                overflow: hidden;
            }

            .toggle .button.r, .toggle .button.r .layer
            {
                border-radius: 100px;
            }

            .toggle .checkbox
            {
                position: relative;
                width: 100%;
                height: 100%;
                padding: 0;
                margin: 0;
                opacity: 0;
                cursor: pointer;
                z-index: 3;
            }

            .toggle .knobs
            {
                z-index: 2;
            }

            .toggle .layer
            {
                width: 100%;
                background-color: #fcebeb;/*#ebf7fc;*/
                transition: 0.3s ease all;
                z-index: 1;
            }

            .toggle .knobs:before
            {
                content: 'NO';/*'YES';*/
                position: absolute;
                top: 4px;
                left: 42px;/*4px;*/
                width: 20px;
                height: 10px;
                color: #fff;
                font-size: 10px;
                font-weight: bold;
                text-align: center;
                line-height: 1;
                padding: 9px 4px;
                background-color: #f44336;/*#03A9F4;*/
                border-radius: 50%;
                transition: 0.3s cubic-bezier(0.18, 0.89, 0.35, 1.15) all;
            }

            .toggle .checkbox:checked + .knobs:before
            {
                content: 'YES';/*'NO';*/
                left: 4px;/*42px;*/
                background-color: #03A9F4;/*#f44336;*/
            }

            .toggle .checkbox:checked ~ .layer
            {
                background-color: #ebf7fc;/*#fcebeb;*/
            }

            .toggle .knobs, .toggle .knobs:before, .toggle .layer
            {
                transition: 0.3s ease all;
            }

            .toggle__label
            {
                margin-left: 10px;
                font-family: monospace;
                font-size: 14px;
                color: #777;
            }

            .upgrade
            {
                padding: 0 6px 2px;
                background: #00b7a8;
                border-radius: 3px;
                text-decoration: none;
                color: white;
            }
            .upgrade:hover
            {
                background: #2e3758;
            }

            code
            {
                padding: 1px 4px;
                background: #efefef;
                font-family: Consolas,Monaco,monospace;
                font-size: 0.9em;
                font-weight: 600;
            }
            pre
            {
                white-space: pre-wrap;
                word-wrap: break-word;
                background: #efefef;
                font-family: Consolas,Monaco,monospace;
                font-size: 0.9em;
            }
            .molongui-setup-wizard-timeline
            {
                margin: 0 0 -1em;
                padding: 0;
            }
            .molongui-setup-wizard-timeline .molongui-setup-wizard-timeline-step
            {
                background-color: #ddd;
            }
            .molongui-setup-wizard-timeline .molongui-setup-wizard-timeline-step.molongui-setup-wizard-timeline-step-active,
            .molongui-setup-wizard-timeline .molongui-setup-wizard-timeline-step.molongui-setup-wizard-timeline-step-completed
            {
                background-color: #00b7a8;
            }

            .molongui-input-radios-with-icons,
            .molongui-plugin-features-list
            {
                margin-top: 2em;
            }
            .settings-input-long-radio:last-of-type
            {
                margin-bottom: 0;
            }
            .settings-input-long-checkbox:last-of-type
            {
                padding-bottom: 0;
            }

        </style>
        <?php echo Helpers::minify_css( ob_get_clean() ); ?>

        <div class="molongui-setup-wizard">

            <div class="molongui-setup-wizard-wrap">

                <header class="molongui-setup-wizard__header">
                    <div class="molongui-setup-wizard__logo">
                        <img src="<?php echo esc_url( MOLONGUI_AUTHORSHIP_URL.'assets/img/wizard/logo.png' ); ?>" alt="<?php echo esc_attr( MOLONGUI_AUTHORSHIP_TITLE ); ?>">
                    </div>
                </header>

                <div class="molongui-setup-wizard__progress">
                    <?php $this->render_timeline( $this->steps, 0 ); ?>
                </div>

                <div class="molongui-setup-wizard__content">

                    <?php
                    if ( file_exists( $this->markup ) )
                    {
                        include $this->markup;
                    }
                    else
                    {

                        echo '<div class="warning">' . sprintf( "No content for this Wizard found. Please check the %s file exists.", esc_html( $this->markup ) ) . '</div>';
                    }
                    ?>

                </div><!-- molongui-setup-wizard__content -->

                <footer class="molongui-setup-wizard__footer">
                    <p class="molongui-exit-link">
                        <a id="molongui-exit-link--back"  href="<?php echo esc_url( admin_url( 'options-general.php?page=molongui-authorship' ) ); ?>"><?php esc_html_e( "Go back to the Dashboard", 'molongui-authorship' ); ?></a>
                        <a id="molongui-exit-link--close" href="<?php echo esc_url( admin_url( 'options-general.php?page=molongui-authorship' ) ); ?>" style="display:none;"><?php esc_html_e( "Close and exit the Setup Wizard", 'molongui-authorship' ); ?></a>
                    </p>
                </footer>

            </div>

        </div>

        <?php ob_start(); ?>
        <script>
            document.addEventListener('DOMContentLoaded', function()
            {
                const steps     = document.querySelectorAll('.molongui-setup-wizard__step');
                const circles   = document.querySelectorAll('.molongui-setup-wizard-timeline-step');
                let currentStep = 0;
                document.querySelectorAll('.molongui-setup-wizard__button.next').forEach(button =>
                {
                    button.addEventListener('click', nextStep);
                });
                document.querySelectorAll('.molongui-setup-wizard__button.back').forEach(button =>
                {
                    button.addEventListener('click', previousStep);
                });
                document.querySelectorAll('.molongui-setup-wizard__button.finish').forEach(button =>
                {
                    button.addEventListener('click', submitWizard);
                });
                function showStep(stepIndex)
                {
                    steps.forEach((step, index) => {
                        step.classList.toggle('active', index === stepIndex);
                    });
                    updateCircles(stepIndex);
                }
                function nextStep()
                {
                    if ( currentStep < steps.length - 1 )
                    {
                        currentStep++;
                        showStep(currentStep);
                    }
                }
                function previousStep()
                {
                    if ( currentStep > 0 )
                    {
                        currentStep--;
                        showStep(currentStep);
                    }
                }
                function submitWizard(event)
                {
                    const inputs   = document.querySelectorAll('input');
                    const formData = new FormData();
                    document.querySelectorAll('input[type="checkbox"]').forEach(function(input)
                    {
                        formData.append(input.name, input.checked ? '1' : '0');
                    });
                    document.querySelectorAll('input[type="radio"]:checked').forEach(function(input)
                    {
                        formData.append(input.name, input.value);
                    });
                    document.querySelectorAll('input[type="hidden"]').forEach(function(input)
                    {
                        formData.append(input.name, input.value);
                    });
                    formData.append( 'action', 'save_wizard_settings' );
                    formData.append( 'nonce', authorshipSetupWizard.nonce );

                    fetch(authorshipSetupWizard.ajaxurl,
                        {
                            method : 'POST',
                            body   : formData,
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success)
                            {
                                window.location.href = authorshipSetupWizard.redirect;
                            }
                            else
                            {
                                console.error('Error:', data.data);
                            }
                        })
                        .catch((error) => {
                            console.error('Error:', error);
                        });
                }
                const updateCircles = (stepIndex) => {
                    if ( stepIndex === 0 )
                    {
                        circles.forEach(circle => {
                            circle.classList.remove('molongui-setup-wizard-timeline-step-completed', 'molongui-setup-wizard-timeline-step-active');
                        });
                    }
                    else
                    {
                        circles.forEach((circle, index) => {
                            if ( index < stepIndex - 1 )
                            {
                                circle.classList.add('molongui-setup-wizard-timeline-step-completed');
                                circle.classList.remove('molongui-setup-wizard-timeline-step-active');
                            } else if ( index === stepIndex - 1 )
                            {
                                circle.classList.add('molongui-setup-wizard-timeline-step-active');
                                circle.classList.remove('molongui-setup-wizard-timeline-step-completed');
                            }
                            else
                            {
                                circle.classList.remove('molongui-setup-wizard-timeline-step-completed', 'molongui-setup-wizard-timeline-step-active');
                            }
                        });
                    }
                };
                document.addEventListener('click', function(ev)
                {
                    const label = ev.target.closest('label.settings-input-long-checkbox');

                    if (label && !ev.target.matches('input[type="checkbox"], input[type="checkbox"] *'))
                    {

                        const checkboxSpan = label.querySelector('.checkbox');
                        if (checkboxSpan && !checkboxSpan.classList.contains('checkbox-disabled'))
                        {
                            checkboxSpan.classList.toggle('checkbox-checked');
                            label.classList.toggle('settings-input-long-checkbox-checked');
                        }
                    }
                }, false);
                document.addEventListener('click', function(ev)
                {
                    const label = ev.target.closest('.molongui-input-radios-with-icons label');

                    if (label && !ev.target.matches('input[type="radio"], input[type="radio"] *'))
                    {
                        const radio = label.querySelector('input[type="radio"]');

                        if (radio.checked)
                        {
                            return;
                        }

                        const radioSpan = label.querySelector('.molongui-styled-radio');
                        if (radioSpan && !radioSpan.classList.contains('molongui-styled-radio-disabled'))
                        {
                            document.querySelectorAll('.settings-input-long-radio').forEach((element) => {
                                element.classList.remove('molongui-styled-radio-label-checked');
                            });
                            document.querySelectorAll('.molongui-styled-radio').forEach((element) => {
                                element.classList.remove('molongui-styled-radio-checked');
                            });

                            radioSpan.classList.toggle('molongui-styled-radio-checked');
                            label.classList.toggle('molongui-styled-radio-label-checked');
                        }
                    }
                }, false);
            });
        </script>
        <?php echo Helpers::minify_js( ob_get_clean() ); ?>

        <?php
    }
    public function setup_wizard_footer()
    {
        $ajaxurl  = admin_url( 'admin-ajax.php' );
        $nonce    = wp_create_nonce( MOLONGUI_AUTHORSHIP_ID.'_setup_wizard' );
        $redirect = admin_url( apply_filters( 'authorship/wizard_fallback', '' ) );
        $upgrade  = MOLONGUI_AUTHORSHIP_WEB;
        ?>
        <script type="text/javascript">var authorshipSetupWizard = {"ajaxurl":"<?php echo esc_url( $ajaxurl ); ?>","nonce":"<?php echo esc_html( $nonce ); ?>","redirect":"<?php echo esc_url( $redirect ); ?>","upgrade":"<?php echo esc_url( $upgrade ); ?>"};</script>
        </body>
        </html>
        <?php
    }
    public function maybe_redirect_after_activation() // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh
    {
        /*!
         * FILTER HOOK
         *
         * Allows disabling redirection to the wizard after installation.
         *
         * @since 3.0.0
         */
        if ( apply_filters( 'authorship/prevent_wizard_redirect', false ) )
        {
            return;
        }
        if ( wp_doing_ajax() or wp_doing_cron() )
        {
            return;
        }
        if ( !get_transient( MOLONGUI_AUTHORSHIP_NAME.'-activation-redirect' ) )
        {
            return;
        }
        delete_transient( MOLONGUI_AUTHORSHIP_NAME.'-activation-redirect' );
        if ( isset( $_GET['activate-multi'] ) or is_network_admin() ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        {
            return;
        }

        $install = get_option( MOLONGUI_AUTHORSHIP_INSTALL );

        if ( !$install )
        {
            return;
        }
        if ( empty( $install['timestamp'] ) or $install['timestamp'] > strtotime( "-2 days" ) )
        {
            wp_safe_redirect( $this->get_url() );
            exit;
        }
    }
    public function get_url()
    {
        return admin_url( 'index.php?page=' . $this->slug );
    }
    public function add_dashboard_page()
    {
        if ( !$this->should_setup_wizard_load() )
        {
            return;
        }

        add_submenu_page( '', '', '', 'manage_options', $this->slug, '' );
    }
    public function save_wizard_settings()
    {
        check_ajax_referer( MOLONGUI_AUTHORSHIP_ID.'_setup_wizard', 'nonce' );
        if ( !current_user_can( 'manage_options' ) )
        {
            wp_send_json_error('Insufficient permissions' );
            return;
        }
        if ( isset( $_POST ) )
        {
            $wizard_settings = array();
            $wizard_settings = apply_filters( 'authorship/wizard_settings', $wizard_settings );
            $options = Settings::get();
            update_option( MOLONGUI_AUTHORSHIP_PREFIX.'_options', array_merge( $options, $wizard_settings ), true );

            wp_send_json_success( array( 'Settings saved', wp_json_encode( $wizard_settings ) ) );
        }
        else
        {
            wp_send_json_error( 'No data received' );
        }
    }
    public function render_timeline( $steps, $current = 0 )
    {
        --$current;
        ?>
        <div class="molongui-setup-wizard-timeline">
        <?php
        for ( $i=0; $i<$steps; $i++ )
        {
            $class = 'molongui-setup-wizard-timeline-step';
            if ( $i < $current )
            {
                $class .= ' molongui-setup-wizard-timeline-step-completed';
            }
            elseif ( $i === $current )
            {
                $class .= ' molongui-setup-wizard-timeline-step-active';
            }
            ?>
            <div class="<?php echo esc_attr( $class ); ?>"><svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" role="img" class="icon icon-success" data-icon="check" data-prefix="fas" focusable="false" aria-hidden="true" width="10" height="10"><path xmlns="http://www.w3.org/2000/svg" fill="currentColor" d="M173.898 439.404l-166.4-166.4c-9.997-9.997-9.997-26.206 0-36.204l36.203-36.204c9.997-9.998 26.207-9.998 36.204 0L192 312.69 432.095 72.596c9.997-9.997 26.207-9.997 36.204 0l36.203 36.204c9.997 9.997 9.997 26.206 0 36.204l-294.4 294.401c-9.998 9.997-26.207 9.997-36.204-.001z"></path></svg><svg viewBox="0 0 352 512" xmlns="http://www.w3.org/2000/svg" role="img" class="icon icon-failed" data-icon="times" data-prefix="fas" focusable="false" aria-hidden="true" width="8" height="11"><path xmlns="http://www.w3.org/2000/svg" fill="currentColor" d="M242.72 256l100.07-100.07c12.28-12.28 12.28-32.19 0-44.48l-22.24-22.24c-12.28-12.28-32.19-12.28-44.48 0L176 189.28 75.93 89.21c-12.28-12.28-32.19-12.28-44.48 0L9.21 111.45c-12.28 12.28-12.28 32.19 0 44.48L109.28 256 9.21 356.07c-12.28 12.28-12.28 32.19 0 44.48l22.24 22.24c12.28 12.28 32.2 12.28 44.48 0L176 322.72l100.07 100.07c12.28 12.28 32.2 12.28 44.48 0l22.24-22.24c12.28-12.28 12.28-32.19 0-44.48L242.72 256z"></path></svg></div>
            <?php
            if ( $i < $steps-1 )
            {
                ?>
                <div class="molongui-setup-wizard-timeline-step-line"></div>
                <?php
            }
        }
        ?>
        </div>
        <?php
    }
    public function render_long_checkbox( $id, $label, $description = null, $checked = false, $disabled = false, $pro = false )
    {
        $label_class = "settings-input-long-checkbox";
        $input_class = "checkbox";
        if ( $checked )
        {
            $label_class .= " settings-input-long-checkbox-checked";
            $input_class .= " checkbox-checked";
        }
        if ( $disabled )
        {
            $label_class .= " settings-input-long-checkbox-disabled";
            $input_class .= " checkbox-disabled";
        }
        ?>

        <label for="molongui-settings-long-checkbox-<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( $label_class ); ?>">
            <span class="settings-input-long-checkbox-container">
                <input id="molongui-settings-long-checkbox-<?php echo esc_attr( $id ); ?>" type="checkbox" name="<?php echo esc_attr( $id ); ?>" <?php checked( $checked ); disabled( $disabled ); ?>>
                <span class="<?php echo esc_attr( $input_class ); ?>">
                    <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" role="img" class="icon" data-icon="check" data-prefix="fas" focusable="false" aria-hidden="true" width="16" height="16">
                        <path xmlns="http://www.w3.org/2000/svg" fill="currentColor" d="M173.898 439.404l-166.4-166.4c-9.997-9.997-9.997-26.206 0-36.204l36.203-36.204c9.997-9.998 26.207-9.998 36.204 0L192 312.69 432.095 72.596c9.997-9.997 26.207-9.997 36.204 0l36.203 36.204c9.997 9.997 9.997 26.206 0 36.204l-294.4 294.401c-9.998 9.997-26.207 9.997-36.204-.001z"></path>
                    </svg>
                </span>
            </span>
            <div class="settings-input-long-checkbox-header">
                <span class="title-container">
                    <span class="label"><?php echo esc_html( $label ); ?></span>
                    <?php if ( $pro ) : ?>
                    <svg class="molongui-pro-badge" viewBox="0 0 46 26" height="24" width="46" xmlns="http://www.w3.org/2000/svg">
                        <defs xmlns="http://www.w3.org/2000/svg"><style>.a-prob{fill:#e6e6e6;}.b-prob{fill:#777;font-size:12px;font-weight:500;text-transform:uppercase;}</style></defs>
                        <rect xmlns="http://www.w3.org/2000/svg" class="a-prob" width="46" height="26" rx="3"></rect>
                        <text xmlns="http://www.w3.org/2000/svg" class="b-prob" transform="translate(9.999 17)"><tspan x="0" y="0"><?php esc_html_e( "Pro", 'molongui-authorship' ); ?></tspan></text>
                    </svg>
                    <?php endif; ?>
                </span>
                <?php if ( isset( $description ) ) : ?>
                <p class="description"><?php echo wp_kses_post( $description ); ?></p>
                <?php endif; ?>
            </div>
        </label>

        <?php
    }
    public function render_radio( $id, $label, $description, $value, $checked = false, $disabled = false, $pro = false )
    {
        $label_class = "settings-input-long-radio";
        $input_class = "molongui-styled-radio";
        if ( $checked )
        {
            $label_class .= " molongui-styled-radio-label-checked";
            $input_class .= " molongui-styled-radio-checked";
        }
        if ( $disabled )
        {
            $label_class .= " molongui-styled-radio-label-disabled";
            $input_class .= " molongui-styled-radio-disabled";
        }
        ?>

        <label for="molongui-settings-radio-<?php echo esc_attr( $id ); ?>[<?php echo esc_attr( $value ); ?>]" class="<?php echo esc_attr( $label_class ); ?>">
            <input id="molongui-settings-radio-<?php echo esc_attr( $id ); ?>[<?php echo esc_attr( $value ); ?>]" type="radio" name="<?php echo esc_attr( $id ); ?>" autocomplete="off" value="<?php echo esc_attr( $value ); ?>" <?php checked( $checked ); disabled( $disabled ); ?>>
            <span class="<?php echo esc_attr( $input_class ); ?>">
                <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" role="img" class="icon" data-icon="check" data-prefix="fas" focusable="false" aria-hidden="true" width="16" height="16">
                    <path xmlns="http://www.w3.org/2000/svg" fill="currentColor" d="M173.898 439.404l-166.4-166.4c-9.997-9.997-9.997-26.206 0-36.204l36.203-36.204c9.997-9.998 26.207-9.998 36.204 0L192 312.69 432.095 72.596c9.997-9.997 26.207-9.997 36.204 0l36.203 36.204c9.997 9.997 9.997 26.206 0 36.204l-294.4 294.401c-9.998 9.997-26.207 9.997-36.204-.001z"></path>
                </svg>
            </span>
            <?php
            $img = 'assets/img/wizard/'.$value.'svg';
            if ( file_exists( MOLONGUI_AUTHORSHIP_DIR . $img ) ) : ?>
                <img src="<?php echo esc_url( MOLONGUI_AUTHORSHIP_URL . $img ); ?>" alt="<?php echo esc_attr( $value ); ?>" class="molongui-logo-icon">
            <?php endif; ?>
            <span class="molongui-styled-radio-text"><?php echo esc_html( $label ); ?></span>
            <?php if ( $pro ) : ?>
                <svg class="molongui-pro-badge" viewBox="0 0 46 26" height="24" width="46" xmlns="http://www.w3.org/2000/svg">
                    <defs xmlns="http://www.w3.org/2000/svg"><style>.a-prob{fill:#e6e6e6;}.b-prob{fill:#777;font-size:12px;font-weight:500;text-transform:uppercase;}</style></defs>
                    <rect xmlns="http://www.w3.org/2000/svg" class="a-prob" width="46" height="26" rx="3"></rect>
                    <text xmlns="http://www.w3.org/2000/svg" class="b-prob" transform="translate(9.999 17)"><tspan x="0" y="0"><?php esc_html_e( "Pro", 'molongui-authorship' ); ?></tspan></text>
                </svg>
            <?php endif; ?>
            <span class="molongui-styled-radio-description"><?php echo wp_kses_post( $description ); ?></span>
        </label>

        <?php
    }
}