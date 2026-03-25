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


         const manager = new ChatManager();
         const ui = new ChatUI();
         const api = new ChatAPI();
         const voiceRecorder = new VoiceRecorder();
         const voiceUI = new VoiceMessageUI(api, voiceRecorder);
         const voiceAdvanced = new VoiceMessageAdvanced(api);

         // Initialize
         await manager.init();
         ui.init(manager);
         ui.renderContacts(manager.contacts, manager.groups); // Render with online status after full init
         voiceUI.init();
         voiceAdvanced.init();

         // Store in window
         window.chatApp = { manager, ui, api, voiceRecorder, voiceUI, voiceAdvanced };



         // Setup listeners
         setupListeners(manager, ui, api, voiceUI);

     } catch (error) {

     }
 }

// Load reactions for a message
function loadMessageReactions(messageId) {
    const ui = window.chatApp?.ui;
    if (!ui) {

        return;
    }
    

    
    fetch(`api/message_reactions.php?message_id=${messageId}`, {
        credentials: 'include'
    }).then(response => {
        if (!response.ok) {

            return null;
        }
        return response.json();
    })
      .then(data => {
          if (!data) return;
          

          
          if (data.reactions && Object.keys(data.reactions).length > 0) {
              const messageEl = ui.messageIdToElement.get(messageId);

              
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

                      }
                  }
              }
          }
      }).catch(error => {

      });
}

function setupListeners(manager, ui, api, voiceUI) {
    // Contact selection
    window.addEventListener('contactSelected', async (e) => {
        const { contactId, userType } = e.detail;


        manager.selectContact(contactId, userType);
        const contact = manager.getCurrentContact();

        if (!contact) {
            console.error('Contact not found:', { contactId, userType, contacts: manager.contacts });
            ui.showError('Contact not found');
            return;
        }

        ui.showChat(contact);
        ui.focusInput();

        // Load messages - pass peer_type for correct room_id generation
        try {
            const response = await api.getMessages(contactId, { peerType: userType || 'user' });
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
                        messageType: m.message_type || 'text',
                        duration: m.duration || 0,
                        url: m.url,
                        time: new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                    };
                });
                ui.renderMessages(formatted);
                
                // Mark messages as read
                await manager.markAsRead(contactId, userType);
                
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

        }
    });

    // Group selection
    window.addEventListener('groupSelected', async (e) => {
        const { groupId } = e.detail;
        
        manager.currentGroupId = groupId;
        manager.currentType = 'group';
        
        const group = manager.groups.find(g => g.id === groupId);
        
        if (!group) {
            ui.showError('Group not found');
            return;
        }
        
        // Show group chat header
         ui.elements.contactName.textContent = group.group_name;
         ui.elements.contactStatus.textContent = group.member_count + ' members';
         
         // Update group avatar (image or icon with background)
         if (group.profile_pic) {
             // Display group image
             const img = document.createElement('img');
             img.src = group.profile_pic;
             img.alt = group.group_name;
             img.style.width = '100%';
             img.style.height = '100%';
             img.style.objectFit = 'cover';
             img.style.borderRadius = '50%';
             img.onerror = () => {
                 // Fallback to icon if image fails to load
                 ui.elements.chatAvatar.innerHTML = '<span style="font-size: 20px;">👥</span>';
                 ui.elements.chatAvatar.style.background = '#667eea';
                 ui.elements.chatAvatar.style.color = 'white';
             };
             ui.elements.chatAvatar.innerHTML = '';
             ui.elements.chatAvatar.appendChild(img);
             ui.elements.chatAvatar.style.background = 'transparent';
         } else {
             // Display icon if no image
             ui.elements.chatAvatar.innerHTML = '<span style="font-size: 20px;">👥</span>';
             ui.elements.chatAvatar.style.background = '#667eea';
             ui.elements.chatAvatar.style.color = 'white';
         }
         
         ui.elements.chatHeader.style.display = 'block';
         ui.elements.chatHeader.classList.remove('hidden');
         ui.elements.welcomeScreen.classList.add('hidden');
         ui.elements.inputArea.classList.remove('hidden');
         ui.elements.messagesContainer.classList.remove('hidden');
        
        ui.focusInput();
        
        // Load group messages
         try {
             const response = await api.getGroupMessages(groupId);
             if (response.messages) {
                 const formatted = response.messages.map(m => {
                     const isOutgoing = m.from_user_id === window.ALQ_USER_ID;
                     let status = 'sending';
                     if (isOutgoing) {
                         // For group messages, default to 'sent' since delivered_at/seen_at aren't tracked
                         status = 'sent';
                     }
                     
                     let text = m.content;
                     let duration = 0;
                     let url = '';
                     
                     // Parse JSON content for voice/special messages
                     if (m.message_type === 'voice' || (m.content && m.content.startsWith('{'))) {
                         try {
                             const parsed = JSON.parse(m.content);
                             if (parsed.type === 'voice') {
                                 duration = parsed.duration || 0;
                                 url = parsed.url || '';
                                 text = parsed.content || m.content;
                             } else {
                                 text = m.content;
                             }
                         } catch (e) {
                             text = m.content;
                         }
                     }
                     
                     return {
                         id: m.id,
                         text: text,
                         type: isOutgoing ? 'outgoing' : 'incoming',
                         senderName: m.sender_name,
                         status: status,
                         messageType: m.message_type || 'text',
                         duration: duration,
                         url: url,
                         time: new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                     };
                 });
                 ui.renderMessages(formatted);
                 
                 // Mark messages as read
                 const unreadIds = response.messages
                     .filter(m => m.from_user_id !== window.ALQ_USER_ID)
                     .map(m => m.id);
                 
                 if (unreadIds.length > 0) {
                     await api.markGroupMessagesRead(groupId, unreadIds);
                 }
                 
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
            console.error('Group messages error:', error);
            ui.showError('Failed to load group messages: ' + error.message);
        }
    });

    // Send message
    let messageCounter = 0;
    window.addEventListener('sendMessage', async () => {

        const text = ui.getMessageText();
        if (!text) {

            return;
        }

        // Check if it's a group or direct message
        if (manager.currentType === 'group') {
            // Send to group
            const groupId = manager.currentGroupId;
            if (!groupId) {
                ui.showError('Group not found');
                return;
            }

            ui.clearInput();
            const messageId = ++messageCounter;
            
            ui.addMessage({
                id: messageId,
                text,
                type: 'outgoing',
                time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
            });

            try {
                const response = await api.sendGroupMessage(groupId, text);
                
                if (response && response.message_id) {
                    // Reload messages
                    const messagesResponse = await api.getGroupMessages(groupId);
                    if (messagesResponse.messages) {
                        const formatted = messagesResponse.messages.map(m => {
                            const isOutgoing = m.from_user_id === window.ALQ_USER_ID;
                            let status = 'sending';
                            if (isOutgoing) {
                                // For group messages, default to 'sent' since delivered_at/seen_at aren't tracked
                                status = 'sent';
                            }
                            
                            return {
                                id: m.id,
                                text: m.content,
                                type: isOutgoing ? 'outgoing' : 'incoming',
                                senderName: m.sender_name,
                                status: status,
                                messageType: m.message_type || 'text',
                                time: new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                            };
                        });
                        ui.renderMessages(formatted);
                    }
                    
                    // Update sidebar with new message
                    const group = manager.groups.find(g => g.id === groupId);
                    if (group) {
                        group.lastMessage = text;
                        group.time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                        ui.renderContacts(manager.contacts, manager.groups);
                    }
                }
                
                ui.clearReplyPreview();
            } catch (error) {
                ui.showError('Failed to send message');
            }
        } else if (manager.currentType === 'contact') {
            // Send to direct contact
            const contact = manager.getCurrentContact();
            if (!contact) {
                ui.showError('Contact not found');
                return;
            }

            ui.clearInput();
            const messageId = ++messageCounter;

            
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
                const response = await api.sendMessage(contact.id, messageBody, { peerType: contact.user_type || 'user' });

                
                if (response && response.id) {
                    // Reload messages to get the server version with proper status
                    try {
                        const messagesResponse = await api.getMessages(contact.id, { peerType: contact.user_type || 'user' });
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
                                    messageType: m.message_type || 'text',
                                    duration: m.duration || 0,
                                    url: m.url,
                                    time: new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                                };
                            });
                            ui.renderMessages(formatted);
                        }
                    } catch (e) {

                        // Fallback: just update the message status
                        ui.updateMessageStatus(messageId, 'delivered');
                    }
                } else {

                    ui.updateMessageStatus(messageId, 'sent');
                }
                
                // Update sidebar with new message
                contact.lastMessage = text;
                contact.time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                ui.renderContacts(manager.contacts, manager.groups);
                
                // Clear reply context after sending
                ui.clearReplyPreview();
                } catch (error) {

                 ui.showError('Failed to send message');
                }
                } else {
                ui.showError('Please select a contact or group first');
                }
                });

    // Online status updates
    window.addEventListener('userStatusUpdated', () => {
        ui.renderContacts(manager.contacts, manager.groups);
        
        // Update header status for current contact (only if in direct message mode)
        if (manager.currentType === 'contact') {
            const currentContact = manager.getCurrentContact();
            if (currentContact) {
                const statusText = currentContact.typing ? 'Typing…' : (currentContact.online ? 'Online' : 'Offline');
                ui.elements.contactStatus.textContent = statusText;
                ui.elements.contactStatus.classList.remove('online', 'offline', 'typing');
                ui.elements.contactStatus.classList.add(currentContact.typing ? 'typing' : (currentContact.online ? 'online' : 'offline'));
            }
        }
    });

    // Typing indicator (only for direct messages, not groups)
    let typingTimeout;
    window.addEventListener('userTyping', async () => {
        // Only send typing for direct messages
        if (manager.currentType !== 'contact') return;
        
        const contact = manager.getCurrentContact();
        if (!contact) return;

        clearTimeout(typingTimeout);

        // Send typing status to server
        await fetch('api/typing.php', {
             method: 'POST',
             credentials: 'include',
             headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
             body: new URLSearchParams({
                 peer_id: contact.id,
                 peer_type: contact.user_type || 'user',
                 typing: '1'
             })
         });

         // Stop typing after 2 seconds of inactivity
         typingTimeout = setTimeout(async () => {
            await fetch('api/typing.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    peer_id: contact.id,
                    peer_type: contact.user_type || 'user',
                    typing: '0'
                })
            });
         }, 2000);
         });

    // Voice message sent (for both direct messages and groups)
    window.addEventListener('voiceMessageSent', async (e) => {
        const { message, duration } = e.detail;
        const currentType = manager.currentType;
        
        if (currentType === 'contact') {
            // Handle direct message voice
            const contact = manager.getCurrentContact();
            
            if (contact) {
                // Reload messages to display the voice message
                try {
                    const response = await api.getMessages(contact.id, { peerType: contact.user_type || 'user' });
                    if (response.messages) {
                        const formatted = response.messages.map(m => {
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
                                messageType: m.message_type || 'text',
                                duration: m.duration || 0,
                                url: m.url,
                                time: new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                            };
                        });
                        ui.renderMessages(formatted);
                    }
                } catch (error) {

                }
                
                // Update sidebar
                contact.lastMessage = '🎤 Voice message';
                contact.time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                ui.renderContacts(manager.contacts, manager.groups);
            }
        } else if (currentType === 'group') {
             // Handle group message voice
             const groupId = manager.currentGroupId;
             
             if (groupId) {
                 // Reload group messages
                 try {
                     const response = await api.getGroupMessages(groupId);
                     if (response.messages) {
                         const formatted = response.messages.map(m => {
                             const isOutgoing = m.from_user_id === window.ALQ_USER_ID;
                             let status = 'sending';
                             if (isOutgoing) {
                                 status = 'sent';
                             }
                             
                             let text = m.content;
                             let duration = 0;
                             let url = '';
                             
                             // Parse JSON content for voice/special messages
                             if (m.message_type === 'voice' || (m.content && m.content.startsWith('{'))) {
                                 try {
                                     const parsed = JSON.parse(m.content);
                                     if (parsed.type === 'voice') {
                                         duration = parsed.duration || 0;
                                         url = parsed.url || '';
                                         text = parsed.content || m.content;
                                     } else {
                                         text = m.content;
                                     }
                                 } catch (e) {
                                     text = m.content;
                                 }
                             }
                             
                             return {
                                 id: m.id,
                                 text: text,
                                 type: isOutgoing ? 'outgoing' : 'incoming',
                                 senderName: m.sender_name,
                                 status: status,
                                 messageType: m.message_type || 'text',
                                 duration: duration,
                                 url: url,
                                 time: new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                             };
                         });
                        ui.renderMessages(formatted);
                        
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

                }
                
                // Update sidebar with new message
                const group = manager.groups.find(g => g.id === groupId);
                if (group) {
                    group.lastMessage = '🎤 Voice message';
                    group.time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    ui.renderContacts(manager.contacts, manager.groups);
                }
            }
        }
    });

    // Logout on unload
    window.addEventListener('beforeunload', () => {
        fetch('api/online_sessions.php?action=logout', {
            method: 'GET',
            credentials: 'include',
            keepalive: true
        });
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
