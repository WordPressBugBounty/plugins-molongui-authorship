<?php
/*!
 * Tool for migrating data from the Co-Authors Plus plugin to Molongui Authorship.
 *
 * This file provides the functionality to handle the migration process from the Co-Authors Plus plugin, allowing users
 * to seamlessly transition their author data to Molongui Authorship.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/migration
 * @since      5.1.0
 */

namespace Molongui\Authorship\Migration;

use Molongui\Authorship\Admin\Post_Count_Updater;
use Molongui\Authorship\Common\Libraries\WP_Background_Process;
use Molongui\Authorship\Common\Modules\Settings\UI;
use Molongui\Authorship\Common\Utils\Helpers;
use Molongui\Authorship\Common\Modules\Notice;
use Molongui\Authorship\Settings;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Co_Authors_Plus extends WP_Background_Process
{
    protected $prefix = 'molongui_authorship';
    protected $action = 'import_cap_data';
    public $cap_tax = 'author';
    public $cap_cpt = 'guest-author';
    public function __construct()
    {
        if ( apply_filters( 'molongui_authorship/enable_coauthors_plus_migration', true ) )
        {
            if ( is_admin() )
            {
                add_filter( 'molongui_authorship/data_migration_tools', array( $this, 'add_migration_option' ) );
                add_action( "admin_print_footer_scripts-" . Settings::instance()->get_screen_id(), array( $this, 'add_migration_option_scripts' ) );

                add_action( "wp_ajax_authorship_migrate_cap_data", array( $this, 'handle_ajax_request' ) );

                add_action( 'admin_init', array( $this, 'migrate_cap_notice' ) );
                add_action( 'admin_notices', array( $this, 'task_status_notice' ) );
            }

            parent::__construct();
        }
    }
    public function migrate_cap_notice()
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
                <?php printf( __( "%sCo-Authors Plus Data Migration%s - You have the option to easily migrate all your Co-Authors Plus data to Molongui Authorship. Simply click the button below to start the migration process.", 'molongui-authorship' ), '<strong>', '</strong>' ); ?>
            </p>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=molongui-authorship&tab=tools#molongui-authorship-cap-migration' ) ); ?>" class="button-primary"><?php _e( "Migrate Co-Authors Plus Data Now", 'molongui-authorship' ); ?></a>
            </p>
            <?php

            $content = ob_get_clean();

            new Notice(
                'migrate_cap',
                $content,
                'info'
            );
        }
    }
    public function add_migration_option( $tools )
    {
        if ( apply_filters( 'molongui_authorship/enable_coauthors_plus_check', false ) )
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
            'title'       => __( "Co-Authors Plus", 'molongui-authorship' ),
            'description' => __( "Migrate all your Co-Authors Plus data to Molongui Authorship.", 'molongui-authorship' ),
            'button'      => array
            (
                'id'    => 'cap-data-migration',
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
                $('#cap-data-migration').on('click', function(e)
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
                                    action : 'authorship_migrate_cap_data',
                                    nonce  : '<?php echo wp_create_nonce( 'molongui_authorship_cap_migration_nonce' ); ?>',
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
        check_ajax_referer( 'molongui_authorship_cap_migration_nonce', 'nonce', true );
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

        $r1 = $r2 = true;
        if ( !taxonomy_exists( $this->cap_tax ) )
        {
            register_taxonomy( $this->cap_tax, 'post', array() );
        }
        if ( apply_filters( 'molongui_authorship/cap_migrate_guest_authors', true ) )
        {
            $r1 = $this->migrate_guests();
        }
        if ( apply_filters( 'molongui_authorship/cap_migrate_postmeta', true ) )
        {
            $r2 = $this->migrate_postmeta();
        }

        return $r1 and $r2;
    }
    public function migrate_guests()
    {
        $r = true;
        $post_ids = $this->get_guest_authors();

        if ( !empty( $post_ids ) )
        {
            foreach ( $post_ids as $post_id )
            {
                $this->push_to_queue( array( 'type' => 'guest', 'id' => $post_id ) );
            }

            $r = $this->save()->dispatch();
        }

        return $r;
    }
    public function migrate_postmeta()
    {
        $r = true;
        $post_ids = $this->get_posts();

        if ( !empty( $post_ids ) )
        {
            foreach ( $post_ids as $post_id )
            {
                $this->push_to_queue( array( 'type' => 'post', 'id' => $post_id ) );
            }

            $r = $this->save()->dispatch();
        }

        return $r;
    }
    protected function task( $item )
    {
        if ( !is_array( $item ) or !isset( $item['type'] ) or !isset( $item['id'] ) )
        {
            return false;
        }

        $type = $item['type'];
        $id   = $item['id'];

        switch ( $type )
        {
            case 'guest':
                $this->convert_guest( $id );
                break;

            case 'post':
                $this->convert_postmeta( $id );
                break;
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
        $count_updater = Post_Count_Updater::instance();
        $count_updater->handle_internal_request();
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
                esc_html__( 'The migration process is now complete. Data from the "Co-Authors Plus" plugin have been successfully migrated.', 'molongui-authorship' )
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
    public function get_guest_authors()
    {
        global $wpdb;
        return $wpdb->get_col(
            $wpdb->prepare("
                SELECT ID
                FROM {$wpdb->posts}
                WHERE post_type = %s
            ", $this->cap_cpt )
        );
    }
    public function convert_guest( $post_id )
    {
        global $wpdb;
        $post_id = intval( $post_id );
        $wpdb->query( 'START TRANSACTION' );
        try
        {
            $wpdb->update(
                $wpdb->posts,
                array( 'post_type' => 'guest_author' ), // New post_type value
                array( 'ID' => $post_id ), // WHERE condition
                array( '%s' ), // Data format
                array( '%d' )  // WHERE condition format
            );
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->posts} 
                 SET post_name = REPLACE(post_name, 'cap-', '') 
                 WHERE ID = %d AND post_name LIKE 'cap-%%'",
                $post_id
            ));
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 SET p.post_content = pm.meta_value
                 WHERE pm.meta_key = 'cap-description' AND p.ID = %d",
                $post_id
            ));
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta}
                WHERE post_id = %d AND meta_key IN ('cap-description', 'cap-user_login', 'cap-linked_account')",
                $post_id
            ));
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->postmeta}
                 SET meta_key = REPLACE(meta_key, 'cap-', '_molongui_guest_author_')
                 WHERE post_id = %d AND meta_key LIKE 'cap-%%'",
                $post_id
            ));
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->postmeta}
                 SET meta_key = CASE
                    WHEN meta_key = '_molongui_guest_author_user_email' THEN '_molongui_guest_author_mail'
                    WHEN meta_key = '_molongui_guest_author_website' THEN '_molongui_guest_author_web'
                 END
                 WHERE post_id = %d AND meta_key IN ('_molongui_guest_author_user_email', '_molongui_guest_author_website')",
                $post_id
            ));
            $wpdb->query( 'COMMIT' );

            error_log( "CAP to MA - Migrated guest author #" . $post_id );

        }
        catch ( \Exception $e )
        {
            $wpdb->query( 'ROLLBACK' );
            error_log( 'Error updating post #' . $post_id . ': ' . $e->getMessage() );
        }
    }
    public function get_posts()
    {
        global $wpdb;

        $supported_post_types = array( 'post' );
        if ( isset( $GLOBALS['coauthors_plus'] ) )
        {
            global $coauthors_plus;
            $supported_post_types = $coauthors_plus->supported_post_types();
        }
        $supported_post_types = apply_filters( 'molongui_authorship/cap_supported_post_types', $supported_post_types );
        $placeholders = implode( ',', array_fill( 0, count( $supported_post_types ), '%s' ) );
        return $wpdb->get_col(
            $wpdb->prepare( "
                SELECT DISTINCT p.ID
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tt.taxonomy = %s
                AND p.post_type IN ( $placeholders )
            ", array_merge( array( $this->cap_tax ), $supported_post_types ) )
        );
    }
    public function convert_postmeta( $post_id )
    {
        $author_terms = wp_get_object_terms( $post_id, 'author', array( 'orderby' => 'term_order', 'order' => 'ASC' ) );

        if ( !empty( $author_terms ) and !is_wp_error( $author_terms ) and is_array( $author_terms ) )
        {
            $post_authors = array();

            foreach ( $author_terms as $author_term )
            {
                $author = $this->get_author_by_term( $author_term, 'guest' );

                if ( !empty( $author ) and is_a( $author, 'WP_Post' ) )
                {
                    $post_authors[] = $author;
                }
                else
                {
                    $author = $this->get_author_by_term( $author_term, 'user' );

                    if ( !empty( $author ) and is_a( $author, 'WP_User' ) )
                    {
                        $post_authors[] = $author;
                    }
                }
                if ( apply_filters( 'molongui_authorship/delete_cap_taxonomies', false ) )
                {
                    wp_remove_object_terms( $post_id, array( $author_term->term_id ), $this->cap_tax );
                    if ( empty( get_objects_in_term( $author_term->term_id, $this->cap_tax ) ) )
                    {
                        wp_delete_term( $author_term->term_id, $this->cap_tax );
                    }
                }
            }

            if ( !empty( $post_authors ) )
            {
                $this->delete_molongui_authorship_meta( $post_id );
                $this->set_post_main_author( $post_id, $post_authors[0]->ID, $this->get_author_type( $post_authors[0] ) );
                foreach ( $post_authors as $post_author )
                {
                    $this->set_post_author( $post_id, $post_author->ID, $this->get_author_type( $post_author ) );
                }

                error_log( "CAP to MA - Migrated postmeta for post #" . $post_id );
            }
            else
            {
                error_log( "CAP to MA - Failed postmeta migration for post #" . $post_id );
            }
        }
    }
    public function get_author_by_term( $term, $author_type = 'user' )
    {
        $author      = null;
        $author_slug = preg_replace( '#^cap\-#', '', $term->slug );

        switch( $author_type )
        {
            case 'guest':
                $author = get_page_by_path( $author_slug, OBJECT, 'guest_author' );
                break;

            case 'user':
            default:
                add_filter( 'authorship/pre_get_user_by', array( Helpers::class, 'short_circuit' ), 10, 2 );
                $author = get_user_by( 'slug', $author_slug );
                remove_filter( 'authorship/pre_get_user_by', array( Helpers::class, 'short_circuit' ), 10 );
                break;
        }

        return $author;
    }
    public function delete_molongui_authorship_meta( $post_id )
    {
        $authors_meta_deleted     = delete_post_meta( $post_id, '_molongui_author' );
        $main_author_meta_deleted = delete_post_meta( $post_id, '_molongui_main_author' );

        return ( !empty( $authors_meta_deleted ) and !empty( $main_author_meta_deleted ) );
    }
    public function set_post_main_author( $post_id, $author_id, $author_type )
    {
        $r = add_post_meta( $post_id, '_molongui_main_author', $author_type . '-' . $author_id, false );

        return !empty( $r );
    }
    public function set_post_author( $post_id, $author_id, $author_type )
    {
        $r = add_post_meta( $post_id, '_molongui_author', $author_type . '-' . $author_id, false );

        return !empty( $r );
    }
    public function get_author_type( $object )
    {
        return 'WP_User' === get_class( $object ) ? 'user' : 'guest';
    }
    public function is_active()
    {
        return is_plugin_active( 'co-authors-plus/co-authors-plus.php' );
    }

} // class
new Co_Authors_Plus();
