/**
 * Anti-Cheat Module for Online Exam System
 *
 * Features:
 * - Fullscreen Enforcement (Starts Exam)
 * - DevTools (F12, Inspect Shortcuts) Prevention
 * - Tab Switching & Window Minimization Detection
 * - Real-Time Server-Side Violation Logging
 */

const AntiCheat = (function() {
    let isExamActive = false;
    let violationCount = 0;
    const MAX_VIOLATIONS = 3;
    let attemptId = null;
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
            e.preventDefault();
            if (!isExamActive) {
                startExam();
            }
        }
    }

    // Start exam in fullscreen
    function startExam() {
        const docElm = document.documentElement;
        const requestFullscreen = docElm.requestFullscreen ||
            docElm.mozRequestFullScreen ||
            docElm.webkitRequestFullScreen ||
            docElm.msRequestFullscreen;

        if (requestFullscreen) {
            requestFullscreen.call(docElm).then(() => {
                isExamActive = true;
                const startOverlay = document.getElementById('exam-start-overlay');
                if (startOverlay) {
                    startOverlay.style.display = 'none';
                }
                document.dispatchEvent(new CustomEvent('examStarted'));
            }).catch(() => {
                alert("You must allow full-screen to start the exam.");
            });
        } else {
            alert("Your browser does not support full-screen mode, which is required for this exam.");
        }
    }

    // Check if fullscreen was exited
    function handleFullscreenChange() {
        const fullscreenElement = document.fullscreenElement ||
            document.mozFullScreenElement ||
            document.webkitFullscreenElement ||
            document.msFullscreenElement;

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

        // Asynchronously log violation to server
        if (attemptId) {
            fetch('../student/log-violation.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    attempt_id: attemptId,
                    violation_type: reason,
                    details: 'Count: ' + violationCount
                })
            }).catch(() => {});
        }

        if (typeof violationCallback === 'function') {
            violationCallback(violationCount, reason);
        } else {
            alert(`WARNING (${violationCount}/${MAX_VIOLATIONS}): ${reason}!\nPlease return to the exam immediately.`);
        }

        if (violationCount >= MAX_VIOLATIONS) {
            terminateExam();
        } else {
            showResumeOverlay(reason);
        }
    }

    // Terminate the exam after max violations
    function terminateExam() {
        isExamActive = false;

        if (typeof terminationCallback === 'function') {
            terminationCallback();
        } else {
            alert("Maximum violations reached. Your exam is being submitted.");
            const examForm = document.getElementById('exam-form') || document.querySelector('form');
            if (examForm) {
                examForm.submit();
            }
        }
    }

    // Force user to re-enter fullscreen
    function showResumeOverlay(reason) {
        if (document.getElementById('anti-cheat-overlay')) {
            return;
        }

        const overlay = document.createElement('div');
        overlay.id = 'anti-cheat-overlay';
        overlay.className = 'fullscreen-overlay';

        overlay.innerHTML = `
            <div class="overlay-card">
                <div class="violation-banner" style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-sm">warning</span> Violation Recorded (${violationCount}/${MAX_VIOLATIONS})
                </div>
                <h2>Exam Paused</h2>
                <p>Reason: <strong>${reason}</strong>.<br>You must return to full-screen mode to continue your exam.</p>
                <button id="anti-cheat-resume-btn" class="btn btn-primary btn-block" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-sm">play_arrow</span> Resume Exam
                </button>
            </div>
        `;

        document.body.appendChild(overlay);

        document.getElementById('anti-cheat-resume-btn').addEventListener('click', () => {
            const docElm = document.documentElement;
            const requestFullscreen = docElm.requestFullscreen ||
                docElm.mozRequestFullScreen ||
                docElm.webkitRequestFullScreen ||
                docElm.msRequestFullscreen;

            if (requestFullscreen) {
                requestFullscreen.call(docElm).then(() => {
                    if (overlay && overlay.parentNode) {
                        overlay.parentNode.removeChild(overlay);
                    }
                }).catch(() => {
                    alert("Failed to enter full-screen. Please click again.");
                });
            }
        });
    }

    // Public API
    return {
        init: function(options = {}) {
            if (options.attemptId) {
                attemptId = options.attemptId;
            }
            if (options.onViolation) {
                violationCallback = options.onViolation;
            }
            if (options.onTerminate) {
                terminationCallback = options.onTerminate;
            }

            document.addEventListener('keydown', handleKeyDown);
            document.addEventListener('contextmenu', e => e.preventDefault());
            document.addEventListener('fullscreenchange', handleFullscreenChange);
            document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
            document.addEventListener('mozfullscreenchange', handleFullscreenChange);
            document.addEventListener('MSFullscreenChange', handleFullscreenChange);
            document.addEventListener('visibilitychange', handleVisibilityChange);
            window.addEventListener('blur', handleBlur);
        },
        start: function() {
            startExam();
        },
        isActive: function() {
            return isExamActive;
        },
        getViolations: function() {
            return violationCount;
        }
    };
})();
