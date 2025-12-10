/**
 * VoiceRecorder - Handles voice message recording and playback
 */
class VoiceRecorder {
    constructor() {
        this.mediaRecorder = null;
        this.audioChunks = [];
        this.stream = null;
        this.isRecording = false;
        this.startTime = null;
        this.timerInterval = null;
        this.audioContext = null;
        this.analyser = null;
        this.animationId = null;
        this.maxDuration = 300000; // 5 minutes in ms
    }

    /**
     * Initialize the voice recorder
     */
    async init() {
        try {
            // Check browser support
            const audioContext = window.AudioContext || window.webkitAudioContext;
            if (!audioContext) {
                throw new Error('Web Audio API not supported');
            }

            this.audioContext = new audioContext();
            
            // Request microphone permission
            const stream = await navigator.mediaDevices.getUserMedia({ 
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true
                } 
            });
            
            this.stream = stream;
            this.setupMediaRecorder();
            
            return true;
        } catch (error) {
            console.error('[VoiceRecorder] Initialization failed:', error);
            alert('Microphone permission required for voice messages');
            return false;
        }
    }

    /**
     * Setup media recorder
     */
    setupMediaRecorder() {
        if (!this.stream) return;

        const options = {
            mimeType: this.getMimeType()
        };

        this.mediaRecorder = new MediaRecorder(this.stream, options);
        this.audioChunks = [];

        this.mediaRecorder.ondataavailable = (event) => {
            if (event.data.size > 0) {
                this.audioChunks.push(event.data);
            }
        };

        this.mediaRecorder.onstop = () => {
            this.onRecordingStop();
        };
    }

    /**
     * Get supported MIME type
     */
    getMimeType() {
        const types = [
            'audio/webm;codecs=opus',
            'audio/webm',
            'audio/ogg;codecs=opus',
            'audio/mp4'
        ];

        for (let type of types) {
            if (MediaRecorder.isTypeSupported(type)) {
                return type;
            }
        }
        return '';
    }

    /**
     * Start recording
     */
    async startRecording(onTick) {
        try {
            if (!this.mediaRecorder) {
                const success = await this.init();
                if (!success) return false;
            }

            this.audioChunks = [];
            this.isRecording = true;
            this.startTime = Date.now();
            
            // Reset animation
            if (this.animationId) {
                cancelAnimationFrame(this.animationId);
            }

            // Setup audio visualization
            this.setupVisualization();

            this.mediaRecorder.start();

            // Start timer
            this.timerInterval = setInterval(() => {
                const elapsed = Date.now() - this.startTime;
                if (onTick) {
                    onTick(Math.floor(elapsed / 1000));
                }

                // Auto-stop at max duration
                if (elapsed >= this.maxDuration) {
                    this.stopRecording();
                }
            }, 100);

            return true;
        } catch (error) {
            console.error('[VoiceRecorder] Failed to start recording:', error);
            return false;
        }
    }

    /**
     * Stop recording
     */
    stopRecording() {
        return new Promise((resolve) => {
            if (!this.isRecording) {
                resolve(null);
                return;
            }

            this.isRecording = false;
            this.mediaRecorder.stop();

            // Clear timer
            if (this.timerInterval) {
                clearInterval(this.timerInterval);
            }

            // Cancel visualization animation
            if (this.animationId) {
                cancelAnimationFrame(this.animationId);
            }

            // Wait for onstop event
            const checkInterval = setInterval(() => {
                if (this.audioChunks.length > 0) {
                    clearInterval(checkInterval);
                    resolve(this.getAudioBlob());
                }
            }, 50);
        });
    }

    /**
     * Cancel recording
     */
    cancelRecording() {
        if (!this.isRecording) return;

        this.isRecording = false;
        this.mediaRecorder.stop();

        if (this.timerInterval) {
            clearInterval(this.timerInterval);
        }

        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
        }

        this.audioChunks = [];
    }

    /**
     * Get audio blob from recorded chunks
     */
    getAudioBlob() {
        if (this.audioChunks.length === 0) {
            return null;
        }

        const mimeType = this.getMimeType();
        return new Blob(this.audioChunks, { type: mimeType });
    }

    /**
     * Setup audio visualization
     */
    setupVisualization() {
        if (!this.stream || !this.audioContext) return;

        try {
            const source = this.audioContext.createMediaStreamSource(this.stream);
            this.analyser = this.audioContext.createAnalyser();
            source.connect(this.analyser);

            // Dispatch visualization event
            const updateVisualization = () => {
                const dataArray = new Uint8Array(this.analyser.frequencyBinCount);
                this.analyser.getByteFrequencyData(dataArray);
                
                const average = dataArray.reduce((a, b) => a + b) / dataArray.length;
                window.dispatchEvent(new CustomEvent('voiceVisualization', {
                    detail: { level: average / 255 }
                }));

                if (this.isRecording) {
                    this.animationId = requestAnimationFrame(updateVisualization);
                }
            };

            updateVisualization();
        } catch (error) {
            console.warn('[VoiceRecorder] Visualization setup failed:', error);
        }
    }

    /**
     * Handle recording stop
     */
    onRecordingStop() {
        // This is called when mediaRecorder.stop() completes
        window.dispatchEvent(new CustomEvent('recordingStop'));
    }

    /**
     * Play audio blob
     */
    playAudio(blob) {
        const url = URL.createObjectURL(blob);
        const audio = new Audio(url);
        audio.play();
        return audio;
    }

    /**
     * Convert seconds to time string (MM:SS)
     */
    static formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }

    /**
     * Cleanup resources
     */
    dispose() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
        }
        
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
        }
        
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
        }

        if (this.audioContext && this.audioContext.state === 'running') {
            this.audioContext.close();
        }
    }
}

// Export for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = VoiceRecorder;
}
