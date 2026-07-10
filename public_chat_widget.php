<!-- Widget Tombol Floating Obrolan Publik -->
<div id="publicChatWidgetBtn" onclick="togglePublicChat()" style="position:fixed; bottom:30px; right:30px; width:60px; height:60px; background:#e67e22; border-radius:50%; box-shadow:0 4px 10px rgba(0,0,0,0.3); display:flex; justify-content:center; align-items:center; cursor:pointer; z-index:9998; transition:transform 0.3s;">
    <span style="font-size:24px; color:white;">📢</span>
    <span id="publicUnreadBadge" style="display:none; position:absolute; top:-5px; right:-5px; background:#e74c3c; color:white; font-size:12px; font-weight:bold; padding:2px 8px; border-radius:10px; border:2px solid white;">Baru</span>
</div>

<!-- Kotak Obrolan Publik -->
<div id="publicChatBox" style="display:none; position:fixed; bottom:100px; right:30px; width:350px; height:450px; background:white; border-radius:12px; box-shadow:0 5px 25px rgba(0,0,0,0.2); flex-direction:column; z-index:9999; overflow:hidden;">
    <div style="background:#e67e22; padding:15px; color:white; display:flex; justify-content:space-between; align-items:center;">
        <div style="display:flex; align-items:center; gap:10px;">
            <span style="font-size:20px;">📢</span>
            <h3 style="margin:0; font-size:16px;">Ruang Diskusi Terbuka</h3>
        </div>
        <button onclick="togglePublicChat()" style="background:transparent; border:none; color:white; font-size:20px; cursor:pointer;">&times;</button>
    </div>
    
    <div id="publicMessageList" style="flex:1; padding:15px; overflow-y:auto; background:#f5f6fa; display:flex; flex-direction:column; gap:10px;">
        <div style="text-align:center; color:#7f8c8d; font-size:12px; margin-top:20px;">Memuat diskusi...</div>
    </div>
    
    <div style="padding:15px; border-top:1px solid #eee; background:white;">
        <form id="publicChatForm" onsubmit="sendPublicMessage(event)" style="display:flex; gap:10px;">
            <input type="text" id="publicMessageInput" placeholder="Ketik pesan untuk semua..." autocomplete="off" style="flex:1; padding:10px; border:1px solid #ccc; border-radius:8px; outline:none; font-family:inherit;">
            <button type="submit" style="background:#e67e22; color:white; border:none; padding:10px 15px; border-radius:8px; cursor:pointer; font-weight:bold;">Kirim</button>
        </form>
    </div>
</div>

<script>
let publicChatPolling = null;
let isPublicChatOpen = false;
let lastPublicMessageId = 0; // Untuk mendeteksi pesan baru dan auto-scroll

function togglePublicChat() {
    isPublicChatOpen = !isPublicChatOpen;
    const box = document.getElementById('publicChatBox');
    const btn = document.getElementById('publicChatWidgetBtn');
    const badge = document.getElementById('publicUnreadBadge');
    
    if (isPublicChatOpen) {
        box.style.display = 'flex';
        btn.style.transform = 'scale(0.9)';
        badge.style.display = 'none'; // Sembunyikan notifikasi saat dibuka
        
        // Mulai polling jika belum jalan
        if (!publicChatPolling) {
            fetchPublicMessages(true); // Fetch awal dan scroll ke bawah
            publicChatPolling = setInterval(() => fetchPublicMessages(false), 4000);
        }
    } else {
        box.style.display = 'none';
        btn.style.transform = 'scale(1)';
        // Kita biarkan polling berjalan di background agar badge bisa muncul jika ada pesan baru
        // atau kita matikan polling jika tidak mau background fetch?
        // Mari kita biarkan jalan agar badge "Baru" bisa muncul.
    }
}

// Karena kita butuh fitur Badge "Baru" saat ditutup, kita jalankan polling diam-diam sejak awal.
window.addEventListener('DOMContentLoaded', () => {
    publicChatPolling = setInterval(() => fetchPublicMessages(false), 5000);
    // Fetch awal sekali tanpa buka
    fetchPublicMessages(false);
});

function fetchPublicMessages(scrollToBottom = false) {
    fetch('api_public_chat.php')
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                const msgs = res.data;
                const list = document.getElementById('publicMessageList');
                let html = '';
                
                let latestId = 0;
                if (msgs.length > 0) {
                    latestId = parseInt(msgs[msgs.length - 1].id);
                }
                
                if (msgs.length === 0) {
                    html = '<div style="text-align:center; color:#7f8c8d; font-size:12px; margin-top:20px;">Belum ada diskusi publik. Jadilah yang pertama menyapa!</div>';
                } else {
                    msgs.forEach(m => {
                        const isMe = m.is_me;
                        const align = isMe ? 'flex-end' : 'flex-start';
                        const bg = isMe ? '#e67e22' : '#ffffff';
                        const color = isMe ? 'white' : '#2c3e50';
                        const nameColor = isMe ? '#f1c40f' : '#2980b9';
                        
                        let photoHtml = '';
                        if (!isMe) {
                            if (m.profile_photo) {
                                photoHtml = `<img src="${m.profile_photo}" style="width:30px; height:30px; border-radius:50%; object-fit:cover;">`;
                            } else {
                                photoHtml = `<div style="width:30px; height:30px; border-radius:50%; background:#2c3e50; color:white; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:bold;">${m.full_name.charAt(0).toUpperCase()}</div>`;
                            }
                        }

                        html += `
                            <div style="display:flex; flex-direction:column; align-items:${align}; margin-bottom:5px;">
                                <div style="display:flex; gap:8px; max-width:85%; flex-direction:${isMe ? 'row-reverse' : 'row'};">
                                    ${photoHtml}
                                    <div style="background:${bg}; color:${color}; padding:8px 12px; border-radius:12px; box-shadow:0 1px 2px rgba(0,0,0,0.1); word-break:break-word;">
                                        ${!isMe ? `<div style="font-size:11px; font-weight:bold; color:${nameColor}; margin-bottom:4px;">${m.full_name}</div>` : ''}
                                        <div style="font-size:13px; line-height:1.4;">${m.message_text}</div>
                                        <div style="font-size:10px; color:${isMe ? '#fdebd0' : '#95a5a6'}; text-align:right; margin-top:4px;">${m.formatted_time}</div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                }
                
                list.innerHTML = html;
                
                // Deteksi pesan baru
                if (latestId > lastPublicMessageId) {
                    if (isPublicChatOpen || scrollToBottom) {
                        list.scrollTop = list.scrollHeight;
                    } else if (lastPublicMessageId !== 0) {
                        // Tampilkan badge "Baru" jika ada pesan masuk saat ditutup (kecuali saat load pertama)
                        document.getElementById('publicUnreadBadge').style.display = 'inline-block';
                    }
                    lastPublicMessageId = latestId;
                }
            }
        })
        .catch(err => console.error(err));
}

function sendPublicMessage(e) {
    e.preventDefault();
    const input = document.getElementById('publicMessageInput');
    const msg = input.value.trim();
    if (!msg) return;
    
    input.value = '';
    
    const formData = new FormData();
    formData.append('message_text', msg);
    
    fetch('api_public_chat.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success') {
            fetchPublicMessages(true); // Force fetch and scroll
        } else {
            alert('Gagal mengirim pesan: ' + res.message);
        }
    })
    .catch(err => console.error(err));
}
</script>
