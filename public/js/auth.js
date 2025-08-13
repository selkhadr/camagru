// Authentication management for Camagru

class Auth {
    constructor() {
        this.currentUser = null;
        this.init();
    }

    async init() {
        // Check if user is logged in
        await this.checkAuthStatus();
        this.bindEvents();
    }

    async checkAuthStatus() {
        try {
            const data = await Utils.get('auth.php', { action: 'check' });
            if (data.success && data.user) {
                this.setUser(data.user);
            } else {
                this.clearUser();
            }
        } catch (error) {
            this.clearUser();
        }
    }

    setUser(user) {
        this.currentUser = user;
        this.updateUI();
    }

    clearUser() {
        this.currentUser = null;
        this.updateUI();
    }

    updateUI() {
        const authButtons = document.getElementById('auth-buttons');
        const userMenu = document.getElementById('user-menu');
        const usernameDisplay = document.getElementById('username-display');
        const userGallerySection = document.getElementById('user-gallery-section');
        const cameraSection = document.getElementById('camera-section');

        if (this.currentUser) {
            // Show user menu, hide auth buttons
            authButtons.classList.add('hidden');
            userMenu.classList.remove('hidden');
            usernameDisplay.textContent = `Hello, ${this.currentUser.username}`;
            
            // Show user-specific sections
            if (userGallerySection) userGallerySection.classList.remove('hidden');
            if (cameraSection) cameraSection.classList.remove('hidden');
            
            // Load user gallery
            if (window.gallery) {
                window.gallery.loadUserGallery();
            }
        } else {
            // Show auth buttons, hide user menu
            authButtons.classList.remove('hidden');
            userMenu.classList.add('hidden');
            
            // Hide user-specific sections
            if (userGallerySection) userGallerySection.classList.add('hidden');
        }
    }

    bindEvents() {
        // Modal controls
        const authModal = document.getElementById('auth-modal');
        const profileModal = document.getElementById('profile-modal');
        
        // Close modals
        document.querySelectorAll('.close').forEach(closeBtn => {
            closeBtn.addEventListener('click', (e) => {
                const modal = e.target.closest('.modal');
                if (modal) {
                    modal.classList.add('hidden');
                    document.body.style.overflow = '';
                }
            });
        });

        // Close modal on outside click
        [authModal, profileModal].forEach(modal => {
            if (modal) {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        modal.classList.add('hidden');
                        document.body.style.overflow = '';
                    }
                });
            }
        });

        // Auth button events
        document.getElementById('login-btn')?.addEventListener('click', () => {
            this.showLoginForm();
        });

        document.getElementById('register-btn')?.addEventListener('click', () => {
            this.showRegisterForm();
        });

        document.getElementById('logout-btn')?.addEventListener('click', () => {
            this.logout();
        });

        document.getElementById('profile-btn')?.addEventListener('click', () => {
            this.showProfileForm();
        });

        // Form switch events
        document.getElementById('show-register')?.addEventListener('click', (e) => {
            e.preventDefault();
            this.showRegisterForm();
        });

        document.getElementById('show-login')?.addEventListener('click', (e) => {
            e.preventDefault();
            this.showLoginForm();
        });

        document.getElementById('show-forgot-password')?.addEventListener('click', (e) => {
            e.preventDefault();
            this.showForgotPasswordForm();
        });

        document.getElementById('back-to-login')?.addEventListener('click', (e) => {
            e.preventDefault();
            this.showLoginForm();
        });

        // Form submissions
        document.getElementById('loginForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleLogin(e.target);
        });

        document.getElementById('registerForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleRegister(e.target);
        });

        document.getElementById('forgotPasswordForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleForgotPassword(e.target);
        });

        document.getElementById('profileForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleUpdateProfile(e.target);
        });
    }

    showLoginForm() {
        Utils.openModal('auth-modal');
        document.getElementById('login-form').classList.remove('hidden');
        document.getElementById('register-form').classList.add('hidden');
        document.getElementById('forgot-password-form').classList.add('hidden');
    }

    showRegisterForm() {
        Utils.openModal('auth-modal');
        document.getElementById('login-form').classList.add('hidden');
        document.getElementById('register-form').classList.remove('hidden');
        document.getElementById('forgot-password-form').classList.add('hidden');
    }

    showForgotPasswordForm() {
        document.getElementById('login-form').classList.add('hidden');
        document.getElementById('register-form').classList.add('hidden');
        document.getElementById('forgot-password-form').classList.remove('hidden');
    }

    showProfileForm() {
        Utils.openModal('profile-modal');
        // Pre-fill form with current user data
        document.getElementById('profile-username').value = this.currentUser.username;
        document.getElementById('profile-email').value = this.currentUser.email;
        document.getElementById('profile-notifications').checked = this.currentUser.notifications_enabled !== false;
    }

    async handleLogin(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const email = form.querySelector('#login-email').value;
        const password = form.querySelector('#login-password').value;

        if (!Utils.validateEmail(email)) {
            Utils.showNotification('Please enter a valid email', 'error');
            return;
        }

        Utils.setLoading(submitBtn);

        try {
            const data = await Utils.post('auth.php', {
                action: 'login',
                email,
                password
            });

            if (data.success) {
                this.setUser(data.user);
                Utils.closeModal('auth-modal');
                Utils.showNotification('Login successful!');
                form.reset();
            } else {
                Utils.showNotification(data.error || 'Login failed', 'error');
            }
        } catch (error) {
            Utils.handleError(error, 'Login failed');
        } finally {
            Utils.setLoading(submitBtn, false);
        }
    }

    async handleRegister(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const username = form.querySelector('#register-username').value;
        const email = form.querySelector('#register-email').value;
        const password = form.querySelector('#register-password').value;
        const confirmPassword = form.querySelector('#register-confirm').value;

        // Validation
        if (username.length < 3 || username.length > 20) {
            Utils.showNotification('Username must be 3-20 characters', 'error');
            return;
        }

        if (!Utils.validateEmail(email)) {
            Utils.showNotification('Please enter a valid email', 'error');
            return;
        }

        if (!Utils.validatePassword(password)) {
            Utils.showNotification('Password must be at least 8 characters with uppercase, lowercase, and number', 'error');
            return;
        }

        if (password !== confirmPassword) {
            Utils.showNotification('Passwords do not match', 'error');
            return;
        }

        Utils.setLoading(submitBtn);

        try {
            const data = await Utils.post('auth.php', {
                action: 'register',
                username,
                email,
                password
            });

            if (data.success) {
                Utils.closeModal('auth-modal');
                Utils.showNotification('Registration successful! Please check your email to verify your account.');
                form.reset();
            } else {
                Utils.showNotification(data.error || 'Registration failed', 'error');
            }
        } catch (error) {
            Utils.handleError(error, 'Registration failed');
        } finally {
            Utils.setLoading(submitBtn, false);
        }
    }

    async handleForgotPassword(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const email = form.querySelector('#forgot-email').value;

        if (!Utils.validateEmail(email)) {
            Utils.showNotification('Please enter a valid email', 'error');
            return;
        }

        Utils.setLoading(submitBtn);

        try {
            const data = await Utils.post('auth.php', {
                action: 'forgot_password',
                email
            });

            if (data.success) {
                Utils.showNotification('Password reset link sent to your email!');
                form.reset();
                this.showLoginForm();
            } else {
                Utils.showNotification(data.error || 'Failed to send reset email', 'error');
            }
        } catch (error) {
            Utils.handleError(error, 'Failed to send reset email');
        } finally {
            Utils.setLoading(submitBtn, false);
        }
    }

    async handleUpdateProfile(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const username = form.querySelector('#profile-username').value;
        const email = form.querySelector('#profile-email').value;
        const notifications = form.querySelector('#profile-notifications').checked;

        // Validation
        if (username.length < 3 || username.length > 20) {
            Utils.showNotification('Username must be 3-20 characters', 'error');
            return;
        }

        if (!Utils.validateEmail(email)) {
            Utils.showNotification('Please enter a valid email', 'error');
            return;
        }

        Utils.setLoading(submitBtn);

        try {
            const data = await Utils.post('profile.php', {
                action: 'update',
                username,
                email,
                notifications_enabled: notifications
            });

            if (data.success) {
                this.currentUser = { ...this.currentUser, username, email, notifications_enabled: notifications };
                this.updateUI();
                Utils.closeModal('profile-modal');
                Utils.showNotification('Profile updated successfully!');
            } else {
                Utils.showNotification(data.error || 'Failed to update profile', 'error');
            }
        } catch (error) {
            Utils.handleError(error, 'Failed to update profile');
        } finally {
            Utils.setLoading(submitBtn, false);
        }
    }

    async logout() {
        try {
            await Utils.post('auth.php', { action: 'logout' });
            this.clearUser();
            Utils.showNotification('Logged out successfully');
            
            // Reload gallery to remove user-specific content
            if (window.gallery) {
                window.gallery.loadGallery();
            }
        } catch (error) {
            Utils.handleError(error, 'Logout failed');
        }
    }

    isLoggedIn() {
        return this.currentUser !== null;
    }

    getCurrentUser() {
        return this.currentUser;
    }
}