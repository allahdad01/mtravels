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
	<link href="assets/css/chat.css" rel="stylesheet">
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
			<!-- Message Search Container -->
			<div class="message-search-container" id="messageSearchContainer">
				<div class="search-input-container">
					<input type="text" class="message-search-input" id="messageSearchInput" placeholder="Search messages...">
				</div>
				<div class="search-results" id="searchResults">
					<div style="padding: 1rem; text-align: center; color: var(--text-secondary);">Start typing to search messages</div>
				</div>
			</div>

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
						<button class="action-button" id="searchBtn" title="Search messages" onclick="window.messageSearch.toggleSearch()">
							<i class="fas fa-search"></i>
						</button>
						<div class="dropdown" id="themeDropdown">
							<button class="action-button" id="themeToggle" title="Theme options">
								<i class="fas fa-palette"></i>
							</button>
							<div class="dropdown-menu" id="themeMenu">
								<div class="dropdown-item" onclick="window.themeManager.toggleTheme()">
									<i class="fas fa-moon"></i>
									<span id="themeText">Dark Mode</span>
								</div>
								<div class="dropdown-item" onclick="window.themeManager.setColorTheme('blue')">
									<i class="fas fa-tint" style="color: #3b82f6;"></i>
									<span>Blue Theme</span>
								</div>
								<div class="dropdown-item" onclick="window.themeManager.setColorTheme('purple')">
									<i class="fas fa-tint" style="color: #8b5cf6;"></i>
									<span>Purple Theme</span>
								</div>
								<div class="dropdown-item" onclick="window.themeManager.setColorTheme('green')">
									<i class="fas fa-tint" style="color: #10b981;"></i>
									<span>Green Theme</span>
								</div>
								<div class="dropdown-item" onclick="window.themeManager.setColorTheme('orange')">
									<i class="fas fa-tint" style="color: #f97316;"></i>
									<span>Orange Theme</span>
								</div>
								<div class="dropdown-item" onclick="window.themeManager.setColorTheme('default')">
									<i class="fas fa-tint" style="color: #4099ff;"></i>
									<span>Default Theme</span>
								</div>
							</div>
						</div>
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

			async loadContacts() {
				try {
					const response = await fetch('api/contacts.php', { credentials: 'include' });
					if (response.ok) {
						const data = await response.json();
						this.contacts = data.contacts || [];
						this.renderContacts(this.contacts);
					}
				} catch (error) {
					console.error('Error loading contacts:', error);
				}
			}

			renderContacts(contacts) {
				const contactList = document.getElementById('contactList');
				if (!contactList) return;

				contactList.innerHTML = contacts.map(contact => {
					// Generate first letter avatar if no photo
					const firstLetter = (contact.name || '?').trim().charAt(0).toUpperCase();
					const avatarHtml = contact.photo
						? `<img src="${contact.photo}" alt="${contact.name || 'User'}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;" />`
						: firstLetter;

					return `
						<div class="contact-item fade-in" data-id="${contact.id}" onclick="chatApp.selectContact('${contact.id}')">
							<div class="contact-avatar">${avatarHtml}</div>
							<div class="contact-info">
								<div class="contact-name">${contact.name}</div>
								<div class="contact-last-message">${contact.lastMessage}</div>
							</div>
							<div class="contact-meta">
								<div class="contact-time">${contact.time}</div>
								${contact.unread > 0 ? `<div class="contact-badge">${contact.unread}</div>` : ''}
							</div>
						</div>
					`;
				}).join('');
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
				if (chatAvatar) {
					// Use first letter avatar for header
					const firstLetter = (contact.name || '?').charAt(0).toUpperCase();
					chatAvatar.textContent = firstLetter;
				}
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

			async loadMessages(contactId) {
				try {
					const response = await fetch(`api/messages.php?peer_id=${encodeURIComponent(contactId)}&limit=50`, { credentials: 'include' });
					if (response.ok) {
						const data = await response.json();
						const messages = data.messages || [];
						this.renderMessages(messages.map(m => ({
							id: m.id,
							text: m.content,
							type: (m.from_user_id === window.ALQ_USER_ID) ? 'outgoing' : 'incoming',
							time: new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
							avatar: (m.from_user_id !== window.ALQ_USER_ID) ? (this.contacts.find(c => c.id == contactId)?.name || '?').charAt(0).toUpperCase() : null
						})));
					}
				} catch (error) {
					console.error('Error loading messages:', error);
				}
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

					// Generate first letter avatar for incoming messages
					const incomingAvatarHtml = message.type === 'incoming'
						? `<div class="message-avatar contact-avatar" style="width: 32px; height: 32px; font-size: 0.875rem;">${(message.avatar || '?').charAt(0).toUpperCase()}</div>`
						: '';

					return `
						<div class="message ${message.type} fade-in" data-message-id="${message.id || ''}">
							${incomingAvatarHtml}
							<div class="message-bubble">
								${replyHtml}
								<div class="message-text">${displayText}</div>
								<div class="message-time">${message.time}</div>
								<div class="message-reactions" id="reactions-${message.id || ''}"></div>
								${message.type === 'outgoing' ? `
									<div class="message-actions">
										<button class="quick-reaction-btn" onclick="window.showReactionPicker(this)" title="React">
											<i class="fas fa-smile"></i>
										</button>
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
											<div class="message-dropdown-item" onclick="window.showReactionPicker(this)">
												<i class="fas fa-smile"></i>
												<span>React</span>
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
										<button class="quick-reaction-btn" onclick="window.showReactionPicker(this)" title="React">
											<i class="fas fa-smile"></i>
										</button>
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
											<div class="message-dropdown-item" onclick="window.showReactionPicker(this)">
												<i class="fas fa-smile"></i>
												<span>React</span>
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

			async sendMessage() {
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

				try {
					const response = await fetch('api/messages.php', {
						method: 'POST',
						credentials: 'include',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: new URLSearchParams({
							to_user_id: this.currentContactId,
							content: message
						})
					});

					if (response.ok) {
						const data = await response.json();
						// Add message to UI with server ID
						this.addMessageToUI({
							id: data.id || Date.now().toString(),
							text: message,
							type: 'outgoing',
							time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
						});
					} else {
						console.error('Failed to send message');
					}
				} catch (error) {
					console.error('Error sending message:', error);
				}
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
					const firstLetter = (message.avatar || '?').charAt(0).toUpperCase();
					messageElement.innerHTML = `
						<div class="message-avatar contact-avatar" style="width: 32px; height: 32px; font-size: 0.875rem;">${firstLetter}</div>
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
							<div class="message-reactions" id="reactions-${message.id || ''}"></div>
							<div class="message-actions">
								<button class="message-action-btn edit" onclick="chatApp.editMessage('${message.id || ''}', this)" title="Edit message">
									<i class="fas fa-edit"></i>
								</button>
								<button class="message-action-btn delete" onclick="chatApp.deleteMessage('${message.id || ''}', this)" title="Delete message">
									<i class="fas fa-trash"></i>
								</button>
							</div>
						</div>
						<div class="message-avatar" style="width: 32px; height: 32px; background: var(--theme-primary) !important; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.875rem;">Me</div>
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

		// Theme management functionality
		window.themeManager = {
			currentTheme: 'light',
			currentColorTheme: 'default',

			init() {
				this.loadSavedPreferences();
				this.setupEventListeners();
			},

			loadSavedPreferences() {
				const savedTheme = localStorage.getItem('chat-theme') || 'light';
				const savedColorTheme = localStorage.getItem('chat-color-theme') || 'default';
				this.setTheme(savedTheme);
				this.setColorTheme(savedColorTheme);
			},

			setTheme(theme) {
				this.currentTheme = theme;
				document.documentElement.setAttribute('data-theme', theme);
				localStorage.setItem('chat-theme', theme);

				const themeIcon = document.getElementById('themeIcon');
				if (themeIcon) {
					themeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
				}
			},

			setColorTheme(colorTheme) {
				this.currentColorTheme = colorTheme;
				document.documentElement.setAttribute('data-color-theme', colorTheme);
				localStorage.setItem('chat-color-theme', colorTheme);
			},

			toggleTheme() {
				const newTheme = this.currentTheme === 'dark' ? 'light' : 'dark';
				this.setTheme(newTheme);
			},

			setupEventListeners() {
				const themeToggle = document.getElementById('themeToggle');
				themeToggle?.addEventListener('click', () => this.toggleTheme());
			}
		};

		// Initialize theme manager
		window.themeManager.init();

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

		// Message reactions functionality
		window.messageReactions = new Map();

		window.showReactionPicker = function(buttonElement) {
			const messageDiv = buttonElement.closest('.msg');
			const messageId = messageDiv.getAttribute('data-message-id');

			// Create reaction picker modal
			const picker = document.createElement('div');
			picker.className = 'reaction-picker';
			picker.style.cssText = `
				position: absolute;
				background: var(--bg-primary);
				border: 1px solid var(--border-color);
				border-radius: 0.5rem;
				padding: 0.5rem;
				box-shadow: 0 10px 20px rgba(0,0,0,0.1);
				z-index: 1000;
				display: flex;
				gap: 0.25rem;
				flex-wrap: wrap;
				max-width: 200px;
			`;

			const reactions = ['👍', '❤️', '😂', '😮', '😢', '😡', '👏', '🔥'];
			reactions.forEach(emoji => {
				const btn = document.createElement('button');
				btn.textContent = emoji;
				btn.style.cssText = `
					background: none;
					border: none;
					font-size: 1.5rem;
					cursor: pointer;
					padding: 0.25rem;
					border-radius: 0.25rem;
					transition: background 0.2s;
				`;
				btn.onmouseover = () => btn.style.background = 'var(--hover-bg)';
				btn.onmouseout = () => btn.style.background = 'none';
				btn.onclick = () => {
					window.addReaction(messageId, emoji);
					picker.remove();
				};
				picker.appendChild(btn);
			});

			// Position the picker
			const rect = buttonElement.getBoundingClientRect();
			picker.style.left = rect.left + 'px';
			picker.style.top = (rect.top - 60) + 'px';

			document.body.appendChild(picker);

			// Close on outside click
			setTimeout(() => {
				document.addEventListener('click', function closePicker(e) {
					if (!picker.contains(e.target)) {
						picker.remove();
						document.removeEventListener('click', closePicker);
					}
				});
			}, 1);
		};

		window.addReaction = function(messageId, emoji) {
			if (!messageId) return;

			const reactionsContainer = document.getElementById(`reactions-${messageId}`);
			if (!reactionsContainer) return;

			// Get current reactions for this message
			let reactions = window.messageReactions.get(messageId) || new Map();

			// Toggle reaction
			const currentCount = reactions.get(emoji) || 0;
			if (currentCount > 0) {
				reactions.set(emoji, currentCount - 1);
				if (reactions.get(emoji) === 0) {
					reactions.delete(emoji);
				}
			} else {
				reactions.set(emoji, 1);
			}

			window.messageReactions.set(messageId, reactions);
			window.updateReactionDisplay(messageId);
		};

		window.updateReactionDisplay = function(messageId) {
			const reactionsContainer = document.getElementById(`reactions-${messageId}`);
			if (!reactionsContainer) return;

			const reactions = window.messageReactions.get(messageId) || new Map();

			if (reactions.size === 0) {
				reactionsContainer.innerHTML = '';
				return;
			}

			let html = '';
			for (const [emoji, count] of reactions) {
				html += `
					<button class="reaction-btn ${count > 0 ? 'active' : ''}" onclick="window.addReaction('${messageId}', '${emoji}')">
						<span>${emoji}</span>
						<span class="reaction-count">${count}</span>
					</button>
				`;
			}
			reactionsContainer.innerHTML = html;
		};

		// Message status update functions
		window.updateMessageStatus = function(messageId, status) {
			const messageEl = document.querySelector(`.msg[data-message-id="${messageId}"]`);
			if (!messageEl || !messageEl._statusElement) return;

			const statusEl = messageEl._statusElement;
			const iconEl = statusEl.querySelector('.status-icon');
			const textEl = statusEl.querySelector('.status-text');

			// Remove all status classes
			statusEl.classList.remove('status-sending', 'status-sent', 'status-delivered', 'status-read', 'status-failed');

			// Update status
			switch (status) {
				case 'sent':
					statusEl.classList.add('status-sent');
					iconEl.textContent = '✓';
					textEl.textContent = 'Sent';
					break;
				case 'delivered':
					statusEl.classList.add('status-delivered');
					iconEl.textContent = '✓✓';
					textEl.textContent = 'Delivered';
					break;
				case 'read':
					statusEl.classList.add('status-read');
					iconEl.textContent = '✓✓';
					textEl.textContent = 'Read';
					break;
				case 'failed':
					statusEl.classList.add('status-failed');
					iconEl.textContent = '✗';
					textEl.textContent = 'Failed';
					break;
				default:
					statusEl.classList.add('status-sending');
					iconEl.textContent = '⏳';
					textEl.textContent = 'Sending';
			}
		};

		// Enhanced typing indicator functionality
		window.showTypingIndicator = function(show, contactName = null) {
			const existingIndicator = document.querySelector('.typing-indicator');
			if (existingIndicator) {
				existingIndicator.remove();
			}

			if (!show) return;

			const messagesContainer = document.getElementById('messages');
			if (!messagesContainer) return;

			const indicator = document.createElement('div');
			indicator.className = 'typing-indicator';
			indicator.innerHTML = `
				<div class="typing-dots">
					<div class="typing-dot"></div>
					<div class="typing-dot"></div>
					<div class="typing-dot"></div>
				</div>
				<span>${contactName || 'Someone'} is typing...</span>
			`;

			messagesContainer.appendChild(indicator);
			messagesContainer.scrollTop = messagesContainer.scrollHeight;

			// Auto-hide after 5 seconds if not manually hidden
			setTimeout(() => {
				if (indicator.parentElement) {
					indicator.remove();
				}
			}, 5000);
		};

		window.hideTypingIndicator = function() {
			const indicator = document.querySelector('.typing-indicator');
			if (indicator) {
				indicator.remove();
			}
		};

		// Message search functionality
		window.messageSearch = {
			isOpen: false,
			messages: [],

			init() {
				const searchBtn = document.getElementById('searchBtn');
				const searchContainer = document.getElementById('messageSearchContainer');
				const searchInput = document.getElementById('messageSearchInput');

				searchBtn?.addEventListener('click', () => {
					this.toggleSearch();
				});

				searchInput?.addEventListener('input', (e) => {
					this.searchMessages(e.target.value);
				});

				searchInput?.addEventListener('keydown', (e) => {
					if (e.key === 'Escape') {
						this.closeSearch();
					}
				});

				// Close on outside click
				document.addEventListener('click', (e) => {
					if (this.isOpen && !searchContainer?.contains(e.target) && e.target !== searchBtn) {
						this.closeSearch();
					}
				});
			},

			toggleSearch() {
				const searchContainer = document.getElementById('messageSearchContainer');
				if (!searchContainer) return;

				this.isOpen = !this.isOpen;
				if (this.isOpen) {
					searchContainer.classList.add('show');
					const searchInput = document.getElementById('messageSearchInput');
					searchInput?.focus();
					this.loadAllMessages();
				} else {
					this.closeSearch();
				}
			},

			closeSearch() {
				const searchContainer = document.getElementById('messageSearchContainer');
				searchContainer?.classList.remove('show');
				this.isOpen = false;
				const searchInput = document.getElementById('messageSearchInput');
				if (searchInput) searchInput.value = '';
				this.clearResults();
			},

			async loadAllMessages() {
				// Load messages from current conversation
				try {
					const contactId = selectedContact?.id;
					if (!contactId) return;

					const response = await fetch(`api/messages.php?peer_id=${encodeURIComponent(contactId)}&limit=1000`, { credentials: 'include' });
					if (response.ok) {
						const data = await response.json();
						this.messages = data.messages || [];
					}
				} catch (error) {
					console.error('Error loading messages for search:', error);
				}
			},

			searchMessages(query) {
				if (!query.trim()) {
					this.clearResults();
					return;
				}

				const results = this.messages.filter(message => {
					const content = this.getMessageContent(message);
					return content.toLowerCase().includes(query.toLowerCase());
				});

				this.displayResults(results, query);
			},

			getMessageContent(message) {
				try {
					const parsed = JSON.parse(message.content);
					if (parsed.type === 'reply') {
						return parsed.content;
					}
					if (parsed.type === 'file') {
						return parsed.name || 'File';
					}
				} catch {
					// Not JSON, use as is
				}
				return message.content;
			},

			displayResults(results, query) {
				const resultsContainer = document.getElementById('searchResults');
				if (!resultsContainer) return;

				if (results.length === 0) {
					resultsContainer.innerHTML = '<div style="padding: 1rem; text-align: center; color: var(--text-secondary);">No messages found</div>';
					return;
				}

				const html = results.slice(0, 20).map(message => {
					const content = this.getMessageContent(message);
					const highlightedContent = this.highlightText(content, query);
					const date = new Date(message.created_at).toLocaleDateString();

					return `
						<div class="search-result-item" onclick="window.messageSearch.scrollToMessage('${message.id}')">
							<div class="search-result-text">${highlightedContent}</div>
							<div class="search-result-meta">${date}</div>
						</div>
					`;
				}).join('');

				resultsContainer.innerHTML = html;
			},

			highlightText(text, query) {
				if (!query) return text;
				const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
				return text.replace(regex, '<span class="search-highlight">$1</span>');
			},

			scrollToMessage(messageId) {
				const messageEl = document.querySelector(`.msg[data-message-id="${messageId}"]`);
				if (messageEl) {
					// Highlight the message temporarily
					messageEl.style.background = 'var(--hover-bg)';
					setTimeout(() => {
						messageEl.style.background = '';
					}, 2000);

					// Scroll to the message
					messageEl.scrollIntoView({
						behavior: 'smooth',
						block: 'center'
					});
				}
				this.closeSearch();
			},

			clearResults() {
				const resultsContainer = document.getElementById('searchResults');
				if (resultsContainer) {
					resultsContainer.innerHTML = '<div style="padding: 1rem; text-align: center; color: var(--text-secondary);">Start typing to search messages</div>';
				}
			}
		};

		// Initialize message search
		window.messageSearch.init();

		// Message preview functionality
		window.messagePreview = {
			currentPreview: null,

			showPreview(url, element) {
				this.hidePreview();

				// Create preview element
				const preview = document.createElement('div');
				preview.className = 'message-preview';
				preview.innerHTML = `
					<div class="preview-content">
						<div style="text-align: center; color: var(--text-secondary);">
							<i class="fas fa-spinner fa-spin"></i>
							<span style="margin-left: 0.5rem;">Loading preview...</span>
						</div>
					</div>
				`;

				document.body.appendChild(preview);
				this.currentPreview = preview;

				// Position the preview
				this.positionPreview(preview, element);

				// Fetch preview data
				this.fetchPreview(url);
			},

			async fetchPreview(url) {
				try {
					// Fetch actual preview data from a service
					const response = await fetch(`https://api.linkpreview.net/?key=YOUR_API_KEY&q=${encodeURIComponent(url)}`);
					const data = await response.json();

					if (this.currentPreview) {
						this.currentPreview.innerHTML = `
							<div class="preview-content">
								${data.image ? `<img src="${data.image}" alt="Preview" class="preview-image" />` : ''}
								<div class="preview-title">${data.title || 'Link Preview'}</div>
								<div class="preview-description">${data.description || 'Click to visit this website'}</div>
								<a href="${url}" target="_blank" class="preview-link">${url}</a>
							</div>
						`;
					}
				} catch (error) {
					console.error('Error fetching preview:', error);
					this.hidePreview();
				}
			},


			positionPreview(preview, element) {
				const rect = element.getBoundingClientRect();
				const previewRect = preview.getBoundingClientRect();

				let left = rect.left;
				let top = rect.bottom + 5;

				// Adjust if preview goes off screen
				if (left + previewRect.width > window.innerWidth) {
					left = window.innerWidth - previewRect.width - 10;
				}

				if (top + previewRect.height > window.innerHeight) {
					top = rect.top - previewRect.height - 5;
				}

				preview.style.left = left + 'px';
				preview.style.top = top + 'px';
			},

			hidePreview() {
				if (this.currentPreview) {
					this.currentPreview.remove();
					this.currentPreview = null;
				}
			}
		};

		// Add link preview detection to messages
		document.addEventListener('mouseover', (e) => {
			if (e.target.tagName === 'A' && e.target.href) {
				const link = e.target;
				// Debounce preview showing
				clearTimeout(window.previewTimeout);
				window.previewTimeout = setTimeout(() => {
					window.messagePreview.showPreview(link.href, link);
				}, 500);
			}
		});

		document.addEventListener('mouseout', (e) => {
			if (e.target.tagName === 'A') {
				clearTimeout(window.previewTimeout);
				setTimeout(() => {
					if (!document.querySelector('.message-preview:hover')) {
						window.messagePreview.hidePreview();
					}
				}, 100);
			}
		});

		// Export chatApp for global access
		window.chatApp = chatApp;
	</script>
	<script src="assets/js/chat.js"></script>
</body>
</html>