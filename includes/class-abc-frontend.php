<?php
class ABC_Frontend {
    private static $parsed_shortcodes = array();
    private static $preloaded_images = array();

    public function __construct() {
        add_shortcode('abc_banner', array($this, 'render_banner_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_filter('script_loader_tag', array($this, 'add_module_type'), 10, 3);
        add_action('wp_footer', array($this, 'maybe_load_assets'), 1);
    }

    public function add_module_type($tag, $handle, $src) {
        if ('abc-carousel-js' === $handle) {
            return str_replace('<script', '<script type="module"', $tag);
        }
        return $tag;
    }

    public function enqueue_scripts() {
        if (!$this->should_load_assets()) {
            return;
        }
        
        wp_register_style('abc-carousel-css', ABC_PLUGIN_URL . 'assets/css/carousel.css', array(), ABC_VERSION);
        wp_register_script('abc-carousel-js', ABC_PLUGIN_URL . 'assets/js/carousel.js', array(), ABC_VERSION, true);
    }

    private function should_load_assets() {
        global $post;
        
        if (is_admin()) return false;
        
        // Check post content
        if ($post && (
            has_shortcode($post->post_content, 'abc_banner') ||
            has_block('abc/banner-carousel')
        )) {
            return true;
        }
        
        // Check widgets
        $sidebars_widgets = wp_get_sidebars_widgets();
        foreach ($sidebars_widgets as $sidebar => $widgets) {
            if (is_active_sidebar($sidebar)) {
                foreach ($widgets as $widget) {
                    if (strpos($widget, 'abc_banner') !== false) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    public function maybe_load_assets() {
        if (!empty(self::$parsed_shortcodes)) {
            wp_print_styles('abc-carousel-css');
            wp_print_scripts('abc-carousel-js');
        }
    }

    private function preload_first_slide() {
        global $post;
        if (!is_object($post) || !isset($post->post_content)) return;
        
        preg_match_all('/\[abc_banner\s+slug=["\']([^"\']+)["\']/', $post->post_content, $matches);
        if (empty($matches[1])) return;
        
        $first_slider_slug = $matches[1][0];
        $banner = ABC_DB::get_banner($first_slider_slug);
        
        if ($banner && !empty($banner->slides)) {
            $slides = maybe_unserialize($banner->slides);
            if (!empty($slides[0]['image']) && !isset(self::$preloaded_images[$slides[0]['image']])) {
                self::$preloaded_images[$slides[0]['image']] = true;
                
                add_action('wp_head', function() use ($slides) {
                    printf(
                        '<link rel="preload" as="image" href="%s" fetchpriority="high" importance="high">',
                        esc_url($slides[0]['image'])
                    );
                }, 1);
                
                if (count($slides) > 1 && !empty($slides[1]['image'])) {
                    add_action('wp_head', function() use ($slides) {
                        printf(
                            '<link rel="prefetch" as="image" href="%s">',
                            esc_url($slides[1]['image'])
                        );
                    }, 2);
                }
            }
        }
    }

    public function render_banner_shortcode($atts) {
        static $instance = 0;
        $instance++;
        
        $atts = shortcode_atts(array(
            'slug' => '',
            'class' => ''
        ), $atts);
        
        if (empty($atts['slug'])) {
            return '<p class="abc-error">Please specify a banner slug</p>';
        }
        
        $cache_key = $this->get_shortcode_cache_key($atts, $instance);
        
        if (isset(self::$parsed_shortcodes[$cache_key])) {
            return self::$parsed_shortcodes[$cache_key];
        }
        
        $html = wp_cache_get($cache_key, 'abc_shortcodes');
        
        if (false === $html) {
            $banner = ABC_DB::get_banner($atts['slug']);
            
            if (!$banner) {
                return '<p class="abc-error">Banner not found</p>';
            }
            
            $slides = maybe_unserialize($banner->slides);
            $settings = maybe_unserialize($banner->settings);
            
            if (empty($slides) || !is_array($slides)) {
                return '<p class="abc-error">No slides found for this banner</p>';
            }

            $slides = array_filter($slides, function($slide) {
                return !empty($slide['image']) && $slide['image'] !== 'null';
            });
            
            if (empty($slides)) {
                return '<p class="abc-error">No valid slides found for this banner</p>';
            }
            
            $default_settings = json_decode(get_option('abc_default_settings'), true);
            $settings = wp_parse_args($settings, $default_settings);
            
            ob_start();
            $this->render_banner_html($banner, $atts, $slides, $settings, $instance);
            $html = ob_get_clean();
            
            self::$parsed_shortcodes[$cache_key] = $html;
            wp_cache_set($cache_key, $html, 'abc_shortcodes', HOUR_IN_SECONDS);
            
            // Enqueue assets when shortcode is used
            wp_enqueue_style('abc-carousel-css');
            wp_enqueue_script('abc-carousel-js');
        }
        
        return $html;
    }

    private function get_shortcode_cache_key($atts, $instance) {
        return 'abc_shortcode_' . md5(serialize($atts) . $instance);
    }

    public static function clear_shortcode_cache($slug = '') {
        self::$parsed_shortcodes = array();
        
        if ($slug) {
            global $wpdb;
            $cache_pattern = 'abc_shortcode_%' . md5($slug) . '%';
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $cache_pattern
            ));
        } else {
            wp_cache_flush();
        }
    }

    private function render_banner_html($banner, $atts, $slides, $settings, $instance) {
        $additional_class = !empty($atts['class']) ? ' ' . esc_attr($atts['class']) : '';
        $carousel_id = 'abc-carousel-' . $instance;
        
        printf(
            '<div id="%s" class="abc-banner-carousel%s" data-settings="%s">',
            esc_attr($carousel_id),
            $additional_class,
            esc_attr(json_encode($settings))
        );
        
        echo '<div class="abc-carousel-wrapper"><div class="abc-carousel-inner">';
        
        foreach ($slides as $index => $slide) {
            $image_data = $this->get_optimized_image_data($slide['image'], $index + 1, $slide['alt_text']);
            
            printf(
                '<div class="abc-slide" data-index="%d">',
                $index
            );
            
            if (!empty($slide['link'])) {
                printf(
                    '<a href="%s" class="abc-slide-link">',
                    esc_url($slide['link'])
                );
            }
            
            echo '<div class="abc-slide-image-container">';
            printf(
                '<img src="%s" data-src="%s" alt="%s" loading="%s" class="abc-slide-image%s" width="%s" height="%s"%s>',
                esc_attr($image_data['placeholder']),
                esc_url($image_data['url']),
                esc_attr($image_data['alt']),
                esc_attr($image_data['loading']),
                $index === 0 ? ' abc-first-slide customFade-active' : '',
                esc_attr($image_data['width']),
                esc_attr($image_data['height']),
                $index === 0 ? ' fetchpriority="high"' : ''
            );
            echo '</div>';
            
            if (!empty($slide['title'])) {
                printf(
                    '<div class="abc-slide-title">%s</div>',
                    esc_html($slide['title'])
                );
            }
            
            if (!empty($slide['link'])) {
                echo '</a>';
            }
            
            echo '</div>';
        }
        
        echo '</div>';
        
        if ($settings['show_arrows']) {
            echo '<button class="abc-carousel-prev" aria-label="Previous slide">';
            echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M15.41 16.59L10.83 12l4.58-4.59L14 6l-6 6 6 6 1.41-1.41z"/></svg>';
            echo '</button>';
            echo '<button class="abc-carousel-next" aria-label="Next slide">';
            echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/></svg>';
            echo '</button>';
        }
        
        echo '</div></div>';
    }

    private function get_optimized_image_data($image_url, $position = 1, $alt_text = '') {
        static $sizes = null;
        
        if ($sizes === null) {
            $sizes = apply_filters('abc_image_sizes', array(
                'width' => 480,
                'height' => 460
            ));
        }
        
        if (empty($image_url) || $image_url === 'null') {
            return array(
                'url' => '',
                'placeholder' => '',
                'width' => $sizes['width'],
                'height' => $sizes['height'],
                'alt' => '',
                'loading' => 'lazy'
            );
        }

        return array(
            'url' => esc_url($image_url),
            'placeholder' => $this->get_placeholder_image(),
            'width' => $sizes['width'],
            'height' => $sizes['height'],
            'alt' => sanitize_text_field($alt_text),
            'loading' => $position <= 2 ? 'eager' : 'lazy'
        );
    }

    private function get_placeholder_image() {
        static $placeholder = null;
        
        if ($placeholder === null) {
            $placeholder = 'data:image/svg+xml;base64,' . base64_encode(
                '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>'
            );
        }
        
        return $placeholder;
    }
}