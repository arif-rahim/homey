<?php

/** Requiere the JWT library. */
use \Firebase\JWT\JWT;

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://enriquechavez.co
 * @since      1.0.0
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @author     Enrique Chavez <noone@tmeister.net>
 */   
class Jwt_Auth_Public
{
    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     *
     * @var string The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     *
     * @var string The current version of this plugin.
     */
    private $version;

    /**
     * The namespace to add to the api calls.
     *
     * @var string The namespace to add to the api call
     */
    private $namespace;

    /**
     * Store errors to display if the JWT is wrong
     *
     * @var WP_Error
     */
    private $jwt_error = null;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     *
     * @param string $plugin_name The name of the plugin.
     * @param string $version     The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->namespace = $this->plugin_name . '/v' . intval($this->version);
    }

    /**
     * Add the endpoints to the API
     */
    public function add_api_routes()
    {
        register_rest_route($this->namespace, 'token', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_token'),
        ));
 
        register_rest_route($this->namespace, 'token/validate', array(
            'methods' => 'POST',
            'callback' => array($this, 'validate_token'),
        ));
         register_rest_route($this->namespace, 'token/register', array(
            'methods' => 'POST',
            'callback' => array($this, 'register_token'),
        ));
         register_rest_route($this->namespace, 'token/retrieve_password', array(
            'methods' => 'POST',
            'callback' => array($this, 'retrieve_password_api'),
        ));
         
         register_rest_route($this->namespace, 'token/get_list', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_list_token'),
        ));
         register_rest_route($this->namespace, 'token/update_password', array(
            'methods' => 'POST',
            'callback' => array($this, 'user_up_password'),
        ));
          register_rest_route($this->namespace, 'token/update_user', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_user_fields'),
        ));
         register_rest_route($this->namespace, 'token/profile_image', array(
            'methods' => 'POST',
            'callback' => array($this, 'upload_image'),
        ));
         register_rest_route($this->namespace, 'token/address_form', array(
            'methods' => 'POST',
            'callback' => array($this, 'user_address_form'),
        ));
         register_rest_route($this->namespace, 'token/emergency_contact', array(
            'methods' => 'POST',
            'callback' => array($this, 'emergency_contact_form'),
        ));
         register_rest_route($this->namespace, 'token/social', array(
            'methods' => 'POST',
            'callback' => array($this, 'social_media_form'),
        ));
         register_rest_route($this->namespace, 'token/user_info', array(
            'methods' => 'Get',
            'callback' => array($this, 'get_user_info'),
        ));
         register_rest_route($this->namespace, 'token/settings_API', array(
            'methods' => 'POST',
            'callback' => array($this, 'action_settings_API'),
        ));
         register_rest_route($this->namespace, 'token/get_settings_API', array(
            'methods' => 'POST',
            'callback' => array($this, 'action_get_settings_API'),
        ));
         register_rest_route($this->namespace, 'search/homey_half_map', array(
            'methods' => 'POST',
            'callback' => array($this, 'homey_half_map_db'),
        ));

         register_rest_route($this->namespace, 'search/check_availability_price', array(
            'methods' => 'GET',
            'callback' => array($this, 'search_availability_db'),
        ));
        
    }

    /**
     * Add CORs suppot to the request.
     */
    public function add_cors_support()
    {
        $enable_cors = defined('JWT_AUTH_CORS_ENABLE') ? JWT_AUTH_CORS_ENABLE : false;
        if ($enable_cors) {
            $headers = apply_filters('jwt_auth_cors_allow_headers', 'Access-Control-Allow-Headers, Content-Type, Authorization');
            header(sprintf('Access-Control-Allow-Headers: %s', $headers));
        }
    }

    /**
     * Get the user and password in the request body and generate a JWT
     *
     * @param [type] $request [description]
     *
     * @return [type] [description]
     */
       public function search_availability_db($request)
    {
        $output = '';
        $prefix = 'homey_';
        $local = homey_get_localization();
        $allowded_html = array();
        $booking_proceed = true;

        $listing_id = intval($request->get_param('listing_id'));
        $check_in_date     =  wp_kses ($request->get_param('check_in_date'), $allowded_html );
        $check_out_date    =  wp_kses ( $request->get_param('check_out_date'), $allowded_html );

        $booking_type = homey_booking_type_by_id( $listing_id );

        if($booking_type != "per_day_date" && strtotime($check_out_date) <= strtotime($check_in_date)) {
            return json_encode(
                array(
                    'success' => false,
                    'message' => $local['ins_book_proceed']
                )
            );
            wp_die();
        }

        $time_difference = abs( strtotime($check_in_date) - strtotime($check_out_date) );
        $days_count      = $time_difference/86400;
        $days_count      = intval($days_count);
        if($booking_type == "per_day_date"){ $days_count += 1; }

        if( $booking_type == 'per_week' ) {

            $min_book_weeks = get_post_meta($listing_id, 'homey_min_book_weeks', true);
            $max_book_weeks = get_post_meta($listing_id, 'homey_max_book_weeks', true);

            $total_weeks_count = $days_count / 7;

            if($total_weeks_count < $min_book_weeks) {
                return json_encode(
                    array(
                        'success' => false,
                        'message' => $local['min_book_weeks_error'].' '.$min_book_weeks
                    )
                );
                wp_die();
            }

            if(($total_weeks_count > $max_book_weeks) && !empty($max_book_weeks)) {
                return json_encode(
                    array(
                        'success' => false,
                        'message' => $local['max_book_weeks_error'].' '.$max_book_weeks
                    )
                );
                wp_die();
            }

        } else if( $booking_type == 'per_month' ) {

            $min_book_months = get_post_meta($listing_id, 'homey_min_book_months', true);
            $max_book_months = get_post_meta($listing_id, 'homey_max_book_months', true);

            $total_months_count = $days_count / 30;

            if($total_months_count < $min_book_months) {
                return json_encode(
                    array(
                        'success' => false,
                        'message' => $local['min_book_months_error'].' '.$min_book_months
                    )
                );
                wp_die();
            }

            if(($total_months_count > $max_book_months) && !empty($max_book_months)) {
                return json_encode(
                    array(
                        'success' => false,
                        'message' => $local['max_book_months_error'].' '.$max_book_months
                    )
                );
                wp_die();
            }

        } else if( $booking_type == 'per_day_date' ) { // per day
            $min_book_days = get_post_meta($listing_id, 'homey_min_book_days', true);
            $max_book_days = get_post_meta($listing_id, 'homey_max_book_days', true);

            if($days_count < $min_book_days) {
                return json_encode(
                    array(
                        'success' => false,
                        'message' => $local['min_book_day_dates_error'].' '.$min_book_days
                    )
                );
                wp_die();
            }

            if(($days_count > $max_book_days) && !empty($max_book_days)) {
                return json_encode(
                    array(
                        'success' => false,
                        'message' => $local['max_book_day_dates_error'].' '.$max_book_days
                    )
                );
                wp_die();
            }
        } else { // Per Night 

            $min_book_days = get_post_meta($listing_id, 'homey_min_book_days', true);
            $max_book_days = get_post_meta($listing_id, 'homey_max_book_days', true);

            if($days_count < $min_book_days) {
                return json_encode(
                    array(
                        'success' => false,
                        'message' => $local['min_book_days_error'].' '.$min_book_days
                    )
                );
                wp_die();
            }

            if(($days_count > $max_book_days) && !empty($max_book_days)) {
                return json_encode(
                    array(
                        'success' => false,
                        'message' => $local['max_book_days_error'].' '.$max_book_days
                    )
                );
                wp_die();
            }
        }

        $reservation_booked_array = get_post_meta($listing_id, 'reservation_dates', true);
        if(empty($reservation_booked_array)) {
            $reservation_booked_array = homey_get_booked_days($listing_id);
        }

        $reservation_pending_array = get_post_meta($listing_id, 'reservation_pending_dates', true);
        if(empty($reservation_pending_array)) {
            $reservation_pending_array = homey_get_booking_pending_days($listing_id);
        }

        $reservation_unavailable_array = get_post_meta($listing_id, 'reservation_unavailable', true);
        if(empty($reservation_unavailable_array)) {
            $reservation_unavailable_array = array();
        }

        $check_in      = new DateTime($check_in_date);
        $check_in_unix = $check_in->getTimestamp();

        $check_out     = new DateTime($check_out_date);

        if($booking_type != "per_day_date"){
            $check_out->modify('yesterday');
        }

        $check_out_unix = $check_out->getTimestamp();

        while ($check_in_unix <= $check_out_unix) {

            if( array_key_exists($check_in_unix, $reservation_booked_array)  || array_key_exists($check_in_unix, $reservation_pending_array) || array_key_exists($check_in_unix, $reservation_unavailable_array) ) {

                return json_encode(
                    array(
                        'success' => false,
                        'message' => $local['dates_not_available']
                    )
                );
                wp_die();

            }
            $check_in->modify('tomorrow');
            $check_in_unix =   $check_in->getTimestamp();
        }
        $reservation_id = array(
            "listing_id" =>$request->get_param('listing_id'),
            "check_in_date" =>$request->get_param('check_in_date'),
            "check_out_date" =>$request->get_param('check_out_date'),
            "guests" =>$request->get_param('guests') ? $request->get_param('guests') :  '0',
            "extra_options" =>$request->get_param('extra_options') ? $request->get_param('extra_options') :  '',
          );
        
        if(empty($reservation_id)) {
            return;
        }
        $extra_options = intval( $reservation_id['extra_options']);

        $listing_id     = intval($reservation_id['listing_id']);
        $check_in_date  = wp_kses ( $reservation_id['check_in_date'], $allowded_html );
        $check_out_date = wp_kses ( $reservation_id['check_out_date'], $allowded_html );
        $guests         = intval($reservation_id['guests']);

        $prices_array = homey_get_prices($check_in_date, $check_out_date, $listing_id, $guests, $extra_options);
        $with_weekend_label = $local['with_weekend_label'];
        if($no_of_days > 1) {
            $night_label = homey_option('glc_day_nights_label');
        } else {
            $night_label = homey_option('glc_day_night_label');
        }

        if($additional_guests > 1) {
            $add_guest_label = $local['cs_add_guests'];
        } else {
            $add_guest_label = $local['cs_add_guest'];
        }
        $array1= $prices_array;
        $array2= json_encode( array( 'success' => true, 'message' => $local['dates_available']));
        $d = array(
            "booking_cost" => $array1,
            "booking_check" => $array2
          );
        $result = $d;
        return $result;
         
         
        wp_die();
    
                       
    }
    public function homey_half_map_db($request)
    {
        global $homey_prefix, $homey_local;

        $homey_prefix = 'homey_';
        $homey_local = homey_get_localization();

        $homey_search_type = homey_search_type();

        $rental_text = $homey_local['rental_label'];
        
        //check_ajax_referer('homey_map_ajax_nonce', 'security');

        $tax_query = array();
        $meta_query = array();
        $allowed_html = array();
        $query_ids = '';

        $cgl_meta = homey_option('cgl_meta');
        $cgl_beds = homey_option('cgl_beds');
        $cgl_baths = homey_option('cgl_baths');
        $cgl_guests = homey_option('cgl_guests');
        $cgl_types = homey_option('cgl_types');
        $price_separator = homey_option('currency_separator');

        $arrive = isset($_POST['arrive']) ? $_POST['arrive'] : '';
        $depart = isset($_POST['depart']) ? $_POST['depart'] : '';
        $guests = isset($_POST['guest']) ? $_POST['guest'] : '';
        $pets = isset($_POST['pets']) ? $_POST['pets'] : -1;
        $bedrooms = isset($_POST['bedrooms']) ? $_POST['bedrooms'] : '';
        $rooms = isset($_POST['rooms']) ? $_POST['rooms'] : '';
        $start_hour = isset($_POST['start_hour']) ? $_POST['start_hour'] : '';
        $end_hour = isset($_POST['end_hour']) ? $_POST['end_hour'] : '';
        $room_size = isset($_POST['room_size']) ? $_POST['room_size'] : '';
        $search_country = isset($_POST['search_country']) ? $_POST['search_country'] : '';
        $search_city = isset($_POST['search_city']) ? $_POST['search_city'] : '';
        $search_area = isset($_POST['search_area']) ? $_POST['search_area'] : '';
        $listing_type = isset($_POST['listing_type']) ? $_POST['listing_type'] : '';
        $search_lat = isset($_POST['search_lat']) ? $_POST['search_lat'] : '';
        $search_lng = isset($_POST['search_lng']) ? $_POST['search_lng'] : '';
        $search_radius = isset($_POST['radius']) ? $_POST['radius'] : 20;

        $paged = isset($_POST['paged']) ? ($_POST['paged']) : '';
        $sort_by = isset($_POST['sort_by']) ? ($_POST['sort_by']) : '';
        $layout = isset($_POST['layout']) ? ($_POST['layout']) : 'list';
        $num_posts = isset($_POST['num_posts']) ? ($_POST['num_posts']) : '9';

        $country = isset($_POST['country']) ? $_POST['country'] : '';
        $state = isset($_POST['state']) ? $_POST['state'] : '';
        $city = isset($_POST['city']) ? $_POST['city'] : '';
        $area = isset($_POST['area']) ? $_POST['area'] : '';
        $booking_type = isset($_POST['booking_type']) ? $_POST['booking_type'] : '';
        $keyword = isset($_POST['keyword']) ? $_POST['keyword'] : '';

        $arrive = homey_search_date_format($arrive);
        $depart = homey_search_date_format($depart);

        $beds_baths_rooms_search = homey_option('beds_baths_rooms_search');
        $search_criteria = '=';
        if( $beds_baths_rooms_search == 'greater') {
            $search_criteria = '>=';
        } elseif ($beds_baths_rooms_search == 'lessthen') {
            $search_criteria = '<=';
        }

        if( !empty($booking_type) ) {
            $homey_search_type = $booking_type;
        }



        $query_args = array(
            'post_type' => 'listing',
            'posts_per_page' => $num_posts,
            'post_status' => 'publish',
            'paged' => $paged,
        );

        $keyword = trim($keyword);
        if (!empty($keyword)) {
            $query_args['s'] = $keyword;
        }
        
        if( !empty( $_POST["optimized_loading"] ) ) {
            $north_east_lat = sanitize_text_field($_POST['north_east_lat']);
            $north_east_lng = sanitize_text_field($_POST['north_east_lng']);
            $south_west_lat = sanitize_text_field($_POST['south_west_lat']);
            $south_west_lng = sanitize_text_field($_POST['south_west_lng']);

            $query_args = apply_filters('homey_optimized_filter', $query_args, $north_east_lat, $north_east_lng, $south_west_lat, $south_west_lng );
        }
        

        if( homey_option('enable_radius') ) {
            if($homey_search_type == 'per_hour') {
                $available_listings_ids = apply_filters('homey_check_hourly_search_availability_filter', $query_args, $arrive, $start_hour, $end_hour);
            } else {
                $available_listings_ids = apply_filters('homey_check_search_availability_filter', $query_args, $arrive, $depart);
            }

            $radius_ids = apply_filters('homey_radius_filter', $query_args, $search_lat, $search_lng, $search_radius);

            if(!empty($available_listings_ids) && !empty($radius_ids)) {
                $query_ids =  array_intersect($available_listings_ids, $radius_ids);

                if(empty($query_ids)) {
                    $query_ids = array(0);
                }

            } elseif(empty($available_listings_ids)) {
                $query_ids = $radius_ids;

            } elseif(empty($radius_ids)) {
                $query_ids = $available_listings_ids;
            }

            if(!empty($query_ids)) {
                $query_args['post__in'] = $query_ids;
            }
        } else {

            if($homey_search_type == 'per_hour') {
                $query_args = apply_filters('homey_check_hourly_search_availability_filter', $query_args, $arrive, $start_hour, $end_hour);
            } else {
                $query_args = apply_filters('homey_check_search_availability_filter', $query_args, $arrive, $depart);
            }

            if(!empty($search_city) || !empty($search_area)) {
                $_tax_query = Array();

                if(!empty($search_city) && !empty($search_area)) {
                    $_tax_query['relation'] = 'AND';
                }

                if(!empty($search_city)) {
                    $_tax_query[] = array(
                        'taxonomy' => 'listing_city',
                        'field' => 'slug',
                        'terms' => $search_city
                    );
                }

                if(!empty($search_area)) {
                    $_tax_query[] = array(
                        'taxonomy' => 'listing_area',
                        'field' => 'slug',
                        'terms' => $search_area
                    );
                }

                $tax_query[] = $_tax_query;
            }

            if(!empty($search_country)) {
                $tax_query[] = array(
                    'taxonomy' => 'listing_country',
                    'field' => 'slug',
                    'terms' => homey_traverse_comma_string($search_country)
                );
            }

        }


        if(!empty($listing_type)) {
            $tax_query[] = array(
                'taxonomy' => 'listing_type',
                'field' => 'slug',
                'terms' => homey_traverse_comma_string($listing_type)
            );
        }

        if(!empty($country)) {
            $tax_query[] = array(
                'taxonomy' => 'listing_country',
                'field' => 'slug',
                'terms' => $country
            );
        }

        if(!empty($state)) {
            $tax_query[] = array(
                'taxonomy' => 'listing_state',
                'field' => 'slug',
                'terms' => $state
            );
        }

        if(!empty($city)) {
            $tax_query[] = array(
                'taxonomy' => 'listing_city',
                'field' => 'slug',
                'terms' => $city
            );
        }

        if(!empty($area)) {
            $tax_query[] = array(
                'taxonomy' => 'listing_area',
                'field' => 'slug',
                'terms' => $area
            );
        }

        // min and max price logic
        if (isset($_POST['min-price']) && !empty($_POST['min-price']) && $_POST['min-price'] != 'any' && isset($_POST['max-price']) && !empty($_POST['max-price']) && $_POST['max-price'] != 'any') {
            $min_price = doubleval(homey_clean($_POST['min-price']));
            $max_price = doubleval(homey_clean($_POST['max-price']));

            if ($min_price > 0 && $max_price > $min_price) {
                $meta_query[] = array(
                    'key' => 'homey_night_price',
                    'value' => array($min_price, $max_price),
                    'type' => 'NUMERIC',
                    'compare' => 'BETWEEN',
                );
            }
        } else if (isset($_POST['min-price']) && !empty($_POST['min-price']) && $_POST['min-price'] != 'any') {
            $min_price = doubleval(homey_clean($_POST['min-price']));
            if ($min_price > 0) {
                $meta_query[] = array(
                    'key' => 'homey_night_price',
                    'value' => $min_price,
                    'type' => 'NUMERIC',
                    'compare' => '>=',
                );
            }
        } else if (isset($_POST['max-price']) && !empty($_POST['max-price']) && $_POST['max-price'] != 'any') {
            $max_price = doubleval(homey_clean($_POST['max-price']));
            if ($max_price > 0) {
                $meta_query[] = array(
                    'key' => 'homey_night_price',
                    'value' => $max_price,
                    'type' => 'NUMERIC',
                    'compare' => '<=',
                );
            }
        }

        if(!empty($guests)) {
            $meta_query[] = array(
                'key' => 'homey_total_guests_plus_additional_guests',
                'value' => intval($guests),
                'type' => 'NUMERIC',
                'compare' => $search_criteria,
            );
        }

        //because this is boolean, no other option other than yes or no
        //$pets = $pets == '' ? 1 : $pets;
        //if(!empty($pets) && $pets != '0') {
        if(!empty($pets) && $pets != -1) {
            $meta_query[] = array(
                'key' => 'homey_pets',
                'value' => $pets,
                'type' => 'NUMERIC',
                'compare' => '=',
            );
        }
//print_r($meta_query);exit;
        if (!empty($bedrooms)) {
            $bedrooms = sanitize_text_field($bedrooms);
            $meta_query[] = array(
                'key' => 'homey_listing_bedrooms',
                'value' => $bedrooms,
                'type' => 'CHAR',
                'compare' => $search_criteria,
            );
        }

        if (!empty($rooms)) {
            $rooms = sanitize_text_field($rooms);
            $meta_query[] = array(
                'key' => 'homey_listing_rooms',
                'value' => $rooms,
                'type' => 'CHAR',
                'compare' => $search_criteria,
            );
        }

        if( !empty($booking_type) ) {
            $meta_query[] = array(
                'key'     => 'homey_booking_type',
                'value'   => $booking_type,
                'compare' => '=',
                'type'    => 'CHAR'
            );
        }

        if (isset($_POST['area']) && !empty($_POST['area'])) {
            if (is_array($_POST['area'])) {
                $areas = $_POST['area'];

                foreach ($areas as $area):
                    $tax_query[] = array(
                        'taxonomy' => 'listing_area',
                        'field' => 'slug',
                        'terms' => homey_traverse_comma_string($area)
                    );
                endforeach;
            }
        }

        if (isset($_POST['amenity']) && !empty($_POST['amenity'])) {
            if (is_array($_POST['amenity'])) {
                $amenities = $_POST['amenity'];

                foreach ($amenities as $amenity):
                    $tax_query[] = array(
                        'taxonomy' => 'listing_amenity',
                        'field' => 'slug',
                        'terms' => $amenity
                    );
                endforeach;
            }
        }

        if (isset($_POST['facility']) && !empty($_POST['facility'])) {
            if (is_array($_POST['facility'])) {
                $facilities = $_POST['facility'];

                foreach ($facilities as $facility):
                    $tax_query[] = array(
                        'taxonomy' => 'listing_facility',
                        'field' => 'slug',
                        'terms' => $facility
                    );
                endforeach;
            }
        }
        
        if(!empty($room_size)) {
            $tax_query[] = array(
                'taxonomy' => 'room_type',
                'field' => 'slug',
                'terms' => homey_traverse_comma_string($room_size)
            );
        }

        if ( $sort_by == 'a_price' ) {
            $query_args['orderby'] = 'meta_value_num';
            $query_args['meta_key'] = 'homey_night_price';
            $query_args['order'] = 'ASC';
        } else if ( $sort_by == 'd_price' ) {
            $query_args['orderby'] = 'meta_value_num';
            $query_args['meta_key'] = 'homey_night_price';
            $query_args['order'] = 'DESC';
        } else if ( $sort_by == 'a_rating' ) {
            $query_args['orderby'] = 'meta_value_num';
            $query_args['meta_key'] = 'listing_total_rating';
            $query_args['order'] = 'ASC';
        } else if ( $sort_by == 'd_rating' ) {
            $query_args['orderby'] = 'meta_value_num';
            $query_args['meta_key'] = 'listing_total_rating';
            $query_args['order'] = 'DESC';
        } else if ( $sort_by == 'featured' ) {
            $query_args['meta_key'] = 'homey_featured';
            $query_args['meta_value'] = '1';
        } else if ( $sort_by == 'a_date' ) {
            $query_args['orderby'] = 'date';
            $query_args['order'] = 'ASC';
        } else if ( $sort_by == 'd_date' ) {
            $query_args['orderby'] = 'date';
            $query_args['order'] = 'DESC';
        } else if ( $sort_by == 'featured_top' ) {
            $query_args['orderby'] = 'meta_value date';
            $query_args['meta_key'] = 'homey_featured';
            $query_args['order'] = 'DESC';
        }

        $meta_count = count($meta_query);

        if( $meta_count > 1 ) {
            $meta_query['relation'] = 'AND';
        }
        if( $meta_count > 0 ){
            $query_args['meta_query'] = $meta_query;
        }

        $tax_count = count( $tax_query );

        if( $tax_count > 1 ) {
            $tax_query['relation'] = 'AND';
        }
        if( $tax_count > 0 ){
            $query_args['tax_query'] = $tax_query;
        }

        $query_args = new WP_Query( $query_args );

        $listings = array();

        ob_start();

        $total_listings = $query_args->found_posts;

        if($total_listings > 1) {
            $rental_text = $homey_local['rentals_label'];
        }

        while( $query_args->have_posts() ): $query_args->the_post();

            $listing_id = get_the_ID();
            $address        = get_post_meta( get_the_ID(), $homey_prefix.'listing_address', true );
            $bedrooms       = get_post_meta( get_the_ID(), $homey_prefix.'listing_bedrooms', true );
            $guests         = get_post_meta( get_the_ID(), $homey_prefix.'guests', true );
            $beds           = get_post_meta( get_the_ID(), $homey_prefix.'beds', true );
            $baths          = get_post_meta( get_the_ID(), $homey_prefix.'baths', true );
            $night_price          = get_post_meta( get_the_ID(), $homey_prefix.'night_price', true );
            $location = get_post_meta( get_the_ID(), $homey_prefix.'listing_location',true);
            $lat_long = explode(',', $location);

            $listing_price = homey_get_price_by_id($listing_id);

            $listing_type = wp_get_post_terms( get_the_ID(), 'listing_type', array("fields" => "ids") );

            if($cgl_beds != 1) {
                $bedrooms = '';
            }

            if($cgl_baths != 1) {
                $baths = '';
            }

            if($cgl_guests != 1) {
                $guests = '';
            }

            $lat = $long = '';
            if(!empty($lat_long[0])) {
                $lat = $lat_long[0];
            }

            if(!empty($lat_long[1])) {
                $long = $lat_long[1];
            }

            $listing = new stdClass();

            $listing->id = $listing_id;
            $listing->title = get_the_title();
            $listing->lat = $lat;
            $listing->long = $long;
            $listing->price = homey_formatted_price($listing_price, false, true).'<sub>'.esc_attr($price_separator).homey_get_price_label_by_id($listing_id).'</sub>';
            $listing->address = $address;
            $listing->bedrooms = $bedrooms;
            $listing->guests = $guests;
            $listing->beds = $beds;
            $listing->baths = $baths;
            
            if($cgl_types != 1) {
                $listing->listing_type = '';
            } else {
                $listing->listing_type = homey_taxonomy_simple('listing_type');
            }
            $listing->thumbnail = get_the_post_thumbnail( $listing_id, 'homey-listing-thumb',  array('class' => 'img-responsive' ) );
            $listing->url = get_permalink();

            $listing->icon = get_template_directory_uri() . '/images/custom-marker.png';

            $listing->retinaIcon = get_template_directory_uri() . '/images/custom-marker.png';

            if(!empty($listing_type)) {
                foreach( $listing_type as $term_id ) {

                    $listing->term_id = $term_id;

                    $icon_id = get_term_meta($term_id, 'homey_marker_icon', true);
                    $retinaIcon_id = get_term_meta($term_id, 'homey_marker_retina_icon', true);

                    $icon = wp_get_attachment_image_src( $icon_id, 'full' );
                    $retinaIcon = wp_get_attachment_image_src( $retinaIcon_id, 'full' );

                    if( !empty($icon['0']) ) {
                        $listing->icon = $icon['0'];
                    } 
                    if( !empty($retinaIcon['0']) ) {
                        $listing->retinaIcon = $retinaIcon['0'];
                    } 
                }
            }

            array_push($listings, $listing);

            if($layout == 'card') {
                get_template_part('template-parts/listing/listing-card');
            } else {
                get_template_part('template-parts/listing/listing-item');
            }

        endwhile;

        wp_reset_postdata();

        homey_pagination_halfmap( $query_args->max_num_pages, $paged, $range = 2 );

        $listings_html = ob_get_contents();
        ob_end_clean();

        if( count($listings) > 0 ) {
            echo json_encode( array( 'getListings' => true, 'listings' => $listings, 'total_results' => $total_listings.' '.$rental_text, 'listingHtml' => $listings_html ) );
            exit();
        } else {
            echo json_encode( array( 'getListings' => false, 'total_results' => $total_listings.' '.$rental_text ) );
            exit();
        }
        die();
   
    }
    public function generate_token($request)
    {
        $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;
        $username = $request->get_param('username');
        $password = $request->get_param('password');

        /** First thing, check the secret key if not exist return a error*/
        if (!$secret_key) {
            return new WP_Error(
                'jwt_auth_bad_config',
                __('JWT is not configurated properly, please contact the admin', 'wp-api-jwt-auth'),
                array(
                    'status' => 403,
                )
            );
        }
        /** Try to authenticate the user with the passed credentials*/
        $user = wp_authenticate($username, $password);

        /** If the authentication fails return a error*/
        if (is_wp_error($user)) {
            $error_code = $user->get_error_code();
            return new WP_Error(
                '[jwt_auth] ' . $error_code,
                $user->get_error_message($error_code),
                array(
                    'status' => 403,
                )
            );
        }

        /** Valid credentials, the user exists create the according Token */
        $issuedAt = time();
        $notBefore = apply_filters('jwt_auth_not_before', $issuedAt, $issuedAt);
        $expire = apply_filters('jwt_auth_expire', $issuedAt + (DAY_IN_SECONDS * 7), $issuedAt);

        $token = array(
            'iss' => get_bloginfo('url'),
            'iat' => $issuedAt,
            'nbf' => $notBefore,
            'exp' => $expire,
            'data' => array(
                'user' => array(
                    'id' => $user->data->ID,
                ),
            ),
        );

        /** Let the user modify the token data before the sign. */
        $token = JWT::encode(apply_filters('jwt_auth_token_before_sign', $token, $user), $secret_key);

        /** The token is signed, now create the object with no sensible user data to the client*/
        $data = array(
            'token' => $token,
            'user_email' => $user->data->user_email,
            'user_nicename' => $user->data->user_nicename,
            'user_display_name' => $user->data->display_name,
        );

        /** Let the user modify the data before send it back */
        return apply_filters('jwt_auth_token_before_dispatch', $data, $user);
    }
    public function action_settings_API($request)
    {
    
        $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;
        /** First thing, check the secret key if not exist return a error*/
        if (!$secret_key) {
            return new WP_Error(
                'jwt_auth_bad_config',
                __('JWT is not configurated properly, please contact the admin', 'wp-api-jwt-auth'),
                array(
                    'status' => 403,
                )
            );
        } 
            $color_cheme= $request->get_param('name');
            $color_code=$request->get_param('value');

            update_option($color_cheme, $color_code);
            // get an option
           $option = get_option($color_cheme);
        /*
          // array of options
          $data_r = array('title' => 'hello world!', 1, false );
          // add a new option
          add_option('wporg_custom_option', $data_r);
          // get an option
          $options_r = get_option('wporg_custom_option');
          // output the title
          echo esc_html($options_r['title']);*/
       
        if ( ! $option || is_wp_error( $option ) ) {
            return new WP_Error(
                'jwt_auth_bad_config',
                __('<strong>Error</strong>: Could not register you&hellip; please contact the <a href="mailto:%s">site admin</a>!'),
                array(
                    'status' => 406,
                )
            );
            }
        
      return $option;
    }
    public function action_get_settings_API($request)
    {
    
        $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;
        /** First thing, check the secret key if not exist return a error*/
        if (!$secret_key) {
            return new WP_Error(
                'jwt_auth_bad_config',
                __('JWT is not configurated properly, please contact the admin', 'wp-api-jwt-auth'),
                array(
                    'status' => 403,
                )
            );
        } 
            $color_cheme= $request->get_param('name');
            $option = get_option($color_cheme);
       
        if ( ! $option || is_wp_error( $option ) ) {
            return new WP_Error(
                'jwt_auth_bad_config',
                __('<strong>Error</strong>: Could not register you&hellip; please contact the <a href="mailto:%s">site admin</a>!'),
                array(
                    'status' => 406,
                )
            );
            }
        
      return $option;
    }
    function username_exists( $username ) 
          {
              $user = get_user_by( 'login', $username );
              if ( $user ) {
                  $user_id = $user->ID;
              } else {
                  $user_id = false;
              }
         return apply_filters( 'username_exists', $user_id, $username );
          }
    public function register_token($request)
    {
    
        $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;
        $user_name = $request->get_param('username');
        $user_email = $request->get_param('useremail');
        $password = $request->get_param('password');
        $meta_key="phone_number";
        $meta_value=$request->get_param('phone_number');
        $unique=false;
        /** First thing, check the secret key if not exist return a error*/
        if (!$secret_key) {
            return new WP_Error(
                'jwt_auth_bad_config',
                __('JWT is not configurated properly, please contact the admin', 'wp-api-jwt-auth'),
                array(
                    'status' => 403,
                )
            );
        } 

        if ( username_exists( $user_name ) ) {
        return new WP_Error(
                'jwt_auth_bad_config',
                __('<strong>Error</strong>: This username is already registered. Please choose another one.'),
                array(
                    'status' => 405,
                )
            );

        } 
         $user_id = wp_create_user( $user_name,$password,$user_email );
         if($user_id){
            $metas = array(
            'phone_number'=>$request->get_param('phone_number'),
            'user_file'   => $request->get_param('user_file')
              );

              foreach($metas as $key => $value) {
                  add_metadata('user', $user_id, $key, $value, $unique );
              }}

        if ( ! $user_id || is_wp_error( $user_id ) ) {
            return new WP_Error(
                'jwt_auth_bad_config',
                __('<strong>Error</strong>: Could not register you&hellip; please contact the <a href="mailto:%s">site admin</a>!'),
                array(
                    'status' => 406,
                )
            );
            }
        
      return $user_id;
    }

      public function user_up_password($request)
    {
    
        $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;
            $userdata = array('ID' => $request->get_param('user_id'),'user_pass'=>$request->get_param('password'));
        /** First thing, check the secret key if not exist return a error*/
        if (!$secret_key) {
            return new WP_Error(
                'jwt_auth_bad_config',
                __('JWT is not configurated properly, please contact the admin', 'wp-api-jwt-auth'),
                array(
                    'status' => 403,
                )
            );
        } 

        if ( username_exists( $request->get_param('username') ) ) {
        return new WP_Error(
                'jwt_auth_bad_config',
                __('<strong>Error</strong>: This username is already registered. Please choose another one.'),
                array(
                    'status' => 405,
                )
            );

        } 

        //$user_obj = get_userdata( $user_id ); return $user_obj; die();
         $user_id =wp_update_user($userdata);
        if ( ! $user_id || is_wp_error( $user_id ) ) {
            return new WP_Error(
                'jwt_auth_bad_config',
                __('<strong>Error</strong>: Could not register you&hellip; please contact the <a href="mailto:%s">site admin</a>!'),
                array(
                    'status' => 406,
                )
            );
            }
        
      return $user_id;
    }
      public function update_user_fields($request)
    {
    
        $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;
           $userdata = array('ID' => $request->get_param('user_id'),'display_name' => $request->get_param('display_name'));
        /** First thing, check the secret key if not exist return a error*/
        if (!$secret_key) {
            return new WP_Error(
                'jwt_auth_bad_config',
                __('JWT is not configurated properly, please contact the admin', 'wp-api-jwt-auth'),
                array(
                    'status' => 403,
                )
            );
        } 

        if ( username_exists( $request->get_param('username') ) ) {
        return new WP_Error(
                'jwt_auth_bad_config',
                __('<strong>Error</strong>: This username is already registered. Please choose another one.'),
                array(
                    'status' => 405,
                )
            );

        } 

        //$user_obj = get_userdata( $user_id ); return $user_obj; die();
         $user_id =wp_update_user($userdata);
         if($user_id){ 
            $metas = array(
                'first_name'=>$request->get_param('first_name'),
                'last_name'   => $request->get_param('last_name'),
                'homey_native_language'   => $request->get_param('language'),
                'homey_other_language'   => $request->get_param('other_language'),
                'description'   => $request->get_param('bio'),
            );

            foreach($metas as $key => $value) {
                update_user_meta( $user_id, $key, $value );
            }}
        if ( ! $user_id || is_wp_error( $user_id ) ) {
            return new WP_Error(
                'jwt_auth_bad_config',
                __('<strong>Error</strong>: Could not register you&hellip; please contact the <a href="mailto:%s">site admin</a>!'),
                array(
                    'status' => 406,
                )
            );
            }
        
      return $user_id;
    }
     public function get_user_info($request)
    {
    
        $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;
           $userdata = $request->get_param('user_id');
        /** First thing, check the secret key if not exist return a error*/
        if (!$secret_key) {
            return new WP_Error(
                'jwt_auth_bad_config',
                __('JWT is not configurated properly, please contact the admin', 'wp-api-jwt-auth'),
                array(
                    'status' => 403,
                )
            );
        } 
         $user = get_user_by( 'ID', $userdata );
         $array2    = array();
         $all_meta = get_user_meta( $userdata );

          foreach( $all_meta as $key => $meta ) {
             
                  $array2[$key] = $meta[0];
          }
          $array1 = $user->data;  
          $d = array(
            "user" => $array1,
            "meta" => $array2
          );
        $result = $d;//array_merge($array1, $array2);
      
        if ( ! $user || is_wp_error( $user ) ) {
            return new WP_Error(
                'jwt_auth_bad_config',
                __('<strong>Error</strong>: Could not register you&hellip; please contact the <a href="mailto:%s">site admin</a>!'),
                array(
                    'status' => 406,
                )
            );
            }
        
      return $result;
    }

      public function upload_image($request)
    {
    
        $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;
        /** First thing, check the secret key if not exist return a error*/
        if (!$secret_key) {
            return new WP_Error(
                'jwt_auth_bad_config',
                __('JWT is not configurated properly, please contact the admin', 'wp-api-jwt-auth'),
                array(
                    'status' => 403,
                )
            );
        } 

        if ( username_exists( $request->get_param('username') ) ) {
        return new WP_Error(
                'jwt_auth_bad_config',
                __('<strong>Error</strong>: This username is already registered. Please choose another one.'),
                array(
                    'status' => 405,
                )
            );

        } 

        //$user_obj = get_userdata( $user_id ); return $user_obj; die();
         $user_id =  $request->get_param('user_id');//wp_update_user($userdata);
         if($user_id){ 
            $metas = array(
                'homey_author_picture_id'   => $request->get_param('user_file')
            );

            foreach($metas as $key => $value) {
                update_user_meta( $user_id, $key, $value );
            }}
        if ( ! $user_id || is_wp_error( $user_id ) ) {
            return new WP_Error(
                'jwt_auth_bad_config',
                __('<strong>Error</strong>: Could not register you&hellip; please contact the <a href="mailto:%s">site admin</a>!'),
                array(
                    'status' => 406,
                )
            );
            }
        
      return $user_id;
    }

      public function user_address_form($request)
    {
    
        $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;
        /** First thing, check the secret key if not exist return a error*/
        if (!$secret_key) {
            return new WP_Error(
                'jwt_auth_bad_config',
                __('JWT is not configurated properly, please contact the admin', 'wp-api-jwt-auth'),
                array(
                    'status' => 403,
                )
            );
        } 

        if ( username_exists( $request->get_param('username') ) ) {
        return new WP_Error(
                'jwt_auth_bad_config',
                __('<strong>Error</strong>: This username is already registered. Please choose another one.'),
                array(
                    'status' => 405,
                )
            );

        } 

        //$user_obj = get_userdata( $user_id ); return $user_obj; die();
         $user_id =  $request->get_param('user_id');//wp_update_user($userdata);
         if($user_id){ 
            $metas = array(
                'homey_street_address'   => $request->get_param('street_address'),
                'homey_apt_suit'   => $request->get_param('apt_suit'),
                'homey_city'   => $request->get_param('city'),
                'homey_state'   => $request->get_param('state'),
                'homey_zipcode'   => $request->get_param('zipcode'),
                'neighborhood'   => $request->get_param('neighborhood'),
                'homey_neighborhood'   => $request->get_param('country'),
            );

            foreach($metas as $key => $value) {
                update_user_meta( $user_id, $key, $value );
            }}
        if ( ! $user_id || is_wp_error( $user_id ) ) {
            return new WP_Error(
                'jwt_auth_bad_config',
                __('<strong>Error</strong>: Could not register you&hellip; please contact the <a href="mailto:%s">site admin</a>!'),
                array(
                    'status' => 406,
                )
            );
            }
        
      return $user_id;
    }
      public function emergency_contact_form($request)
    {
    
        $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;
        /** First thing, check the secret key if not exist return a error*/
        if (!$secret_key) {
            return new WP_Error(
                'jwt_auth_bad_config',
                __('JWT is not configurated properly, please contact the admin', 'wp-api-jwt-auth'),
                array(
                    'status' => 403,
                )
            );
        } 

        if ( username_exists( $request->get_param('username') ) ) {
        return new WP_Error(
                'jwt_auth_bad_config',
                __('<strong>Error</strong>: This username is already registered. Please choose another one.'),
                array(
                    'status' => 405,
                )
            );

        } 

        //$user_obj = get_userdata( $user_id ); return $user_obj; die();
         $user_id =  $request->get_param('user_id');//wp_update_user($userdata);
         if($user_id){ 
            $metas = array(
                'homey_em_contact_name'   => $request->get_param('em_contact_name'),
                'homey_em_relationship'   => $request->get_param('em_relationship'),
                'homey_em_email'   => $request->get_param('em_email'),
                'homey_em_phone'   => $request->get_param('em_phone')
            );

            foreach($metas as $key => $value) {
                update_user_meta( $user_id, $key, $value );
            }}
        if ( ! $user_id || is_wp_error( $user_id ) ) {
            return new WP_Error(
                'jwt_auth_bad_config',
                __('<strong>Error</strong>: Could not register you&hellip; please contact the <a href="mailto:%s">site admin</a>!'),
                array(
                    'status' => 406,
                )
            );
            }
        
      return $user_id;
    }
      public function social_media_form($request)
    {
    
        $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;
        /** First thing, check the secret key if not exist return a error*/
        if (!$secret_key) {
            return new WP_Error(
                'jwt_auth_bad_config',
                __('JWT is not configurated properly, please contact the admin', 'wp-api-jwt-auth'),
                array(
                    'status' => 403,
                )
            );
        } 

        if ( username_exists( $request->get_param('username') ) ) {
        return new WP_Error(
                'jwt_auth_bad_config',
                __('<strong>Error</strong>: This username is already registered. Please choose another one.'),
                array(
                    'status' => 405,
                )
            );

        } 

        //$user_obj = get_userdata( $user_id ); return $user_obj; die();
         $user_id =  $request->get_param('user_id');//wp_update_user($userdata);
         if($user_id){ 
            $metas = array(
                'homey_author_facebook'   => $request->get_param('facebook'),
                'homey_author_twitter'   => $request->get_param('twitter'),
                'homey_author_linkedin'   => $request->get_param('linkedin'),
                'homey_author_googleplus'   => $request->get_param('googleplus'),
                'homey_author_instagram'   => $request->get_param('instagram'),
                'homey_author_pinterest'   => $request->get_param('pinterest'),
                'homey_author_youtube'   => $request->get_param('youtube'),
                'homey_author_vimeo'   => $request->get_param('vimeo'),
                'homey_author_airbnb'   => $request->get_param('airbnb'),
                'homey_author_trip_advisor'   => $request->get_param('trip_advisor')
            );

            foreach($metas as $key => $value) {
                update_user_meta( $user_id, $key, $value );
            }}
        if ( ! $user_id || is_wp_error( $user_id ) ) {
            return new WP_Error(
                'jwt_auth_bad_config',
                __('<strong>Error</strong>: Could not register you&hellip; please contact the <a href="mailto:%s">site admin</a>!'),
                array(
                    'status' => 406,
                )
            );
            }
        
      return $user_id;
    }
      public function retrieve_password_api($request)
    {
        $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;
        $user_login = $request->get_param('user_login');

        /** First thing, check the secret key if not exist return a error*/
        if (!$secret_key) {
            return new WP_Error(
                'jwt_auth_bad_config',
                __('JWT is not configurated properly, please contact the admin', 'wp-api-jwt-auth'),
                array(
                    'status' => 403,
                )
            );
        }
        /** Try to authenticate the user with the passed credentials*/
        $user = retrieve_password($user_login);
        return $user;
    }



    public function get_list_token($args = null)
    {
        $defaults = array(
        'numberposts'      => 2,
        'category'         => 0,
        'orderby'          => 'date',
        'order'            => 'DESC',
        'include'          => array(),
        'exclude'          => array(),
        'meta_key'         => '',
        'meta_value'       => '',
        'post_type'        => 'post',
        'suppress_filters' => true,);
 
        $parsed_args = wp_parse_args( $args, $defaults );
        if ( empty( $parsed_args['post_status'] ) ) {
            $parsed_args['post_status'] = ( 'attachment' === $parsed_args['post_type'] ) ? 'inherit' : 'publish';
        }
        if ( ! empty( $parsed_args['numberposts'] ) && empty( $parsed_args['posts_per_page'] ) ) {
            $parsed_args['posts_per_page'] = $parsed_args['numberposts'];
        }
        if ( ! empty( $parsed_args['category'] ) ) {
            $parsed_args['cat'] = $parsed_args['category'];
        }
        if ( ! empty( $parsed_args['include'] ) ) {
            $incposts                      = wp_parse_id_list( $parsed_args['include'] );
            $parsed_args['posts_per_page'] = count( $incposts );  // Only the number of posts included.
            $parsed_args['post__in']       = $incposts;
        } elseif ( ! empty( $parsed_args['exclude'] ) ) {
            $parsed_args['post__not_in'] = wp_parse_id_list( $parsed_args['exclude'] );
        }
     
        $parsed_args['ignore_sticky_posts'] = true;
        $parsed_args['no_found_rows']       = true;
     
        $get_posts = new WP_Query;
        return $get_posts->query( $parsed_args );
    }
    /**
     * This is our Middleware to try to authenticate the user according to the
     * token send.
     *
     * @param (int|bool) $user Logged User ID
     *
     * @return (int|bool)
     */



    public function determine_current_user($user)
    {
        /**
         * This hook only should run on the REST API requests to determine
         * if the user in the Token (if any) is valid, for any other
         * normal call ex. wp-admin/.* return the user.
         *
         * @since 1.2.3
         **/
        $rest_api_slug = rest_get_url_prefix();
        $valid_api_uri = strpos($_SERVER['REQUEST_URI'], $rest_api_slug);
        if (!$valid_api_uri) {
            return $user;
        }

        /*
         * if the request URI is for validate the token don't do anything,
         * this avoid double calls to the validate_token function.
         */
        $validate_uri = strpos($_SERVER['REQUEST_URI'], 'token/validate');
        if ($validate_uri > 0) {
            return $user;
        }

        $token = $this->validate_token(false);

        if (is_wp_error($token)) {
            if ($token->get_error_code() != 'jwt_auth_no_auth_header') {
                /** If there is a error, store it to show it after see rest_pre_dispatch */
                $this->jwt_error = $token;
                return $user;
            } else {
                return $user;
            }
        }
        /** Everything is ok, return the user ID stored in the token*/
        return $token->data->user->id;
    }

    /**
     * Main validation function, this function try to get the Autentication
     * headers and decoded.
     *
     * @param bool $output
     *
     * @return WP_Error | Object | Array
     */
    public function validate_token($output = true)
    {
        /*
         * Looking for the HTTP_AUTHORIZATION header, if not present just
         * return the user.
         */
        $auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : false;

        /* Double check for different auth header string (server dependent) */
        if (!$auth) {
            $auth = isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) ? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] : false;
        }

        if (!$auth) {
            return new WP_Error(
                'jwt_auth_no_auth_header',
                'Authorization header not found.',
                array(
                    'status' => 403,
                )
            );
        }

        /*
         * The HTTP_AUTHORIZATION is present verify the format
         * if the format is wrong return the user.
         */
        list($token) = sscanf($auth, 'Bearer %s');
        if (!$token) {
            return new WP_Error(
                'jwt_auth_bad_auth_header',
                'Authorization header malformed.',
                array(
                    'status' => 403,
                )
            );
        }

        /** Get the Secret Key */
        $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;
        if (!$secret_key) {
            return new WP_Error(
                'jwt_auth_bad_config',
                'JWT is not configurated properly, please contact the admin',
                array(
                    'status' => 403,
                )
            );
        }

        /** Try to decode the token */
        try {
            $token = JWT::decode($token, $secret_key, array('HS256'));
            /** The Token is decoded now validate the iss */
            if ($token->iss != get_bloginfo('url')) {
                /** The iss do not match, return error */
                return new WP_Error(
                    'jwt_auth_bad_iss',
                    'The iss do not match with this server',
                    array(
                        'status' => 403,
                    )
                );
            }
            /** So far so good, validate the user id in the token */
            if (!isset($token->data->user->id)) {
                /** No user id in the token, abort!! */
                return new WP_Error(
                    'jwt_auth_bad_request',
                    'User ID not found in the token',
                    array(
                        'status' => 403,
                    )
                );
            }
            /** Everything looks good return the decoded token if the $output is false */
            if (!$output) {
                return $token;
            }
            /** If the output is true return an answer to the request to show it */
            return array(
                'code' => 'jwt_auth_valid_token',
                'data' => array(
                    'status' => 200,
                ),
            );
        } catch (Exception $e) {
            /** Something is wrong trying to decode the token, send back the error */
            return new WP_Error(
                'jwt_auth_invalid_token',
                $e->getMessage(),
                array(
                    'status' => 403,
                )
            );
        }
    }

    /**
     * Filter to hook the rest_pre_dispatch, if the is an error in the request
     * send it, if there is no error just continue with the current request.
     *
     * @param $request
     */
    public function rest_pre_dispatch($request)
    {
        if (is_wp_error($this->jwt_error)) {
            return $this->jwt_error;
        }
        return $request;
    }
}
