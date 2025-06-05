<?php
/*
Plugin Name: Offers Carousel
Description: Create beautiful offer carousels with custom backgrounds and text
Version: 1.0.0
Author: Your Name
*/

if (!defined('ABSPATH')) exit;

// Define constants
define('OC_VERSION', '1.0.0');
define('OC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include core classes
require_once OC_PLUGIN_DIR . 'includes/class-oc-cache.php';
require_once OC_PLUGIN_DIR . 'includes/class-oc-db.php';
require_once OC_PLUGIN_DIR . 'includes/class-oc-admin.php';
require_once OC_PLUGIN_DIR . 'includes/class-oc-frontend.php';

// Initialize components
register_activation_hook(__FILE__, ['OC_DB', 'create_tables']);

add_action('plugins_loaded', function() {
    if (class_exists('OC_DB') && class_exists('OC_Admin') && class_exists('OC_Frontend') && class_exists('OC_Cache')) {
        OC_DB::check_tables();
        new OC_Admin();
        new OC_Frontend();
        OC_Cache::init(); // Initialize cache using static method
    } else {
        error_log('Offers Carousel: Failed to load required classes');
    }
});