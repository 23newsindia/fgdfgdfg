document.addEventListener('DOMContentLoaded', function() {
    const initCarousel = (carousel) => {
        // Remove any conflicting classes first
        carousel.classList.remove('cg-carousel-mode');
        
        // Clear existing content if it's being reinitialized
        if (carousel.classList.contains('initialized')) {
            carousel.innerHTML = carousel.getAttribute('data-original-content') || carousel.innerHTML;
            carousel.classList.remove('initialized');
        }

        // Store original content for potential reinitialization
        if (!carousel.getAttribute('data-original-content')) {
            carousel.setAttribute('data-original-content', carousel.innerHTML);
        }

        const container = carousel.querySelector('.oc-carousel-container');
        if (!container) {
            console.error('Carousel container not found');
            return;
        }

        const slides = Array.from(carousel.querySelectorAll('.oc-slide'));
        if (slides.length === 0) {
            console.error('No slides found in carousel');
            return;
        }

        const prevBtn = carousel.querySelector('.oc-carousel-nav.prev');
        const nextBtn = carousel.querySelector('.oc-carousel-nav.next');
        const dots = Array.from(carousel.querySelectorAll('.oc-carousel-dot'));

        // Set initial states
        slides[0].classList.add('active');
        if (slides[1]) slides[1].classList.add('next');
        if (slides[slides.length - 1]) slides[slides.length - 1].classList.add('prev');
        
        const config = {
            slidesPerView: parseInt(carousel.dataset.slidesPerView) || 2,
            autoplay: carousel.dataset.autoplay === 'true',
            autoplayDelay: parseInt(carousel.dataset.autoplayDelay) || 3000
        };
        
        const state = {
            currentIndex: 0,
            autoplayInterval: null,
            isAnimating: false,
            isDragging: false,
            startX: 0,
            startY: 0,
            currentX: 0,
            currentY: 0,
            isScrolling: null,
            isNavigating: false
        };

        const updateCarousel = (instant = false) => {
            if (state.isAnimating && !instant) return;
            state.isAnimating = true;

            const prevIndex = (state.currentIndex - 1 + slides.length) % slides.length;
            const nextIndex = (state.currentIndex + 1) % slides.length;

            slides.forEach((slide, index) => {
                slide.classList.remove('active', 'prev', 'next');
                if (instant) {
                    slide.style.transition = 'none';
                } else {
                    slide.style.transition = '';
                }

                if (index === state.currentIndex) {
                    slide.classList.add('active');
                    slide.style.transform = 'translateX(-50%) scale(1)';
                    slide.style.opacity = '1';
                    slide.style.zIndex = '3';
                } else if (index === prevIndex) {
                    slide.classList.add('prev');
                    slide.style.transform = 'translateX(-150%) scale(0.75)';
                    slide.style.opacity = '1';
                    slide.style.zIndex = '2';
                } else if (index === nextIndex) {
                    slide.classList.add('next');
                    slide.style.transform = 'translateX(50%) scale(0.75)';
                    slide.style.opacity = '1';
                    slide.style.zIndex = '2';
                } else {
                    slide.style.transform = 'translateX(0) scale(0.5)';
                    slide.style.opacity = '0';
                    slide.style.zIndex = '1';
                }
            });

            if (instant) {
                requestAnimationFrame(() => {
                    slides.forEach(slide => {
                        slide.style.transition = '';
                    });
                });
            }

            updatePagination();
            state.isAnimating = false;
        };

        const navigate = (direction) => {
            if (state.isAnimating || state.isNavigating) return;
            state.currentIndex = (state.currentIndex + direction + slides.length) % slides.length;
            updateCarousel();
        };

        const updatePagination = () => {
            dots.forEach((dot, index) => {
                dot.classList.toggle('active', index === state.currentIndex);
                dot.setAttribute('aria-selected', index === state.currentIndex ? 'true' : 'false');
            });
        };

        // Initialize
        updateCarousel(true);

        // Event Listeners
        if (prevBtn) prevBtn.addEventListener('click', () => navigate(-1));
        if (nextBtn) nextBtn.addEventListener('click', () => navigate(1));

        // Pagination
        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                if (state.isAnimating || state.isNavigating) return;
                state.currentIndex = index;
                updateCarousel();
            });
        });

        // Touch and Mouse Events
        const handleDragStart = (e) => {
            if (state.isNavigating) return;
            state.isDragging = true;
            state.startX = e.type === 'mousedown' ? e.clientX : e.touches[0].clientX;
            state.startY = e.type === 'mousedown' ? e.clientY : e.touches[0].clientY;
            state.isScrolling = null;
            container.style.cursor = 'grabbing';

            // Prevent default only for mouse events
            if (e.type === 'mousedown') {
                e.preventDefault();
            }
        };

        const handleDragMove = (e) => {
            if (!state.isDragging || state.isNavigating) return;
            
            const currentX = e.type === 'mousemove' ? e.clientX : e.touches[0].clientX;
            const currentY = e.type === 'mousemove' ? e.clientY : e.touches[0].clientY;
            const deltaX = currentX - state.startX;
            const deltaY = currentY - state.startY;

            // Determine scroll direction on first move
            if (state.isScrolling === null) {
                state.isScrolling = Math.abs(deltaY) > Math.abs(deltaX);
            }

            // If scrolling vertically, don't prevent default
            if (state.isScrolling) {
                state.isDragging = false;
                return;
            }

            // Prevent default only if sliding horizontally
            e.preventDefault();
            
            state.currentX = currentX;
            slides.forEach(slide => {
                slide.style.transition = 'none';
                const currentTransform = new WebKitCSSMatrix(window.getComputedStyle(slide).transform);
                const newX = currentTransform.m41 + deltaX * 0.1;
                slide.style.transform = `translateX(${newX}px) scale(${currentTransform.m11})`;
            });
        };

        const handleDragEnd = (e) => {
            if (!state.isDragging || state.isNavigating) return;
            state.isDragging = false;
            container.style.cursor = '';

            if (!state.isScrolling) {
                const diff = state.currentX - state.startX;
                if (Math.abs(diff) > 50) {
                    navigate(diff > 0 ? -1 : 1);
                } else {
                    updateCarousel();
                }
            }

            state.isScrolling = null;
        };

        // Mouse Events
        container.addEventListener('mousedown', handleDragStart);
        window.addEventListener('mousemove', handleDragMove);
        window.addEventListener('mouseup', handleDragEnd);

        // Touch Events
        container.addEventListener('touchstart', handleDragStart, { passive: true });
        container.addEventListener('touchmove', handleDragMove, { passive: false });
        container.addEventListener('touchend', handleDragEnd);
        container.addEventListener('touchcancel', handleDragEnd);

        // Prevent default image drag
        container.querySelectorAll('img').forEach(img => {
            img.addEventListener('dragstart', (e) => e.preventDefault());
        });

        // Autoplay
        if (config.autoplay) {
            const startAutoplay = () => {
                stopAutoplay();
                state.autoplayInterval = setInterval(() => {
                    if (!state.isDragging && !state.isNavigating) {
                        navigate(1);
                    }
                }, config.autoplayDelay);
            };

            const stopAutoplay = () => {
                if (state.autoplayInterval) {
                    clearInterval(state.autoplayInterval);
                    state.autoplayInterval = null;
                }
            };

            // Start autoplay if page is visible
            if (document.visibilityState === 'visible') {
                startAutoplay();
            }

            // Pause on hover
            carousel.addEventListener('mouseenter', stopAutoplay);
            carousel.addEventListener('mouseleave', startAutoplay);

            // Handle visibility changes
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    stopAutoplay();
                } else {
                    startAutoplay();
                }
            });

            // Handle intersection
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        startAutoplay();
                    } else {
                        stopAutoplay();
                    }
                });
            }, { threshold: 0.5 });

            observer.observe(carousel);
        }

        // Button click handlers
        carousel.querySelectorAll('.oc-coupon-button').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const href = button.dataset.href;
                if (href) {
                    state.isNavigating = true;
                    window.location.href = href;
                }
            });
        });

        // Preload images
        const preloadImages = () => {
            if (state.isNavigating) return;
            const nextIndex = (state.currentIndex + 1) % slides.length;
            const prevIndex = (state.currentIndex - 1 + slides.length) % slides.length;
            
            [nextIndex, prevIndex].forEach(index => {
                const img = slides[index].querySelector('img');
                if (img && !img.complete) {
                    img.setAttribute('loading', 'eager');
                }
            });
        };

        preloadImages();
        carousel.addEventListener('slideChange', preloadImages);
    };

   // Initialize all carousels
    window.initCarousel = initCarousel; // Make it globally available

    const carousels = document.querySelectorAll('.oc-carousel-wrapper:not(.initialized)');
    if ('requestIdleCallback' in window) {
        carousels.forEach(carousel => {
            requestIdleCallback(() => {
                try {
                    initCarousel(carousel);
                    carousel.classList.add('initialized');
                } catch (error) {
                    console.error('Failed to initialize carousel:', error);
                }
            });
        });
    } else {
        carousels.forEach(carousel => {
            try {
                initCarousel(carousel);
                carousel.classList.add('initialized');
            } catch (error) {
                console.error('Failed to initialize carousel:', error);
            }
        });
    }
});