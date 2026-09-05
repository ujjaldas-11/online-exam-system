/**
 * Examify Examination Countdown Timer
 * Supports dynamic time synchronization, extensions, and automated submission.
 */
(function(window) {
    let timeLeft = 0;
    let timerInterval = null;
    let timerDisplay = null;
    let examForm = null;
    let submitBtn = null;
    let timerText = null;

    function formatTime(totalSeconds) {
        if (totalSeconds < 0) totalSeconds = 0;
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        return `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
    }

    function renderDisplay() {
        if (!timerDisplay) return;

        if (timerText) {
            timerText.textContent = formatTime(timeLeft);
        } else {
            timerDisplay.innerHTML = `⏳ Time Left: ${formatTime(timeLeft)}`;
        }

        if (timeLeft <= 300 && timeLeft > 60) {
            timerDisplay.style.color = 'var(--color-warning, #d97706)';
        } else if (timeLeft <= 60) {
            timerDisplay.style.color = 'var(--color-danger, #dc2626)';
        } else {
            timerDisplay.style.color = '';
        }
    }

    function onTimeExpired() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }

        if (timerText) {
            timerText.textContent = "Time's up!";
        } else if (timerDisplay) {
            timerDisplay.innerHTML = "Time's up! Submitting your exam...";
        }

        if (submitBtn) submitBtn.disabled = true;

        if (window.AntiCheat && typeof window.AntiCheat.stop === 'function') {
            window.AntiCheat.stop();
        }

        if (examForm) {
            examForm.submit();
        }
    }

    function tick() {
        if (timeLeft <= 0) {
            onTimeExpired();
        } else {
            renderDisplay();
            timeLeft--;
        }
    }

    function initTimer() {
        timerDisplay = document.getElementById('timerDisplay');
        examForm = document.getElementById('examForm');
        submitBtn = document.getElementById('submitBtn') || document.getElementById('btn-confirm-submit');
        timerText = document.getElementById('timerText');

        if (!timerDisplay) return;

        const initialSec = parseInt(timerDisplay.getAttribute('data-time-left'), 10);
        timeLeft = isNaN(initialSec) ? 0 : Math.max(0, initialSec);

        renderDisplay();

        if (timerInterval) clearInterval(timerInterval);
        timerInterval = setInterval(tick, 1000);
    }

    // Public API
    window.Timer = {
        init: initTimer,
        getTimeLeft: () => timeLeft,
        syncTimeLeft: function(seconds) {
            if (typeof seconds === 'number' && !isNaN(seconds)) {
                const serverSec = Math.max(0, Math.floor(seconds));
                // Resync if drift exceeds 3 seconds or if time was extended by proctor
                if (Math.abs(timeLeft - serverSec) > 3 || serverSec > timeLeft) {
                    timeLeft = serverSec;
                    renderDisplay();
                }
            }
        },
        addMinutes: function(minutes) {
            if (typeof minutes === 'number' && minutes > 0) {
                timeLeft += Math.floor(minutes * 60);
                renderDisplay();
            }
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTimer);
    } else {
        initTimer();
    }
})(window);
