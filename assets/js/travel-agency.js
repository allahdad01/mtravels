/**
 * Wanderlust Travel Agency - Custom JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize loader
    initLoader();
    
    // Initialize navbar scroll effect
    initNavbarScroll();
    
    // Initialize mobile menu
    initMobileMenu();
    
    // Initialize AOS animations
    initAOS();
    
    // Initialize flying airplane animation
    initFlyingPlane();
    
    // Initialize parallax effect
    initParallax();
    
    // Initialize rotating globe
    initGlobe();
    
    // Initialize weather widget
    initWeatherWidget();
    
    // Initialize interactive map
    initInteractiveMap();
    
    // Initialize smooth scrolling
    initSmoothScrolling();
});

/**
 * Initialize page loader
 */
function initLoader() {
    const loader = document.querySelector('.loader');
    
    if (loader) {
        // Enhanced loader animation with bouncing effect
        const plane = loader.querySelector('.plane');
        if (plane) {
            plane.style.animation = 'plane-loader 1.5s ease infinite, plane-bounce 2s ease-in-out infinite';
        }
        
        // Add clouds to loader
        for (let i = 0; i < 5; i++) {
            const cloud = document.createElement('div');
            cloud.classList.add('loader-cloud');
            cloud.style.top = Math.random() * 80 + '%';
            cloud.style.left = Math.random() * 80 + '%';
            cloud.style.animationDelay = (Math.random() * 2) + 's';
            loader.appendChild(cloud);
        }
        
        // Hide loader after page is fully loaded
        window.addEventListener('load', function() {
            setTimeout(function() {
                loader.style.opacity = '0';
                setTimeout(function() {
                    loader.style.display = 'none';
                }, 500);
            }, 1000);
        });
    }
}

/**
 * Initialize navbar scroll effect
 */
function initNavbarScroll() {
    const navbar = document.querySelector('.navbar');
    
    if (navbar) {
        // Enhanced navbar animation
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
                // Add subtle shadow animation
                navbar.style.boxShadow = '0 2px 20px rgba(0, 0, 0, 0.1)';
                navbar.style.padding = '10px 0';
            } else {
                navbar.classList.remove('scrolled');
                navbar.style.boxShadow = 'none';
                navbar.style.padding = '20px 0';
            }
        });
    }
}

/**
 * Initialize mobile menu
 */
function initMobileMenu() {
    const mobileToggle = document.querySelector('.hamburger');
    const navLinks = document.querySelector('.nav-links');
    
    if (mobileToggle && navLinks) {
        // Enhanced mobile menu animation
        mobileToggle.addEventListener('click', function() {
            navLinks.classList.toggle('active');
            document.body.classList.toggle('menu-open');
            
            // Animate hamburger to X
            this.classList.toggle('active');
        });
        
        // Close menu when clicking on a link
        const links = navLinks.querySelectorAll('a');
        links.forEach(link => {
            link.addEventListener('click', function() {
                navLinks.classList.remove('active');
                document.body.classList.remove('menu-open');
                mobileToggle.classList.remove('active');
            });
        });
    }
}

/**
 * Initialize AOS animations
 */
function initAOS() {
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 800,
            easing: 'ease',
            once: true,
            offset: 100,
            delay: 100
        });
    }
}

/**
 * Initialize flying airplane animation
 */
function initFlyingPlane() {
    // Create flying plane element
    const plane = document.createElement('div');
    plane.classList.add('flying-plane');
    plane.innerHTML = `<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M21,16V14L13,9V3.5A1.5,1.5,0,0,0,11.5,2h0A1.5,1.5,0,0,0,10,3.5V9L2,14V16L10,13.5V19L8,20.5V22L11.5,21L15,22V20.5L13,19V13.5Z" />
    </svg>`;
    document.body.appendChild(plane);
    
    // Variables for plane position and movement
    let planeX = -100;
    let planeY = window.innerHeight / 2;
    let targetX = -100;
    let targetY = window.innerHeight / 2;
    let flyingActive = false;
    
    // Enhanced plane animation with smoke trail
    plane.innerHTML += '<div class="plane-trail"></div>';
    const trail = plane.querySelector('.plane-trail');
    
    // Update plane position based on scroll
    window.addEventListener('scroll', function() {
        const scrollPercent = window.scrollY / (document.body.scrollHeight - window.innerHeight);
        
        // Start flying at 15% scroll
        if (scrollPercent > 0.15 && !flyingActive) {
            flyingActive = true;
            targetX = window.innerWidth + 100;
            animatePlane();
            createSmokeParticles(plane);
        }
        
        // Flying back at 70% scroll
        if (scrollPercent > 0.7 && flyingActive && planeX > window.innerWidth) {
            flyingActive = false;
            plane.style.transform = 'scaleX(-1)';
            targetX = -100;
            animatePlane();
        }
        
        // Update Y position based on scroll with smooth wave pattern
        targetY = window.innerHeight * 0.2 + scrollPercent * window.innerHeight * 0.5 
                + Math.sin(scrollPercent * 10) * 30;
    });
    
    // Create smoke particles behind the plane
    function createSmokeParticles(plane) {
        if (!flyingActive) return;
        
        const particle = document.createElement('div');
        particle.classList.add('smoke-particle');
        particle.style.left = (parseInt(plane.style.left) || 0) - 20 + 'px';
        particle.style.top = (parseInt(plane.style.top) || 0) + 10 + 'px';
        document.body.appendChild(particle);
        
        // Remove particle after animation completes
        setTimeout(() => {
            particle.remove();
        }, 3000);
        
        // Continue creating particles
        if (flyingActive) {
            setTimeout(() => createSmokeParticles(plane), 200);
        }
    }
    
    // Animate plane movement
    function animatePlane() {
        function update() {
            // Smoothly move towards target position
            planeX += (targetX - planeX) * 0.01;
            planeY += (targetY - planeY) * 0.05;
            
            // Apply position
            plane.style.left = planeX + 'px';
            plane.style.top = planeY + 'px';
            
            // Apply rotation based on vertical movement
            const verticalChange = (targetY - planeY) * 0.1;
            plane.style.transform = flyingActive ? 
                `scaleX(1) rotate(${Math.min(Math.max(verticalChange, -15), 15)}deg)` : 
                `scaleX(-1) rotate(${Math.min(Math.max(-verticalChange, -15), 15)}deg)`;
            
            // Continue animation if not reached target
            if ((targetX > 0 && planeX < targetX) || (targetX < 0 && planeX > targetX)) {
                requestAnimationFrame(update);
            } else {
                // Reset plane direction when target reached
                if (targetX < 0) {
                    plane.style.transform = 'scaleX(1)';
                }
            }
        }
        
        // Start animation
        update();
    }
}

/**
 * Initialize parallax effect for sections
 */
function initParallax() {
    const parallaxElements = document.querySelectorAll('.parallax');
    
    if (parallaxElements.length) {
        window.addEventListener('scroll', function() {
            const scrollTop = window.scrollY;
            
            parallaxElements.forEach(element => {
                const speed = element.getAttribute('data-speed') || 0.5;
                const offset = element.offsetTop;
                const height = element.offsetHeight;
                
                if (scrollTop + window.innerHeight > offset && scrollTop < offset + height) {
                    const yPos = (scrollTop - offset) * speed;
                    element.style.backgroundPosition = `center ${yPos}px`;
                }
            });
        });
    }
}

/**
 * Initialize rotating globe animation
 */
function initGlobe() {
    const globeContainer = document.querySelector('.globe-container');
    
    if (globeContainer) {
        const globe = document.createElement('div');
        globe.classList.add('globe');
        globeContainer.appendChild(globe);
        
        // Add continents to the globe
        const continents = ['asia', 'africa', 'north-america', 'south-america', 'europe', 'australia', 'antarctica'];
        continents.forEach(continent => {
            const continentEl = document.createElement('div');
            continentEl.classList.add('continent', continent);
            globe.appendChild(continentEl);
        });
        
        // Add rotation animation
        let rotationDegree = 0;
        function rotateGlobe() {
            rotationDegree += 0.1;
            globe.style.transform = `rotateY(${rotationDegree}deg)`;
            requestAnimationFrame(rotateGlobe);
        }
        
        rotateGlobe();
    }
}

/**
 * Initialize weather widget
 */
function initWeatherWidget() {
    const weatherWidget = document.querySelector('.weather-widget');
    
    if (weatherWidget) {
        // Simulate weather data (in a real app, you would fetch from an API)
        const destinations = [
            { city: 'Paris', temp: '18°C', icon: 'sun' },
            { city: 'Tokyo', temp: '24°C', icon: 'cloud-sun' },
            { city: 'New York', temp: '22°C', icon: 'cloud' },
            { city: 'Sydney', temp: '26°C', icon: 'sun' },
            { city: 'Dubai', temp: '36°C', icon: 'sun' }
        ];
        
        let currentIndex = 0;
        
        function updateWeather() {
            const destination = destinations[currentIndex];
            weatherWidget.innerHTML = `
                <div class="weather-icon"><i class="fas fa-${destination.icon}"></i></div>
                <div class="weather-info">
                    <div class="weather-city">${destination.city}</div>
                    <div class="weather-temp">${destination.temp}</div>
                </div>
            `;
            
            currentIndex = (currentIndex + 1) % destinations.length;
            
            // Animate widget
            weatherWidget.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                weatherWidget.style.transform = 'translateY(0)';
            }, 500);
        }
        
        // Initial update
        updateWeather();
        
        // Update every 5 seconds
        setInterval(updateWeather, 5000);
    }
}

/**
 * Initialize interactive map
 */
function initInteractiveMap() {
    const interactiveMap = document.querySelector('.interactive-map');
    
    if (interactiveMap) {
        // Add map pins for popular destinations
        const destinations = [
            { name: 'Paris', x: 48.5, y: 31.5 },
            { name: 'Tokyo', x: 82.5, y: 34.5 },
            { name: 'New York', x: 25.5, y: 32.5 },
            { name: 'Sydney', x: 88.5, y: 71.5 },
            { name: 'Cairo', x: 55.5, y: 40.5 },
            { name: 'Rio', x: 35.5, y: 62.5 }
        ];
        
        destinations.forEach(dest => {
            const pin = document.createElement('div');
            pin.classList.add('map-pin');
            pin.style.left = `${dest.x}%`;
            pin.style.top = `${dest.y}%`;
            pin.innerHTML = `
                <div class="pin-dot"></div>
                <div class="pin-pulse"></div>
                <div class="pin-label">${dest.name}</div>
            `;
            
            interactiveMap.appendChild(pin);
            
            // Add hover effect
            pin.addEventListener('mouseenter', function() {
                this.classList.add('active');
            });
            
            pin.addEventListener('mouseleave', function() {
                this.classList.remove('active');
            });
        });
    }
}

/**
 * Smooth scrolling for anchor links
 */
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        
        const targetId = this.getAttribute('href');
        if (targetId === '#') return;
        
        const targetElement = document.querySelector(targetId);
        if (targetElement) {
            // Smooth scroll with easing
            const startPosition = window.scrollY;
            const targetPosition = targetElement.offsetTop - 80;
            const distance = targetPosition - startPosition;
            const duration = 1000;
            let start = null;
            
            function step(timestamp) {
                if (!start) start = timestamp;
                const progress = timestamp - start;
                const percentage = Math.min(progress / duration, 1);
                
                // Easing function: easeInOutCubic
                const easing = percentage < 0.5
                    ? 4 * percentage * percentage * percentage
                    : 1 - Math.pow(-2 * percentage + 2, 3) / 2;
                
                window.scrollTo(0, startPosition + distance * easing);
                
                if (progress < duration) {
                    window.requestAnimationFrame(step);
                }
            }
            
            window.requestAnimationFrame(step);
        }
    });
});

/**
 * Destination filter functionality
 */
function initDestinationFilter() {
    const filterButtons = document.querySelectorAll('.destination-filter button');
    const destinations = document.querySelectorAll('.destination-card');
    
    if (filterButtons.length && destinations.length) {
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons
                filterButtons.forEach(btn => btn.classList.remove('active'));
                
                // Add active class to clicked button
                this.classList.add('active');
                
                const filter = this.getAttribute('data-filter');
                
                // Enhanced filter animation
                destinations.forEach(destination => {
                    // Add fade-out effect
                    destination.style.transition = 'all 0.5s ease';
                    destination.style.opacity = '0.3';
                    destination.style.transform = 'scale(0.95)';
                    
                    setTimeout(() => {
                        if (filter === 'all') {
                            destination.style.display = 'block';
                        } else {
                            const category = destination.getAttribute('data-category');
                            destination.style.display = category === filter ? 'block' : 'none';
                        }
                        
                        // Add fade-in effect for visible elements
                        if (destination.style.display === 'block') {
                            setTimeout(() => {
                                destination.style.opacity = '1';
                                destination.style.transform = 'scale(1)';
                            }, 50);
                        }
                    }, 300);
                });
            });
        });
    }
}

/**
 * Countdown timer for deals
 */
function initCountdownTimers() {
    const countdowns = document.querySelectorAll('.deal-countdown');
    
    if (countdowns.length) {
        countdowns.forEach(countdown => {
            const endDate = new Date(countdown.getAttribute('data-end-date')).getTime();
            
            const timer = setInterval(function() {
                const now = new Date().getTime();
                const distance = endDate - now;
                
                if (distance < 0) {
                    clearInterval(timer);
                    countdown.innerHTML = '<div class="countdown-expired">Expired</div>';
                    return;
                }
                
                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                countdown.innerHTML = `
                    <div class="countdown-item"><span>${days}</span><span class="countdown-label">Days</span></div>
                    <div class="countdown-item"><span>${hours}</span><span class="countdown-label">Hours</span></div>
                    <div class="countdown-item"><span>${minutes}</span><span class="countdown-label">Mins</span></div>
                    <div class="countdown-item"><span>${seconds}</span><span class="countdown-label">Secs</span></div>
                `;
                
                // Add pulse animation to seconds
                const secondsElement = countdown.querySelector('.countdown-item:last-child span:first-child');
                secondsElement.classList.add('pulse');
                setTimeout(() => {
                    secondsElement.classList.remove('pulse');
                }, 500);
                
            }, 1000);
        });
    }
}

/**
 * Initialize testimonial slider with Swiper
 */
function initTestimonialSlider() {
    const slider = document.querySelector('.testimonial-slider');
    
    if (slider && typeof Swiper !== 'undefined') {
        new Swiper('.testimonial-slider', {
            slidesPerView: 1,
            spaceBetween: 30,
            loop: true,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            effect: 'coverflow',
            coverflowEffect: {
                rotate: 30,
                slideShadows: false,
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
                dynamicBullets: true
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            breakpoints: {
                768: {
                    slidesPerView: 2,
                    spaceBetween: 30,
                },
                1024: {
                    slidesPerView: 3,
                    spaceBetween: 30,
                },
            }
        });
    }
}

/**
 * Initialize smooth scrolling for anchor links
 */
function initSmoothScrolling() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// Call additional initializations if needed
window.addEventListener('load', function() {
    initDestinationFilter();
    initCountdownTimers();
    initTestimonialSlider();
    
    // Add animations to CTA buttons
    document.querySelectorAll('.btn-primary, .btn-secondary, .btn-outline').forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.classList.add('btn-hover');
        });
        
        button.addEventListener('mouseleave', function() {
            this.classList.remove('btn-hover');
        });
    });
    
    // Add typing effect to hero title
    const heroTitle = document.querySelector('.hero-content h1');
    if (heroTitle) {
        const text = heroTitle.textContent;
        heroTitle.textContent = '';
        
        let i = 0;
        function typeWriter() {
            if (i < text.length) {
                heroTitle.textContent += text.charAt(i);
                i++;
                setTimeout(typeWriter, 100);
            }
        }
        
        setTimeout(typeWriter, 1000);
    }
}); 