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

// Database connection
require_once('includes/db.php');
$tenant_id = $_SESSION['tenant_id'];
// Fetch settings data
try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->execute([$tenant_id]);
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Settings Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
        <!-- Favicon icon -->
        <link rel="icon" href="uploads/logo/<?= htmlspecialchars($settings['logo']) ?>" type="image/x-icon">
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
        
        .tenant-group {
            margin: 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .tenant-header {
            padding: 0.75rem 1rem;
            background: #f3f4f6;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: #374151;
            transition: background 0.2s;
            user-select: none;
        }
        
        .tenant-header:hover {
            background: #e5e7eb;
        }
        
        .tenant-arrow {
            display: inline-block;
            min-width: 1rem;
            text-align: center;
            font-size: 0.8rem;
            transition: transform 0.2s;
        }
        
        .tenant-name {
            flex: 1;
            font-size: 0.9rem;
        }
        
        .tenant-count {
            font-size: 0.8rem;
            color: #9ca3af;
            font-weight: 500;
        }
        
        .tenant-contacts {
            transition: display 0.2s;
        }
        
        .contact-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-left: 3px solid transparent;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-left: 0;
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
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-bottom: 1px solid rgba(222, 226, 230, 0.6);
            padding: 1rem 1.5rem;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .chat-header > div:first-child {
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
             
             .search-messages-bar {
                 padding: 0.75rem 1.5rem;
                 background: #f8f9fa;
                 border-top: 1px solid rgba(222, 226, 230, 0.6);
             }
             
             #searchMessagesInput {
                 border-radius: 20px;
                 border: 1px solid rgba(222, 226, 230, 0.8);
                 padding: 8px 16px;
             }
             
             #searchMessagesInput:focus {
                 outline: none;
                 border-color: #4099ff;
                 box-shadow: 0 0 0 3px rgba(64, 153, 255, 0.15);
             }
             
             #clearSearchBtn {
                 border-radius: 20px;
                 border: 1px solid rgba(222, 226, 230, 0.8);
             }
             
             .chat-menu-dropdown {
                 position: absolute;
                 top: 100%;
                 right: 0;
                 background: white;
                 border: 1px solid #e5e7eb;
                 border-radius: 8px;
                 box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
                 z-index: 1001;
                 min-width: 160px;
                 overflow: hidden;
                 margin-top: 4px;
                 margin-right: 0;
             }
             
             .chat-menu-item {
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
             
             .chat-menu-item:hover {
                 background: #f3f4f6;
                 color: #111827;
             }
             
             .chat-menu-item.danger {
                 color: #ef4444;
             }
             
             .chat-menu-item.danger:hover {
                 background: #fee2e2;
             }
             
             .chat-menu-divider {
                 height: 1px;
                 background: #e5e7eb;
                 margin: 4px 0;
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
             background-image: url('uploads/chat_background/original.png');
             background-attachment: fixed;

             background-position: center;
         }
        
        .message {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
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
        
        .message {
            position: relative;
        }
        
        .message-bubble {
            max-width: 70%;
            padding: 0.75rem 1rem;
            border-radius: 18px;
            background: #f3f4f6;
            color: #1f2937;
            word-wrap: break-word;
            position: relative;
        }
        
        /* Incoming message tail - bottom left */
        .message.incoming .message-bubble::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 0;
            height: 0;
            border-left: 8px solid #f3f4f6;
            border-top: 8px solid transparent;
        }
        
        .message.outgoing .message-bubble {
            background: var(--primary-color);
            color: white;
            border-radius: 18px;
        }
        
        /* Outgoing message tail - bottom right */
        .message.outgoing .message-bubble::after {
            content: '';
            position: absolute;
            bottom: -8px;
            right: 0;
            width: 0;
            height: 0;
            border-right: 8px solid var(--primary-color);
            border-top: 8px solid transparent;
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
              position: absolute;
              top: 2px;
              right: 0;
              z-index: 10;
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
               top: 100%;
               background: white;
               border: 1px solid #e5e7eb;
               border-radius: 8px;
               box-shadow: 0 10px 25px rgba(0,0,0,0.1);
               z-index: 1001;
               min-width: 160px;
               overflow: hidden;
               margin-top: 4px;
           }
           
           /* For incoming messages - dropdown opens to the right */
           .message.incoming .message-dropdown {
               left: 0;
           }
           
           /* For outgoing messages - dropdown opens to the left */
           .message.outgoing .message-dropdown {
               right: 0;
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
        
        /* WhatsApp-like Emoji Picker */
        .emoji-picker-btn {
            position: relative;
        }
        
        .emoji-picker-btn button {
            background: #fff;
            border: 1px solid #ddd;
            color: #4099ff;
            width: 40px;
            height: 40px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 18px;
        }
        
        .emoji-picker-btn button:hover {
            background: #f0f0f0;
            border-color: #4099ff;
            transform: scale(1.05);
        }
        
        .emoji-picker-btn button:active {
            transform: scale(0.95);
        }
        
        .emoji-picker {
            position: absolute;
            bottom: 100%;
            right: -260px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 12px;
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 8px;
            width: 300px;
            max-height: 250px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            margin-bottom: 10px;
            animation: popUp 0.3s ease-out;
        }
        
        @keyframes popUp {
            from {
                opacity: 0;
                transform: translateY(10px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .emoji-picker.hidden {
            display: none !important;
        }
        
        .emoji-btn {
            background: none;
            border: none;
            font-size: 1.75rem;
            cursor: pointer;
            padding: 6px;
            border-radius: 8px;
            transition: all 0.2s;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .emoji-btn:hover {
            background: #f0f0f0;
            transform: scale(1.2);
        }
        
        .emoji-btn:active {
            transform: scale(0.95);
        }
        
        /* WhatsApp-like File Upload */
        .file-upload-label {
            cursor: pointer;
            background: linear-gradient(135deg, rgba(64, 153, 255, 0.1) 0%, rgba(64, 153, 255, 0.05) 100%);
            border: 1px solid rgba(64, 153, 255, 0.3);
            color: #4099ff;
            width: 44px;
            height: 44px;
            padding: 0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            font-size: 18px;
            box-shadow: 0 2px 4px rgba(64, 153, 255, 0.1), 
                        inset 0 1px 2px rgba(255, 255, 255, 0.5);
        }
        
        .file-upload-label:hover {
            background: linear-gradient(135deg, rgba(64, 153, 255, 0.15) 0%, rgba(64, 153, 255, 0.1) 100%);
            border-color: #4099ff;
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(64, 153, 255, 0.2), 
                        inset 0 1px 2px rgba(255, 255, 255, 0.5);
        }
        
        .file-upload-label:active {
            transform: scale(0.95);
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
        
        /* WhatsApp-like Voice Messages */
        .message-bubble.voice-message {
            max-width: 340px;
            padding: 12px 16px;
            border-radius: 16px;
            background: white;
            color: #000;
            display: flex;
            flex-direction: column;
            gap: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
        }
        
        .message.outgoing .message-bubble.voice-message {
            background: white;
            color: #000;
            border-color: #e5e7eb;
        }
        
        .voice-message.sent {
            background: white;
            color: #000;
        }
        
        .voice-message.received {
            background: white;
            color: #000;
        }
        
        .voice-content {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 0;
        }
        
        /* WhatsApp Style Voice Message Box */
        .voice-message-box {
            width: 350px;
            background: #fff;
            border-radius: 12px;
            padding: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }
        
        .message.outgoing .voice-message-box {
            background: rgba(64, 153, 255, 0.9);
        }
        
        .message.incoming .voice-message-box {
            background: #f0f0f0;
        }
        
        .voice-player {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            width: 100%;
            padding: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
        }
        
        .message.outgoing .voice-player {
            background: rgba(255, 255, 255, 0.15);
        }
        
        .message.incoming .voice-player {
            background: rgba(0, 0, 0, 0.05);
        }
        
        .voice-play-btn {
            background: none;
            border: none;
            color: #9ca3af;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            flex-shrink: 0;
            font-size: 18px;
            position: relative;
            padding: 0;
        }
        
        .voice-play-btn:hover:not(:disabled) {
            color: #555;
            transform: scale(1.1);
        }
        
        .voice-play-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .message.outgoing .voice-play-btn {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .message.outgoing .voice-play-btn:hover {
            color: white;
        }
        
        .voice-play-btn.playing {
            color: #4fc3f7;
            animation: pulse-btn 0.6s infinite;
        }
        
        @keyframes pulse-btn {
            0% { transform: scale(1); box-shadow: 0 4px 12px rgba(64,153,255,0.4); }
            50% { transform: scale(1.08); box-shadow: 0 6px 16px rgba(64,153,255,0.6); }
            100% { transform: scale(1); box-shadow: 0 4px 12px rgba(64,153,255,0.4); }
        }
        
        .voice-info {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
            min-width: 0;
        }
        
        /* Waveform Visualization */
         .waveform-wrapper {
             flex: 1;
             height: 40px;
             min-width: 150px;
         }
         
         /* WaveSurfer Container */
         #waveform, [id^="waveform-"] {
             flex: 1;
             height: 40px;
         }
         
         .voice-waveform-container {
             display: flex;
             align-items: center;
             justify-content: flex-start;
             height: 40px;
             gap: 1.5px;
             cursor: pointer;
             flex: 1;
             padding: 0 8px;
             background: transparent;
         }
         
         .voice-waveform-container:hover {
             opacity: 1;
         }
         
         .waveform-bar {
             width: 3px;
             height: 12px;
             background: #9ca3af;
             border-radius: 2px;
             transition: all 0.15s ease;
             flex-shrink: 0;
             position: relative;
         }
         
         .voice-time {
             font-size: 12px;
             color: #555;
             font-weight: 500;
             min-width: 28px;
         }
         
         .message.outgoing .voice-time {
             color: rgba(255, 255, 255, 0.9);
         }
        
        /* Received messages - light gray theme */
        .voice-message.received .voice-waveform-container {
            background: transparent;
        }
        
        .voice-message.received .waveform-bar {
            background: #9ca3af;
        }
        
        .voice-message.received .waveform-bar.played {
            background: #4fc3f7;
            height: 20px;
        }
        
        /* Sent messages - light theme */
        .voice-message.sent .voice-waveform-container {
            background: transparent;
        }
        
        .voice-message.sent .waveform-bar {
            background: #9ca3af;
        }
        
        .voice-message.sent .waveform-bar.played {
            background: #4fc3f7;
            height: 20px;
        }
        
        /* Generic voice message */
        .voice-message .waveform-bar {
            background: #9ca3af;
        }
        
        .voice-message .waveform-bar.played {
            background: #4fc3f7;
            height: 20px;
        }
        
        /* Hover effect */
        .voice-waveform-container:hover .waveform-bar {
            opacity: 0.8;
        }
        
        .voice-message.received .voice-waveform-container:hover .waveform-bar.played {
            box-shadow: 0 0 6px rgba(64,153,255,0.7);
        }
        
        .voice-message.sent .voice-waveform-container:hover .waveform-bar.played {
           box-shadow: 0 0 6px rgba(64,153,255,0.7);
        }
        
        /* Voice message footer with time and avatar */
        .voice-footer {
           display: flex;
           align-items: center;
           justify-content: space-between;
           gap: 12px;
           padding: 0;
        }
        
        .voice-times {
           display: flex;
           gap: 12px;
           font-size: 0.75rem;
           color: #9ca3af;
           font-weight: 500;
        }
        
        .voice-current-time {
           color: #9ca3af;
        }
        
        .voice-timestamp {
           color: #9ca3af;
        }
        
        .voice-message.received .voice-times {
           color: #9ca3af;
        }
        
        .voice-message.sent .voice-times {
           color: #9ca3af;
        }
        
        /* Avatar in voice message */
        .voice-avatar {
           width: 32px;
           height: 32px;
           border-radius: 50%;
           display: flex;
           align-items: center;
           justify-content: center;
           font-size: 0.8rem;
           font-weight: 600;
           flex-shrink: 0;
           background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
           color: white;
        }
        
        .voice-avatar img {
           width: 100%;
           height: 100%;
           border-radius: 50%;
           object-fit: cover;
        }
        
        .voice-message.received .voice-avatar {
           order: 1;
        }
        
        /* Active playback - bars respond to frequency */
        .voice-player.playing .waveform-bar {
            opacity: 1;
        }
        
        /* Smooth height transitions during playback */
        .voice-player.playing .waveform-bar {
            transition: all 0.12s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        /* Timer Row */
        .voice-timer-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            padding: 0 4px;
            font-size: 12px;
        }
        
        .voice-current-time {
            font-weight: 500;
            min-width: 28px;
            text-align: left;
            color: #555;
        }
        
        .voice-duration {
            font-weight: 500;
            min-width: 28px;
            text-align: right;
            color: #888;
        }
        
        .message.outgoing .voice-current-time {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .message.outgoing .voice-duration {
            color: rgba(255, 255, 255, 0.9);
        }
        
        .message.incoming .voice-current-time {
            color: #555;
        }
        
        .message.incoming .voice-duration {
            color: #888;
        }
        
        /* Legacy waveform for animation reference */
        .voice-waveform {
            height: 24px;
            display: flex;
            align-items: center;
            gap: 2px;
            flex: 1;
        }
        
        .voice-waveform span {
            width: 2px;
            height: 4px;
            background: currentColor;
            border-radius: 1px;
            opacity: 0.6;
            animation: wave-animate 0.6s ease-in-out infinite;
        }
        
        .voice-waveform span:nth-child(1) { animation-delay: 0s; }
        .voice-waveform span:nth-child(2) { animation-delay: 0.1s; }
        .voice-waveform span:nth-child(3) { animation-delay: 0.2s; }
        .voice-waveform span:nth-child(4) { animation-delay: 0.3s; }
        .voice-waveform span:nth-child(5) { animation-delay: 0.4s; }
        
        @keyframes wave-animate {
            0%, 100% { height: 4px; }
            50% { height: 12px; }
        }
        
        /* Voice Recording UI - WhatsApp Style */
        #voiceTimer {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #f0f0f0;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin: 0 8px;
            animation: slideUp 0.3s ease-out;
        }
        
        #voiceTimer.hidden {
            display: none;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        #voiceBtn.recording {
            background: #f44336;
            animation: pulse-record 0.8s infinite;
        }
        
        @keyframes pulse-record {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        /* Recording indicator dot */
        #voiceBtn.recording::before {
            content: '';
            position: absolute;
            top: 4px;
            right: 4px;
            width: 8px;
            height: 8px;
            background: #fff;
            border-radius: 50%;
            animation: blink 1s infinite;
        }
        
        @keyframes blink {
            0%, 49%, 100% { opacity: 1; }
            50%, 99% { opacity: 0; }
        }
        
        /* Voice button styling */
        #voiceBtn {
            position: relative;
            background: #fff;
            border: 1px solid #ddd;
            color: #4099ff;
            width: 40px;
            height: 40px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
            font-size: 18px;
        }
        
        #voiceBtn:hover {
            background: #f0f0f0;
            border-color: #4099ff;
            color: #4099ff;
            transform: scale(1.05);
        }
        
        #voiceBtn:active {
            transform: scale(0.95);
        }
        
        #voiceBtn.recording {
            background: #f44336;
            border-color: #f44336;
            color: #fff;
        }
        
        #voiceBtn.recording:hover {
            background: #e53935;
            border-color: #e53935;
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
            background: linear-gradient(180deg, rgba(255,255,255,1) 0%, rgba(248,249,250,0.8) 100%);
            border-top: 1px solid rgba(222, 226, 230, 0.6);
            padding: 16px 20px;
            flex-shrink: 0;
            box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.04);
            backdrop-filter: blur(10px);
        }
        
        .input-area .d-flex {
            gap: 12px;
        }
        
        .input-area .btn-group {
            display: flex;
            gap: 8px;
            align-items: center;
            padding: 0;
            border: none;
            background: transparent;
        }
        
        /* WhatsApp-like Message Input */
        #messageInput {
            border: 1px solid rgba(222, 226, 230, 0.8);
            border-radius: 24px;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 500;
            resize: none;
            max-height: 120px;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            background: #f8f9fa;
            color: #1f2937;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        #messageInput:focus {
            outline: none;
            border-color: #4099ff;
            box-shadow: 0 0 0 3px rgba(64, 153, 255, 0.15), 
                        inset 0 1px 3px rgba(0, 0, 0, 0.05);
            background: #fff;
        }
        
        #messageInput::placeholder {
            color: #9ca3af;
            font-weight: 400;
        }
        
        /* Send Button */
        #sendBtn {
            background: linear-gradient(135deg, #4099ff 0%, #2d7acc 100%);
            border: none;
            border-radius: 50%;
            width: 44px;
            height: 44px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            font-size: 18px;
            flex-shrink: 0;
            color: white;
            box-shadow: 0 2px 8px rgba(64, 153, 255, 0.3), 
                        inset 0 1px 2px rgba(255, 255, 255, 0.2);
            cursor: pointer;
        }
        
        #sendBtn:hover {
            background: linear-gradient(135deg, #2d7acc 0%, #1a5fa0 100%);
            transform: scale(1.08);
            box-shadow: 0 4px 12px rgba(64, 153, 255, 0.4), 
                        inset 0 1px 2px rgba(255, 255, 255, 0.2);
        }
        
        #sendBtn:active {
            transform: scale(0.95);
        }
        
        #sendBtn:disabled {
            background: #d1d5db;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        /* WhatsApp-like File Previews */
        .whatsapp-image-preview {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            overflow: hidden;
            background: #f0f0f0;
            max-width: 300px;
            max-height: 300px;
        }
        
        .whatsapp-image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .whatsapp-image-preview img:hover {
            transform: scale(1.02);
        }
        
        .whatsapp-image-preview .file-fallback {
            width: 100%;
            height: 100%;
            display: flex !important;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: #e8e8e8;
            border-radius: 12px;
        }
        
        .whatsapp-video-preview {
            max-width: 320px;
            border-radius: 12px;
            overflow: hidden;
            background: #000;
        }
        
        .whatsapp-video-preview video {
            width: 100%;
            height: auto;
            display: block;
            border-radius: 12px;
        }
        
        .whatsapp-audio-preview {
            padding: 12px;
            background: #f0f0f0;
            border-radius: 12px;
            min-width: 280px;
        }
        
        .whatsapp-audio-preview audio {
            width: 100%;
            border-radius: 8px;
        }
        
        .whatsapp-file-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: #f0f0f0;
            border-radius: 12px;
            min-width: 280px;
            max-width: 340px;
            transition: background 0.2s;
        }
        
        .whatsapp-file-card:hover {
            background: #e8e8e8;
        }
        
        .message.outgoing .whatsapp-file-card {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .message.outgoing .whatsapp-file-card:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .file-card-icon {
            font-size: 2rem;
            min-width: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.7;
        }
        
        .file-card-content {
            flex: 1;
            min-width: 0;
        }
        
        .file-card-name {
            font-weight: 500;
            color: #1f2937;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 0.95rem;
        }
        
        .message.outgoing .file-card-name {
            color: white;
        }
        
        .file-card-size {
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 2px;
        }
        
        .message.outgoing .file-card-size {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .file-card-download {
            padding: 8px;
            background: #4099ff;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            flex-shrink: 0;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9rem;
        }
        
        .file-card-download:hover {
            background: #2d7acc;
            transform: scale(1.1);
        }
        
        .message.outgoing .file-card-download {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .message.outgoing .file-card-download:hover {
            background: rgba(255, 255, 255, 0.5);
            color: white;
        }
        
        /* Image Lightbox Modal */
        .image-lightbox-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .image-lightbox-modal.hidden {
            display: none !important;
        }
        
        .lightbox-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: -1;
            backdrop-filter: blur(5px);
        }
        
        .lightbox-container {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            max-width: 90vw;
            max-height: 90vh;
            animation: zoomIn 0.3s ease;
        }
        
        @keyframes zoomIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .lightbox-content {
            display: flex;
            align-items: center;
            justify-content: center;
            max-width: 90vw;
            max-height: 80vh;
            overflow: auto;
        }
        
        .lightbox-content img {
            width: auto;
            height: auto;
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }
        
        .lightbox-close {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 44px;
            height: 44px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            z-index: 10001;
        }
        
        .lightbox-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }
        
        .lightbox-info {
            color: white;
            margin-top: 16px;
            font-size: 0.95rem;
            text-align: center;
            max-width: 90vw;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        /* Back button on mobile */
        .back-button {
            display: none;
        }
        
        /* Waveform Mobile Optimization */
        @media (max-width: 768px) {
            .voice-waveform-container {
                height: 32px;
                gap: 1.5px;
            }
            
            .waveform-bar {
                width: 2px;
            }
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
            <div class="chat-header hidden" id="chatHeader">
                <div style="display: flex; align-items: center; gap: 1rem; position: relative;">
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
                        <button class="btn btn-light btn-sm rounded-circle" id="searchMessagesBtn" title="Search messages">
                            <i class="fas fa-search"></i>
                        </button>
                        <button class="btn btn-light btn-sm rounded-circle" id="chatMenuBtn" title="More options" style="position: relative;">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Search messages bar (hidden by default) -->
                <div class="search-messages-bar hidden" id="searchMessagesBar">
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" id="searchMessagesInput" placeholder="Search messages...">
                        <button class="btn btn-outline-secondary btn-sm" id="clearSearchBtn">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Chat options menu (hidden by default) -->
                <div class="chat-menu-dropdown hidden" id="chatMenuDropdown">
                    <button class="chat-menu-item" id="deleteChatBtn">
                        <i class="fas fa-trash"></i>
                        <span>Clear chat</span>
                    </button>
                    <button class="chat-menu-item" id="blockUserBtn">
                        <i class="fas fa-ban"></i>
                        <span>Block user</span>
                    </button>
                    <div class="chat-menu-divider"></div>
                    <button class="chat-menu-item" id="chatInfoBtn">
                        <i class="fas fa-info-circle"></i>
                        <span>Chat info</span>
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
            <div class="input-area hidden" id="inputArea">
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

    <!-- Image Lightbox Modal -->
    <div id="imageLightboxModal" class="image-lightbox-modal hidden">
        <div class="lightbox-overlay" id="lightboxOverlay"></div>
        <div class="lightbox-container">
            <button class="lightbox-close" id="lightboxClose" title="Close">
                <i class="fas fa-times"></i>
            </button>
            <div class="lightbox-content">
                <img id="lightboxImage" src="" alt="Full view image" />
            </div>
            <div class="lightbox-info">
                <span id="lightboxFileName"></span>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- WaveSurfer for voice visualization -->
    <script src="https://unpkg.com/wavesurfer.js"></script>
    
    <!-- Chat Modules -->
    <script src="assets/js/chat/UIUtilities.js"></script>
    <script src="assets/js/chat/FileUploadProgress.js"></script>
    <script src="assets/js/chat/EmojiPickerEnhanced.js"></script>
    <script src="assets/js/chat/MessageActions.js"></script>
    <script src="assets/js/chat/KeyboardNavigation.js"></script>
    <script src="assets/js/chat/Accessibility.js"></script>
    <script src="assets/js/chat/AudioVisualization.js"></script>
    <script src="assets/js/chat/ChatManager.js"></script>
    <script src="assets/js/chat/ChatUIClean.js"></script>
    <script src="assets/js/chat/ChatAPI.js"></script>
    <script src="assets/js/chat/VoiceRecorder.js"></script>
    <script src="assets/js/chat/VoiceMessageUI.js"></script>
    <script src="assets/js/chat/VoiceMessageEnhanced.js"></script>
    <script src="assets/js/chat/VoiceMessageAdvanced.js"></script>
    <script src="assets/js/chat/init-clean.js"></script>
    <script src="assets/js/chat/EnhancementsInit.js"></script>
    
    <script>
         window.ALQ_USER_ID = <?php echo json_encode($currentUserId); ?>;
         window.csrfToken = <?php echo json_encode($csrfToken); ?>;

         // Make emoji picker accessible to the global scope
         window.chatApp = window.chatApp || {};
         window.chatApp.selectedEmoji = function(emoji) {
             const input = document.getElementById('messageInput');
             if (input) {
                 const cursorPos = input.selectionStart || 0;
                 const text = input.value;
                 input.value = text.substring(0, cursorPos) + emoji + text.substring(cursorPos);
                 input.focus();
             }
         };
         
         // Chat header functionality
         document.addEventListener('DOMContentLoaded', function() {
             const searchBtn = document.getElementById('searchMessagesBtn');
             const searchBar = document.getElementById('searchMessagesBar');
             const searchInput = document.getElementById('searchMessagesInput');
             const clearSearchBtn = document.getElementById('clearSearchBtn');
             const chatMenuBtn = document.getElementById('chatMenuBtn');
             const chatMenuDropdown = document.getElementById('chatMenuDropdown');
             const messagesContainer = document.getElementById('messagesContainer');
             
             // Toggle search bar
             searchBtn?.addEventListener('click', () => {
                 searchBar?.classList.toggle('hidden');
                 if (!searchBar?.classList.contains('hidden')) {
                     searchInput?.focus();
                 } else {
                     clearSearch();
                 }
             });
             
             // Clear search
             clearSearchBtn?.addEventListener('click', () => {
                 searchInput.value = '';
                 clearSearch();
             });
             
             // Search messages as user types
             searchInput?.addEventListener('input', (e) => {
                 const query = e.target.value.toLowerCase();
                 if (!query) {
                     clearSearch();
                     return;
                 }
                 
                 const messages = messagesContainer?.querySelectorAll('.message');
                 messages?.forEach(msg => {
                     const text = msg.querySelector('.message-bubble')?.textContent || '';
                     if (text.toLowerCase().includes(query)) {
                         msg.style.opacity = '1';
                         msg.style.display = 'flex';
                     } else {
                         msg.style.opacity = '0.3';
                     }
                 });
             });
             
             function clearSearch() {
                 const messages = messagesContainer?.querySelectorAll('.message');
                 messages?.forEach(msg => {
                     msg.style.opacity = '1';
                     msg.style.display = 'flex';
                 });
             }
             
             // Toggle chat menu
             chatMenuBtn?.addEventListener('click', (e) => {
                 e.stopPropagation();
                 chatMenuDropdown?.classList.toggle('hidden');
             });
             
             // Close menu on outside click
             document.addEventListener('click', () => {
                 chatMenuDropdown?.classList.add('hidden');
             });
             
             // Chat menu actions
             document.getElementById('deleteChatBtn')?.addEventListener('click', () => {
                 if (confirm('Clear all messages in this chat?')) {
                     messagesContainer.innerHTML = '';
                     chatMenuDropdown?.classList.add('hidden');
                     alert('Chat cleared');
                 }
             });
             
             document.getElementById('blockUserBtn')?.addEventListener('click', () => {
                 if (confirm('Block this user?')) {
                     chatMenuDropdown?.classList.add('hidden');
                     alert('User blocked');
                 }
             });
             
             document.getElementById('chatInfoBtn')?.addEventListener('click', () => {
                 const contactName = document.getElementById('contactName').textContent;
                 alert('Chat with: ' + contactName);
                 chatMenuDropdown?.classList.add('hidden');
             });
         });
     </script>
</body>
</html>
