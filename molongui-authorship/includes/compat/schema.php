<?php
/*!
 * Reusable helpers to build Schema.org author entities from Molongui authors, for any SEO plugin integration.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/compat
 * @since      5.2.0
 */

namespace Molongui\Authorship\Compat;

use Molongui\Authorship\Author;
use Molongui\Authorship\Common\Utils\Debug;
use Molongui\Authorship\Common\Utils\Helpers;
use Molongui\Authorship\Post;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
trait Schema
{
    protected function build_author_data_from_object( $author )
    {
        $type         = $author->get_type();
        $name         = $author->get_display_name();
        $url          = $author->get_archive_url();
        $avatar       = $author->get_avatar( 'full', 'url' );
        $description  = $author->get_description();
        $social       = $author->get_social();
        $employer     = $author->get_meta( 'company' );
        $employer_url = $author->get_meta( 'company_link' );
        $job_title    = $author->get_meta( 'job' );
        $is_org       = false; // Reserved for future use when authors can be organizations.

        $works_for = array();

        if ( ! empty( $employer ) || ! empty( $employer_url ) )
        {
            $works_for['name'] = ! empty( $employer ) ? $employer : '';
            $works_for['url']  = ! empty( $employer_url ) ? $employer_url : '';
        }

        $item = array
        (
            'type'            => ! empty( $type ) ? $type : 'user',
            'name'            => ! empty( $name ) ? $name : '',
            'url'             => Helpers::is_valid_url( $url ) ? $url : '',
            'image'           => ! empty( $avatar ) ? $avatar : '',
            'description'     => ! empty( $description ) ? $description : '',
            'sameAs'          => ! empty( $social ) && is_array( $social ) ? $social : array(),
            'worksFor'        => ! empty( $works_for ) && is_array( $works_for ) ? $works_for : array(),
            'jobTitle'        => ! empty( $job_title ) ? $job_title : '',
            'is_organization' => ! empty( $is_org ),
        );
        $item = $this->clean_schema_array( $item );
        if ( empty( $item['name'] ) )
        {
            return array();
        }

        return $item;
    }
    protected function get_post_authors_for_schema( $post_id )
    {
        $authors = array();

        if ( ! method_exists( Post::class, 'get_authors' ) )
        {
            return $authors;
        }
        $post_authors = Post::get_authors( $post_id );

        if ( empty( $post_authors ) )
        {
            return $authors;
        }

        foreach ( $post_authors as $post_author )
        {
            if ( empty( $post_author->ID ) || empty( $post_author->type ) )
            {
                continue;
            }

            $author = new Author( (int) $post_author->ID, $post_author->type );

            $item = $this->build_author_data_from_object( $author );

            if ( ! empty( $item ) )
            {
                $authors[] = $item;
            }
        }

        return $authors;
    }
    protected function should_override_schema_author_data( array $authors )
    {
        if ( empty( $authors ) )
        {
            return false;
        }

        $has_guest = false;

        foreach ( $authors as $author )
        {
            if ( isset( $author['type'] ) && 'guest' === $author['type'] )
            {
                $has_guest = true;
                break;
            }
        }

        if ( $has_guest )
        {
            return true;
        }

        return ( count( $authors ) > 1 );
    }
    protected function normalize_authors_for_schema( array $authors )
    {
        $schema_authors = array();

        foreach ( $authors as $author )
        {
            $schema_author = $this->build_schema_author_entity( $author );

            if ( ! empty( $schema_author ) )
            {
                $schema_authors[] = $schema_author;
            }
        }

        return $schema_authors;
    }
    protected function build_schema_author_entity( array $author )
    {
        $name = isset( $author['name'] ) ? $author['name'] : '';

        if ( '' === $name )
        {
            return array();
        }

        $url    = isset( $author['url'] ) ? $author['url'] : '';
        $is_org = ! empty( $author['is_organization'] );

        $schema_author = array
        (
            '@type' => $is_org ? 'Organization' : 'Person',
            'name'  => $name,
        );

        if ( $url )
        {
            $fragment = $is_org ? '#organization' : '#author';

            $schema_author['url'] = $url;
            $schema_author['@id'] = trailingslashit( $url ) . $fragment;
        }

        if ( ! empty( $author['image'] ) )
        {
            $schema_author['image'] = array
            (
                '@type' => 'ImageObject',
                'url'   => $author['image'],
            );
        }

        if ( ! empty( $author['description'] ) )
        {
            $schema_author['description'] = $author['description'];
        }

        if ( ! empty( $author['sameAs'] ) && is_array( $author['sameAs'] ) )
        {
            $schema_author['sameAs'] = array_values( array_filter( $author['sameAs'] ) );
        }

        if ( ! empty( $author['worksFor'] ) && is_array( $author['worksFor'] ) )
        {
            $works_for_name = isset( $author['worksFor']['name'] ) ? $author['worksFor']['name'] : '';
            $works_for_url  = isset( $author['worksFor']['url'] )  ? $author['worksFor']['url']  : '';

            if ( $works_for_name )
            {
                $schema_author['worksFor'] = array
                (
                    '@type' => 'Organization',
                    'name'  => $works_for_name,
                );

                if ( $works_for_url )
                {
                    $schema_author['worksFor']['url'] = $works_for_url;
                    $schema_author['worksFor']['@id'] = trailingslashit( $works_for_url ) . '#organization';
                }
            }
        }

        if ( ! empty( $author['jobTitle'] ) )
        {
            $schema_author['jobTitle'] = $author['jobTitle'];
        }
        $schema_author = $this->clean_schema_array( $schema_author );

        return $schema_author;
    }
    protected function apply_schema_authors_to_graph( array $graph, array $schema_authors )
    {
        if ( empty( $schema_authors ) )
        {
            return $graph;
        }
        $main_author = $schema_authors[0];

        foreach ( $graph as $key => $schema )
        {
            if ( ! is_array( $schema ) || empty( $schema['@type'] ) )
            {
                continue;
            }

            $type = $schema['@type'];
            if ( in_array( $type, array( 'Article', 'BlogPosting' ), true ) )
            {
                $graph[ $key ]['author'] = ( 1 === count( $schema_authors ) )
                    ? $main_author
                    : $schema_authors;
            }
            if ( 'Person' === $type )
            {
                $graph[ $key ] = $main_author;
            }
            if ( 'ProfilePage' === $type )
            {
                if ( isset( $schema['author'] ) && is_array( $schema['author'] ) )
                {
                    $schema['author'] = $main_author;
                }
                else
                {
                }

                $graph[ $key ] = $schema;
            }
        }

        return $graph;
    }
    protected function clean_schema_array( array $array )
    {
        foreach ( $array as $key => $value )
        {
            if ( $value === null || $value === '' || $value === array() )
            {
                unset( $array[ $key ] );
                continue;
            }
            if ( is_array( $value ) )
            {
                $array[ $key ] = $this->clean_schema_array( $value );
                if ( empty( $array[ $key ] ) )
                {
                    unset( $array[ $key ] );
                }
            }
        }

        return $array;
    }

}
