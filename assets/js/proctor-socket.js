/**
 * Examify Real-Time Proctoring Client
 * Handles WebSocket connection, live DOM updates, and seamless HTTP polling fallback.
 */
(function () {
    'use strict';

    window.ExamifyProctor = {
        config: {
            examId: 0,
            wsUrl: 'ws://' + window.location.hostname + ':8085',
            pollIntervalMs: 8000,
        },
        socket: null,
        reconnectAttempts: 0,
        maxReconnectAttempts: 5,
        reconnectTimer: null,
        pingTimer: null,
        pollTimer: null,
        isPollingActive: false,

        init: function (config) {
            this.config = Object.assign(this.config, config || {});
            this.injectStyles();
            this.setupStatusIndicator();
            this.connectWebSocket();
        },

        injectStyles: function () {
            if (document.getElementById('proctor-socket-styles')) return;
            const style = document.createElement('style');
            style.id = 'proctor-socket-styles';
            style.textContent = `
                @keyframes pulseHighlight {
                    0% { background-color: rgba(239, 68, 68, 0.25); }
                    50% { background-color: rgba(239, 68, 68, 0.12); }
                    100% { background-color: transparent; }
                }
                @keyframes pulseSuccess {
                    0% { background-color: rgba(16, 185, 129, 0.25); }
                    100% { background-color: transparent; }
                }
                .row-pulse-danger {
                    animation: pulseHighlight 2s ease-out;
                }
                .row-pulse-success {
                    animation: pulseSuccess 2s ease-out;
                }
                .ws-badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    font-size: 0.75rem;
                    font-weight: 600;
                    padding: 4px 10px;
                    border-radius: 9999px;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                }
                .ws-dot {
                    width: 8px;
                    height: 8px;
                    border-radius: 50%;
                    display: inline-block;
                }
                .ws-live { background: rgba(16, 185, 129, 0.15); color: #059669; }
                .ws-live .ws-dot { background: #10b981; box-shadow: 0 0 6px #10b981; }
                .ws-connecting { background: rgba(245, 158, 11, 0.15); color: #d97706; }
                .ws-connecting .ws-dot { background: #f59e0b; }
                .ws-polling { background: rgba(100, 116, 139, 0.15); color: #475569; }
                .ws-polling .ws-dot { background: #64748b; }
            `;
            document.head.appendChild(style);
        },

        setupStatusIndicator: function () {
            const container = document.getElementById('proctor-live-badge-container');
            if (!container) return;
            container.innerHTML = `
                <span id="proctor-live-badge" class="ws-badge ws-connecting">
                    <span class="ws-dot"></span> <span id="proctor-live-label">Connecting...</span>
                </span>
            `;
        },

        updateStatusBadge: function (mode) {
            const badge = document.getElementById('proctor-live-badge');
            const label = document.getElementById('proctor-live-label');
            if (!badge || !label) return;

            badge.className = 'ws-badge ' + (
                mode === 'live' ? 'ws-live' :
                mode === 'polling' ? 'ws-polling' : 'ws-connecting'
            );

            label.textContent = (
                mode === 'live' ? 'Live (WebSocket)' :
                mode === 'polling' ? 'Live (Auto-Sync)' : 'Connecting...'
            );
        },

        connectWebSocket: function () {
            const self = this;
            if (this.socket && this.socket.readyState === WebSocket.OPEN) return;

            try {
                this.updateStatusBadge('connecting');
                this.socket = new WebSocket(this.config.wsUrl);

                this.socket.onopen = function () {
                    self.reconnectAttempts = 0;
                    self.updateStatusBadge('live');
                    self.stopFallbackPolling();

                    // Subscribe to exam channel
                    self.socket.send(JSON.stringify({
                        action: 'subscribe',
                        channel: 'exam:' + self.config.examId
                    }));

                    // Setup heartbeat ping
                    clearInterval(self.pingTimer);
                    self.pingTimer = setInterval(function () {
                        if (self.socket && self.socket.readyState === WebSocket.OPEN) {
                            self.socket.send(JSON.stringify({ action: 'ping' }));
                        }
                    }, 25000);
                };

                this.socket.onmessage = function (event) {
                    try {
                        const payload = JSON.parse(event.data);
                        self.handleSocketEvent(payload);
                    } catch (e) {
                        // Non-JSON frame
                    }
                };

                this.socket.onerror = function () {
                    self.handleDisconnect();
                };

                this.socket.onclose = function () {
                    self.handleDisconnect();
                };
            } catch (err) {
                this.handleDisconnect();
            }
        },

        handleDisconnect: function () {
            clearInterval(this.pingTimer);

            if (this.reconnectAttempts < this.maxReconnectAttempts) {
                this.reconnectAttempts++;
                this.updateStatusBadge('connecting');
                const delay = Math.min(1000 * Math.pow(2, this.reconnectAttempts), 16000);
                clearTimeout(this.reconnectTimer);
                const self = this;
                this.reconnectTimer = setTimeout(function () {
                    self.connectWebSocket();
                }, delay);
            } else {
                // Max attempts reached: Activate HTTP polling fallback
                this.startFallbackPolling();
            }
        },

        startFallbackPolling: function () {
            if (this.isPollingActive) return;
            this.isPollingActive = true;
            this.updateStatusBadge('polling');

            const self = this;
            this.pollStatus(); // Immediate poll
            clearInterval(this.pollTimer);
            this.pollTimer = setInterval(function () {
                self.pollStatus();
            }, this.config.pollIntervalMs);
        },

        stopFallbackPolling: function () {
            this.isPollingActive = false;
            clearInterval(this.pollTimer);
        },

        pollStatus: async function () {
            try {
                const res = await fetch('api-proctor-status.php?exam_id=' + this.config.examId);
                if (!res.ok) return;
                const data = await res.json();
                if (data.success) {
                    this.applyFullSync(data);
                }
            } catch (e) {
                // Background poll error
            }
        },

        handleSocketEvent: function (payload) {
            const event = payload.event;
            const data = payload.data || {};

            if (event === 'violation') {
                this.updateStudentViolation(data.student_id, data.total_violations);
            } else if (event === 'answer_saved') {
                this.updateStudentAnswered(data.student_id, data.answered_count);
            } else if (event === 'student_started') {
                this.updateStudentStarted(data.student_id, data.attempt_id);
            } else if (event === 'exam_submitted') {
                this.updateStudentSubmitted(data.student_id, data.score);
            } else if (event === 'time_extended') {
                this.handleTimeExtended(data.extra_minutes);
            }
        },

        updateStudentViolation: function (studentId, totalViolations) {
            const row = document.getElementById('student-row-' + studentId);
            if (!row) return;

            const violCell = row.querySelector('.col-violations');
            if (violCell) {
                if (totalViolations > 0) {
                    violCell.innerHTML = `
                        <span class="badge badge-rejected" title="Tab switches or fullscreen exit recorded" style="display: inline-flex; align-items: center; gap: 4px;">
                            <span class="material-symbols-outlined icon-xs">warning</span> ${totalViolations}
                        </span>
                    `;
                }
            }

            // Visual highlight pulse
            row.classList.remove('row-pulse-danger');
            void row.offsetWidth; // Force reflow
            row.classList.add('row-pulse-danger');

            this.incrementSummaryViolations();
        },

        updateStudentAnswered: function (studentId, answeredCount) {
            const row = document.getElementById('student-row-' + studentId);
            if (!row) return;

            const ansCell = row.querySelector('.col-answered');
            if (ansCell) {
                const totalQs = row.getAttribute('data-total-questions') || '0';
                ansCell.textContent = answeredCount + ' / ' + totalQs + ' Qs';
            }
        },

        updateStudentStarted: function (studentId, attemptId) {
            const row = document.getElementById('student-row-' + studentId);
            if (!row) return;

            row.setAttribute('data-attempt-id', attemptId);
            const statusCell = row.querySelector('.col-status');
            if (statusCell) {
                statusCell.innerHTML = '<span class="badge badge-running">In Progress</span>';
            }

            row.classList.remove('row-pulse-success');
            void row.offsetWidth;
            row.classList.add('row-pulse-success');

            this.recalculateSummary();
        },

        updateStudentSubmitted: function (studentId, score) {
            const row = document.getElementById('student-row-' + studentId);
            if (!row) return;

            const statusCell = row.querySelector('.col-status');
            if (statusCell) {
                statusCell.innerHTML = '<span class="badge badge-active">Submitted</span>';
            }

            const scoreCell = row.querySelector('.col-score');
            if (scoreCell) {
                const totalMarks = row.getAttribute('data-total-marks') || '0';
                const formatted = Number(score || 0).toFixed(2);
                scoreCell.innerHTML = `<strong>${formatted}</strong> / ${totalMarks}`;
            }

            row.classList.remove('row-pulse-success');
            void row.offsetWidth;
            row.classList.add('row-pulse-success');

            this.recalculateSummary();
        },

        handleTimeExtended: function (extraMinutes) {
            const banner = document.createElement('div');
            banner.className = 'alert alert-success';
            banner.style.position = 'fixed';
            banner.style.top = '20px';
            banner.style.right = '20px';
            banner.style.zIndex = '9999';
            banner.textContent = `Emergency Time Extended: +${extraMinutes} minutes added to this exam.`;
            document.body.appendChild(banner);
            setTimeout(() => banner.remove(), 6000);
        },

        applyFullSync: function (data) {
            const students = data.students || [];
            students.forEach(st => {
                const row = document.getElementById('student-row-' + st.student_id);
                if (!row) return;

                // Status
                const statusCell = row.querySelector('.col-status');
                if (statusCell) {
                    if (st.attempt_status === 'completed') {
                        statusCell.innerHTML = '<span class="badge badge-active">Submitted</span>';
                    } else if (st.attempt_status === 'in_progress') {
                        statusCell.innerHTML = '<span class="badge badge-running">In Progress</span>';
                    } else {
                        statusCell.innerHTML = '<span class="badge badge-inactive">Not Started</span>';
                    }
                }

                // Answered
                const ansCell = row.querySelector('.col-answered');
                if (ansCell) {
                    ansCell.textContent = st.attempt_id ? (st.answered_count + ' / ' + st.total_questions + ' Qs') : '—';
                }

                // Score
                const scoreCell = row.querySelector('.col-score');
                if (scoreCell) {
                    scoreCell.innerHTML = st.attempt_status === 'completed'
                        ? `<strong>${Number(st.score || 0).toFixed(2)}</strong> / ${data.total_marks}`
                        : '—';
                }

                // Violations
                const violCell = row.querySelector('.col-violations');
                if (violCell) {
                    violCell.innerHTML = st.violation_count > 0 ? `
                        <span class="badge badge-rejected" title="Tab switches or fullscreen exit recorded" style="display: inline-flex; align-items: center; gap: 4px;">
                            <span class="material-symbols-outlined icon-xs">warning</span> ${st.violation_count}
                        </span>
                    ` : `
                        <span style="color: var(--color-success); font-weight: bold; display: inline-flex; align-items: center; gap: 2px;">
                            <span class="material-symbols-outlined icon-xs">check</span> 0
                        </span>
                    `;
                }
            });

            // Update Summary Cards
            if (data.summary) {
                const s = data.summary;
                const elTotal = document.getElementById('summary-total-enrolled');
                const elInProg = document.getElementById('summary-in-progress');
                const elComp = document.getElementById('summary-completed');
                const elNotStart = document.getElementById('summary-not-started');
                const elViol = document.getElementById('summary-total-violations');

                if (elTotal) elTotal.textContent = s.total_enrolled;
                if (elInProg) elInProg.textContent = s.in_progress;
                if (elComp) elComp.textContent = s.completed;
                if (elNotStart) elNotStart.textContent = s.not_started;
                if (elViol) elViol.textContent = s.total_violations;
            }
        },

        incrementSummaryViolations: function () {
            const el = document.getElementById('summary-total-violations');
            if (el) {
                const count = parseInt(el.textContent, 10) || 0;
                el.textContent = count + 1;
            }
        },

        recalculateSummary: function () {
            let inProgress = 0;
            let completed = 0;
            let notStarted = 0;

            document.querySelectorAll('tr[id^="student-row-"]').forEach(row => {
                const statusCell = row.querySelector('.col-status');
                if (!statusCell) return;
                const text = statusCell.textContent.trim().toLowerCase();
                if (text.includes('submitted')) completed++;
                else if (text.includes('progress')) inProgress++;
                else notStarted++;
            });

            const elInProg = document.getElementById('summary-in-progress');
            const elComp = document.getElementById('summary-completed');
            const elNotStart = document.getElementById('summary-not-started');

            if (elInProg) elInProg.textContent = inProgress;
            if (elComp) elComp.textContent = completed;
            if (elNotStart) elNotStart.textContent = notStarted;
        }
    };
})();
