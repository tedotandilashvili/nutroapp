<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

renderHeader('კვების ანალიზი', 'analyze');
?>
<style>
.analyze-wrap { max-width: 640px; margin: 0 auto; }

/* Chat messages */
.chat-area {
  display: flex;
  flex-direction: column;
  gap: .625rem;
  min-height: 140px;
  max-height: 460px;
  overflow-y: auto;
  padding: 1rem;
  scroll-behavior: smooth;
}
.chat-area::-webkit-scrollbar { width: 3px; }
.chat-area::-webkit-scrollbar-track { background: transparent; }
.chat-area::-webkit-scrollbar-thumb { background: var(--border-s); border-radius: 99px; }

.bubble {
  padding: 11px 15px;
  border-radius: 18px;
  font-size: 14px;
  line-height: 1.6;
  max-width: 88%;
  animation: bubbleIn .2s cubic-bezier(.4,0,.2,1);
}
@keyframes bubbleIn {
  from { opacity: 0; transform: translateY(8px) scale(.97); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}
.bubble.user {
  background: var(--green);
  color: #fff;
  align-self: flex-end;
  border-radius: 18px 18px 4px 18px;
}
.bubble.ai {
  background: var(--bg-card);
  border: 0.5px solid var(--border-s);
  align-self: flex-start;
  border-radius: 18px 18px 18px 4px;
  box-shadow: var(--shadow-sm);
  color: var(--t1);
}

/* Food items */
.food-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 6px 0;
  border-bottom: 0.5px solid var(--border);
  font-size: 13px;
  color: var(--t1);
}
.food-item:last-of-type { border-bottom: none; }
.food-item strong { color: var(--green); font-size: 14px; font-weight: 700; }

/* Macro chips */
.macro-row { display: flex; gap: 5px; flex-wrap: wrap; margin-top: 10px; }
.macro-chip {
  font-size: 11px;
  padding: 3px 10px;
  border-radius: 99px;
  font-weight: 600;
}
.chip-k { background: rgba(0,0,0,.06); color: var(--t2); }
.chip-p { background: rgba(22,163,112,.12); color: var(--green-2); }
.chip-c { background: rgba(245,158,11,.1);  color: #92400E; }
.chip-f { background: rgba(229,57,53,.08);  color: #991B1B; }

/* Tips */
.tip-box {
  margin-top: 8px;
  font-size: 13px;
  padding: 8px 12px;
  border-radius: var(--r-sm);
  line-height: 1.5;
}
.tip-green { background: rgba(22,163,112,.1); color: var(--green-2); }
.tip-amber { background: rgba(245,158,11,.08); color: #92400E; }

/* Total strip */
.total-strip {
  background: var(--green-soft);
  border-radius: var(--r-md);
  padding: 10px 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  border: 0.5px solid rgba(22,163,112,.2);
}
.total-strip span { font-size: 13px; color: var(--green-2); font-weight: 500; }
.total-strip strong { font-size: 20px; font-weight: 700; color: var(--green); font-family: "Outfit",sans-serif; }

/* Quick chips */
.quick-chips { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 1rem; }
.quick-chip {
  font-size: 12px;
  padding: 6px 14px;
  border-radius: 99px;
  border: 0.5px solid var(--border-s);
  background: var(--bg-card);
  cursor: pointer;
  color: var(--t2);
  transition: all .15s;
  font-family: inherit;
  font-weight: 500;
}
.quick-chip:hover {
  border-color: var(--green);
  color: var(--green);
  background: var(--green-soft);
}

/* Input area */
.input-card {
  background: var(--bg-card);
  border: 0.5px solid var(--border-s);
  border-radius: var(--r-xl);
  overflow: hidden;
  box-shadow: var(--shadow-md);
}
.chat-messages-wrap {
  border-bottom: 0.5px solid var(--border);
}
.input-inner {
  padding: .75rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.food-textarea {
  width: 100%;
  background: transparent;
  border: none;
  outline: none;
  font-family: "DM Sans", sans-serif;
  font-size: 15px;
  color: var(--t1);
  resize: none;
  min-height: 44px;
  max-height: 120px;
  line-height: 1.5;
}
.food-textarea::placeholder { color: var(--t4); }
.input-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.input-actions-left { display: flex; align-items: center; gap: 6px; }
.attach-btn {
  width: 36px; height: 36px;
  border-radius: 50%;
  border: 0.5px solid var(--border-s);
  background: var(--bg);
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  font-size: 16px;
  transition: all .15s;
  color: var(--t3);
}
.attach-btn:hover { background: var(--green-soft); border-color: var(--green); }
.send-btn {
  height: 40px;
  padding: 0 20px;
  background: var(--green);
  color: #fff;
  border: none;
  border-radius: 99px;
  font-family: "DM Sans", sans-serif;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: flex; align-items: center; gap: 6px;
  transition: all .18s;
  box-shadow: 0 3px 12px rgba(22,163,112,.3);
}
.send-btn:hover { box-shadow: 0 5px 18px rgba(22,163,112,.45); transform: translateY(-1px); }
.send-btn:active { transform: scale(.96); }
.send-btn:disabled { opacity: .4; cursor: not-allowed; transform: none; box-shadow: none; }

/* Photo preview */
.photo-preview-wrap {
  display: none;
  position: relative;
  padding: .5rem 1rem 0;
}
.photo-preview-wrap img {
  max-height: 120px;
  border-radius: var(--r-sm);
  border: 0.5px solid var(--border-s);
  display: block;
}
.photo-remove {
  position: absolute;
  top: 10px; right: 16px;
  background: rgba(0,0,0,.6);
  color: #fff;
  border: none;
  border-radius: 50%;
  width: 22px; height: 22px;
  font-size: 11px;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
}

/* Typing dots */
.typing { display: flex; gap: 4px; align-items: center; padding: 2px 0; }
.dot {
  width: 7px; height: 7px;
  background: var(--t3);
  border-radius: 50%;
  animation: tdot .9s ease-in-out infinite;
}
.dot:nth-child(2) { animation-delay: .15s; }
.dot:nth-child(3) { animation-delay: .3s; }
@keyframes tdot { 0%,80%,100%{transform:translateY(0)} 40%{transform:translateY(-6px)} }
</style>

<div class="analyze-wrap">
  <div class="page-header">
    <div class="page-title">კვების ანალიზი</div>
    <div class="page-subtitle">მითხარი რისი ჭამა გინდა — AI კალორიებს დათვლის</div>
  </div>

  <div id="total-strip" class="total-strip" style="display:none;">
    <span>ამ საუბარში სულ</span>
    <strong id="total-kcal-val">0 კკ</strong>
  </div>

  <div class="quick-chips" id="quick-chips">
    <button class="quick-chip" onclick="useQuick(this)">2 კვერცხი + პური</button>
    <button class="quick-chip" onclick="useQuick(this)">ქათამი ბრინჯით 200გ</button>
    <button class="quick-chip" onclick="useQuick(this)">მაწონი 150გ ნიგვზით</button>
    <button class="quick-chip" onclick="useQuick(this)">ხინკალი 5 ცალი</button>
    <button class="quick-chip" onclick="useQuick(this)">ლობიო ერთი თასი</button>
    <button class="quick-chip" onclick="useQuick(this)">შაურმა პატარა</button>
  </div>

  <div class="input-card">
    <!-- Messages -->
    <div class="chat-messages-wrap">
      <div class="chat-area" id="chat-area">
        <div class="bubble ai">
          გამარჯობა! 👋 მითხარი რა ჭამე ან გინდა ჭამო — კალორიებს, ცილას, ნახშირწყლებსა და ცხიმს დავთვლი. ფოტოს ატვირთვაც შეგიძლია 📷
        </div>
      </div>
    </div>

    <!-- Photo preview -->
    <div id="photo-preview-wrap" class="photo-preview-wrap">
      <img id="photo-preview" alt="preview">
      <button onclick="clearPhoto()" class="photo-remove">✕</button>
    </div>

    <!-- Input -->
    <div class="input-inner">
      <textarea class="food-textarea" id="food-input"
        placeholder="მაგ: 2 კვერცხი, 1 ჭიქა მაწონი..."
        onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg();}"></textarea>
      <div class="input-actions">
        <div class="input-actions-left">
          <input type="file" id="photo-input" accept="image/*" style="display:none;" onchange="onPhotoSelected(this)">
          <button class="attach-btn" onclick="document.getElementById('photo-input').click()" title="ფოტო">📷</button>
          <span style="font-size:11px;color:var(--t4);">Enter — გაგზავნა</span>
        </div>
        <button class="send-btn" id="send-btn" onclick="sendMsg()">
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M13 1L1 13M13 1H5M13 1V9" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          გაგზავნა
        </button>
      </div>
    </div>
  </div>
</div>

<script>
var messages = [];
var totalKcal = 0;
var isSending = false;

var SYSTEM = 'You are a Georgian nutrition expert. User tells you what they want to eat or have eaten. Your job: calculate calories and macros, give brief advice in Georgian, suggest improvements using local Georgian foods.\n\nCRITICAL: Respond ONLY with valid raw JSON starting with { and ending with }. No markdown, no backticks, no extra text.\n\nJSON format:\n{"foods":[{"name":"კვერცხი","portion":"2 ცალი","kcal":140}],"total_kcal":220,"protein_g":14,"carbs_g":18,"fat_g":9,"advice":"კარგი საუზმეა.","suggestion":"დაამატე პომიდორი ვიტამინებისთვის.","warning":""}\n\nRules: warning only if truly unhealthy/excessive, else empty string. All text in Georgian. Realistic Georgian portion sizes.';

function useQuick(btn) {
  document.getElementById('food-input').value = btn.textContent.trim();
  document.getElementById('food-input').focus();
}

var selectedPhotoBase64 = null;
var selectedPhotoType = 'image/jpeg';

function onPhotoSelected(input) {
  var file = input.files[0];
  if (!file) return;
  selectedPhotoType = file.type || 'image/jpeg';
  var reader = new FileReader();
  reader.onload = function(e) {
    selectedPhotoBase64 = e.target.result.split(',')[1];
    document.getElementById('photo-preview').src = e.target.result;
    document.getElementById('photo-preview-wrap').style.display = 'block';
    if (!document.getElementById('food-input').value.trim()) {
      document.getElementById('food-input').value = 'ამ ფოტოში რა საკვებია? დათვალე კალორიები.';
    }
  };
  reader.readAsDataURL(file);
}

function clearPhoto() {
  selectedPhotoBase64 = null;
  document.getElementById('photo-preview-wrap').style.display = 'none';
  document.getElementById('photo-preview').src = '';
  document.getElementById('photo-input').value = '';
}

function sendMsg() {
  if (isSending) return;
  var input = document.getElementById('food-input');
  var text  = input.value.trim();
  if (!text && !selectedPhotoBase64) return;

  isSending = true;
  document.getElementById('send-btn').disabled = true;

  if (selectedPhotoBase64) {
    var imgHtml = '<img src="data:'+selectedPhotoType+';base64,'+selectedPhotoBase64+'" style="max-width:200px;max-height:120px;border-radius:10px;display:block;margin-bottom:6px;">' + (text ? '<div>'+esc(text)+'</div>' : '');
    addBubble(imgHtml, 'user', true);
  } else {
    addBubble(text, 'user');
  }

  input.value = '';
  input.style.height = '44px';

  var userContent = selectedPhotoBase64
    ? [{type:'image',source:{type:'base64',media_type:selectedPhotoType,data:selectedPhotoBase64}},{type:'text',text:text||'ამ ფოტოში რა საკვებია? კალორიები.'}]
    : text;

  messages.push({role:'user', content: userContent});
  clearPhoto();
  showTyping();

  fetch('/api/analyze_food.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({messages: messages})
  })
  .then(function(r){ return r.json(); })
  .then(function(data) {
    hideTyping();
    isSending = false;
    document.getElementById('send-btn').disabled = false;
    if (data.error) { addBubble('შეცდომა: ' + data.error, 'ai'); return; }
    messages.push({role:'assistant', content: JSON.stringify(data)});
    renderResponse(data);
  })
  .catch(function(e) {
    hideTyping();
    isSending = false;
    document.getElementById('send-btn').disabled = false;
    addBubble('კავშირის შეცდომა. სცადეთ თავიდან.', 'ai');
  });
}

function renderResponse(d) {
  var html = '<div>';
  if (d.foods && d.foods.length) {
    d.foods.forEach(function(f) {
      html += '<div class="food-item"><span>'+esc(f.name)+(f.portion?' <span style="color:var(--t3);font-size:12px;">'+esc(f.portion)+'</span>':'')
            + '</span><strong>'+f.kcal+' კკ</strong></div>';
    });
    html += '<div class="macro-row">'
          + '<span class="macro-chip chip-k">🔥 '+(d.total_kcal||0)+' კკ</span>'
          + '<span class="macro-chip chip-p">ც '+(d.protein_g||0)+'გ</span>'
          + '<span class="macro-chip chip-c">ნ '+(d.carbs_g||0)+'გ</span>'
          + '<span class="macro-chip chip-f">ჯ '+(d.fat_g||0)+'გ</span>'
          + '</div>';
    totalKcal += (d.total_kcal||0);
    document.getElementById('total-strip').style.display = 'flex';
    document.getElementById('total-kcal-val').textContent = totalKcal + ' კკ';
  }
  if (d.advice)     html += '<p style="margin-top:9px;font-size:13px;color:var(--t2);line-height:1.6;">'+esc(d.advice)+'</p>';
  if (d.suggestion) html += '<div class="tip-box tip-green">💡 '+esc(d.suggestion)+'</div>';
  if (d.warning)    html += '<div class="tip-box tip-amber">⚠️ '+esc(d.warning)+'</div>';
  html += '</div>';
  addBubble(html, 'ai', true);
}

function addBubble(content, type, isHTML) {
  var area = document.getElementById('chat-area');
  var div  = document.createElement('div');
  div.className = 'bubble ' + type;
  if (isHTML) div.innerHTML = content; else div.textContent = content;
  area.appendChild(div);
  area.scrollTop = area.scrollHeight;
}

function showTyping() {
  var area = document.getElementById('chat-area');
  var div  = document.createElement('div');
  div.className = 'bubble ai'; div.id = 'typing-bubble';
  div.innerHTML = '<div class="typing"><div class="dot"></div><div class="dot"></div><div class="dot"></div></div>';
  area.appendChild(div);
  area.scrollTop = area.scrollHeight;
}

function hideTyping() {
  var t = document.getElementById('typing-bubble');
  if (t) t.remove();
}

function esc(str) {
  var d = document.createElement('div'); d.textContent = str||''; return d.innerHTML;
}

document.getElementById('food-input').addEventListener('input', function() {
  this.style.height = '44px';
  this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});
</script>

<?php renderFooter(); ?>