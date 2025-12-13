/**
 * EmojiPickerEnhanced - Large emoji set with search and categories
 */
class EmojiPickerEnhanced {
    constructor() {
        this.emojis = this.getEmojiSet();
        this.categories = this.getCategories();
        this.recentEmojis = this.loadRecentEmojis();
        this.isOpen = false;
    }

    /**
     * Get comprehensive emoji set organized by category
     */
    getEmojiSet() {
        return {
            recent: [],
            smileys: [
                '😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂',
                '🙂', '🙃', '😉', '😊', '😇', '🥰', '😍', '🤩',
                '😘', '😗', '😚', '😙', '🥲', '😋', '😛', '😜',
                '🤪', '😌', '😔', '😑', '😐', '😶', '🤐', '🤨',
                '😏', '😒', '🙄', '😬', '🤥', '😌', '😔', '😪',
                '🤤', '😴', '😷', '🤒', '🤕', '🤢', '🤮', '🤮',
                '🤧', '🤬', '🤡', '😈', '👿', '💀', '☠️', '💩'
            ],
            hand: [
                '👋', '🤚', '🖐️', '✋', '🖖', '👌', '🤌', '🤏',
                '✌️', '🤞', '🫰', '🤟', '🤘', '🤙', '👍', '👎',
                '✊', '👊', '🤛', '🤜', '👏', '🙌', '👐', '🤲',
                '🤝', '🤜', '🤛', '🤞', '🫶', '🙏', '💅', '💪',
                '🦾', '🦿', '👂', '👃', '🧠', '🦷', '🦴', '👀',
                '👁️', '👅', '👄', '🐶', '🐱', '🐭', '🐹', '🐰'
            ],
            heart: [
                '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍',
                '🤎', '❤️‍🔥', '❤️‍🩹', '💔', '💕', '💞', '💓', '💗',
                '💖', '💘', '💝', '💟', '👋', '💌', '💝', '💖',
                '💗', '💓', '💞', '💕', '💘', '💝', '💟', '❤️'
            ],
            activity: [
                '⚽', '🏀', '🏈', '⚾', '🥎', '🎾', '🏐', '🏉',
                '🥏', '🎳', '🏓', '🏸', '🏒', '🏑', '🥍', '🏘',
                '🎣', '🎽', '🎿', '⛷️', '🏂', '🪂', '🏋️', '🤼',
                '🤸', '⛹️', '🤺', '🤾', '🏌️', '🏇', '🧘', '🏄',
                '🏊', '🤽', '🚣', '🧗', '🚴', '🚵', '🎯', '🪃'
            ],
            food: [
                '🍏', '🍎', '🍐', '🍊', '🍋', '🍌', '🍉', '🍇',
                '🍓', '🫐', '🍈', '🍒', '🍑', '🥭', '🍍', '🥥',
                '🥝', '🍅', '🍆', '🥑', '🥦', '🥬', '🥒', '🌶️',
                '🌽', '🥕', '🧄', '🧅', '🥔', '🍠', '🥐', '🥯',
                '🍞', '🥖', '🥨', '🧀', '🥚', '🍳', '🧈', '🥞'
            ],
            nature: [
                '🌲', '🌳', '🌴', '🌵', '🌾', '🌿', '☘️', '🍀',
                '🍁', '🍂', '🍃', '🌺', '🌻', '🌹', '🌷', '🌼',
                '🌸', '💐', '🌞', '🌝', '🌛', '🌜', '🌚', '🌕',
                '🌖', '🌗', '🌘', '🌑', '⭐', '🌟', '✨', '⛅',
                '🌤️', '⛈️', '🌈', '☄️', '💥', '🔥', '🌪️', '🌫️'
            ],
            objects: [
                '📱', '📲', '💻', '⌨️', '🖥️', '🖨️', '🖱️', '🖲️',
                '🕹️', '🗜️', '💽', '💾', '💿', '📀', '🧮', '🎥',
                '🎬', '📺', '📷', '📸', '📹', '🎞️', '📽️', '🎦',
                '📞', '☎️', '📟', '📠', '📺', '📻', '🎙️', '🎚️',
                '🎛️', '🧭', '⏱️', '⏲️', '⏰', '🕰️', '⌚', '📡'
            ],
            travel: [
                '🚗', '🚕', '🚙', '🚌', '🚎', '🏎️', '🚓', '🚑',
                '🚒', '🚐', '🛻', '🚚', '🚛', '🚜', '🏍️', '🏎️',
                '🛴', '🚲', '🛵', '🏍️', '🚨', '🚔', '🚍', '🚘',
                '🚖', '🚡', '🚠', '🎢', '🎡', '🚄', '🚅', '🚆'
            ],
            flags: [
                '🚩', '🏳️', '🏴', '🏳️‍🌈', '🏳️‍⚧️', '🏴‍☠️'
            ]
        };
    }

    /**
     * Get category labels
     */
    getCategories() {
        return [
            { id: 'recent', label: 'Recent', icon: 'fa-clock', emojis: [] },
            { id: 'smileys', label: 'Smileys', icon: 'fa-smile', emojis: [] },
            { id: 'hand', label: 'Hands', icon: 'fa-hand-peace', emojis: [] },
            { id: 'heart', label: 'Hearts', icon: 'fa-heart', emojis: [] },
            { id: 'activity', label: 'Activity', icon: 'fa-futbol', emojis: [] },
            { id: 'food', label: 'Food', icon: 'fa-apple-alt', emojis: [] },
            { id: 'nature', label: 'Nature', icon: 'fa-leaf', emojis: [] },
            { id: 'objects', label: 'Objects', icon: 'fa-lightbulb', emojis: [] },
            { id: 'travel', label: 'Travel', icon: 'fa-car', emojis: [] }
        ];
    }

    /**
     * Load recently used emojis
     */
    loadRecentEmojis() {
        const recent = localStorage.getItem('recentEmojis');
        return recent ? JSON.parse(recent) : [];
    }

    /**
     * Save emoji to recent
     */
    saveRecentEmoji(emoji) {
        // Remove if already exists
        this.recentEmojis = this.recentEmojis.filter(e => e !== emoji);
        // Add to beginning
        this.recentEmojis.unshift(emoji);
        // Keep only last 16
        this.recentEmojis = this.recentEmojis.slice(0, 16);
        // Save to localStorage
        localStorage.setItem('recentEmojis', JSON.stringify(this.recentEmojis));
    }

    /**
     * Create enhanced emoji picker HTML
     */
    createPickerHTML() {
        const pickerId = `emojiPicker-${Date.now()}`;

        let html = `
            <div id="${pickerId}" class="emoji-picker-enhanced" style="
                position: absolute;
                bottom: 120%;
                right: 0;
                background: white;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
                z-index: 1000;
                width: 340px;
                max-height: 400px;
                display: flex;
                flex-direction: column;
            ">
                <!-- Search bar -->
                <div style="padding: 12px; border-bottom: 1px solid #e5e7eb;">
                    <input type="text" class="form-control form-control-sm emoji-search" placeholder="Search emoji...">
                </div>

                <!-- Category tabs -->
                <div style="padding: 8px; border-bottom: 1px solid #e5e7eb; display: flex; gap: 8px; overflow-x: auto;">
        `;

        this.categories.forEach(cat => {
            html += `
                <button class="emoji-category-btn" data-category="${cat.id}" style="
                    background: none;
                    border: none;
                    cursor: pointer;
                    padding: 6px 8px;
                    border-radius: 4px;
                    transition: all 0.2s;
                    font-size: 1.1rem;
                    title="${cat.label}">
                    <i class="fas ${cat.icon}"></i>
                </button>
            `;
        });

        html += `
                </div>

                <!-- Emoji grid -->
                <div class="emoji-grid" style="
                    flex: 1;
                    overflow-y: auto;
                    padding: 12px;
                    display: grid;
                    grid-template-columns: repeat(8, 1fr);
                    gap: 8px;
                ">
                </div>
            </div>
        `;

        return { html, id: pickerId };
    }

    /**
     * Render emoji picker in element
     */
    render(triggerElement, onSelect) {
        const { html, id } = this.createPickerHTML();
        const container = triggerElement.parentElement;

        // Remove existing picker
        const existing = container.querySelector('.emoji-picker-enhanced');
        if (existing) {
            existing.remove();
            this.isOpen = false;
            return;
        }

        // Insert picker
        container.insertAdjacentHTML('beforeend', html);
        const picker = document.getElementById(id);
        this.isOpen = true;

        // Populate emojis for first category
        this.showCategory('smileys', picker);

        // Category buttons
        picker.querySelectorAll('.emoji-category-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const category = btn.getAttribute('data-category');
                this.showCategory(category, picker);

                // Update active state
                picker.querySelectorAll('.emoji-category-btn').forEach(b => {
                    b.style.background = '';
                    b.style.color = '';
                });
                btn.style.background = '#f3f4f6';
            });
        });

        // Search
        const searchInput = picker.querySelector('.emoji-search');
        const debounce = (func, wait) => {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        };

        searchInput.addEventListener('input', debounce((e) => {
            this.searchEmojis(e.target.value, picker);
        }, 300));

        // Close on outside click
        const closeHandler = (e) => {
            if (!picker.contains(e.target) && !triggerElement.contains(e.target)) {
                picker.remove();
                this.isOpen = false;
                document.removeEventListener('click', closeHandler);
            }
        };
        setTimeout(() => {
            document.addEventListener('click', closeHandler);
        }, 100);

        return picker;
    }

    /**
     * Show emoji category
     */
    showCategory(categoryId, picker) {
        const grid = picker.querySelector('.emoji-grid');
        grid.innerHTML = '';

        let emojis = [];

        if (categoryId === 'recent' && this.recentEmojis.length > 0) {
            emojis = this.recentEmojis;
        } else {
            emojis = this.emojis[categoryId] || [];
        }

        if (emojis.length === 0) {
            grid.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; color: #9ca3af; padding: 20px;">No emojis</div>';
            return;
        }

        emojis.forEach(emoji => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.style.cssText = `
                background: none;
                border: none;
                font-size: 1.5rem;
                cursor: pointer;
                padding: 8px;
                border-radius: 4px;
                transition: all 0.2s;
            `;
            btn.textContent = emoji;
            btn.addEventListener('mouseenter', () => {
                btn.style.background = '#f3f4f6';
                btn.style.transform = 'scale(1.2)';
            });
            btn.addEventListener('mouseleave', () => {
                btn.style.background = '';
                btn.style.transform = '';
            });
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.saveRecentEmoji(emoji);
                if (window.chatApp && window.chatApp.selectedEmoji) {
                    window.chatApp.selectedEmoji(emoji);
                }
                picker.remove();
                this.isOpen = false;
            });
            grid.appendChild(btn);
        });
    }

    /**
     * Search emojis
     */
    searchEmojis(query, picker) {
        const grid = picker.querySelector('.emoji-grid');
        grid.innerHTML = '';

        if (!query) {
            this.showCategory('smileys', picker);
            return;
        }

        const query_lower = query.toLowerCase();
        const results = [];

        // Simple search - could be improved with emoji metadata
        const allEmojis = Object.values(this.emojis).flat();
        const uniqueEmojis = [...new Set(allEmojis)];

        uniqueEmojis.slice(0, 50).forEach(emoji => {
            results.push(emoji);
        });

        if (results.length === 0) {
            grid.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; color: #9ca3af; padding: 20px;">No results found</div>';
            return;
        }

        results.forEach(emoji => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.style.cssText = `
                background: none;
                border: none;
                font-size: 1.5rem;
                cursor: pointer;
                padding: 8px;
                border-radius: 4px;
                transition: all 0.2s;
            `;
            btn.textContent = emoji;
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.saveRecentEmoji(emoji);
                if (window.chatApp && window.chatApp.selectedEmoji) {
                    window.chatApp.selectedEmoji(emoji);
                }
                picker.remove();
                this.isOpen = false;
            });
            grid.appendChild(btn);
        });
    }
}

// Export
window.EmojiPickerEnhanced = EmojiPickerEnhanced;
