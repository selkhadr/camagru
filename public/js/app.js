// Main application initialization and management

class CamagruApp {
    constructor() {
        this.auth = null;
        this.webcam = null;
        this.gallery = null;
        this.init();
    }

    async init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.initializeApp());
        } else {
            this.initializeApp();
        }
    }

    async initializeApp() {
        try {
            // Initialize core modules
            this.auth = new Auth();
            this.webcam = new WebcamManager();
            this.gallery = new Gallery();

            // Make instances globally available
            window.auth = this.auth;
            window.webcam = this.webcam;
            window.gallery = this.gallery;

            // Handle URL parameters (for email verification, password reset)
            this.handleURLParams();

            // Initialize service worker for PWA capabilities (optional)
            this.initServiceWorker();

            console.log('Camagru application initialized successfully');
        } catch (error) {
            console.error('Failed to initialize application:', error);
            Utils.showNotification('Failed to initialize application', 'error');
        }
    }

    handleURLParams() {
        const urlParams = new URLSearchParams(window.location.search);
        
        // Handle email verification
        const verifyToken = urlParams.get('verify');
        if (verifyToken) {
            this.handleEmailVerification(verifyToken);
        }

        // Handle password reset
        const resetToken = urlParams.get('reset');
        if (resetToken) {
            this.handlePasswordReset(resetToken);
        }
    }

    async handleEmailVerification(token) {
        try {
            const data = await Utils.get('auth.php', {
                action: 'verify',
                token: token
            });

            if (data.success) {
                Utils.showNotification('Email verified successfully! You can now log in.');
            } else {
                Utils.showNotification(data.error || 'Email verification failed', 'error');
            }
        } catch (error) {
            Utils.handleError(error, 'Email verification failed');
        }

        // Clean URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    async handlePasswordReset(token) {
        // Show password reset form
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Reset Your Password</h2>
                <form id="reset-password-form">
                    <div class="form-group">
                        <label for="new-password">New Password</label>
                        <input type="password" id="new-password" placeholder="Enter new password" required>
                        <small>Must be at least 8 characters with uppercase, lowercase, and number</small>
                    </div>
                    <div class="form-group">
                        <label for="confirm-new-password">Confirm Password</label>
                        <input type="password" id="confirm-new-password" placeholder="Confirm new password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </form>
            </div>
        `;

        document.body.appendChild(modal);

        // Bind events
        modal.querySelector('.close').addEventListener('click', () => {
            modal.remove();
            window.history.replaceState({}, document.title, window.location.pathname);
        });

        modal.querySelector('#reset-password-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.submitPasswordReset(token, e.target, modal);
        });

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });
    }

    async submitPasswordReset(token, form, modal) {
        const newPassword = form.querySelector('#new-password').value;
        const confirmPassword = form.querySelector('#confirm-new-password').value;

        if (!Utils.validatePassword(newPassword)) {
            Utils.showNotification('Password must be at least 8 characters with uppercase, lowercase, and number', 'error');
            return;
        }

        if (newPassword !== confirmPassword) {
            Utils.showNotification('Passwords do not match', 'error');
            return;
        }

        const submitBtn = form.querySelector('button[type="submit"]');
        Utils.setLoading(submitBtn);

        try {
            const data = await Utils.post('auth.php', {
                action: 'reset_password',
                token: token,
                password: newPassword
            });

            if (data.success) {
                Utils.showNotification('Password reset successfully! You can now log in.');
                modal.remove();
                window.history.replaceState({}, document.title, window.location.pathname);
            } else {
                Utils.showNotification(data.error || 'Password reset failed', 'error');
            }
        } catch (error) {
            Utils.handleError(error, 'Password reset failed');
        } finally {
            Utils.setLoading(submitBtn, false);
        }
    }

    initServiceWorker() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('./sw.js')
                .then(registration => {
                    console.log('Service Worker registered:', registration);
                })
                .catch(error => {
                    console.log('Service Worker registration failed:', error);
                });
        }
    }

    // Handle application errors globally
    handleGlobalError(error) {
        console.error('Global error:', error);
        Utils.showNotification('An unexpected error occurred', 'error');
    }

    // Clean up resources when page unloads
    cleanup() {
        if (this.webcam) {
            this.webcam.destroy();
        }
    }
}

// Initialize application
const app = new CamagruApp();

// Global error handling
window.addEventListener('error', (event) => {
    app.handleGlobalError(event.error);
});

window.addEventListener('unhandledrejection', (event) => {
    app.handleGlobalError(event.reason);
    event.preventDefault();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    app.cleanup();
});

// Export for module compatibility
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CamagruApp;
}