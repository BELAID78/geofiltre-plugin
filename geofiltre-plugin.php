<?php
/*
Plugin Name: Geofiltre Plugin
Description: Plugin pour ajouter une recherche basée sur la géolocalisation.
Version: 1.0
Author: BELAID Yasser
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Check if ACF is installed and active
if (!class_exists('ACF')) {
    add_action('admin_notices', 'geofiltre_acf_missing_notice');
    add_action('admin_init', 'geofiltre_deactivate_plugin');
    return;
}

function geofiltre_acf_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php _e('Le plugin Geofiltre nécessite le plugin Advanced Custom Fields (ACF) pour fonctionner. Veuillez installer et activer ACF.', 'geofiltre-plugin'); ?></p>
    </div>
    <?php
}

function geofiltre_deactivate_plugin() {
    deactivate_plugins(plugin_basename(__FILE__));
}

// Enqueue Scripts and Styles
function geofiltre_enqueue_scripts() {
    wp_enqueue_script('geofiltre-custom-scripts', plugin_dir_url(__FILE__) . 'assets/js/custom-scripts.js', array('jquery'), null, true);
    wp_enqueue_style('geofiltre-custom-styles', plugin_dir_url(__FILE__) . 'assets/css/custom-styles.css');
    wp_enqueue_script('geofiltre-google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . get_option('geofiltre_google_maps_api_key') . '&libraries=places', array(), null, true);
}
add_action('wp_enqueue_scripts', 'geofiltre_enqueue_scripts');

// Enqueue Admin Scripts
function geofiltre_enqueue_admin_scripts() {
    wp_enqueue_script('geofiltre-acf-geo', plugin_dir_url(__FILE__) . 'assets/js/acf-geo.js', array('jquery'), null, true);
}
add_action('admin_enqueue_scripts', 'geofiltre_enqueue_admin_scripts');

// Register Shortcodes
function geofiltre_register_shortcodes() {
    add_shortcode('search_form', 'geofiltre_search_form');
}
add_action('init', 'geofiltre_register_shortcodes');

// Search Form Shortcode
function geofiltre_search_form() {
    $default_radius = get_option('geofiltre_default_radius', 5);
    $min_radius = get_option('geofiltre_min_radius', 1);
    $max_radius = get_option('geofiltre_max_radius', 200);
    $address_placeholder = get_option('geofiltre_address_placeholder', 'Entrez une adresse');
    $geolocate_button_text = get_option('geofiltre_geolocate_button_text', 'Utiliser ma géolocalisation');
    $search_button_text = get_option('geofiltre_search_button_text', 'Rechercher');

    ob_start();
    ?>
    <div class="search-form-container">
        <form id="search-form">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="text" id="address" name="address" placeholder="<?php echo esc_attr($address_placeholder); ?>" required>
                <button type="button" id="geolocate" class="geolocate-btn">
                    <span>📍</span> <?php echo esc_html($geolocate_button_text); ?>
                </button>
            </div>
            <input type="hidden" id="latitude" name="latitude">
            <input type="hidden" id="longitude" name="longitude">
            <div>
                <input type="range" id="radius" name="radius" min="<?php echo esc_attr($min_radius); ?>" max="<?php echo esc_attr($max_radius); ?>" value="<?php echo esc_attr($default_radius); ?>" oninput="document.getElementById('range-value').textContent = this.value">
                <span id="range-value" class="range-value"><?php echo esc_html($default_radius); ?></span> km
            </div>
            <button type="submit" id="search-button"><?php echo esc_html($search_button_text); ?></button>
        </form>
    </div>
    <div id="search-results" class="search-results"></div>
    <?php
    return ob_get_clean();
}

// Search Function
function geofiltre_calculate_distance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371; // Earth's radius in kilometers

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
         sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));

    return $earth_radius * $c;
}

function geofiltre_search_results($request) {
    $lat = sanitize_text_field($request->get_param('lat'));
    $lng = sanitize_text_field($request->get_param('lng'));
    $radius = (int) $request->get_param('radius');

    $articles = get_posts(array('post_type' => 'post', 'numberposts' => -1));
    $filtered_articles = array();

    foreach ($articles as $article) {
        $address_field = get_field('adresse', $article->ID);
        if ($address_field) {
            $article_lat = $address_field['lat'];
            $article_lng = $address_field['lng'];

            $distance = geofiltre_calculate_distance($lat, $lng, $article_lat, $article_lng);
            if ($distance <= $radius) {
                $filtered_articles[] = array(
                    'title' => get_the_title($article->ID),
                    'link' => get_permalink($article->ID),
                    'distance' => $distance
                );
            }
        }
    }

    return new WP_REST_Response($filtered_articles, 200);
}

function geofiltre_register_api_routes() {
    register_rest_route('geofiltre/v1', '/search', array(
        'methods' => 'GET',
        'callback' => 'geofiltre_search_results',
    ));
}
add_action('rest_api_init', 'geofiltre_register_api_routes');

// Admin Page
function geofiltre_add_admin_menu() {
    add_menu_page(
        'Geofiltre Settings',
        'Geofiltre',
        'manage_options',
        'geofiltre',
        'geofiltre_settings_page',
        'dashicons-admin-generic'
    );
}
add_action('admin_menu', 'geofiltre_add_admin_menu');

function geofiltre_settings_page() {
    ?>
    <div class="wrap">
        <h1>Geofiltre Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('geofiltre_settings_group');
            do_settings_sections('geofiltre');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function geofiltre_register_settings() {
    register_setting('geofiltre_settings_group', 'geofiltre_google_maps_api_key');
    register_setting('geofiltre_settings_group', 'geofiltre_default_radius');
    register_setting('geofiltre_settings_group', 'geofiltre_min_radius');
    register_setting('geofiltre_settings_group', 'geofiltre_max_radius');
    register_setting('geofiltre_settings_group', 'geofiltre_address_placeholder');
    register_setting('geofiltre_settings_group', 'geofiltre_geolocate_button_text');
    register_setting('geofiltre_settings_group', 'geofiltre_search_button_text');

    add_settings_section('geofiltre_settings_section', '', null, 'geofiltre');

    add_settings_field('geofiltre_google_maps_api_key', 'Google Maps API Key', 'geofiltre_google_maps_api_key_render', 'geofiltre', 'geofiltre_settings_section');
    add_settings_field('geofiltre_default_radius', 'Default Radius', 'geofiltre_default_radius_render', 'geofiltre', 'geofiltre_settings_section');
    add_settings_field('geofiltre_min_radius', 'Minimum Radius', 'geofiltre_min_radius_render', 'geofiltre', 'geofiltre_settings_section');
    add_settings_field('geofiltre_max_radius', 'Maximum Radius', 'geofiltre_max_radius_render', 'geofiltre', 'geofiltre_settings_section');
    add_settings_field('geofiltre_address_placeholder', 'Address Placeholder', 'geofiltre_address_placeholder_render', 'geofiltre', 'geofiltre_settings_section');
    add_settings_field('geofiltre_geolocate_button_text', 'Geolocate Button Text', 'geofiltre_geolocate_button_text_render', 'geofiltre', 'geofiltre_settings_section');
    add_settings_field('geofiltre_search_button_text', 'Search Button Text', 'geofiltre_search_button_text_render', 'geofiltre', 'geofiltre_settings_section');
}
add_action('admin_init', 'geofiltre_register_settings');

function geofiltre_google_maps_api_key_render() {
    $value = get_option('geofiltre_google_maps_api_key');
    ?>
    <input type="text" name="geofiltre_google_maps_api_key" value="<?php echo esc_attr($value); ?>" size="50">
    <?php
}

function geofiltre_default_radius_render() {
    $value = get_option('geofiltre_default_radius', 5);
    ?>
    <input type="number" name="geofiltre_default_radius" value="<?php echo esc_attr($value); ?>" min="1" max="200">
    <?php
}

function geofiltre_min_radius_render() {
    $value = get_option('geofiltre_min_radius', 1);
    ?>
    <input type="number" name="geofiltre_min_radius" value="<?php echo esc_attr($value); ?>" min="1" max="200">
    <?php
}

function geofiltre_max_radius_render() {
    $value = get_option('geofiltre_max_radius', 200);
    ?>
    <input type="number" name="geofiltre_max_radius" value="<?php echo esc_attr($value); ?>" min="1" max="200">
    <?php
}

function geofiltre_address_placeholder_render() {
    $value = get_option('geofiltre_address_placeholder', 'Entrez une adresse');
    ?>
    <input type="text" name="geofiltre_address_placeholder" value="<?php echo esc_attr($value); ?>" size="50">
    <?php
}

function geofiltre_geolocate_button_text_render() {
    $value = get_option('geofiltre_geolocate_button_text', 'Utiliser ma géolocalisation');
    ?>
    <input type="text" name="geofiltre_geolocate_button_text" value="<?php echo esc_attr($value); ?>" size="50">
    <?php
}

function geofiltre_search_button_text_render() {
    $value = get_option('geofiltre_search_button_text', 'Rechercher');
    ?>
    <input type="text" name="geofiltre_search_button_text" value="<?php echo esc_attr($value); ?>" size="50">
    <?php
}
?>
