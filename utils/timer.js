
document.addEventListener("DOMContentLoaded", function() {
    const timerDisplay = document.getElementById('timerDisplay');
    const examForm = document.getElementById('examForm');
    const submitBtn = document.getElementById('submitBtn');

    if (!timerDisplay) return;

    let timeLeft = parseInt(timerDisplay.getAttribute('data-time-left'), 10);

    // Update the timer every 1000ms (1 second)
    const countdown = setInterval(() => {
        if (timeLeft <= 0) {
            // Time is up!
            clearInterval(countdown);
            timerDisplay.innerHTML = "Time's up! Submitting your exam...";
            timerDisplay.style.background = "#333"; // Change color to dark gray
            
            if (submitBtn) submitBtn.disabled = true; // Prevent double clicks
            
            // Automatically submit the form
            if (examForm) examForm.submit();
        } else {
            // Calculate minutes and seconds
            let minutes = Math.floor(timeLeft / 60);
            let seconds = timeLeft % 60;
            
            // Add a leading zero if seconds is less than 10 (e.g., 9:05)
            if (seconds < 10) {
                seconds = "0" + seconds;
            }
            
            // Update the screen
            timerDisplay.innerHTML = `⏳ Time Left: ${minutes}:${seconds}`;
            
            // Decrease time left by 1 second
            timeLeft--;
        }
    }, 1000);
});