<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$db      = getDB();
$user_id = (int)$_SESSION['user_id'];
$user    = getCurrentUser();

// Check premium
$sub        = getUserSubscription($user_id);
$is_premium = $sub && $sub['slug'] === 'high_waltage' && $sub['expires_at'] > time();

renderHeader('პრემიუმ ჩათი', 'chat');
?>
<style>
.chat-wrap { max-width: 680px; margin: 0 auto; display: flex; flex-direction: column; height: calc(100vh - 160px); }
.chat-header { display: flex; align-items: center; justify-content: space-between; padding: .75rem 1rem; background: #fff; border: 1px solid var(--gray-200); border-radius: 14px 14px 0 0; }
.chat-title { font-weight: 500; font-size: 15px; }
.online-badge { font-size: 11px; background: #E1F5EE; color: #0F6E56; padding: 3px 10px; border-radius: 99px; font-weight: 500; }
.chat-messages { flex: 1; overflow-y: auto; padding: 1rem; background: #F8F7F2; border-left: 1px solid var(--gray-200); border-right: 1px solid var(--gray-200); display: flex; flex-direction: column; gap: 10px; scroll-behavior: smooth; }
.msg-wrap { display: flex; flex-direction: column; max-width: 80%; }
.msg-wrap.mine { align-self: flex-end; align-items: flex-end; }
.msg-wrap.theirs { align-self: flex-start; align-items: flex-start; }
.msg-name { font-size: 11px; color: var(--gray-400); margin-bottom: 2px; }
.msg-bubble { padding: 10px 14px; border-radius: 14px; font-size: 14px; line-height: 1.5; word-break: break-word; }
.msg-wrap.mine   .msg-bubble { background: #1D9E75; color: #fff; border-radius: 14px 14px 4px 14px; }
.msg-wrap.theirs .msg-bubble { background: #fff; border: 1px solid var(--gray-200); border-radius: 14px 14px 14px 4px; }
.msg-time { font-size: 10px; color: var(--gray-400); margin-top: 2px; }
.chat-input-row { display: flex; gap: 8px; padding: .75rem 1rem; background: #fff; border: 1px solid var(--gray-200); border-top: none; border-radius: 0 0 14px 14px; align-items: flex-end; }
.chat-textarea { flex: 1; border: 1px solid var(--gray-200); border-radius: 10px; padding: 10px 14px; font-family: 'DM Sans', sans-serif; font-size: 14px; resize: none; height: 44px; max-height: 120px; line-height: 1.5; outline: none; transition: border-color .15s; }
.chat-textarea:focus { border-color: #1D9E75; }
.chat-send { background: #1D9E75; color: #fff; border: none; border-radius: 10px; width: 44px; height: 44px; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: background .15s; }
.chat-send:hover { background: #0F6E56; }
.chat-send:disabled { opacity: .4; cursor: not-allowed; }
.date-divider { text-align: center; font-size: 11px; color: var(--gray-400); padding: 4px 0; }
.lock-screen { text-align: center; padding: 3rem 1rem; }
.typing-dot { width: 6px; height: 6px; background: var(--gray-400); border-radius: 50%; display: inline-block; margin: 0 2px; animation: tdot .9s ease-in-out infinite; }
.typing-dot:nth-child(2) { animation-delay: .2s; }
.typing-dot:nth-child(3) { animation-delay: .4s; }
@keyframes tdot { 0%,80%,100%{transform:translateY(0)} 40%{transform:translateY(-6px)} }
</style>

<?php if (!$is_premium): ?>
<!-- Lock screen -->
<div class="lock-screen">
  <div style="font-size:48px;margin-bottom:1rem;">👑</div>
  <h2 style="font-size:20px;font-weight:500;margin-bottom:.5rem;">პრემიუმ ფუნქცია</h2>
  <p style="font-size:14px;color:var(--gray-400);margin-bottom:1.5rem;max-width:360px;margin-left:auto;margin-right:auto;">
    საზოგადოების ჩათი ხელმისაწვდომია მხოლოდ <strong>High Waltage</strong> გამომწერებისთვის.
    შეუერთდი პრემიუმ საზოგადოებას!
  </p>
  <a href="/pricing.php" class="btn btn-primary" style="padding:12px 32px;">პრემიუმ გეგმა →</a>
  <?php if ($sub): ?>
    <div style="margin-top:1rem;font-size:12px;color:var(--gray-400);">
      მიმდინარე გეგმა: <?php echo sanitize($sub['name_ka']); ?>
    </div>
  <?php endif; ?>
</div>

<?php else: ?>
<!-- Chat UI -->
<div class="chat-wrap">
  <div class="chat-header">
    <div>
      <div class="chat-title">💬 პრემიუმ საზოგადოება</div>
      <div style="font-size:11px;color:var(--gray-400);">ისტორია ინახება 2 დღე</div>
    </div>
    <div class="online-badge" id="online-badge">● 0 ონლაინ</div>
  </div>

  <div class="chat-messages" id="chat-messages">
    <div style="text-align:center;padding:1rem;">
      <div class="typing-dot"></div>
      <div class="typing-dot"></div>
      <div class="typing-dot"></div>
    </div>
  </div>

  <!-- Photo preview -->
  <div id="photo-preview-bar" style="display:none;padding:6px 1rem;background:#F8F7F2;border-left:1px solid var(--gray-200);border-right:1px solid var(--gray-200);">
    <div style="position:relative;display:inline-block;">
      <img id="chat-photo-preview" style="height:80px;border-radius:8px;display:block;object-fit:cover;">
      <button onclick="clearChatPhoto()" style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;background:#1A1A18;color:#fff;border:none;border-radius:50%;cursor:pointer;font-size:11px;line-height:20px;text-align:center;padding:0;">✕</button>
    </div>
  </div>

  <div class="chat-input-row">
    <input type="file" id="chat-photo-input" accept="image/*" style="display:none;" onchange="onChatPhoto(this)">
    <button onclick="document.getElementById('chat-photo-input').click()"
            style="background:#F1EFE8;border:none;border-radius:10px;width:44px;height:44px;cursor:pointer;font-size:18px;flex-shrink:0;"
            title="ფოტოს გაგზავნა">📷</button>
    <textarea class="chat-textarea" id="chat-input"
      placeholder="დაწერე შეტყობინება..."
      onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg();}"
    ></textarea>
    <button class="chat-send" id="send-btn" onclick="sendMsg()" title="გაგზავნა">
      <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
        <path d="M15 9H3M15 9L10 4M15 9L10 14" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>
  </div>
</div>

<script>
var lastId     = 0;
var myUserId   = <?php echo $user_id; ?>;
var myName     = <?php echo json_encode($user['name']); ?>;
var pollTimer  = null;
var isSending  = false;  // prevent double-send

function escHtml(s) {
  var d = document.createElement('div');
  d.textContent = s || '';
  return d.innerHTML;
}

function renderMessages(messages, append) {
  var box  = document.getElementById('chat-messages');
  var html = append ? box.innerHTML : '';
  var lastDate = '';

  messages.forEach(function(m) {
    if (m.date !== lastDate) {
      html += '<div class="date-divider">' + escHtml(m.date) + '</div>';
      lastDate = m.date;
    }
    html += '<div class="msg-wrap ' + (m.mine ? 'mine' : 'theirs') + '">';
    if (!m.mine) html += '<div class="msg-name">' + escHtml(m.user) + '</div>';
    if (m.image) {
      html += '<div class="msg-bubble" style="padding:4px;">';
      html += '<img src="' + escHtml(m.image) + '" style="max-width:220px;border-radius:10px;display:block;" loading="lazy">';
      if (m.message) html += '<div style="padding:6px 8px 2px;font-size:14px;">' + escHtml(m.message) + '</div>';
      html += '</div>';
    } else {
      html += '<div class="msg-bubble">' + escHtml(m.message) + '</div>';
    }
    html += '<div class="msg-time">' + escHtml(m.time) + '</div>';
    html += '</div>';
    if (m.id > lastId) lastId = m.id;
  });

  if (!append && messages.length === 0) {
    html = '<div style="text-align:center;padding:2rem;color:var(--gray-400);font-size:14px;">პირველი შეტყობინება გაგზავნე! 👋</div>';
  }

  box.innerHTML = html;
  box.scrollTop = box.scrollHeight;
}

function loadMessages() {
  fetch('/api/chat.php?action=messages&since=0')
    .then(function(r){ return r.json(); })
    .then(function(data) {
      if (data.error) return;
      renderMessages(data.messages, false);
      document.getElementById('online-badge').textContent = '● ' + data.online + ' ონლაინ';
      startPolling();
    })
    .catch(function(){});
}

function pollMessages() {
  fetch('/api/chat.php?action=messages&since=' + lastId)
    .then(function(r){ return r.json(); })
    .then(function(data) {
      if (data.error) return;
      if (data.messages && data.messages.length > 0) {
        var box  = document.getElementById('chat-messages');
        var html = box.innerHTML;
        data.messages.forEach(function(m) {
          html += '<div class="msg-wrap ' + (m.mine ? 'mine' : 'theirs') + '">';
          if (!m.mine) html += '<div class="msg-name">' + escHtml(m.user) + '</div>';
          if (m.image) {
      html += '<div class="msg-bubble" style="padding:4px;">';
      html += '<img src="' + escHtml(m.image) + '" style="max-width:220px;border-radius:10px;display:block;" loading="lazy">';
      if (m.message) html += '<div style="padding:6px 8px 2px;font-size:14px;">' + escHtml(m.message) + '</div>';
      html += '</div>';
    } else {
      html += '<div class="msg-bubble">' + escHtml(m.message) + '</div>';
    }
          html += '<div class="msg-time">' + escHtml(m.time) + '</div>';
          html += '</div>';
          if (m.id > lastId) lastId = m.id;
        });
        box.innerHTML = html;
        box.scrollTop = box.scrollHeight;
      }
      document.getElementById('online-badge').textContent = '● ' + data.online + ' ონლაინ';
    })
    .catch(function(){});
}

function startPolling() {
  if (pollTimer) clearInterval(pollTimer);
  pollTimer = setInterval(pollMessages, 4000);
}

function sendMsg() {
  if (isSending) return;
  var input = document.getElementById('chat-input');
  var text  = input.value.trim();
  if (!text && !chatPhotoBase64) return;

  isSending = true;
  clearInterval(pollTimer); // pause polling while sending
  var btn = document.getElementById('send-btn');
  btn.disabled = true;
  input.value  = '';
  input.style.height = '44px';

  var payload = {message: text};
  if (chatPhotoBase64) {
    payload.image    = chatPhotoBase64;
    payload.img_type = chatPhotoType;
  }

  fetch('/api/chat.php?action=send', {
    method:  'POST',
    headers: {'Content-Type': 'application/json'},
    body:    JSON.stringify(payload)
  })
  .then(function(r){ return r.json(); })
  .then(function(data) {
    isSending = false;
    btn.disabled = false;
    startPolling(); // resume polling
    if (data.error) { alert('შეცდომა: ' + data.error); return; }
    clearChatPhoto();
    // Append own message immediately
    var box  = document.getElementById('chat-messages');
    var html = box.innerHTML;
    html += '<div class="msg-wrap mine">';
    if (data.image) {
      html += '<div class="msg-bubble" style="padding:4px;">';
      html += '<img src="' + escHtml(data.image) + '" style="max-width:220px;border-radius:10px;display:block;" loading="lazy">';
      if (data.message) html += '<div style="padding:6px 8px 2px;font-size:14px;">' + escHtml(data.message) + '</div>';
      html += '</div>';
    } else {
      html += '<div class="msg-bubble">' + escHtml(data.message) + '</div>';
    }
    html += '<div class="msg-time">' + escHtml(data.time) + '</div>';
    html += '</div>';
    box.innerHTML = html;
    box.scrollTop = box.scrollHeight;
    if (data.id > lastId) lastId = data.id;
  })
  .catch(function(e) {
    isSending = false;
    btn.disabled = false;
    startPolling();
    alert('გაგზავნა ვერ მოხდა.');
  });
}

// Auto-resize textarea
document.getElementById('chat-input').addEventListener('input', function() {
  this.style.height = '44px';
  this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

var chatPhotoBase64 = null;
var chatPhotoType   = 'image/jpeg';

function onChatPhoto(input) {
  var file = input.files[0];
  if (!file) return;
  // Max 3MB
  if (file.size > 3 * 1024 * 1024) {
    alert('ფოტო ძალიან დიდია. მაქს. 3MB.');
    return;
  }
  chatPhotoType = file.type || 'image/jpeg';
  var reader = new FileReader();
  reader.onload = function(e) {
    chatPhotoBase64 = e.target.result.split(',')[1];
    document.getElementById('chat-photo-preview').src = e.target.result;
    document.getElementById('photo-preview-bar').style.display = 'block';
    document.getElementById('chat-input').placeholder = 'დაამატე კომენტარი (სურვ.)...';
  };
  reader.readAsDataURL(file);
}

function clearChatPhoto() {
  chatPhotoBase64 = null;
  document.getElementById('photo-preview-bar').style.display = 'none';
  document.getElementById('chat-photo-preview').src = '';
  document.getElementById('chat-photo-input').value = '';
  document.getElementById('chat-input').placeholder = 'დაწერე შეტყობინება...';
}

// Load on start
loadMessages();

// Stop polling when tab hidden
document.addEventListener('visibilitychange', function() {
  if (document.hidden) {
    clearInterval(pollTimer);
  } else {
    pollMessages();
    startPolling();
  }
});
</script>
<?php endif; ?>

<?php renderFooter(); ?>