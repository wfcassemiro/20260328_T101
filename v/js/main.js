// Translators101 - JavaScript principal

document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    
    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
        });
    }
    
    // Smooth scrolling para links âncora
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Lazy loading para imagens
    const images = document.querySelectorAll('img[data-src]');
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                imageObserver.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
    
    // Parallax effect no hero banner
    const heroSection = document.querySelector('.hero-banner');
    if (heroSection) {
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const rate = scrolled * -0.5;
            heroSection.style.transform = `translateY(${rate}px)`;
        });
    }
    
    // Animação de fade-in para elementos
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
            }
        });
    }, observerOptions);
    
    document.querySelectorAll('.animate-on-scroll').forEach(el => {
        observer.observe(el);
    });
    
    // Confirmação para ações importantes
    document.querySelectorAll('.confirm-action').forEach(button => {
        button.addEventListener('click', function(e) {
            const message = this.dataset.confirm || 'Tem certeza?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
    
    // Auto-hide alerts
    document.querySelectorAll('.alert[data-auto-hide]').forEach(alert => {
        const delay = parseInt(alert.dataset.autoHide) || 5000;
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, delay);
    });
    
    // Search functionality
    const searchInput = document.querySelector('#search-input');
    const searchResults = document.querySelector('#search-results');
    
    if (searchInput && searchResults) {
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            
            clearTimeout(searchTimeout);
            
            if (query.length < 2) {
                searchResults.innerHTML = '';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                // Simular busca (implementar busca real conforme necessário)
                performSearch(query);
            }, 300);
        });
    }
    
    // Form validation
    document.querySelectorAll('form[data-validate]').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
    
    // Password strength indicator
    const passwordInput = document.querySelector('input[type="password"][data-strength]');
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            updatePasswordStrength(this.value);
        });
    }
    
    // Card hover effects
    document.querySelectorAll('.lecture-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
    
    // Back to top button
    const backToTopButton = document.querySelector('#back-to-top');
    if (backToTopButton) {
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                backToTopButton.style.display = 'block';
            } else {
                backToTopButton.style.display = 'none';
            }
        });
        
        backToTopButton.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
    
    // Video player enhancements
    document.querySelectorAll('.video-container iframe').forEach(iframe => {
        iframe.addEventListener('load', function() {
            // Video loaded successfully
            console.log('Video loaded');
        });
    });
    
    // Cookie consent (se necessário)
    const cookieConsent = document.querySelector('#cookie-consent');
    if (cookieConsent && !localStorage.getItem('cookie-consent')) {
        cookieConsent.style.display = 'block';
        
        document.querySelector('#accept-cookies')?.addEventListener('click', () => {
            localStorage.setItem('cookie-consent', 'accepted');
            cookieConsent.style.display = 'none';
        });
    }
});

// Função de busca
function performSearch(query) {
    // Implementar busca real via AJAX
    fetch(`/api/search.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            displaySearchResults(data);
        })
        .catch(error => {
            console.error('Erro na busca:', error);
        });
}

function displaySearchResults(results) {
    const searchResults = document.querySelector('#search-results');
    if (!searchResults) return;
    
    if (results.length === 0) {
        searchResults.innerHTML = '<p class="text-gray-400">Nenhum resultado encontrado.</p>';
        return;
    }
    
    const html = results.map(result => `
        <div class="search-result p-4 border-b border-gray-700">
            <h4 class="font-semibold">${result.title}</h4>
            <p class="text-gray-400 text-sm">${result.description}</p>
            <a href="${result.url}" class="text-purple-400 hover:text-purple-300">Ver mais</a>
        </div>
    `).join('');
    
    searchResults.innerHTML = html;
}

// Validação de formulário
function validateForm(form) {
    let isValid = true;
    const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            showFieldError(input, 'Este campo é obrigatório');
            isValid = false;
        } else {
            clearFieldError(input);
        }
        
        // Validações específicas
        if (input.type === 'email' && input.value.trim()) {
            if (!isValidEmail(input.value)) {
                showFieldError(input, 'Email inválido');
                isValid = false;
            }
        }
        
        if (input.type === 'password' && input.value.trim()) {
            if (input.value.length < 6) {
                showFieldError(input, 'A senha deve ter pelo menos 6 caracteres');
                isValid = false;
            }
        }
    });
    
    return isValid;
}

function showFieldError(input, message) {
    clearFieldError(input);
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error text-red-400 text-sm mt-1';
    errorDiv.textContent = message;
    
    input.parentNode.appendChild(errorDiv);
    input.classList.add('border-red-400');
}

function clearFieldError(input) {
    const existingError = input.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
    input.classList.remove('border-red-400');
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Indicador de força da senha
function updatePasswordStrength(password) {
    const strengthIndicator = document.querySelector('#password-strength');
    if (!strengthIndicator) return;
    
    let strength = 0;
    let feedback = [];
    
    if (password.length >= 8) strength++;
    else feedback.push('Pelo menos 8 caracteres');
    
    if (/[a-z]/.test(password)) strength++;
    else feedback.push('Letra minúscula');
    
    if (/[A-Z]/.test(password)) strength++;
    else feedback.push('Letra maiúscula');
    
    if (/\d/.test(password)) strength++;
    else feedback.push('Número');
    
    if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength++;
    else feedback.push('Caractere especial');
    
    const strengthLevels = ['Muito fraca', 'Fraca', 'Regular', 'Boa', 'Forte'];
    const strengthColors = ['red', 'orange', 'yellow', 'blue', 'green'];
    
    strengthIndicator.textContent = strengthLevels[strength] || 'Muito fraca';
    strengthIndicator.className = `text-${strengthColors[strength] || 'red'}-400 text-sm mt-1`;
}

// Utility functions
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} fixed top-4 right-4 z-50 max-w-sm`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(amount);
}

function formatDate(date) {
    return new Intl.DateTimeFormat('pt-BR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    }).format(new Date(date));
}

// Loading states
function showLoading(element) {
    const spinner = document.createElement('div');
    spinner.className = 'spinner mx-auto';
    element.innerHTML = '';
    element.appendChild(spinner);
}

function hideLoading(element, originalContent = '') {
    element.innerHTML = originalContent;
}

// Export functions for use in other scripts
window.Translators101 = {
    showNotification,
    formatCurrency,
    formatDate,
    showLoading,
    hideLoading,
    validateForm
};
