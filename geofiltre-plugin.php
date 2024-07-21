<?php
/*
Plugin Name: Geofiltre Plugin
Description: Plugin pour ajouter une recherche basée sur la géolocalisation.
Version: 2.0
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
    wp_enqueue_style('geofiltre-font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css');

    // Pass the settings to the script
    wp_localize_script('geofiltre-custom-scripts', 'geofiltreSettings', array(
        'errorMessage' => get_option('geofiltre_error_message', 'Tous les champs sont requis.'),
        'geolocateIcon' => get_option('geofiltre_geolocate_icon', '<i class="fa fa-location-arrow" aria-hidden="true" style="color: red;"></i>'),
        'resultsPerPage' => get_option('geofiltre_results_per_page', 10),
        'showThumbnail' => get_option('geofiltre_show_thumbnail', true),
        'showExcerpt' => get_option('geofiltre_show_excerpt', true),
        'showAuthor' => get_option('geofiltre_show_author', true),
        'showDate' => get_option('geofiltre_show_date', true),
        'showDistance' => get_option('geofiltre_show_distance', true),
        'showReviews' => get_option('geofiltre_show_reviews', true),
        'textColor' => get_option('geofiltre_text_color', '#000000'),
        'resultTextColor' => get_option('geofiltre_result_text_color', '#000000'),
        'thumbnailSize' => get_option('geofiltre_thumbnail_size', '100px'),
        'titleCharacterLimit' => get_option('geofiltre_title_character_limit', 50),
        'authorLabel' => get_option('geofiltre_author_label', 'Auteur'),
        'dateLabel' => get_option('geofiltre_date_label', 'Date'),
        'distanceLabel' => get_option('geofiltre_distance_label', 'Distance'),
        'showAvatar' => get_option('geofiltre_show_avatar', true),
        'readMoreText' => get_option('geofiltre_read_more_text', 'Read More »'),
        'resultsFoundText' => get_option('geofiltre_results_found_text', '%d résultats trouvés à proximité de votre adresse'),
        'noResultsText' => get_option('geofiltre_no_results_text', 'Aucun résultat trouvé.')
    ));
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
    $max_radius = get_option('geofiltre_max_radius', 1000);
    $address_placeholder = get_option('geofiltre_address_placeholder', 'Entrez une adresse');
    $geolocate_button_text = get_option('geofiltre_geolocate_button_text', 'Utiliser ma géolocalisation');
    $search_button_text = get_option('geofiltre_search_button_text', 'Rechercher');
    $geolocate_icon = get_option('geofiltre_geolocate_icon', '<i class="fa fa-location-arrow" aria-hidden="true" style="color: red;"></i>');

    ob_start();
    ?>
    <div class="search-form-container">
        <form id="search-form">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="text" id="address" name="address" placeholder="<?php echo esc_attr($address_placeholder); ?>" style="border-radius: 5px;" required>
                <button type="button" id="geolocate" class="geolocate-btn">
                    <?php echo $geolocate_icon; ?> <?php echo esc_html($geolocate_button_text); ?>
                </button>
            </div>
            <input type="hidden" id="latitude" name="latitude">
            <input type="hidden" id="longitude">
            <div class="rangeandsum">
                <input type="range" id="radius" name="radius" min="<?php echo esc_attr($min_radius); ?>" max="<?php echo esc_attr($max_radius); ?>" value="<?php echo esc_attr($default_radius); ?>" oninput="document.getElementById('range-value').textContent = this.value">
                <span id="range-value" class="range-value"><?php echo esc_html($default_radius); ?></span> km
            </div>
            <button type="submit" id="search-button"><?php echo esc_html($search_button_text); ?></button>
        </form>
    </div>
    <div id="search-results-container">
        <div id="search-results" class="search-results"></div>
    </div>
    <?php
    return ob_get_clean();
}

// Set ACF Google Maps API Key
function geofiltre_acf_init() {
    $api_key = get_option('geofiltre_google_maps_api_key');
    if ($api_key) {
        acf_update_setting('google_api_key', $api_key);
    }
}
add_action('acf/init', 'geofiltre_acf_init');

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
    $results_per_page = get_option('geofiltre_results_per_page', 10);
    $paged = (int) $request->get_param('paged');

    $articles = get_posts(array(
        'post_type' => 'post',
        'numberposts' => -1
    ));
    $filtered_articles = array();

    foreach ($articles as $article) {
        $address_field = get_field('adresse', $article->ID);
        if ($address_field) {
            $article_lat = $address_field['lat'];
            $article_lng = $address_field['lng'];

            $distance = geofiltre_calculate_distance($lat, $lng, $article_lat, $article_lng);
            if ($distance <= $radius) {
                $title = get_the_title($article->ID);
                $title_limit = get_option('geofiltre_title_character_limit', 50);
                if (strlen($title) > $title_limit) {
                    $title = substr($title, 0, $title_limit) . '...';
                }
                $filtered_articles[] = array(
                    'title' => $title,
                    'link' => get_permalink($article->ID),
                    'distance' => $distance,
                    'thumbnail' => get_the_post_thumbnail_url($article->ID, 'thumbnail'),
                    'excerpt' => get_the_excerpt($article->ID),
                    'author' => get_the_author_meta('display_name', $article->post_author),
                    'date' => get_the_date('', $article->ID),
                    'reviews' => get_comments_number($article->ID) // Assuming reviews are comments
                );
            }
        }
    }

    // Pagination
    $total_results = count($filtered_articles);
    $total_pages = ceil($total_results / $results_per_page);
    $offset = ($paged - 1) * $results_per_page;
    $paged_articles = array_slice($filtered_articles, $offset, $results_per_page);

    return new WP_REST_Response(array(
        'articles' => $paged_articles,
        'total_pages' => $total_pages,
        'current_page' => $paged,
        'total_results' => $total_results
    ), 200);
}

function geofiltre_register_api_routes() {
    register_rest_route('geofiltre/v1', '/search', array(
        'methods' => 'GET',
        'callback' => 'geofiltre_search_results',
        'args' => array(
            'lat' => array('required' => true),
            'lng' => array('required' => true),
            'radius' => array('required' => true),
            'paged' => array('required' => false, 'default' => 1)
        )
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
        <h2>Shortcode</h2>
        <p>Utilisez le shortcode suivant pour afficher le formulaire de recherche :</p>
        <code>[search_form]</code>
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
    register_setting('geofiltre_settings_group', 'geofiltre_error_message');
    register_setting('geofiltre_settings_group', 'geofiltre_geolocate_icon');
    register_setting('geofiltre_settings_group', 'geofiltre_results_per_page');
    register_setting('geofiltre_settings_group', 'geofiltre_show_thumbnail');
    register_setting('geofiltre_settings_group', 'geofiltre_show_excerpt');
    register_setting('geofiltre_settings_group', 'geofiltre_show_author');
    register_setting('geofiltre_settings_group', 'geofiltre_show_date');
    register_setting('geofiltre_settings_group', 'geofiltre_show_distance');
    register_setting('geofiltre_settings_group', 'geofiltre_show_reviews');
    register_setting('geofiltre_settings_group', 'geofiltre_text_color');
    register_setting('geofiltre_settings_group', 'geofiltre_thumbnail_size');
    register_setting('geofiltre_settings_group', 'geofiltre_title_character_limit');
    register_setting('geofiltre_settings_group', 'geofiltre_author_label');
    register_setting('geofiltre_settings_group', 'geofiltre_date_label');
    register_setting('geofiltre_settings_group', 'geofiltre_distance_label');
    register_setting('geofiltre_settings_group', 'geofiltre_result_text_color');
    register_setting('geofiltre_settings_group', 'geofiltre_show_avatar');
    register_setting('geofiltre_settings_group', 'geofiltre_read_more_text');
    register_setting('geofiltre_settings_group', 'geofiltre_results_found_text');
    register_setting('geofiltre_settings_group', 'geofiltre_no_results_text');

    add_settings_section('geofiltre_settings_section_search', 'Réglages de la Recherche', 'geofiltre_settings_section_search_cb', 'geofiltre');
    add_settings_section('geofiltre_settings_section_results', 'Réglages des Résultats', 'geofiltre_settings_section_results_cb', 'geofiltre');

    add_settings_field('geofiltre_google_maps_api_key', 'Google Maps API Key', 'geofiltre_google_maps_api_key_render', 'geofiltre', 'geofiltre_settings_section_search');
    add_settings_field('geofiltre_default_radius', 'Default Radius', 'geofiltre_default_radius_render', 'geofiltre', 'geofiltre_settings_section_search');
    add_settings_field('geofiltre_min_radius', 'Minimum Radius', 'geofiltre_min_radius_render', 'geofiltre', 'geofiltre_settings_section_search');
    add_settings_field('geofiltre_max_radius', 'Maximum Radius', 'geofiltre_max_radius_render', 'geofiltre', 'geofiltre_settings_section_search');
    add_settings_field('geofiltre_address_placeholder', 'Address Placeholder', 'geofiltre_address_placeholder_render', 'geofiltre', 'geofiltre_settings_section_search');
    add_settings_field('geofiltre_geolocate_button_text', 'Geolocate Button Text', 'geofiltre_geolocate_button_text_render', 'geofiltre', 'geofiltre_settings_section_search');
    add_settings_field('geofiltre_search_button_text', 'Search Button Text', 'geofiltre_search_button_text_render', 'geofiltre', 'geofiltre_settings_section_search');
    add_settings_field('geofiltre_error_message', 'Error Message', 'geofiltre_error_message_render', 'geofiltre', 'geofiltre_settings_section_search');
    add_settings_field('geofiltre_geolocate_icon', 'Geolocate Icon', 'geofiltre_geolocate_icon_render', 'geofiltre', 'geofiltre_settings_section_search', array('label_for' => 'geofiltre_geolocate_icon', 'description' => 'Entrez la balise HTML de l\'icône. Exemple: <code>&lt;i class="fa fa-location-arrow" aria-hidden="true" style="color: red;"&gt;&lt;/i&gt;</code>'));

    add_settings_field('geofiltre_results_per_page', 'Results Per Page', 'geofiltre_results_per_page_render', 'geofiltre', 'geofiltre_settings_section_results');
    add_settings_field('geofiltre_show_thumbnail', 'Show Thumbnail', 'geofiltre_show_thumbnail_render', 'geofiltre', 'geofiltre_settings_section_results');
    add_settings_field('geofiltre_thumbnail_size', 'Thumbnail Size', 'geofiltre_thumbnail_size_render', 'geofiltre', 'geofiltre_settings_section_results');
    add_settings_field('geofiltre_show_excerpt', 'Show Excerpt', 'geofiltre_show_excerpt_render', 'geofiltre', 'geofiltre_settings_section_results');
    add_settings_field('geofiltre_show_author', 'Show Author', 'geofiltre_show_author_render', 'geofiltre', 'geofiltre_settings_section_results');
    add_settings_field('geofiltre_author_label', 'Author Label', 'geofiltre_author_label_render', 'geofiltre', 'geofiltre_settings_section_results');
    add_settings_field('geofiltre_show_date', 'Show Date', 'geofiltre_show_date_render', 'geofiltre', 'geofiltre_settings_section_results');
    add_settings_field('geofiltre_date_label', 'Date Label', 'geofiltre_date_label_render', 'geofiltre', 'geofiltre_settings_section_results');
    add_settings_field('geofiltre_show_distance', 'Show Distance', 'geofiltre_show_distance_render', 'geofiltre', 'geofiltre_settings_section_results');
    add_settings_field('geofiltre_distance_label', 'Distance Label', 'geofiltre_distance_label_render', 'geofiltre', 'geofiltre_settings_section_results');
    add_settings_field('geofiltre_show_reviews', 'Show Reviews', 'geofiltre_show_reviews_render', 'geofiltre', 'geofiltre_settings_section_results');
    add_settings_field('geofiltre_text_color', 'Text Color', 'geofiltre_text_color_render', 'geofiltre', 'geofiltre_settings_section_results');
    add_settings_field('geofiltre_title_character_limit', 'Title Character Limit', 'geofiltre_title_character_limit_render', 'geofiltre', 'geofiltre_settings_section_results');
    add_settings_field('geofiltre_result_text_color', 'Couleur du texte des résultats', 'geofiltre_result_text_color_render', 'geofiltre', 'geofiltre_settings_section_results');
    add_settings_field('geofiltre_show_avatar', 'Show Avatar', 'geofiltre_show_avatar_render', 'geofiltre', 'geofiltre_settings_section_results');
    add_settings_field('geofiltre_read_more_text', 'Read More Text', 'geofiltre_read_more_text_render', 'geofiltre', 'geofiltre_settings_section_results');
    add_settings_field('geofiltre_results_found_text', 'Results Found Text', 'geofiltre_results_found_text_render', 'geofiltre', 'geofiltre_settings_section_results');
    add_settings_field('geofiltre_no_results_text', 'No Results Text', 'geofiltre_no_results_text_render', 'geofiltre', 'geofiltre_settings_section_results');
}
add_action('admin_init', 'geofiltre_register_settings');

function geofiltre_settings_section_search_cb() {
    echo '<p>Réglages pour la section de recherche.</p>';
}

function geofiltre_settings_section_results_cb() {
    echo '<p>Réglages pour la section des résultats de recherche.</p>';
}

function geofiltre_google_maps_api_key_render() {
    $value = get_option('geofiltre_google_maps_api_key');
    ?>
    <input type="text" name="geofiltre_google_maps_api_key" value="<?php echo esc_attr($value); ?>" size="50">
    <?php
}

function geofiltre_default_radius_render() {
    $value = get_option('geofiltre_default_radius', 5);
    ?>
    <input type="number" name="geofiltre_default_radius" value="<?php echo esc_attr($value); ?>" min="1" max="1000">
    <?php
}

function geofiltre_min_radius_render() {
    $value = get_option('geofiltre_min_radius', 1);
    ?>
    <input type="number" name="geofiltre_min_radius" value="<?php echo esc_attr($value); ?>" min="1" max="1000">
    <?php
}

function geofiltre_max_radius_render() {
    $value = get_option('geofiltre_max_radius', 1000);
    ?>
    <input type="number" name="geofiltre_max_radius" value="<?php echo esc_attr($value); ?>" min="1" max="1000">
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

function geofiltre_error_message_render() {
    $value = get_option('geofiltre_error_message', 'Tous les champs sont requis.');
    ?>
    <input type="text" name="geofiltre_error_message" value="<?php echo esc_attr($value); ?>" size="50">
    <?php
}

function geofiltre_geolocate_icon_render($args) {
    $value = get_option('geofiltre_geolocate_icon', '<i class="fa fa-location-arrow" aria-hidden="true" style="color: red;"></i>');
    ?>
    <textarea name="geofiltre_geolocate_icon" rows="3" cols="50"><?php echo esc_textarea($value); ?></textarea>
    <p class="description"><?php echo $args['description']; ?></p>
    <?php
}

function geofiltre_results_per_page_render() {
    $value = get_option('geofiltre_results_per_page', 10);
    ?>
    <input type="number" name="geofiltre_results_per_page" value="<?php echo esc_attr($value); ?>" min="1">
    <?php
}

function geofiltre_show_thumbnail_render() {
    $value = get_option('geofiltre_show_thumbnail', true);
    ?>
    <input type="checkbox" name="geofiltre_show_thumbnail" value="1" <?php checked(1, $value, true); ?>>
    <?php
}

function geofiltre_thumbnail_size_render() {
    $value = get_option('geofiltre_thumbnail_size', '100px');
    ?>
    <input type="text" name="geofiltre_thumbnail_size" value="<?php echo esc_attr($value); ?>" size="10">
    <?php
}

function geofiltre_show_excerpt_render() {
    $value = get_option('geofiltre_show_excerpt', true);
    ?>
    <input type="checkbox" name="geofiltre_show_excerpt" value="1" <?php checked(1, $value, true); ?>>
    <?php
}

function geofiltre_show_author_render() {
    $value = get_option('geofiltre_show_author', true);
    ?>
    <input type="checkbox" name="geofiltre_show_author" value="1" <?php checked(1, $value, true); ?>>
    <?php
}

function geofiltre_author_label_render() {
    $value = get_option('geofiltre_author_label', 'Auteur');
    ?>
    <input type="text" name="geofiltre_author_label" value="<?php echo esc_attr($value); ?>" size="50">
    <?php
}

function geofiltre_show_date_render() {
    $value = get_option('geofiltre_show_date', true);
    ?>
    <input type="checkbox" name="geofiltre_show_date" value="1" <?php checked(1, $value, true); ?>>
    <?php
}

function geofiltre_date_label_render() {
    $value = get_option('geofiltre_date_label', 'Date');
    ?>
    <input type="text" name="geofiltre_date_label" value="<?php echo esc_attr($value); ?>" size="50">
    <?php
}

function geofiltre_show_distance_render() {
    $value = get_option('geofiltre_show_distance', true);
    ?>
    <input type="checkbox" name="geofiltre_show_distance" value="1" <?php checked(1, $value, true); ?>>
    <?php
}

function geofiltre_distance_label_render() {
    $value = get_option('geofiltre_distance_label', 'Distance');
    ?>
    <input type="text" name="geofiltre_distance_label" value="<?php echo esc_attr($value); ?>" size="50">
    <?php
}

function geofiltre_show_reviews_render() {
    $value = get_option('geofiltre_show_reviews', true);
    ?>
    <input type="checkbox" name="geofiltre_show_reviews" value="1" <?php checked(1, $value, true); ?>>
    <?php
}

function geofiltre_text_color_render() {
    $value = get_option('geofiltre_text_color', '#000000');
    ?>
    <input type="text" name="geofiltre_text_color" value="<?php echo esc_attr($value); ?>" class="my-color-field" data-default-color="#000000">
    <?php
}

function geofiltre_title_character_limit_render() {
    $value = get_option('geofiltre_title_character_limit', 50);
    ?>
    <input type="number" name="geofiltre_title_character_limit" value="<?php echo esc_attr($value); ?>" min="1">
    <?php
}

function geofiltre_result_text_color_render() {
    $value = get_option('geofiltre_result_text_color', '#000000');
    ?>
    <input type="text" name="geofiltre_result_text_color" value="<?php echo esc_attr($value); ?>" class="my-color-field" data-default-color="#000000">
    <?php
}

function geofiltre_show_avatar_render() {
    $value = get_option('geofiltre_show_avatar', true);
    ?>
    <input type="checkbox" name="geofiltre_show_avatar" value="1" <?php checked(1, $value, true); ?>>
    <?php
}

function geofiltre_read_more_text_render() {
    $value = get_option('geofiltre_read_more_text', 'Read More »');
    ?>
    <input type="text" name="geofiltre_read_more_text" value="<?php echo esc_attr($value); ?>" size="50">
    <?php
}

function geofiltre_results_found_text_render() {
    $value = get_option('geofiltre_results_found_text', '%d résultats trouvés à proximité de votre adresse');
    ?>
    <input type="text" name="geofiltre_results_found_text" value="<?php echo esc_attr($value); ?>" size="50">
    <?php
}

function geofiltre_no_results_text_render() {
    $value = get_option('geofiltre_no_results_text', 'Aucun résultat trouvé.');
    ?>
    <input type="text" name="geofiltre_no_results_text" value="<?php echo esc_attr($value); ?>" size="50">
    <?php
}
?>
