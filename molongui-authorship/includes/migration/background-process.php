<?php
/*!
 * Handles background data migration from third-party plugins into Molongui Authorship.
 *
 * Provides a base class for offloading migration tasks to background processes  to prevent performance impact during
 * admin operations.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/migration
 * @since      5.1.0
 */

namespace Molongui\Authorship\Migration;

use Molongui\Authorship\Common\Libraries\WP_Background_Process;
use Molongui\Authorship\Common\Modules\Notice;
use Molongui\Authorship\Common\Modules\Settings\UI;
use Molongui\Authorship\Common\Utils\Helpers;
use Molongui\Authorship\Common\Utils\WP;
use Molongui\Authorship\Settings;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
abstract class Background_Process extends WP_Background_Process
{
    protected $prefix = 'molongui_authorship';
    protected $name, $id, $path;
    public function __construct()
    {
        $this->action = 'import_'.$this->id.'_data';
        if ( WP::is_plugin_installed( $this->path ) )
        {
            add_filter( 'molongui_authorship/data_migration_tools', array( $this, 'add_migration_option' ) );
            add_action( "admin_print_footer_scripts-" . Settings::instance()->get_screen_id(), array( $this, 'add_migration_option_scripts' ) );

            add_action( "wp_ajax_authorship_migrate_{$this->id}_data", array( $this, 'handle_ajax_request' ) );

            add_action( 'admin_init', array( $this, 'add_migration_notice' ) );
            add_action( 'admin_notices', array( $this, 'task_status_notice' ) );

            parent::__construct();
        }
    }
    public function add_migration_option( $tools )
    {
        echo UI::card( array
        (
            'id'          => '',
            'image'       => '',
            'title'       => esc_html__( $this->name ),
            'description' => sprintf( esc_html__( "Migrate all your %s data to Molongui Authorship.", 'molongui-authorship' ), $this->name ),
            'button'      => array
            (
                'id'    => $this->id.'-data-migration',
                'label' => esc_html__( "Migrate Now", 'molongui-authorship' ),
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
                $('#<?php echo $this->id.'-data-migration'; ?>').on('click', function(e)
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
                                    action : 'authorship_migrate_<?php echo esc_js( $this->id ); ?>_data',
                                    nonce  : '<?php echo wp_create_nonce( 'molongui_authorship_'.$this->id.'_migration_nonce' ); ?>',
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
        echo Helpers::minify_js( ob_get_clean() );
    }
    public function handle_ajax_request()
    {
        check_ajax_referer( 'molongui_authorship_'.$this->id.'_migration_nonce', 'nonce', true );
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
        if ( apply_filters( 'authorship/check_wp_cron', true ) )
        {
            if ( defined( 'DISABLE_WP_CRON' ) and DISABLE_WP_CRON )
            {
                return false;
            }
        }

        return $this->migrate_data();
    }
    abstract protected function migrate_data();
    public function dispatch()
    {
        $result = parent::dispatch();

        if ( !is_wp_error( $result ) )
        {
            add_option( $this->prefix . '_' . $this->action . '_running', true, '', true );
        }

        return $result;
    }
    protected function task( $item )
    {
        return false;
    }
    protected function complete()
    {
        parent::complete();
        add_option( $this->prefix . '_' . $this->action . '_complete', true, '', true );
        add_option( $this->prefix . '_' . $this->action . '_done', true, '', true );
    }
    public function add_migration_notice()
    {
        if ( get_option( $this->prefix . '_' . $this->action . '_done' ) )
        {
            return;
        }
        if ( get_option( $this->prefix . '_' . $this->action . '_running' ) )
        {
            return;
        }

        ob_start();

        printf( '<p><strong>%s</strong> - %s</p>',
            /*! // translators: %s: The name of the plugin being migrated. */
            sprintf( esc_html__( "%s Data Migration", 'molongui-authorship' ), $this->name ),
            /*! // translators: %s: The name of the plugin being migrated. */
            sprintf( esc_html__( "You have the option to easily migrate all your %s data to Molongui Authorship. Simply click the button below to start the migration process.", 'molongui-authorship' ), $this->name )
        );
        ?>
        <p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=molongui-authorship&tab=tools#molongui-authorship-'.$this->id.'-migration' ) ); ?>" class="button-primary">
                <?php printf( esc_html__( "Migrate %s Data Now", 'molongui-authorship' ), $this->name ); ?>
            </a>
        </p>
        <?php

        $content = ob_get_clean();

        new Notice(
            'migrate_'.$this->id,
            $content,
            'info'
        );
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
                /*! // translators: %s: The name of the plugin being migrated. */
                sprintf( esc_html__( 'The migration process is now complete. Data from the %s plugin have been successfully migrated.', 'molongui-authorship' ), $this->name )
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

}
