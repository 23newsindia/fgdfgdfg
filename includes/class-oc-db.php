<?php
class OC_DB {
    private static $table_name = 'offers_carousels';

    public static function create_tables() {
        global $wpdb;
        $table = $wpdb->prefix . self::$table_name;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            carousel_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            slides LONGTEXT NOT NULL,
            settings LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (carousel_id),
            UNIQUE KEY slug (slug)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function check_tables() {
        global $wpdb;
        $table = $wpdb->prefix . self::$table_name;
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            self::create_tables();
        }
    }

    public static function get_carousel($slug) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}offers_carousels 
            WHERE slug = %s", 
            $slug
        ));
    }
  
  
  
   public static function get_all_carousels() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT carousel_id, name, slug, created_at 
             FROM {$wpdb->prefix}" . self::$table_name . "
             ORDER BY created_at DESC"
        );
    }
  
  public static function get_carousel_by_id($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}offers_carousels 
            WHERE carousel_id = %d", 
            $id
        ));
    }

    public static function save_carousel($data) {
        global $wpdb;
        
        $defaults = [
            'name' => '',
            'slug' => '',
            'slides' => json_encode([]),
            'settings' => json_encode([
                'slides_per_view' => 3,
                'effect' => 'slide',
                'autoplay' => true,
                'autoplay_delay' => 3000
            ]),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        $insert_data = wp_parse_args($data, $defaults);
        
        return $wpdb->insert(
            $wpdb->prefix . self::$table_name,
            $insert_data
        );
    }

    public static function update_carousel($id, $data) {
        global $wpdb;
        
        $data['updated_at'] = current_time('mysql');
        
        return $wpdb->update(
            $wpdb->prefix . self::$table_name,
            $data,
            ['carousel_id' => $id]
        );
    }

    public static function delete_carousel($id) {
        global $wpdb;
        return $wpdb->delete(
            $wpdb->prefix . self::$table_name,
            ['carousel_id' => $id],
            ['%d']
        );
    }

    // Additional database methods will be added in next parts
}