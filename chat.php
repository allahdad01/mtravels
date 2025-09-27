<?php
	// Enhanced chat interface with improved mobile responsiveness and professional design
	session_start();
	$currentUserId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
	if (!$currentUserId) {
		header('Location: login.php');
		exit;
	}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no">
	<title>Chat - Professional Messaging</title>
	<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
	<style>
		:root {
			--chat-primary: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
			--chat-primary-dark: linear-gradient(135deg, #2ed8b6 0%, #4099ff 100%);
			--chat-primary-solid: #4099ff;
			--chat-primary-dark-solid: #2ed8b6;
			--chat-secondary: #f8fafc;
			--chat-accent: #10b981;
			--chat-danger: #ef4444;
			--chat-warning: #f59e0b;
			--sidebar-width: 320px;
			--header-height: 64px;
			--input-height: 80px;
		}

		* {
			box-sizing: border-box;
		}

		body {
			margin: 0;
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
			-webkit-font-smoothing: antialiased;
			-moz-osx-font-smoothing: grayscale;
		}

		/* Ensure app fills the visual viewport on mobile */
		html, body {
			height: 100%;
			overscroll-behavior-y: contain;
		}

		.chat-container {
			height: calc(var(--app-vh, 1vh) * 100);
			display: flex;
			overflow: hidden;
			background: #f1f5f9;
		}

		/* Sidebar Styles */
		.sidebar {
			width: var(--sidebar-width);
			background: white;
			display: flex;
			flex-direction: column;
			border-right: 1px solid #e2e8f0;
			transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
			position: relative;
			z-index: 20;
		}

		.sidebar-header {
			height: var(--header-height);
			background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
			color: white;
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 0 1rem;
			flex-shrink: 0;
		}

		.sidebar-header h1 {
			font-size: 1.25rem;
			font-weight: 600;
			margin: 0;
		}

		.search-container {
			padding: 1rem;
			background: white;
			border-bottom: 1px solid #e2e8f0;
		}

		.search-input {
			width: 100%;
			padding: 0.75rem 1rem 0.75rem 2.5rem;
			border: 1px solid #d1d5db;
			border-radius: 0.5rem;
			font-size: 0.875rem;
			background: #f9fafb;
			transition: all 0.2s;
		}

		.search-input:focus {
			outline: none;
			border-color: #4099ff;
			background: white;
			box-shadow: 0 0 0 3px rgba(64, 153, 255, 0.1);
		}

		.search-icon {
			position: absolute;
			left: 1.75rem;
			top: 50%;
			transform: translateY(-50%);
			color: #9ca3af;
			pointer-events: none;
		}

		.contact-list {
			flex: 1;
			overflow-y: auto;
			-webkit-overflow-scrolling: touch;
			min-height: 0;
			padding: 0.5rem 0;
		}

		.contact-item {
			padding: 1rem;
			cursor: pointer;
			border-bottom: 1px solid #f1f5f9;
			transition: all 0.2s;
			display: flex;
			align-items: center;
			gap: 0.75rem;
			position: relative;
		}

		.contact-item:hover {
			background: #f8fafc;
		}

		.contact-item.active {
			background: #eff6ff;
			border-right: 3px solid #4099ff;
		}

		.contact-avatar {
			width: 48px;
			height: 48px;
			border-radius: 50%;
			background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
			display: flex;
			align-items: center;
			justify-content: center;
			color: white;
			font-weight: 600;
			font-size: 1.125rem;
			flex-shrink: 0;
		}

		.contact-info {
			flex: 1;
			min-width: 0;
		}

		.contact-name {
			font-weight: 600;
			color: #1f2937;
			margin-bottom: 0.25rem;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}

		.contact-last-message {
			font-size: 0.875rem;
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
		}

		.contact-time {
			font-size: 0.75rem;
			color: #9ca3af;
		}

		.contact-badge {
			background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
			color: white;
			border-radius: 50%;
			width: 20px;
			height: 20px;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 0.75rem;
			font-weight: 600;
		}

		/* Main Chat Area */
		.chat-main {
			flex: 1;
			display: flex;
			flex-direction: column;
			background: white;
			min-width: 0;
			min-height: 0;
		}

		/* Make chat screen fill and stack vertically so input stays at bottom */
		.chat-screen {
			display: flex;
			flex-direction: column;
			height: 100%;
			min-height: 0;
		}

		/* Prevent this non-scrolling section from taking flexible height */
		.load-older-container {
			flex-shrink: 0;
		}

		.chat-header {
			height: var(--header-height);
			background: white;
			border-bottom: 1px solid #e2e8f0;
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 0 1rem;
			flex-shrink: 0;
		}

		.chat-header-left {
			display: flex;
			align-items: center;
			gap: 1rem;
		}

		.back-button {
			display: none;
			background: none;
			border: none;
			font-size: 1.25rem;
			color: #6b7280;
			cursor: pointer;
			padding: 0.5rem;
			border-radius: 0.5rem;
			transition: background 0.2s;
		}

		.back-button:hover {
			background: #f3f4f6;
		}

		.chat-header-info {
			display: flex;
			align-items: center;
			gap: 0.75rem;
		}

		.chat-avatar {
			width: 40px;
			height: 40px;
			border-radius: 50%;
			background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
			display: flex;
			align-items: center;
			justify-content: center;
			color: white;
			font-weight: 600;
		}

		.chat-contact-info h3 {
			margin: 0;
			font-size: 1rem;
			font-weight: 600;
			color: #1f2937;
		}

		.chat-contact-status {
			font-size: 0.75rem;
			margin: 0;
			color: #6b7280; /* default neutral */
		}
		.chat-contact-status.online { color: #10b981; }
		.chat-contact-status.offline { color: #9ca3af; }
		.chat-contact-status.typing { color: #2563eb; }

		.chat-actions {
			display: flex;
			align-items: center;
			gap: 0.5rem;
		}

		.action-button {
			background: #f3f4f6;
			border: none;
			border-radius: 0.5rem;
			padding: 0.5rem;
			color: #6b7280;
			cursor: pointer;
			transition: all 0.2s;
			font-size: 0.875rem;
		}

		.action-button:hover {
			background: #e5e7eb;
			color: #374151;
		}

		.action-button.active {
			background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
			color: white;
		}

		/* Dropdown */
		.dropdown { position: relative; }
		.dropdown-menu {
			position: absolute;
			right: 0;
			top: 100%;
			margin-top: 0.5rem;
			background: #ffffff;
			border: 1px solid #e5e7eb;
			border-radius: 0.5rem;
			min-width: 200px;
			box-shadow: 0 10px 20px rgba(0,0,0,0.08);
			z-index: 40;
			display: none;
			padding: 0.25rem;
		}
		.dropdown.open .dropdown-menu { display: block; }
		.dropdown-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.75rem; border-radius: 0.375rem; cursor: pointer; color: #374151; }
		.dropdown-item:hover { background: #f3f4f6; }

		/* Messages Area */
		.messages-container {
			flex: 1;
			overflow-y: auto;
			-webkit-overflow-scrolling: touch;
			min-height: 0;
			padding: 1rem;
			background: linear-gradient(to bottom, #f8fafc, #ffffff);
			display: flex;
			flex-direction: column;
			gap: 0.5rem;
		}

		/* Ensure dynamic messages from chat.js align correctly */
		.messages-container .msg {
			max-width: 70%;
			width: fit-content;
		}

		.messages-container .msg.me { margin-left: auto; }
		.messages-container .msg.peer { margin-right: auto; }

		.messages-container img, 
		.messages-container video, 
		.messages-container audio {
			max-width: 100%;
			display: block;
		}

		.message {
			display: flex;
			margin-bottom: 1rem;
			animation: fadeInUp 0.3s ease-out;
			width: 100%;
		}

		.message.incoming {
			justify-content: flex-start;
		}

		.message.outgoing {
			justify-content: flex-end;
		}

		/* Use auto margins on bubbles to snap to edges */
		.message .message-bubble {
			margin: 0;
		}

		.message.incoming .message-bubble {
			margin-right: auto;
		}

		.message.outgoing .message-bubble {
			margin-left: auto;
		}

		.message-avatar {
			width: 32px;
			height: 32px;
			border-radius: 50%;
			margin: 0 0.5rem;
			flex-shrink: 0;
		}

		.message-bubble {
			max-width: 70%;
			padding: 0.75rem 1rem;
			border-radius: 1rem;
			position: relative;
			word-wrap: break-word;
		}

		.msg {
			position: relative;
		}

		.message.incoming .message-bubble {
			background: #f3f4f6;
			color: #1f2937;
			border-bottom-left-radius: 0.25rem;
		}

		.message.outgoing .message-bubble {
			background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
			color: white;
			border-bottom-right-radius: 0.25rem;
		}

		.message-time {
			font-size: 0.75rem;
			opacity: 0.7;
			margin-top: 0.25rem;
		}

		.message-actions {
			position: absolute;
			top: 0.5rem;
			display: none;
		}

		/* For sent messages (right-aligned) */
		.msg.me .message-actions {
			right: 0.5rem;
		}

		/* For received messages (left-aligned) */
		.msg.peer .message-actions {
			left: 0.5rem;
		}

		.msg:hover .message-actions {
			display: block;
		}

		/* Ensure message bubbles have relative positioning for dropdown */
		.message-bubble {
			position: relative;
		}

		.message-menu-btn {
			background: rgba(0, 0, 0, 0.5);
			border: none;
			color: white;
			font-size: 0.875rem;
			padding: 0.25rem;
			border-radius: 50%;
			cursor: pointer;
			transition: background 0.2s;
			width: 24px;
			height: 24px;
			display: flex;
			align-items: center;
			justify-content: center;
		}

		.message-menu-btn:hover {
			background: rgba(0, 0, 0, 0.7);
		}

		.message-dropdown {
			position: absolute;
			top: 100%;
			background: white;
			border: 1px solid #e5e7eb;
			border-radius: 0.5rem;
			box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
			min-width: 160px;
			z-index: 1000;
			display: none;
			margin-top: 0.25rem;
		}

		/* For sent messages (right-aligned) - dropdown appears from right */
		.msg.me .message-dropdown {
			right: 0;
		}

		/* For received messages (left-aligned) - dropdown appears from left */
		.msg.peer .message-dropdown {
			left: 0;
		}

		.message-dropdown.show {
			display: block;
		}

		.message-dropdown-item {
			display: flex;
			align-items: center;
			gap: 0.5rem;
			padding: 0.75rem 1rem;
			cursor: pointer;
			color: #374151;
			transition: background 0.2s;
			border-radius: 0.25rem;
			margin: 0.125rem;
		}

		.message-dropdown-item:hover {
			background: #f3f4f6;
		}

		.message-dropdown-item.danger {
			color: #dc2626;
		}

		.message-dropdown-item.danger:hover {
			background: #fef2f2;
		}

		.message-dropdown-divider {
			height: 1px;
			background: #e5e7eb;
			margin: 0.25rem 0;
		}

		/* Forward Modal Styles */
		.forward-modal {
			position: fixed;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: rgba(0, 0, 0, 0.5);
			display: flex;
			align-items: center;
			justify-content: center;
			z-index: 10000;
			opacity: 0;
			visibility: hidden;
			transition: all 0.3s ease;
		}

		.forward-modal.show {
			opacity: 1;
			visibility: visible;
		}

		.forward-modal-content {
			background: white;
			border-radius: 0.75rem;
			width: 90%;
			max-width: 400px;
			max-height: 80vh;
			display: flex;
			flex-direction: column;
			box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
		}

		.forward-modal-header {
			padding: 1.5rem 1.5rem 1rem;
			border-bottom: 1px solid #e5e7eb;
		}

		.forward-modal-title {
			font-size: 1.125rem;
			font-weight: 600;
			color: #1f2937;
			margin: 0;
		}

		.forward-modal-subtitle {
			font-size: 0.875rem;
			color: #6b7280;
			margin: 0.5rem 0 0;
		}

		.forward-modal-body {
			flex: 1;
			overflow-y: auto;
			padding: 0;
		}

		.forward-contact-list {
			padding: 0.5rem 0;
		}

		.forward-contact-item {
			display: flex;
			align-items: center;
			padding: 0.75rem 1.5rem;
			cursor: pointer;
			transition: background 0.2s;
			border-bottom: 1px solid #f3f4f6;
		}

		.forward-contact-item:hover {
			background: #f9fafb;
		}

		.forward-contact-item.selected {
			background: #eff6ff;
		}

		.forward-contact-checkbox {
			width: 20px;
			height: 20px;
			border: 2px solid #d1d5db;
			border-radius: 4px;
			margin-right: 1rem;
			display: flex;
			align-items: center;
			justify-content: center;
			transition: all 0.2s;
		}

		.forward-contact-item.selected .forward-contact-checkbox {
			background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
			border-color: #4099ff;
		}

		.forward-contact-item.selected .forward-contact-checkbox::after {
			content: '✓';
			color: white;
			font-size: 12px;
			font-weight: bold;
		}

		.forward-contact-avatar {
			width: 40px;
			height: 40px;
			border-radius: 50%;
			background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
			display: flex;
			align-items: center;
			justify-content: center;
			color: white;
			font-weight: 600;
			margin-right: 0.75rem;
			flex-shrink: 0;
		}

		.forward-contact-info {
			flex: 1;
			min-width: 0;
		}

		.forward-contact-name {
			font-weight: 600;
			color: #1f2937;
			margin-bottom: 0.125rem;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}

		.forward-contact-role {
			font-size: 0.875rem;
			color: #6b7280;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}

		.forward-modal-footer {
			padding: 1rem 1.5rem 1.5rem;
			border-top: 1px solid #e5e7eb;
			display: flex;
			gap: 0.75rem;
		}

		.forward-btn {
			flex: 1;
			padding: 0.75rem 1rem;
			border: none;
			border-radius: 0.5rem;
			font-weight: 500;
			cursor: pointer;
			transition: all 0.2s;
		}

		.forward-btn.primary {
			background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
			color: white;
		}

		.forward-btn.primary:hover {
			background: linear-gradient(135deg, #2ed8b6 0%, #4099ff 100%) !important;
		}

		.forward-btn.primary:disabled {
			background: #d1d5db;
			cursor: not-allowed;
		}

		.forward-btn.secondary {
			background: #f3f4f6;
			color: #374151;
		}

		.forward-btn.secondary:hover {
			background: #e5e7eb;
		}

		.msg.editing {
			border: 2px solid #4099ff;
		}

		.message-edit-input {
			width: 100%;
			border: none;
			background: transparent;
			color: inherit;
			font-size: inherit;
			font-family: inherit;
			outline: none;
			resize: none;
		}

		/* Reply context styling */
		.reply-context {
			transition: all 0.2s ease;
			cursor: pointer;
		}

		.reply-context:hover {
			background: #e5e7eb !important;
		}

		/* Highlight effect for replied messages */
		.msg.highlight-reply {
			animation: highlightReply 2s ease-in-out;
		}

		@keyframes highlightReply {
			0% { background-color: #dbeafe; }
			50% { background-color: #bfdbfe; }
			100% { background-color: transparent; }
		}

		/* Highlight effect for replied messages */
		.message.highlight-reply {
			animation: highlightReply 2s ease-in-out;
		}

		@keyframes highlightReply {
			0% { background-color: #dbeafe; }
			50% { background-color: #bfdbfe; }
			100% { background-color: transparent; }
		}

		/* Input Area */
		.input-container {
			padding: 1rem;
			background: white;
			border-top: 1px solid #e2e8f0;
			flex-shrink: 0;
		}

		.input-wrapper {
			display: flex;
			align-items: flex-end;
			gap: 0.5rem;
			background: #f9fafb;
			border: 1px solid #d1d5db;
			border-radius: 1.5rem;
			padding: 0.5rem;
			transition: all 0.2s;
		}

		.input-wrapper:focus-within {
			border-color: #4099ff;
			box-shadow: 0 0 0 3px rgba(64, 153, 255, 0.1);
		}

		.message-input {
			flex: 1;
			border: none;
			background: none;
			outline: none;
			padding: 0.5rem 1rem;
			font-size: 0.875rem;
			resize: none;
			max-height: 120px;
			min-height: 20px;
		}

		.input-actions {
			display: flex;
			gap: 0.25rem;
		}

		.input-action {
			background: none;
			border: none;
			color: #6b7280;
			cursor: pointer;
			padding: 0.5rem;
			border-radius: 50%;
			transition: all 0.2s;
			display: flex;
			align-items: center;
			justify-content: center;
		}

		.input-action:hover {
			background: #e5e7eb;
			color: #374151;
		}

		.send-button {
			background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
			color: white;
		}

		.send-button:hover {
			background: linear-gradient(135deg, #2ed8b6 0%, #4099ff 100%) !important;
		}

		.send-button:disabled {
			background: #d1d5db !important;
			cursor: not-allowed;
		}

		/* Welcome Screen */
		.welcome-screen {
			flex: 1;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			text-align: center;
			padding: 2rem;
			background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
		}

		.welcome-icon {
			font-size: 4rem;
			background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
			-webkit-background-clip: text !important;
			-webkit-text-fill-color: transparent !important;
			background-clip: text;
			margin-bottom: 1rem;
		}

		.welcome-title {
			font-size: 1.5rem;
			font-weight: 600;
			color: #1f2937;
			margin-bottom: 0.5rem;
		}

		.welcome-subtitle {
			color: #6b7280;
			font-size: 1rem;
		}

		/* Mobile Styles */
		@media (max-width: 768px) {
			.sidebar {
				position: fixed;
				left: 0;
				top: 0;
				height: 100vh;
				transform: translateX(-100%);
				z-index: 30;
				box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
			}

			.sidebar.open {
				transform: translateX(0);
			}

			/* Ensure chat fills the full width when sidebar is hidden */
			.chat-main {
				transform: translateX(0) !important;
			}

			.back-button {
				display: block;
			}

			.chat-main.sidebar-open {
				transform: translateX(var(--sidebar-width));
			}

			.message-bubble {
				max-width: 85%;
			}

			.chat-actions {
				gap: 0.25rem;
			}

			.action-button {
				padding: 0.375rem;
				font-size: 0.75rem;
			}
		}

		@media (max-width: 480px) {
			:root {
				--sidebar-width: 280px;
			}

			.contact-item {
				padding: 0.75rem;
			}

			.contact-avatar {
				width: 40px;
				height: 40px;
			}

			.messages-container {
				padding: 0.5rem;
			}

			.input-container {
				padding: 0.75rem;
			}
		}

		/* Animations */
		@keyframes fadeInUp {
			from {
				opacity: 0;
				transform: translateY(10px);
			}
			to {
				opacity: 1;
				transform: translateY(0);
			}
		}

		.fade-in {
			animation: fadeInUp 0.3s ease-out;
		}

		/* Utility Classes */
		.hidden {
			display: none !important;
		}

		.sr-only {
			position: absolute;
			width: 1px;
			height: 1px;
			padding: 0;
			margin: -1px;
			overflow: hidden;
			clip: rect(0, 0, 0, 0);
			white-space: nowrap;
			border: 0;
		}

		/* Scrollbar Styling */
		.contact-list::-webkit-scrollbar,
		.messages-container::-webkit-scrollbar {
			width: 6px;
		}

		.contact-list::-webkit-scrollbar-track,
		.messages-container::-webkit-scrollbar-track {
			background: #f1f5f9;
		}

		.contact-list::-webkit-scrollbar-thumb,
		.messages-container::-webkit-scrollbar-thumb {
			background: #cbd5e1;
			border-radius: 3px;
		}

		.contact-list::-webkit-scrollbar-thumb:hover,
		.messages-container::-webkit-scrollbar-thumb:hover {
			background: #94a3b8;
		}
	</style>
</head>

<body>
	<div class="chat-container">
		<!-- Sidebar -->
		<aside class="sidebar" id="sidebar">
			<div class="sidebar-header">
				<h1>Messages</h1>
				<button class="action-button" id="newChatBtn" title="New Chat">
					<i class="fas fa-plus"></i>
				</button>
			</div>

			<div class="search-container">
				<div style="position: relative;">
					<i class="fas fa-search search-icon"></i>
					<input 
						type="text" 
						class="search-input" 
						id="contactSearch" 
						placeholder="Search conversations..."
						autocomplete="off"
					>
				</div>
			</div>

			<div class="contact-list" id="contactList">
				<!-- Contacts will be populated here -->
			</div>
		</aside>

		<!-- Main Chat Area -->
		<main class="chat-main" id="chatMain">
			<!-- Welcome Screen -->
			<div class="welcome-screen" id="welcomeScreen">
				<i class="fas fa-comments welcome-icon"></i>
				<h2 class="welcome-title">Welcome to Chat</h2>
				<p class="welcome-subtitle">Select a conversation to start messaging</p>
			</div>

			<!-- Chat Screen -->
			<div class="chat-screen hidden" id="chatScreen">
				<!-- Chat Header -->
				<header class="chat-header">
					<div class="chat-header-left">
						<button class="back-button" id="backButton" aria-label="Back to conversations">
							<i class="fas fa-arrow-left"></i>
						</button>
						
						<div class="chat-header-info">
							<div class="chat-avatar" id="chatAvatar">JD</div>
							<div class="chat-contact-info">
								<h3 id="contactName">John Doe</h3>
								<p class="chat-contact-status" id="contactStatus">Online</p>
							</div>
						</div>

						<div style="margin-left: 1rem;" class="hidden">
							<span id="myIdEl" style="font-size: 0.75rem; color: #6b7280;"></span>
							<select id="roomId" class="action-button" style="margin-left: 0.5rem;">
								<option value="">Select Room</option>
							</select>
						</div>
					</div>

					<div class="chat-actions">
						<div class="dropdown" id="headerDropdown">
							<button class="action-button" id="dropdownToggle" title="Actions">
								<i class="fas fa-ellipsis-v"></i>
							</button>
							<div class="dropdown-menu" id="dropdownMenu">
								<div class="dropdown-item" id="blockBtn"><i class="fas fa-ban"></i> <span>Block / Unblock</span></div>
								<div class="dropdown-item" id="muteBtn"><i class="fas fa-volume-mute"></i> <span>Mute / Unmute</span></div>
								<label class="dropdown-item" style="cursor: pointer;">
									<i class="fas fa-download"></i>
									<input type="checkbox" id="autoDownload" style="margin: 0 0.5rem 0 0;" />
									<span>Auto Download</span>
								</label>
							</div>
						</div>
					</div>
				</header>

				<!-- Notice Area -->
				<div id="notice" class="hidden" style="padding: 0.75rem; background: #fef2f2; color: #dc2626; border-bottom: 1px solid #fecaca; font-size: 0.875rem;"></div>

				<!-- Load Older Button -->
				<div class="load-older-container">
					<button id="loadOlder" class="action-button" style="padding: 0.5rem 1rem;">
						<i class="fas fa-history"></i> Load older messages
					</button>
				</div>

				<!-- Messages Container -->
				<div class="messages-container" id="messages">
					<!-- Messages will be populated here -->
				</div>

				<!-- Input Area -->
				<div class="input-container">
					<div class="input-wrapper">
						<textarea 
							class="message-input" 
							id="textInput" 
							placeholder="Type your message..."
							rows="1"
						></textarea>
						
						<div class="input-actions">
							<input type="file" id="fileInput" class="hidden" multiple />
							<button class="input-action" id="fileBtn" title="Attach file">
								<i class="fas fa-paperclip"></i>
							</button>
							<button class="input-action" id="recBtn" title="Voice message">
								<i class="fas fa-microphone"></i>
							</button>
							<button class="input-action send-button" id="sendBtn" title="Send message">
								<i class="fas fa-paper-plane"></i>
							</button>
						</div>
					</div>
				</div>
			</div>
		</main>
	</div>

	<!-- Forward Modal -->
	<div class="forward-modal" id="forwardModal">
		<div class="forward-modal-content">
			<div class="forward-modal-header">
				<h3 class="forward-modal-title">Forward Message</h3>
				<p class="forward-modal-subtitle" id="forwardMessagePreview">Select contacts to forward this message to</p>
			</div>
			<div class="forward-modal-body">
				<div class="forward-contact-list" id="forwardContactList">
					<!-- Contacts will be populated here -->
				</div>
			</div>
			<div class="forward-modal-footer">
				<button class="forward-btn secondary" onclick="window.closeForwardModal()">Cancel</button>
				<button class="forward-btn primary" id="forwardSendBtn" onclick="window.sendForwardedMessage()" disabled>Forward</button>
			</div>
		</div>
	</div>

	<script>
		// Global configuration
		window.ALQ_USER_ID = <?php echo json_encode($currentUserId); ?>;
		window.SIGNALING_URL = (location.protocol === 'https:' ? 'wss://' : 'ws://') + (location.hostname) + ':8089';
		window.CHAT_SETTINGS = null;
		window.TURN_RELAYS = window.TURN_RELAYS || null;

		// Dynamic viewport height fix for mobile browsers (address bar)
		(function setAppVh() {
			const appVh = window.innerHeight * 0.01;
			document.documentElement.style.setProperty('--app-vh', `${appVh}px`);
		})();
		window.addEventListener('resize', () => {
			const appVh = window.innerHeight * 0.01;
			document.documentElement.style.setProperty('--app-vh', `${appVh}px`);
		});
		window.addEventListener('orientationchange', () => {
			setTimeout(() => {
				const appVh = window.innerHeight * 0.01;
				document.documentElement.style.setProperty('--app-vh', `${appVh}px`);
			}, 250);
		});

		// Enhanced Chat Application
		class ChatApp {
			constructor() {
				this.currentContactId = null;
				this.contacts = [];
				this.init();
			}

			init() {
				this.bindEvents();
				this.loadChatSettings();
				this.loadContacts();
				this.setupMessageInput();
				// Ensure mobile shows contacts primarily until a contact is selected
				if (window.innerWidth <= 768) {
					this.showSidebar();
				}
			}

			bindEvents() {
				// Mobile navigation
				const backButton = document.getElementById('backButton');
				const sidebar = document.getElementById('sidebar');
				const chatMain = document.getElementById('chatMain');

				backButton?.addEventListener('click', () => {
					this.showSidebar();
				});

				// Search functionality
				const searchInput = document.getElementById('contactSearch');
				searchInput?.addEventListener('input', (e) => {
					this.filterContacts(e.target.value);
				});

				// File input
				const fileBtn = document.getElementById('fileBtn');
				const fileInput = document.getElementById('fileInput');
				fileBtn?.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); });

				// Send button and enter key
				const sendBtn = document.getElementById('sendBtn');
				const textInput = document.getElementById('textInput');
				
				sendBtn?.addEventListener('click', () => this.sendMessage());
				textInput?.addEventListener('keydown', (e) => {
					if (e.key === 'Enter' && !e.shiftKey) {
						e.preventDefault();
						this.sendMessage();
					}
				});

				// Auto-resize textarea
				textInput?.addEventListener('input', this.autoResizeTextarea);
			}

			autoResizeTextarea(e) {
				const textarea = e.target;
				textarea.style.height = 'auto';
				textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
			}

			setupMessageInput() {
				const textInput = document.getElementById('textInput');
				const sendBtn = document.getElementById('sendBtn');
				
				textInput?.addEventListener('input', (e) => {
					const hasText = e.target.value.trim().length > 0;
					sendBtn?.classList.toggle('active', hasText);
					if (sendBtn) sendBtn.disabled = !hasText;
				});
			}

			async loadChatSettings() {
				try {
					const response = await fetch('api/chat_settings.php', { credentials: 'include' });
					if (response.ok) {
						const settings = await response.json();
						window.CHAT_SETTINGS = settings;
						
						const autoDownloadCheckbox = document.getElementById('autoDownload');
						if (autoDownloadCheckbox && typeof settings.default_auto_download === 'boolean') {
							autoDownloadCheckbox.checked = settings.default_auto_download;
						}
					}
				} catch (error) {
					console.error('Failed to load chat settings:', error);
				}
			}

			loadContacts() {
				// Example contact data - replace with actual API call
				const exampleContacts = [
					{
						id: '1',
						name: 'John Doe',
						lastMessage: 'Hey, how are you doing today?',
						time: '2 min',
						unread: 2,
						avatar: 'JD',
						online: true
					},
					{
						id: '2',
						name: 'Jane Smith',
						lastMessage: 'See you at the meeting tomorrow!',
						time: '1 hour',
						unread: 0,
						avatar: 'JS',
						online: false
					},
					{
						id: '3',
						name: 'Mike Johnson',
						lastMessage: 'Thanks for your help with the project',
						time: '3 hours',
						unread: 1,
						avatar: 'MJ',
						online: true
					},
					{
						id: '4',
						name: 'Sarah Wilson',
						lastMessage: 'The documents are ready for review',
						time: '1 day',
						unread: 0,
						avatar: 'SW',
						online: false
					}
				];

				this.contacts = exampleContacts;
				this.renderContacts(this.contacts);
			}

			renderContacts(contacts) {
				const contactList = document.getElementById('contactList');
				if (!contactList) return;

				contactList.innerHTML = contacts.map(contact => `
					<div class="contact-item fade-in" data-id="${contact.id}" onclick="chatApp.selectContact('${contact.id}')">
						<div class="contact-avatar">${contact.avatar}</div>
						<div class="contact-info">
							<div class="contact-name">${contact.name}</div>
							<div class="contact-last-message">${contact.lastMessage}</div>
						</div>
						<div class="contact-meta">
							<div class="contact-time">${contact.time}</div>
							${contact.unread > 0 ? `<div class="contact-badge">${contact.unread}</div>` : ''}
						</div>
					</div>
				`).join('');
			}

			filterContacts(query) {
				const filtered = this.contacts.filter(contact =>
					contact.name.toLowerCase().includes(query.toLowerCase()) ||
					contact.lastMessage.toLowerCase().includes(query.toLowerCase())
				);
				this.renderContacts(filtered);
			}

			selectContact(contactId) {
				const contact = this.contacts.find(c => c.id === contactId);
				if (!contact) return;

				this.currentContactId = contactId;

				// Update UI
				const welcomeScreen = document.getElementById('welcomeScreen');
				const chatScreen = document.getElementById('chatScreen');
				const contactName = document.getElementById('contactName');
				const chatAvatar = document.getElementById('chatAvatar');
				const contactStatus = document.getElementById('contactStatus');

				welcomeScreen?.classList.add('hidden');
				chatScreen?.classList.remove('hidden');

				if (contactName) contactName.textContent = contact.name;
				if (chatAvatar) chatAvatar.textContent = contact.avatar;
				if (contactStatus) {
					contactStatus.textContent = contact.online ? 'Online' : 'Offline';
					contactStatus.classList.remove('online', 'offline');
					contactStatus.classList.add(contact.online ? 'online' : 'offline');
				}

				// Update active state
				document.querySelectorAll('.contact-item').forEach(item => {
					item.classList.remove('active');
				});
				const selectedContact = document.querySelector(`.contact-item[data-id="${contactId}"]`);
				selectedContact?.classList.add('active');

				// Hide sidebar on mobile
				this.hideSidebar();

				// Load messages for this contact
				this.loadMessages(contactId);

				// Dispatch event for chat.js integration
				window.dispatchEvent(new CustomEvent('contactSelected', { detail: { contactId } }));
			}

			loadMessages(contactId) {
				// Example messages - replace with actual API call
				const exampleMessages = [
					{
						id: '1',
						text: 'Hi there! How are you doing?',
						type: 'incoming',
						time: '10:30 AM',
						avatar: 'JD'
					},
					{
						id: '2',
						text: 'Hey! I\'m doing great, thanks for asking. How about you?',
						type: 'outgoing',
						time: '10:32 AM'
					},
					{
						id: '3',
						text: 'That\'s wonderful to hear! I\'m doing well too. Are we still on for our meeting tomorrow?',
						type: 'incoming',
						time: '10:35 AM',
						avatar: 'JD'
					},
					{
						id: '4',
						text: 'Yes, the meeting is still on for tomorrow at 2 PM.',
						type: 'outgoing',
						time: '10:36 AM'
					}
				];

				this.renderMessages(exampleMessages);
			}

			renderMessages(messages) {
				const messagesContainer = document.getElementById('messages');
				if (!messagesContainer) return;

				messagesContainer.innerHTML = messages.map(message => {
					// Handle reply messages
					let displayText = message.text;
					let replyContext = null;

					try {
						const parsed = JSON.parse(message.text);
						if (parsed.type === 'reply') {
							replyContext = {
								replyTo: parsed.replyTo,
								replyText: parsed.replyText
							};
							displayText = parsed.content;
						}
					} catch {
						// Not a JSON message, use as is
					}

					// Add reply context if exists
					let replyHtml = '';
					if (replyContext) {
						replyHtml = `
							<div class="reply-context bg-gray-200 p-2 rounded mb-2 border-l-4" style="border-left-color: #4099ff !important;" onclick="window.scrollToReply('${replyContext.replyTo}')">
								<div class="text-xs text-gray-600 mb-1">Replying to:</div>
								<div class="text-sm text-gray-800 truncate">${replyContext.replyText}</div>
							</div>
						`;
					}

					return `
						<div class="message ${message.type} fade-in" data-message-id="${message.id || ''}">
							${message.type === 'incoming' ? `<div class="message-avatar contact-avatar" style="width: 32px; height: 32px; font-size: 0.875rem;">${message.avatar}</div>` : ''}
							<div class="message-bubble">
								${replyHtml}
								<div class="message-text">${displayText}</div>
								<div class="message-time">${message.time}</div>
								${message.type === 'outgoing' ? `
									<div class="message-actions">
										<button class="message-menu-btn" onclick="window.toggleMessageMenu(this)" title="Message options">
											<i class="fas fa-ellipsis-v"></i>
										</button>
										<div class="message-dropdown">
											<div class="message-dropdown-item" onclick="window.replyToMessage(this)">
												<i class="fas fa-reply"></i>
												<span>Reply</span>
											</div>
											<div class="message-dropdown-item" onclick="window.forwardMessage(this)">
												<i class="fas fa-share"></i>
												<span>Forward</span>
											</div>
											<div class="message-dropdown-item" onclick="window.copyMessage(this)">
												<i class="fas fa-copy"></i>
												<span>Copy</span>
											</div>
											<div class="message-dropdown-item" onclick="window.editMessage(this)">
												<i class="fas fa-edit"></i>
												<span>Edit</span>
											</div>
											<div class="message-dropdown-divider"></div>
											<div class="message-dropdown-item danger" onclick="window.deleteMessage(this)">
												<i class="fas fa-trash"></i>
												<span>Delete</span>
											</div>
										</div>
									</div>
								` : message.type === 'incoming' ? `
									<div class="message-actions">
										<button class="message-menu-btn" onclick="window.toggleMessageMenu(this)" title="Message options">
											<i class="fas fa-ellipsis-v"></i>
										</button>
										<div class="message-dropdown">
											<div class="message-dropdown-item" onclick="window.replyToMessage(this)">
												<i class="fas fa-reply"></i>
												<span>Reply</span>
											</div>
											<div class="message-dropdown-item" onclick="window.forwardMessage(this)">
												<i class="fas fa-share"></i>
												<span>Forward</span>
											</div>
											<div class="message-dropdown-item" onclick="window.copyMessage(this)">
												<i class="fas fa-copy"></i>
												<span>Copy</span>
											</div>
											<div class="message-dropdown-divider"></div>
											<div class="message-dropdown-item danger" onclick="window.deleteMessage(this)">
												<i class="fas fa-trash"></i>
												<span>Delete</span>
											</div>
										</div>
									</div>
								` : ''}
							</div>
							${message.type === 'outgoing' ? `<div class="message-avatar" style="width: 32px; height: 32px; background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;"></div>` : ''}
						</div>
					`;
				}).join('');

				// Scroll to bottom
				messagesContainer.scrollTop = messagesContainer.scrollHeight;
			}

			sendMessage() {
				const textInput = document.getElementById('textInput');
				const message = textInput?.value.trim();
				
				if (!message || !this.currentContactId) return;

				// Clear input
				textInput.value = '';
				textInput.style.height = 'auto';
				
				// Update send button state
				const sendBtn = document.getElementById('sendBtn');
				sendBtn?.classList.remove('active');
				if (sendBtn) sendBtn.disabled = true;

				// Add message to UI (example)
				this.addMessageToUI({
					id: Date.now().toString(),
					text: message,
					type: 'outgoing',
					time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
				});

				// TODO: Send message via your chat system (WebSocket, API, etc.)
				console.log('Sending message:', message, 'to contact:', this.currentContactId);
			}

			addMessageToUI(message) {
				const messagesContainer = document.getElementById('messages');
				if (!messagesContainer) return;

				const messageElement = document.createElement('div');
				messageElement.className = `message ${message.type} fade-in`;
				messageElement.setAttribute('data-message-id', message.id || '');

				// Handle reply messages
				let displayText = message.text;
				let replyContext = null;

				try {
					const parsed = JSON.parse(message.text);
					if (parsed.type === 'reply') {
						replyContext = {
							replyTo: parsed.replyTo,
							replyText: parsed.replyText
						};
						displayText = parsed.content;
					}
				} catch {
					// Not a JSON message, use as is
				}

				// Add reply context if exists
				let replyHtml = '';
				if (replyContext) {
					replyHtml = `
						<div class="reply-context bg-gray-200 p-2 rounded mb-2 border-l-4 border-blue-500 cursor-pointer" onclick="window.scrollToReply('${replyContext.replyTo}')">
							<div class="text-xs text-gray-600 mb-1">Replying to:</div>
							<div class="text-sm text-gray-800 truncate">${replyContext.replyText}</div>
						</div>
					`;
				}

				if (message.type === 'incoming') {
					messageElement.innerHTML = `
						<div class="message-avatar contact-avatar" style="width: 32px; height: 32px; font-size: 0.875rem;">${message.avatar || 'U'}</div>
						<div class="message-bubble">
							${replyHtml}
							<div class="message-text">${displayText}</div>
							<div class="message-time">${message.time}</div>
						</div>
					`;
				} else {
					messageElement.innerHTML = `
						<div class="message-bubble">
							${replyHtml}
							<div class="message-text">${displayText}</div>
							<div class="message-time">${message.time}</div>
							<div class="message-actions">
								<button class="message-action-btn edit" onclick="chatApp.editMessage('${message.id || ''}', this)" title="Edit message">
									<i class="fas fa-edit"></i>
								</button>
								<button class="message-action-btn delete" onclick="chatApp.deleteMessage('${message.id || ''}', this)" title="Delete message">
									<i class="fas fa-trash"></i>
								</button>
							</div>
						</div>
						<div class="message-avatar" style="width: 32px; height: 32px; background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.875rem;">Me</div>
					`;
				}

				messagesContainer.appendChild(messageElement);
				messagesContainer.scrollTop = messagesContainer.scrollHeight;
			}

			showSidebar() {
				const sidebar = document.getElementById('sidebar');
				const chatMain = document.getElementById('chatMain');
				
				sidebar?.classList.add('open');
				chatMain?.classList.add('sidebar-open');
				
				// Hide welcome/chat screen on mobile
				const welcomeScreen = document.getElementById('welcomeScreen');
				const chatScreen = document.getElementById('chatScreen');
				welcomeScreen?.classList.remove('hidden');
				chatScreen?.classList.add('hidden');
			}

			hideSidebar() {
				const sidebar = document.getElementById('sidebar');
				const chatMain = document.getElementById('chatMain');
				
				sidebar?.classList.remove('open');
				if (chatMain) {
					chatMain.classList.remove('sidebar-open');
					chatMain.style.transform = '';
				}
			}

			// Public method to update user ID display
			updateUserIdDisplay(userId) {
				const myIdEl = document.getElementById('myIdEl');
				if (myIdEl && userId) {
					myIdEl.textContent = `ID: ${userId}`;
				}
			}

			// Public method to update room options
			updateRoomOptions(rooms) {
				const roomSelect = document.getElementById('roomId');
				if (!roomSelect || !rooms) return;

				roomSelect.innerHTML = '<option value="">Select Room</option>' + 
					rooms.map(room => `<option value="${room.id}">${room.name}</option>`).join('');
			}

			// Public method to show notices
			showNotice(message, type = 'error') {
				const notice = document.getElementById('notice');
				if (!notice) return;

				notice.textContent = message;
				notice.className = type === 'error' ? 
					'p-2 text-sm text-red-600 bg-red-50 border-b border-red-200' : 
					'p-2 text-sm text-blue-600 bg-blue-50 border-b border-blue-200';
				notice.classList.remove('hidden');

				// Auto-hide after 5 seconds
				setTimeout(() => {
					notice.classList.add('hidden');
				}, 5000);
			}

			// Public method to hide notice
			hideNotice() {
				const notice = document.getElementById('notice');
				notice?.classList.add('hidden');
			}

			// Edit message functionality
			editMessage(messageId, buttonElement) {
				if (!messageId) return;

				const messageDiv = buttonElement.closest('.message');
				const messageBubble = messageDiv.querySelector('.message-bubble');
				const messageText = messageDiv.querySelector('.message-text');

				if (!messageText || !messageBubble) return;

				const currentText = messageText.textContent.trim();
				const textarea = document.createElement('textarea');
				textarea.className = 'message-edit-input';
				textarea.value = currentText;
				textarea.rows = Math.max(1, Math.ceil(currentText.length / 50));

				messageText.style.display = 'none';
				messageBubble.classList.add('editing');
				messageBubble.insertBefore(textarea, messageText);

				textarea.focus();
				textarea.select();

				const saveEdit = async () => {
					const newText = textarea.value.trim();
					if (newText && newText !== currentText) {
						try {
							const response = await fetch(`api/messages.php?id=${messageId}`, {
								method: 'PUT',
								headers: { 'Content-Type': 'application/json' },
								body: JSON.stringify({ content: newText }),
								credentials: 'include'
							});
							const result = await response.json();
							if (result.ok) {
								messageText.textContent = newText;
								// Broadcast edit to peer via WebRTC if connected
								this.broadcastEdit(messageId, newText);
							} else {
								alert('Failed to edit message: ' + (result.error || 'Unknown error'));
							}
						} catch (error) {
							console.error('Edit message error:', error);
							alert('Failed to edit message');
						}
					}
					cancelEdit();
				};

				const cancelEdit = () => {
					textarea.remove();
					messageText.style.display = '';
					messageBubble.classList.remove('editing');
				};

				textarea.addEventListener('keydown', (e) => {
					if (e.key === 'Enter' && !e.shiftKey) {
						e.preventDefault();
						saveEdit();
					} else if (e.key === 'Escape') {
						cancelEdit();
					}
				});

				textarea.addEventListener('blur', cancelEdit);
			}

			// Delete message functionality
			async deleteMessage(messageId, buttonElement) {
				if (!messageId) return;

				if (!confirm('Are you sure you want to delete this message?')) return;

				try {
					const response = await fetch(`api/messages.php?id=${messageId}`, {
						method: 'DELETE',
						credentials: 'include'
					});
					const result = await response.json();
					if (result.ok) {
						const messageDiv = buttonElement.closest('.message');
						messageDiv.remove();
						// Broadcast delete to peer via WebRTC if connected
						this.broadcastDelete(messageId);
					} else {
						alert('Failed to delete message: ' + (result.error || 'Unknown error'));
					}
				} catch (error) {
					console.error('Delete message error:', error);
					alert('Failed to delete message');
				}
			}

			// Broadcast edit to connected peers
			broadcastEdit(messageId, newContent) {
				if (window.broadcastEdit) {
					window.broadcastEdit(messageId, newContent);
				}
			}

			// Broadcast delete to connected peers
			broadcastDelete(messageId) {
				if (window.broadcastDelete) {
					window.broadcastDelete(messageId);
				}
			}
		}

		// Initialize chat application
		const chatApp = new ChatApp();

		// Update user ID display
		if (window.ALQ_USER_ID) {
			chatApp.updateUserIdDisplay(window.ALQ_USER_ID);
		}

		// Handle window resize
		let resizeTimeout;
		window.addEventListener('resize', () => {
			clearTimeout(resizeTimeout);
			resizeTimeout = setTimeout(() => {
				if (window.innerWidth > 768) {
					const sidebar = document.getElementById('sidebar');
					const chatMain = document.getElementById('chatMain');
					sidebar?.classList.remove('open');
					chatMain?.classList.remove('sidebar-open');
				}
			}, 250);
		});

		// Additional event handlers for integration with existing chat.js
		document.addEventListener('DOMContentLoaded', () => {
			// Dropdown toggle
			const dropdown = document.getElementById('headerDropdown');
			const toggle = document.getElementById('dropdownToggle');
			const menu = document.getElementById('dropdownMenu');
			toggle?.addEventListener('click', (e) => {
				e.preventDefault();
				e.stopPropagation();
				dropdown?.classList.toggle('open');
			});
			document.addEventListener('click', (e) => {
				if (!dropdown?.contains(e.target)) dropdown?.classList.remove('open');
			});

			// Join button handler
			const joinBtn = document.getElementById('joinBtn');
			joinBtn?.addEventListener('click', () => {
				const roomId = document.getElementById('roomId')?.value;
				if (roomId) {
					console.log('Joining room:', roomId);
					// Integrate with your existing join logic
				}
			});

			// Block button handler
			const blockBtn = document.getElementById('blockBtn');
			blockBtn?.addEventListener('click', () => {
				if (chatApp.currentContactId) {
					console.log('Toggle block for contact:', chatApp.currentContactId);
					// Integrate with your existing block logic
				}
			});

			// Mute button handler
			const muteBtn = document.getElementById('muteBtn');
			muteBtn?.addEventListener('click', () => {
				if (chatApp.currentContactId) {
					console.log('Toggle mute for contact:', chatApp.currentContactId);
					// Integrate with your existing mute logic
				}
			});

			// Load older messages handler
			const loadOlder = document.getElementById('loadOlder');
			loadOlder?.addEventListener('click', () => {
				if (chatApp.currentContactId) {
					console.log('Loading older messages for:', chatApp.currentContactId);
					// Integrate with your existing load older messages logic
				}
			});

			// Voice recording handler
			const recBtn = document.getElementById('recBtn');
			recBtn?.addEventListener('click', () => {
				console.log('Start/stop voice recording');
				// Integrate with your existing voice recording logic
			});

			// File upload handler
			const fileInput = document.getElementById('fileInput');
			fileInput?.addEventListener('change', (e) => {
				const files = Array.from(e.target.files);
				if (files.length > 0) {
					console.log('Files selected:', files);
					// Integrate with your existing file upload logic
				}
			});

			// Auto-download toggle handler
			const autoDownload = document.getElementById('autoDownload');
			autoDownload?.addEventListener('change', (e) => {
				console.log('Auto-download toggled:', e.target.checked);
				// Save preference to settings
			});
		});

		// Export chatApp for global access
		window.chatApp = chatApp;
	</script>
	<script src="assets/js/chat.js"></script>
</body>
</html>