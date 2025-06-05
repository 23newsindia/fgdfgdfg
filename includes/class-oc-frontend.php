<?php
class OC_Frontend {
    private $should_load_assets = false;

    public function __construct() {
        add_shortcode('offers_carousel', [$this, 'render_carousel']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('wp_footer', [$this, 'maybe_load_assets']);
        add_filter('script_loader_tag', [$this, 'add_defer_attribute'], 10, 2);
        add_filter('style_loader_tag', [$this, 'add_preload_attribute'], 10, 2);
    }

    public function register_assets() {
        wp_register_style('oc-frontend-css', OC_PLUGIN_URL . 'assets/css/frontend.css', [], OC_VERSION);
        wp_register_script('oc-frontend-js', OC_PLUGIN_URL . 'assets/js/frontend.js', [], OC_VERSION, true);
        
        add_action('wp_head', function() {
            if ($this->should_load_assets) {
                echo '<link rel="preload" href="' . esc_url(OC_PLUGIN_URL . 'assets/css/frontend.css') . '" as="style">';
                echo '<link rel="preload" href="' . esc_url(OC_PLUGIN_URL . 'assets/js/frontend.js') . '" as="script">';
            }
        }, 1);
    }

    public function maybe_load_assets() {
        if ($this->should_load_assets) {
            wp_enqueue_style('oc-frontend-css');
            wp_enqueue_script('oc-frontend-js');
            
            wp_localize_script('oc-frontend-js', 'oc_frontend_vars', [
                'plugin_url' => OC_PLUGIN_URL,
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('oc_frontend_nonce')
            ]);
        }
    }

    public function add_defer_attribute($tag, $handle) {
        if ('oc-frontend-js' === $handle) {
            return str_replace(' src', ' defer src', $tag);
        }
        return $tag;
    }

    public function add_preload_attribute($tag, $handle) {
        if ('oc-frontend-css' === $handle) {
            return str_replace(' rel="stylesheet"', ' rel="preload" as="style" onload="this.onload=null;this.rel=\'stylesheet\'"', $tag);
        }
        return $tag;
    }

    public function render_carousel($atts) {
        $this->should_load_assets = true;
        
        $atts = shortcode_atts(['slug' => ''], $atts);
        if (empty($atts['slug'])) return '';
        
        $is_mobile = wp_is_mobile();
        $cache_key = $atts['slug'] . '_' . ($is_mobile ? 'mobile' : 'desktop');
        
        $cached_html = OC_Cache::get_shortcode_cache($cache_key);
        if ($cached_html) {
            return $cached_html;
        }
        
        $carousel = OC_Cache::get_carousel($atts['slug']);
        if (!$carousel) return '';
        
        $slides = json_decode($carousel->slides, true);
        if (!is_array($slides)) {
            error_log('Offers Carousel: Invalid slides data for carousel ' . $atts['slug']);
            return '';
        }

        $settings = json_decode($carousel->settings, true);
        if (!is_array($settings)) {
            $settings = [
                'slides_per_view' => 3,
                'autoplay' => true,
                'autoplay_delay' => 3000
            ];
        }
        
        $image_size = $is_mobile ? '600x400' : '1200x800';
        
        ob_start();
        ?>
        <section class="oc-carousel-wrapper"
                 data-slides-per-view="<?php echo esc_attr($settings['slides_per_view']); ?>"
                 data-autoplay="<?php echo $settings['autoplay'] ? 'true' : 'false'; ?>"
                 data-autoplay-delay="<?php echo esc_attr($settings['autoplay_delay']); ?>">
            
            <?php if (!empty($carousel->name)): ?>
                <h2 class="oc-carousel-title"><?php echo esc_html($carousel->name); ?></h2>
            <?php endif; ?>
            
            <div class="oc-carousel-container">
                <?php foreach ($slides as $index => $slide): 
                    if (empty($slide['bg_image'])) continue;
                    
                    // Important: Remove the deferred class and data attribute
                    $slide_class = 'oc-slide';
                    ?>
                    <div class="<?php echo esc_attr($slide_class); ?>">
                        <div class="oc-coupon">
                            <div class="oc-coupon-cuts top">
                                <div class="oc-top-cut"></div>
                            </div>
                            
                            <div class="oc-coupon-container">
                                <img loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>" 
                                     decoding="async"
                                     fetchpriority="<?php echo $index === 0 ? 'high' : 'low'; ?>"
                                     draggable="false" 
                                     alt="<?php echo esc_attr($slide['title']); ?>" 
                                     src="<?php echo esc_url($slide['bg_image']); ?>"
                                     class="oc-coupon-bg">
                                
                                <div class="oc-coupon-content">
                                    <p class="oc-coupon-title"><?php echo esc_html($slide['title']); ?></p>
                                    <p class="oc-coupon-subtitle"><?php echo esc_html($slide['subtitle']); ?></p>
                                </div>
                            </div>
                            
                            <div class="oc-center-cut"></div>
                            
                            <button class="oc-coupon-button"
                                    data-href="<?php echo esc_url($slide['button_link']); ?>">
                                <div class="oc-cta-text">
                                    <p class="flex justify-center gap-1">
                                        <span><?php echo esc_html($slide['button_text'] ?: 'Shop Now'); ?></span>
                                    </p>
                                </div>
                            </button>
                            
                            <div class="oc-coupon-cuts bottom">
                                <div class="oc-bottom-cut">
                                    <?php for($i = 0; $i < 13; $i++): ?>
                                        <span></span>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <button class="oc-carousel-nav prev" aria-label="Previous slide">‹</button>
            <button class="oc-carousel-nav next" aria-label="Next slide">›</button>
            
            <div class="oc-carousel-pagination" role="tablist">
                <?php foreach($slides as $index => $slide): ?>
                    <button class="oc-carousel-dot <?php echo $index === 0 ? 'active' : ''; ?>" 
                            data-index="<?php echo $index; ?>"
                            role="tab"
                            aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                            aria-label="Go to slide <?php echo $index + 1; ?>"></button>
                <?php endforeach; ?>
            </div>
            
            <div class="oc-bottom-strip-container">
                <div class="oc-stars-left"></div>
                <div class="oc-bottom-strip">
                    <p class="z-20">+ Free Delivery &amp; Easy Returns</p>
                </div>
                <div class="oc-stars-right"></div>
            </div>
        </section>
        <?php
        $html = ob_get_clean();
        
        OC_Cache::set_shortcode_cache($cache_key, $html);
        
        return $html;
    }

    private function get_optimized_image_url($url, $size) {
        if (empty($url)) {
            return '';
        }
        return $url;
    }
}