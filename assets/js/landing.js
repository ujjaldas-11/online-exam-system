/* LANDING PAGE — Nav scroll shadow & Feature card spotlight/3D tilt */
(function () {
    'use strict';

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var hasFinePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    /* ---------- Nav shadow on scroll ---------- */
    var nav = document.querySelector('.landing-nav');
    if (nav) {
        var onScroll = function () {
            nav.classList.toggle('is-scrolled', window.scrollY > 8);
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    if (reduceMotion || !hasFinePointer) {
        return;
    }

    /* ---------- Feature cards: cursor spotlight + subtle 3D tilt ---------- */
    document.querySelectorAll('.feature-card').forEach(function (card) {
        card.addEventListener('mousemove', function (e) {
            var r = card.getBoundingClientRect();
            var px = (e.clientX - r.left) / r.width;
            var py = (e.clientY - r.top) / r.height;
            card.style.setProperty('--mx', (px * 100) + '%');
            card.style.setProperty('--my', (py * 100) + '%');
            card.style.transform =
                'rotateX(' + ((0.5 - py) * 6) + 'deg) ' +
                'rotateY(' + ((px - 0.5) * 8) + 'deg) translateY(-3px)';
        });

        card.addEventListener('mouseleave', function () {
            card.style.transform = '';
        });
    });
})();