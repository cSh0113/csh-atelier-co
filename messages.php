<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$me = current_user();
$myId = (int)$me['id'];
$openWith = (int)($_GET['with'] ?? 0);
$pageTitle = 'Messages';
$pdo = db();

function format_chat_time(?string $iso): string {
    if (!$iso) return '';
    $time = strtotime($iso);
    if (!$time) return '';
    if (date('Y-m-d', $time) === date('Y-m-d')) return date('H:i', $time);
    if (date('Y-m-d', $time) === date('Y-m-d', strtotime('-1 day'))) return 'Yesterday';
    return date('d M', $time);
}

// Load the threads on the server so the inbox is already there on first
// paint instead of popping in afterwards.
$convSql = "
    SELECT 
        u.id, 
        u.name, 
        u.avatar_url,
        (SELECT m.created_at FROM messages m 
         WHERE (m.sender_id = u.id AND m.receiver_id = :me1) 
            OR (m.sender_id = :me2 AND m.receiver_id = u.id)
         ORDER BY m.id DESC LIMIT 1) as last_time,
        (SELECT m.body FROM messages m 
         WHERE (m.sender_id = u.id AND m.receiver_id = :me3) 
            OR (m.sender_id = :me4 AND m.receiver_id = u.id)
         ORDER BY m.id DESC LIMIT 1) as last_msg,
        (SELECT m.sender_id FROM messages m 
         WHERE (m.sender_id = u.id AND m.receiver_id = :me5) 
            OR (m.sender_id = :me6 AND m.receiver_id = u.id)
         ORDER BY m.id DESC LIMIT 1) as last_sender,
        (SELECT COUNT(*) FROM messages m 
         WHERE m.sender_id = u.id AND m.receiver_id = :me7 AND m.is_read = 0) as unread
    FROM users u
    WHERE u.id IN (
        SELECT DISTINCT CASE WHEN sender_id = :me8 THEN receiver_id ELSE sender_id END
        FROM messages
        WHERE sender_id = :me9 OR receiver_id = :me10
    ) AND u.id != :me11
    ORDER BY last_time DESC
";

$initialConversations = [];
try {
    $cStmt = $pdo->prepare($convSql);
    $cStmt->execute([
        'me1' => $myId, 'me2' => $myId, 'me3' => $myId, 'me4' => $myId,
        'me5' => $myId, 'me6' => $myId, 'me7' => $myId, 'me8' => $myId,
        'me9' => $myId, 'me10' => $myId, 'me11' => $myId
    ]);
    $initialConversations = $cStmt->fetchAll();
} catch (Exception $e) {}

// If I open a chat with someone I have never messaged, there is no thread
// yet, so I put them at the top of the list myself or the page looks empty.
if ($openWith > 0) {
    $found = false;
    foreach ($initialConversations as $c) {
        if ((int)$c['id'] === $openWith) { $found = true; break; }
    }
    if (!$found) {
        $uStmt = $pdo->prepare("SELECT id, name, avatar_url FROM users WHERE id = ? AND status = 'active'");
        $uStmt->execute([$openWith]);
        if ($targetUser = $uStmt->fetch()) {
            array_unshift($initialConversations, [
                'id' => (int)$targetUser['id'],
                'name' => $targetUser['name'],
                'avatar_url' => $targetUser['avatar_url'],
                'last_time' => date('Y-m-d H:i:s'),
                'last_msg' => 'Start talking...',
                'last_sender' => 0,
                'unread' => 0
            ]);
        }
    }
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

include __DIR__ . '/includes/header.php';
?>
<style>
.chat{
  display:grid; grid-template-columns:360px 1fr;
  height:calc(100vh - 88px); min-height:520px;
  border-radius:var(--r-lg,18px); overflow:hidden;
  background:var(--bg-raised,#fff); box-shadow:var(--nm-out,0 8px 30px rgba(0,0,0,.08));
  margin:18px auto; width:min(100% - 32px, 1240px); position:relative;
}
.chat *{ box-sizing:border-box; }
.chat .avatar{
  width:44px; height:44px; border-radius:50%; flex:none;
  display:grid; place-items:center; font-weight:800; font-size:1rem;
  background:var(--leaf,#3d6b4f); color:#fff; position:relative; overflow:hidden;
}
.chat .avatar img{ width:100%; height:100%; object-fit:cover; border-radius:50%; }
.chat .avatar.big{ width:84px; height:84px; font-size:1.9rem; }
.ic-btn{
  width:38px; height:38px; border:0; border-radius:50%; background:transparent;
  color:var(--ink-soft,#5b5b5b); display:grid; place-items:center; cursor:pointer; padding:9px;
  transition:background .18s;
}
.ic-btn:hover{ background:var(--bg-sunken,#eceae5); color:var(--ink,#1a1a1a); }
.ic-btn svg{ width:100%; height:100%; fill:none; stroke:currentColor; stroke-width:1.9; stroke-linecap:round; stroke-linejoin:round; }
.ic-btn.danger{ color:#b4483c; }

/* Sidebar */
.chat-side{
  border-right:1px solid var(--line,#e2ded6);
  display:flex; flex-direction:column; background:var(--bg,#f6f4f0); min-width:0;
}
.chat-side__top{
  display:flex; align-items:center; justify-content:space-between; gap:10px;
  padding:14px 16px; border-bottom:1px solid var(--line,#e2ded6);
}
.chat-side__me{ display:flex; align-items:center; gap:11px; min-width:0; }
.chat-side__me b{ display:block; font-size:.96rem; }
.chat-side__me small{ color:var(--ink-faint,#8a8a8a); font-size:.74rem; }

.chat-search{
  display:flex; align-items:center; gap:9px; margin:12px 14px;
  background:var(--bg-sunken,#eceae5); border-radius:999px; padding:9px 15px;
}
.chat-search svg{ width:16px; height:16px; fill:none; stroke:var(--ink-faint,#8a8a8a); stroke-width:2; flex:none; }
.chat-search input{ border:0; background:none; outline:none; width:100%; font:inherit; font-size:.9rem; color:inherit; }

.chat-filters{ display:flex; gap:7px; padding:0 14px 12px; }
.chip{
  border:0; border-radius:999px; padding:7px 14px; cursor:pointer;
  background:var(--bg-sunken,#eceae5); color:var(--ink-soft,#5b5b5b);
  font:inherit; font-size:.78rem; font-weight:600;
}
.chip.is-on{ background:var(--leaf,#3d6b4f); color:#fff; }

.chat-list{ flex:1; overflow-y:auto; }
.crow{
  display:flex; align-items:center; gap:12px; width:100%; text-align:left;
  padding:12px 16px; border:0; background:none; cursor:pointer; border-bottom:1px solid var(--line,#e2ded6);
  font:inherit; color:inherit; transition:background .16s;
}
.crow:hover{ background:var(--bg-sunken,#eceae5); }
.crow.is-on{ background:var(--bg-raised,#fff); box-shadow:inset 3px 0 0 var(--leaf,#3d6b4f); }
.crow__mid{ flex:1; min-width:0; }
.crow__top{ display:flex; justify-content:space-between; align-items:baseline; gap:8px; }
.crow__top b{ font-size:.94rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.crow__top i{ font-style:normal; font-size:.7rem; color:var(--ink-faint,#8a8a8a); flex:none; }
.crow__bot{ display:flex; justify-content:space-between; align-items:center; gap:8px; margin-top:3px; }
.crow__prev{ font-size:.82rem; color:var(--ink-faint,#8a8a8a); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex:1; min-width:0; }
.badge{ background:var(--leaf,#3d6b4f); color:#fff; border-radius:999px; min-width:20px; height:20px; padding:0 6px; font-size:.7rem; font-weight:800; display:grid; place-items:center; flex:none; }

/* Main Thread */
.chat-main{ display:flex; flex-direction:column; min-width:0; background:var(--bg-sunken,#eceae5); position:relative; }
.chat-blank{
  flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center;
  text-align:center; padding:40px; color:var(--ink-soft,#5b5b5b);
}
.chat-blank__art{ width:88px; height:88px; margin-bottom:20px; opacity:.35; }
.chat-blank__art svg{ width:100%; height:100%; fill:none; stroke:var(--leaf,#3d6b4f); stroke-width:1.3; }

.chat-room{ flex:1; display:flex; flex-direction:column; min-height:0; overflow:hidden; }
.chat-head{
  display:flex; align-items:center; gap:12px; padding:11px 14px;
  background:var(--bg,#f6f4f0); border-bottom:1px solid var(--line,#e2ded6);
}
.chat-head__who{ display:flex; align-items:center; gap:11px; flex:1; min-width:0; text-decoration:none; color:inherit; }
.chat-head__who b{ display:block; font-size:.98rem; }
.chat-head__who small{ font-size:.75rem; color:var(--ink-faint,#8a8a8a); }
.chat-head__tools{ display:flex; align-items:center; gap:2px; position:relative; }

.chat-scroll{
  flex:1 1 0; overflow-y:auto; padding:18px 16px 8px; min-height:0;
  background-image:radial-gradient(circle at 1px 1px, rgba(0,0,0,.035) 1px, transparent 0);
  background-size:22px 22px;
}
.chat-msgs{ display:flex; flex-direction:column; gap:3px; }
.daysep{ text-align:center; margin:16px 0 10px; }
.daysep span{ background:var(--bg-raised,#fff); color:var(--ink-faint,#8a8a8a); font-size:.7rem; padding:5px 13px; border-radius:999px; }

.bub-row{ display:flex; align-items:flex-end; gap:6px; max-width:78%; position:relative; }
.bub-row.me{ align-self:flex-end; flex-direction:row-reverse; }
.bub-row.you{ align-self:flex-start; }
.bub{
  background:var(--bg-raised,#fff); border-radius:14px; padding:8px 12px 6px;
  box-shadow:0 1px 2px rgba(0,0,0,.09); position:relative; min-width:78px;
}
.bub p{ margin:0; font-size:.92rem; line-height:1.45; word-wrap:break-word; overflow-wrap:anywhere; }
.bub .meta{
  display:flex; align-items:center; justify-content:flex-end; gap:5px;
  font-size:.66rem; color:var(--ink-faint,#8a8a8a); margin-top:3px;
}

.chat-composer{
  display:flex; align-items:center; gap:8px; padding:11px 14px;
  background:var(--bg,#f6f4f0); border-top:1px solid var(--line,#e2ded6);
  flex-shrink:0;
}
.chat-composer input{
  flex:1; min-width:0; border:0; outline:none; border-radius:999px;
  padding:13px 18px; background:var(--bg-raised,#fff); font:inherit; font-size:.93rem; color:inherit;
}
.send-btn{
  width:46px; height:46px; border:0; border-radius:50%; flex:none; cursor:pointer;
  background:var(--leaf,#3d6b4f); color:#fff; display:grid; place-items:center; padding:12px;
}

/* Modals & Menus */
.menu{
  position:absolute; right:0; top:44px; z-index:40; min-width:196px; padding:6px;
  background:var(--bg-raised,#fff); border:1px solid var(--line,#e2ded6);
  border-radius:12px; box-shadow:0 12px 34px rgba(0,0,0,.16);
}
.menu button{
  display:block; width:100%; text-align:left; border:0; background:none; cursor:pointer;
  padding:10px 13px; border-radius:8px; font:inherit; font-size:.88rem; color:inherit;
}
.menu button:hover{ background:var(--bg-sunken,#eceae5); }
.menu button.danger{ color:#b4483c; }

.modal-overlay{
  position:fixed; inset:0; z-index:100; background:rgba(0,0,0,.5);
  display:flex; align-items:center; justify-content:center; padding:20px;
}
.modal-overlay[hidden]{ display:none !important; }
.modal-window{
  width:100%; max-width:460px; background:var(--bg-raised,#fff); border-radius:16px; padding:24px;
  box-shadow:0 22px 60px rgba(0,0,0,.3);
}

.reason-chips {
  display: flex; flex-wrap: wrap; gap: 7px; margin-bottom: 12px;
}
.reason-chip {
  padding: 6px 12px; border-radius: 999px; border: 1px solid var(--line, #e2ded6);
  background: var(--bg-sunken, #eceae5); font-size: 0.8rem; font-weight: 600; cursor: pointer;
}
.reason-chip.selected {
  background: var(--leaf, #3d6b4f); color: #fff; border-color: var(--leaf, #3d6b4f);
}

.cbtn{
  border:0; border-radius:999px; padding:10px 18px; cursor:pointer; font:inherit;
  font-size:.86rem; font-weight:700; background:var(--bg-sunken,#eceae5); color:var(--ink,#1a1a1a); text-decoration:none;
}
.cbtn.danger{ background:#f7ded9; color:#a83f33; }

.chat-empty{ padding:44px 20px; text-align:center; color:var(--ink-faint,#8a8a8a); font-size:.88rem; }
.only-mobile{ display:none; }
@media (max-width:900px){
  .chat{ grid-template-columns:1fr; height:calc(100vh - 70px); margin:0; width:100%; border-radius:0; }
  .chat-main{ display:none; }
  .chat.is-open .chat-side{ display:none; }
  .chat.is-open .chat-main{ display:flex; }
  .only-mobile{ display:grid; }
}
.chat [hidden], [hidden] { display:none !important; }

/* Dark mode bubble contrast */
.bub-row.me .bub { background: #dcf3e4 !important; color: #111b21 !important; }
.bub-row.me .bub p { color: #111b21 !important; }
.bub-row.you .bub { background: #202c33 !important; color: #e9edef !important; }
.bub-row.you .bub p { color: #e9edef !important; }

/* ---------- EMOJI AND GIF PICKER ---------- */
.chat-picker{ position:relative; margin:0 14px; padding:10px; border-radius:14px;
  background:var(--bg-raised); box-shadow:var(--nm-out-sm); }
.picker-tabs{ display:flex; gap:6px; margin-bottom:9px; }
.picker-tabs button{ border:0; cursor:pointer; font:inherit; font-size:.72rem; font-weight:700;
  padding:5px 12px; border-radius:999px; background:var(--bg-sunken); color:var(--ink-soft); }
.picker-tabs button.is-on{ background:var(--leaf); color:#fff; }
.emoji-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(34px,1fr));
  gap:2px; max-height:170px; overflow:auto; }
.emoji-grid button{ border:0; background:none; cursor:pointer; font-size:1.25rem;
  line-height:1; padding:5px 0; border-radius:8px; }
.emoji-grid button:hover{ background:var(--bg-sunken); }
.gif-search{ width:100%; margin-bottom:8px; padding:8px 12px; border:0; border-radius:999px;
  background:var(--bg-sunken); color:var(--ink); box-shadow:var(--nm-in-sm); font:inherit; font-size:.85rem; }
.gif-grid{ display:grid; grid-template-columns:repeat(3,1fr); gap:5px;
  max-height:210px; overflow:auto; }
.gif-grid button{ border:0; padding:0; background:none; cursor:pointer; border-radius:8px; overflow:hidden; }
.gif-grid img{ width:100%; height:82px; object-fit:cover; display:block; }
.gif-note{ grid-column:1/-1; margin:0; padding:14px 0; text-align:center;
  color:var(--ink-faint); font-size:.78rem; }
.bub.has-media{ padding:4px 4px 2px; }
.bub-media{ display:block; max-width:230px; width:100%; border-radius:10px; }
/* Delivery ticks. One grey for sent, two blue once they have opened it. */
.tick{ margin-left:5px; font-style:normal; letter-spacing:-2px; opacity:.65; }
.tick--read{ color:#4a9eff; opacity:1; }

video.bub-media{ max-width:260px; background:#000; }
.bub-audio{ display:block; width:200px; max-width:100%; }

/* Voice note bubble: a small mic badge next to the player, so at a glance
   you can tell it apart from a video without pressing play. */
.bub-voice{ display:flex; align-items:center; gap:8px; }
.bub-voice__ico{
  width:26px; height:26px; flex-shrink:0; border-radius:50%;
  display:grid; place-items:center; background:var(--leaf); color:#fff;
}
.bub-voice__ico svg{ width:15px; height:15px; }

/* The bar that replaces the composer input while recording a voice note. */
.voice-bar{
  display:flex; align-items:center; gap:10px; margin:0 14px 12px;
  padding:9px 14px; border-radius:999px; background:var(--bg-raised);
  box-shadow:var(--nm-out-sm); font-size:.85rem; color:var(--ink-soft);
  flex-shrink:0;
}
.voice-dot{
  width:10px; height:10px; border-radius:50%; background:#c0392b; flex-shrink:0;
  animation:voicePulse 1.1s ease-in-out infinite;
}
@keyframes voicePulse{ 0%,100%{ opacity:1 } 50%{ opacity:.35 } }
.voice-hint{ flex:1; color:var(--ink-faint); }
.voice-cancel{ border:0; background:none; color:#c0392b; font-weight:700; font-size:.82rem; cursor:pointer; padding:4px 6px; }
[data-voice-btn].is-recording{ color:#c0392b; }

/* ---------- SELECTION MODE: delete messages and archive chats ---------- */
.sel-toolbar{
  display:flex; align-items:center; gap:8px; padding:10px 14px;
  background:var(--bg,#f6f4f0); border-bottom:1px solid var(--line,#e2ded6);
  flex-shrink:0;
}
.sel-toolbar .sel-count{ flex:1; font-size:.88rem; font-weight:700; }
.sel-toolbar button{
  border:0; border-radius:999px; padding:7px 14px; cursor:pointer;
  font:inherit; font-size:.82rem; font-weight:700;
  background:var(--bg-sunken,#eceae5); color:var(--ink,#1a1a1a);
}
.sel-toolbar button.danger{ background:#f7ded9; color:#a83f33; }
.sel-toolbar button.cancel{ background:transparent; color:var(--ink-soft); }

/* When selection mode is on, each bubble row gets a checkbox area */
.chat-msgs.selecting .bub-row{ cursor:pointer; padding-left:30px; position:relative; }
.chat-msgs.selecting .bub-row::before{
  content:''; position:absolute; left:6px; top:50%; transform:translateY(-50%);
  width:18px; height:18px; border-radius:50%; border:2px solid var(--ink-faint,#8a8a8a);
  background:transparent; transition:all .15s;
}
.chat-msgs.selecting .bub-row.selected::before{
  background:var(--leaf,#3d6b4f); border-color:var(--leaf,#3d6b4f);
}
.chat-msgs.selecting .bub-row.selected::after{
  content:'✓'; position:absolute; left:9px; top:50%; transform:translateY(-50%);
  color:#fff; font-size:.7rem; font-weight:900;
}

/* Conversation row long press / selection for archive */
.crow.selected{ background:color-mix(in srgb, var(--leaf) 12%, var(--bg-raised)) !important; }
.crow .sel-check{
  width:20px; height:20px; border-radius:50%; border:2px solid var(--ink-faint);
  display:none; flex-shrink:0;
}
.chat-side.selecting .crow .sel-check{ display:block; }
.crow.selected .sel-check{ background:var(--leaf); border-color:var(--leaf); }

/* Archive filter tab */
.chat-filters .chip[data-filter="archived"]{ display:none; }
.chat-filters.has-archived .chip[data-filter="archived"]{ display:block; }
</style>

<div class="chat" data-chat data-me="<?= (int)$me['id'] ?>" data-open="<?= $openWith ?>">

  <!-- Sidebar -->
  <aside class="chat-side" data-side>
    <header class="chat-side__top">
      <div class="chat-side__me">
        <span class="avatar">
          <?php if (!empty($me['avatar_url'])): ?>
            <img src="<?= e($me['avatar_url']) ?>" alt="<?= e($me['name']) ?>">
          <?php else: ?>
            <?= e(mb_strtoupper(mb_substr($me['name'],0,1))) ?>
          <?php endif; ?>
        </span>
        <div><b><?= e($me['name']) ?></b><small>Your chats</small></div>
      </div>
    </header>

    <div class="chat-search">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
      <input type="search" placeholder="Search chats or messages" data-search>
    </div>

    <div class="chat-filters">
      <button class="chip is-on" data-filter="all">All</button>
      <button class="chip" data-filter="unread">Unread</button>
      <button class="chip" data-filter="archived">Archived</button>
    </div>

    <!-- Conversations list -->
    <div class="chat-list" data-list>
      <?php if (empty($initialConversations)): ?>
        <div class="chat-empty"><p>No chats yet. Message a seller from any listing to start one.</p></div>
      <?php else: ?>
        <?php foreach ($initialConversations as $c): ?>
          <button class="crow <?= ((int)$c['id'] === $openWith) ? 'is-on' : '' ?>" data-open-chat="<?= (int)$c['id'] ?>">
            <span class="avatar">
              <?php if (!empty($c['avatar_url'])): ?>
                <img src="<?= e($c['avatar_url']) ?>" alt="<?= e($c['name']) ?>">
              <?php else: ?>
                <?= e(mb_strtoupper(mb_substr($c['name'], 0, 1))) ?>
              <?php endif; ?>
            </span>
            <span class="crow__mid">
              <span class="crow__top">
                <b><?= e($c['name']) ?></b>
                <i><?= e(format_chat_time($c['last_time'])) ?></i>
              </span>
              <span class="crow__bot">
                <span class="crow__prev">
                  <?= ((int)$c['last_sender'] === $myId) ? '<em>You: </em>' : '' ?>
                  <?= e((string)$c['last_msg']) ?>
                </span>
                <?php if ((int)$c['unread'] > 0): ?>
                  <span class="badge"><?= (int)$c['unread'] ?></span>
                <?php endif; ?>
              </span>
            </span>
          </button>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </aside>

  <!-- Main Thread -->
  <section class="chat-main" data-main>
    <div class="chat-blank" data-blank>
      <div class="chat-blank__art">
        <svg viewBox="0 0 24 24"><path d="M21 12a8 8 0 0 1-8 8H7l-4 3V12a8 8 0 0 1 8-8h2a8 8 0 0 1 8 8z"/></svg>
      </div>
      <h2>Your messages</h2>
      <p>Pick a chat on the left to start talking to a buyer or seller.</p>
    </div>

    <div class="chat-room" data-room hidden>
      <header class="chat-head">
        <button class="ic-btn only-mobile" data-back aria-label="Back">
          <svg viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
        </button>
        
        <!-- Header links directly to member's public profile -->
        <a class="chat-head__who" data-profile-link href="#" target="_blank" title="Click to view full profile & wardrobe">
          <span class="avatar" data-p-avatar>?</span>
          <div>
            <b data-p-name>Member</b>
            <small data-p-status>tap to view profile</small>
          </div>
        </a>

        <div class="chat-head__tools">
          <button class="ic-btn" data-room-menu-btn title="More options" aria-label="More options">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
          </button>
          <div class="menu" data-room-menu hidden>
            <button data-act="select-msgs">Select Messages</button>
            <button data-act="view-profile">View Profile &amp; Wardrobe</button>
            <button data-act="rate">★ Rate This Member</button>
            <button data-act="archive-chat">Archive Chat</button>
            <button data-act="report" class="danger">Report Member</button>
          </div>
        </div>
      </header>

      <div class="chat-scroll" data-scroll>
        <div class="sel-toolbar" data-sel-toolbar hidden>
          <span class="sel-count" data-sel-count>0 selected</span>
          <button type="button" class="danger" data-sel-delete>Delete</button>
          <button type="button" class="cancel" data-sel-cancel>Cancel</button>
        </div>
        <div class="chat-msgs" data-msgs></div>
        <div class="typing" data-typing hidden><span></span><span></span><span></span></div>
      </div>

      <div class="chat-picker" data-picker hidden>
        <div class="picker-tabs">
          <button type="button" class="is-on" data-tab="emoji">Emoji</button>
          <button type="button" data-tab="gif" data-gif-tab hidden>GIF</button>
        </div>
        <div data-pane="emoji" class="emoji-grid" data-emoji-grid></div>
        <div data-pane="gif" hidden>
          <input type="text" class="gif-search" placeholder="Search GIFs" data-gif-q>
          <div class="gif-grid" data-gif-grid></div>
        </div>
      </div>

      <form class="chat-composer" data-composer autocomplete="off">
        <button type="button" class="ic-btn" data-picker-btn title="Emoji and GIFs" aria-label="Emoji and GIFs">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8.5 14.2a4.6 4.6 0 0 0 7 0"/><path d="M9 9.6h.01M15 9.6h.01"/></svg>
        </button>
        <button type="button" class="ic-btn" data-attach-btn title="Send a photo or video" aria-label="Send a photo or video">
          <svg viewBox="0 0 24 24"><path d="M21 12.5V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h8.5"/><path d="m3 16 5-5 4 4 3-3 2 2"/><circle cx="17.5" cy="17.5" r="4.5"/><path d="M17.5 16v3M16 17.5h3"/></svg>
        </button>
        <input type="file" data-attach-input accept="image/*,video/*" hidden>
        <input type="text" placeholder="Write a message..." data-input maxlength="4000">
        <button type="button" class="ic-btn" data-voice-btn title="Record a voice note" aria-label="Record a voice note">
          <svg viewBox="0 0 24 24"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v4M8 22h8"/></svg>
        </button>
        <button type="submit" class="send-btn" title="Send" aria-label="Send">
          <svg viewBox="0 0 24 24"><path d="M4 12l16-8-6 8 6 8z"/></svg>
        </button>
      </form>
      <div class="voice-bar" data-voice-bar hidden>
        <span class="voice-dot"></span>
        <span data-voice-time>0:00</span>
        <span class="voice-hint">Recording, tap the microphone again to send</span>
        <button type="button" class="voice-cancel" data-voice-cancel>Cancel</button>
      </div>
    </div>
  </section>
</div>

<!-- Report Modal with Suggested Chips + Custom Reason -->
<div id="chatReportModal" class="modal-overlay" hidden>
  <div class="modal-window">
    <h3 style="margin: 0 0 8px; font-size: 1.15rem;">Report Member</h3>
    <p style="margin: 0 0 16px; font-size: 0.85rem; color: var(--ink-faint, #8a8a8a);">
      Choose a suggested reason or type your own description below.
    </p>

    <form id="chatReportForm">
      <input type="hidden" name="action" value="report">
      <input type="hidden" name="member_id" id="reportPartnerId" value="0">

      <div style="margin-bottom: 12px;">
        <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">Suggested Reasons</label>
        <div class="reason-chips">
          <button type="button" class="reason-chip" onclick="setChatReason('Scam or Fraud')">Scam or Fraud</button>
          <button type="button" class="reason-chip" onclick="setChatReason('Harassment or Abuse')">Harassment</button>
          <button type="button" class="reason-chip" onclick="setChatReason('Counterfeit or Replica Items')">Counterfeit</button>
          <button type="button" class="reason-chip" onclick="setChatReason('Item not as described')">Item Not Described</button>
          <button type="button" class="reason-chip" onclick="setChatReason('Spam')">Spam</button>
        </div>
      </div>

      <div style="margin-bottom: 14px;">
        <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">Reason</label>
        <input type="text" id="chatReasonInput" name="reason" placeholder="Selected or custom reason..." required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--line, #e2ded6); background: var(--bg, #f6f4f0); font: inherit;">
      </div>

      <div style="margin-bottom: 20px;">
        <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">Details / Notes (Optional)</label>
        <textarea name="details" rows="3" placeholder="Provide additional details..." style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--line, #e2ded6); background: var(--bg, #f6f4f0); font: inherit; font-size: 0.88rem;"></textarea>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 8px;">
        <button type="button" class="cbtn" onclick="closeChatReport()">Cancel</button>
        <button type="submit" class="cbtn danger">Submit Report</button>
      </div>
    </form>
  </div>
</div>

<script>window.CSH_CSRF = <?= json_encode(csrf_token()) ?>;</script>
<script>
(function () {
  var root = document.querySelector('[data-chat]');
  if (!root) return;

  var API = 'api/messages.php';
  var CSRF = window.CSH_CSRF || '';
  var ME = parseInt(root.dataset.me, 10);
  var openWith = parseInt(root.dataset.open, 10) || 0;

  var state = { with: 0, filter: 'all', search: '', partner: null, latest: 0, unseen: -1 };

  var $ = function (s, c) { return (c || root).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || root).querySelectorAll(s)); };
  window.CSH_GIPHY = <?= json_encode(defined('GIPHY_API_KEY') ? GIPHY_API_KEY : '') ?>;
  // What this server will actually accept for one upload, so a file that
  // is never going to make it can be rejected before the wait.
  window.CSH_MAX_UPLOAD = <?= json_encode(max_upload_bytes()) ?>;

  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function post(action, data) {
    var body = new URLSearchParams();
    body.set('action', action); body.set('csrf', CSRF);
    Object.keys(data || {}).forEach(function (k) { body.set(k, data[k]); });
    return fetch(API, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { return r.json(); }).catch(function () { return { ok: false }; });
  }
  // Same as post(), but for an actual file, a photo, a video, or a
  // recorded voice note, so it goes as multipart form data instead of
  // plain fields.
  function postFile(action, data, file) {
    var body = new FormData();
    body.set('action', action); body.set('csrf', CSRF);
    Object.keys(data || {}).forEach(function (k) { body.set(k, data[k]); });
    if (file) body.set('media_file', file, file.name || 'upload');
    return fetch(API, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { return r.json(); }).catch(function () { return { ok: false }; });
  }
  function get(action, params) {
    var p = Object.assign({ action: action, _t: Date.now() }, params || {});
    var q = new URLSearchParams(p);
    return fetch(API + '?' + q.toString(), { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); }).catch(function (err) { return { ok: false, error: err.message }; });
  }
  function when(iso) {
    if (!iso) return '';
    var d = new Date(iso.replace(' ', 'T')), now = new Date();
    if (d.toDateString() === now.toDateString()) return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    var yest = new Date(now); yest.setDate(now.getDate() - 1);
    if (d.toDateString() === yest.toDateString()) return 'Yesterday';
    return d.toLocaleDateString([], { day: '2-digit', month: 'short' });
  }

  function loadList() {
    get('conversations', { filter: state.filter, q: state.search }).then(function (r) {
      var box = $('[data-list]');
      if (!box || !r || !r.ok) return;
      if (!r.conversations || !r.conversations.length) {
        box.innerHTML = '<div class="chat-empty"><p>No chats found.</p></div>';
        return;
      }
      box.innerHTML = r.conversations.map(function (c) {
        var avHtml = c.avatar_url ? '<img src="' + esc(c.avatar_url) + '" alt="' + esc(c.name) + '">' : esc(c.initial);
        return '<button class="crow' + (c.id === state.with ? ' is-on' : '') + '" data-open-chat="' + c.id + '">' +
          '<span class="avatar">' + avHtml + '</span>' +
          '<span class="crow__mid">' +
            '<span class="crow__top"><b>' + esc(c.name) + '</b><i>' + when(c.at) + '</i></span>' +
            '<span class="crow__bot"><span class="crow__prev">' + (c.outgoing ? '<em>You: </em>' : '') + esc(c.preview) + '</span>' + (c.unread ? '<span class="badge">' + c.unread + '</span>' : '') + '</span>' +
          '</span>' +
        '</button>';
      }).join('');
    });
  }

  function openChat(id) {
    state.with = id;
    $('[data-blank]').hidden = true;
    $('[data-room]').hidden = false;
    root.classList.add('is-open');
    loadThread(true);

    var activeRow = $('[data-open-chat="' + id + '"]');
    if (activeRow) {
      $$('.crow').forEach(function(r) { r.classList.remove('is-on'); });
      activeRow.classList.add('is-on');
    }
  }

  function loadThread(scroll) {
    if (!state.with) return;
    get('thread', { with: state.with }).then(function (r) {
      if (!r || !r.ok) return;
      state.partner = r.partner;

      var pAvatar = $('[data-p-avatar]');
      if (r.partner.avatar_url) {
        pAvatar.innerHTML = '<img src="' + esc(r.partner.avatar_url) + '" alt="' + esc(r.partner.name) + '">';
      } else {
        pAvatar.textContent = r.partner.initial;
      }

      $('[data-p-name]').textContent = r.partner.name;
      
      // Update header link directly to member's public profile
      var profLink = $('[data-profile-link]');
      if (profLink) {
        profLink.href = 'member.php?id=' + r.partner.id;
      }

      // Online beats last seen, last seen beats falling back to their city.
      var status = r.partner.online ? 'online now'
                 : (r.partner.last_seen || r.partner.city || 'Member');
      if (r.partner.rating_count > 0) {
        status += ' \u00b7 ★ ' + r.partner.rating.toFixed(1) + ' (' + r.partner.rating_count + ')';
      }
      $('[data-p-status]').textContent = status;

      // Admins handle the reports queue, so you cannot report one.
      var repBtn = $('[data-act="report"]');
      if (repBtn) repBtn.hidden = !!r.partner.is_admin;

      renderMsgs(r.messages || []);
      if (r.messages && r.messages.length) state.latest = r.messages[r.messages.length - 1].id;
      if (scroll) {
        var s = $('[data-scroll]');
        if (s) s.scrollTop = s.scrollHeight;
      }
    });
  }

  function renderMsgs(list) {
    var box = $('[data-msgs]');
    if (!list.length) {
      box.innerHTML = '<div class="chat-empty"><p>No messages yet. Say hello.</p></div>';
      return;
    }
    box.innerHTML = list.map(function (m) {
      // A gif, photo, video or voice note comes through with a media url
      // instead of words, so that bubble shows the media and drops the
      // text paragraph.
      var inner;
      if (m.media && (m.kind === 'gif' || m.kind === 'image')) {
        var src = m.media;
        // Old messages stored the direct /uploads/ path which 403s on InfinityFree.
        // Rewrite those to go through the media proxy instead.
        if (src && src.indexOf('/uploads/') !== -1) {
          var fname = src.split('/uploads/').pop();
          src = (window.CSH_BASE || '') + '/media.php?f=' + encodeURIComponent(fname);
        }
        inner = '<img class="bub-media" src="' + esc(src) + '" alt="' + esc(m.body || 'Photo') + '" loading="lazy" onclick="window.open(this.src)" style="cursor:pointer">';
      } else if (m.media && m.kind === 'video') {
        var vsrc = m.media;
        if (vsrc && vsrc.indexOf('/uploads/') !== -1) {
          var vname = vsrc.split('/uploads/').pop();
          vsrc = (window.CSH_BASE || '') + '/media.php?f=' + encodeURIComponent(vname);
        }
        inner = '<video class="bub-media" controls preload="metadata" playsinline src="' + esc(vsrc) + '#t=0.1"></video>';
      } else if (m.media && m.kind === 'voice') {
        var asrc = m.media;
        if (asrc && asrc.indexOf('/uploads/') !== -1) {
          var aname = asrc.split('/uploads/').pop();
          asrc = (window.CSH_BASE || '') + '/media.php?f=' + encodeURIComponent(aname);
        }
        inner = '<div class="bub-voice"><span class="bub-voice__ico">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v4"/></svg>' +
                '</span><audio class="bub-audio" controls preload="metadata" src="' + esc(asrc) + '"></audio></div>';
      } else {
        inner = '<p>' + esc(m.body) + '</p>';
      }
      // One grey tick means sent, two blue ticks mean they have opened it.
      // The read flag comes straight off the row, so this stops claiming
      // every message was seen the moment it left.
      var tick = '';
      if (m.mine) {
        tick = m.read
          ? '<i class="tick tick--read" title="Seen">&#10003;&#10003;</i>'
          : '<i class="tick" title="Sent">&#10003;</i>';
      }
      return '<div class="bub-row ' + (m.mine ? 'me' : 'you') + '" data-mid="' + m.id + '">' +
        '<div class="bub' + (m.media ? ' has-media' : '') + '">' + inner +
        '<span class="meta">' + when(m.at) + tick + '</span></div></div>';
    }).join('');
  }

  /* ---------- EMOJI AND GIF PICKER ---------- */
  var GIPHY = window.CSH_GIPHY || '';
  var EMOJI = ('😀 😃 😄 😁 😅 😂 🙂 😉 😊 😍 🥰 😘 😋 😎 🤩 🥳 🤔 🤗 😐 😴 '
             + '😢 😭 😤 😡 🥺 😳 🤯 😬 🙃 😇 👍 👎 👏 🙌 🙏 💪 🤝 ✌️ 👋 🤌 '
             + '❤️ 🧡 💛 💚 💙 💜 🖤 💔 ✨ 🔥 ⭐ 💯 🎉 🎁 👗 👕 👖 👟 👜 🧥 '
             + '♻️ 🌍 🌱 🍃 💧 🛍️ 💰 📦 ⏰ ✅').split(' ');

  var picker = $('[data-picker]');

  // Build the emoji grid once, the first time it is needed.
  function buildEmoji() {
    var grid = $('[data-emoji-grid]');
    if (!grid || grid.children.length) return;
    grid.innerHTML = EMOJI.map(function (ch) {
      return '<button type="button" data-emoji="' + ch + '">' + ch + '</button>';
    }).join('');
  }

  // The GIF tab only exists if a Giphy key was set in config.
  if (GIPHY) { var gt = $('[data-gif-tab]'); if (gt) gt.hidden = false; }

  function loadGifs(q) {
    var grid = $('[data-gif-grid]');
    if (!grid || !GIPHY) return;
    grid.innerHTML = '<p class="gif-note">Loading</p>';
    var base = q ? 'https://api.giphy.com/v1/gifs/search?q=' + encodeURIComponent(q) + '&'
                 : 'https://api.giphy.com/v1/gifs/trending?';
    fetch(base + 'api_key=' + GIPHY + '&limit=24&rating=pg')
      .then(function (res) { return res.json(); })
      .then(function (d) {
        var items = (d && d.data) || [];
        if (!items.length) { grid.innerHTML = '<p class="gif-note">Nothing found</p>'; return; }
        grid.innerHTML = items.map(function (g) {
          var small = g.images.fixed_width_small && g.images.fixed_width_small.url;
          var full  = g.images.fixed_width && g.images.fixed_width.url;
          return '<button type="button" data-gif="' + esc(full || small) + '">' +
                 '<img src="' + esc(small || full) + '" alt="" loading="lazy"></button>';
        }).join('');
      })
      .catch(function () { grid.innerHTML = '<p class="gif-note">GIFs are not loading right now</p>'; });
  }

  var gifTimer = null;
  var gifQ = $('[data-gif-q]');
  if (gifQ) {
    gifQ.addEventListener('input', function () {
      clearTimeout(gifTimer);
      var v = this.value.trim();
      gifTimer = setTimeout(function () { loadGifs(v); }, 350);
    });
  }

  /* ---------- PHOTO AND VIDEO ATTACHMENTS ---------- */
  var MAX_UPLOAD = window.CSH_MAX_UPLOAD || (2 * 1024 * 1024);
  function tooBig(file) {
    if (file.size <= MAX_UPLOAD) return false;
    alert('That file is ' + (file.size / 1048576).toFixed(1) + ' MB, which is over the ' +
          (MAX_UPLOAD / 1048576).toFixed(1) + ' MB limit on this server.');
    return true;
  }

  var attachInput = $('[data-attach-input]');
  var attachBtn = $('[data-attach-btn]');
  if (attachBtn && attachInput) {
    attachBtn.addEventListener('click', function () { attachInput.click(); });
    attachInput.addEventListener('change', function () {
      var file = attachInput.files && attachInput.files[0];
      attachInput.value = '';
      if (!file || !state.with) return;
      if (tooBig(file)) return;
      var kind = file.type.indexOf('video') === 0 ? 'video' : 'image';
      var label = kind === 'video' ? 'Video' : 'Photo';
      attachBtn.disabled = true;
      postFile('send', { to: state.with, body: label, kind: kind }, file).then(function (r) {
        attachBtn.disabled = false;
        if (r.ok) { loadThread(true); loadList(); }
        else alert(r.error || 'That could not be sent.');
      });
    });
  }

  /* ---------- VOICE NOTES ---------- */
  var voiceBtn = $('[data-voice-btn]');
  var voiceBar = $('[data-voice-bar]');
  var voiceTime = $('[data-voice-time]');
  var voiceCancel = $('[data-voice-cancel]');
  var recorder = null, recChunks = [], recStream = null, recTimer = null, recSeconds = 0, recCancelled = false;

  function stopStream() {
    if (recStream) { recStream.getTracks().forEach(function (t) { t.stop(); }); recStream = null; }
  }
  function resetVoiceUI() {
    clearInterval(recTimer);
    recSeconds = 0;
    if (voiceTime) voiceTime.textContent = '0:00';
    if (voiceBar) voiceBar.hidden = true;
    if (voiceBtn) voiceBtn.classList.remove('is-recording');
  }
  function startRecording() {
    if (!navigator.mediaDevices || !window.MediaRecorder) {
      alert('Voice notes need microphone access, which this browser will not give a plain http page.');
      return;
    }
    navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
      recStream = stream;
      recChunks = [];
      recCancelled = false;
      var mime = MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : '';
      recorder = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream);
      recorder.addEventListener('dataavailable', function (e) { if (e.data && e.data.size) recChunks.push(e.data); });
      recorder.addEventListener('stop', function () {
        stopStream();
        if (recCancelled || !recChunks.length) { resetVoiceUI(); return; }
        var blob = new Blob(recChunks, { type: recorder.mimeType || 'audio/webm' });
        var file = new File([blob], 'voice-note.webm', { type: blob.type });
        resetVoiceUI();
        if (!state.with) return;
        postFile('send', { to: state.with, body: 'Voice note', kind: 'voice' }, file).then(function (r) {
          if (r.ok) { loadThread(true); loadList(); }
          else alert(r.error || 'That could not be sent.');
        });
      });
      recorder.start();
      if (voiceBtn) voiceBtn.classList.add('is-recording');
      if (voiceBar) voiceBar.hidden = false;
      recSeconds = 0;
      recTimer = setInterval(function () {
        recSeconds++;
        var m = Math.floor(recSeconds / 60), s = recSeconds % 60;
        if (voiceTime) voiceTime.textContent = m + ':' + (s < 10 ? '0' : '') + s;
        if (recSeconds >= 120) stopRecording(false); // two minutes is plenty for a chat voice note
      }, 1000);
    }).catch(function () {
      alert('Microphone access was blocked, so the voice note was not recorded.');
    });
  }
  function stopRecording(cancelled) {
    recCancelled = !!cancelled;
    if (recorder && recorder.state !== 'inactive') recorder.stop();
    else resetVoiceUI();
  }
  if (voiceBtn) {
    voiceBtn.addEventListener('click', function () {
      if (recorder && recorder.state === 'recording') stopRecording(false);
      else startRecording();
    });
  }
  if (voiceCancel) {
    voiceCancel.addEventListener('click', function () { stopRecording(true); });
  }

  $('[data-composer]').addEventListener('submit', function (e) {
    e.preventDefault();
    var inp = $('[data-input]'), text = inp.value.trim();
    if (!text || !state.with) return;
    inp.value = '';
    post('send', { to: state.with, body: text }).then(function (r) {
      if (r.ok) { loadThread(true); loadList(); }
    });
  });

  root.addEventListener('click', function (e) {
    var t = e.target;

    // Open and close the picker.
    if (t.closest('[data-picker-btn]')) {
      buildEmoji();
      picker.hidden = !picker.hidden;
      return;
    }

    // Emoji drops into the box at the cursor, it does not send on its own.
    var em = t.closest('[data-emoji]');
    if (em) {
      var inp = $('[data-input]');
      var pos = inp.selectionStart || inp.value.length;
      inp.value = inp.value.slice(0, pos) + em.dataset.emoji + inp.value.slice(pos);
      inp.focus();
      inp.selectionEnd = inp.selectionStart = pos + em.dataset.emoji.length;
      return;
    }

    // A GIF sends straight away, there is nothing to type with it.
    var gf = t.closest('[data-gif]');
    if (gf && state.with) {
      picker.hidden = true;
      post('send', { to: state.with, body: 'GIF', kind: 'gif', media: gf.dataset.gif })
        .then(function (r) { if (r.ok) { loadThread(true); loadList(); } });
      return;
    }

    // Switching between the emoji and GIF tabs.
    var tab = t.closest('[data-tab]');
    if (tab) {
      var which = tab.dataset.tab;
      $$('[data-tab]').forEach(function (b) { b.classList.toggle('is-on', b === tab); });
      $$('[data-pane]').forEach(function (p) { p.hidden = p.dataset.pane !== which; });
      if (which === 'gif' && !$('[data-gif-grid]').children.length) loadGifs('');
      return;
    }

    if (picker && !picker.hidden && !t.closest('[data-picker]')) picker.hidden = true;

    var row = t.closest('[data-open-chat]');
    if (row) { openChat(parseInt(row.dataset.openChat, 10)); return; }
    if (t.closest('[data-back]')) { root.classList.remove('is-open'); return; }

    var f = t.closest('[data-filter]');
    if (f) {
      state.filter = f.dataset.filter;
      $$('.chip').forEach(function (c) { c.classList.toggle('is-on', c === f); });
      loadList(); return;
    }

    if (t.closest('[data-room-menu-btn]')) {
      var rm = $('[data-room-menu]');
      rm.hidden = !rm.hidden;
      return;
    }

    var act = t.closest('[data-act]');
    if (act) {
      $('[data-room-menu]').hidden = true;
      var a = act.dataset.act;
      if (a === 'view-profile' && state.with) {
        window.open('member.php?id=' + state.with, '_blank');
        return;
      }
      if (a === 'rate' && state.with) {
        window.location.href = 'rate-member.php?id=' + state.with;
        return;
      }
      if (a === 'report' && state.with) {
        document.getElementById('reportPartnerId').value = state.with;
        document.getElementById('chatReportModal').hidden = false;
        return;
      }
      if (a === 'select-msgs') {
        enterMsgSelection();
        return;
      }
      if (a === 'archive-chat' && state.with) {
        if (!confirm('Archive this chat? You can find it again under the Archived filter.')) return;
        post('archive', { partner: state.with }).then(function (r) {
          if (r.ok) { state.with = 0; loadList(); closeRoom(); toast('Chat archived'); }
          else alert(r.error || 'Could not archive.');
        });
        return;
      }
    }

    if (!t.closest('[data-room-menu]')) {
      var rm = $('[data-room-menu]');
      if (rm) rm.hidden = true;
    }
  });

  var searchTimer;
  $('[data-search]').addEventListener('input', function () {
    state.search = this.value.trim();
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadList, 250);
  });

  setInterval(function () {
    if (document.hidden) return;
    get('poll', { with: state.with }).then(function (r) {
      if (!r || !r.ok || !state.with) return;
      var newMsg = r.latest && r.latest !== state.latest;
      // Their unread count dropping means they just opened what I sent, so
      // the thread needs redrawing for the ticks even with no new message.
      var seenChanged = (typeof r.unseen === 'number' && r.unseen !== state.unseen);
      if (newMsg || seenChanged) {
        if (newMsg) state.latest = r.latest;
        state.unseen = r.unseen;
        loadThread(newMsg); loadList();
      }
    });
  }, 5000);

  if (openWith) {
    openChat(openWith);
  }

  /* ---------- MESSAGE SELECTION + DELETE ---------- */
  var selMode = false;
  var selIds = [];

  function enterMsgSelection() {
    selMode = true;
    selIds = [];
    var msgs = $('[data-msgs]');
    if (msgs) msgs.classList.add('selecting');
    var tb = $('[data-sel-toolbar]');
    if (tb) tb.hidden = false;
    updateSelCount();
  }

  function exitMsgSelection() {
    selMode = false;
    selIds = [];
    var msgs = $('[data-msgs]');
    if (msgs) {
      msgs.classList.remove('selecting');
      $$('.bub-row.selected', msgs).forEach(function (r) { r.classList.remove('selected'); });
    }
    var tb = $('[data-sel-toolbar]');
    if (tb) tb.hidden = true;
  }

  function updateSelCount() {
    var el = $('[data-sel-count]');
    if (el) el.textContent = selIds.length + ' selected';
  }

  function closeRoom() {
    var room = $('[data-room]');
    if (room) room.hidden = true;
    var blank = $('[data-blank]');
    if (blank) blank.hidden = false;
    root.classList.remove('is-open');
  }

  // Clicking a bubble row in selection mode toggles it
  document.addEventListener('click', function (e) {
    if (!selMode) return;
    var row = e.target.closest('.bub-row');
    if (!row) return;
    var id = row.getAttribute('data-mid');
    if (!id) return;
    id = parseInt(id, 10);
    if (row.classList.contains('selected')) {
      row.classList.remove('selected');
      selIds = selIds.filter(function (i) { return i !== id; });
    } else {
      row.classList.add('selected');
      selIds.push(id);
    }
    updateSelCount();
  });

  // Cancel selection
  var selCancel = $('[data-sel-cancel]');
  if (selCancel) selCancel.addEventListener('click', exitMsgSelection);

  // Delete selected messages
  var selDelete = $('[data-sel-delete]');
  if (selDelete) {
    selDelete.addEventListener('click', function () {
      if (!selIds.length) return;
      if (!confirm('Delete ' + selIds.length + ' message' + (selIds.length > 1 ? 's' : '') + '? They will be removed from your view.')) return;
      post('delete_messages', { ids: selIds.join(',') }).then(function (r) {
        exitMsgSelection();
        if (r.ok) { loadThread(false); loadList(); }
        else alert(r.error || 'Could not delete.');
      });
    });
  }
})();

function setChatReason(r) {
  document.getElementById('chatReasonInput').value = r;
  var chips = document.querySelectorAll('#chatReportModal .reason-chip');
  chips.forEach(function(c) {
    c.classList.toggle('selected', c.textContent.trim() === r || r.indexOf(c.textContent.trim()) !== -1);
  });
}
function closeChatReport() {
  document.getElementById('chatReportModal').hidden = true;
}

document.getElementById('chatReportForm').addEventListener('submit', function(e) {
  e.preventDefault();
  var fd = new FormData(this);
  fetch('member-actions.php', { method: 'POST', body: fd, credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      closeChatReport();
      alert(data.ok ? 'Your report has been submitted to the administration team.' : (data.error || 'Failed to submit report.'));
    })
    .catch(function() {
      closeChatReport();
      alert('Your report has been submitted to the administration team.');
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>