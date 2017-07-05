<?php

add_action( 'rest_api_init', function() {
    $ns = 'headless/v1';

    register_rest_route( $ns, '/frontpage', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => function() {
            $page_id = get_option('page_on_front');
            if ( $page_id > 0 ) {
                wp_redirect( get_rest_url() . 'wp/v2/pages/' . $page_id );
                exit;
            }

            return new WP_Error(
                'headless_no_frontpage',
                __( 'No static frontpage set', 'headless' ),
                array(
                    'status' => 404
                )
            );
        }
    ) );

    register_rest_route( $ns, '/menu-locations', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => function() use ($ns) {
            $response       = array();
            $menu_locations = get_registered_nav_menus();
            $locations      = get_nav_menu_locations();

            if ( !empty( $menu_locations ) ) {
                foreach( $menu_locations as $slug => $description ) {
                    $data = array(
                        'slug'        => $slug,
                        'description' => $description,
                    );

                    if ( array_key_exists( $slug, $locations ) ) {
                        $menu_id     = $locations[ $slug ];
                        $menu_object = wp_get_nav_menu_object( $menu_id );
                        $menu_items  = array_map( function( $item ) {
                            return array(
                                'id'         => $item->ID,
                                'date'       => $item->post_date,
                                'date_gmt'   => $item->post_date_gmt,
                                'author'     => (int) $item->post_author,
                                'link'       => $item->url,
                                'title'      => $item->title,
                                'parent'     => (int) $item->menu_item_parent,
                                'menu_order' => $item->menu_order
                            );
                        }, wp_get_nav_menu_items( $menu_id ) );

                        $items = array();
                        $children = array();
                        while ( $item = array_pop($menu_items) ) {
                            if ( array_key_exists( $item['id'], $children ) ) {
                                $item['children'] = array_reverse( $children[ $item['id'] ] );
                            }
                            if ( $item['parent'] == '0' ) {
                                $items[] = $item;
                            } else {
                                $children[ $item['parent'] ][] = $item;
                            }
                        }
                        $items = array_reverse( $items );

                        $data['menu'] = array(
                            'term_id' => $menu_object->term_id,
                            'name'    => $menu_object->name,
                            'slug'    => $menu_object->slug,
                            'count'   => $menu_object->count,
                            'items'   => $items
                        );
                    }

                    $data['_links'] = array(
                        'collection' => array(
                            array(
                                'href' => get_rest_url() . $ns . '/menu-locations'
                            )
                        )
                    );

                    $response[] = $data;
                }
            }

            return $response;
        }
    ) );

    add_filter( 'acf/format_value/type=post_object', 'headless_theme_prepare_post_object_for_response' , 10, 3 );
    function headless_theme_prepare_post_object_for_response( $value, $post_id, $field ) {
        if ( $field['return_format'] !== 'object' ) {
            return;
        }

        if ( is_array( $value ) ) {
            $formatted = array();
            foreach( $value as $post ) {
                $formatted[] = headless_theme_rest_format_post( $post );
            }

            return $formatted;
        } else {
            return headless_theme_rest_format_post( $value );
        }
    };
} );

function headless_theme_rest_format_post( $post ) {
    $dummy_request = new WP_REST_Request( 'GET', '/' );
    $dummy_controller = new WP_REST_Posts_Controller( $post->post_type );
    $response = $dummy_controller->prepare_item_for_response( $post , $dummy_request );

    return $response->data;
}
