document.addEventListener('DOMContentLoaded', function() {
    // Animation des compteurs
    const counters = document.querySelectorAll('.counter');
    const speed = 200;
    
    function animateCounters() {
        counters.forEach(counter => {
            const target = +counter.getAttribute('data-target');
            const count = +counter.innerText;
            const increment = target / speed;
            
            if (count < target) {
                counter.innerText = Math.ceil(count + increment);
                setTimeout(animateCounters, 1);
            } else {
                counter.innerText = target;
            }
        });
    }
    
    // Détecter quand la section est visible pour lancer l'animation
    const statsSection = document.querySelector('.stats-section');
    const observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting) {
            animateCounters();
            observer.unobserve(statsSection);
        }
    });
    
    observer.observe(statsSection);
    
    // Slider des fonctionnalités
    const sliderTrack = document.querySelector('.slider-track');
    const slides = document.querySelectorAll('.slide');
    const prevBtn = document.querySelector('.slider-prev');
    const nextBtn = document.querySelector('.slider-next');
    const dotsContainer = document.querySelector('.slider-dots');
    
    let currentIndex = 0;
    const slideWidth = slides[0].clientWidth;
    const gap = 30;
    const slidesToShow = 3;
    
    // Créer les dots
    slides.forEach((_, index) => {
        const dot = document.createElement('div');
        dot.classList.add('slider-dot');
        if (index === 0) dot.classList.add('active');
        dot.addEventListener('click', () => goToSlide(index));
        dotsContainer.appendChild(dot);
    });
    
    function updateSlider() {
        const newPosition = -(currentIndex * (slideWidth + gap));
        sliderTrack.style.transform = `translateX(${newPosition}px)`;
        
        // Mettre à jour les dots actifs
        document.querySelectorAll('.slider-dot').forEach((dot, index) => {
            dot.classList.toggle('active', index === currentIndex);
        });
    }
    
    function goToSlide(index) {
        currentIndex = index;
        updateSlider();
    }
    
    function nextSlide() {
        if (currentIndex < slides.length - slidesToShow) {
            currentIndex++;
            updateSlider();
        }
    }
    
    function prevSlide() {
        if (currentIndex > 0) {
            currentIndex--;
            updateSlider();
        }
    }
    
    nextBtn.addEventListener('click', nextSlide);
    prevBtn.addEventListener('click', prevSlide);
    
    // Slider des témoignages
    const testimonialTrack = document.querySelector('.testimonial-track');
    const testimonialSlides = document.querySelectorAll('.testimonial-slide');
    const testimonialPrevBtn = document.querySelector('.testimonial-prev');
    const testimonialNextBtn = document.querySelector('.testimonial-next');
    const testimonialDotsContainer = document.querySelector('.testimonial-dots');
    
    let testimonialCurrentIndex = 0;
    
    // Créer les dots pour les témoignages
    testimonialSlides.forEach((_, index) => {
        const dot = document.createElement('div');
        dot.classList.add('testimonial-dot');
        if (index === 0) dot.classList.add('active');
        dot.addEventListener('click', () => goToTestimonialSlide(index));
        testimonialDotsContainer.appendChild(dot);
    });
    
    function updateTestimonialSlider() {
        const newPosition = -(testimonialCurrentIndex * 100);
        testimonialTrack.style.transform = `translateX(${newPosition}%)`;
        
        // Mettre à jour les dots actifs
        document.querySelectorAll('.testimonial-dot').forEach((dot, index) => {
            dot.classList.toggle('active', index === testimonialCurrentIndex);
        });
    }
    
    function goToTestimonialSlide(index) {
        testimonialCurrentIndex = index;
        updateTestimonialSlider();
    }
    
    function nextTestimonialSlide() {
        if (testimonialCurrentIndex < testimonialSlides.length - 1) {
            testimonialCurrentIndex++;
            updateTestimonialSlider();
        } else {
            testimonialCurrentIndex = 0;
            updateTestimonialSlider();
        }
    }
    
    function prevTestimonialSlide() {
        if (testimonialCurrentIndex > 0) {
            testimonialCurrentIndex--;
            updateTestimonialSlider();
        } else {
            testimonialCurrentIndex = testimonialSlides.length - 1;
            updateTestimonialSlider();
        }
    }
    
    testimonialNextBtn.addEventListener('click', nextTestimonialSlide);
    testimonialPrevBtn.addEventListener('click', prevTestimonialSlide);
    
    // Auto-play pour le slider des témoignages
    setInterval(nextTestimonialSlide, 5000);
    
    // Animation au scroll
    const animatedElements = document.querySelectorAll('.stat-item, .feature-card, .news-card');
    
    function checkAnimation() {
        animatedElements.forEach(element => {
            const elementPosition = element.getBoundingClientRect().top;
            const screenPosition = window.innerHeight / 1.2;
            
            if (elementPosition < screenPosition) {
                element.style.opacity = '1';
                element.style.transform = 'translateY(0)';
            }
        });
    }
    
    // Initialiser les éléments comme invisibles
    animatedElements.forEach(element => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        element.style.transition = 'all 0.6s ease';
    });
    
    window.addEventListener('scroll', checkAnimation);
    checkAnimation(); // Vérifier au chargement
    
    // Gestion du formulaire de contact
    const contactForm = document.getElementById('contactForm');
    
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Ici vous pourriez ajouter la logique d'envoi du formulaire
            alert('Message envoyé avec succès! Nous vous contacterons bientôt.');
            contactForm.reset();
        });
    }
});