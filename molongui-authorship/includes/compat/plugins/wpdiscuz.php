<?php

use Molongui\Authorship\Author;
defined( 'ABSPATH' ) or exit;
add_filter( 'authorship/pre_get_user_by', function( $user, $original_user, $field, $value )
{
    $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
    $fn    = 'getCurrentUser';
    $class = 'WpdiscuzHelper';

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) )
         and
         isset( $dbt[$i]['class'] ) and ( $dbt[$i]['class'] === $class ) )
    {
        return $original_user;
    }

    return $user;
}, 10, 4 );
add_filter( '_authorship/get_avatar_data/filter/author', function( $author, $id_or_email, $dbt )
{
    $fn    = 'renderFrontForm';
    $class = 'wpdFormAttr\Form';
    $file  = '/wpdiscuz/forms/wpDiscuzForm.php';
    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) )
    {
        if ( isset( $dbt[$i]['class'] ) and $dbt[$i]['class'] == $class )
        {
            if ( is_int( $id_or_email ) )
            {
                if ( !is_object( $author->object ) ) $author->object = new WP_User();
                $author->object->ID = $id_or_email;
                $author->id         = $id_or_email;
                $author->type       = 'user';
            }
        }
    }
    return $author;
}, 10, 3 );
add_filter( 'wpdiscuz_comment_author', function( $authorName, $comment )
{
    return ( $comment->comment_author ? $comment->comment_author : __( 'Anonymous', 'wpdiscuz' ) );
}, 99, 2 );
add_filter( 'get_comment_author_url', function( $commentAuthorUrl, $comment_id, $comment )
{
    $email = $comment->comment_author_email;
    if ( !$email ) return $commentAuthorUrl;
    if ( $guest = molongui_get_author_by( '_molongui_guest_author_mail', $email, 'guest' ) )
    {
        $author = new Author( $guest->ID, 'guest' );
        $commentAuthorUrl = $author->get_url();
    }
    return $commentAuthorUrl;

}, 10, 3 );
add_filter( 'wpdiscuz_profile_url', function( $profileUrl, $user )
{
    return '';
}, 10, 2 );
add_filter( 'wpdiscuz_author_avatar_field', function( $authorAvatarField, $comment, $user, $profileUrl )
{
    return $comment->comment_author_email ? $comment->comment_author_email : $authorAvatarField;
}, 10, 4 );
add_filter( 'authorship/pre_get_user_by', function( $user, $original_user, $field, $value )
{
    $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
    $fn    = 'start_el';
    $class = 'WpdiscuzWalker';

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) )
         and
         isset( $dbt[$i]['class'] ) and ( $dbt[$i]['class'] === $class ) )
    {
        return $original_user;
    }

    return $user;
}, 10, 4 );