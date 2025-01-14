<?php
/*!
 * Tool for migrating data from the One User Avatar plugin to Molongui Authorship.
 *
 * This file provides the functionality to handle the migration process from the One User Avatar plugin, allowing users
 * to seamlessly transition their custom user avatars to Molongui Authorship.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/migration
 * @since      5.1.0
 */

namespace Molongui\Authorship\Migration;

use Molongui\Authorship\Common\Libraries\WP_Background_Process;
use Molongui\Authorship\Common\Modules\Settings\UI;
use Molongui\Authorship\Common\Utils\Helpers;
use Molongui\Authorship\Common\Modules\Notice;
use Molongui\Authorship\Settings;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class One_User_Avatar extends WP_Background_Process
{
    protected $plugin = 'One User Avatar';
    protected $prefix = 'molongui_authorship';
    protected $action = 'import_oua_data';
    public $oua_postmeta = '_wp_attachment_wp_user_avatar';
    public function __construct()
    {
        if ( apply_filters( 'molongui_authorship/enable_one_user_avatar_migration', true ) )
        {
            if ( is_admin() )
            {
                add_action( 'molongui_authorship/data_migration_tools', array( $this, 'add_migration_option' ) );
                add_action( "admin_print_footer_scripts-" . Settings::instance()->get_screen_id(), array( $this, 'add_migration_option_scripts' ) );

                add_action( "wp_ajax_authorship_migrate_oua_data", array( $this, 'handle_ajax_request' ) );

                add_action( 'admin_init', array( $this, 'migrate_notice' ) );
                add_action( 'admin_notices', array( $this, 'task_status_notice' ) );
            }

            parent::__construct();
        }
    }
    public function migrate_notice()
    {
        if ( get_option( $this->prefix . '_' . $this->action . '_running' ) )
        {
            return;
        }
        if ( $this->is_active() )
        {
            ob_start();
            ?>
            <p>
                <?php printf( __( "%sOne User Avatar Data Migration%s - You have the option to easily migrate all your One User Avatar data to Molongui Authorship. Simply click the button below to start the migration process.", 'molongui-authorship' ), '<strong>', '</strong>' ); ?>
            </p>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=molongui-authorship&tab=tools#molongui-authorship-oua-migration' ) ); ?>" class="button-primary"><?php _e( "Migrate One User Avatar Data Now", 'molongui-authorship' ); ?></a>
            </p>
            <?php

            $content = ob_get_clean();

            new Notice(
                'migrate_oua',
                $content,
                'info'
            );
        }
    }
    public function add_migration_option( $tools )
    {
        if ( apply_filters( 'molongui_authorship/enable_one_user_avatar_check', false ) )
        {
            if ( !$this->is_active() )
            {
                return $tools;
            }
        }

        echo UI::card( array
        (
            'id'          => '',
            'image'       => '',
            'title'       => __( "One User Avatar", 'molongui-authorship' ),
            'description' => __( "Migrate all your One User Avatar data to Molongui Authorship.", 'molongui-authorship' ),
            'button'      => array
            (
                'id'    => 'oua-data-migration',
                'label' => __( "Migrate Now", 'molongui-authorship' ),
                'link'  => '',
            ),
            'echo'        => false,
            'disabled'    => false,
            'class'       => '',
        ));
    }
    public function add_migration_option_scripts()
    {
        ob_start();
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($)
            {
                $('#oua-data-migration').on('click', function(e)
                {
                    const $this  = $(this);
                    const $label = $(this).html();
                    const loader = '<div class="m-typing-loader"></div>';
                    $(this).html(loader);
                    molongui_swal(
                    {
                        icon       : "warning",
                        title      : '<?php _e( "Data Migration Warning!", 'molongui-authorship' ); ?>',
                        content    :
                        {
                            element    : 'div',
                            attributes :
                            {
                                innerHTML: '<span style="line-height:1.5"><?php _e( "You are about to begin a data migration. This process is irreversible and could lead to data loss. Please ensure you have a full backup of your site, including the database and files, before proceeding. Are you sure you want to continue?", 'molongui-authorship' ); ?></span>' +
                                    '<br><br>' +
                                    '<small style="color:gray"><i><?php _e( "Molongui cannot be held responsible for any data loss, missing data, corruption, or errors during the migration. By proceeding, you accept full responsibility for any issues that may arise.", 'molongui-authorship' ); ?></i></small>',
                            },
                        },
                        closeOnEsc : true,
                        buttons    :
                        {
                            cancel:
                            {
                                text       : '<?php _e( "Cancel", 'molongui-authorship' ); ?>',
                                value      : null,
                                visible    : true,
                                className  : '',
                                closeModal : true,
                            },
                            confirm:
                            {
                                text       : '<?php _e( "Yes, proceed", 'molongui-authorship' ); ?>',
                                value      : true,
                                visible    : true,
                                className  : '',
                                closeModal : false
                            },
                        },
                    })
                    .then((value) =>
                    {
                        if (value)
                        {
                            $.ajax(
                            {
                                url  : ajaxurl,
                                type : 'POST',
                                data :
                                {
                                    action : 'authorship_migrate_oua_data',
                                    nonce  : '<?php echo wp_create_nonce( 'molongui_authorship_oua_migration_nonce' ); ?>',
                                },
                                success: function(response)
                                {
                                    if (response)
                                    {
                                        if ('cron_disabled' === response)
                                        {
                                            molongui_swal(
                                            {
                                                icon  : 'error',
                                                title : '<?php _e( "Error", 'molongui-authorship' ); ?>',
                                                text  : '<?php _e( "WP-Cron is disabled, causing the migration process to fail. Please enable WP-Cron in your wp-config.php file to proceed.", 'molongui-authorship' ); ?>',
                                            })
                                            .then( (value) =>
                                            {
                                                $this.html($label);
                                            });
                                        }
                                        else if ('false' === response)
                                        {
                                            molongui_swal(
                                            {
                                                icon  : 'error',
                                                title : '<?php _e( "Error", 'molongui-authorship' ); ?>',
                                                text  : '<?php _e( "Something went wrong, and the data migration failed. Please refresh the page and try again.", 'molongui-authorship' ); ?>',
                                            })
                                            .then( (value) =>
                                            {
                                                $this.html($label);
                                            });
                                        }
                                        else
                                        {
                                            molongui_swal(
                                            {
                                                icon  : 'success',
                                                title : '<?php _e( "Running...", 'molongui-authorship' ); ?>',
                                                text  : '<?php _e( "The data migration process is running in the background. You will be informed of the migration status through an admin notice. You can safely close this window now.", 'molongui-authorship' ); ?>',
                                        })
                                            .then( (value) =>
                                            {
                                                $this.html($label);
                                            });
                                        }
                                    }
                                    else
                                    {
                                        molongui_swal(
                                        {
                                            icon  : 'error',
                                            title : '<?php _e( "Error", 'molongui-authorship' ); ?>',
                                            text  : '<?php _e( "Something went wrong and a connection to the server couldn\'t be established. Please try again.", 'molongui-authorship' ); ?>',
                                        })
                                        .then( (value) =>
                                        {
                                            $this.html($label);
                                        });
                                    }
                                },
                                error: function( jqXHR, textStatus, errorThrown )
                                {
                                    molongui_swal(
                                    {
                                        icon  : 'error',
                                        title : '<?php _e( "Error", 'molongui-authorship' ); ?>',
                                        text  : '<?php _e( "Something went wrong and a connection to the server couldn\'t be established. Please try again.", 'molongui-authorship' ); ?>',
                                    })
                                    .then((value) =>
                                    {
                                        $this.html($label);
                                    });
                                }
                            });
                        }
                        else
                        {
                            $this.html($label);
                        }
                    });

                    e.preventDefault();
                });
            });
        </script>
        <?php
        echo Helpers::minify_js( ob_get_clean() ); //ob_get_clean();
    }
    public function handle_ajax_request()
    {
        check_ajax_referer( 'molongui_authorship_oua_migration_nonce', 'nonce', true );
        if ( apply_filters( 'authorship/check_wp_cron', true ) and defined( 'DISABLE_WP_CRON' ) and DISABLE_WP_CRON )
        {
            $result = 'cron_disabled';
        }
        else
        {
            $result = $this->run();
        }
        echo json_encode( is_wp_error( $result ) ? 'false' : $result );
        wp_die();
    }
    public function run()
    {
        $r = true;
        $relationships = $this->get_user_attachment_relationships();

        if ( !empty( $relationships ) )
        {
            foreach ( $relationships as $relationship )
            {
                $this->push_to_queue( array( 'attachment_id' => $relationship['post_id'], 'user_id' => $relationship['meta_value'] ) );
            }

            $r = $this->save()->dispatch();
        }

        return $r;
    }
    public function get_user_attachment_relationships()
    {
        global $wpdb;

        return $wpdb->get_results( "
            SELECT post_id, meta_value 
            FROM $wpdb->postmeta 
            WHERE meta_key = '_wp_attachment_wp_user_avatar'
        ", ARRAY_A );
    }
    protected function task( $item )
    {
        if ( !is_array( $item ) or !isset( $item['user_id'] ) or !isset( $item['attachment_id'] ) )
        {
            return false;
        }

        $user_id       = $item['user_id'];
        $attachment_id = $item['attachment_id'];

        update_user_meta( $user_id, 'molongui_author_image_id', $attachment_id );
        update_user_meta( $user_id, 'molongui_author_image_url', Helpers::get_attachment_image_url( $attachment_id ) );
        update_user_meta( $user_id, 'molongui_author_image_edit', Helpers::get_attachment_edit_url( $attachment_id ) );
        if ( apply_filters( 'molongui_authorship/delete_oua_meta', false ) )
        {
            global $wpdb, $blog_id;
            $wpdb->delete(
                $wpdb->postmeta,
                array
                (
                    'meta_key'   => '_wp_attachment_wp_user_avatar',
                    'meta_value' => $user_id
                ),
                array
                (
                    '%s', // data type for meta_key
                    '%d'  // data type for meta_value (if $user_id is numeric)
                )
            );
            $wpdb->delete(
                $wpdb->usermeta,
                array
                (
                    'user_id'   => $user_id,
                    'meta_key'  => $wpdb->prepare( $wpdb->get_blog_prefix( $blog_id ) . '%s', 'user_avatar' )
                ),
                array
                (
                    '%d',  // data type for 'user_id'
                    '%s'   // data type for 'meta_key'
                )
            );
        }
        return false;
    }
    public function dispatch()
    {
        $result = parent::dispatch();

        if ( !is_wp_error( $result ) )
        {
            add_option( $this->prefix . '_' . $this->action . '_running', true, '', true );
        }

        return $result;
    }
    protected function complete()
    {
        parent::complete();
        add_option( $this->prefix . '_' . $this->action . '_complete', true, '', true );
    }
    public function task_status_notice()
    {
        if ( get_option( $this->prefix . '_' . $this->action . '_complete' ) )
        {
            delete_option( $this->prefix . '_' . $this->action . '_complete' );
            delete_option( $this->prefix . '_' . $this->action . '_running' );

            $message = sprintf(
                '<p><strong>%s</strong> - %s</p>',
                esc_html__( "Data Migration", 'molongui-authorship' ),
                esc_html__( 'The migration process is now complete. User avatar images from the "One User Avatar" plugin have been successfully migrated.', 'molongui-authorship' )
            );
            echo '<div class="notice notice-success is-dismissible">' . wp_kses_post( $message ) . '</div>';
        }
        elseif ( get_option( $this->prefix . '_' . $this->action . '_running' ) )
        {
            $message = sprintf(
                '<p><strong>%s</strong> - %s</p>',
                esc_html__( "Data Migration", 'molongui-authorship' ),
                esc_html__( "The migration process is running in the background and may take some time to complete. Please be patient. Refresh the page to check the status of the process.", 'molongui-authorship' )
            );
            echo '<div class="notice notice-warning is-dismissible">' . wp_kses_post( $message ) . '</div>';
        }
    }
    public function is_active()
    {
        return is_plugin_active( 'one-user-avatar/one-user-avatar.php' );
    }

} // class
new One_User_Avatar();
