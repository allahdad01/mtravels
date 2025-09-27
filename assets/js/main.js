/**
 * Main JavaScript file for common functionality
 */

$(document).ready(function() {
    // Initialize destination sliders with slick carousel
    $('.destination-slider').slick({
        slidesToShow: 3,
        slidesToScroll: 1,
        autoplay: true,
        autoplaySpeed: 3000,
        dots: true,
        arrows: true,
        infinite: true,
        responsive: [
            {
                breakpoint: 992,
                settings: {
                    slidesToShow: 2
                }
            },
            {
                breakpoint: 576,
                settings: {
                    slidesToShow: 1
                }
            }
        ]
    });
    
    // Initialize deals slider
    $('.deals-slider').slick({
        slidesToShow: 3,
        slidesToScroll: 1,
        autoplay: true,
        autoplaySpeed: 3500,
        dots: true,
        arrows: true,
        infinite: true,
        responsive: [
            {
                breakpoint: 992,
                settings: {
                    slidesToShow: 2
                }
            },
            {
                breakpoint: 576,
                settings: {
                    slidesToShow: 1
                }
            }
        ]
    });
    
    // Initialize blog slider
    $('.blog-slider').slick({
        slidesToShow: 3,
        slidesToScroll: 1,
        autoplay: true,
        autoplaySpeed: 4000,
        dots: true,
        arrows: true,
        infinite: true,
        responsive: [
            {
                breakpoint: 992,
                settings: {
                    slidesToShow: 2
                }
            },
            {
                breakpoint: 576,
                settings: {
                    slidesToShow: 1
                }
            }
        ]
    });
    
    // Initialize parallax effect for hero section
    $(window).scroll(function() {
        var scroll = $(window).scrollTop();
        $('.hero').css({
            'background-position': '50% ' + (scroll * 0.5) + 'px'
        });
    });
    
    // Initialize counter animations
    $('.counter').each(function() {
        $(this).prop('Counter', 0).animate({
            Counter: $(this).text()
        }, {
            duration: 3000,
            easing: 'swing',
            step: function(now) {
                $(this).text(Math.ceil(now));
            }
        });
    });
    
    // Form validation for contact form
    $('#contact-form').submit(function(e) {
        var valid = true;
        var name = $('#name').val();
        var email = $('#email').val();
        var message = $('#message').val();
        
        // Simple validation
        if (name.trim() === '') {
            $('#name').addClass('error');
            valid = false;
        } else {
            $('#name').removeClass('error');
        }
        
        var emailPattern = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/;
        if (!emailPattern.test(email)) {
            $('#email').addClass('error');
            valid = false;
        } else {
            $('#email').removeClass('error');
        }
        
        if (message.trim() === '') {
            $('#message').addClass('error');
            valid = false;
        } else {
            $('#message').removeClass('error');
        }
        
        if (!valid) {
            e.preventDefault();
            return false;
        }
    });
}); 