document.addEventListener('DOMContentLoaded', () => {
    // Utility: Debounce function to limit frequent executions
    function debounce(func, wait) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // Search functionality: filter repair rows based on input
    const repairSearchInput = document.getElementById('repairSearch');
    if (repairSearchInput) {
        const rows = Array.from(document.querySelectorAll('.repair-table tbody tr'));
        const rowData = rows.map(row => ({
            element: row,
            text: row.textContent.toLowerCase().trim(), // Cache text content once
        }));

        const applySearch = debounce(() => {
            const filter = repairSearchInput.value.toLowerCase();
            rowData.forEach(({ element, text }) => {
                element.style.display = text.includes(filter) ? '' : 'none';
            });
        }, 300);

        repairSearchInput.addEventListener('input', applySearch);
        applySearch(); // Initial call
    }

    // Row click functionality: navigate to repair details when a row is clicked
    const clickableRows = document.querySelectorAll('.repair-table tbody tr[data-url]');
    clickableRows.forEach(row => {
        row.style.cursor = 'pointer';
        row.addEventListener('click', (e) => {
            if (e.target.tagName.toLowerCase() !== 'a' && !e.target.closest('a')) {
                const url = row.getAttribute('data-url');
                if (url) {
                    window.location.href = url;
                }
            }
        });
    });

    // Sort by priority when clicking ID header
    const idHeader = document.querySelector('.repair-table thead th:first-child');
    const tbody = document.querySelector('.repair-table tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    let sortDirection = 1;
    if (idHeader) {
        idHeader.style.cursor = 'pointer';
        idHeader.addEventListener('click', () => {
            const visibleRows = rows.filter(row => row.style.display !== 'none');
            const hiddenRows = rows.filter(row => row.style.display === 'none');
            visibleRows.sort((a, b) => {
                const aPriority = a.querySelector('.priority-dot')?.classList[1]?.split('-')[1] || 'normal';
                const bPriority = b.querySelector('.priority-dot')?.classList[1]?.split('-')[1] || 'normal';
                const order = { 'high': 3, 'normal': 2, 'low': 1 };
                return (order[aPriority] - order[bPriority]) * sortDirection;
            });
            sortDirection *= -1;

            const fragment = document.createDocumentFragment();
            visibleRows.concat(hiddenRows).forEach(row => fragment.appendChild(row));
            tbody.appendChild(fragment);
        });
    }

    // Right-click context menu (pre-built and reused)
    const contextMenu = document.createElement('div');
    contextMenu.className = 'context-menu';
    contextMenu.style.position = 'absolute';
    contextMenu.style.background = '#fff';
    contextMenu.style.border = '1px solid #ccc';
    contextMenu.style.borderRadius = '4px';
    contextMenu.style.boxShadow = '0 2px 5px rgba(0,0,0,0.2)';
    contextMenu.style.zIndex = '1000';
    contextMenu.style.padding = '5px 0';
    contextMenu.style.minWidth = '150px';
    contextMenu.style.display = 'none';
    contextMenu.innerHTML = `
        <div class="context-menu-item" data-action="open">Otevřít v slotu</div>
        <div class="context-menu-item" data-action="note">Přidat poznámku</div>
    `;
    document.body.appendChild(contextMenu);

    function showContextMenu(x, y) {
        contextMenu.style.left = `${x}px`;
        contextMenu.style.top = `${y}px`;
        contextMenu.style.display = 'block';
    }

    tbody.addEventListener('contextmenu', (e) => {
        e.preventDefault();
        const row = e.target.closest('tr');
        if (row) {
            const repairId = row.getAttribute('data-repair-id');
            const url = row.getAttribute('data-url');
            contextMenu.dataset.repairId = repairId;
            contextMenu.dataset.url = url;
            showContextMenu(e.pageX, e.pageY);
        }
    });

    contextMenu.addEventListener('click', (e) => {
        const action = e.target.dataset.action;
        const repairId = contextMenu.dataset.repairId;
        const url = contextMenu.dataset.url;
        if (action === 'open') openInSlot(url);
        if (action === 'note') addNote(repairId);
    });

    // Hide context menu on outside click
    document.addEventListener('click', (e) => {
        if (!contextMenu.contains(e.target)) {
            contextMenu.style.display = 'none';
        }
    });

    // Open Repair in Available Slot
    function openInSlot(url) {
        contextMenu.style.display = 'none';
        if (url) {
            window.location.href = url;
        }
    }

    // Add Note Function with Modal
    function addNote(repairId) {
        contextMenu.style.display = 'none';
        const modal = document.createElement('div');
        modal.className = 'note-modal';
        modal.style.position = 'fixed';
        modal.style.top = '50%';
        modal.style.left = '50%';
        modal.style.transform = 'translate(-50%, -50%)';
        modal.style.background = '#fff';
        modal.style.padding = '20px';
        modal.style.border = '1px solid #ccc';
        modal.style.borderRadius = '5px';
        modal.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
        modal.style.zIndex = '1000';
        modal.style.width = '300px';
        modal.innerHTML = `
            <h4>Přidat poznámku</h4>
            <textarea class="form-control" rows="3" id="noteText" placeholder="Zadejte poznámku..."></textarea>
            <button class="btn btn-primary mt-2" onclick="saveNote(${repairId})">Uložit</button>
            <button class="btn btn-secondary mt-2 ms-2" onclick="this.parentElement.remove()">Zavřít</button>
        `;
        document.body.appendChild(modal);

        document.addEventListener('click', function closeModal(e) {
            if (!modal.contains(e.target)) {
                modal.remove();
                document.removeEventListener('click', closeModal);
            }
        }, { once: true });
    }

    // Save Note Function (global for button onclick)
    window.saveNote = function(repairId) {
        const noteText = document.getElementById('noteText').value;
        fetch(`/save-note?repairId=${repairId}&note=${encodeURIComponent(noteText)}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Poznámka uložena.');
                document.querySelector('.note-modal').remove();
            } else {
                alert('Nepodařilo se uložit poznámku.');
            }
        })
        .catch(error => console.error('Error:', error));
    };

    // Lazy load chat messages with Intersection Observer
    const currentUserId = document.querySelector('meta[name="current-user-id"]')?.content;
    if (currentUserId) {
        function loadChatMessages(repairId, container) {
            fetch(`/get-chat-messages?repairId=${repairId}`)
                .then(response => response.json())
                .then(messages => {
                    container.innerHTML = messages.map(msg => `
                        <div class="chat-message">
                            <strong class="${msg.sender_id === currentUserId ? 'sender-me' : 'sender-other'}">
                                ${msg.sender_id === currentUserId ? 'Vy' : 'Zákazník'}
                            </strong>: ${msg.message}
                            <small class="chat-time">${new Date(msg.created_at).toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' })}</small>
                        </div>
                    `).join('');
                })
                .catch(error => console.error('Error loading chat:', error));
        }

        document.querySelectorAll('.chat-section').forEach(section => {
            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) {
                    const repairId = section.dataset.repairId;
                    if (repairId) {
                        loadChatMessages(repairId, section.querySelector('.chat-messages'));
                    }
                    observer.disconnect();
                }
            }, { threshold: 0.1 });
            observer.observe(section);
        });
    }
});