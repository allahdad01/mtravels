/**
 * Clean Chat Initialization
 */

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initChat);
} else {
    initChat();
}

async function initChat() {
     try {
         console.log('[Chat] Initializing...');

         const manager = new ChatManager();
         const ui = new ChatUI();
         const api = new ChatAPI();
         const voiceRecorder = new VoiceRecorder();
         const voiceUI = new VoiceMessageUI(api, voiceRecorder);
         const voiceAdvanced = new VoiceMessageAdvanced(api);

         // Initialize
         await manager.init();
         ui.init(manager);
         ui.renderContacts(manager.contacts);
         voiceUI.init();
         voiceAdvanced.init();

         // Store in window
         window.chatApp = { manager, ui, api, voiceRecorder, voiceUI, voiceAdvanced };

         console.log(`[Chat] Ready. ${manager.contacts.length} contacts loaded.`);

         // Setup listeners
         setupListeners(manager, ui, api, voiceUI);

     } catch (error) {
         console.error('[Chat] Error:', error);
     }
 }

// Load reactions for a message
function loadMessageReactions(messageId) {
    const ui = window.chatApp?.ui;
    if (!ui) {
        console.warn('[Chat] UI not available');
        return;
    }
    
    console.log('[Chat] Loading reactions for message:', messageId);
    
    fetch(`api/message_reactions.php?message_id=${messageId}`, {
        credentials: 'include'
    }).then(response => {
        if (!response.ok) {
            console.warn(`[Chat] Reactions API returned ${response.status}`);
            return null;
        }
        return response.json();
    })
      .then(data => {
          if (!data) return;
          
          console.log('[Chat] Got reactions data:', data);
          
          if (data.reactions && Object.keys(data.reactions).length > 0) {
              const messageEl = ui.messageIdToElement.get(messageId);
              console.log('[Chat] Message element found:', !!messageEl);
              
              if (messageEl) {
                  const bubble = messageEl.querySelector('.message-bubble');
                  if (bubble) {
                      let reactionsContainer = bubble.querySelector('.message-reactions');
                      if (!reactionsContainer) {
                          reactionsContainer = document.createElement('div');
                          reactionsContainer.className = 'message-reactions';
                          bubble.appendChild(reactionsContainer);
                      }
                      
                      reactionsContainer.innerHTML = '';
                      for (const [emoji, reactions] of Object.entries(data.reactions)) {
                          const reactionEl = document.createElement('div');
                          reactionEl.className = 'reaction-item';
                          reactionEl.setAttribute('data-emoji', emoji);
                          reactionEl.innerHTML = `
                              <span class="reaction-emoji">${emoji}</span>
                              <span class="reaction-count">${reactions.length}</span>
                          `;
                          reactionsContainer.appendChild(reactionEl);
                          console.log('[Chat] Added reaction:', emoji, reactions.length);
                      }
                  }
              }
          }
      }).catch(error => {
          console.error('[Chat] Failed to load reactions:', error);
      });
}

function setupListeners(manager, ui, api, voiceUI) {
    // Contact selection
    window.addEventListener('contactSelected', async (e) => {
        const { contactId } = e.detail;
        console.log('[Chat] Selected contact:', contactId);

        manager.selectContact(contactId);
        const contact = manager.getCurrentContact();

        if (!contact) {
            ui.showError('Contact not found');
            return;
        }

        ui.showChat(contact);
        ui.focusInput();

        // Load messages
        try {
            const response = await api.getMessages(contactId);
            if (response.messages) {
                const formatted = response.messages.map(m => {
                    const isOutgoing = m.from_user_id === window.ALQ_USER_ID;
                    let status = 'sending';
                    if (isOutgoing) {
                        if (m.seen_at) status = 'read';
                        else if (m.delivered_at) status = 'delivered';
                        else status = 'sent';
                    }
                    
                    // Handle reply messages
                    let displayText = m.content;
                    let replyContext = null;
                    
                    try {
                        const parsed = JSON.parse(m.content);
                        if (parsed.type === 'reply') {
                            displayText = parsed.content;
                            // Find original message to show sender name
                            const originalMsg = response.messages.find(orig => orig.id === parseInt(parsed.replyTo));
                            const senderName = originalMsg 
                                ? (originalMsg.from_user_id === window.ALQ_USER_ID ? 'You' : contact.name)
                                : 'Unknown';
                            
                            replyContext = {
                                replyTo: parsed.replyTo,
                                sender: senderName,
                                replyText: parsed.replyText
                            };
                        }
                    } catch (e) {
                        // Not JSON, use as is
                    }
                    
                    return {
                        id: m.id,
                        text: displayText,
                        type: isOutgoing ? 'outgoing' : 'incoming',
                        status: status,
                        replyContext: replyContext,
                        time: new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                    };
                });
                ui.renderMessages(formatted);
                
                // Mark messages as read
                await manager.markAsRead(contactId);
                
                // Load reactions for all messages
                formatted.forEach(msg => {
                    if (msg.id) {
                        setTimeout(() => {
                            loadMessageReactions(msg.id);
                        }, 100);
                    }
                });
            }
        } catch (error) {
            console.error('[Chat] Failed to load messages:', error);
        }
    });

    // Send message
    let messageCounter = 0;
    window.addEventListener('sendMessage', async () => {
        console.log('[Chat] sendMessage event triggered');
        const contact = manager.getCurrentContact();
        if (!contact) {
            console.warn('[Chat] No contact selected');
            return;
        }

        const text = ui.getMessageText();
        if (!text) {
            console.warn('[Chat] No message text');
            return;
        }

        console.log('[Chat] Sending message to contact:', contact.id);
        ui.clearInput();
        const messageId = ++messageCounter;
        console.log('[Chat] Created local message ID:', messageId);
        
        // Prepare message body with optional reply
        let messageBody = text;
        if (ui.replyContext) {
            messageBody = JSON.stringify({
                type: 'reply',
                content: text,
                replyTo: ui.replyContext.messageId,
                replyText: ui.replyContext.fullText
            });
        }
        
        ui.addMessage({
            id: messageId,
            text,
            type: 'outgoing',
            status: 'sending',
            time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        });

        try {
            const response = await api.sendMessage(contact.id, messageBody);
            console.log('[Chat] API sendMessage response:', response);
            
            if (response && response.id) {
                // Reload messages to get the server version with proper status
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
                        ui.renderMessages(formatted);
                    }
                } catch (e) {
                    console.error('[Chat] Failed to reload messages:', e);
                    // Fallback: just update the message status
                    ui.updateMessageStatus(messageId, 'delivered');
                }
            } else {
                console.warn('[Chat] No server ID in response');
                ui.updateMessageStatus(messageId, 'sent');
            }
            
            // Update sidebar with new message
            contact.lastMessage = text;
            contact.time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            ui.renderContacts(manager.contacts);
            
            // Clear reply context after sending
            ui.clearReplyPreview();
        } catch (error) {
            console.error('[Chat] Failed to send message:', error);
            ui.showError('Failed to send message');
        }
    });

    // Online status updates
    window.addEventListener('userStatusUpdated', () => {
        ui.renderContacts(manager.contacts);
        
        // Update header status for current contact
        const currentContact = manager.getCurrentContact();
        if (currentContact) {
            const statusText = currentContact.typing ? 'Typing…' : (currentContact.online ? 'Online' : 'Offline');
            ui.elements.contactStatus.textContent = statusText;
            ui.elements.contactStatus.classList.remove('online', 'offline', 'typing');
            ui.elements.contactStatus.classList.add(currentContact.typing ? 'typing' : (currentContact.online ? 'online' : 'offline'));
        }
    });

    // Typing indicator
    let typingTimeout;
    window.addEventListener('userTyping', async () => {
        if (!manager.getCurrentContact()) return;

        clearTimeout(typingTimeout);

        // Send typing status to server
        await fetch('api/typing.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                peer_id: manager.getCurrentContact().id,
                typing: '1'
            })
        }).catch(e => console.warn('[Chat] Typing status error:', e));

        // Stop typing after 2 seconds of inactivity
        typingTimeout = setTimeout(async () => {
            await fetch('api/typing.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    peer_id: manager.getCurrentContact().id,
                    typing: '0'
                })
            }).catch(e => console.warn('[Chat] Typing status error:', e));
        }, 2000);
    });

    // Voice message sent
    window.addEventListener('voiceMessageSent', async (e) => {
        const { message, duration } = e.detail;
        const contact = manager.getCurrentContact();
        
        if (contact) {
            // Reload messages to display the voice message
            try {
                const response = await api.getMessages(contact.id);
                if (response.messages) {
                    const formatted = response.messages.map(m => {
                        const isOutgoing = m.from_user_id === window.ALQ_USER_ID;
                        let status = 'sending';
                        if (isOutgoing) {
                            if (m.seen_at) status = 'read';
                            else if (m.delivered_at) status = 'delivered';
                            else status = 'sent';
                        }
                        
                        // Check if voice message
                        const isVoice = m.message_type === 'voice' || (m.content && m.content.includes('voice'));
                        
                        return {
                            id: m.id,
                            text: m.content,
                            type: isOutgoing ? 'outgoing' : 'incoming',
                            status: status,
                            messageType: m.message_type || 'text',
                            duration: m.duration || 0,
                            url: m.url,
                            time: new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                        };
                    });
                    ui.renderMessages(formatted);
                }
            } catch (error) {
                console.error('[Chat] Failed to reload messages after voice message:', error);
            }
            
            // Update sidebar
            contact.lastMessage = '🎤 Voice message';
            contact.time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            ui.renderContacts(manager.contacts);
        }
    });

    // Logout on unload
    window.addEventListener('beforeunload', () => {
        fetch('api/online_sessions.php?action=logout', {
            method: 'GET',
            credentials: 'include',
            keepalive: true
        }).catch(e => console.warn('[Chat] Logout error:', e));
    });
    }

// Mobile: show sidebar by default
if (window.innerWidth < 769) {
    document.addEventListener('DOMContentLoaded', () => {
        const sidebar = document.getElementById('sidebar');
        const chatArea = document.getElementById('chatArea');
        if (sidebar) sidebar.classList.add('show');
        if (chatArea) chatArea.classList.remove('show');
    });
}
