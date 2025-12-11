$(document).ready(function() {
    const currentPath = window.location.pathname;
    $('.nav-link').removeClass('active');
    
    if (currentPath.endsWith('posts.html')) {
        $('.nav-link[href="posts.html"]').addClass('active');
    } else if (currentPath.endsWith('index.html') || currentPath === '/' || currentPath.endsWith('/')) {
        $('.nav-link[href="index.html"]').addClass('active');
        $('.nav-link-main').addClass('active');
    } else {
        $('.nav-link').first().addClass('active');
    }

    $('.nav-toggle').click(function() {
        $('.nav-menu').toggleClass('active');
    });

    $('.nav-link').click(function() {
        $('.nav-link').removeClass('active');
        $(this).addClass('active');
        $('.nav-menu').removeClass('active');
    });
});