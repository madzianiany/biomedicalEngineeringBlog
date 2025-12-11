const PostManager = {
    displayPosts: function(posts) {
        const container = $('#postsContainer');
        if (posts.length === 0) {
            container.html('<p>Nie masz jeszcze żadnych postów. Dodaj pierwszy post!</p>');
            return;
        }

        let html = '<div class="posts-list">';
        posts.forEach(function(post) {
            const statusClass = post.status === 'published' ? 'status-published' : 'status-draft';
            const statusText = post.status === 'published' ? 'Opublikowany' : 'Szkic';
            
            html += '<div class="post-card post-manage-card">';
            html += '<div class="post-header">';
            html += '<h3>' + (post.title || 'Bez tytułu') + '</h3>';
            html += '<span class="post-status ' + statusClass + '">' + statusText + '</span>';
            html += '</div>';
            html += '<div class="post-meta">';
            if (post.created_at) {
                html += 'Utworzono: ' + new Date(post.created_at).toLocaleDateString('pl-PL');
            }
            if (post.updated_at && post.updated_at !== post.created_at) {
                html += ' | Zaktualizowano: ' + new Date(post.updated_at).toLocaleDateString('pl-PL');
            }
            html += '</div>';
            html += '<div class="post-content-preview">' + (post.content ? post.content.substring(0, 200) + (post.content.length > 200 ? '...' : '') : '') + '</div>';
            html += '<div class="post-actions">';
            html += '<button class="btn-edit" data-id="' + post.id + '">Edytuj</button>';
            if (post.status === 'draft') {
                html += '<button class="btn-publish" data-id="' + post.id + '">Opublikuj</button>';
            } else {
                html += '<button class="btn-unpublish" data-id="' + post.id + '">Cofnij publikację</button>';
            }
            html += '<button class="btn-delete" data-id="' + post.id + '">Usuń</button>';
            html += '</div>';
            html += '</div>';
        });
        html += '</div>';
        container.html(html);
    },

    showPostModal: function(post = null) {
        const modal = $('#postModal');
        const form = $('#postForm');
        
        if (post) {
            $('#postId').val(post.id);
            $('#postTitle').val(post.title);
            $('#postContent').val(post.content);
            $('#postStatus').val(post.status);
            $('#postModalTitle').text('Edytuj post');
            $('#postSubmitBtn').text('Zaktualizuj');
        } else {
            form[0].reset();
            $('#postId').val('');
            $('#postStatus').val('draft');
            $('#postModalTitle').text('Nowy post');
            $('#postSubmitBtn').text('Utwórz');
        }
        
        modal.show();
    },

    hidePostModal: function() {
        $('#postModal').hide();
        $('#postForm')[0].reset();
        $('#postMessage').hide().removeClass('success error');
    },

    createPost: function(title, content, status) {
        $.ajax({
            url: 'api/posts.php',
            method: 'POST',
            contentType: 'application/json',
            xhrFields: {
                withCredentials: true
            },
            data: JSON.stringify({
                title: title,
                content: content,
                status: status
            }),
            success: function() {
                PostManager.hidePostModal();
                Auth.loadPosts();
            },
            error: function(xhr) {
                const response = JSON.parse(xhr.responseText);
                $('#postMessage').text(response.error).addClass('error').show();
            }
        });
    },

    updatePost: function(id, title, content, status) {
        $.ajax({
            url: 'api/posts.php?id=' + id,
            method: 'PUT',
            contentType: 'application/json',
            xhrFields: {
                withCredentials: true
            },
            data: JSON.stringify({
                title: title,
                content: content,
                status: status
            }),
            success: function() {
                PostManager.hidePostModal();
                Auth.loadPosts();
            },
            error: function(xhr) {
                const response = JSON.parse(xhr.responseText);
                $('#postMessage').text(response.error).addClass('error').show();
            }
        });
    },

    deletePost: function(id) {
        if (!confirm('Czy na pewno chcesz usunąć ten post?')) {
            return;
        }

        $.ajax({
            url: 'api/posts.php?id=' + id,
            method: 'DELETE',
            xhrFields: {
                withCredentials: true
            },
            success: function() {
                Auth.loadPosts();
            },
            error: function(xhr) {
                const response = JSON.parse(xhr.responseText);
                alert('Błąd: ' + response.error);
            }
        });
    },

    togglePublishStatus: function(id, currentStatus) {
        const newStatus = currentStatus === 'draft' ? 'published' : 'draft';
        
        $.ajax({
            url: 'api/posts.php?id=' + id,
            method: 'PUT',
            contentType: 'application/json',
            xhrFields: {
                withCredentials: true
            },
            data: JSON.stringify({
                status: newStatus
            }),
            success: function() {
                Auth.loadPosts();
            },
            error: function(xhr) {
                const response = JSON.parse(xhr.responseText);
                alert('Błąd: ' + response.error);
            }
        });
    }
};

$(document).ready(function() {
    if (window.location.pathname.endsWith('posts.html')) {
        $('#postsSection').prepend('<button class="btn-add-post">+ Dodaj nowy post</button>');

        $(document).on('click', '.btn-add-post', function() {
            PostManager.showPostModal();
        });

        $(document).on('click', '.btn-edit', function() {
            const postId = $(this).data('id');
            $.ajax({
                url: 'api/posts.php?id=' + postId,
                method: 'GET',
                xhrFields: {
                    withCredentials: true
                },
                success: function(post) {
                    PostManager.showPostModal(post);
                },
                error: function() {
                    alert('Błąd podczas ładowania posta.');
                }
            });
        });

        $(document).on('click', '.btn-delete', function() {
            const postId = $(this).data('id');
            PostManager.deletePost(postId);
        });

        $(document).on('click', '.btn-publish, .btn-unpublish', function() {
            const postId = $(this).data('id');
            const currentStatus = $(this).hasClass('btn-publish') ? 'draft' : 'published';
            PostManager.togglePublishStatus(postId, currentStatus);
        });

        $('#postForm').submit(function(e) {
            e.preventDefault();
            const id = $('#postId').val();
            const title = $('#postTitle').val();
            const content = $('#postContent').val();
            const status = $('#postStatus').val();

            if (!title || !content) {
                $('#postMessage').text('Tytuł i treść są wymagane').addClass('error').show();
                return;
            }

            if (id) {
                PostManager.updatePost(id, title, content, status);
            } else {
                PostManager.createPost(title, content, status);
            }
        });

        $('.post-modal-close, .post-modal').click(function(e) {
            if (e.target === this) {
                PostManager.hidePostModal();
            }
        });
    }
});
