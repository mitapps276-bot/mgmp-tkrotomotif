<!-- Chat Modal -->
<style>
    @media (max-width: 480px) {
        #privateChatBox {
            width: calc(100% - 40px) !important;
            height: 80vh !important;
            max-height: none !important;
            border-radius: 12px !important;
        }
    }
    @media (max-height: 600px) {
        #privateChatBox {
            height: calc(100vh - 40px) !important;
            max-height: none !important;
            width: 350px !important;
            max-width: 100% !important;
        }
    }
    @media (max-height: 450px) {
        #privateChatBox {
            height: 100vh !important;
            width: 100vw !important;
            border-radius: 0 !important;
            margin: 0 !important;
        }
    }
</style>
<div id="privateChatModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(4px);">
    <div id="privateChatBox" style="background:white; width:350px; height:450px; max-width:100%; border-radius:12px; display:flex; flex-direction:column; box-shadow:0 15px 35px rgba(0,0,0,0.2); overflow:hidden;">
        <!-- Header -->
        <div style="padding:15px 20px; background:#f8f9fa; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h3 style="margin:0; font-size:18px; color:#2c3e50; font-weight:800; display:flex; align-items:center; gap:8px;">
                    <span style="font-size:20px;">💬</span> <span id="chatTargetName">Loading...</span>
                </h3>
            </div>
            <button onclick="closeChatModal()" style="background:none !important; border:none !important; font-size:24px; color:#7f8c8d; cursor:pointer; line-height:1; width:auto !important; padding:0 !important; margin:0 !important;">&times;</button>
        </div>
        
        <!-- Body / Message List -->
        <div id="chatMessageList" style="flex:1; padding:20px; overflow-y:auto; background:#fbfcfd; display:flex; flex-direction:column; gap:15px;">
            <!-- Messages will be rendered here via AJAX -->
        </div>
        
        <!-- Footer / Input Form -->
        <div style="padding:15px 20px; background:#fff; border-top:1px solid #eee;">
            <form id="chatForm" onsubmit="sendChatMessage(event)" style="display:flex; gap:10px; margin:0; padding:0;">
                <input type="hidden" id="chatTargetId" value="">
                <textarea id="chatInputMessage" placeholder="Ketik pesan..." required style="flex:1; padding:12px !important; margin:0 !important; border:1px solid #ddd; border-radius:8px; font-family:inherit; resize:none; font-size:14px; height:45px; min-height:45px !important; width:auto !important;"></textarea>
                <button type="submit" id="btnSendChat" style="background:#2ecc71 !important; color:white; border:none !important; padding:0 20px !important; margin:0 !important; border-radius:8px; font-weight:bold; cursor:pointer; font-size:14px; display:flex; align-items:center; justify-content:center; width:auto !important; min-width:80px;">Kirim</button>
            </form>
        </div>
    </div>
</div>

<script>
let chatPollingInterval = null;
let currentChatTarget = null;
let lastMessageCount = 0;

function openChatModal(targetId, targetName) {
    document.getElementById('chatTargetId').value = targetId;
    document.getElementById('chatTargetName').innerText = targetName;
    document.getElementById('privateChatModal').style.display = 'flex';
    document.getElementById('chatMessageList').innerHTML = '<div style="text-align:center; padding:20px; color:#7f8c8d;">Memuat percakapan...</div>';
    
    currentChatTarget = targetId;
    lastMessageCount = 0;
    
    // Sembunyikan badge unread jika ada
    let badge = document.getElementById('badge_unread_' + targetId);
    if (badge) {
        badge.style.display = 'none';
    }
    
    // Fetch immediately
    fetchChatMessages(true);
    
    // Start polling every 5 seconds
    if (chatPollingInterval) clearInterval(chatPollingInterval);
    chatPollingInterval = setInterval(() => fetchChatMessages(false), 5000);
}

function closeChatModal() {
    document.getElementById('privateChatModal').style.display = 'none';
    currentChatTarget = null;
    if (chatPollingInterval) {
        clearInterval(chatPollingInterval);
        chatPollingInterval = null;
    }
}

// Global polling untuk unread badge setiap 10 detik
setInterval(() => {
    fetch('api_unread.php')
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                let unreadData = res.data;
                // Ambil semua wrapper tombol chat di dashboard
                let wrappers = document.querySelectorAll('[id^="chat_btn_wrapper_"]');
                wrappers.forEach(wrapper => {
                    let userId = wrapper.id.replace('chat_btn_wrapper_', '');
                    let count = unreadData[userId] ? parseInt(unreadData[userId]) : 0;
                    
                    let badge = document.getElementById('badge_unread_' + userId);
                    if (count > 0) {
                        let displayCount = count > 99 ? '99+' : count;
                        if (badge) {
                            badge.innerText = displayCount;
                            badge.style.display = 'inline-block';
                        } else {
                            let newBadge = document.createElement('span');
                            newBadge.id = 'badge_unread_' + userId;
                            newBadge.style.cssText = 'position:absolute; top:-8px; right:-8px; background:#e74c3c; color:white; font-size:10px; font-weight:bold; padding:2px 6px; border-radius:10px; border:1px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.2);';
                            newBadge.innerText = displayCount;
                            wrapper.appendChild(newBadge);
                        }
                    } else {
                        if (badge) {
                            badge.style.display = 'none';
                        }
                    }
                });
            }
        })
        .catch(err => console.error('Error fetching unread counts:', err));
}, 10000);

function fetchChatMessages(scrollToBottom = false) {
    if (!currentChatTarget) return;
    
    fetch('api_chat.php?target_id=' + currentChatTarget)
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success') {
                renderMessages(res.data, scrollToBottom);
            }
        })
        .catch(err => console.error('Error fetching chat:', err));
}

function renderMessages(messages, forceScroll) {
    const list = document.getElementById('chatMessageList');
    
    if (messages.length === 0) {
        list.innerHTML = '<div style="text-align:center; padding:20px; color:#bdc3c7; font-size:14px;">Belum ada pesan. Mulai sapa sekarang!</div>';
        return;
    }
    
    // Only re-render if count changed to avoid flickering
    if (!forceScroll && messages.length === lastMessageCount) {
        return; 
    }
    
    lastMessageCount = messages.length;
    let html = '';
    
    messages.forEach(msg => {
        if (msg.is_me) {
            html += `
                <div style="align-self:flex-end; max-width:80%;">
                    <div style="background:#dcf8c6; padding:10px 15px; border-radius:12px 0 12px 12px; color:#2c3e50; font-size:14px; box-shadow:0 1px 2px rgba(0,0,0,0.05); position:relative; word-wrap:break-word; white-space:pre-wrap;">${escapeHtml(msg.message_text)}</div>
                    <div style="font-size:10px; color:#95a5a6; text-align:right; margin-top:4px; display:flex; justify-content:flex-end; gap:5px;">
                        ${msg.formatted_time} 
                        ${msg.is_read == 1 ? '<span style="color:#3498db;">✓✓</span>' : '<span style="color:#bdc3c7;">✓</span>'}
                    </div>
                </div>
            `;
        } else {
            html += `
                <div style="align-self:flex-start; max-width:80%;">
                    <div style="background:#fff; padding:10px 15px; border-radius:0 12px 12px 12px; color:#2c3e50; font-size:14px; border:1px solid #edf0f2; box-shadow:0 1px 2px rgba(0,0,0,0.05); position:relative; word-wrap:break-word; white-space:pre-wrap;">${escapeHtml(msg.message_text)}</div>
                    <div style="font-size:10px; color:#95a5a6; text-align:left; margin-top:4px;">${msg.formatted_time}</div>
                </div>
            `;
        }
    });
    
    list.innerHTML = html;
    
    // Scroll to bottom
    list.scrollTop = list.scrollHeight;
}

function sendChatMessage(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSendChat');
    const input = document.getElementById('chatInputMessage');
    const targetId = document.getElementById('chatTargetId').value;
    const msgText = input.value.trim();
    
    if (!msgText || !targetId) return;
    
    btn.disabled = true;
    btn.innerText = '...';
    
    const formData = new FormData();
    formData.append('target_id', targetId);
    formData.append('message_text', msgText);
    
    fetch('api_chat.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(res => {
        if (res.status === 'success') {
            input.value = '';
            fetchChatMessages(true);
        } else {
            alert('Gagal mengirim pesan: ' + res.message);
        }
    })
    .catch(err => {
        alert('Terjadi kesalahan koneksi.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerText = 'Kirim';
        input.focus();
    });
}

function escapeHtml(unsafe) {
    return unsafe
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}

// Enter to submit (Shift+Enter for newline)
document.getElementById('chatInputMessage').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.getElementById('chatForm').dispatchEvent(new Event('submit'));
    }
});
</script>
