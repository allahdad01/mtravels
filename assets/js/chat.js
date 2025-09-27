(() => {
	// Ensure DOM is loaded
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	function init() {
		// DOM elements
		const elements = {
			roomSelect: document.getElementById('roomId'),
			joinBtn: document.getElementById('joinBtn'),
			messagesEl: document.getElementById('messages'),
			textInput: document.getElementById('textInput'),
			sendBtn: document.getElementById('sendBtn'),
			fileBtn: document.getElementById('fileBtn'),
			fileInput: document.getElementById('fileInput'),
			recBtn: document.getElementById('recBtn'),
			myIdEl: document.getElementById('myIdEl'), // Changed from 'myId' to 'myIdEl'
			contactList: document.getElementById('contactList'),
			blockBtn: document.getElementById('blockBtn'),
			muteBtn: document.getElementById('muteBtn'),
			autoDownloadEl: document.getElementById('autoDownload'),
			noticeEl: document.getElementById('notice'),
			loadOlderBtn: document.getElementById('loadOlder'),
			contactSearch: document.getElementById('contactSearch'),
			contactName: document.getElementById('contactName'),
			welcomeScreen: document.querySelector('.welcome-screen'),
			chatScreen: document.querySelector('.chat-screen'),
			contactStatus: document.getElementById('contactStatus')
		};

		// Check for critical DOM elements
		if (!elements.messagesEl || !elements.contactList) {
			console.error('[chat] Critical DOM elements missing:', {
				messagesEl: !!elements.messagesEl,
				contactList: !!elements.contactList
			});
			return;
		}
		if (!elements.myIdEl) {
			console.warn('[chat] myIdEl is missing, some UI features may be unavailable');
		}

		const baseIce = [{ urls: 'stun:stun.l.google.com:19302' }];
		const extraTurn = Array.isArray(window.TURN_RELAYS) ? window.TURN_RELAYS : [];
		const ICE_SERVERS = baseIce.concat(extraTurn);

		let ws, myClientId = null, roomId = null;
		let peerConnections = new Map();
		let dataChannels = new Map();
		let mediaRecorder = null;
		let recordingStartedAt = 0;
		let recordingTimer = null;
		let recordingUIEl = null;
		let discardNextRecording = false;
		let contacts = [];
		let roomIdToContact = new Map();
		let selectedContact = null;
		let prefs = { blocked: new Set(), muted: new Set() };
		let unreadByRoom = new Map();
		let roomIdToElement = new Map();
		let typingPeers = new Set();
		let typingStopTimer = null;
		let lastTypingSentAt = 0;
		let nextMessageId = 1;
		const myMessageIdToElement = new Map();
		let nextBeforeId = 0;

		let MAX_FILE_BYTES = 25 * 1024 * 1024;
		let ALLOWED_MIME_PREFIXES = ['image/', 'video/', 'audio/', 'application/pdf', 'text/'];
		let pageIsVisible = true;
		let windowHasFocus = true;
		let notificationsEnabled = false;

		if (window.CHAT_SETTINGS) {
			MAX_FILE_BYTES = window.CHAT_SETTINGS.max_file_bytes || MAX_FILE_BYTES;
			if (Array.isArray(window.CHAT_SETTINGS.allowed_mime_prefixes)) ALLOWED_MIME_PREFIXES = window.CHAT_SETTINGS.allowed_mime_prefixes;
			if (elements.autoDownloadEl && typeof window.CHAT_SETTINGS.default_auto_download === 'boolean') elements.autoDownloadEl.checked = window.CHAT_SETTINGS.default_auto_download;
		}

		function log(...args) { console.log('[chat]', ...args); }

		function canNotify() {
			return notificationsEnabled && (document.hidden || !windowHasFocus) && 'Notification' in window;
		}

		async function ensureNotificationPermission() {
			try {
				if (!('Notification' in window)) return false;
				if (Notification.permission === 'granted') { notificationsEnabled = true; return true; }
				if (Notification.permission !== 'denied') {
					const res = await Notification.requestPermission();
					notificationsEnabled = (res === 'granted');
					return notificationsEnabled;
				}
				return false;
			} catch { return false; }
		}

		function showMessageNotification({ title = 'New message', body = '', icon = undefined }) {
			if (!canNotify()) return;
			try {
				const n = new Notification(title, { body, icon });
				n.onclick = () => { try { window.focus(); n.close(); } catch {} };
			} catch {}
		}

		function setHeaderStatus(isOnline) {
			if (!elements.contactStatus) return;
			elements.contactStatus.textContent = isOnline ? 'Online' : 'Offline';
			elements.contactStatus.classList.remove('online', 'offline');
			elements.contactStatus.classList.add(isOnline ? 'online' : 'offline');
		}

		function updateHeaderAvatar(contact) {
			const avatar = document.getElementById('chatAvatar');
			if (!avatar) return;
			if (contact && contact.photo) {
				avatar.style.background = 'none';
				avatar.style.borderRadius = '50%';
				avatar.style.overflow = 'hidden';
				avatar.innerHTML = `<img src="${contact.photo}" alt="${contact.name || 'User'}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" />`;
			} else if (contact) {
				const initials = (contact.name || '?').trim().split(/\s+/).map(s => s[0]).slice(0,2).join('').toUpperCase();
				avatar.innerHTML = initials || 'U';
				avatar.style.background = '';
			}
		}

		function showNotice(text) { if (elements.noticeEl) elements.noticeEl.textContent = text || ''; }

		function showTypingIndicator(show) {
			if (!elements.contactStatus) return;
			if (show) {
				if (elements._savedStatusText == null) elements._savedStatusText = elements.contactStatus.textContent || '';
				elements.contactStatus.textContent = 'Typing…';
				elements.contactStatus.classList.remove('online', 'offline');
				elements.contactStatus.classList.add('typing');
			} else {
				if (elements._savedStatusText != null) elements.contactStatus.textContent = elements._savedStatusText;
				elements.contactStatus.classList.remove('typing');
			}
		}

		function createMessageElement(text, who = 'me', timestamp = new Date()) {
			const div = document.createElement('div');
			div.className = `msg p-3 rounded-lg ${who === 'me' ? 'me bg-green-100 self-end' : 'peer bg-gray-100 self-start'}`;

			// Handle reply messages
			let displayText = text;
			let replyContext = null;

			try {
				const parsed = JSON.parse(text);
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
			if (replyContext) {
				const replyDiv = document.createElement('div');
				replyDiv.className = 'reply-context bg-gray-200 p-2 rounded mb-2 border-l-4 border-blue-500 cursor-pointer hover:bg-gray-300 transition-colors';
				replyDiv.onclick = () => window.scrollToReply(replyContext.replyTo);
				replyDiv.innerHTML = `
					<div class="text-xs text-gray-600 mb-1">Replying to:</div>
					<div class="text-sm text-gray-800 truncate">${replyContext.replyText}</div>
				`;
				div.appendChild(replyDiv);
			}

			const content = document.createElement('span');
			content.className = 'message-text';
			content.textContent = displayText;
			const timeSpan = document.createElement('span');
			timeSpan.className = 'text-xs text-gray-500 ml-2';
			timeSpan.textContent = timestamp.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
			div.appendChild(content);
			div.appendChild(timeSpan);
			// Add ticks for sent messages
			if (who === 'me') {
				const ticks = document.createElement('span');
				ticks.className = 'ticks text-xs text-gray-500 ml-2';
				div.appendChild(ticks);
			}

			// Add dropdown menu for all messages
			const actionsDiv = document.createElement('div');
			actionsDiv.className = 'message-actions';
			let dropdownItems = '';

			if (who === 'me') {
				// Options for sent messages
				dropdownItems = `
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
				`;
			} else {
				// Options for received messages
				dropdownItems = `
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
				`;
			}

			actionsDiv.innerHTML = `
				<button class="message-menu-btn" onclick="window.toggleMessageMenu(this)" title="Message options">
					<i class="fas fa-ellipsis-v"></i>
				</button>
				<div class="message-dropdown">
					${dropdownItems}
				</div>
			`;
			div.appendChild(actionsDiv);
			return div;
		}

		function setMessageDelivered(messageId) {
			const el = myMessageIdToElement.get(messageId);
			if (!el) return;
			const ticks = el.querySelector('.ticks');
			if (ticks) {
				ticks.textContent = '✓';
				ticks.classList.remove('text-blue-500');
				ticks.classList.add('text-gray-500');
			}
		}

		function setMessageSeen(messageId) {
			const el = myMessageIdToElement.get(messageId);
			if (!el) return;
			const ticks = el.querySelector('.ticks');
			if (ticks) {
				ticks.textContent = '✓✓';
				ticks.classList.remove('text-gray-500');
				ticks.classList.add('text-blue-500');
			}
		}

		function addMessage(text, who = 'me', timestamp = new Date(), messageId = null) {
			const div = createMessageElement(text, who, timestamp);
			if (messageId) {
				div.setAttribute('data-message-id', messageId);
			}
			elements.messagesEl.appendChild(div);
			elements.messagesEl.scrollTop = elements.messagesEl.scrollHeight;
			if (who === 'me') {
				const ticks = div.querySelector('.ticks');
				if (ticks) {
					ticks.textContent = '✓';
					ticks.classList.remove('text-blue-500');
					ticks.classList.add('text-gray-500');
				}
			}
			return div;
		}

		function renderFileMessage({ blob = null, who = 'peer', filename = 'file', filePath = null, mimeType = '', messageId = null }) {
			const div = document.createElement('div');
			div.className = `msg p-3 rounded-lg ${who === 'me' ? 'me bg-green-100 self-end' : 'peer bg-gray-100 self-start'}`;
			const timeSpan = document.createElement('span');
			timeSpan.className = 'text-xs text-gray-500 ml-2';
			timeSpan.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

			const buildMediaUrl = () => filePath ? `api/download.php?inline=1&file=${encodeURIComponent(filePath)}` : (blob ? URL.createObjectURL(blob) : '');
			const url = buildMediaUrl();

			let type = (mimeType || (blob && blob.type) || '').toLowerCase();
			// Fallback by extension if MIME is missing or generic
			if (!type || type === 'application/octet-stream') {
				const lowerName = (filename || '').toLowerCase();
				if (/(\.png|\.jpg|\.jpeg|\.gif|\.webp|\.bmp|\.svg)$/.test(lowerName)) type = 'image/*';
				else if (/(\.mp3|\.wav|\.ogg|\.m4a|\.weba|\.webm)$/.test(lowerName)) type = 'audio/*';
				else if (/(\.mp4|\.webm|\.ogv|\.mov)$/.test(lowerName)) type = 'video/*';
			}

			let rendered = false;
			if (type.startsWith('image/')) {
				const img = document.createElement('img');
				img.src = url;
				img.alt = filename;
				img.style.borderRadius = '0.5rem';
				div.appendChild(img);
				rendered = true;
			}
			if (!rendered && type.startsWith('audio/')) {
				const audio = document.createElement('audio');
				audio.controls = true;
				audio.src = url;
				div.appendChild(audio);
				rendered = true;
			}
			if (!rendered && type.startsWith('video/')) {
				const video = document.createElement('video');
				video.controls = true;
				video.src = url;
				video.style.maxHeight = '360px';
				div.appendChild(video);
				rendered = true;
			}
			if (!rendered) {
				const isPdf = type === 'application/pdf' || /\.pdf$/i.test(filename || '');
				if (isPdf) {
					const a = document.createElement('a');
					a.href = url;
					a.download = filename || 'file.pdf';
					a.title = filename || 'PDF';
					a.style.display = 'inline-flex';
					a.style.alignItems = 'center';
					a.style.gap = '0.5rem';
					const icon = document.createElement('i');
					icon.className = 'fas fa-file-pdf';
					icon.style.color = '#dc2626';
					icon.style.fontSize = '1.5rem';
					a.appendChild(icon);
					const nameSpan = document.createElement('span');
					nameSpan.textContent = filename || 'PDF';
					nameSpan.style.maxWidth = '240px';
					nameSpan.style.whiteSpace = 'nowrap';
					nameSpan.style.overflow = 'hidden';
					nameSpan.style.textOverflow = 'ellipsis';
					a.appendChild(nameSpan);
					div.appendChild(a);
				} else {
					const a = document.createElement('a');
					a.textContent = `Download ${filename}`;
					a.className = 'text-blue-500 underline';
					a.href = url;
					a.download = filename;
					div.appendChild(a);
				}
			}

			div.appendChild(timeSpan);
			// Add ticks for sent messages
			if (who === 'me') {
				const ticks = document.createElement('span');
				ticks.className = 'ticks text-xs text-gray-500 ml-2';
				div.appendChild(ticks);
			}

			// Add dropdown menu for all file messages
			const actionsDiv = document.createElement('div');
			actionsDiv.className = 'message-actions';
			let dropdownItems = '';

			if (who === 'me') {
				// Options for sent file messages
				dropdownItems = `
					<div class="message-dropdown-item" onclick="window.replyToMessage(this)">
						<i class="fas fa-reply"></i>
						<span>Reply</span>
					</div>
					<div class="message-dropdown-item" onclick="window.forwardMessage(this)">
						<i class="fas fa-share"></i>
						<span>Forward</span>
					</div>
					<div class="message-dropdown-item" onclick="window.downloadFile(this)">
						<i class="fas fa-download"></i>
						<span>Download</span>
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
				`;
			} else {
				// Options for received file messages
				dropdownItems = `
					<div class="message-dropdown-item" onclick="window.replyToMessage(this)">
						<i class="fas fa-reply"></i>
						<span>Reply</span>
					</div>
					<div class="message-dropdown-item" onclick="window.forwardMessage(this)">
						<i class="fas fa-share"></i>
						<span>Forward</span>
					</div>
					<div class="message-dropdown-item" onclick="window.downloadFile(this)">
						<i class="fas fa-download"></i>
						<span>Download</span>
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
				`;
			}

			actionsDiv.innerHTML = `
				<button class="message-menu-btn" onclick="window.toggleMessageMenu(this)" title="Message options">
					<i class="fas fa-ellipsis-v"></i>
				</button>
				<div class="message-dropdown">
					${dropdownItems}
				</div>
			`;
			div.appendChild(actionsDiv);
			if (messageId) {
				div.setAttribute('data-message-id', messageId);
			}
			elements.messagesEl.appendChild(div);
			elements.messagesEl.scrollTop = elements.messagesEl.scrollHeight;

			return { div, url };
		}

		function autoDownloadBlob(blob, filename) {
			const { url } = renderFileMessage({ blob, who: 'peer', filename, mimeType: blob.type });
			const a = document.createElement('a');
			a.href = url;
			a.download = filename;
			document.body.appendChild(a);
			a.click();
			setTimeout(() => { URL.revokeObjectURL(url); a.remove(); }, 5000);
		}

		function validateFile(file) {
			if (file.size > MAX_FILE_BYTES) {
				alert(`File too large. Max ${Math.round(MAX_FILE_BYTES / (1024 * 1024))} MB.`);
				return false;
			}
			if (!ALLOWED_MIME_PREFIXES.some(p => (file.type || '').startsWith(p))) {
				const ok = confirm('This file type may be unsafe. Send anyway?');
				if (!ok) return false;
			}
			return true;
		}

		function incrementUnread(rid) {
			if (!rid) return;
			const current = unreadByRoom.get(rid) || 0;
			unreadByRoom.set(rid, current + 1);
			const el = roomIdToElement.get(rid);
			if (el) renderContactBadge(el, unreadByRoom.get(rid));
			// Notify parent window of unread count change
			updateParentUnreadCount();
		}

		function clearUnread(rid) {
			if (!rid) return;
			unreadByRoom.set(rid, 0);
			const el = roomIdToElement.get(rid);
			if (el) renderContactBadge(el, 0);
			// Notify parent window of unread count change
			updateParentUnreadCount();
		}

		function renderContactBadge(contactEl, count) {
			let badge = contactEl.querySelector('.badge');
			if (!badge) {
				badge = document.createElement('span');
				badge.className = 'badge bg-red-500 text-white rounded-full px-2 py-1 text-xs ml-2';
				const nameEl = contactEl.querySelector('.font-semibold');
				if (nameEl) nameEl.appendChild(badge);
			}
			badge.textContent = count > 0 ? String(count) : '';
			badge.style.display = count > 0 ? 'inline-block' : 'none';
		}

		function connectWS() {
			ws = new WebSocket(window.SIGNALING_URL);
			ws.onopen = () => {
				log('WS connected');
				if (elements.myIdEl) elements.myIdEl.textContent = 'My ID: Connecting...';
			};
			ws.onmessage = async (ev) => {
				let msg;
				try {
					msg = JSON.parse(ev.data);
				} catch (e) {
					log('Invalid WebSocket message:', e);
					return;
				}
				if (!msg.type) return;

				if (msg.type === 'welcome') {
					myClientId = msg.clientId;
					if (elements.myIdEl) elements.myIdEl.textContent = 'My ID: ' + myClientId;
					return;
				}
				if (msg.type === 'peers') {
					for (const peerId of msg.peers) {
						await createOfferTo(peerId);
					}
					return;
				}
				if (msg.type === 'peer-joined') {
					await createOfferTo(msg.clientId);
					return;
				}
				if (msg.type === 'signal') {
					await handleSignal(msg.fromId, msg.data);
					return;
				}
				if (msg.type === 'typing') {
					if (msg.state === 'start') typingPeers.add(msg.clientId);
					else typingPeers.delete(msg.clientId);
					showTypingIndicator(typingPeers.size > 0);
					return;
				}
				if (msg.type === 'peer-left') {
					cleanupPeer(msg.clientId);
					typingPeers.delete(msg.clientId);
					showTypingIndicator(typingPeers.size > 0);
					return;
				}
			};
			ws.onclose = () => {
				log('WS closed');
				if (elements.myIdEl) elements.myIdEl.textContent = 'My ID: Disconnected';
			};
			ws.onerror = (e) => log('WS error:', e);
		}

		function joinRoom(id) {
			if (!ws || ws.readyState !== WebSocket.OPEN) {
				log('WebSocket not ready, cannot join room:', id);
				return;
			}
			roomId = id;
			ws.send(JSON.stringify({ type: 'join', roomId }));
			typingPeers.clear();
			showTypingIndicator(false);
			if (selectedContact) loadHistory(selectedContact.id);
		}

		function sendSignal(targetId, data) {
			if (ws && ws.readyState === WebSocket.OPEN) {
				ws.send(JSON.stringify({ type: 'signal', targetId, data }));
			} else {
				log('Cannot send signal, WebSocket not open');
			}
		}

		function sendTyping(state) {
			if (ws && ws.readyState === WebSocket.OPEN && roomId) {
				ws.send(JSON.stringify({ type: 'typing', state }));
			}
		}

		function createPeer(peerId) {
			if (peerConnections.has(peerId)) return peerConnections.get(peerId);
			const pc = new RTCPeerConnection({ iceServers: ICE_SERVERS });
			peerConnections.set(peerId, pc);
			pc.onicecandidate = (e) => {
				if (e.candidate) sendSignal(peerId, { candidate: e.candidate });
			};
			pc.ondatachannel = (e) => {
				setupDataChannel(peerId, e.channel);
			};
			return pc;
		}

		function setupDataChannel(peerId, channel) {
			dataChannels.set(peerId, channel);
			channel.binaryType = 'arraybuffer';
			const openTimer = setTimeout(() => {
				if (channel.readyState !== 'open') showNotice('Having trouble connecting directly. A TURN relay may be required.');
			}, 10000);
			channel.onopen = () => {
				clearTimeout(openTimer);
				showNotice('');
				log('DataChannel open with', peerId);
				// Assume active conversation is online when any channel opens
				setHeaderStatus(true);
			};
			channel.onmessage = (e) => {
				if (typeof e.data === 'string') {
					try {
						const obj = JSON.parse(e.data);
						if (obj.type === 'text') {
							// Handle reply messages
							let displayText = obj.text;
							try {
								const parsed = JSON.parse(obj.text);
								if (parsed.type === 'reply') {
									displayText = JSON.stringify(parsed);
								}
							} catch {
								// Not a JSON message, use as is
							}

							addMessage(displayText, 'peer', new Date());
							// Notify if not visible
							const contactTitle = selectedContact ? `${selectedContact.role} ${selectedContact.name || ''}`.trim() : 'Contact';
							showMessageNotification({ title: contactTitle || 'New message', body: obj.text });
							for (const [, ch] of dataChannels) {
								if (ch.readyState !== 'open') continue;
								ch.send(JSON.stringify({ type: 'receipt', receipt: 'delivered', id: obj.id }));
								if (selectedContact && roomId === selectedContact?.room_id) {
									ch.send(JSON.stringify({ type: 'receipt', receipt: 'seen', id: obj.id }));
								}
							}
							if (!selectedContact || roomId !== selectedContact?.room_id) incrementUnread(roomId);
						}
						if (obj.type === 'receipt') {
							if (obj.receipt === 'delivered') setMessageDelivered(obj.id);
							if (obj.receipt === 'seen') setMessageSeen(obj.id);
						}
						if (obj.type === 'file-meta') {
							channel._expectedFile = obj;
						}
						if (obj.type === 'message_edit') {
							handleMessageEdit(obj.messageId, obj.content);
						}
						if (obj.type === 'message_delete') {
							handleMessageDelete(obj.messageId);
						}
					} catch {
						addMessage(e.data, 'peer', new Date());
						showMessageNotification({ title: 'New message', body: String(e.data).slice(0, 80) });
						if (!selectedContact || roomId !== selectedContact?.room_id) incrementUnread(roomId);
					}
				} else {
					const meta = channel._expectedFile;
					if (meta) {
						const blob = new Blob([e.data], { type: meta.mimeType || 'application/octet-stream' });
						if (elements.autoDownloadEl?.checked) autoDownloadBlob(blob, meta.name || 'file');
						else renderFileMessage({ blob, who: 'peer', filename: meta.name || 'file', mimeType: meta.mimeType || '' });
						for (const [, ch] of dataChannels) {
							if (ch.readyState !== 'open') continue;
							ch.send(JSON.stringify({ type: 'receipt', receipt: 'delivered', id: meta.id }));
							if (selectedContact && roomId === selectedContact?.room_id) {
								ch.send(JSON.stringify({ type: 'receipt', receipt: 'seen', id: meta.id }));
							}
						}
						if (!selectedContact || roomId !== selectedContact?.room_id) incrementUnread(roomId);
						const contactTitle = selectedContact ? `${selectedContact.role} ${selectedContact.name || ''}`.trim() : 'Contact';
						showMessageNotification({ title: contactTitle || 'New file', body: meta?.name ? `Sent a file: ${meta.name}` : 'Sent a file' });
						channel._expectedFile = null;
					}
				}
			};
			channel.onclose = () => {
				log('DataChannel closed with', peerId);
				// If no open channels remain, mark offline
				const anyOpen = Array.from(dataChannels.values()).some(ch => ch.readyState === 'open');
				if (!anyOpen) setHeaderStatus(false);
			};
		}

		async function createOfferTo(peerId) {
			const pc = createPeer(peerId);
			if (pc.signalingState !== 'stable') {
				log('Peer connection not in stable state, queuing offer for', peerId);
				setTimeout(() => createOfferTo(peerId), 1000);
				return;
			}
			const dc = pc.createDataChannel('chat');
			setupDataChannel(peerId, dc);
			try {
				const offer = await pc.createOffer();
				await pc.setLocalDescription(offer);
				sendSignal(peerId, { sdp: pc.localDescription });
			} catch (e) {
				log('Error creating offer for', peerId, e);
			}
		}

		async function handleSignal(peerId, data) {
			const pc = createPeer(peerId);
			if (data.sdp) {
				try {
					if (data.sdp.type === 'offer' && pc.signalingState !== 'stable') {
						log('Ignoring offer in non-stable state for', peerId, pc.signalingState);
						return;
					}
					if (data.sdp.type === 'answer' && pc.signalingState !== 'have-local-offer') {
						log('Ignoring answer in invalid state for', peerId, pc.signalingState);
						return;
					}
					await pc.setRemoteDescription(new RTCSessionDescription(data.sdp));
					if (data.sdp.type === 'offer') {
						const answer = await pc.createAnswer();
						await pc.setLocalDescription(answer);
						sendSignal(peerId, { sdp: pc.localDescription });
					}
				} catch (e) {
					log('Error handling SDP for', peerId, e);
				}
			}
			if (data.candidate) {
				try {
					await pc.addIceCandidate(new RTCIceCandidate(data.candidate));
				} catch (e) {
					log('Error adding ICE candidate for', peerId, e);
				}
			}
		}

		function cleanupPeer(peerId) {
			const dc = dataChannels.get(peerId);
			if (dc) try { dc.close(); } catch {}
			dataChannels.delete(peerId);
			const pc = peerConnections.get(peerId);
			if (pc) try { pc.close(); } catch {}
			peerConnections.delete(peerId);
		}

		function handleMessageEdit(messageId, newContent) {
			const messageEl = document.querySelector(`.msg[data-message-id="${messageId}"]`);
			if (messageEl) {
				const messageText = messageEl.querySelector('.message-text');
				if (messageText) {
					messageText.textContent = newContent;
				}
			}
		}

		function handleMessageDelete(messageId) {
			const messageEl = document.querySelector(`.msg[data-message-id="${messageId}"]`);
			if (messageEl) {
				messageEl.remove();
			}
		}

		function broadcastJSON(obj) {
			for (const [, channel] of dataChannels) {
				if (channel.readyState === 'open') channel.send(JSON.stringify(obj));
			}
		}

		// Broadcast edit to connected peers
		function broadcastEdit(messageId, newContent) {
			broadcastJSON({
				type: 'message_edit',
				messageId: messageId,
				content: newContent
			});
		}

		// Broadcast delete to connected peers
		function broadcastDelete(messageId) {
			broadcastJSON({
				type: 'message_delete',
				messageId: messageId
			});
		}

		// Make broadcast functions globally available
		window.broadcastEdit = broadcastEdit;
		window.broadcastDelete = broadcastDelete;

		// Update parent window unread count
		function updateParentUnreadCount() {
			const totalUnread = Array.from(unreadByRoom.values()).reduce((sum, count) => sum + count, 0);
			// Send message to parent window if this is an iframe
			if (window.parent && window.parent !== window) {
				window.parent.postMessage({
					type: 'unreadCountUpdate',
					count: totalUnread
				}, '*');
			}
		}

		// Global edit message function
		window.editMessage = function(buttonElement) {
			const messageDiv = buttonElement.closest('.msg');
			const messageText = messageDiv.querySelector('.message-text');
			const messageId = messageDiv.getAttribute('data-message-id');

			if (!messageText || !messageId) return;

			const currentText = messageText.textContent.trim();
			const textarea = document.createElement('textarea');
			textarea.className = 'message-edit-input';
			textarea.value = currentText;
			textarea.rows = Math.max(1, Math.ceil(currentText.length / 50));

			messageText.style.display = 'none';
			messageDiv.classList.add('editing');
			messageDiv.insertBefore(textarea, messageText);

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
							if (window.broadcastEdit) {
								window.broadcastEdit(messageId, newText);
							}
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
				messageDiv.classList.remove('editing');
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
		};

		// Global toggle message menu function
		window.toggleMessageMenu = function(buttonElement) {
			// Close all other dropdowns first
			document.querySelectorAll('.message-dropdown.show').forEach(dropdown => {
				if (dropdown !== buttonElement.nextElementSibling) {
					dropdown.classList.remove('show');
				}
			});

			const dropdown = buttonElement.nextElementSibling;
			dropdown.classList.toggle('show');

			// Close dropdown when clicking outside
			const closeDropdown = (e) => {
				if (!dropdown.contains(e.target) && !buttonElement.contains(e.target)) {
					dropdown.classList.remove('show');
					document.removeEventListener('click', closeDropdown);
				}
			};

			if (dropdown.classList.contains('show')) {
				setTimeout(() => document.addEventListener('click', closeDropdown), 1);
			}
		};

		// Global reply to message function
		window.replyToMessage = function(buttonElement) {
			const messageDiv = buttonElement.closest('.msg');
			const messageId = messageDiv.getAttribute('data-message-id');

			let replyText = '';
			let isFileMessage = false;

			// Check if it's a text message
			const messageText = messageDiv.querySelector('.message-text');
			if (messageText) {
				replyText = messageText.textContent || '';
			} else {
				// For file messages, create appropriate reply text
				isFileMessage = true;
				const img = messageDiv.querySelector('img');
				const video = messageDiv.querySelector('video');
				const audio = messageDiv.querySelector('audio');
				const link = messageDiv.querySelector('a');

				if (img) {
					replyText = '📷 Photo';
				} else if (video) {
					replyText = '🎬 Video';
				} else if (audio) {
					replyText = '🎵 Audio';
				} else if (link) {
					const fileName = link.textContent || link.download || 'File';
					const fileExt = fileName.split('.').pop()?.toLowerCase();
					if (['pdf'].includes(fileExt)) {
						replyText = `📄 ${fileName}`;
					} else {
						replyText = `📎 ${fileName}`;
					}
				} else {
					replyText = '📎 File';
				}
			}

			// Store reply context
			window.replyContext = {
				messageId: messageId,
				text: replyText,
				isFileMessage: isFileMessage
			};

			// Update input placeholder
			const textInput = document.getElementById('textInput');
			if (textInput) {
				textInput.placeholder = `Replying to: ${replyText.slice(0, 50)}${replyText.length > 50 ? '...' : ''}`;
				textInput.focus();
			}

			// Close dropdown
			const dropdown = buttonElement.closest('.message-dropdown');
			dropdown.classList.remove('show');
		};

		// Global scroll to reply function
		window.scrollToReply = function(messageId) {
			const targetMessage = document.querySelector(`.msg[data-message-id="${messageId}"]`);
			if (targetMessage) {
				// Add highlight effect
				targetMessage.classList.add('highlight-reply');
				setTimeout(() => {
					targetMessage.classList.remove('highlight-reply');
				}, 2000);

				// Scroll to the message
				targetMessage.scrollIntoView({
					behavior: 'smooth',
					block: 'center'
				});
			} else {
				console.log('Target message not found for ID:', messageId);
			}
		};


		// Global forward message function
		window.forwardMessage = function(buttonElement) {
			const messageDiv = buttonElement.closest('.msg');
			const messageText = messageDiv.querySelector('.message-text')?.textContent || '';
			const messageId = messageDiv.getAttribute('data-message-id');

			// Check if this is a file message
			const fileLink = messageDiv.querySelector('a');
			const img = messageDiv.querySelector('img');
			const video = messageDiv.querySelector('video');
			const audio = messageDiv.querySelector('audio');

			let isFile = false;
			let fileInfo = {};

			if (fileLink && fileLink.href) {
				isFile = true;
				fileInfo = {
					filename: fileLink.textContent || fileLink.download || 'file',
					filePath: fileLink.href,
					mimeType: 'application/octet-stream'
				};
			} else if (img && img.src) {
				isFile = true;
				fileInfo = {
					filename: img.alt || 'image',
					filePath: img.src,
					mimeType: 'image/*'
				};
			} else if (video && video.src) {
				isFile = true;
				fileInfo = {
					filename: 'video',
					filePath: video.src,
					mimeType: 'video/*'
				};
			} else if (audio && audio.src) {
				isFile = true;
				fileInfo = {
					filename: 'audio',
					filePath: audio.src,
					mimeType: 'audio/*'
				};
			}

			// Store forward context
			window.forwardContext = {
				messageId: messageId,
				text: isFile ? '' : messageText,
				selectedContacts: new Set(),
				isFile: isFile,
				...fileInfo
			};

			// Show forward modal
			window.showForwardModal();

			// Close dropdown
			const dropdown = buttonElement.closest('.message-dropdown');
			dropdown.classList.remove('show');
		};

		// Show forward modal
		window.showForwardModal = function() {
			const modal = document.getElementById('forwardModal');
			const preview = document.getElementById('forwardMessagePreview');
			const contactList = document.getElementById('forwardContactList');

			if (!modal || !preview || !contactList) return;

			// Update preview
			const maxLength = 50;
			const previewText = window.forwardContext.text.length > maxLength
				? window.forwardContext.text.substring(0, maxLength) + '...'
				: window.forwardContext.text;
			preview.textContent = `"${previewText}"`;

			// Load and display contacts
			window.loadForwardContacts();

			// Show modal
			modal.classList.add('show');
		};

		// Close forward modal
		window.closeForwardModal = function() {
			const modal = document.getElementById('forwardModal');
			if (modal) {
				modal.classList.remove('show');
			}
			// Clear selection
			window.forwardContext.selectedContacts.clear();
			window.updateForwardButton();
		};

		// Load contacts for forwarding
		window.loadForwardContacts = async function() {
			try {
				const response = await fetch('api/contacts.php', { credentials: 'include' });
				if (!response.ok) return;

				const data = await response.json();
				const contacts = data.contacts || [];
				const contactList = document.getElementById('forwardContactList');

				if (!contactList) return;

				contactList.innerHTML = contacts.map(contact => `
					<div class="forward-contact-item" data-contact-id="${contact.id}" onclick="window.toggleContactSelection(${contact.id})">
						<div class="forward-contact-checkbox"></div>
						<div class="forward-contact-avatar">${(contact.name || '?').trim().split(/\s+/).map(s => s[0]).slice(0,2).join('').toUpperCase()}</div>
						<div class="forward-contact-info">
							<div class="forward-contact-name">${contact.name || ''}</div>
							<div class="forward-contact-role">${contact.role || ''}</div>
						</div>
					</div>
				`).join('');

			} catch (error) {
				console.error('Error loading forward contacts:', error);
			}
		};

		// Toggle contact selection
		window.toggleContactSelection = function(contactId) {
			const contactItem = document.querySelector(`.forward-contact-item[data-contact-id="${contactId}"]`);
			if (!contactItem) return;

			if (window.forwardContext.selectedContacts.has(contactId)) {
				window.forwardContext.selectedContacts.delete(contactId);
				contactItem.classList.remove('selected');
			} else {
				window.forwardContext.selectedContacts.add(contactId);
				contactItem.classList.add('selected');
			}

			window.updateForwardButton();
		};

		// Update forward button state
		window.updateForwardButton = function() {
			const sendBtn = document.getElementById('forwardSendBtn');
			if (sendBtn) {
				const hasSelection = window.forwardContext.selectedContacts.size > 0;
				sendBtn.disabled = !hasSelection;
				sendBtn.textContent = hasSelection ? `Forward (${window.forwardContext.selectedContacts.size})` : 'Forward';
			}
		};

		// Send forwarded message
		window.sendForwardedMessage = async function() {
			if (window.forwardContext.selectedContacts.size === 0) return;

			const selectedContacts = Array.from(window.forwardContext.selectedContacts);
			let successCount = 0;

			for (const contactId of selectedContacts) {
				try {
					if (window.forwardContext.isFile) {
						// For file messages, we need to download and re-upload the file
						let fileBlob = null;
						let fileName = window.forwardContext.filename || 'forwarded_file';
						let mimeType = window.forwardContext.mimeType || 'application/octet-stream';

						// Try to fetch the file from the filePath
						if (window.forwardContext.filePath) {
							try {
								const response = await fetch(window.forwardContext.filePath);
								if (response.ok) {
									fileBlob = await response.blob();
									// Update MIME type from actual blob
									mimeType = fileBlob.type || mimeType;
								}
							} catch (fetchError) {
								console.error('Error fetching file for forwarding:', fetchError);
							}
						}

						if (fileBlob) {
							// Create FormData and upload the file
							const formData = new FormData();
							const file = new File([fileBlob], fileName, { type: mimeType });
							formData.append('file', file);
							formData.append('to_user_id', String(contactId));

							const uploadResponse = await fetch('api/upload.php', {
								method: 'POST',
								credentials: 'include',
								body: formData
							});

							if (uploadResponse.ok) {
								const uploadData = await uploadResponse.json();
								if (!uploadData.error) {
									successCount++;
								}
							}
						} else {
							// Fallback: send file info as text message
							const fileMessage = JSON.stringify({
								type: 'file',
								name: fileName,
								filePath: window.forwardContext.filePath,
								mimeType: mimeType
							});

							const response = await fetch('api/messages.php', {
								method: 'POST',
								credentials: 'include',
								headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
								body: new URLSearchParams({
									to_user_id: contactId,
									content: fileMessage
								})
							});

							if (response.ok) {
								successCount++;
							}
						}
					} else {
						// For text messages
						const response = await fetch('api/messages.php', {
							method: 'POST',
							credentials: 'include',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: new URLSearchParams({
								to_user_id: contactId,
								content: window.forwardContext.text
							})
						});

						if (response.ok) {
							successCount++;
						}
					}
				} catch (error) {
					console.error('Error forwarding to contact', contactId, error);
				}
			}

			// Show success message
			if (successCount > 0) {
				alert(`Message forwarded to ${successCount} contact${successCount > 1 ? 's' : ''}`);
			} else {
				alert('Failed to forward message');
			}

			// Close modal
			window.closeForwardModal();
		};

		// Global download file function
		window.downloadFile = function(buttonElement) {
			const messageDiv = buttonElement.closest('.msg');

			// Find the file element (img, video, audio, or link)
			const img = messageDiv.querySelector('img');
			const video = messageDiv.querySelector('video');
			const audio = messageDiv.querySelector('audio');
			const link = messageDiv.querySelector('a');

			let downloadUrl = '';
			let filename = 'file';

			if (img && img.src) {
				downloadUrl = img.src;
				filename = 'image';
			} else if (video && video.src) {
				downloadUrl = video.src;
				filename = 'video';
			} else if (audio && audio.src) {
				downloadUrl = audio.src;
				filename = 'audio';
			} else if (link && link.href) {
				downloadUrl = link.href;
				filename = link.download || link.textContent || 'file';
			}

			if (downloadUrl) {
				const a = document.createElement('a');
				a.href = downloadUrl;
				a.download = filename;
				a.style.display = 'none';
				document.body.appendChild(a);
				a.click();
				document.body.removeChild(a);
			}

			// Close dropdown
			const dropdown = buttonElement.closest('.message-dropdown');
			dropdown.classList.remove('show');
		};

		// Global copy message function
		window.copyMessage = function(buttonElement) {
			const messageDiv = buttonElement.closest('.msg');
			let textToCopy = '';

			// Check if it's a text message
			const messageText = messageDiv.querySelector('.message-text');
			if (messageText) {
				textToCopy = messageText.textContent || '';
			} else {
				// For file messages, copy the file name
				const fileLink = messageDiv.querySelector('a');
				if (fileLink) {
					textToCopy = fileLink.textContent || fileLink.href || 'File';
				} else {
					textToCopy = 'File message';
				}
			}

			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(textToCopy).then(() => {
					showCopyNotification();
				}).catch(() => {
					fallbackCopyTextToClipboard(textToCopy);
				});
			} else {
				fallbackCopyTextToClipboard(textToCopy);
			}

			// Close dropdown
			const dropdown = buttonElement.closest('.message-dropdown');
			dropdown.classList.remove('show');
		};

		function showCopyNotification() {
			// Create a temporary notification
			const notification = document.createElement('div');
			notification.textContent = 'Message copied!';
			notification.style.cssText = `
				position: fixed;
				top: 20px;
				right: 20px;
				background: #10b981;
				color: white;
				padding: 0.5rem 1rem;
				border-radius: 0.5rem;
				z-index: 10000;
				font-size: 0.875rem;
			`;
			document.body.appendChild(notification);
			setTimeout(() => notification.remove(), 2000);
		}

		function fallbackCopyTextToClipboard(text) {
			const textArea = document.createElement('textarea');
			textArea.value = text;
			textArea.style.position = 'fixed';
			textArea.style.left = '-999999px';
			textArea.style.top = '-999999px';
			document.body.appendChild(textArea);
			textArea.focus();
			textArea.select();

			try {
				document.execCommand('copy');
				showCopyNotification();
			} catch (err) {
				alert('Failed to copy message');
			}

			textArea.remove();
		}

		// Global edit message function
		window.editMessage = function(buttonElement) {
			const messageDiv = buttonElement.closest('.msg');
			const messageText = messageDiv.querySelector('.message-text');
			const messageId = messageDiv.getAttribute('data-message-id');

			if (!messageText || !messageId) return;

			const currentText = messageText.textContent.trim();
			const textarea = document.createElement('textarea');
			textarea.className = 'message-edit-input';
			textarea.value = currentText;
			textarea.rows = Math.max(1, Math.ceil(currentText.length / 50));

			messageText.style.display = 'none';
			messageDiv.classList.add('editing');
			messageDiv.insertBefore(textarea, messageText);

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
							if (window.broadcastEdit) {
								window.broadcastEdit(messageId, newText);
							}
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
				messageDiv.classList.remove('editing');
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

			// Close dropdown
			const dropdown = buttonElement.closest('.message-dropdown');
			dropdown.classList.remove('show');
		};

		// Global delete message function
		window.deleteMessage = async function(buttonElement) {
			const messageDiv = buttonElement.closest('.msg');
			const messageId = messageDiv.getAttribute('data-message-id');
			const isOwnMessage = messageDiv.classList.contains('me');

			if (!confirm('Are you sure you want to delete this message?')) return;

			// For own messages, delete from database
			if (isOwnMessage && messageId) {
				try {
					const response = await fetch(`api/messages.php?id=${messageId}`, {
						method: 'DELETE',
						credentials: 'include'
					});
					const result = await response.json();
					if (result.ok) {
						messageDiv.remove();
						// Broadcast delete to peer via WebRTC if connected
						if (window.broadcastDelete) {
							window.broadcastDelete(messageId);
						}
					} else {
						alert('Failed to delete message: ' + (result.error || 'Unknown error'));
					}
				} catch (error) {
					console.error('Delete message error:', error);
					alert('Failed to delete message');
				}
			} else {
				// For received messages, just remove from UI (local delete)
				messageDiv.remove();
			}

			// Close dropdown
			const dropdown = buttonElement.closest('.message-dropdown');
			dropdown.classList.remove('show');
		};

		async function persistText(toUserId, content) {
			try {
				const response = await fetch('api/messages.php', {
					method: 'POST',
					credentials: 'include',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: new URLSearchParams({ to_user_id: String(toUserId), content })
				});
				if (response.ok) {
					const data = await response.json();
					return data.id;
				}
			} catch {}
			return null;
		}

		async function sendText(text) {
			let messageContent = text;
			let displayText = text;

			// Handle reply context
			if (window.replyContext) {
				messageContent = JSON.stringify({
					type: 'reply',
					replyTo: window.replyContext.messageId,
					replyText: window.replyContext.text,
					content: text
				});
				displayText = messageContent; // Use the full JSON for display so it renders properly
				// Clear reply context
				window.replyContext = null;
				// Reset input placeholder
				const textInput = document.getElementById('textInput');
				if (textInput) {
					textInput.placeholder = 'Type your message...';
				}
			}

			const localId = nextMessageId++;
			const el = addMessage(displayText, 'me', new Date(), localId);
			myMessageIdToElement.set(localId, el);
			broadcastJSON({ type: 'text', id: localId, text: messageContent });
			if (selectedContact) {
				const serverId = await persistText(selectedContact.id, messageContent);
				if (serverId) {
					el.setAttribute('data-message-id', serverId);
					// Update the map with server ID
					myMessageIdToElement.delete(localId);
					myMessageIdToElement.set(serverId, el);
				}
			}
		}

		async function sendFiles(files) {
			const arr = Array.from(files);
			for (const f of arr) {
				if (!validateFile(f)) continue;
				const formData = new FormData();
				formData.append('file', f);
				formData.append('to_user_id', String(selectedContact.id));
				try {
					const res = await fetch('api/upload.php', {
						method: 'POST',
						credentials: 'include',
						body: formData
					});
					const data = await res.json();
					if (!res.ok || data.error) {
						showNotice('Failed to upload file: ' + (data.error || res.statusText));
						continue;
					}
					// Send file metadata via WebRTC for real-time delivery
					const buf = await f.arrayBuffer();
					const outId = nextMessageId++;
					for (const [, channel] of dataChannels) {
						if (channel.readyState !== 'open') continue;
						channel.send(JSON.stringify({ type: 'file-meta', id: outId, name: f.name, size: f.size, mimeType: f.type }));
						channel.send(buf);
					}
					// Display file message (with preview if possible)
					const r = renderFileMessage({ blob: new Blob([buf], { type: f.type }), who: 'me', filename: f.name, filePath: data.file_path, mimeType: f.type, messageId: outId });
					myMessageIdToElement.set(outId, r.div);
					const ticks = r.div.querySelector('.ticks');
					if (ticks) {
						ticks.textContent = '✓';
						ticks.classList.remove('text-blue-500');
						ticks.classList.add('text-gray-500');
					}
				} catch (e) {
					showNotice('Error uploading file');
					console.error('File upload failed:', e);
				}
			}
		}

		function createRecordingUI() {
			if (recordingUIEl) return;
			const container = document.createElement('div');
			container.className = 'flex items-center gap-3 p-2 bg-red-50 border border-red-200 rounded-lg mb-2';
			const dot = document.createElement('span');
			dot.style.width = '10px';
			dot.style.height = '10px';
			dot.style.borderRadius = '50%';
			dot.style.background = '#ef4444';
			dot.style.animation = 'pulse 1s infinite';
			const timer = document.createElement('span');
			timer.id = 'recTimer';
			timer.className = 'text-sm text-red-700';
			timer.textContent = '00:00';
			const cancelBtn = document.createElement('button');
			cancelBtn.textContent = 'Cancel';
			cancelBtn.className = 'px-2 py-1 text-sm text-red-700 hover:text-red-900';
			cancelBtn.onclick = () => { discardNextRecording = true; stopRecording(); };
			const stopBtn = document.createElement('button');
			stopBtn.textContent = 'Stop';
			stopBtn.className = 'px-3 py-1 text-sm text-white rounded bg-red-500 hover:bg-red-600';
			stopBtn.onclick = () => stopRecording();
			container.appendChild(dot);
			container.appendChild(timer);
			container.appendChild(cancelBtn);
			container.appendChild(stopBtn);
			const inputContainer = elements.chatScreen?.querySelector('.input-container .input-wrapper') || elements.chatScreen;
			if (inputContainer && inputContainer.parentElement) {
				inputContainer.parentElement.insertBefore(container, inputContainer);
				recordingUIEl = container;
			}
		}

		function removeRecordingUI() {
			if (recordingUIEl) { recordingUIEl.remove(); recordingUIEl = null; }
			if (recordingTimer) { clearInterval(recordingTimer); recordingTimer = null; }
		}

		function formatElapsed(ms) {
			const s = Math.floor(ms / 1000);
			const mm = String(Math.floor(s / 60)).padStart(2, '0');
			const ss = String(s % 60).padStart(2, '0');
			return mm + ':' + ss;
		}

		async function startRecording() {
			try {
				const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
				mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/webm' });
				const chunks = [];
				mediaRecorder.ondataavailable = (e) => {
					if (e.data.size) chunks.push(e.data);
				};
				mediaRecorder.onstop = async () => {
					const blob = new Blob(chunks, { type: 'audio/webm' });
					const nowName = `voice-${Date.now()}.webm`;
					const buf = await blob.arrayBuffer();
					// Optionally discard (Cancel)
					if (!discardNextRecording) {
						// Send via WebRTC to peers
						for (const [, channel] of dataChannels) {
							if (channel.readyState !== 'open') continue;
							channel.send(JSON.stringify({ type: 'file-meta', name: nowName, size: buf.byteLength, mimeType: 'audio/webm' }));
							channel.send(buf);
						}
						// Upload to server
						try {
							const formData = new FormData();
							const file = new File([blob], nowName, { type: 'audio/webm' });
							formData.append('file', file);
							if (selectedContact) formData.append('to_user_id', String(selectedContact.id));
							const res = await fetch('api/upload.php', { method: 'POST', credentials: 'include', body: formData });
							let filePath = null;
							if (res.ok) {
								const data = await res.json();
								if (!data.error) filePath = data.file_path || null;
							}
							renderFileMessage({ blob, who: 'me', filename: nowName, filePath, mimeType: 'audio/webm', messageId: nextMessageId++ });
						} catch (e) {
							renderFileMessage({ blob, who: 'me', filename: nowName, mimeType: 'audio/webm' });
						}
					}
					// Cleanup
					stream.getTracks().forEach(t => t.stop());
					removeRecordingUI();
					elements.recBtn.textContent = 'Rec';
					discardNextRecording = false;
				};
				mediaRecorder.start();
				elements.recBtn.textContent = 'Stop';
				recordingStartedAt = Date.now();
				createRecordingUI();
				recordingTimer = setInterval(() => {
					const timerEl = document.getElementById('recTimer');
					if (timerEl) timerEl.textContent = formatElapsed(Date.now() - recordingStartedAt);
				}, 200);
			} catch (e) {
				log('Error starting recording:', e);
				showNotice('Failed to start recording. Please check microphone permissions.');
			}
		}

		function stopRecording() {
			if (mediaRecorder && mediaRecorder.state !== 'inactive') {
				mediaRecorder.stop();
				elements.recBtn.textContent = 'Rec';
			}
		}

		async function loadPrefs() {
			try {
				const res = await fetch('api/chat_prefs.php?action=list', { credentials: 'include' });
				if (!res.ok) return;
				const data = await res.json();
				prefs.blocked = new Set(data.blocked || []);
				prefs.muted = new Set(data.muted || []);
				updatePrefButtons();
			} catch {}
		}

		function updatePrefButtons() {
			if (!selectedContact) {
				elements.blockBtn.disabled = true;
				elements.muteBtn.disabled = true;
				return;
			}
			elements.blockBtn.disabled = false;
			elements.muteBtn.disabled = false;
			elements.blockBtn.textContent = prefs.blocked.has(selectedContact.id) ? 'Unblock' : 'Block';
			elements.muteBtn.textContent = prefs.muted.has(selectedContact.id) ? 'Unmute' : 'Mute';
		}

		async function handleBlockToggle() {
			if (!selectedContact) return;
			const action = prefs.blocked.has(selectedContact.id) ? 'unblock' : 'block';
			try {
				await fetch('api/chat_prefs.php', {
					method: 'POST',
					credentials: 'include',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: new URLSearchParams({ action, target_id: String(selectedContact.id) })
				});
				await loadContacts();
				await loadPrefs();
			} catch {}
		}

		async function handleMuteToggle() {
			if (!selectedContact) return;
			const action = prefs.muted.has(selectedContact.id) ? 'unmute' : 'mute';
			try {
				await fetch('api/chat_prefs.php', {
					method: 'POST',
					credentials: 'include',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: new URLSearchParams({ action, target_id: String(selectedContact.id) })
				});
				await loadPrefs();
			} catch {}
		}

		async function loadContacts() {
			try {
				const res = await fetch('api/contacts.php', { credentials: 'include' });
				if (!res.ok) return;
				const data = await res.json();
				contacts = data.contacts || [];
				renderContacts();
			} catch {}
		}

		function renderContacts() {
			const q = (elements.contactSearch?.value || '').toLowerCase().trim();
			elements.contactList.innerHTML = '';
			elements.roomSelect.innerHTML = '';
			roomIdToContact.clear();
			roomIdToElement.clear();
			const formatPreview = (val) => {
				if (!val) return '';
				let text = String(val);
				try {
					const parsed = JSON.parse(text);
					if (parsed && parsed.type === 'reply') {
						// For reply messages, show the actual reply content
						return parsed.content || 'Reply';
					}
					if (parsed && parsed.type === 'file') {
						const name = parsed.name || 'file';
						const mt = (parsed.mimeType || '').toLowerCase();
						const icon = mt.startsWith('image/') ? '🖼' : (mt.startsWith('audio/') ? '🎵' : (mt.startsWith('video/') ? '🎬' : '📎'));
						return `${icon} ${name}`;
					}
				} catch {}
				text = text.replace(/\s+/g, ' ').trim();
				return text.length > 80 ? text.slice(0, 80) + '…' : text;
			};
			for (const c of contacts) {
				if (q && !(c.role.toLowerCase().includes(q) || (c.agency_name || '').toLowerCase().includes(q))) continue;
				roomIdToContact.set(c.room_id, c);
				const div = document.createElement('div');
				div.className = 'contact p-3 border-b border-gray-100 cursor-pointer hover:bg-gray-50';
				const initials = (c.name || '?').trim().split(/\s+/).map(s => s[0]).slice(0,2).join('').toUpperCase();
				const avatarHtml = c.photo
					? `<img src="${c.photo}" alt="${c.name || 'User'}" style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0;" />`
					: `<div style="width:40px;height:40px;border-radius:50%;background:#2563eb;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;flex-shrink:0;">${initials}</div>`;
				div.innerHTML = `
					<div style="display:flex;gap:12px;align-items:center;">
						${avatarHtml}
						<div style="min-width:0;flex:1;">
							<div class="font-semibold">${(c.name || '').trim()} (${c.role || ''})<span class="badge"></span></div>
							<div class="text-sm text-gray-500">${c.agency_name || ''}</div>
							<div class="text-sm text-gray-400 truncate">${formatPreview(c.lastMessage)}</div>
						</div>
					</div>
				`;
				div.onclick = () => {
					for (const el of elements.contactList.children) el.classList.remove('active');
					div.classList.add('active');
					selectRoom(c.room_id);
					selectedContact = c;
					elements.contactName.textContent = `${c.name || ''} (${c.role || ''})`.trim();
					updateHeaderAvatar(c);
					elements.welcomeScreen.classList.add('hidden');
					elements.chatScreen.classList.remove('hidden');
					// Default to offline until a live connection is established
					setHeaderStatus(false);
					clearUnread(c.room_id);
					updatePrefButtons();
					loadHistory(c.id);
					if (window.innerWidth < 640) {
						document.querySelector('.sidebar')?.classList.remove('open');
						elements.conversation?.classList.remove('hidden');
					}
				};
				elements.contactList.appendChild(div);
				roomIdToElement.set(c.room_id, div);
				const opt = document.createElement('option');
				opt.value = c.room_id;
				opt.textContent = c.role;
				elements.roomSelect.appendChild(opt);
			}
		}

		function markRoomSeen(peerUserId) {
			fetch('api/messages.php', {
				method: 'POST',
				credentials: 'include',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams({ action: 'mark_seen', peer_id: String(peerUserId) })
			}).catch(() => {});
		}

		async function loadHistory(peerUserId) {
			try {
				const res = await fetch(`api/messages.php?peer_id=${encodeURIComponent(String(peerUserId))}&limit=50`, { credentials: 'include' });
				if (!res.ok) return;
				const data = await res.json();
				elements.messagesEl.innerHTML = '';
				for (const m of data.messages || []) {
					const who = (m.from_user_id === window.ALQ_USER_ID) ? 'me' : 'peer';
					const date = new Date(m.created_at);
					const timestamp = isNaN(date) ? new Date() : date;
					try {
						const content = JSON.parse(m.content);
						if (content.type === 'file') {
							const r = renderFileMessage({ blob: null, who, filename: content.name, filePath: content.filePath, mimeType: content.mimeType || '', messageId: m.id });
							if (who === 'me' && m.seen_at) {
								const ticks = r.div.querySelector('.ticks');
								if (ticks) { ticks.textContent = '✓✓'; ticks.classList.remove('text-gray-500'); ticks.classList.add('text-blue-500'); }
							}
						} else if (content.type === 'reply') {
							const el = addMessage(JSON.stringify(content), who, timestamp);
							el.setAttribute('data-message-id', m.id);
							if (who === 'me' && m.seen_at) {
								const ticks = el.querySelector('.ticks');
								if (ticks) { ticks.textContent = '✓✓'; ticks.classList.remove('text-gray-500'); ticks.classList.add('text-blue-500'); }
							}
						} else {
							const el = addMessage(m.content, who, timestamp);
							el.setAttribute('data-message-id', m.id);
							if (who === 'me' && m.seen_at) {
								const ticks = el.querySelector('.ticks');
								if (ticks) { ticks.textContent = '✓✓'; ticks.classList.remove('text-gray-500'); ticks.classList.add('text-blue-500'); }
							}
						}
					} catch {
						const el = addMessage(m.content, who, timestamp);
						el.setAttribute('data-message-id', m.id);
						if (who === 'me' && m.seen_at) {
							const ticks = el.querySelector('.ticks');
							if (ticks) { ticks.textContent = '✓✓'; ticks.classList.remove('text-gray-500'); ticks.classList.add('text-blue-500'); }
						}
					}
				}
				nextBeforeId = data.next_before_id || 0;
				if (elements.loadOlderBtn) elements.loadOlderBtn.disabled = !nextBeforeId;
				elements.messagesEl.scrollTop = elements.messagesEl.scrollHeight;
				// Mark room seen server-side
				markRoomSeen(peerUserId);
			} catch {}
		}
		
		async function loadOlder() {
			if (!selectedContact || !nextBeforeId) return;
			const topBefore = elements.messagesEl.firstChild;
			try {
				const url = `api/messages.php?peer_id=${encodeURIComponent(String(selectedContact.id))}&limit=50&before_id=${encodeURIComponent(String(nextBeforeId))}`;
				const res = await fetch(url, { credentials: 'include' });
				if (!res.ok) return;
				const data = await res.json();
				const frag = document.createDocumentFragment();
				for (const m of data.messages || []) {
					const who = (m.from_user_id === window.ALQ_USER_ID) ? 'me' : 'peer';
					const date = new Date(m.created_at);
					const timestamp = isNaN(date) ? new Date() : date;
					let div;
					try {
						const content = JSON.parse(m.content);
						if (content.type === 'file') {
							div = renderFileMessage({ blob: null, who, filename: content.name, filePath: content.filePath, mimeType: content.mimeType || '', messageId: m.id }).div;
						} else if (content.type === 'reply') {
							div = createMessageElement(JSON.stringify(content), who, timestamp);
							div.setAttribute('data-message-id', m.id);
						} else {
							div = createMessageElement(m.content, who, timestamp);
							div.setAttribute('data-message-id', m.id);
						}
					} catch {
						div = createMessageElement(m.content, who, timestamp);
						div.setAttribute('data-message-id', m.id);
					}
					frag.appendChild(div);
				}
				elements.messagesEl.insertBefore(frag, elements.messagesEl.firstChild);
				nextBeforeId = data.next_before_id || 0;
				if (elements.loadOlderBtn) elements.loadOlderBtn.disabled = !nextBeforeId;
				if (topBefore) topBefore.scrollIntoView();
			} catch {}
		}

		function selectRoom(roomIdVal) {
			for (const opt of elements.roomSelect.options) {
				if (opt.value === roomIdVal) {
					elements.roomSelect.value = roomIdVal;
					break;
				}
			}
			joinRoom(roomIdVal);
		}

		// Handle contact selection from HTML
		window.addEventListener('contactSelected', (e) => {
			const { contactId } = e.detail;
			const contact = contacts.find(c => c.id === contactId);
			if (contact) {
				for (const el of elements.contactList.children) el.classList.remove('active');
				const contactEl = roomIdToElement.get(contact.room_id);
				if (contactEl) contactEl.classList.add('active');
				selectRoom(contact.room_id);
				selectedContact = contact;
				elements.contactName.textContent = `${contact.name || ''} (${contact.role || ''})`.trim();
				updateHeaderAvatar(contact);
				elements.welcomeScreen.classList.add('hidden');
				elements.chatScreen.classList.remove('hidden');
				// Default to offline until a live connection is established
				setHeaderStatus(false);
				clearUnread(contact.room_id);
				updatePrefButtons();
				loadHistory(contact.id);
				if (window.innerWidth < 640) {
					document.querySelector('.sidebar')?.classList.remove('open');
					elements.conversation?.classList.remove('hidden');
				}
				markRoomSeen(contact.id);
			}
		});

		// Event listeners
		if (elements.contactSearch) elements.contactSearch.addEventListener('input', renderContacts);
		if (elements.joinBtn) {
			elements.joinBtn.onclick = () => {
				selectRoom(elements.roomSelect.value);
				selectedContact = roomIdToContact.get(elements.roomSelect.value) || null;
				if (selectedContact) {
					elements.contactName.textContent = `${selectedContact.name || ''} (${selectedContact.role || ''})`.trim();
					updateHeaderAvatar(selectedContact);
					elements.welcomeScreen.classList.add('hidden');
					elements.chatScreen.classList.remove('hidden');
					clearUnread(selectedContact.room_id);
					loadHistory(selectedContact.id);
					markRoomSeen(selectedContact.id);
				}
				updatePrefButtons();
			};
		}
		if (elements.sendBtn) elements.sendBtn.onclick = () => {
			const t = elements.textInput.value.trim();
			if (t) {
				sendText(t);
				elements.textInput.value = '';
			}
		};
		if (elements.fileBtn) {
			// Ensure we don't create multiple triggers
			elements.fileBtn.onclick = (ev) => { ev.preventDefault(); ev.stopPropagation(); elements.fileInput.click(); };
		}
		if (elements.fileInput) elements.fileInput.onchange = () => {
			if (elements.fileInput.files?.length) sendFiles(elements.fileInput.files);
			elements.fileInput.value = '';
		};
		if (elements.recBtn) elements.recBtn.onclick = () => {
			if (!mediaRecorder || mediaRecorder.state === 'inactive') startRecording();
			else stopRecording();
		};
		if (elements.blockBtn) elements.blockBtn.onclick = handleBlockToggle;
		if (elements.muteBtn) elements.muteBtn.onclick = handleMuteToggle;
		if (elements.textInput) elements.textInput.addEventListener('input', () => {
			const now = Date.now();
			if (now - lastTypingSentAt > 1500) {
				lastTypingSentAt = now;
				sendTyping('start');
			}
			if (typingStopTimer) clearTimeout(typingStopTimer);
			typingStopTimer = setTimeout(() => sendTyping('stop'), 2000);
		});
		if (elements.loadOlderBtn) elements.loadOlderBtn.onclick = loadOlder;

		// Visibility and focus
		document.addEventListener('visibilitychange', () => { pageIsVisible = !document.hidden; });
		window.addEventListener('focus', () => { windowHasFocus = true; });
		window.addEventListener('blur', () => { windowHasFocus = false; });
		ensureNotificationPermission();

		// Initialize
		connectWS();
		loadContacts().then(loadPrefs);
	}
})();