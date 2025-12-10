/**
 * VoiceMessageEnhanced - Enhanced UI for voice messages with advanced controls
 * Features: playback speed, progress bar, timer, detailed actions menu
 */
class VoiceMessageEnhanced {
    /**
     * Create enhanced voice player HTML
     */
    static createVoicePlayer(messageId, url, duration, isOwn = false) {
        const durationStr = VoiceRecorder.formatTime(duration || 0);
        
        return `
            <div class="voice-player-enhanced" data-message-id="${messageId}">
                <!-- Main Player -->
                <div class="voice-player-main">
                    <!-- Play Button -->
                    <button class="voice-play-btn-enhanced" data-message-id="${messageId}" data-url="${url}" title="Play/Pause">
                        <i class="fas fa-play"></i>
                    </button>
                    
                    <!-- Duration and Progress -->
                    <div class="voice-progress-container">
                        <span class="voice-current-time">0:00</span>
                        <div class="voice-progress-bar">
                            <div class="voice-progress-fill"></div>
                        </div>
                        <span class="voice-total-time">${durationStr}</span>
                    </div>
                    
                    <!-- Controls Toolbar -->
                    <div class="voice-controls-toolbar">
                        <button class="voice-action-btn" data-action="speed" data-message-id="${messageId}" title="Playback speed (1x)">
                            1x
                        </button>
                        <button class="voice-action-btn" data-action="download" data-message-id="${messageId}" title="Download">
                            <i class="fas fa-download"></i>
                        </button>
                        <button class="voice-action-btn" data-action="favorite" data-message-id="${messageId}" title="Add to favorites">
                            <i class="fas fa-star"></i>
                        </button>
                        <button class="voice-action-btn" data-action="transcribe" data-message-id="${messageId}" title="Transcribe">
                            <i class="fas fa-closed-captioning"></i>
                        </button>
                        <button class="voice-action-btn" data-action="info" data-message-id="${messageId}" title="Message info">
                            <i class="fas fa-info-circle"></i>
                        </button>
                        ${!isOwn ? '' : `
                            <button class="voice-action-btn" data-action="delete" data-message-id="${messageId}" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        `}
                    </div>
                </div>
                
                <!-- Waveform Visualization -->
                <div class="voice-waveform-enhanced">
                    <canvas class="waveform-canvas" width="100" height="30"></canvas>
                </div>
            </div>
        `;
    }

    /**
     * Add CSS styles for enhanced voice player
     */
    static injectStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .voice-player-enhanced {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
                padding: 0.75rem;
                background: rgba(0,0,0,0.02);
                border-radius: 8px;
            }

            .voice-player-main {
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }

            .voice-play-btn-enhanced {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: #4099ff;
                color: white;
                border: none;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all 0.2s;
                flex-shrink: 0;
                font-size: 1rem;
            }

            .voice-play-btn-enhanced:hover {
                background: #2d7acc;
                transform: scale(1.05);
            }

            .voice-play-btn-enhanced.playing {
                background: #ff6b6b;
            }

            .voice-progress-container {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                flex: 1;
                min-width: 0;
            }

            .voice-current-time, .voice-total-time {
                font-size: 0.75rem;
                color: #666;
                min-width: 35px;
            }

            .voice-progress-bar {
                flex: 1;
                height: 4px;
                background: #e0e0e0;
                border-radius: 2px;
                cursor: pointer;
                position: relative;
                overflow: hidden;
            }

            .voice-progress-fill {
                height: 100%;
                background: #4099ff;
                width: 0%;
                border-radius: 2px;
                transition: width 0.1s linear;
            }

            .voice-controls-toolbar {
                display: flex;
                gap: 0.25rem;
                flex-wrap: wrap;
            }

            .voice-action-btn {
                padding: 6px 10px;
                border: 1px solid #ddd;
                background: white;
                border-radius: 4px;
                cursor: pointer;
                font-size: 0.85rem;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.25rem;
                color: #333;
            }

            .voice-action-btn:hover {
                background: #f0f0f0;
                border-color: #4099ff;
                color: #4099ff;
            }

            .voice-action-btn:active {
                transform: scale(0.95);
            }

            .voice-action-btn.favorited {
                color: #ffc107;
                border-color: #ffc107;
            }

            .voice-waveform-enhanced {
                height: 40px;
                background: rgba(64, 153, 255, 0.05);
                border-radius: 4px;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
            }

            .waveform-canvas {
                max-width: 100%;
                height: 100%;
            }

            /* Voice Message Info Panel */
            .voice-info-panel {
                background: #f9f9f9;
                border: 1px solid #e0e0e0;
                border-radius: 6px;
                padding: 1rem;
                margin-top: 0.5rem;
                font-size: 0.9rem;
            }

            .info-header {
                font-weight: 600;
                margin-bottom: 0.5rem;
                padding-bottom: 0.5rem;
                border-bottom: 1px solid #ddd;
            }

            .info-row {
                display: flex;
                justify-content: space-between;
                padding: 0.5rem 0;
                font-size: 0.85rem;
            }

            .info-label {
                color: #666;
                font-weight: 500;
            }

            .info-value {
                color: #333;
                font-family: monospace;
            }

            /* Voice Transcript */
            .voice-transcript {
                background: #f0f7ff;
                border-left: 4px solid #4099ff;
                border-radius: 4px;
                padding: 1rem;
                margin-top: 0.5rem;
                font-size: 0.9rem;
            }

            .transcript-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 0.75rem;
                font-weight: 600;
            }

            .transcript-close {
                background: none;
                border: none;
                font-size: 1.5rem;
                cursor: pointer;
                color: #666;
            }

            .transcript-content {
                line-height: 1.6;
            }

            /* Forward Dialog */
            .voice-forward-dialog {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
            }

            .forward-modal {
                background: white;
                border-radius: 8px;
                padding: 1.5rem;
                max-width: 400px;
                max-height: 500px;
                overflow-y: auto;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }

            .forward-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 1rem;
            }

            .forward-header h6 {
                margin: 0;
                font-size: 1.1rem;
            }

            .forward-contacts {
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
            }

            .forward-contact {
                padding: 0.75rem 1rem;
                background: #f0f0f0;
                border: 1px solid #ddd;
                border-radius: 4px;
                cursor: pointer;
                text-align: left;
                transition: all 0.2s;
            }

            .forward-contact:hover {
                background: #4099ff;
                color: white;
                border-color: #4099ff;
            }

            /* Received Voice Message Styling */
            .message.incoming .voice-player-enhanced {
                background: rgba(64, 153, 255, 0.05);
            }

            .message.outgoing .voice-player-enhanced {
                background: rgba(64, 153, 255, 0.1);
            }

            @media (max-width: 768px) {
                .voice-controls-toolbar {
                    gap: 0.15rem;
                }

                .voice-action-btn {
                    padding: 4px 8px;
                    font-size: 0.75rem;
                }

                .voice-progress-container {
                    gap: 0.25rem;
                }

                .voice-current-time, .voice-total-time {
                    min-width: 30px;
                }
            }
        `;

        document.head.appendChild(style);
    }

    /**
     * Draw waveform visualization
     */
    static drawWaveform(canvas, audioBuffer = null) {
        const ctx = canvas.getContext('2d');
        const width = canvas.width;
        const height = canvas.height;

        // Clear canvas
        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, width, height);

        // Draw animated bars
        const barCount = 20;
        const barWidth = (width / barCount) - 2;
        const centerY = height / 2;

        for (let i = 0; i < barCount; i++) {
            const barHeight = Math.random() * height * 0.6 + height * 0.2;
            const x = i * (barWidth + 2);
            const y = centerY - barHeight / 2;

            ctx.fillStyle = '#4099ff';
            ctx.globalAlpha = 0.6 + Math.random() * 0.4;
            ctx.fillRect(x, y, barWidth, barHeight);
        }

        ctx.globalAlpha = 1;
    }
}

// Inject styles when module loads
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        VoiceMessageEnhanced.injectStyles();
    });
} else {
    VoiceMessageEnhanced.injectStyles();
}

// Export for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = VoiceMessageEnhanced;
}
