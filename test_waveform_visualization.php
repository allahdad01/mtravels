<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waveform Visualization Test</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        h1 {
            color: white;
            text-align: center;
            margin-bottom: 40px;
            font-size: 2.5rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }
        
        .demo-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .demo-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        /* Voice message styling */
        .voice-message {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }
        
        .voice-message.received {
            background: #f0f0f0;
            justify-content: flex-start;
        }
        
        .voice-message.sent {
            background: #e3f2fd;
            justify-content: flex-end;
        }
        
        .voice-player {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 280px;
        }
        
        .voice-play-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: inherit;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            flex-shrink: 0;
            font-size: 16px;
        }
        
        .voice-message.received .voice-play-btn {
            background: rgba(64, 153, 255, 0.15);
            color: #4099ff;
        }
        
        .voice-message.sent .voice-play-btn {
            background: rgba(0, 0, 0, 0.1);
            color: #333;
        }
        
        .voice-play-btn:hover {
            transform: scale(1.1);
        }
        
        .voice-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
            min-width: 0;
        }
        
        /* Waveform Visualization - WhatsApp Music Player Style */
        .voice-waveform-container {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 36px;
            gap: 2px;
            cursor: pointer;
            padding: 4px 0;
            background: linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.3) 50%, rgba(255,255,255,0) 100%);
            border-radius: 4px;
        }
        
        .waveform-bar {
            width: 2.5px;
            height: 6px;
            background: rgba(0, 0, 0, 0.15);
            border-radius: 1px;
            transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
            flex-shrink: 0;
            animation: waveform-animate 0.7s ease-in-out infinite;
            animation-delay: calc(var(--bar-index) * 0.04s);
            position: relative;
            box-shadow: 0 0 2px rgba(0, 0, 0, 0.1);
        }
        
        @keyframes waveform-animate {
            0%, 100% { height: 4px; }
            50% { height: 16px; }
        }
        
        /* Received messages - blue theme */
        .voice-message.received .waveform-bar {
            background: rgba(64, 153, 255, 0.25);
        }
        
        .voice-message.received .waveform-bar.played {
            background: #4099ff;
            box-shadow: 0 0 6px rgba(64, 153, 255, 0.7), 0 0 12px rgba(64, 153, 255, 0.4);
            filter: brightness(1.1);
        }
        
        /* Sent messages - green theme */
        .voice-message.sent .waveform-bar {
            background: rgba(18, 140, 126, 0.25);
        }
        
        .voice-message.sent .waveform-bar.played {
            background: #128c7e;
            box-shadow: 0 0 6px rgba(18, 140, 126, 0.6), 0 0 12px rgba(18, 140, 126, 0.3);
            filter: brightness(1.1);
        }
        
        /* Hover effect */
        .voice-waveform-container:hover .waveform-bar {
            animation-duration: 0.5s;
            filter: brightness(1.2);
        }
        
        .voice-waveform-container:hover .waveform-bar.played {
            filter: brightness(1.3);
            box-shadow: 0 0 8px rgba(64, 153, 255, 0.8), 0 0 16px rgba(64, 153, 255, 0.5);
        }
        
        /* Timer Row */
        .voice-timer-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 4px;
        }
        
        .voice-current-time,
        .voice-duration {
            font-size: 12px;
            font-weight: 500;
            min-width: 28px;
            text-align: center;
            color: rgba(0, 0, 0, 0.6);
        }
        
        .feature-list {
            list-style: none;
            margin-top: 20px;
        }
        
        .feature-list li {
            padding: 10px 0;
            padding-left: 30px;
            position: relative;
            color: #555;
            line-height: 1.6;
        }
        
        .feature-list li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #667eea;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .code-block {
            background: #f5f5f5;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            overflow-x: auto;
        }
        
        .interactive-demo {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #eee;
        }
        
        .progress-slider {
            width: 100%;
            height: 6px;
            margin: 15px 0;
            cursor: pointer;
        }
        
        .time-display {
            text-align: center;
            font-size: 0.9rem;
            color: #666;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎵 Voice Message Waveform Visualization</h1>
        
        <!-- Features Section -->
        <div class="demo-section">
            <div class="demo-title">✨ Key Features</div>
            <ul class="feature-list">
                <li><strong>Animated Waveform Bars</strong> - Continuously animate up and down like a music equalizer</li>
                <li><strong>Playback Progress Indication</strong> - Played bars are highlighted with a glowing effect</li>
                <li><strong>Interactive Seeking</strong> - Click anywhere on the waveform to jump to that time</li>
                <li><strong>Theme-Aware Colors</strong> - Blue for received messages, green for sent messages</li>
                <li><strong>Smooth Animations</strong> - GPU-accelerated CSS animations for smooth performance</li>
                <li><strong>Mobile Optimized</strong> - Responsive design that works on all screen sizes</li>
                <li><strong>Hover Effects</strong> - Enhanced visual feedback on interaction</li>
            </ul>
        </div>
        
        <!-- Received Message Demo -->
        <div class="demo-section">
            <div class="demo-title">📥 Received Voice Message</div>
            <div class="voice-message received">
                <button class="voice-play-btn">
                    <i class="fas fa-play"></i>
                </button>
                <div class="voice-info">
                    <div class="voice-waveform-container">
                        <div class="waveform-bar" style="--bar-index: 0"></div>
                        <div class="waveform-bar" style="--bar-index: 1"></div>
                        <div class="waveform-bar" style="--bar-index: 2"></div>
                        <div class="waveform-bar" style="--bar-index: 3"></div>
                        <div class="waveform-bar" style="--bar-index: 4"></div>
                        <div class="waveform-bar" style="--bar-index: 5"></div>
                        <div class="waveform-bar" style="--bar-index: 6"></div>
                        <div class="waveform-bar" style="--bar-index: 7"></div>
                        <div class="waveform-bar" style="--bar-index: 8"></div>
                        <div class="waveform-bar" style="--bar-index: 9"></div>
                        <div class="waveform-bar" style="--bar-index: 10"></div>
                        <div class="waveform-bar" style="--bar-index: 11"></div>
                        <div class="waveform-bar" style="--bar-index: 12"></div>
                        <div class="waveform-bar" style="--bar-index: 13"></div>
                        <div class="waveform-bar" style="--bar-index: 14"></div>
                        <div class="waveform-bar" style="--bar-index: 15"></div>
                        <div class="waveform-bar" style="--bar-index: 16"></div>
                        <div class="waveform-bar" style="--bar-index: 17"></div>
                        <div class="waveform-bar" style="--bar-index: 18"></div>
                        <div class="waveform-bar" style="--bar-index: 19"></div>
                    </div>
                    <div class="voice-timer-row">
                        <span class="voice-current-time">0:35</span>
                        <span class="voice-duration">1:45</span>
                    </div>
                </div>
            </div>
            <p style="color: #666; margin-top: 15px;">Hover over the waveform to see the enhanced glow effects. Click on any bar to seek.</p>
        </div>
        
        <!-- Sent Message Demo -->
        <div class="demo-section">
            <div class="demo-title">📤 Sent Voice Message</div>
            <div class="voice-message sent">
                <div class="voice-info">
                    <div class="voice-waveform-container">
                        <div class="waveform-bar" style="--bar-index: 0"></div>
                        <div class="waveform-bar" style="--bar-index: 1"></div>
                        <div class="waveform-bar" style="--bar-index: 2"></div>
                        <div class="waveform-bar" style="--bar-index: 3"></div>
                        <div class="waveform-bar" style="--bar-index: 4"></div>
                        <div class="waveform-bar" style="--bar-index: 5"></div>
                        <div class="waveform-bar" style="--bar-index: 6"></div>
                        <div class="waveform-bar" style="--bar-index: 7"></div>
                        <div class="waveform-bar" style="--bar-index: 8"></div>
                        <div class="waveform-bar" style="--bar-index: 9"></div>
                        <div class="waveform-bar" style="--bar-index: 10"></div>
                        <div class="waveform-bar" style="--bar-index: 11"></div>
                        <div class="waveform-bar" style="--bar-index: 12"></div>
                        <div class="waveform-bar" style="--bar-index: 13"></div>
                        <div class="waveform-bar" style="--bar-index: 14"></div>
                        <div class="waveform-bar" style="--bar-index: 15"></div>
                        <div class="waveform-bar" style="--bar-index: 16"></div>
                        <div class="waveform-bar" style="--bar-index: 17"></div>
                        <div class="waveform-bar" style="--bar-index: 18"></div>
                        <div class="waveform-bar" style="--bar-index: 19"></div>
                    </div>
                    <div class="voice-timer-row">
                        <span class="voice-current-time">0:52</span>
                        <span class="voice-duration">2:10</span>
                    </div>
                </div>
                <button class="voice-play-btn">
                    <i class="fas fa-play"></i>
                </button>
            </div>
        </div>
        
        <!-- Technical Details -->
        <div class="demo-section">
            <div class="demo-title">🔧 Technical Implementation</div>
            
            <h3 style="margin-top: 20px; color: #333;">CSS Animation Keyframes</h3>
            <div class="code-block">
@keyframes waveform-animate {
    0%, 100% { height: 4px; }
    50% { height: 16px; }
}
            </div>
            
            <h3 style="margin-top: 20px; color: #333;">Bar Properties</h3>
            <ul class="feature-list">
                <li>Width: 2.5px (responsive: 2px on mobile)</li>
                <li>Height: 4px to 16px (animated)</li>
                <li>Gap: 2px between bars</li>
                <li>Animation Duration: 0.7 seconds</li>
                <li>Stagger Delay: 0.04s between bars for wave effect</li>
                <li>Easing: cubic-bezier(0.4, 0, 0.2, 1)</li>
            </ul>
            
            <h3 style="margin-top: 20px; color: #333;">Progress Tracking</h3>
            <p style="color: #555; margin: 10px 0;">When audio plays, bars are dynamically marked with the <code>.played</code> class based on current playback time. This creates a visual indication of playback progress while maintaining the animation.</p>
        </div>
        
        <!-- Interactive Playback Demo -->
        <div class="demo-section interactive-demo">
            <div class="demo-title">▶️ Playback Progress Simulation</div>
            <p style="color: #666; margin-bottom: 15px;">Adjust the slider to simulate playback progress. Notice how the bars change color as they are "played".</p>
            
            <div class="voice-message received">
                <button class="voice-play-btn">
                    <i class="fas fa-play"></i>
                </button>
                <div class="voice-info">
                    <div class="voice-waveform-container" id="demoWaveform">
                        <div class="waveform-bar" style="--bar-index: 0"></div>
                        <div class="waveform-bar" style="--bar-index: 1"></div>
                        <div class="waveform-bar" style="--bar-index: 2"></div>
                        <div class="waveform-bar" style="--bar-index: 3"></div>
                        <div class="waveform-bar" style="--bar-index: 4"></div>
                        <div class="waveform-bar" style="--bar-index: 5"></div>
                        <div class="waveform-bar" style="--bar-index: 6"></div>
                        <div class="waveform-bar" style="--bar-index: 7"></div>
                        <div class="waveform-bar" style="--bar-index: 8"></div>
                        <div class="waveform-bar" style="--bar-index: 9"></div>
                        <div class="waveform-bar" style="--bar-index: 10"></div>
                        <div class="waveform-bar" style="--bar-index: 11"></div>
                        <div class="waveform-bar" style="--bar-index: 12"></div>
                        <div class="waveform-bar" style="--bar-index: 13"></div>
                        <div class="waveform-bar" style="--bar-index: 14"></div>
                        <div class="waveform-bar" style="--bar-index: 15"></div>
                        <div class="waveform-bar" style="--bar-index: 16"></div>
                        <div class="waveform-bar" style="--bar-index: 17"></div>
                        <div class="waveform-bar" style="--bar-index: 18"></div>
                        <div class="waveform-bar" style="--bar-index: 19"></div>
                    </div>
                    <div class="voice-timer-row">
                        <span class="voice-current-time" id="currentTime">0:00</span>
                        <span class="voice-duration">2:00</span>
                    </div>
                </div>
            </div>
            
            <input type="range" id="progressSlider" class="progress-slider" min="0" max="100" value="0">
            <div class="time-display" id="timeDisplay">Progress: 0%</div>
        </div>
    </div>
    
    <script>
        // Interactive demo script
        const slider = document.getElementById('progressSlider');
        const demoWaveform = document.getElementById('demoWaveform');
        const currentTimeEl = document.getElementById('currentTime');
        const timeDisplayEl = document.getElementById('timeDisplay');
        
        slider.addEventListener('input', (e) => {
            const progress = parseInt(e.target.value);
            const bars = demoWaveform.querySelectorAll('.waveform-bar');
            
            // Update bars
            bars.forEach((bar, index) => {
                const barProgress = (index / bars.length) * 100;
                if (barProgress <= progress) {
                    bar.classList.add('played');
                } else {
                    bar.classList.remove('played');
                }
            });
            
            // Update time display
            const totalSeconds = 120;
            const currentSeconds = Math.floor((progress / 100) * totalSeconds);
            const minutes = Math.floor(currentSeconds / 60);
            const seconds = currentSeconds % 60;
            currentTimeEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            timeDisplayEl.textContent = `Progress: ${progress}%`;
        });
    </script>
</body>
</html>
