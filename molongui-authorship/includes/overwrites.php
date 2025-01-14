<?php
/*!
 * WordPress core function overrides.
 *
 * @internal   Plugin helper functions are not yet available, so they cannot be used in this file.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes
 * @since      4.4.2
 */

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
if ( !function_exists( 'get_user_by' ) )
{
    function get_user_by( $field, $value )
    {
        $userdata = WP_User::get_data_by( $field, $value );

        if ( ! $userdata )
        {
            /*!
             * [PRIVATE] FILTER HOOK
             * For internal use only. This filter may be changed or removed at any time without notice or deprecation.
             * If you choose to use it, you do so at your own risk, as it may cause code issues.
             *
             * @since 4.4.2
             */
            $user = apply_filters( '_authorship/no_userdata', false, $field, $value );

            if ( $user )
            {
                return $user;
            }

            return false;
        }

        $user = new WP_User;
        $user->init( $userdata );

        /*!
         * [PRIVATE] FILTER HOOK
         * For internal use only. This filter may be changed or removed at any time without notice or deprecation.
         * If you choose to use it, you do so at your own risk, as it may cause code issues.
         *
         * @since 4.4.2
         */
        $user = apply_filters( '_authorship/get_user_by', $user, $field, $value );

        return $user;
    }
}