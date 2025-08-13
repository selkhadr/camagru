// Gallery management for public and user images

class Gallery {
    constructor() {
        this.currentPage = 1;
        this.totalPages = 1;
        this.imagesPerPage = 5;
        this.currentImages = [];
        
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadGallery();
    }

    bindEvents() {
        // Pagination
        document.getElementById('prev-page')?.addEventListener('click', () => {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.loadGallery();
            }
        });

        document.getElementById('next-page')?.addEventListener('click', () => {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.loadGallery();
            }
        });

        // Modal close for comments
        const commentModal = document.getElementById('comment-modal');
        if (commentModal) {
            commentModal.addEventListener('click', (e) => {
                if (e.target === commentModal) {
                    Utils.closeModal('comment-modal');
                }
            });
        }
    }

    async loadGallery() {
        try {
            const data = await Utils.get('gallery.php', {
                action: 'get_gallery',
                page: this.currentPage,
                limit: this.imagesPerPage
            });

            if (data.success) {
                this.currentImages = data.images;
                this.totalPages = Math.ceil(data.total / this.imagesPerPage);
                this.renderGallery(data.images);
                this.updatePagination();
            }
        } catch (error) {
            Utils.handleError(error, 'Failed to load gallery');
        }
    }

    async loadUserGallery() {
        if (!window.auth?.isLoggedIn()) return;

        try {
            const data = await Utils.get('gallery.php', {
                action: 'get_user_gallery'
            });

            if (data.success) {
                this.renderUserGallery(data.images);
            }
        } catch (error) {
            console.error('Failed to load user gallery:', error);
        }
    }

    renderGallery(images) {
        const gallery = document.getElementById('gallery');
        if (!gallery) return;

        if (images.length === 0) {
            gallery.innerHTML = '<p class="text-center">No images to display</p>';
            return;
        }

        gallery.innerHTML = images.map(image => this.createGalleryItem(image, false)).join('');
        this.bindGalleryEvents();
    }

    renderUserGallery(images) {
        const userGallery = document.getElementById('user-gallery');
        if (!userGallery) return;

        if (images.length === 0) {
            userGallery.innerHTML = '<p class="text-center">You haven\'t created any images yet</p>';
            return;
        }

        userGallery.innerHTML = images.map(image => this.createGalleryItem(image, true)).join('');
        this.bindGalleryEvents();
    }

    createGalleryItem(image, isUserGallery = false) {
        const currentUser = window.auth?.getCurrentUser();
        const isOwner = currentUser && currentUser.id == image.user_id;
        
        return `
            <div class="gallery-item" data-image-id="${image.id}">
                <img src="./images/uploads/${image.filename}" alt="User photo" class="gallery-image" loading="lazy">
                <div class="gallery-info">
                    <div class="gallery-meta">
                        <span>By: <strong>${Utils.sanitizeHTML(image.username)}</strong></span>
                        <span>${Utils.formatDate(image.created_at)}</span>
                    </div>
                    <div class="gallery-actions">
                        <div class="left-actions">
                            <button class="like-btn ${image.user_liked ? 'liked' : ''}" 
                                    data-image-id="${image.id}"
                                    ${!currentUser ? 'disabled' : ''}>
                                ❤️ <span class="like-count">${image.likes_count || 0}</span>
                            </button>
                        </div>
                        <div class="right-actions">
                            <button class="comment-btn" data-image-id="${image.id}">
                                💬 Comments (${image.comments_count || 0})
                            </button>
                            ${isOwner ? `<button class="delete-btn" data-image-id="${image.id}">🗑️ Delete</button>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    bindGalleryEvents() {
        // Like buttons
        document.querySelectorAll('.like-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                await this.toggleLike(btn.dataset.imageId, btn);
            });
        });

        // Comment buttons
        document.querySelectorAll('.comment-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.showComments(btn.dataset.imageId);
            });
        });

        // Delete buttons
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                if (confirm('Are you sure you want to delete this image?')) {
                    await this.deleteImage(btn.dataset.imageId);
                }
            });
        });
    }

    async toggleLike(imageId, button) {
        if (!window.auth?.isLoggedIn()) {
            Utils.showNotification('Please log in to like images', 'error');
            return;
        }

        try {
            const data = await Utils.post('comments.php', {
                action: 'toggle_like',
                image_id: imageId
            });

            if (data.success) {
                // Update button state
                const likeCount = button.querySelector('.like-count');
                if (data.liked) {
                    button.classList.add('liked');
                    likeCount.textContent = parseInt(likeCount.textContent) + 1;
                } else {
                    button.classList.remove('liked');
                    likeCount.textContent = parseInt(likeCount.textContent) - 1;
                }
            }
        } catch (error) {
            Utils.handleError(error, 'Failed to toggle like');
        }
    }

    async showComments(imageId) {
        try {
            const data = await Utils.get('comments.php', {
                action: 'get_comments',
                image_id: imageId
            });

            if (data.success) {
                this.renderCommentsModal(imageId, data.comments, data.image);
                Utils.openModal('comment-modal');
            }
        } catch (error) {
            Utils.handleError(error, 'Failed to load comments');
        }
    }

    renderCommentsModal(imageId, comments, image) {
        const commentContent = document.getElementById('comment-content');
        const currentUser = window.auth?.getCurrentUser();
        
        commentContent.innerHTML = `
            <div class="comments-section">
                <h3>Comments for ${Utils.sanitizeHTML(image.username)}'s photo</h3>
                
                ${currentUser ? `
                    <form class="comment-form" id="comment-form-${imageId}">
                        <div class="form-group">
                            <textarea placeholder="Write a comment..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Post Comment</button>
                    </form>
                ` : '<p>Please log in to post comments.</p>'}
                
                <div class="comments-list">
                    ${comments.length === 0 ? 
                        '<p>No comments yet.</p>' : 
                        comments.map(comment => `
                            <div class="comment">
                                <div class="comment-meta">
                                    <strong>${Utils.sanitizeHTML(comment.username)}</strong>
                                    <span>${Utils.formatDate(comment.created_at)}</span>
                                    ${currentUser && currentUser.id == comment.user_id ? 
                                        `<button class="delete-comment-btn" data-comment-id="${comment.id}">Delete</button>` : 
                                        ''}
                                </div>
                                <div class="comment-content">${Utils.sanitizeHTML(comment.content)}</div>
                            </div>
                        `).join('')
                    }
                </div>
            </div>
        `;

        // Bind comment form
        if (currentUser) {
            const commentForm = document.getElementById(`comment-form-${imageId}`);
            commentForm?.addEventListener('submit', async (e) => {
                e.preventDefault();
                await this.postComment(imageId, commentForm);
            });
        }

        // Bind delete comment buttons
        document.querySelectorAll('.delete-comment-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (confirm('Delete this comment?')) {
                    await this.deleteComment(btn.dataset.commentId, imageId);
                }
            });
        });
    }

    async postComment(imageId, form) {
        const textarea = form.querySelector('textarea');
        const content = textarea.value.trim();
        
            if (!content) {
                Utils.showNotification('Please enter a comment', 'error');
                return;
            }
    
            const submitBtn = form.querySelector('button[type="submit"]');
            Utils.setLoading(submitBtn);
    
            try {
                const data = await Utils.post('comments.php', {
                    action: 'add_comment',
                    image_id: imageId,
                    content: content
                });
    
                if (data.success) {
                    Utils.showNotification('Comment posted successfully!');
                    textarea.value = '';
                    // Refresh comments
                    this.showComments(imageId);
                    
                    // Update comment count in gallery
                    this.updateCommentCount(imageId);
                } else {
                    Utils.showNotification(data.error || 'Failed to post comment', 'error');
                }
            } catch (error) {
                Utils.handleError(error, 'Failed to post comment');
            } finally {
                Utils.setLoading(submitBtn, false);
            }
        }
    
        async deleteComment(commentId, imageId) {
            try {
                const data = await Utils.post('comments.php', {
                    action: 'delete_comment',
                    comment_id: commentId
                });
    
                if (data.success) {
                    Utils.showNotification('Comment deleted successfully!');
                    // Refresh comments
                    this.showComments(imageId);
                    
                    // Update comment count in gallery
                    this.updateCommentCount(imageId, -1);
                } else {
                    Utils.showNotification(data.error || 'Failed to delete comment', 'error');
                }
            } catch (error) {
                Utils.handleError(error, 'Failed to delete comment');
            }
        }
    
        async deleteImage(imageId) {
            try {
                const data = await Utils.post('gallery.php', {
                    action: 'delete_image',
                    image_id: imageId
                });
    
                if (data.success) {
                    Utils.showNotification('Image deleted successfully!');
                    
                    // Remove from DOM
                    const imageElement = document.querySelector(`[data-image-id="${imageId}"]`);
                    if (imageElement) {
                        imageElement.remove();
                    }
                    
                    // Reload galleries
                    this.loadGallery();
                    this.loadUserGallery();
                } else {
                    Utils.showNotification(data.error || 'Failed to delete image', 'error');
                }
            } catch (error) {
                Utils.handleError(error, 'Failed to delete image');
            }
        }
    
        updateCommentCount(imageId, delta = 0) {
            const commentBtn = document.querySelector(`[data-image-id="${imageId}"] .comment-btn`);
            if (commentBtn) {
                const match = commentBtn.textContent.match(/Comments \((\d+)\)/);
                if (match) {
                    const currentCount = parseInt(match[1]);
                    const newCount = Math.max(0, currentCount + delta);
                    commentBtn.textContent = `💬 Comments (${newCount})`;
                }
            }
        }
    
        updatePagination() {
            const prevBtn = document.getElementById('prev-page');
            const nextBtn = document.getElementById('next-page');
            const pageInfo = document.getElementById('page-info');
    
            if (prevBtn) prevBtn.disabled = this.currentPage <= 1;
            if (nextBtn) nextBtn.disabled = this.currentPage >= this.totalPages;
            if (pageInfo) pageInfo.textContent = `Page ${this.currentPage} of ${this.totalPages}`;
        }
    }