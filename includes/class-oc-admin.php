<?php
/**
 * Offers Carousel Admin Class
 * Handles all admin functionality for the Offers Carousel plugin
 */

class OC_Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_oc_save_carousel', [$this, 'save_carousel_ajax']);
        add_action('wp_ajax_oc_get_carousel', [$this, 'get_carousel_ajax']);
        add_action('wp_ajax_oc_delete_carousel', [$this, 'delete_carousel_ajax']);
        add_action('admin_bar_menu', [$this, 'add_admin_bar_clear_cache'], 999);
        add_action('admin_post_oc_clear_cache', [$this, 'handle_clear_cache_request']);
    }

    public function add_admin_menu() {
        add_menu_page(
            'Offers Carousels',
            'Offers Carousels',
            'manage_options',
            'offers-carousels',
            [$this, 'render_admin_page'],
            'dashicons-slides',
            30
        );
    }
    
    
    public function add_admin_bar_clear_cache($wp_admin_bar) {
    if (!current_user_can('manage_options')) return;

    $wp_admin_bar->add_node([
        'id'    => 'oc-clear-cache',
        'title' => 'Clear Offers Cache',
        'href'  => wp_nonce_url(
            admin_url('admin-post.php?action=oc_clear_cache'), 
            'oc_clear_cache'
        ),
        'meta'  => [
            'title' => 'Clear all cached carousels',
        ],
    ]);
}

/**
 * Handle cache clearance request
 */
public function handle_clear_cache_request() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    check_admin_referer('oc_clear_cache');
    
    // Clear cache
    OC_Cache::clear_all_cache();
    
    // Redirect back with success notice
    wp_redirect(
        add_query_arg(
            'oc_cache_cleared', 
            '1', 
            admin_url('admin.php?page=offers-carousels')
        )
    );
    exit;
}
    

    public function render_admin_page() {
        ?>
         <div class="wrap">
        <?php if (!empty($_GET['oc_cache_cleared'])) : ?>
            <div class="notice notice-success is-dismissible">
                <p>Offers Carousel cache cleared successfully!</p>
            </div>
        <?php endif; ?>
        
            <!-- ADD THE ERROR NOTICE RIGHT BELOW THE SUCCESS NOTICE -->
        <?php if (!empty($_GET['oc_cache_error'])) : ?>
            <div class="notice notice-error is-dismissible">
                <p>Failed to clear cache. Check error logs.</p>
            </div>
        <?php endif; ?>
        
        
            <div class="oc-admin-container">
                <div class="oc-admin-header">
                    <h1><?php esc_html_e('Offers Carousels', 'offers-carousel'); ?></h1>
                    <button id="oc-add-new" class="button button-primary">
                        <?php esc_html_e('Add New Carousel', 'offers-carousel'); ?>
                    </button>
                </div>

                <div class="oc-carousel-list">
                    <?php $this->render_carousels_table(); ?>
                </div>

                <div class="oc-carousel-editor" style="display: none;">
                    <?php $this->render_carousel_editor(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_offers-carousels') return;
        
        wp_enqueue_media();
        wp_enqueue_style('oc-admin-css', OC_PLUGIN_URL . 'assets/css/admin.css');
        wp_enqueue_script('oc-admin-js', OC_PLUGIN_URL . 'assets/js/admin.js', 
            ['jquery', 'wp-util', 'media-editor'], OC_VERSION, true);
        
        wp_localize_script('oc-admin-js', 'oc_admin_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oc_admin_nonce'),
            'default_bg' => OC_PLUGIN_URL . 'assets/images/default-offer-bg.png'
        ]);
    }

    private function render_carousels_table() {
        $carousels = OC_DB::get_all_carousels();
        ?>
        <table class="wp-list-table widefat fixed striped oc-carousels-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Name', 'offers-carousel'); ?></th>
                    <th><?php esc_html_e('Shortcode', 'offers-carousel'); ?></th>
                    <th><?php esc_html_e('Created', 'offers-carousel'); ?></th>
                    <th><?php esc_html_e('Actions', 'offers-carousel'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($carousels as $carousel) : ?>
                    <tr>
                        <td><?php echo esc_html($carousel->name); ?></td>
                        <td><code>[offers_carousel slug="<?php echo esc_attr($carousel->slug); ?>"]</code></td>
                        <td><?php echo date_i18n(get_option('date_format'), strtotime($carousel->created_at)); ?></td>
                        <td>
                            <button class="button oc-edit-carousel" data-id="<?php echo esc_attr($carousel->carousel_id); ?>">
                                <?php esc_html_e('Edit', 'offers-carousel'); ?>
                            </button>
                            <button class="button oc-delete-carousel" data-id="<?php echo esc_attr($carousel->carousel_id); ?>">
                                <?php esc_html_e('Delete', 'offers-carousel'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function render_carousel_editor() {
        ?>
        <div class="oc-editor-container">
            <div class="oc-editor-header">
                <h2><?php esc_html_e('Edit Offer Carousel', 'offers-carousel'); ?></h2>
                <div class="oc-editor-actions">
                    <button id="oc-save-carousel" class="button button-primary">
                        <?php esc_html_e('Save Carousel', 'offers-carousel'); ?>
                    </button>
                    <button id="oc-cancel-edit" class="button">
                        <?php esc_html_e('Cancel', 'offers-carousel'); ?>
                    </button>
                </div>
            </div>
            
            

            <div class="oc-form-section">
                <div class="oc-form-group">
                    <label for="oc-carousel-name"><?php esc_html_e('Carousel Name', 'offers-carousel'); ?></label>
                    <input type="text" id="oc-carousel-name" class="regular-text">
                </div>

                <div class="oc-form-group">
                    <label for="oc-carousel-slug"><?php esc_html_e('Carousel Slug', 'offers-carousel'); ?></label>
                    <input type="text" id="oc-carousel-slug" class="regular-text">
                    <p class="description"><?php esc_html_e('Used in the shortcode', 'offers-carousel'); ?></p>
                </div>
            </div>

            <div class="oc-form-section">
                <h3><?php esc_html_e('Slides', 'offers-carousel'); ?></h3>
                <div id="oc-slides-container" class="oc-slides-container">
                    <!-- Slides will be added here via JS -->
                </div>
                <button id="oc-add-slide" class="button">
                    <?php esc_html_e('+ Add Slide', 'offers-carousel'); ?>
                </button>
            </div>

            <div class="oc-form-section">
                <h3><?php esc_html_e('Display Settings', 'offers-carousel'); ?></h3>
                <?php $this->render_display_settings(); ?>
            </div>
        </div>
        <?php
    }

    private function render_display_settings() {
        ?>
        <div class="oc-settings-grid">
            <div class="oc-form-group">
                <label for="oc-slides-per-view"><?php esc_html_e('Visible Slides', 'offers-carousel'); ?></label>
                <select id="oc-slides-per-view">
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3" selected>3</option>
                    <option value="4">4</option>
                </select>
            </div>

            <div class="oc-form-group">
                <label for="oc-effect"><?php esc_html_e('Effect', 'offers-carousel'); ?></label>
                <select id="oc-effect">
                    <option value="slide">Slide</option>
                    <option value="fade">Fade</option>
                    <option value="coverflow">Coverflow</option>
                </select>
            </div>

            <div class="oc-form-group">
                <label>
                    <input type="checkbox" id="oc-autoplay" checked>
                    <?php esc_html_e('Autoplay', 'offers-carousel'); ?>
                </label>
            </div>

            <div class="oc-form-group">
                <label for="oc-autoplay-delay"><?php esc_html_e('Autoplay Delay (ms)', 'offers-carousel'); ?></label>
                <input type="number" id="oc-autoplay-delay" value="3000" min="1000" step="500">
            </div>
        </div>
        <?php
    }

    public function save_carousel_ajax() {
        try {
            if (!isset($_POST['nonce'])) {
                throw new Exception('Nonce not provided');
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'oc_admin_nonce')) {
                throw new Exception('Security check failed');
            }

            if (!current_user_can('manage_options')) {
                throw new Exception('Permission denied');
            }

            // Validate required fields
            if (empty($_POST['name']) || empty($_POST['slug'])) {
                throw new Exception('Name and slug are required');
            }

            // Get and validate slides
            $slides = isset($_POST['slides']) ? json_decode(stripslashes($_POST['slides']), true) : [];
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($slides)) {
                throw new Exception('Invalid slides data');
            }

            // Get and validate settings
            $settings = isset($_POST['settings']) ? json_decode(stripslashes($_POST['settings']), true) : [];
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($settings)) {
                throw new Exception('Invalid settings data');
            }

            // Prepare data for database
            $data = [
                'name' => sanitize_text_field($_POST['name']),
                'slug' => sanitize_title($_POST['slug']),
                'slides' => wp_json_encode($slides),
                'settings' => wp_json_encode($settings),
                'updated_at' => current_time('mysql')
            ];

            global $wpdb;
            $table_name = $wpdb->prefix . 'offers_carousels';

            // Insert or update
            if (!empty($_POST['carousel_id'])) {
                $result = $wpdb->update(
                    $table_name,
                    $data,
                    ['carousel_id' => absint($_POST['carousel_id'])]
                );
            } else {
                $data['created_at'] = current_time('mysql');
                $result = $wpdb->insert($table_name, $data);
            }

            if ($result === false) {
                throw new Exception($wpdb->last_error ?: 'Database error occurred');
            }
            
              // Add this line after successful save
        // Get the carousel ID (new or existing)
$carousel_id = !empty($_POST['carousel_id']) ? absint($_POST['carousel_id']) : $wpdb->insert_id;

// Trigger update action
do_action('oc_carousel_updated', $carousel_id);

            wp_send_json_success([
                'message' => 'Carousel saved successfully',
                'id' => !empty($_POST['carousel_id']) ? $_POST['carousel_id'] : $wpdb->insert_id
            ]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage(), 500);
        }
    }

    public function get_carousel_ajax() {
        try {
            if (!isset($_POST['nonce'])) {
                throw new Exception('Nonce not provided');
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'oc_admin_nonce')) {
                throw new Exception('Security check failed');
            }

            if (!current_user_can('manage_options')) {
                throw new Exception('Permission denied');
            }

            $carousel_id = isset($_POST['id']) ? absint($_POST['id']) : 0;
            if (!$carousel_id) {
                throw new Exception('Invalid carousel ID');
            }

            $carousel = OC_DB::get_carousel_by_id($carousel_id);
            if (!$carousel) {
                throw new Exception('Carousel not found');
            }

            wp_send_json_success([
                'id' => $carousel->carousel_id,
                'name' => $carousel->name,
                'slug' => $carousel->slug,
                'slides' => json_decode($carousel->slides, true),
                'settings' => json_decode($carousel->settings, true)
            ]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage(), 500);
        }
    }

    public function delete_carousel_ajax() {
        try {
            if (!isset($_POST['nonce'])) {
                throw new Exception('Nonce not provided');
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'oc_admin_nonce')) {
                throw new Exception('Security check failed');
            }

            if (!current_user_can('manage_options')) {
                throw new Exception('Permission denied');
            }

            $carousel_id = isset($_POST['id']) ? absint($_POST['id']) : 0;
            if (!$carousel_id) {
                throw new Exception('Invalid carousel ID');
            }


// Add this line before deletion
        do_action('oc_carousel_deleted', $carousel_id);


            $result = OC_DB::delete_carousel($carousel_id);
            if ($result === false) {
                throw new Exception('Failed to delete carousel');
            }

            wp_send_json_success(['message' => 'Carousel deleted successfully']);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage(), 500);
        }
    }
}