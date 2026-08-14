/**
 * Anti-Cheat Module for Online Exam System
 * 
 * Features:
 * - F11 Fullscreen Enforcement (Starts Exam)
 * - F12 & Context Menu (DevTools) Prevention
 * - Tab Switching/Minimizing Detection
 * - Window Focus Loss Detection
 */

const AntiCheat = (function() {
    let isExamActive = false;
    let violationCount = 0;
    const MAX_VIOLATIONS = 3;
    let violationCallback = null;
    let terminationCallback = null;

    // Handle keydown events
    function handleKeyDown(e) {
        // Prevent F12 (DevTools)
        if (e.key === 'F12' || e.keyCode === 123) {
            e.preventDefault();
            return false;
        }

        // Prevent Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+Shift+C, Ctrl+U
        if (e.ctrlKey && e.shiftKey && ['I', 'J', 'C'].includes(e.key.toUpperCase())) {
            e.preventDefault();
            return false;
        }
        if (e.ctrlKey && e.key.toUpperCase() === 'U') {
            e.preventDefault();
            return false;
        }

        // F11 to start exam in fullscreen
        if (e.key === 'F11' || e.keyCode === 122) {
            e.preventDefault(); // Prevent default browser F11 behavior
            if (!isExamActive) {
                startExam();
            }
        }
    }

    // Start exam in fullscreen
    function startExam() {
        const docElm = document.documentElement;
        const requestFullscreen = docElm.requestFullscreen || docElm.mozRequestFullScreen || docElm.webkitRequestFullScreen || docElm.msRequestFullscreen;

        if (requestFullscreen) {
            requestFullscreen.call(docElm).then(() => {
                isExamActive = true;
                console.log("Exam started. Anti-cheat measures activated.");
                
                // Hide any "Press F11 to Start" overlay if it exists
                const startOverlay = document.getElementById('exam-start-overlay');
                if (startOverlay) {
                    startOverlay.style.display = 'none';
                }
                
                // Dispatch a custom event that main app can listen to
                document.dispatchEvent(new CustomEvent('examStarted'));
            }).catch(err => {
                console.error("Fullscreen request failed:", err);
                alert("You must allow full-screen to start the exam.");
            });
        } else {
            alert("Your browser does not support full-screen mode, which is required for this exam.");
        }
    }

    // Check if fullscreen was exited
    function handleFullscreenChange() {
        const fullscreenElement = document.fullscreenElement || document.mozFullScreenElement || document.webkitFullscreenElement || document.msFullscreenElement;
        
        if (isExamActive && !fullscreenElement) {
            triggerViolation("Exited full-screen mode");
        }
    }

    // Check if tab is switched or window is minimized
    function handleVisibilityChange() {
        if (isExamActive && document.hidden) {
            triggerViolation("Switched tab or minimized window");
        }
    }

    // Check if window loses focus
    function handleBlur() {
        if (isExamActive) {
            triggerViolation("Clicked outside the exam window");
        }
    }

    // Handle a violation event
    function triggerViolation(reason) {
        violationCount++;
        
        if (typeof violationCallback === 'function') {
            violationCallback(violationCount, reason);
        } else {
            alert(`WARNING (${violationCount}/${MAX_VIOLATIONS}): ${reason}!\nPlease return to the exam immediately.`);
        }

        if (violationCount >= MAX_VIOLATIONS) {
            terminateExam();
        } else {
            showResumeOverlay();
        }
    }

    // Terminate the exam after max violations
    function terminateExam() {
        isExamActive = false;
        
        if (typeof terminationCallback === 'function') {
            terminationCallback();
        } else {
            alert("Maximum violations reached. Your exam is terminated.");
        }
    }

    // Force user to re-enter fullscreen
    function showResumeOverlay() {
        if (document.getElementById('anti-cheat-overlay')) return;

        const overlay = document.createElement('div');
        overlay.id = 'anti-cheat-overlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.95);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 999999;
            font-family: Arial, sans-serif;
            text-align: center;
        `;

        overlay.innerHTML = `
            <h1 style="color: #ff4444; margin-bottom: 10px;">Exam Paused</h1>
            <p style="font-size: 18px; margin-bottom: 20px;">Violation recorded. You must return to full-screen to continue.</p>
            <button id="anti-cheat-resume-btn" style="
                padding: 12px 24px;
                font-size: 18px;
                background: #007bff;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            ">Resume Exam</button>
        `;

        document.body.appendChild(overlay);

        document.getElementById('anti-cheat-resume-btn').addEventListener('click', () => {
            const docElm = document.documentElement;
            const requestFullscreen = docElm.requestFullscreen || docElm.mozRequestFullScreen || docElm.webkitRequestFullScreen || docElm.msRequestFullscreen;
            
            if (requestFullscreen) {
                requestFullscreen.call(docElm).then(() => {
                    document.body.removeChild(overlay);
                }).catch(err => {
                    alert("Failed to enter full-screen. Please try again.");
                });
            }
        });
    }

    // Public API
    return {
        /**
         * Initialize the anti-cheat module
         * @param {Object} options Configuration options
         * @param {Function} options.onViolation Callback when a violation occurs: function(count, reason)
         * @param {Function} options.onTerminate Callback when max violations are reached: function()
         */
        init: function(options = {}) {
            if (options.onViolation) violationCallback = options.onViolation;
            if (options.onTerminate) terminationCallback = options.onTerminate;

            document.addEventListener('keydown', handleKeyDown);
            
            // Disable Right Click
            document.addEventListener('contextmenu', e => e.preventDefault());
            
            // Fullscreen change events for various browsers
            document.addEventListener('fullscreenchange', handleFullscreenChange);
            document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
            document.addEventListener('mozfullscreenchange', handleFullscreenChange);
            document.addEventListener('MSFullscreenChange', handleFullscreenChange);
            
            // Tab switch and focus loss events
            document.addEventListener('visibilitychange', handleVisibilityChange);
            window.addEventListener('blur', handleBlur);
            
            console.log("Anti-cheat module initialized. Press F11 to start the exam.");
        },
        isActive: function() {
            return isExamActive;
        },
        getViolations: function() {
            return violationCount;
        }
    };
})();

// AntiCheat.init();
// To be activated from PHP program
