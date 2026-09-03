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
    const MAX_VIOLATIONS = 2;
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

    let logEndpoint = 'log-violation.php';

    // Handle a violation event
    function triggerViolation(reason) {
        violationCount++;

        // Asynchronously log violation to server
        if (attemptId) {
            fetch(logEndpoint, {
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
    function checkDeviceCompliance() {
        const isCoarseOnly = window.matchMedia && window.matchMedia('(pointer: coarse)').matches && !window.matchMedia('(pointer: fine)').matches;
        const isMobileDimensions = window.screen.width < 992 || window.screen.height < 500;
        const isMobileUAData = (navigator.userAgentData && navigator.userAgentData.mobile === true);
        const isMobileUARegex = /Android|iPhone|iPad|iPod|Mobile|Tablet/i.test(navigator.userAgent);

        // Strict Mobile / Tablet Lockout (even if Request Desktop Site was toggled)
        if ((isMobileDimensions && isCoarseOnly) || isMobileUAData || (isCoarseOnly && isMobileUARegex)) {
            renderMobileLockout();
            return false;
        }

        // Touchscreen Laptop Detection (Desktop OS / resolution, but has touch layer)
        const hasTouchscreen = (navigator.maxTouchPoints > 0) || ('ontouchstart' in window);
        if (hasTouchscreen) {
            const touchWarningEl = document.getElementById('touchscreen-laptop-warning');
            if (touchWarningEl) {
                touchWarningEl.style.display = 'flex';
            }
            enableTouchscreenSuppression();
        }

        return true;
    }

    function renderMobileLockout() {
        const startOverlay = document.getElementById('exam-start-overlay');
        if (startOverlay) {
            startOverlay.innerHTML = `
                <div class="overlay-card" style="max-width: 520px; text-align: center; padding: 36px 24px;">
                    <div style="display: inline-flex; align-items: center; justify-content: center; width: 72px; height: 72px; border-radius: 50%; background: #fee2e2; color: #dc2626; margin-bottom: 16px;">
                        <span class="material-symbols-outlined" style="font-size: 40px;">desktop_windows</span>
                    </div>
                    <h2 style="margin-bottom: 8px; color: var(--color-dark);">Desktop Workstation Required</h2>
                    <p style="color: var(--color-text-secondary); font-size: 0.95rem; line-height: 1.6; margin-bottom: 20px;">
                        Mobile phones and tablets are not permitted for active exams. Please switch to a college lab desktop PC or laptop computer with a physical touchpad/mouse.
                    </p>
                    <a href="dashboard.php" class="btn btn-primary btn-block">Return to Student Dashboard</a>
                </div>
            `;
            startOverlay.style.display = 'flex';
        }
    }

    function enableTouchscreenSuppression() {
        let lastToastTime = 0;
        function handleTouch(e) {
            e.preventDefault();
            e.stopPropagation();

            const now = Date.now();
            if (now - lastToastTime > 1500) {
                lastToastTime = now;
                showTouchToast("Touchscreen input disabled. Please strictly use your touchpad or mouse.");
            }
            return false;
        }

        window.addEventListener('touchstart', handleTouch, { passive: false });
        window.addEventListener('touchmove', handleTouch, { passive: false });
        window.addEventListener('touchend', handleTouch, { passive: false });
    }

    function showTouchToast(msg) {
        let toast = document.getElementById('exam-touch-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'exam-touch-toast';
            toast.style.cssText = 'position:fixed;top:20px;left:50%;transform:translateX(-50%);background:#0f172a;color:#f8fafc;padding:12px 22px;border-radius:10px;font-size:0.92rem;font-weight:600;z-index:999999;box-shadow:0 10px 30px rgba(0,0,0,0.4);border:1px solid #eab308;display:flex;align-items:center;gap:10px;transition:opacity 0.25s ease,transform 0.25s ease;pointer-events:none;';
            document.body.appendChild(toast);
        }
        toast.innerHTML = '<span class="material-symbols-outlined" style="color:#eab308;font-size:22px;">touchpad_mouse</span> <span>' + msg + '</span>';
        toast.style.opacity = '1';
        toast.style.display = 'flex';
        clearTimeout(toast._timeout);
        toast._timeout = setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => { toast.style.display = 'none'; }, 250);
        }, 3200);
    }

    return {
        init: function(options = {}) {
            if (options.attemptId) {
                attemptId = options.attemptId;
            }
            if (options.endpoint) {
                logEndpoint = options.endpoint;
            }
            if (options.onViolation) {
                violationCallback = options.onViolation;
            }
            if (options.onTerminate) {
                terminationCallback = options.onTerminate;
            }

            // Verify device integrity (blocks mobile evasion, manages touchscreen laptop mode)
            checkDeviceCompliance();

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
        stop: function() {
            isExamActive = false;
        },
        isActive: function() {
            return isExamActive;
        },
        getViolations: function() {
            return violationCount;
        }
    };
})();
