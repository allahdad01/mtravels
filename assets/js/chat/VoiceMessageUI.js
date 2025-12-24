/**
 * VoiceMessageUI - Handles voice message UI interactions
 */
class VoiceMessageUI {
    constructor(chatAPI, voiceRecorder) {
        this.chatAPI = chatAPI;
        this.voiceRecorder = voiceRecorder;
        this.voiceBtn = document.getElementById('voiceBtn');
        this.recordingDuration = 0;
        this.isRecording = false;
        this.setupElements();
    }

    /**
     * Setup UI elements
     */
    setupElements() {
        // Add timer display if not exists
        if (!document.getElementById('voiceTimer')) {
            const timerDiv = document.createElement('div');
            timerDiv.id = 'voiceTimer';
            timerDiv.className = 'voice-timer hidden';
            this.voiceBtn?.parentElement?.insertBefore(timerDiv, this.voiceBtn.nextSibling);
        }

        this.timerDisplay = document.getElementById('voiceTimer');
    }

    /**
     * Initialize voice message UI
     */
    init() {
        if (!this.voiceBtn) {
            console.warn('[VoiceMessageUI] Voice button not found');
            return;
        }

        this.voiceBtn.addEventListener('click', () => this.handleVoiceButtonClick());
        window.addEventListener('voiceVisualization', (e) => this.updateVisualization(e));
        window.addEventListener('recordingStop', () => this.handleRecordingStop());
    }

    /**
     * Handle voice button click
     */
    async handleVoiceButtonClick() {
        if (this.isRecording) {
            // Stop recording
            await this.stopRecording();
        } else {
            // Start recording
            await this.startRecording();
        }
    }

    /**
     * Start recording
     */
    async startRecording() {
        try {
            const contactId = window.chatApp?.manager?.currentContactId;
            if (!contactId) {
                alert('Please select a contact first');
                return;
            }

            const success = await this.voiceRecorder.startRecording((seconds) => {
                this.updateTimer(seconds);
            });

            if (!success) {
                console.error('[VoiceMessageUI] Failed to start recording');
                return;
            }

            this.isRecording = true;
            this.updateRecordingUI(true);
            console.log('[VoiceMessageUI] Recording started');
        } catch (error) {
            console.error('[VoiceMessageUI] Start recording failed:', error);
            alert('Failed to start recording');
        }
    }

    /**
     * Stop recording
     */
    async stopRecording() {
        try {
            this.isRecording = false;
            this.updateRecordingUI(false);

            const audioBlob = await this.voiceRecorder.stopRecording();

            if (!audioBlob) {
                console.warn('[VoiceMessageUI] No audio recorded');
                return;
            }

            // Send voice message
            await this.sendVoiceMessage(audioBlob);
        } catch (error) {
            console.error('[VoiceMessageUI] Stop recording failed:', error);
            alert('Failed to stop recording');
        }
    }

    /**
     * Send voice message
     */
    async sendVoiceMessage(audioBlob) {
        try {
            const contactId = window.chatApp?.manager?.currentContactId;
            if (!contactId) {
                alert('No contact selected');
                return;
            }

            // Show sending indicator
            const sendBtn = document.getElementById('sendBtn');
            const originalHTML = sendBtn?.innerHTML;
            if (sendBtn) {
                sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                sendBtn.disabled = true;
            }

            const duration = this.recordingDuration;

            // Send to API
            const response = await this.chatAPI.sendVoiceMessage(
                contactId,
                audioBlob,
                duration
            );

            if (response.success || response.message_id) {
                console.log('[VoiceMessageUI] Voice message sent successfully');

                // Dispatch event so the chat can update
                window.dispatchEvent(new CustomEvent('voiceMessageSent', {
                    detail: {
                        message: response,
                        duration: duration
                    }
                }));

                // Clear timer
                this.recordingDuration = 0;
                if (this.timerDisplay) {
                    this.timerDisplay.textContent = '';
                    this.timerDisplay.classList.add('hidden');
                }
            } else {
                alert('Failed to send voice message');
            }

            // Reset button
            if (sendBtn) {
                sendBtn.innerHTML = originalHTML;
                sendBtn.disabled = false;
            }
        } catch (error) {
            console.error('[VoiceMessageUI] Failed to send voice message:', error);
            alert('Error sending voice message: ' + error.message);

            // Reset button
            const sendBtn = document.getElementById('sendBtn');
            if (sendBtn) {
                sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
                sendBtn.disabled = false;
            }
        }
    }

    /**
     * Update timer display
     */
    updateTimer(seconds) {
        this.recordingDuration = seconds;

        if (this.timerDisplay) {
            this.timerDisplay.textContent = VoiceRecorder.formatTime(seconds);
        }

        // Warn at max duration
        if (seconds >= 299) {
            if (this.voiceBtn) {
                this.voiceBtn.style.animation = 'pulse 0.5s infinite';
            }
        }
    }

    /**
     * Update visualization during recording
     */
    updateVisualization(event) {
        if (!this.voiceBtn) return;

        const level = event.detail.level || 0;

        // Scale the icon size based on audio level
        const scale = 1 + (level * 0.2);
        this.voiceBtn.style.transform = `scale(${scale})`;
    }

    /**
     * Update recording UI state
     */
    updateRecordingUI(isRecording) {
        if (!this.voiceBtn) return;

        if (isRecording) {
            this.voiceBtn.classList.add('recording');
            this.voiceBtn.title = 'Stop recording';

            if (this.timerDisplay) {
                this.timerDisplay.classList.remove('hidden');
            }
        } else {
            this.voiceBtn.classList.remove('recording');
            this.voiceBtn.style.transform = 'scale(1)';
            this.voiceBtn.title = 'Voice message';

            if (this.timerDisplay) {
                this.timerDisplay.classList.add('hidden');
            }
        }
    }

    /**
     * Handle recording stop
     */
    handleRecordingStop() {
        // Recording has stopped, audio is ready
        console.log('[VoiceMessageUI] Recording stopped, ready to send');
    }

    /**
     * Display voice message in chat
     */
    displayVoiceMessage(message, isOwn = false) {
        const container = document.getElementById('messagesContainer');
        if (!container) return;

        const messageGroup = document.createElement('div');
        messageGroup.className = `message ${isOwn ? 'outgoing' : 'incoming'}`;
        messageGroup.id = `msg-${message.id}`;
        messageGroup.setAttribute('data-message-id', message.id);

        const messageBubble = document.createElement('div');
        messageBubble.className = `message-bubble voice-message ${isOwn ? 'sent' : 'received'}`;

        const voiceContent = document.createElement('div');
        voiceContent.className = 'voice-content';

        // Create waveform bars (30 bars for visualization)
        const waveformBars = Array.from({ length: 30 }, () => {
            const height = Math.floor(Math.random() * 12) + 4;
            return `<div class="waveform-bar" style="height: ${height}px;"></div>`;
        }).join('');

        // Get user initials or avatar
        const senderInitial = message.sender_name ? message.sender_name.charAt(0).toUpperCase() : 'U';
        const avatarURL = message.sender_avatar || '';

        // Format the timestamp
        const timestamp = new Date(message.created_at || Date.now());
        const timeStr = timestamp.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

        voiceContent.innerHTML = `
            <div class="voice-player">
                <button class="voice-play-btn" data-message-id="${message.id}" data-url="${message.url || '#'}" title="Play voice message">
                    <i class="fas fa-play"></i>
                </button>
                <div class="voice-info">
                    <div class="voice-waveform-container">
                        ${waveformBars}
                    </div>
                    <div class="voice-footer">
                        <div class="voice-times">
                            <span class="voice-current-time">0:00</span>
                            <span class="voice-timestamp">${timeStr}</span>
                        </div>
                        <div class="voice-avatar">
                            ${avatarURL ? `<img src="${avatarURL}" alt="${message.sender_name}">` : senderInitial}
                        </div>
                    </div>
                </div>
            </div>
        `;

        messageBubble.appendChild(voiceContent);
        messageGroup.appendChild(messageBubble);

        container.appendChild(messageGroup);

        // Add play button event
        const playBtn = voiceContent.querySelector('.voice-play-btn');
        if (playBtn && message.url) {
            playBtn.addEventListener('click', () => this.playVoiceMessage(message.url, playBtn));
        }

        // Scroll to bottom
        container.scrollTop = container.scrollHeight;
    }

    /**
     * Play voice message
     */
    playVoiceMessage(url, playBtn) {
        if (!url || url === '#') {
            alert('Voice message URL not available');
            return;
        }

        try {
            const icon = playBtn.querySelector('i');
            const voicePlayer = playBtn.closest('.voice-player');

            // Check if audio is already playing for this button
            let audio = playBtn._voiceAudio;

            if (!audio) {
                // Create new audio element
                audio = new Audio(url);
                playBtn._voiceAudio = audio;

                audio.addEventListener('play', () => {
                    icon.className = 'fas fa-pause';
                    playBtn.classList.add('playing');

                    // Stop other playing audios
                    document.querySelectorAll('.voice-play-btn.playing').forEach(btn => {
                        if (btn !== playBtn && btn._voiceAudio) {
                            btn._voiceAudio.pause();
                        }
                    });
                });

                audio.addEventListener('pause', () => {
                    icon.className = 'fas fa-play';
                    playBtn.classList.remove('playing');
                });

                audio.addEventListener('ended', () => {
                    icon.className = 'fas fa-play';
                    playBtn.classList.remove('playing');
                });

                audio.addEventListener('error', (e) => {
                    console.error('[VoiceMessageUI] Audio playback error:', e);
                    alert('Failed to play voice message');
                    icon.className = 'fas fa-play';
                    playBtn.classList.remove('playing');
                });

                audio.addEventListener('timeupdate', () => {
                    this.updateVoiceProgress(voicePlayer, audio);
                });

                audio.addEventListener('loadedmetadata', () => {
                    this.updateVoiceProgress(voicePlayer, audio);
                });
            }

            // Setup seeking - attach to progress bar
            const progressBar = voicePlayer?.querySelector('.voice-progress-bar');
            if (progressBar && !progressBar._seekListener) {
                progressBar._seekListener = (e) => {
                    if (!audio.duration) return;
                    const rect = progressBar.getBoundingClientRect();
                    const clickX = e.clientX - rect.left;
                    const percentage = Math.min(Math.max(clickX / rect.width, 0), 1);
                    audio.currentTime = percentage * audio.duration;
                };
                progressBar.addEventListener('click', progressBar._seekListener);
                progressBar.style.cursor = 'pointer';
            }

            // Toggle play/pause
            if (audio.paused) {
                const playPromise = audio.play();
                if (playPromise !== undefined) {
                    playPromise.catch(error => {
                        console.error('[VoiceMessageUI] Play failed:', error);
                        alert('Failed to play voice message');
                    });
                }
            } else {
                audio.pause();
            }
        } catch (error) {
            console.error('[VoiceMessageUI] Failed to play voice message:', error);
            alert('Failed to play voice message: ' + error.message);
        }
    }

    /**
     * Update voice message progress bar and timer
     */
    updateVoiceProgress(voicePlayer, audio) {
        if (!voicePlayer) {
            console.warn('[VoiceMessageUI] voicePlayer is null');
            return;
        }

        if (!audio || !audio.duration) {
            console.warn('[VoiceMessageUI] audio or duration is not set');
            return;
        }

        const currentTimeEl = voicePlayer.querySelector('.voice-current-time');
        const waveformBars = voicePlayer.querySelectorAll('.waveform-bar');

        // Update current time display
        if (currentTimeEl) {
            const timeStr = VoiceRecorder.formatTime(Math.floor(audio.currentTime));
            currentTimeEl.textContent = timeStr;
        }

        // Update waveform bar progress
        if (waveformBars && waveformBars.length > 0) {
            const progress = (audio.currentTime / audio.duration);
            const playedBarsCount = Math.ceil(progress * waveformBars.length);

            waveformBars.forEach((bar, index) => {
                if (index < playedBarsCount) {
                    bar.classList.add('played');
                } else {
                    bar.classList.remove('played');
                }
            });
        }
    }

    /**
     * Format message timestamp
     */
    formatMessageTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;

        if (diff < 60000) return 'just now';
        if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
        if (diff < 86400000) return Math.floor(diff / 3600000) + 'h ago';

        return date.toLocaleDateString();
    }

    /**
     * Cleanup resources
     */
    dispose() {
        if (this.isRecording) {
            this.voiceRecorder.cancelRecording();
        }
    }
}

// Export for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = VoiceMessageUI;
}
