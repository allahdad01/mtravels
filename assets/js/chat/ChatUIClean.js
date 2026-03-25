/**
 * ChatUI - Clean UI management using Bootstrap 5
 */
class ChatUI {
    constructor() {
        this.currentContact = null;
        this.elements = this.cacheElements();
    }

    cacheElements() {
        return {
            sidebar: document.getElementById('sidebar'),
            chatArea: document.getElementById('chatArea'),
            contactList: document.getElementById('contactList'),
            contactSearch: document.getElementById('contactSearch'),
            backButton: document.getElementById('backButton'),

            chatHeader: document.getElementById('chatHeader'),
            inputArea: document.getElementById('inputArea'),
            welcomeScreen: document.getElementById('welcomeScreen'),
            messagesContainer: document.getElementById('messagesContainer'),

            contactName: document.getElementById('contactName'),
            contactStatus: document.getElementById('contactStatus'),
            chatAvatar: document.getElementById('chatAvatar'),

            messageInput: document.getElementById('messageInput'),
            sendBtn: document.getElementById('sendBtn'),
            
            // Lightbox elements
            lightboxModal: document.getElementById('imageLightboxModal'),
            lightboxImage: document.getElementById('lightboxImage'),
            lightboxFileName: document.getElementById('lightboxFileName'),
            lightboxClose: document.getElementById('lightboxClose'),
            lightboxOverlay: document.getElementById('lightboxOverlay'),
        };
    }

    // Track message IDs for status updates
    messageIdToElement = new Map();

    init(chatManager) {
        this.chatManager = chatManager;
        this.bindEvents();
    }

    bindEvents() {
        // Lightbox handlers
        this.elements.lightboxClose?.addEventListener('click', () => {
            this.closeLightbox();
        });

        this.elements.lightboxOverlay?.addEventListener('click', () => {
            this.closeLightbox();
        });

        // Close lightbox on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.elements.lightboxModal && !this.elements.lightboxModal.classList.contains('hidden')) {
                this.closeLightbox();
            }
        });

        // Back button
        this.elements.backButton?.addEventListener('click', () => {
            this.showSidebar();
        });

        // Search
        this.elements.contactSearch?.addEventListener('input', (e) => {
            this.filterContacts(e.target.value);
        });

        // Drag and drop for file upload
        this.elements.messagesContainer?.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.elements.messagesContainer.style.backgroundColor = '#f0f0f0';
        });

        this.elements.messagesContainer?.addEventListener('dragleave', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.elements.messagesContainer.style.backgroundColor = '';
        });

        this.elements.messagesContainer?.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.elements.messagesContainer.style.backgroundColor = '';

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const fileInput = document.getElementById('fileUploadBtn');
                if (fileInput) {
                    fileInput.files = files;
                    const event = new Event('change', { bubbles: true });
                    fileInput.dispatchEvent(event);
                }
            }
        });

        // Message input auto-resize
        this.elements.messageInput?.addEventListener('input', (e) => {
            e.target.style.height = 'auto';
            e.target.style.height = Math.min(e.target.scrollHeight, 120) + 'px';

            // Typing indicator
            window.dispatchEvent(new CustomEvent('userTyping'));
        });

        // Send button
        this.elements.sendBtn?.addEventListener('click', () => {
            window.dispatchEvent(new CustomEvent('sendMessage'));
        });

        // Enter to send
        this.elements.messageInput?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                window.dispatchEvent(new CustomEvent('sendMessage'));
            }
        });

        // Emoji picker
        const emojiBtn = document.getElementById('emojiBtn');
        const emojiPicker = document.getElementById('emojiPicker');

        emojiBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            emojiPicker?.classList.toggle('hidden');
        });

        emojiPicker?.querySelectorAll('.emoji-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const emoji = btn.textContent;
                const input = this.elements.messageInput;
                input.value += emoji;
                input.focus();
                emojiPicker?.classList.add('hidden');
            });
        });

        // Close emoji picker on outside click
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.emoji-picker-btn')) {
                emojiPicker?.classList.add('hidden');
            }
        });

        // Voice message playback handler
        this.elements.messagesContainer?.addEventListener('click', (e) => {
            const playBtn = e.target.closest('.voice-play-btn');
            if (playBtn) {
                e.preventDefault();
                e.stopPropagation();
                this.handleVoiceMessagePlayback(playBtn);
            }
        });
    }

    /**
     * Handle voice message playback
     */
    handleVoiceMessagePlayback(button) {
        const url = button.dataset.url;
        if (!url || url === '#') {
            alert('Voice message URL not available');
            return;
        }

        try {
            const icon = button.querySelector('i');
            const voicePlayer = button.closest('.voice-player');
            let audio = button._voiceAudio;

            if (!audio) {
                audio = new Audio(url);
                button._voiceAudio = audio;
                const messageId = voicePlayer?.dataset.messageId;
                const waveformContainer = voicePlayer?.querySelector('.voice-waveform-container');

                audio.addEventListener('play', () => {
                    icon.className = 'fas fa-pause';
                    button.classList.add('playing');
                    
                    // Stop other playing audios
                    document.querySelectorAll('.voice-play-btn.playing').forEach(btn => {
                        if (btn !== button && btn._voiceAudio) {
                            btn._voiceAudio.pause();
                        }
                    });

                    // Start audio visualization with progress tracking
                    if (window.audioVisualization && messageId && waveformContainer) {
                        window.audioVisualization.initVisualization(messageId, audio, waveformContainer);
                        // Store audio element reference for progress tracking
                        window.audioVisualization._audioElements = window.audioVisualization._audioElements || {};
                        window.audioVisualization._audioElements[messageId] = audio;
                    }
                });

                audio.addEventListener('pause', () => {
                    icon.className = 'fas fa-play';
                    button.classList.remove('playing');
                    
                    // Stop visualization
                    if (window.audioVisualization && messageId) {
                        window.audioVisualization.stopVisualization(messageId);
                    }
                });

                audio.addEventListener('ended', () => {
                    icon.className = 'fas fa-play';
                    button.classList.remove('playing');
                    
                    // Stop visualization and reset
                    if (window.audioVisualization && messageId) {
                        window.audioVisualization.stopVisualization(messageId);
                        window.audioVisualization.resetVisualization(waveformContainer);
                    }
                });

                audio.addEventListener('error', (e) => {

                    alert('Failed to play voice message');
                    icon.className = 'fas fa-play';
                    button.classList.remove('playing');
                });
                
                // Update progress bar on timeupdate
                audio.addEventListener('timeupdate', () => {
                    this.updateVoiceProgress(voicePlayer, audio);
                });
                
                // Update when metadata is loaded
                audio.addEventListener('loadedmetadata', () => {
                    this.updateVoiceProgress(voicePlayer, audio);
                });
            }
            
            // Setup seeking on waveform container
            const waveformContainer = voicePlayer?.querySelector('.voice-waveform-container');
            if (waveformContainer && !waveformContainer._seekListener) {
                waveformContainer._seekListener = (e) => {
                    if (!audio.duration) return;
                    const rect = waveformContainer.getBoundingClientRect();
                    const clickX = e.clientX - rect.left;
                    const percentage = Math.min(Math.max(clickX / rect.width, 0), 1);
                    audio.currentTime = percentage * audio.duration;
                };
                waveformContainer.addEventListener('click', waveformContainer._seekListener);
                waveformContainer.style.cursor = 'pointer';
            }
            
            // Fallback: Setup seeking on progress bar if it exists
            const progressBar = voicePlayer?.querySelector('.voice-progress-bar');
            if (progressBar && !progressBar._seekListener) {
                progressBar._seekListener = (e) => {
                    if (!audio.duration) return;
                    const rect = progressBar.getBoundingClientRect();
                    const clickX = e.clientX - rect.left;
                    const percentage = Math.min(Math.max(clickX / rect.width, 0), 1);
                    audio.currentTime = percentage * audio.duration;
                };
                progressBar.addEventListener('click', progressBar._seekListener);
                progressBar.style.cursor = 'pointer';
            }

            if (audio.paused) {
                const playPromise = audio.play();
                if (playPromise !== undefined) {
                    playPromise.catch(error => {

                        alert('Failed to play voice message');
                    });
                }
            } else {
                audio.pause();
            }
        } catch (error) {

            alert('Error playing voice message: ' + error.message);
        }
    }
    
    /**
     * Update voice message progress bar and timer
     */
    updateVoiceProgress(voicePlayer, audio) {
        if (!voicePlayer) return;
        if (!audio || !audio.duration) return;
        
        const waveformContainer = voicePlayer.querySelector('.voice-waveform-container');
        const currentTimeEl = voicePlayer.querySelector('.voice-current-time');
        
        // Update waveform progress
        if (waveformContainer) {
            const progress = (audio.currentTime / audio.duration) * 100;
            
            // Update all bars
            const bars = waveformContainer.querySelectorAll('.waveform-bar');
            bars.forEach((bar, index) => {
                const barProgress = (index / bars.length) * 100;
                if (barProgress <= progress) {
                    bar.classList.add('played');
                } else {
                    bar.classList.remove('played');
                }
            });
        }
        
        // Update current time display
        if (currentTimeEl) {
            const timeStr = VoiceRecorder.formatTime(Math.floor(audio.currentTime));
            currentTimeEl.textContent = timeStr;
        }
    }

    formatRole(role) {
        const roleMap = {
            'tenant_super_admin': 'Owner',
            'admin': 'Admin',
            'manager': 'Manager',
            'staff': 'Staff'
        };
        return roleMap[role] || role;
    }

    openImageLightbox(imageUrl, fileName) {
        if (!this.elements.lightboxModal) return;
        
        this.elements.lightboxImage.src = imageUrl;
        this.elements.lightboxFileName.textContent = fileName;
        this.elements.lightboxModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    closeLightbox() {
        if (!this.elements.lightboxModal) return;
        
        this.elements.lightboxModal.classList.add('hidden');
        document.body.style.overflow = '';
    }

    renderContactItem(contact) {
         const firstLetter = (contact.name || '?').charAt(0).toUpperCase();
         // Construct photo URL - handle both plain filenames and full paths
         let photoUrl = null;
         if (contact.photo) {
             const photo = this.escape(contact.photo);
             // If photo already contains a path, use as-is; otherwise prepend assets/images/user/
             photoUrl = photo.includes('/') ? photo : `assets/images/user/${photo}`;
         }
         const avatar = photoUrl
             ? `<img src="${photoUrl}" alt="${this.escape(contact.name)}">`
             : firstLetter;

        const onlineIndicator = contact.online
            ? '<div class="online-indicator"></div>'
            : '';

        const unreadBadge = contact.unread > 0
            ? `<div class="badge-unread">${contact.unread}</div>`
            : '';

        const displayRole = contact.role ? this.formatRole(contact.role) : '';

        return `
            <div class="contact-item" data-id="${contact.id}" data-user-type="${contact.user_type || 'user'}" role="button">
                <div class="contact-avatar">
                    ${avatar}
                    ${onlineIndicator}
                </div>
                <div class="contact-info">
                    <p class="contact-name">${this.escape(contact.name)}</p>
                    <p class="contact-agency">${this.escape(contact.agency_name || '')} ${contact.branch_name ? '• ' + this.escape(contact.branch_name) : ''} ${displayRole ? '• ' + this.escape(displayRole) : ''}</p>
                    <p class="contact-message">${this.extractMessagePreview(contact.lastMessage)}</p>
                </div>
                <div class="contact-meta">
                    <span class="contact-time">${contact.time || ''}</span>
                    ${unreadBadge}
                </div>
            </div>
        `;
    }

    renderContacts(contacts, groups = []) {
        // Group contacts by tenant
        const groupedByTenant = {};
        contacts.forEach(contact => {
            const tenantId = contact.tenant_id;
            const tenantName = contact.tenant_name || 'Unknown Tenant';
            if (!groupedByTenant[tenantId]) {
                groupedByTenant[tenantId] = { name: tenantName, contacts: [], groups: [] };
            }
            groupedByTenant[tenantId].contacts.push(contact);
        });

        // Add groups to the first tenant (all groups belong to user's current tenant)
        if (groups.length > 0) {
            const firstTenant = Object.keys(groupedByTenant)[0];
            if (firstTenant) {
                groupedByTenant[firstTenant].groups = groups;
            }
        }

        // Build HTML with collapsible sections
        let html = '';
        for (const [tenantId, group] of Object.entries(groupedByTenant)) {
            const isCollapsed = localStorage.getItem(`chat-tenant-collapsed-${tenantId}`) === 'true';
            const arrowIcon = isCollapsed ? '▶' : '▼';
            const contactsDisplay = isCollapsed ? 'none' : 'block';

            html += `
                <div class="tenant-group">
                    <div class="tenant-header" data-tenant-id="${tenantId}" role="button">
                        <span class="tenant-arrow">${arrowIcon}</span>
                        <span class="tenant-name">${this.escape(group.name)}</span>
                        <span class="tenant-count">(${group.contacts.length}${group.groups?.length > 0 ? '+' + group.groups.length : ''})</span>
                    </div>
                    <div class="tenant-contacts" style="display: ${contactsDisplay}">
                        ${group.contacts.map(c => this.renderContactItem(c)).join('')}
                        ${group.groups?.length > 0 ? '<div class="group-section"><div style="padding: 8px 16px; font-weight: 600; color: #666; font-size: 12px; text-transform: uppercase;">Groups</div>' + group.groups.map(g => this.renderGroupItem(g)).join('') + '</div>' : ''}
                    </div>
                </div>
            `;
        }

        this.elements.contactList.innerHTML = html;

        // Add click listeners for tenant headers (collapse/expand)
        this.elements.contactList.querySelectorAll('.tenant-header').forEach(header => {
            header.addEventListener('click', () => {
                const tenantId = header.getAttribute('data-tenant-id');
                const contactsDiv = header.nextElementSibling;
                const arrow = header.querySelector('.tenant-arrow');
                
                const isCurrentlyCollapsed = contactsDiv.style.display === 'none';
                contactsDiv.style.display = isCurrentlyCollapsed ? 'block' : 'none';
                arrow.textContent = isCurrentlyCollapsed ? '▼' : '▶';
                
                // Save state in localStorage
                localStorage.setItem(`chat-tenant-collapsed-${tenantId}`, !isCurrentlyCollapsed);
            });
        });

        // Add click listeners for contact items (exclude group items)
        this.elements.contactList.querySelectorAll('.contact-item:not(.group-item)').forEach(item => {
            item.addEventListener('click', () => {
                const contactId = parseInt(item.getAttribute('data-id'), 10);
                const userType = item.getAttribute('data-user-type') || 'user';
                window.dispatchEvent(new CustomEvent('contactSelected', {
                    detail: { contactId, userType }
                }));
            });
        });

        // Add click listeners for group items
        this.elements.contactList.querySelectorAll('.group-item').forEach(item => {
            item.addEventListener('click', () => {
                const groupId = parseInt(item.getAttribute('data-group-id'), 10);
                window.dispatchEvent(new CustomEvent('groupSelected', {
                    detail: { groupId }
                }));
            });
        });
    }

    renderGroupItem(group) {
         const icon = '👥';
         let avatarContent = '';
         
         if (group.profile_pic) {
             // Display group image if available
             avatarContent = `<img src="${this.escape(group.profile_pic)}" alt="${this.escape(group.group_name)}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;" onerror="this.parentElement.innerHTML='<span>${icon}</span>'; this.parentElement.style.background='#667eea';">`;
         } else {
             // Display icon if no image
             avatarContent = `<span>${icon}</span>`;
         }
         
         // Extract last message preview and timestamp
         const lastMessage = group.lastMessage ? this.extractMessagePreview(group.lastMessage) : `${group.member_count} member${group.member_count !== 1 ? 's' : ''}`;
         const time = group.time ? group.time : '';
         
         return `
             <div class="group-item contact-item" data-group-id="${group.id}" role="button" style="cursor: pointer;">
                 <div class="contact-avatar" style="background: #667eea;">
                     ${avatarContent}
                 </div>
                 <div class="contact-info">
                     <p class="contact-name">${this.escape(group.group_name)}</p>
                     <p class="contact-message" style="font-size: 12px; color: #999; margin: 4px 0 0 0;">${this.escape(lastMessage)}</p>
                 </div>
                 ${time ? `<span class="contact-time" style="font-size: 11px; color: #ccc;">${time}</span>` : ''}
             </div>
         `;
     }

    extractMessagePreview(messageText) {
         if (!messageText) return 'No messages yet';

         try {
             // Try to parse as JSON if it looks like JSON
             if (messageText.startsWith('{') || messageText.startsWith('[')) {
                 const data = JSON.parse(messageText);
                 
                 // Handle voice messages
                 if (data.type === 'voice') {
                     const duration = data.duration ? this.formatDuration(data.duration) : '0:00';
                     return `🎤 Voice message (${duration})`;
                 }
                 
                 // Handle file messages
                 if (data.type === 'file' && data.filename) {
                     const fileIcon = this.getFileIcon(data.mimetype || '');
                     return `${fileIcon} ${this.escape(data.filename)}`;
                 }
                 
                 // Handle image messages
                 if (data.type === 'image') {
                     return '🖼️ Image';
                 }
                 
                 // Handle video messages
                 if (data.type === 'video') {
                     return '🎥 Video';
                 }
                 
                 // Handle generic content/text fields
                 if (data.content) return this.escape(data.content);
                 if (data.text) return this.escape(data.text);
             }
         } catch (e) {
             // Not JSON, return as-is
         }

         return this.escape(messageText);
     }
     
     /**
      * Format duration in seconds to MM:SS
      */
     formatDuration(seconds) {
         const mins = Math.floor(seconds / 60);
         const secs = seconds % 60;
         return `${mins}:${secs.toString().padStart(2, '0')}`;
     }

    getFileIcon(mimeType) {
        if (!mimeType) return '📄';
        if (mimeType.startsWith('image/')) return '🖼️';
        if (mimeType.startsWith('video/')) return '🎥';
        if (mimeType.startsWith('audio/')) return '🔊';
        if (mimeType.includes('pdf')) return '📕';
        if (mimeType.includes('word') || mimeType.includes('document')) return '📝';
        if (mimeType.includes('sheet') || mimeType.includes('excel')) return '📊';
        if (mimeType.includes('presentation') || mimeType.includes('powerpoint')) return '🎪';
        if (mimeType.includes('zip') || mimeType.includes('archive') || mimeType.includes('compress')) return '🗜️';
        return '📎';
    }

    renderMessageContent(msg) {
         // Handle voice messages - check multiple fields for robustness
         if (msg.messageType === 'voice' || msg.type === 'voice') {
             const duration = msg.duration ? VoiceRecorder.formatTime(msg.duration) : '0:00';
             const url = msg.url || '#';
             const messageId = msg.id || Math.random();



             return `
                  <div class="voice-message-box">
                      <button class="voice-play-btn" data-message-id="${messageId}" ${url === '#' ? 'disabled' : ''} data-url="${url}" title="${url === '#' ? 'Voice message URL not available' : 'Play voice message'}">
                          <i class="fas fa-play"></i>
                      </button>
                      <div class="waveform-wrapper" id="waveform-${messageId}"></div>
                      <span class="voice-time" id="time-${messageId}">0:00</span>
                  </div>
              `;
         }

        // Try to parse as JSON to detect file/special message types
        try {
            if (typeof msg.text === 'string' && (msg.text.startsWith('{') || msg.text.startsWith('['))) {
                const data = JSON.parse(msg.text);

                // Handle voice messages stored as JSON
                if (data.type === 'voice' && data.url) {
                    const url = data.url || '#';
                    const duration = data.duration || 0;
                    const messageId = msg.id || Math.random();
                    const isOwn = msg.type === 'outgoing';



                    // Use enhanced voice player
                    return VoiceMessageEnhanced.createVoicePlayer(messageId, url, duration, isOwn);
                }

                // Handle file messages
                if (data.type === 'file' && data.filePath) {
                    const fileSize = data.size ? (data.size / 1024 / 1024).toFixed(2) : '?';
                    const fileName = this.escape(data.name || 'File');
                    const filePath = this.escape(data.filePath);
                    const downloadUrl = `uploads/files/${filePath}`;
                    const mimeType = data.mimeType || '';
                    const fileIcon = this.getFileIcon(mimeType);

                    // Check if it's an image
                    if (mimeType.startsWith('image/')) {
                        return `
                            <div class="whatsapp-image-preview">
                                <img src="${downloadUrl}" alt="${fileName}" class="preview-image" onclick="window.chatApp.ui.openImageLightbox('${downloadUrl}', '${fileName}')" style="cursor: pointer;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="file-fallback" style="display:none;">
                                    <div style="font-size: 2.5em;">${fileIcon}</div>
                                    <div style="margin-top: 8px; font-size: 0.9em;">${fileName}</div>
                                </div>
                            </div>
                        `;
                    }

                    // Check if it's a video
                    if (mimeType.startsWith('video/')) {
                        return `
                            <div class="whatsapp-video-preview">
                                <video controls>
                                    <source src="${downloadUrl}" type="${mimeType}">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        `;
                    }

                    // Check if it's audio
                    if (mimeType.startsWith('audio/')) {
                        return `
                            <div class="whatsapp-audio-preview">
                                <audio controls style="width: 100%;">
                                    <source src="${downloadUrl}" type="${mimeType}">
                                    Your browser does not support the audio tag.
                                </audio>
                            </div>
                        `;
                    }

                    // For other files, show WhatsApp-like file card
                    const formatFileSize = (size) => {
                        if (size < 1024) return size + ' B';
                        if (size < 1024 * 1024) return (size / 1024).toFixed(1) + ' KB';
                        return (size / 1024 / 1024).toFixed(1) + ' MB';
                    };
                    const displaySize = formatFileSize(data.size || 0);

                    return `
                        <div class="whatsapp-file-card">
                            <div class="file-card-icon">${fileIcon}</div>
                            <div class="file-card-content">
                                <div class="file-card-name" title="${fileName}">${fileName}</div>
                                <div class="file-card-size">${displaySize}</div>
                            </div>
                            <a href="${downloadUrl}" download title="Download ${fileName}" class="file-card-download">
                                <i class="fas fa-download"></i>
                            </a>
                        </div>
                    `;
                }

                // Handle reply messages - show the content
                if (data.type === 'reply' && data.content) {
                    return this.escape(data.content);
                }
            }
        } catch (e) {

        }

        // Regular text message
        return this.escape(msg.text);
    }

    filterContacts(query) {
        const filtered = this.chatManager.contacts.filter(c =>
            c.name.toLowerCase().includes(query.toLowerCase()) ||
            (c.lastMessage || '').toLowerCase().includes(query.toLowerCase())
        );
        this.renderContacts(filtered);
    }

    showChat(contact) {
        this.currentContact = contact;

        // Update header
        const displayRole = contact.role ? ` (${this.formatRole(contact.role)})` : '';
        this.elements.contactName.textContent = `${contact.name || 'Unknown'}${displayRole}`;

        // Display online status with typing indicator support
        const statusText = contact.typing ? 'Typing…' : (contact.online ? 'Online' : 'Offline');
        this.elements.contactStatus.textContent = statusText;
        this.elements.contactStatus.classList.remove('online', 'offline', 'typing');
        this.elements.contactStatus.classList.add(contact.typing ? 'typing' : (contact.online ? 'online' : 'offline'));

        // Update avatar
        if (contact.photo) {
            const img = document.createElement('img');
            // Handle both plain filenames and full paths
            const photoPath = contact.photo.includes('/') ? contact.photo : `assets/images/user/${contact.photo}`;
            img.src = photoPath;
            img.alt = contact.name || 'User';
            img.onerror = () => {
                this.setAvatarInitials(contact);
            };
            this.elements.chatAvatar.innerHTML = '';
            this.elements.chatAvatar.appendChild(img);
        } else {
            this.setAvatarInitials(contact);
        }

        // Show chat, hide welcome
        this.elements.welcomeScreen.classList.add('hidden');
        this.elements.messagesContainer.classList.remove('hidden');
        
        // Show header and input area
        this.elements.chatHeader.classList.remove('hidden');
        this.elements.inputArea.classList.remove('hidden');

        // Mobile: hide sidebar, show chat
        if (window.innerWidth < 769) {
            this.showChatView();
        }
        }

    setAvatarInitials(contact) {
        const initials = (contact.name || '?').trim().split(/\s+/).map(s => s[0]).slice(0, 2).join('').toUpperCase();
        this.elements.chatAvatar.textContent = initials || 'U';
    }

    showChatView() {
        this.elements.sidebar.classList.remove('show');
        this.elements.chatArea.classList.add('show');
    }

    showSidebar() {
        this.elements.sidebar.classList.add('show');
        this.elements.chatArea.classList.remove('show');
    }

    renderMessages(messages) {
        this.messageIdToElement.clear();
        const html = messages.map(msg => {
            const statusIcon = msg.type === 'outgoing' ? this.getStatusIcon(msg.status || 'sending') : '';
            const dropdownItems = this.getMessageDropdownItems(msg.type);

            // Build reply context HTML if exists
            let replyHtml = '';
            if (msg.replyContext) {
                replyHtml = `
                     <div class="reply-context">
                         <div class="reply-context-sender">
                             <i class="fas fa-reply"></i>
                             ${this.escape(msg.replyContext.sender || 'Contact')}
                         </div>
                         <div class="reply-context-text">${this.escape(msg.replyContext.replyText)}</div>
                     </div>
                 `;
            }

            const msgHtml = `
                 <div class="message ${msg.type}" data-message-id="${msg.id || ''}">
                     <div class="message-bubble">
                         ${replyHtml}
                         <div class="message-text">${this.renderMessageContent(msg)}</div>
                         <div class="message-time">
                             ${msg.time}
                             ${statusIcon ? `<span class="message-status">${statusIcon}</span>` : ''}
                         </div>
                         <div class="message-actions">
                             <button class="message-menu-btn" title="Message options">
                                 <i class="fas fa-ellipsis-v"></i>
                             </button>
                             <div class="message-dropdown">
                                 ${dropdownItems}
                             </div>
                         </div>
                     </div>
                 </div>
             `;
            return msgHtml;
        }).join('');

        this.elements.messagesContainer.innerHTML = html;

        // Cache message elements and attach listeners
        messages.forEach(msg => {
            if (msg.id) {
                const el = this.elements.messagesContainer.querySelector(`[data-message-id="${msg.id}"]`);
                if (el) {
                    this.messageIdToElement.set(msg.id, el);
                    this.attachMessageListeners(el, msg);
                }
            }
        });

        this.scrollToBottom();
        
        // Initialize WaveSurfer for voice messages
        this.initializeVoiceMessages();
        }
        
        initializeVoiceMessages() {
         const voiceButtons = document.querySelectorAll('.voice-play-btn');
         
         voiceButtons.forEach(btn => {
             const messageId = btn.dataset.messageId;
             const url = btn.dataset.url;
             const waveformContainer = document.getElementById(`waveform-${messageId}`);
             const timeLabel = document.getElementById(`time-${messageId}`);
             
             if (!waveformContainer || url === '#') return;
             
             // Create WaveSurfer instance if not already created
             if (!btn._wavesurfer && typeof WaveSurfer !== 'undefined') {
                 btn._wavesurfer = WaveSurfer.create({
                     container: waveformContainer,
                     waveColor: '#ddd',
                     progressColor: '#4fc3f7',
                     height: 40,
                     barWidth: 3,
                     barGap: 1.5,
                     cursorWidth: 0
                 });
                 
                 // Load audio
                 btn._wavesurfer.load(url);
                 
                 // Update time display
                 btn._wavesurfer.on('ready', () => {
                     const duration = btn._wavesurfer.getDuration();
                     const durationStr = this.formatVoiceTime(Math.floor(duration));
                     timeLabel.textContent = durationStr;
                 });
                 
                 // Update time during playback
                 btn._wavesurfer.on('timeupdate', (currentTime) => {
                     timeLabel.textContent = this.formatVoiceTime(Math.floor(currentTime));
                 });
                 
                 // Update button state
                 btn._wavesurfer.on('play', () => {
                     btn.innerHTML = '<i class="fas fa-pause"></i>';
                     btn.classList.add('playing');
                 });
                 
                 btn._wavesurfer.on('pause', () => {
                     btn.innerHTML = '<i class="fas fa-play"></i>';
                     btn.classList.remove('playing');
                 });
             }
             
             // Toggle play/pause
             btn.addEventListener('click', (e) => {
                 e.stopPropagation();
                 if (btn._wavesurfer) {
                     btn._wavesurfer.playPause();
                 }
             });
         });
        }
        
        formatVoiceTime(seconds) {
         seconds = Math.floor(seconds);
         return Math.floor(seconds / 60) + ':' + (seconds % 60).toString().padStart(2, '0');
        }

    addMessage(message) {


        const statusIcon = message.type === 'outgoing' ? this.getStatusIcon(message.status || 'sending') : '';
        const dropdownItems = this.getMessageDropdownItems(message.type);
        // Render message content (handles files, replies, etc)
        const displayText = this.renderMessageContent(message);
        const html = `
             <div class="message ${message.type}" data-message-id="${message.id || ''}">
                 <div class="message-bubble">
                     <div>${displayText}</div>
                     <div class="message-time">
                         ${message.time}
                         ${statusIcon ? `<span class="message-status">${statusIcon}</span>` : ''}
                     </div>
                     <div class="message-actions">
                         <button class="message-menu-btn" title="Message options">
                             <i class="fas fa-ellipsis-v"></i>
                         </button>
                         <div class="message-dropdown">
                             ${dropdownItems}
                         </div>
                     </div>
                 </div>
             </div>
         `;

        if (!this.elements.messagesContainer) {

            return;
        }

        this.elements.messagesContainer.insertAdjacentHTML('beforeend', html);


        // Cache the element and attach listeners
        if (message.id) {
            const el = this.elements.messagesContainer.querySelector(`[data-message-id="${message.id}"]`);
            if (el) {

                this.messageIdToElement.set(message.id, el);
                this.attachMessageListeners(el, message);
            } else {

            }
        }

        this.scrollToBottom();
    }

    getMessageDropdownItems(messageType) {
        const isOutgoing = messageType === 'outgoing';
        return `
             <div class="message-dropdown-item" onclick="window.chatApp.ui.replyToMessage(this)">
                 <i class="fas fa-reply"></i>
                 <span>Reply</span>
             </div>
             <div class="message-dropdown-item" onclick="window.chatApp.ui.forwardMessage(this)">
                 <i class="fas fa-share"></i>
                 <span>Forward</span>
             </div>
             <div class="message-dropdown-item" onclick="window.chatApp.ui.copyMessage(this)">
                 <i class="fas fa-copy"></i>
                 <span>Copy</span>
             </div>
             <div class="message-dropdown-item" onclick="window.chatApp.ui.showReactionPicker(this)">
                 <i class="fas fa-smile"></i>
                 <span>React</span>
             </div>
             ${isOutgoing ? `
                 <div class="message-dropdown-item" onclick="window.chatApp.ui.editMessage(this)">
                     <i class="fas fa-edit"></i>
                     <span>Edit</span>
                 </div>
             ` : ''}
             <div class="message-dropdown-divider"></div>
             <div class="message-dropdown-item danger" onclick="window.chatApp.ui.deleteMessage(this)">
                 <i class="fas fa-trash"></i>
                 <span>Delete</span>
             </div>
         `;
    }

    attachMessageListeners(messageEl, message) {
        const menuBtn = messageEl.querySelector('.message-menu-btn');
        const dropdown = messageEl.querySelector('.message-dropdown');

        if (menuBtn && dropdown) {
            menuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                // Close other dropdowns
                document.querySelectorAll('.message-dropdown').forEach(d => {
                    if (d !== dropdown) d.classList.remove('open');
                });
                dropdown.classList.toggle('open');
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', () => {
            if (dropdown) dropdown.classList.remove('open');
        });
    }

    replyToMessage(element) {
        const messageEl = element.closest('.message');
        if (!messageEl) return;

        const messageId = messageEl.getAttribute('data-message-id');
        const messageText = messageEl.querySelector('.message-text')?.textContent || messageEl.textContent.trim();
        const isOutgoing = messageEl.classList.contains('outgoing');
        const senderName = isOutgoing ? 'You' : (this.currentContact?.name || 'Contact');

        // Set reply context
        this.replyContext = {
            messageId: messageId,
            to: senderName,
            text: messageText.substring(0, 100),
            fullText: messageText
        };

        // Show reply preview in input area
        this.showReplyPreview(senderName, messageText);
        this.elements.messageInput.focus();
    }

    showReplyPreview(senderName, messageText) {
        // Remove existing preview
        const existing = this.elements.messageInput.parentElement.querySelector('.reply-preview');
        if (existing) existing.remove();

        const preview = document.createElement('div');
        preview.className = 'reply-preview';
        preview.innerHTML = `
             <div class="reply-preview-content">
                 <div class="reply-preview-header">
                     <span class="reply-preview-sender">${this.escape(senderName)}</span>
                     <button class="reply-preview-close" onclick="this.closest('.reply-preview').remove(); window.chatApp.ui.replyContext = null;">
                         <i class="fas fa-times"></i>
                     </button>
                 </div>
                 <div class="reply-preview-text">${this.escape(messageText.substring(0, 100))}</div>
             </div>
         `;

        this.elements.messageInput.parentElement.insertBefore(preview, this.elements.messageInput);
    }

    clearReplyPreview() {
        const preview = this.elements.messageInput.parentElement.querySelector('.reply-preview');
        if (preview) preview.remove();
        this.replyContext = null;
    }

    forwardMessage(element) {
        const messageEl = element.closest('.message');
        if (!messageEl) return;

        const text = messageEl.querySelector('.message-text')?.textContent || messageEl.textContent.trim();
        const messageId = parseInt(messageEl.getAttribute('data-message-id'), 10);

        // Show contact selection modal
        this.showForwardDialog(text, messageId);
    }

    showForwardDialog(messageText, messageId) {
        const modal = document.createElement('div');
        modal.className = 'forward-modal';
        modal.style.cssText = `
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
         `;

        const contacts = window.chatApp?.manager?.contacts || [];
        const contactHtml = contacts.map(c => `
             <div class="forward-contact-item" data-contact-id="${c.id}" style="padding: 10px; cursor: pointer; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 10px; transition: background 0.2s;">
                 <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                     ${(c.name || 'U').charAt(0).toUpperCase()}
                 </div>
                 <div>
                     <div style="font-weight: 500;">${this.escape(c.name)}</div>
                     <div style="font-size: 0.8rem; color: #999;">${this.escape(c.agency_name || '')}</div>
                 </div>
             </div>
         `).join('');

        const content = document.createElement('div');
        content.style.cssText = `
             background: white;
             border-radius: 8px;
             width: 90%;
             max-width: 400px;
             max-height: 70vh;
             display: flex;
             flex-direction: column;
             box-shadow: 0 4px 12px rgba(0,0,0,0.15);
         `;

        content.innerHTML = `
             <div style="padding: 20px; border-bottom: 1px solid #eee;">
                 <h5 style="margin: 0; font-weight: 600;">Forward to</h5>
                 <p style="margin: 8px 0 0 0; font-size: 0.9rem; color: #666;">${this.escape(messageText.substring(0, 50))}${messageText.length > 50 ? '...' : ''}</p>
             </div>
             <div class="forward-contacts-list" style="flex: 1; overflow-y: auto;">
                 ${contactHtml || '<div style="padding: 20px; text-align: center; color: #999;">No contacts available</div>'}
             </div>
             <div style="padding: 15px; border-top: 1px solid #eee; display: flex; gap: 10px;">
                 <button class="btn btn-sm btn-secondary" style="flex: 1;">Cancel</button>
             </div>
         `;

        modal.appendChild(content);
        document.body.appendChild(modal);

        // Close on cancel
        content.querySelector('.btn-secondary').addEventListener('click', () => {
            modal.remove();
        });

        // Close on backdrop click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.remove();
        });

        // Handle contact selection
        content.querySelectorAll('.forward-contact-item').forEach(item => {
            item.addEventListener('mouseover', () => {
                item.style.background = '#f5f5f5';
            });
            item.addEventListener('mouseout', () => {
                item.style.background = 'transparent';
            });
            item.addEventListener('click', () => {
                const contactId = parseInt(item.getAttribute('data-contact-id'), 10);
                this.sendForwardedMessage(contactId, messageText, messageId);
                modal.remove();
            });
        });
    }

    sendForwardedMessage(contactId, messageText, originalMessageId) {
        if (!window.chatApp?.api) return;

        const api = window.chatApp.api;
        const manager = window.chatApp.manager;

        // Send message directly via API
        api.sendMessage(contactId, messageText)
            .then(async response => {
                this.showSuccess('Message forwarded');

                // Switch to contact
                manager.selectContact(contactId);
                const contact = manager.getCurrentContact();

                // Reload messages for the contact
                try {
                    const messagesResponse = await api.getMessages(contactId);
                    if (messagesResponse.messages) {
                        const formatted = messagesResponse.messages.map(m => {
                            const isOutgoing = m.from_user_id === window.ALQ_USER_ID;
                            let status = 'sending';
                            if (isOutgoing) {
                                if (m.seen_at) status = 'read';
                                else if (m.delivered_at) status = 'delivered';
                                else status = 'sent';
                            }
                            return {
                                id: m.id,
                                text: m.content,
                                type: isOutgoing ? 'outgoing' : 'incoming',
                                status: status,
                                time: new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                            };
                        });
                        this.renderMessages(formatted);
                    }
                } catch (e) {

                }

                // Update sidebar
                if (contact) {
                    contact.lastMessage = messageText;
                    contact.time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    this.renderContacts(manager.contacts);
                }
            })
            .catch(error => {

                this.showError('Failed to forward message');
            });
    }

    copyMessage(element) {
        const messageEl = element.closest('.message');
        if (!messageEl) return;

        const text = messageEl.querySelector('.message-text')?.textContent || messageEl.textContent.trim();

        navigator.clipboard.writeText(text).then(() => {
            this.showSuccess('Message copied');
        }).catch(() => {
            this.showError('Failed to copy message');
        });
    }

    editMessage(element) {
        const messageEl = element.closest('.message');
        if (!messageEl) return;

        const text = messageEl.querySelector('.message-text')?.textContent || messageEl.textContent.trim();
        this.elements.messageInput.value = text;
        this.elements.messageInput.focus();

        // Mark as editing
        this.editingMessageId = messageEl.getAttribute('data-message-id');
        this.showSuccess('Editing message');
    }

    deleteMessage(element) {
        const messageEl = element.closest('.message');
        if (!messageEl) return;

        if (confirm('Delete this message?')) {
            messageEl.style.opacity = '0.5';
            const messageId = messageEl.getAttribute('data-message-id');

            // Send delete request
            fetch('api/messages.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'delete',
                    message_id: messageId
                })
            }).then(response => {
                if (response.ok) {
                    messageEl.remove();
                    this.showSuccess('Message deleted');
                } else {
                    messageEl.style.opacity = '1';
                    this.showError('Failed to delete message');
                }
            }).catch(error => {

                messageEl.style.opacity = '1';
                this.showError('Failed to delete message');
            });
        }
    }

    showReactionPicker(element) {
        const messageEl = element.closest('.message');
        if (!messageEl) return;

        const reactions = ['👍', '❤️', '😂', '😮', '😢', '🔥', '🎉', '✨'];
        const messageId = messageEl.getAttribute('data-message-id');

        const pickerHtml = `
             <div class="reaction-picker">
                 ${reactions.map(emoji => `
                     <button class="reaction-btn" data-emoji="${emoji}" title="Add ${emoji}">
                         ${emoji}
                     </button>
                 `).join('')}
             </div>
         `;

        const existing = messageEl.querySelector('.reaction-picker');
        if (existing) {
            existing.remove();
            return;
        }

        const bubble = messageEl.querySelector('.message-bubble');
        if (bubble) {
            bubble.insertAdjacentHTML('beforeend', pickerHtml);

            // Add click listeners to reaction buttons
            messageEl.querySelectorAll('.reaction-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const emoji = btn.getAttribute('data-emoji');
                    this.addReaction(messageId, emoji);
                });
            });
        }
    }

    addReaction(messageId, emoji) {


        // Ensure messageId is a number
        const numericId = parseInt(messageId, 10);
        let messageEl = this.messageIdToElement.get(messageId);

        // Try with numeric ID if string lookup failed
        if (!messageEl) {
            messageEl = this.messageIdToElement.get(numericId);
        }

        // Try looking up in DOM directly as fallback
        if (!messageEl) {
            messageEl = document.querySelector(`[data-message-id="${messageId}"]`);
            if (messageEl) {
                this.messageIdToElement.set(messageId, messageEl);
            }
        }

        if (!messageEl) {


            return;
        }

        // Remove picker
        const picker = messageEl.querySelector('.reaction-picker');
        if (picker) picker.remove();

        // Add reaction to server FIRST
        fetch('api/message_reactions.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                message_id: messageId,
                emoji: emoji,
                csrf_token: window.csrfToken || ''
            })
        }).then(response => {

            if (!response.ok) {

                this.showError('Failed to add reaction');
                return null;
            }
            return response.json();
        }).then(data => {

            if (data && (data.ok || data.action)) {

                // Now update UI
                let reactionsContainer = messageEl.querySelector('.message-reactions');
                if (!reactionsContainer) {
                    reactionsContainer = document.createElement('div');
                    reactionsContainer.className = 'message-reactions';
                    messageEl.querySelector('.message-bubble').appendChild(reactionsContainer);
                }

                // Refresh reactions from server
                setTimeout(() => {
                    if (window.chatApp) {
                        const messageId = parseInt(messageEl.getAttribute('data-message-id'), 10);

                        fetch(`api/message_reactions.php?message_id=${messageId}`, {
                            credentials: 'include'
                        }).then(r => r.json())
                            .then(reactionsData => {

                                reactionsContainer.innerHTML = '';
                                if (reactionsData.reactions) {
                                    for (const [emoji, reactions] of Object.entries(reactionsData.reactions)) {
                                        const reactionEl = document.createElement('div');
                                        reactionEl.className = 'reaction-item';
                                        reactionEl.setAttribute('data-emoji', emoji);
                                        reactionEl.innerHTML = `
                                           <span class="reaction-emoji">${emoji}</span>
                                           <span class="reaction-count">${reactions.length}</span>
                                       `;
                                        reactionsContainer.appendChild(reactionEl);
                                    }
                                }
                                this.showSuccess('Reaction updated');
                            });
                    }
                }, 200);
            } else if (data && data.error) {

                this.showError(data.error);
            }
        }).catch(error => {

            this.showError('Failed to add reaction');
        });
    }

    getStatusIcon(status) {
        switch (status) {
            case 'sending':
                return '⏳';
            case 'sent':
            case 'delivered':
                return '✓';
            case 'seen':
            case 'read':
                return '✓✓';
            default:
                return '';
        }
    }

    updateMessageStatus(messageId, status) {
        window.__lastStatusUpdate = { messageId, status, timestamp: Date.now() };

        let el = this.messageIdToElement.get(messageId);

        // Try with numeric ID if string lookup failed
        if (!el) {
            const numericId = parseInt(messageId, 10);
            el = this.messageIdToElement.get(numericId);
        }

        // Try looking up in DOM directly as fallback
        if (!el) {
            el = document.querySelector(`[data-message-id="${messageId}"]`);
            if (el) {
                this.messageIdToElement.set(messageId, el);
            }
        }

        if (!el) {

            return;
        }

        // Look for the status span - might be in different places
        let statusSpan = el.querySelector('.message-status');

        // If not found, create it if needed
        if (!statusSpan && status !== 'sending') {
            const messageTime = el.querySelector('.message-time');
            if (messageTime) {
                statusSpan = document.createElement('span');
                statusSpan.className = 'message-status';
                messageTime.appendChild(statusSpan);
            }
        }

        if (statusSpan) {
            const icon = this.getStatusIcon(status);
            statusSpan.textContent = icon;
            statusSpan.className = `message-status status-${status}`;
        }
    }

    scrollToBottom() {
        this.elements.messagesContainer.scrollTop = this.elements.messagesContainer.scrollHeight;
    }

    getMessageText() {
        return this.elements.messageInput?.value.trim() || '';
    }

    clearInput() {
        if (this.elements.messageInput) {
            this.elements.messageInput.value = '';
            this.elements.messageInput.style.height = 'auto';
        }
    }

    focusInput() {
        this.elements.messageInput?.focus();
    }

    showError(message) {
        alert(`Error: ${message}`);
    }

    showSuccess(message) {

    }

    showTypingIndicator() {
        if (this.elements.messagesContainer.querySelector('.typing-indicator')) {
            return; // Already showing
        }

        const html = `
            <div class="message">
                <div class="typing-indicator">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
            </div>
        `;

        this.elements.messagesContainer.insertAdjacentHTML('beforeend', html);
        this.scrollToBottom();
    }

    hideTypingIndicator() {
        const indicator = this.elements.messagesContainer.querySelector('.typing-indicator');
        if (indicator) {
            indicator.closest('.message').remove();
        }
    }

    handleFileUpload(event) {
        const files = Array.from(event.target.files);
        if (files.length === 0) return;

        const contact = this.currentContact;
        if (!contact) {
            this.showError('Please select a contact first');
            event.target.value = '';
            return;
        }

        const api = window.chatApp?.api;
        if (!api) {
            this.showError('API not available');
            event.target.value = '';
            return;
        }

        // Validate files
        const maxFileSize = 50 * 1024 * 1024; // 50MB
        const validFiles = [];

        for (const file of files) {
            if (file.size > maxFileSize) {
                this.showError(`File "${file.name}" is too large (max 50MB)`);
                continue;
            }
            validFiles.push(file);
        }

        if (validFiles.length === 0) {
            event.target.value = '';
            return;
        }

        validFiles.forEach(file => {
            const fileName = file.name;
            const fileSize = (file.size / 1024 / 1024).toFixed(2);

            const messageId = Math.random(); // temp ID
            this.addMessage({
                id: messageId,
                text: `📎 ${fileName} (uploading...)`,
                type: 'outgoing',
                status: 'sending',
                time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
            });

            // Upload file
            api.uploadFile(file, contact.id)
                .then(async response => {
                    if (response && response.ok) {
                        // Reload messages to show the uploaded file
                        try {
                            const messagesResponse = await api.getMessages(contact.id);
                            if (messagesResponse.messages) {
                                const formatted = messagesResponse.messages.map(m => {
                                    const isOutgoing = m.from_user_id === window.ALQ_USER_ID;
                                    let status = 'sending';
                                    if (isOutgoing) {
                                        if (m.seen_at) status = 'read';
                                        else if (m.delivered_at) status = 'delivered';
                                        else status = 'sent';
                                    }
                                    return {
                                        id: m.id,
                                        text: m.content,
                                        type: isOutgoing ? 'outgoing' : 'incoming',
                                        status: status,
                                        time: new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                                    };
                                });
                                this.renderMessages(formatted);
                            }
                        } catch (e) {

                        }
                        // Update sidebar
                        contact.lastMessage = `📎 ${fileName}`;
                        contact.time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                        this.renderContacts(window.chatApp?.manager?.contacts || []);
                        this.showSuccess(`"${fileName}" sent successfully`);
                    } else {
                        this.showError(`Failed to send "${fileName}"`);
                    }
                })
                .catch(error => {

                    this.showError(`Upload failed: ${error.message || 'Unknown error'}`);
                });
        });

        event.target.value = ''; // Reset
        this.focusInput();
    }

    updateMessageStatus(messageId, status) {
        const msg = this.elements.messagesContainer.querySelector(`[data-message-id="${messageId}"]`);
        if (!msg) return;

        let icon = '⏳'; // Sending
        if (status === 'sent') icon = '✓';
        if (status === 'read') icon = '✓✓';

        const statusEl = msg.querySelector('.message-status');
        if (statusEl) {
            statusEl.textContent = icon;
        }
    }

    addNotification(text, type = 'info') {
        const color = type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info';
        const html = `
            <div class="alert alert-${color} alert-dismissible fade show" role="alert" style="margin: 0.5rem;">
                ${text}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;

        const container = document.querySelector('.messages-container');
        if (container) {
            container.insertAdjacentHTML('beforeend', html);

            // Auto-dismiss after 3 seconds
            setTimeout(() => {
                const alert = container.querySelector('.alert');
                if (alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 3000);
        }
    }

    escape(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = ChatUI;
}
