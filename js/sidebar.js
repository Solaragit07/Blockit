// Enhanced sidebar functionality for BlockIT
$(document).ready(function() {
    // Add click effect to sidebar items
    $('.nav-item .nav-link').click(function(e) {
        // Remove active class from all items
        $('.nav-item').removeClass('active');
        
        // Add active class to clicked item
        $(this).parent('.nav-item').addClass('active');
        
        // Store active state in localStorage
        const href = $(this).attr('href');
        if(href && href !== '#') {
            localStorage.setItem('activeNavItem', href);
        }
    });
    
    // Restore active state from localStorage on page load
    const activeNavItem = localStorage.getItem('activeNavItem');
    if(activeNavItem) {
        $('.nav-item').removeClass('active');
        $(`a[href="${activeNavItem}"]`).parent('.nav-item').addClass('active');
    }
    
    // Handle sidebar toggle for mobile
    $('#sidebarToggle').click(function() {
        $('#accordionSidebar').toggleClass('show');
    });
    
    // Close sidebar when clicking outside on mobile
    $(document).click(function(e) {
        if ($(window).width() <= 768) {
            if (!$(e.target).closest('#accordionSidebar, #sidebarToggle').length) {
                $('#accordionSidebar').removeClass('show');
            }
        }
    });
    
    // Smooth scroll for sidebar
    $('#accordionSidebar').on('scroll', function() {
        // Custom scroll behavior if needed
    });
    
    // Add visual feedback for navigation
    $('.nav-item .nav-link').hover(
        function() {
            if (!$(this).parent('.nav-item').hasClass('active')) {
                $(this).parent('.nav-item').css({
                    'background': 'linear-gradient(135deg, #d4edda, #c3e6cb)',
                    'border-radius': '8px',
                    'margin': '2px 8px'
                });
                $(this).css('color', '#155724');
                $(this).find('i').css('color', '#155724');
            }
        },
        function() {
            if (!$(this).parent('.nav-item').hasClass('active')) {
                $(this).parent('.nav-item').css({
                    'background': '',
                    'border-radius': '',
                    'margin': ''
                });
                $(this).css('color', '');
                $(this).find('i').css('color', '');
            }
        }
    );
});
