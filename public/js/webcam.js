// Webcam and image capture functionality

class WebcamManager {
    constructor() {
        this.video = document.getElementById('video');
        this.canvas = document.getElementById('canvas');
        this.previewContainer = document.getElementById('preview-container');
        this.previewImage = document.getElementById('preview-image');
        this.overlayPreview = document.getElementById('overlay-preview');
        this.fileInput = document.getElementById('file-input');
        
        this.stream = null;
        this.selectedOverlay = null;
        this.capturedImageData = null;
        this.overlays = [];
        
        this.init();
    }

    async init() {
        await this.loadOverlays();
        this.bindEvents();
    }

    async loadOverlays() {
        try {
            const data = await Utils.get('upload.php', { action: 'get_overlays' });
            if (data.success) {
                this.overlays = data.overlays;
                this.renderOverlayOptions();
            }
        } catch (error) {
            console.error('Failed to load overlays:', error);
        }
    }

    renderOverlayOptions() {
        const overlayOptions = document.getElementById('overlay-options');
        if (!overlayOptions) return;

        overlayOptions.innerHTML = '';
        
        this.overlays.forEach(overlay => {
            const option = document.createElement('div');
            option.className = 'overlay-option';
            option.style.backgroundImage = `url(./images/overlays/${overlay})`;
            option.dataset.overlay = overlay;
            option.title = overlay;
            
            option.addEventListener('click', () => {
                this.selectOverlay(overlay, option);
            });
            
            overlayOptions.appendChild(option);
        });
    }

    selectOverlay(overlayName, element) {
        // Remove previous selection
        document.querySelectorAll('.overlay-option').forEach(opt => {
            opt.classList.remove('selected');
        });
        
        // Add selection to clicked element
        element.classList.add('selected');
        this.selectedOverlay = overlayName;
        
        // Update overlay preview
        this.updateOverlayPreview();
        
        // Enable capture button
        document.getElementById('capture-btn').disabled = false;
    }

    updateOverlayPreview() {
        if (this.selectedOverlay) {
            this.overlayPreview.style.backgroundImage = `url(./images/overlays/${this.selectedOverlay})`;
        } else {
            this.overlayPreview.style.backgroundImage = '';
        }
    }

    bindEvents() {
        // Start camera button
        document.getElementById('start-camera-btn')?.addEventListener('click', () => {
            this.startCamera();
        });

        // Upload button
        document.getElementById('upload-btn')?.addEventListener('click', () => {
            this.fileInput.click();
        });

        // File input change
        this.fileInput?.addEventListener('change', (e) => {
            if (e.target.files[0]) {
                this.handleFileUpload(e.target.files[0]);
            }
        });

        // Capture button
        document.getElementById('capture-btn')?.addEventListener('click', () => {
            if (this.video.srcObject) {
                this.captureFromWebcam();
            } else {
                this.processUploadedImage();
            }
        });

        // Save button
        document.getElementById('save-btn')?.addEventListener('click', () => {
            this.saveImage();
        });

        // Retake button
        document.getElementById('retake-btn')?.addEventListener('click', () => {
            this.retake();
        });
    }

    async startCamera() {
        try {
            this.stream = await navigator.mediaDevices.getUserMedia({ 
                video: { 
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                } 
            });
            
            this.video.srcObject = this.stream;
            this.video.style.display = 'block';
            this.previewContainer.classList.add('hidden');
            
            // Show overlay preview
            this.updateOverlayPreview();
            
            Utils.showNotification('Camera started successfully');
        } catch (error) {
            console.error('Camera access denied:', error);
            Utils.showNotification('Camera access denied. Please use file upload instead.', 'error');
        }
    }

    stopCamera() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
            this.video.srcObject = null;
        }
    }

    handleFileUpload(file) {
        // Validate file type
        if (!file.type.startsWith('image/')) {
            Utils.showNotification('Please select a valid image file', 'error');
            return;
        }

        // Validate file size (5MB limit)
        if (file.size > 5 * 1024 * 1024) {
            Utils.showNotification('Image file is too large. Maximum size is 5MB.', 'error');
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            this.previewImage.src = e.target.result;
            this.previewImage.onload = () => {
                this.video.style.display = 'none';
                this.previewContainer.classList.remove('hidden');
                this.stopCamera();
                
                // Update overlay preview
                this.updateOverlayPreview();
            };
        };
        reader.readAsDataURL(file);
    }

    captureFromWebcam() {
        if (!this.selectedOverlay) {
            Utils.showNotification('Please select an overlay first', 'error');
            return;
        }

        const context = this.canvas.getContext('2d');
        this.canvas.width = this.video.videoWidth;
        this.canvas.height = this.video.videoHeight;
        
        // Draw video frame
        context.drawImage(this.video, 0, 0);
        
        // Get image data
        this.capturedImageData = this.canvas.toDataURL('image/jpeg', 0.8);
        
        // Show preview
        this.previewImage.src = this.capturedImageData;
        this.video.style.display = 'none';
        this.previewContainer.classList.remove('hidden');
        
        this.showCaptureControls();
    }

    processUploadedImage() {
        if (!this.selectedOverlay) {
            Utils.showNotification('Please select an overlay first', 'error');
            return;
        }

        // Get the uploaded image data
        this.capturedImageData = this.previewImage.src;
        this.showCaptureControls();
    }

    showCaptureControls() {
        document.getElementById('capture-btn').classList.add('hidden');
        document.getElementById('save-btn').classList.remove('hidden');
        document.getElementById('retake-btn').classList.remove('hidden');
        document.getElementById('start-camera-btn').style.display = 'none';
        document.getElementById('upload-btn').style.display = 'none';
    }

    retake() {
        this.capturedImageData = null;
        
        // Hide capture controls
        document.getElementById('capture-btn').classList.remove('hidden');
        document.getElementById('save-btn').classList.add('hidden');
        document.getElementById('retake-btn').classList.add('hidden');
        document.getElementById('start-camera-btn').style.display = 'inline-block';
        document.getElementById('upload-btn').style.display = 'inline-block';
        
        // Reset UI
        this.previewContainer.classList.add('hidden');
        this.video.style.display = 'block';
        
        // Clear overlay selection
        this.selectedOverlay = null;
        document.querySelectorAll('.overlay-option').forEach(opt => {
            opt.classList.remove('selected');
        });
        this.overlayPreview.style.backgroundImage = '';
        document.getElementById('capture-btn').disabled = true;
        
        // Restart camera if it was running
        if (this.stream) {
            this.video.srcObject = this.stream;
        }
    }

    async saveImage() {
        if (!this.capturedImageData || !this.selectedOverlay) {
            Utils.showNotification('No image to save', 'error');
            return;
        }

        // Check if user is logged in
        if (!window.auth?.isLoggedIn()) {
            Utils.showNotification('Please log in to save images', 'error');
            return;
        }

        const saveBtn = document.getElementById('save-btn');
        Utils.setLoading(saveBtn);

        try {
            const formData = new FormData();
            formData.append('action', 'save_image');
            formData.append('image_data', this.capturedImageData);
            formData.append('overlay', this.selectedOverlay);
            formData.append('is_webcam', this.video.srcObject ? '1' : '0');

            const data = await Utils.postFormData('upload.php', formData);

            if (data.success) {
                Utils.showNotification('Image saved successfully!');
                
                // Reset the form
                this.retake();
                
                // Reload user gallery
                if (window.gallery) {
                    window.gallery.loadUserGallery();
                    window.gallery.loadGallery(); // Also refresh public gallery
                }
            } else {
                Utils.showNotification(data.error || 'Failed to save image', 'error');
            }
        } catch (error) {
            Utils.handleError(error, 'Failed to save image');
        } finally {
            Utils.setLoading(saveBtn, false);
        }
    }

    // Clean up resources
    destroy() {
        this.stopCamera();
    }
}