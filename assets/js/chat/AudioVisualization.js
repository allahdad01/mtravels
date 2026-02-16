/**
 * AudioVisualization - Real-time audio frequency visualization
 * Analyzes audio playback and updates waveform bars based on frequency data
 */
class AudioVisualization {
    constructor() {
        this.audioContexts = new Map();
        this.analysers = new Map();
        this.animationFrames = new Map();
    }

    /**
     * Initialize audio visualization for a voice message
     */
    initVisualization(messageId, audioElement, waveformContainer) {
        if (!audioElement || !waveformContainer) return;

        try {
            // Create audio context if not exists
            let audioContext = this.audioContexts.get(messageId);
            if (!audioContext) {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                audioContext = new AudioContext();
                this.audioContexts.set(messageId, audioContext);
            }

            // Create analyser if not exists
            let analyser = this.analysers.get(messageId);
            if (!analyser) {
                analyser = audioContext.createAnalyser();
                analyser.fftSize = 256;
                analyser.smoothingTimeConstant = 0.8;

                // Connect audio element to analyser
                const source = audioContext.createMediaElementAudioSource(audioElement);
                source.connect(analyser);
                analyser.connect(audioContext.destination);

                this.analysers.set(messageId, analyser);
            }

            // Resume audio context if suspended
            if (audioContext.state === 'suspended') {
                audioContext.resume();
            }

            // Start visualization with audio element for progress tracking
            this.startVisualization(messageId, analyser, waveformContainer, audioElement);
        } catch (error) {

        }
    }

    /**
     * Start the visualization loop
     */
    startVisualization(messageId, analyser, waveformContainer, audioElement = null) {
        // Cancel previous animation frame if exists
        if (this.animationFrames.has(messageId)) {
            cancelAnimationFrame(this.animationFrames.get(messageId));
        }

        const bars = waveformContainer.querySelectorAll('.waveform-bar');
        const barCount = bars.length;
        const dataArray = new Uint8Array(analyser.frequencyBinCount);

        const animate = () => {
            // Get frequency data
            analyser.getByteFrequencyData(dataArray);

            // Calculate progress if audio element provided
            let progress = 0;
            if (audioElement && audioElement.duration) {
                progress = (audioElement.currentTime / audioElement.duration) * 100;
            }

            // Update each bar based on frequency data AND progress
            bars.forEach((bar, index) => {
                // Map frequency bin to bar
                const frequencyIndex = Math.floor((index / barCount) * dataArray.length);
                const frequency = dataArray[frequencyIndex];

                // Normalize frequency to 0-1 range
                const normalizedFrequency = frequency / 255;

                // Calculate bar height (4px min to 20px max)
                const minHeight = 4;
                const maxHeight = 20;
                const barHeight = minHeight + (normalizedFrequency * (maxHeight - minHeight));

                // Apply smooth transition
                bar.style.height = barHeight + 'px';
                bar.style.transition = 'height 0.12s cubic-bezier(0.34, 1.56, 0.64, 1)';

                // Update progress indicator
                const barProgress = (index / barCount) * 100;
                if (barProgress <= progress) {
                    bar.classList.add('played');
                } else {
                    bar.classList.remove('played');
                }
            });

            const frameId = requestAnimationFrame(animate);
            this.animationFrames.set(messageId, frameId);
        };

        animate();
    }

    /**
     * Stop visualization
     */
    stopVisualization(messageId) {
        if (this.animationFrames.has(messageId)) {
            cancelAnimationFrame(this.animationFrames.get(messageId));
            this.animationFrames.delete(messageId);
        }
    }

    /**
     * Clean up audio context
     */
    cleanup(messageId) {
        this.stopVisualization(messageId);

        // Close audio context after a delay to prevent abrupt cutoff
        const audioContext = this.audioContexts.get(messageId);
        if (audioContext && audioContext.state === 'running') {
            setTimeout(() => {
                // Keep context alive for potential reuse
                // Only close on complete cleanup
            }, 100);
        }
    }

    /**
     * Reset visualization to idle state
     */
    resetVisualization(waveformContainer) {
        if (!waveformContainer) return;

        const bars = waveformContainer.querySelectorAll('.waveform-bar');
        bars.forEach(bar => {
            bar.style.height = '6px';
            bar.style.transition = 'height 0.3s ease-out';
        });
    }
}

// Create global instance
window.audioVisualization = new AudioVisualization();

// Export for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AudioVisualization;
}
