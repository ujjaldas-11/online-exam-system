/* LANDING PAGE — Active Navigation & Theme Toggle */
(function () {
    'use strict';

    // Theme toggle management
    var themeToggleBtn = document.getElementById('themeToggleBtn');
    var themeIcon = document.getElementById('themeIcon');
    var htmlElement = document.documentElement;

    var savedTheme = localStorage.getItem('examify-theme') || 'dark';
    setTheme(savedTheme);

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', function () {
            var currentTheme = htmlElement.getAttribute('data-theme');
            var targetTheme = (currentTheme === 'dark') ? 'light' : 'dark';
            setTheme(targetTheme);
        });
    }

    function setTheme(theme) {
        htmlElement.setAttribute('data-theme', theme);
        localStorage.setItem('examify-theme', theme);
        if (themeIcon) {
            themeIcon.textContent = (theme === 'dark') ? 'light_mode' : 'dark_mode';
        }
    }

    // Interactive countdown simulation on mock test card
    var timerDisplay = document.getElementById('liveMockTimer');
    if (timerDisplay) {
        var totalSeconds = 42 * 60 + 4; // 42:04
        setInterval(function () {
            if (totalSeconds > 0) {
                totalSeconds--;
                var minutes = Math.floor(totalSeconds / 60);
                var seconds = totalSeconds % 60;
                timerDisplay.textContent = 
                    (minutes < 10 ? '0' : '') + minutes + ':' + 
                    (seconds < 10 ? '0' : '') + seconds;
            }
        }, 1000);
    }

    // Scroll active link spy
    var sections = document.querySelectorAll('section[id]');
    var navLinks = document.querySelectorAll('.nav-links .nav-item');

    window.addEventListener('scroll', function () {
        var scrollY = window.pageYOffset;

        sections.forEach(function (current) {
            var sectionHeight = current.offsetHeight;
            var sectionTop = current.offsetTop - 120;
            var sectionId = current.getAttribute('id');

            if (scrollY > sectionTop && scrollY <= sectionTop + sectionHeight) {
                navLinks.forEach(function (link) {
                    link.classList.remove('active');
                    if (link.getAttribute('href') === '#' + sectionId) {
                        link.classList.add('active');
                    }
                });
            }
        });
    }, { passive: true });
})();