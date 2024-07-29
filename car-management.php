<?php
/**
 * Plugin Name: Car Management
 * Description: A plugin to manage car listings with custom post types and taxonomies.
 * Version: 1.0
 * Author: Jignesh Viramgami
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'car_management_activate');
register_deactivation_hook(__FILE__, 'car_management_deactivate');


function car_management_activate() {
    car_management_setup();
    flush_rewrite_rules();
}

function car_management_deactivate() {
    flush_rewrite_rules();
}

// Register Custom Post Type and Taxonomies
function car_management_setup() {
    
    register_post_type('car', array(
        'labels' => array(
            'name' => 'Cars',
            'singular_name' => 'Car'
        ),
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor', 'thumbnail'),
        'rewrite' => array('slug' => 'cars'),
    ));

    
    $taxonomies = array(
        'make' => 'Make',
        'model' => 'Model',
        'lyear' => 'Luanch Year',
        'fuel_type' => 'Fuel Type'
    );

    foreach ($taxonomies as $slug => $name) {
        register_taxonomy($slug, 'car', array(
            'label' => $name,
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => $slug),
        ));
    }
}
add_action('init', 'car_management_setup');


function car_entry_form() {
    ob_start(); ?>

<form id="car-entry-form" method="post" enctype="multipart/form-data">
    <?php wp_nonce_field('car_entry_nonce_action', 'car_entry_nonce'); ?>
    <p>
        <label for="car_name">Car Name:</label>
        <input type="text" id="car_name" name="car_name" required>
        <span id="car-name-error" style="color:red;"></span>
    </p>
    <p>
        <label for="make">Make:</label>
        <select id="make" name="make" required>
            <?php
                $terms = get_terms(array('taxonomy' => 'make', 'hide_empty' => false));
                foreach ($terms as $term) {
                    echo "<option value='{$term->term_id}'>{$term->name}</option>";
                }
                ?>
        </select>
    </p>
    <p>
        <label for="model">Model:</label>
        <select id="model" name="model" required>
            <?php
                $terms = get_terms(array('taxonomy' => 'model', 'hide_empty' => false));
                foreach ($terms as $term) {
                    echo "<option value='{$term->term_id}'>{$term->name}</option>";
                }
                ?>
        </select>
    </p>
    <p>
        <label for="fuel_type">Fuel Type:</label>
        <?php
            $terms = get_terms(array('taxonomy' => 'fuel_type', 'hide_empty' => false));
            foreach ($terms as $term) {
                echo "<input type='radio' name='fuel_type' value='{$term->term_id}' required> {$term->name}";
            }
            ?>
    </p>
    <p>
        <label for="lyear">Launch Year:</label>
        <select id="lyear" name="lyear" required>
            <?php
                $terms = get_terms(array('taxonomy' => 'lyear', 'hide_empty' => false));
                foreach ($terms as $term) {
                    echo "<option value='{$term->term_id}'>{$term->name}</option>";
                }
                ?>
        </select>
    </p>
    <p>
        <label for="car_image">Image:</label>
        <input type="file" id="car_image" name="car_image" accept="image/*" required>
    </p>
    <p>
        <button type="submit">Submit</button>
    </p>
    <p id="form-message" style="color:green;"></p>
</form>

<?php
    return ob_get_clean();
}
add_shortcode('car_entry', 'car_entry_form');


function car_management_enqueue_scripts() {
    wp_enqueue_script('car-management-js', plugins_url('car-management.js', __FILE__), array('jquery'), null, true);
    wp_localize_script('car-management-js', 'car_management', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_enqueue_scripts', 'car_management_enqueue_scripts');

function handle_car_entry() {

    if (!isset($_POST['car_entry_nonce']) || !wp_verify_nonce($_POST['car_entry_nonce'], 'car_entry_nonce_action')) {
        wp_send_json_error(array('message' => 'Nonce verification failed.'));
    }

    if (empty($_POST['car_name']) || empty($_POST['make']) || empty($_POST['model']) || empty($_POST['fuel_type']) || empty($_FILES['car_image'])) {
        wp_send_json_error(array('message' => 'Missing fields.'));
    }

    $car_name = sanitize_text_field($_POST['car_name']);
    $make = intval($_POST['make']);
    $model = intval($_POST['model']);
    $fuel_type = intval($_POST['fuel_type']);
    $lyear = intval($_POST['lyear']);
    
    $existing_car = get_page_by_title($car_name, OBJECT, 'car');
    if ($existing_car) {
        wp_send_json_error(array('message' => 'Car name already exists.'));
    }

    $post_id = wp_insert_post(array(
        'post_title' => $car_name,
        'post_type' => 'car',
        'post_status' => 'publish',
    ));

    if ($post_id) {
        
        wp_set_post_terms($post_id, array($make), 'make');
        wp_set_post_terms($post_id, array($model), 'model');
        wp_set_post_terms($post_id, array($fuel_type), 'fuel_type');  
        wp_set_post_terms($post_id, array($lyear), 'lyear');    
        
        if (!empty($_FILES['car_image']['name'])) {
            $attachment_id = media_handle_upload('car_image', $post_id);
            if (!is_wp_error($attachment_id)) {
                set_post_thumbnail($post_id, $attachment_id);
            } else {
                wp_send_json_error(array('message' => 'Failed to upload image: ' . $attachment_id->get_error_message()));
            }
        }

        wp_send_json_success(array('message' => 'Car added successfully.'));
    } else {
        wp_send_json_error(array('message' => 'Failed to add car.'));
    }
}
add_action('wp_ajax_handle_car_entry', 'handle_car_entry');
add_action('wp_ajax_nopriv_handle_car_entry', 'handle_car_entry');



function car_list_shortcode($atts) {
    
    $atts = shortcode_atts(array(
        'paged' => 1, 
        'posts_per_page' => 10,
    ), $atts, 'car_list');

    $paged = intval($atts['paged']);
    $posts_per_page = intval($atts['posts_per_page']);

    $args = array(
        'post_type' => 'car',
        'posts_per_page' => $posts_per_page,
        'paged' => $paged,
        'post_status' => 'publish', 
    );

    $query = new WP_Query($args);

    ob_start();

    if ($query->have_posts()) {
        echo '<ul>';
        while ($query->have_posts()) {
            $query->the_post();
            echo '<li>';
            echo '<h2>' . get_the_title() . '</h2>';
            if (has_post_thumbnail()) {
                echo get_the_post_thumbnail(get_the_ID(), 'thumbnail');
            }
            echo '<p>Make: ' . implode(', ', wp_get_post_terms(get_the_ID(), 'make', array('fields' => 'names'))) . '</p>';
            echo '<p>Model: ' . implode(', ', wp_get_post_terms(get_the_ID(), 'model', array('fields' => 'names'))) . '</p>';
            echo '<p>Fuel Type: ' . implode(', ', wp_get_post_terms(get_the_ID(), 'fuel_type', array('fields' => 'names'))) . '</p>';
            echo '</li>';
        }
        echo '</ul>';

        
        $total_pages = $query->max_num_pages;
        if ($total_pages > 1) {
            echo '<div class="pagination">';
            echo paginate_links(array(
                'total' => $total_pages,
                'current' => $paged,
                'format' => '?paged=%#%',
                'prev_text' => __('&laquo; Previous'),
                'next_text' => __('Next &raquo;'),
            ));
            echo '</div>';
        }
    } else {
        echo 'No cars found.';
    }

    wp_reset_postdata();

    return ob_get_clean();
}
add_shortcode('car_list', 'car_list_shortcode');

function create_dummy_data() {
    $dummy_data = get_option('car_management_dummy_data');
    if ($dummy_data) {
        return;
    }

    
    $makes = ['Toyota', 'Ford', 'BMW'];
    foreach ($makes as $make) {
        wp_insert_term($make, 'make');
    }

   
    $models = ['Corolla', 'Focus', '3 Series'];
    foreach ($models as $model) {
        wp_insert_term($model, 'model');
    }

    
    $years = ['2020', '2021', '2022'];
    foreach ($years as $year) {
        wp_insert_term($year, 'lyear');
    }

    
    $fuel_types = ['Petrol', 'Diesel', 'Electric'];
    foreach ($fuel_types as $fuel_type) {
        wp_insert_term($fuel_type, 'fuel_type');
    }

    
    $cars = [
        ['name' => 'Toyota Car', 'make' => 'Toyota', 'model' => 'Corolla', 'lyear' => '2020', 'fuel_type' => 'Petrol'],
        ['name' => 'Ford Car', 'make' => 'Ford', 'model' => 'Focus', 'lyear' => '2021', 'fuel_type' => 'Diesel'],
        ['name' => 'BMW Car', 'make' => 'BMW', 'model' => '3 Series', 'lyear' => '2022', 'fuel_type' => 'Electric'],
    ];

    foreach ($cars as $car) {
        $post_id = wp_insert_post([
            'post_title' => $car['name'],
            'post_type' => 'car',
            'post_status' => 'publish',
        ]);

        if ($post_id) {
            wp_set_post_terms($post_id, [$car['make']], 'make');
            wp_set_post_terms($post_id, [$car['model']], 'model');
            wp_set_post_terms($post_id, [$car['lyear']], 'lyear');
            wp_set_post_terms($post_id, [$car['fuel_type']], 'fuel_type');
            
            
        }
    }

    update_option('car_management_dummy_data', 1);
}
add_action('init', 'create_dummy_data');