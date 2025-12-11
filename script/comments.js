const Comments = {
    loadComments: function(postId) {
        $.ajax({
            url: 'api/comments.php?post_id=' + postId,
            method: 'GET',
            xhrFields: {
                withCredentials: true
            },
            success: function(comments) {
                const commentsList = $('#comments-' + postId);
                if (comments.length === 0) {
                    commentsList.html('<p class="no-comments">Brak komentarzy. Bądź pierwszy!</p>');
                } else {
                    $.ajax({
                        url: 'api/auth.php',
                        method: 'GET',
                        xhrFields: {
                            withCredentials: true
                        },
                        success: function(authResponse) {
                            const currentUserId = authResponse.loggedIn ? authResponse.user.id : null;
                            let html = '';
                            comments.forEach(function(comment) {
                                const isOwner = currentUserId && comment.user_id && parseInt(comment.user_id) === parseInt(currentUserId);
                                html += '<div class="comment-item" data-comment-id="' + comment.id + '">';
                                html += '<div class="comment-header">';
                                html += '<span class="comment-author">' + (comment.author || 'Anonimowy') + '</span>';
                                html += '<span class="comment-date">' + new Date(comment.created_at).toLocaleDateString('pl-PL') + '</span>';
                                html += '</div>';
                                html += '<div class="comment-content" id="comment-content-' + comment.id + '">' + comment.content + '</div>';
                                if (isOwner) {
                                    html += '<div class="comment-actions" data-comment-id="' + comment.id + '">';
                                    html += '<button class="btn-comment-edit" data-comment-id="' + comment.id + '">Edytuj</button>';
                                    html += '<button class="btn-comment-delete" data-comment-id="' + comment.id + '">Usuń</button>';
                                    html += '</div>';
                                }
                                html += '</div>';
                            });
                            commentsList.html(html);
                        },
                        error: function() {
                            let html = '';
                            comments.forEach(function(comment) {
                                html += '<div class="comment-item" data-comment-id="' + comment.id + '">';
                                html += '<div class="comment-header">';
                                html += '<span class="comment-author">' + (comment.author || 'Anonimowy') + '</span>';
                                html += '<span class="comment-date">' + new Date(comment.created_at).toLocaleDateString('pl-PL') + '</span>';
                                html += '</div>';
                                html += '<div class="comment-content" id="comment-content-' + comment.id + '">' + comment.content + '</div>';
                                html += '</div>';
                            });
                            commentsList.html(html);
                        }
                    });
                }
                Comments.renderCommentForm(postId);
            },
            error: function() {
                $('#comments-' + postId).html('<p class="auth-message error">Błąd podczas ładowania komentarzy.</p>');
            }
        });
    },

    renderCommentForm: function(postId) {
        const formContainer = $('#comment-form-' + postId);
        if (formContainer.length === 0) return;

        $.ajax({
            url: 'api/auth.php',
            method: 'GET',
            xhrFields: {
                withCredentials: true
            },
            success: function(response) {
                if (response.loggedIn) {
                    if (formContainer.find('.comment-form').length === 0) {
                        let html = '<form class="comment-form" data-post-id="' + postId + '">';
                        html += '<textarea class="comment-input" placeholder="Napisz komentarz..." required></textarea>';
                        html += '<button type="submit" class="btn-comment-submit">Dodaj komentarz</button>';
                        html += '<div class="comment-message" id="comment-message-' + postId + '"></div>';
                        html += '</form>';
                        formContainer.html(html);
                    }
                } else {
                    formContainer.html('<p class="comment-login-prompt">Zaloguj się, aby dodać komentarz.</p>');
                }
            },
            error: function() {
                formContainer.html('<p class="comment-login-prompt">Zaloguj się, aby dodać komentarz.</p>');
            }
        });
    },

    createComment: function(postId, content) {
        $.ajax({
            url: 'api/comments.php?post_id=' + postId,
            method: 'POST',
            contentType: 'application/json',
            xhrFields: {
                withCredentials: true
            },
            data: JSON.stringify({
                content: content
            }),
            success: function() {
                Comments.loadComments(postId);
                $('#comment-form-' + postId + ' .comment-input').val('');
                $('#comment-message-' + postId).text('').removeClass('success error').hide();
            },
            error: function(xhr) {
                const response = JSON.parse(xhr.responseText);
                $('#comment-message-' + postId).text(response.error).addClass('error').show();
            }
        });
    },

    editComment: function(commentId, newContent) {
        const commentItem = $('.comment-item[data-comment-id="' + commentId + '"]');
        const postId = commentItem.closest('.comments-section').data('post-id');
        
        $.ajax({
            url: 'api/comments.php?id=' + commentId,
            method: 'PUT',
            contentType: 'application/json',
            xhrFields: {
                withCredentials: true
            },
            data: JSON.stringify({
                content: newContent
            }),
            success: function(comment) {
                Comments.loadComments(postId);
            },
            error: function(xhr) {
                const response = JSON.parse(xhr.responseText);
                alert('Błąd: ' + response.error);
                commentItem.find('.btn-comment-save').prop('disabled', false).text('Zapisz');
                commentItem.find('.btn-comment-cancel').prop('disabled', false);
            }
        });
    },

    deleteComment: function(commentId) {
        if (!confirm('Czy na pewno chcesz usunąć ten komentarz?')) {
            return;
        }

        $.ajax({
            url: 'api/comments.php?id=' + commentId,
            method: 'DELETE',
            xhrFields: {
                withCredentials: true
            },
            success: function() {
                const postId = $('.comment-item[data-comment-id="' + commentId + '"]').closest('.comments-section').data('post-id');
                Comments.loadComments(postId);
            },
            error: function(xhr) {
                const response = JSON.parse(xhr.responseText);
                alert('Błąd: ' + response.error);
            }
        });
    }
};

$(document).ready(function() {
    $(document).on('submit', '.comment-form', function(e) {
        e.preventDefault();
        const postId = $(this).data('post-id');
        const content = $(this).find('.comment-input').val().trim();
        if (content) {
            Comments.createComment(postId, content);
        }
    });

    $(document).on('click', '.btn-comment-edit', function() {
        const commentId = $(this).data('comment-id');
        const commentItem = $('.comment-item[data-comment-id="' + commentId + '"]');
        const contentDiv = commentItem.find('.comment-content');
        const commentActions = commentItem.find('.comment-actions');
        const currentContent = contentDiv.text();
        
        if (contentDiv.find('textarea').length > 0) {
            return;
        }
        
        commentActions.hide();
        
        const textarea = $('<textarea class="comment-edit-input">').val(currentContent);
        const saveBtn = $('<button class="btn-comment-save" data-comment-id="' + commentId + '">Zapisz</button>');
        const cancelBtn = $('<button class="btn-comment-cancel" data-comment-id="' + commentId + '">Anuluj</button>');
        const editActions = $('<div class="comment-edit-actions"></div>').append(saveBtn).append(cancelBtn);
        
        contentDiv.html('').append(textarea).append(editActions);
        textarea.focus();
    });

    $(document).on('click', '.btn-comment-save', function() {
        const commentId = $(this).data('comment-id');
        const commentItem = $('.comment-item[data-comment-id="' + commentId + '"]');
        const newContent = commentItem.find('.comment-edit-input').val().trim();
        
        if (!newContent) {
            alert('Treść komentarza nie może być pusta');
            return;
        }
        
        const saveBtn = commentItem.find('.btn-comment-save');
        const cancelBtn = commentItem.find('.btn-comment-cancel');
        saveBtn.prop('disabled', true).text('Zapisywanie...');
        cancelBtn.prop('disabled', true);
        
        Comments.editComment(commentId, newContent);
    });

    $(document).on('click', '.btn-comment-cancel', function() {
        const commentId = $(this).data('comment-id');
        const postId = $('.comment-item[data-comment-id="' + commentId + '"]').closest('.comments-section').data('post-id');
        Comments.loadComments(postId);
    });

    $(document).on('click', '.btn-comment-delete', function() {
        const commentId = $(this).data('comment-id');
        Comments.deleteComment(commentId);
    });
});

