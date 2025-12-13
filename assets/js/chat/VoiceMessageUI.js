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
        messageGroup.className = `message-group ${isOwn ? 'own' : 'other'}`;
        messageGroup.id = `msg-${message.id}`;

        const messageBubble = document.createElement('div');
        messageBubble.className = `message-bubble voice-message ${isOwn ? 'sent' : 'received'}`;

        const voiceContent = document.createElement('div');
        voiceContent.className = 'voice-content';
        voiceContent.innerHTML = `
            <div class="voice-player">
                <button class="voice-play-btn" data-message-id="${message.id}">
                    <i class="fas fa-play"></i>
                </button>
                <div class="voice-info">
                    <span class="voice-duration">${VoiceRecorder.formatTime(message.duration || 0)}</span>
                    <div class="voice-waveform"></div>
                </div>
            </div>
        `;

        messageBubble.appendChild(voiceContent);
        messageGroup.appendChild(messageBubble);

        // Add message info
        const messageInfo = document.createElement('div');
        messageInfo.className = 'message-info';
        messageInfo.innerHTML = `
            <small class="text-muted">${this.formatMessageTime(message.created_at)}</small>
        `;
        messageGroup.appendChild(messageInfo);

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

            // Check if audio is already playing for this button
            let audio = playBtn._voiceAudio;

            if (!audio) {
                // Create new audio element
                audio = new Audio(url);
                playBtn._voiceAudio = audio;

                audio.addEventListener('play', () => {
                    icon.className = 'fas fa-pause';
                    playBtn.classList.add('playing');
                }, { once: false });

                audio.addEventListener('pause', () => {
                    icon.className = 'fas fa-play';
                    playBtn.classList.remove('playing');
                }, { once: false });

                audio.addEventListener('ended', () => {
                    icon.className = 'fas fa-play';
                    playBtn.classList.remove('playing');
                }, { once: false });

                audio.addEventListener('error', (e) => {
                    console.error('[VoiceMessageUI] Audio playback error:', e);
                    alert('Failed to play voice message');
                    icon.className = 'fas fa-play';
                    playBtn.classList.remove('playing');
                });
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
