<?php
/*!
 * Request-local LRU cache.
 *
 * @author     Molongui
 * @package    Framework
 * @subpackage fw/core/utils
 * @since      3.4.1
 * @version    3.4.1
 */

namespace Molongui\Authorship\Common\Utils;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
final class Request_LRU_Cache
{
    private $enabled = true;
    private $capacity = 5000;
    private $size = 0;
    private $namespace = 'default';
    private $map = array();
    private $head = null;
    private $tail = null;
    private $stats = array
    (
        'hits'      => 0,
        'misses'    => 0,
        'sets'      => 0,
        'evictions' => 0,
    );
    public function __construct( $capacity = 5000, $enabled = true, $namespace = 'default' )
    {
        $this->capacity  = max( 0, (int) $capacity );
        $this->enabled   = (bool) $enabled;
        $this->namespace = (string) $namespace;
    }
    public function get( $segment, $key )
    {
        if ( ! $this->enabled || $this->capacity === 0 )
        {
            return null;
        }

        $fq = $this->fq_key( $segment, $key );

        if ( isset( $this->map[ $fq ] ) )
        {
            if ( isset( $this->map[ $fq ]['value'] ) )
            {
                $this->touch( $fq );
                $this->stats['hits']++;
                return $this->map[$fq]['value'];
            }
            else
            {
                $this->stats['misses']++;
                return null;
            }
        }

        $this->stats['misses']++;
        return null;
    }
    public function has( $segment, $key )
    {
        if ( ! $this->enabled || $this->capacity === 0 )
        {
            return false;
        }

        $fq = $this->fq_key( $segment, $key );

        if ( isset( $this->map[ $fq ] ) )
        {
            $this->touch( $fq ); // maintain recency invariant
            $this->stats['hits']++;
            return true;
        }

        $this->stats['misses']++;
        return false;
    }
    public function set( $segment, $key, $value )
    {
        if ( ! $this->enabled || $this->capacity === 0 )
        {
            return;
        }

        $fq = $this->fq_key( $segment, $key );
        if ( isset( $this->map[ $fq ] ) )
        {
            $this->map[ $fq ]['value'] = $value;
            $this->touch( $fq );
            $this->stats['sets']++;
            return;
        }
        $node = array(
            'k'       => (string) $key,
            'segment' => (string) $segment,
            'value'   => $value,
            'prev'    => null,
            'next'    => $this->head,
        );

        if ( $this->head !== null )
        {
            $this->map[ $this->head ]['prev'] = $fq;
        }

        $this->map[ $fq ] = $node;
        $this->head       = $fq;

        if ( $this->tail === null )
        {
            $this->tail = $fq;
        }

        $this->size++;
        $this->stats['sets']++;
        $this->evict_if_needed();
    }
    public function delete( $segment, $key )
    {
        if ( ! $this->enabled || $this->capacity === 0 )
        {
            return;
        }

        $fq = $this->fq_key( $segment, $key );

        if ( isset( $this->map[ $fq ] ) )
        {
            $this->unlink( $fq );
            unset( $this->map[ $fq ] );
            $this->size--;
        }
    }
    public function clear( $segment = null )
    {
        if ( $segment === null )
        {
            $this->map  = array();
            $this->head = null;
            $this->tail = null;
            $this->size = 0;
            return;
        }
        $fq = $this->head;
        while ( $fq !== null )
        {
            $next = isset( $this->map[ $fq ] ) ? $this->map[ $fq ]['next'] : null;
            if ( isset( $this->map[ $fq ] ) && $this->map[ $fq ]['segment'] === $segment )
            {
                $this->unlink( $fq );
                unset( $this->map[ $fq ] );
                $this->size--;
            }
            $fq = $next;
        }
        if ( $this->head !== null && ! isset( $this->map[ $this->head ] ) )
        {
            $this->head = $this->find_new_head();
        }
        if ( $this->tail !== null && ! isset( $this->map[ $this->tail ] ) )
        {
            $this->tail = $this->find_new_tail();
        }

        if ( $this->size === 0 )
        {
            $this->head = null;
            $this->tail = null;
        }
    }
    public function remember( $segment, $key, $producer )
    {
        $hit = $this->get( $segment, $key );
        if ( $hit !== null )
        {
            return $hit;
        }
        $value = call_user_func( $producer );
        if ( $this->enabled && $this->capacity > 0 )
        {
            $this->set( $segment, $key, $value );
        }

        return $value;
    }
    public function is_enabled()
    {
        return $this->enabled;
    }
    public function set_enabled( $enabled )
    {
        $this->enabled = (bool) $enabled;
    }
    public function get_capacity()
    {
        return $this->capacity;
    }
    public function set_capacity( $capacity )
    {
        $this->capacity = max( 0, (int) $capacity );
        $this->evict_if_needed();
    }
    public function get_size()
    {
        return $this->size;
    }
    public function stats()
    {
        return array(
            'hits'      => (int) $this->stats['hits'],
            'misses'    => (int) $this->stats['misses'],
            'sets'      => (int) $this->stats['sets'],
            'evictions' => (int) $this->stats['evictions'],
            'size'      => (int) $this->size,
            'capacity'  => (int) $this->capacity,
            'namespace' => (string) $this->namespace,
        );
    }
    public function dump( $segment = null )
    {
        $out = array();
        $fq  = $this->tail; // start from LRU

        while ( $fq !== null )
        {
            $n = $this->map[ $fq ];
            if ( $segment === null || $n['segment'] === $segment )
            {
                $out[] = array(
                    'segment' => $n['segment'],
                    'key'     => $n['k'],
                );
            }
            $fq = $n['next'];
        }

        return $out;
    }
    private function fq_key( $segment, $key )
    {
        return (string) $segment . '|' . (string) $key;
    }
    private function touch( $fq )
    {
        if ( $this->head === $fq )
        {
            return;
        }
        if ( ! isset( $this->map[ $fq ] ) )
        {
            return;
        }
        $node = &$this->map[ $fq ];
        $prev = isset( $node['prev'] ) ? $node['prev'] : null;
        $next = isset( $node['next'] ) ? $node['next'] : null;

        if ( $prev !== null && isset( $this->map[ $prev ] ) )
        {
            $this->map[ $prev ]['next'] = $next;
        }
        if ( $next !== null && isset( $this->map[ $next ] ) )
        {
            $this->map[ $next ]['prev'] = $prev;
        }
        if ( $this->tail === $fq )
        {
            $this->tail = $next;
        }
        $node['prev'] = null;
        $node['next'] = $this->head;

        if ( $this->head !== null && isset( $this->map[ $this->head ] ) )
        {
            $this->map[ $this->head ]['prev'] = $fq;
        }

        $this->head = $fq;
        if ( $this->tail === null )
        {
            $this->tail = $fq;
        }
    }
    private function unlink( $fq )
    {
        $node = $this->map[ $fq ];

        $prev = $node['prev'];
        $next = $node['next'];

        if ( $prev !== null )
        {
            $this->map[ $prev ]['next'] = $next;
        }
        if ( $next !== null )
        {
            $this->map[ $next ]['prev'] = $prev;
        }

        if ( $this->head === $fq )
        {
            $this->head = $next;
        }
        if ( $this->tail === $fq )
        {
            $this->tail = $prev;
        }
    }
    private function evict_if_needed()
    {
        while ( $this->size > $this->capacity && $this->tail !== null )
        {
            $fq = $this->tail;
            $next = ( isset( $this->map[ $fq ] ) && isset( $this->map[ $fq ]['next'] ) )
                ? $this->map[ $fq ]['next']
                : null;
            if ( $next !== null && isset( $this->map[ $next ] ) )
            {
                $this->map[ $next ]['prev'] = null;
            }

            $this->tail = $next;
            unset( $this->map[ $fq ] );
            $this->size--;
            $this->stats['evictions']++;
        }
        if ( $this->capacity === 0 && $this->size > 0 )
        {
            $this->clear( null );
        }
        if ( $this->size === 0 )
        {
            $this->head = null;
            $this->tail = null;
        }
    }
    private function find_new_head()
    {
        $fq = $this->tail;
        if ( $fq === null )
        {
            return null;
        }
        while ( isset( $this->map[ $fq ] ) && $this->map[ $fq ]['next'] !== null )
        {
            $fq = $this->map[ $fq ]['next'];
        }
        return $fq;
    }
    private function find_new_tail()
    {
        $fq = $this->head;
        if ( $fq === null )
        {
            return null;
        }
        while ( isset( $this->map[ $fq ] ) && $this->map[ $fq ]['prev'] !== null )
        {
            $fq = $this->map[ $fq ]['prev'];
        }
        return $fq;
    }
} // class
