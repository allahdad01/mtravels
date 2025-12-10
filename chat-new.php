<?php
session_start();
$currentUserId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
if (!$currentUserId) {
    header('Location: login.php');
    exit;
}

// Generate or retrieve CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chat - Messaging</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #4099ff;
            --success-color: #10b981;
            --sidebar-width: 320px;
        }
        
        body {
            height: 100vh;
            overflow: hidden;
            font-size: 0.95rem;
        }
        
        .chat-wrapper {
            display: flex;
            height: 100vh;
            gap: 0;
        }
        
        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            border-right: 1px solid #dee2e6;
            background: #fff;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .sidebar-header {
            background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
            color: white;
            padding: 1rem;
            flex-shrink: 0;
        }
        
        .sidebar-header h5 {
            margin: 0;
            font-weight: 600;
        }
        
        .sidebar-search {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            flex-shrink: 0;
        }
        
        .contacts-list {
            flex: 1;
            overflow-y: auto;
            padding: 0.5rem 0;
        }
        
        .contact-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-left: 3px solid transparent;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .contact-item:hover {
            background: #f8f9fa;
        }
        
        .contact-item.active {
            background: #e3f2fd;
            border-left-color: var(--primary-color);
        }
        
        .contact-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
        }
        
        .contact-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .online-indicator {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 12px;
            height: 12px;
            background: var(--success-color);
            border: 2px solid white;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .contact-info {
            flex: 1;
            min-width: 0;
        }
        
        .contact-name {
            font-weight: 600;
            color: #1f2937;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 0.9rem;
        }
        
        .contact-agency {
            font-size: 0.75rem;
            color: #9ca3af;
            margin: 2px 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .contact-message {
            font-size: 0.8rem;
            color: #6b7280;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .contact-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.25rem;
            flex-shrink: 0;
        }
        
        .contact-time {
            font-size: 0.75rem;
            color: #9ca3af;
        }
        
        .badge-unread {
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        /* Chat Area */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #fff;
            overflow: hidden;
        }
        
        .chat-header {
            background: #fff;
            border-bottom: 1px solid #dee2e6;
            padding: 1rem;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .chat-header-info {
            flex: 1;
            min-width: 0;
        }
        
        .chat-header-name {
            font-weight: 600;
            color: #1f2937;
            margin: 0;
            font-size: 0.95rem;
        }
        
        .chat-header-status {
            font-size: 0.75rem;
            color: #9ca3af;
            margin: 4px 0 0 0;
        }
        
        .chat-header-status.online {
             color: var(--success-color);
         }
         
         .chat-header-status.typing {
              color: #4099ff;
              font-weight: 500;
          }
          
          .hidden {
              display: none !important;
          }
         
          .welcome-screen {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: #9ca3af;
            text-align: center;
        }
        
        .welcome-screen i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
        
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .message {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            animation: slideIn 0.3s ease;
        }
        
        .message.outgoing {
            justify-content: flex-end;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .message-bubble {
            max-width: 70%;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            background: #f3f4f6;
            color: #1f2937;
            word-wrap: break-word;
        }
        
        .message.outgoing .message-bubble {
            background: var(--primary-color);
            color: white;
        }
        
        .message-time {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 4px;
        }
        
        .message-status {
             font-size: 0.75rem;
             opacity: 0.7;
             margin-left: 6px;
             display: inline-block;
             font-weight: 500;
         }
         
         .message-status.status-sending {
             color: #9ca3af;
         }
         
         .message-status.status-sent,
         .message-status.status-delivered {
             color: #6b7280;
         }
         
         .message-status.status-seen,
          .message-status.status-read {
              color: #4099ff;
          }
          
          .message-actions {
              display: none;
              margin-top: 8px;
              position: relative;
          }
          
          .message:hover .message-actions {
              display: block;
          }
          
          .message-menu-btn {
              background: none;
              border: none;
              color: #9ca3af;
              cursor: pointer;
              padding: 4px 8px;
              border-radius: 4px;
              transition: all 0.2s;
              font-size: 0.9rem;
          }
          
          .message-menu-btn:hover {
              background: rgba(0,0,0,0.05);
              color: #6b7280;
          }
          
          .message-dropdown {
              display: none;
              position: absolute;
              right: 0;
              background: white;
              border: 1px solid #e5e7eb;
              border-radius: 8px;
              box-shadow: 0 10px 25px rgba(0,0,0,0.1);
              z-index: 100;
              min-width: 160px;
              overflow: hidden;
          }
          
          .message-dropdown.open {
              display: block;
          }
          
          .message-dropdown-item {
              padding: 12px 16px;
              cursor: pointer;
              display: flex;
              align-items: center;
              gap: 10px;
              color: #374151;
              transition: all 0.2s;
              font-size: 0.9rem;
              border: none;
              background: none;
              width: 100%;
              text-align: left;
          }
          
          .message-dropdown-item:hover {
              background: #f3f4f6;
              color: #111827;
          }
          
          .message-dropdown-item.danger {
              color: #ef4444;
          }
          
          .message-dropdown-item.danger:hover {
              background: #fee2e2;
          }
          
          .message-dropdown-divider {
              height: 1px;
              background: #e5e7eb;
              margin: 4px 0;
          }
          
          .reaction-picker {
              display: flex;
              gap: 6px;
              margin-top: 8px;
              padding: 8px;
              background: #f9fafb;
              border-radius: 6px;
              flex-wrap: wrap;
          }
          
          .reaction-btn {
              background: white;
              border: 1px solid #e5e7eb;
              border-radius: 4px;
              padding: 6px 10px;
              cursor: pointer;
              font-size: 1.2rem;
              transition: all 0.2s;
          }
          
          .reaction-btn:hover {
              background: #f3f4f6;
              transform: scale(1.1);
          }
          
          .message-reactions {
              display: flex;
              gap: 6px;
              margin-top: 8px;
              flex-wrap: wrap;
          }
          
          .reaction-item {
              display: flex;
              align-items: center;
              gap: 4px;
              background: #f3f4f6;
              border: 1px solid #e5e7eb;
              border-radius: 16px;
              padding: 4px 8px;
              font-size: 0.9rem;
              cursor: pointer;
              transition: all 0.2s;
          }
          
          .reaction-item:hover {
              background: #e5e7eb;
          }
          
          .reaction-emoji {
              font-size: 1.1rem;
          }
          
          .reaction-count {
              font-size: 0.8rem;
              color: #6b7280;
              font-weight: 500;
          }
          
          .reply-preview {
              background: #f3f4f6;
              border-left: 3px solid #4099ff;
              border-radius: 4px;
              padding: 12px;
              margin-bottom: 10px;
          }
          
          .reply-preview-content {
              display: flex;
              justify-content: space-between;
              align-items: flex-start;
          }
          
          .reply-preview-header {
              display: flex;
              align-items: center;
              gap: 8px;
              margin-bottom: 6px;
          }
          
          .reply-preview-sender {
              font-weight: 600;
              font-size: 0.9rem;
              color: #374151;
          }
          
          .reply-preview-text {
              font-size: 0.85rem;
              color: #6b7280;
              line-height: 1.4;
              word-break: break-word;
          }
          
          .reply-preview-close {
              background: none;
              border: none;
              color: #9ca3af;
              cursor: pointer;
              padding: 4px;
              font-size: 1rem;
              transition: color 0.2s;
          }
          
          .reply-preview-close:hover {
              color: #6b7280;
          }
          
          .reply-context {
              background: rgba(0,0,0,0.05);
              border-left: 3px solid #4099ff;
              padding: 10px;
              margin-bottom: 10px;
              border-radius: 4px;
          }
          
          .reply-context-sender {
              font-size: 0.8rem;
              font-weight: 600;
              color: #4099ff;
              margin-bottom: 6px;
              display: flex;
              align-items: center;
              gap: 6px;
          }
          
          .reply-context-sender i {
              font-size: 0.75rem;
          }
          
          .reply-context-text {
              font-size: 0.9rem;
              color: #374151;
              line-height: 1.4;
              word-break: break-word;
          }
          
          .typing-indicator {
            display: flex;
            gap: 4px;
            padding: 0.5rem;
        }
        
        .typing-dot {
            width: 8px;
            height: 8px;
            background: #ccc;
            border-radius: 50%;
            animation: typing 1.4s infinite;
        }
        
        .typing-dot:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .typing-dot:nth-child(3) {
            animation-delay: 0.4s;
        }
        
        @keyframes typing {
            0%, 60%, 100% { opacity: 0.5; transform: translateY(0); }
            30% { opacity: 1; transform: translateY(-10px); }
        }
        
        .emoji-picker-btn {
            position: relative;
        }
        
        .emoji-picker {
            position: absolute;
            bottom: 100%;
            right: 0;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 8px;
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 4px;
            width: 200px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .emoji-picker.hidden {
            display: none;
        }
        
        .emoji-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: background 0.2s;
        }
        
        .emoji-btn:hover {
            background: #f0f0f0;
        }
        
        .file-upload-label {
            cursor: pointer;
            padding: 8px;
            border-radius: 4px;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .file-upload-label:hover {
            background: #f0f0f0;
        }
        
        #voiceBtn.recording {
            background: #ff4757 !important;
            color: white;
            animation: pulse 1s infinite;
        }
        
        .voice-timer {
            font-size: 0.75rem;
            color: #ff4757;
            min-width: 40px;
            text-align: center;
            font-weight: 600;
        }
        
        .voice-message {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            max-width: 300px;
        }
        
        .voice-message.received {
            background: #e3f2fd;
            color: #333;
        }
        
        .voice-content {
            padding: 0.5rem;
        }
        
        .voice-player {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .voice-play-btn {
            background: rgba(255, 255, 255, 0.3);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            flex-shrink: 0;
        }
        
        .voice-message.received .voice-play-btn {
            background: rgba(64, 153, 255, 0.2);
            color: #4099ff;
        }
        
        .voice-play-btn:hover {
            background: rgba(255, 255, 255, 0.5);
            transform: scale(1.1);
        }
        
        .voice-play-btn.playing {
            background: rgba(255, 255, 255, 0.5);
        }
        
        .voice-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .voice-duration {
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .voice-waveform {
            height: 20px;
            display: flex;
            align-items: center;
            gap: 2px;
        }
        
        .voice-waveform span {
            width: 2px;
            height: 8px;
            background: currentColor;
            border-radius: 1px;
            opacity: 0.7;
        }
        
        .conversation-info {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .conversation-info h6 {
            margin: 0 0 0.5rem 0;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .conversation-info p {
            margin: 0.25rem 0;
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        .input-area {
            background: #fff;
            border-top: 1px solid #dee2e6;
            padding: 1rem;
            flex-shrink: 0;
        }
        
        /* Back button on mobile */
        .back-button {
            display: none;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .chat-wrapper {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: 100%;
                position: absolute;
                left: 0;
                top: 0;
                z-index: 100;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .chat-area {
                width: 100%;
                height: 100%;
                position: absolute;
                left: 0;
                top: 0;
                transform: translateX(100%);
                transition: transform 0.3s ease;
                z-index: 50;
            }
            
            .chat-area.show {
                transform: translateX(0);
            }
            
            .back-button {
                display: block;
                background: none;
                border: none;
                color: var(--primary-color);
                font-size: 1.5rem;
                cursor: pointer;
            }
            
            .message-bubble {
                max-width: 90%;
            }
            
            .sidebar-width {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="chat-wrapper">
        <!-- Sidebar with Contacts -->
        <div class="sidebar show" id="sidebar">
            <div class="sidebar-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5>Messages</h5>
                    <button class="btn btn-light btn-sm rounded-circle">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </div>
            
            <div class="sidebar-search">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-0">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" class="form-control border-0 bg-light" placeholder="Search..." id="contactSearch">
                </div>
            </div>
            
            <div class="contacts-list" id="contactList">
                <!-- Contacts will be rendered here -->
            </div>
        </div>
        
        <!-- Chat Area -->
        <div class="chat-area show" id="chatArea">
            <!-- Chat Header -->
            <div class="chat-header">
                <button class="back-button" id="backButton">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div class="d-flex align-items-center gap-3" style="flex: 1;">
                    <div class="contact-avatar" id="chatAvatar" style="width: 40px; height: 40px;">JD</div>
                    <div class="chat-header-info">
                        <h6 class="chat-header-name" id="contactName">Select a contact</h6>
                        <p class="chat-header-status" id="contactStatus">offline</p>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-light btn-sm rounded-circle">
                        <i class="fas fa-search"></i>
                    </button>
                    <button class="btn btn-light btn-sm rounded-circle">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>
            </div>
            
            <!-- Welcome or Chat Content -->
            <div class="welcome-screen" id="welcomeScreen">
                <i class="fas fa-comments"></i>
                <h5>Welcome to Chat</h5>
                <p>Select a conversation to start messaging</p>
            </div>
            
            <div class="messages-container hidden" id="messagesContainer">
                <!-- Messages will be rendered here -->
            </div>
            
            <!-- Input Area -->
            <div class="input-area">
                <!-- File upload (hidden) -->
                <input type="file" id="fileInput" class="d-none" multiple>
                
                <!-- Message input and actions -->
                <div class="d-flex gap-2 align-items-flex-end">
                    <!-- Action buttons -->
                    <div class="btn-group" role="group">
                         <!-- File upload -->
                         <label class="file-upload-label" title="Attach file">
                             <i class="fas fa-paperclip"></i>
                             <input type="file" id="fileUploadBtn" class="d-none" multiple onchange="window.chatApp.ui.handleFileUpload(event)">
                         </label>
                         
                         <!-- Voice message -->
                         <button class="btn btn-light btn-sm" id="voiceBtn" title="Voice message">
                             <i class="fas fa-microphone"></i>
                         </button>
                         
                         <!-- Emoji picker -->
                         <div class="emoji-picker-btn">
                             <button class="btn btn-light btn-sm" id="emojiBtn" title="Emoji">
                                 <i class="fas fa-smile"></i>
                             </button>
                            <div class="emoji-picker hidden" id="emojiPicker">
                                <button type="button" class="emoji-btn">😀</button>
                                <button type="button" class="emoji-btn">😂</button>
                                <button type="button" class="emoji-btn">❤️</button>
                                <button type="button" class="emoji-btn">👍</button>
                                <button type="button" class="emoji-btn">🎉</button>
                                <button type="button" class="emoji-btn">🔥</button>
                                <button type="button" class="emoji-btn">😢</button>
                                <button type="button" class="emoji-btn">😡</button>
                                <button type="button" class="emoji-btn">🤔</button>
                                <button type="button" class="emoji-btn">👋</button>
                                <button type="button" class="emoji-btn">✨</button>
                                <button type="button" class="emoji-btn">🎯</button>
                                <button type="button" class="emoji-btn">📸</button>
                                <button type="button" class="emoji-btn">🎵</button>
                                <button type="button" class="emoji-btn">🚀</button>
                                <button type="button" class="emoji-btn">⚡</button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Message input -->
                    <textarea class="form-control" id="messageInput" placeholder="Type a message..." rows="1" style="resize: none; max-height: 120px;"></textarea>
                    
                    <!-- Send button -->
                    <button class="btn btn-primary rounded-circle" id="sendBtn" style="width: 44px; height: 44px; display: flex; align-items: center; justify-content: center;" title="Send message">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chat Modules -->
    <script src="assets/js/chat/ChatManager.js"></script>
    <script src="assets/js/chat/ChatUIClean.js"></script>
    <script src="assets/js/chat/ChatAPI.js"></script>
    <script src="assets/js/chat/VoiceRecorder.js"></script>
    <script src="assets/js/chat/VoiceMessageUI.js"></script>
    <script src="assets/js/chat/VoiceMessageEnhanced.js"></script>
    <script src="assets/js/chat/VoiceMessageAdvanced.js"></script>
    <script src="assets/js/chat/init-clean.js"></script>
    
    <script>
        window.ALQ_USER_ID = <?php echo json_encode($currentUserId); ?>;
        window.csrfToken = <?php echo json_encode($csrfToken); ?>;
    </script>
</body>
</html>
