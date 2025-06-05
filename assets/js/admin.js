document.addEventListener('DOMContentLoaded', function() {
    // Toggle between list and editor views
    const addNewBtn = document.getElementById('oc-add-new');
    const carouselList = document.querySelector('.oc-carousel-list');
    const carouselEditor = document.querySelector('.oc-carousel-editor');

    if (addNewBtn && carouselList && carouselEditor) {
        addNewBtn.addEventListener('click', function() {
            carouselList.style.display = 'none';
            carouselEditor.style.display = 'block';
            resetEditor();
        });

        document.getElementById('oc-cancel-edit').addEventListener('click', function() {
            carouselList.style.display = 'block';
            carouselEditor.style.display = 'none';
        });
    }

    function resetEditor() {
        document.getElementById('oc-carousel-name').value = '';
        document.getElementById('oc-carousel-slug').value = '';
        document.getElementById('oc-slides-container').innerHTML = '';
        document.querySelector('.oc-carousel-editor').dataset.id = '';
        
        // Reset settings to defaults
        document.getElementById('oc-slides-per-view').value = '3';
        document.getElementById('oc-effect').value = 'slide';
        document.getElementById('oc-autoplay').checked = true;
        document.getElementById('oc-autoplay-delay').value = '3000';
    }

    // Generate slug from name
    const nameInput = document.getElementById('oc-carousel-name');
    if (nameInput) {
        nameInput.addEventListener('input', function() {
            const slugInput = document.getElementById('oc-carousel-slug');
            if (!slugInput.value) {
                slugInput.value = this.value.toLowerCase()
                    .replace(/\s+/g, '-')
                    .replace(/[^\w\-]+/g, '')
                    .replace(/\-\-+/g, '-')
                    .replace(/^-+/, '')
                    .replace(/-+$/, '');
            }
        });
    }

    // Add new slide
    document.getElementById('oc-add-slide')?.addEventListener('click', function(e) {
        e.preventDefault();
        addNewSlide();
    });

    function addNewSlide(slideData = {}) {
        const slideId = Date.now();
        const slidesContainer = document.getElementById('oc-slides-container');
        
        const slideElement = document.createElement('div');
        slideElement.className = 'oc-slide';
        slideElement.dataset.slideId = slideId;
        
        // Ensure slideData has all required properties
        const defaultSlideData = {
            bg_image: '',
            title: '',
            subtitle: '',
            button_link: '',
            button_text: 'Shop Now'
        };
        
        // Merge default data with provided data
        slideData = Object.assign({}, defaultSlideData, slideData);
        
        slideElement.innerHTML = `
            <div class="oc-slide-header">
                <h4>Slide #${slidesContainer.children.length + 1}</h4>
                <button class="button oc-remove-slide">Remove</button>
            </div>
            <div class="oc-slide-fields">
                <div class="oc-form-group">
                    <label>Background Image</label>
                    <button class="button oc-upload-bg">Upload</button>
                    <input type="text" class="oc-bg-image regular-text" 
                           value="${slideData.bg_image ? esc_attr(slideData.bg_image) : ''}" readonly>
                    <div class="oc-image-preview" style="display: ${slideData.bg_image ? 'block' : 'none'}">
                        <img src="${slideData.bg_image ? esc_attr(slideData.bg_image) : ''}" style="max-width:100px;">
                    </div>
                </div>
                <div class="oc-form-group">
                    <label>Title Text</label>
                    <input type="text" class="oc-title-text regular-text" 
                           value="${slideData.title ? esc_attr(slideData.title) : ''}" 
                           placeholder="e.g. Buy 2 @999">
                </div>
                <div class="oc-form-group">
                    <label>Subtitle Text</label>
                    <input type="text" class="oc-subtitle-text regular-text" 
                           value="${slideData.subtitle ? esc_attr(slideData.subtitle) : ''}" 
                           placeholder="e.g. Get any 2 T-shirts for 999">
                </div>
                <div class="oc-form-group">
                    <label>Button Link</label>
                    <input type="text" class="oc-button-link regular-text" 
                           value="${slideData.button_link ? esc_attr(slideData.button_link) : ''}" 
                           placeholder="URL when clicked">
                </div>
                <div class="oc-form-group">
                    <label>Button Text</label>
                    <input type="text" class="oc-button-text regular-text" 
                           value="${slideData.button_text ? esc_attr(slideData.button_text) : 'Shop Now'}">
                </div>
            </div>
        `;
        
        slidesContainer.appendChild(slideElement);
        
        // Initialize media uploader for this slide
        initMediaUploader(slideElement);
    }

    // Helper function to escape HTML attributes
    function esc_attr(str) {
        if (!str) return '';
        return str.replace(/[&<>"']/g, function(match) {
            const escape = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            };
            return escape[match];
        });
    }

    function initMediaUploader(slideElement) {
        const uploadBtn = slideElement.querySelector('.oc-upload-bg');
        const input = slideElement.querySelector('.oc-bg-image');
        const preview = slideElement.querySelector('.oc-image-preview');
        
        uploadBtn?.addEventListener('click', function(e) {
            e.preventDefault();
            
            const frame = wp.media({
                title: 'Select Background Image',
                button: { text: 'Use this image' },
                multiple: false
            });
            
            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                if (attachment && attachment.url) {
                    input.value = attachment.url;
                    preview.style.display = 'block';
                    preview.querySelector('img').src = attachment.url;
                }
            });
            
            frame.open();
        });
    }

    // Remove slide
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('oc-remove-slide')) {
            e.preventDefault();
            e.target.closest('.oc-slide').remove();
        }
    });

    // Save carousel
    document.getElementById('oc-save-carousel')?.addEventListener('click', async function(e) {
        e.preventDefault();
        
        const name = document.getElementById('oc-carousel-name').value;
        const slug = document.getElementById('oc-carousel-slug').value;
        
        if (!name || !slug) {
            alert('Name and slug are required!');
            return;
        }
        
        // Collect slides data
        const slides = [];
        document.querySelectorAll('.oc-slide').forEach(slide => {
            const bgImage = slide.querySelector('.oc-bg-image').value;
            if (!bgImage) {
                alert('All slides must have a background image!');
                return;
            }
            
            slides.push({
                bg_image: bgImage,
                title: slide.querySelector('.oc-title-text').value,
                subtitle: slide.querySelector('.oc-subtitle-text').value,
                button_link: slide.querySelector('.oc-button-link').value,
                button_text: slide.querySelector('.oc-button-text').value || 'Shop Now'
            });
        });
        
        if (slides.length === 0) {
            alert('Add at least one slide!');
            return;
        }
        
        // Collect settings
        const settings = {
            slides_per_view: parseInt(document.getElementById('oc-slides-per-view').value),
            effect: document.getElementById('oc-effect').value,
            autoplay: document.getElementById('oc-autoplay').checked,
            autoplay_delay: parseInt(document.getElementById('oc-autoplay-delay').value)
        };
        
        // Show loading state
        const saveBtn = this;
        const originalText = saveBtn.textContent;
        saveBtn.textContent = 'Saving...';
        saveBtn.disabled = true;

        try {
            const params = new URLSearchParams({
                action: 'oc_save_carousel',
                nonce: oc_admin_vars.nonce,
                name: name,
                slug: slug,
                slides: JSON.stringify(slides),
                settings: JSON.stringify(settings)
            });

            const carouselId = document.querySelector('.oc-carousel-editor').dataset.id;
            if (carouselId) {
                params.append('carousel_id', carouselId);
            }

            const response = await fetch(oc_admin_vars.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: params
            });

            if (!response.ok) {
                const text = await response.text();
                throw new Error(`HTTP error! status: ${response.status} - ${text}`);
            }

            const data = await response.json();
            
            if (data.success) {
                alert('Carousel saved successfully!');
                window.location.reload();
            } else {
                throw new Error(data.data || 'Failed to save carousel');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to save carousel: ' + error.message);
        } finally {
            saveBtn.textContent = originalText;
            saveBtn.disabled = false;
        }
    });

    // Edit carousel
    document.addEventListener('click', async function(e) {
        if (e.target.classList.contains('oc-edit-carousel')) {
            e.preventDefault();
            
            const carouselId = e.target.dataset.id;
            
            try {
                const response = await fetch(oc_admin_vars.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'oc_get_carousel',
                        nonce: oc_admin_vars.nonce,
                        id: carouselId
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    const carousel = data.data;
                    
                    // Switch to editor view
                    carouselList.style.display = 'none';
                    carouselEditor.style.display = 'block';
                    
                    // Set carousel ID
                    carouselEditor.dataset.id = carousel.id;
                    
                    // Fill in basic info
                    document.getElementById('oc-carousel-name').value = carousel.name;
                    document.getElementById('oc-carousel-slug').value = carousel.slug;
                    
                    // Clear and recreate slides
                    document.getElementById('oc-slides-container').innerHTML = '';
                    carousel.slides.forEach(slide => addNewSlide(slide));
                    
                    // Set settings
                    document.getElementById('oc-slides-per-view').value = carousel.settings.slides_per_view;
                    document.getElementById('oc-effect').value = carousel.settings.effect;
                    document.getElementById('oc-autoplay').checked = carousel.settings.autoplay;
                    document.getElementById('oc-autoplay-delay').value = carousel.settings.autoplay_delay;
                } else {
                    throw new Error(data.data || 'Failed to load carousel');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to load carousel: ' + error.message);
            }
        }
    });

    // Delete carousel
    document.addEventListener('click', async function(e) {
        if (e.target.classList.contains('oc-delete-carousel')) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to delete this carousel?')) {
                return;
            }
            
            const carouselId = e.target.dataset.id;
            
            try {
                const response = await fetch(oc_admin_vars.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'oc_delete_carousel',
                        nonce: oc_admin_vars.nonce,
                        id: carouselId
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    alert('Carousel deleted successfully!');
                    window.location.reload();
                } else {
                    throw new Error(data.data || 'Failed to delete carousel');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to delete carousel: ' + error.message);
            }
        }
    });
});