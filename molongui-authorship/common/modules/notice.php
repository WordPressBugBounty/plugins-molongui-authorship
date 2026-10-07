<?php

namespace Molongui\Authorship\Common\Modules;

use Molongui\Authorship\Common\Utils\Helpers;

defined( 'ABSPATH' ) or exit;  

class Notice
{
    private $id;

    private $message;

    private $type;

    private $dismissible;

    private $dismissal_period;

    private $screens;

    private $action = 'authorship_dismiss_admin_notice_';

    private $meta_key = 'authorship_dismissed_notice_';

    public function __construct( $id, $message, $type = 'success', $dismissible = true, $dismissal_period = 0, $screens = array() )
    {
        $this->id               = $id;
        $this->message          = $message;
        $this->type             = $type;
        $this->dismissible      = $dismissible;
        $this->dismissal_period = $dismissal_period * DAY_IN_SECONDS;  
        $this->screens          = $screens;

        add_action( 'admin_notices', array( $this, 'display' ) );

        add_action( 'wp_ajax_' . $this->action . $this->id, array( $this, 'dismiss' ) );
    }

    public function display()
    {
        if ( !current_user_can('manage_options' ) )
        {
            return;
        }

        if ( get_user_meta( get_current_user_id(), $this->meta_key . $this->id, true ) )
        {
            return;
        }
        $dismissed_time = get_user_meta( get_current_user_id(), $this->meta_key . $this->id, true );
        if ( $dismissed_time )
        {
            if ( $this->dismissal_period === 0 )
            {
                return;
            }

            if ( time() - $dismissed_time < $this->dismissal_period )
            {
                return;
            }
        }

        if ( !empty( $this->screens ) )
        {
            global $current_screen;
            if ( !in_array( $current_screen->id, $this->screens ) )
            {
                return;
            }
        }

        $nonce = wp_create_nonce( $this->action . $this->id );

        ?>
        <div class="notice notice-<?php echo esc_attr( $this->type ); ?> <?php echo $this->dismissible ? 'is-dismissible' : ''; ?>" id="<?php echo esc_attr( $this->id ); ?>">
            <?php echo wp_kses_post( $this->message ); ?>

            <?php ob_start(); ?>
            <script type="text/javascript">
                /*
                 * VANILLA JAVASCRIPT APPROACH
                 *
                 * The performance benefit of switching to vanilla JavaScript is minimal, as jQuery is already being
                 * loaded into memory by WordPress. That said, removing jQuery is useful to avoid unnecessary dependency
                 * and keep the code lean.
                 * Removing jQuery dependency helps ensure the code is more flexible and works in any environment.
                 */
                document.addEventListener('DOMContentLoaded', function()
                {
                    /*
                     * Event Delegation: The event listener is attached to the entire document, which ensures that
                     * dynamically added '.notice-dismiss' button is correctly targeted.
                     */
                    document.addEventListener('click', function(event)
                    {
                        // Check if the clicked element is the dismiss button inside the notice
                        if (event.target.closest('.notice-dismiss') && event.target.closest('#<?php echo esc_js( $this->id ); ?>'))
                        {
                            const xhr = new XMLHttpRequest();
                            xhr.open('POST', '<?php echo admin_url( 'admin-ajax.php' ); ?>', true);
                            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                            xhr.send('action=<?php echo esc_js( $this->action . $this->id ); ?>&nonce=<?php echo esc_js( $nonce ); ?>');
                        }
                    });
                });

                /*
                 * JQUERY APPROACH
                 *
                 * WordPress loads jQuery by default on the admin side, so using it does not result in any performance
                 * loss.
                 */ /*
                jQuery(document).ready(function($)
                {
                    const noticeId = '<?php echo esc_js( $this->id ); ?>';
                    const nonce    = '<?php echo esc_js( $nonce ); ?>';

                    // Use event delegation to bind the click event to future elements (like the dynamically added dismiss button).
                    $(document).on('click', '#' + noticeId + ' .notice-dismiss', function()
                    {
                        // Send AJAX request to mark the notice as dismissed.
                        $.post(ajaxurl,
                            {
                                action: '<?php echo esc_js( $this->action ); ?>' + noticeId,
                                nonce: nonce
                            });
                    });
                }); */
            </script>
            <?php echo Helpers::minify_js( ob_get_clean() ); ?>
        </div>
        <?php
    }

    public function dismiss()
    {
        if ( !isset( $_POST['nonce'] ) or !wp_verify_nonce( $_POST['nonce'], $this->action . $this->id ) )
        {
            wp_die( 'Invalid nonce.' );
        }

        update_user_meta( get_current_user_id(), $this->meta_key . $this->id, time() );

        wp_die( 'Admin Notice permanently dismissed.' );
    }

}  
