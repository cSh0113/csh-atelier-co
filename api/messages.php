<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth_required']);
    exit;
}

$me = current_user();
$myId = (int)$me['id'];
$action = $_REQUEST['action'] ?? '';
$pdo = db();

// Any hit on this file means I am active right now, so my last seen moves up.
// Wrapped in a try because older databases do not have the column yet.
try {
    $pdo->prepare("UPDATE users SET last_seen_at = NOW() WHERE id = ?")->execute([$myId]);
} catch (Exception $e) {}

/** Turns a last seen timestamp into online, or wording a person can read. */
function presence(?string $seenAt): array
{
    if (!$seenAt) return ['online' => false, 'last_seen' => null];
    $gap = time() - strtotime($seenAt);
    if ($gap < 120)   return ['online' => true,  'last_seen' => null];
    if ($gap < 3600)  return ['online' => false, 'last_seen' => 'last seen ' . max(2, (int)($gap / 60)) . ' min ago'];
    if ($gap < 86400) return ['online' => false, 'last_seen' => 'last seen ' . (int)($gap / 3600) . 'h ago'];
    if ($gap < 604800) return ['online' => false, 'last_seen' => 'last seen ' . (int)($gap / 86400) . 'd ago'];
    return ['online' => false, 'last_seen' => 'last seen ' . date('j M', strtotime($seenAt))];
}

// Guards below. Older databases are missing some of the messaging tables
// and columns, so I check before I query instead of crashing the inbox.
function table_exists(PDO $pdo, string $table): bool {
    try {
        $result = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
        return $result && $result->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Same idea, one level down.
function column_exists(PDO $pdo, string $table, string $column): bool {
    try {
        $result = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column));
        return $result && $result->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

$hasMeta = table_exists($pdo, 'conversation_state');
$hasReactions = table_exists($pdo, 'message_reactions');
$hasBlocks = table_exists($pdo, 'user_blocks');
$hasKind = column_exists($pdo, 'messages', 'kind');
$hasReply = column_exists($pdo, 'messages', 'reply_to');
$hasStar = column_exists($pdo, 'messages', 'is_starred');
$hasEdited = column_exists($pdo, 'messages', 'is_edited');
$hasDeleteCols = column_exists($pdo, 'messages', 'deleted_for_sender');

switch ($action) {
    case 'conversations':
        $filter = $_GET['filter'] ?? 'all';
        $q = trim((string)($_GET['q'] ?? ''));

        // Everyone this member has a thread with.
        $sql = "
            SELECT 
                u.id, 
                u.name, 
                u.avatar_url,
                u.last_seen_at,
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
        ";

        $params = [
            'me1' => $myId, 'me2' => $myId, 'me3' => $myId, 'me4' => $myId,
            'me5' => $myId, 'me6' => $myId, 'me7' => $myId, 'me8' => $myId,
            'me9' => $myId, 'me10' => $myId, 'me11' => $myId
        ];

        if ($q !== '') {
            $sql .= " AND u.name LIKE :q ";
            $params['q'] = "%$q%";
        }

        $sql .= " ORDER BY last_time DESC";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            $conversations = [];
            foreach ($rows as $r) {
                $pid = (int)$r['id'];

                $isPinned = false;
                $isMuted = false;
                $isArchived = false;

                if ($hasMeta) {
                    $mStmt = $pdo->prepare("SELECT is_pinned, is_muted, is_archived FROM conversation_state WHERE owner_id = ? AND partner_id = ?");
                    $mStmt->execute([$myId, $pid]);
                    if ($meta = $mStmt->fetch()) {
                        $isPinned = (bool)$meta['is_pinned'];
                        $isMuted = (bool)$meta['is_muted'];
                        $isArchived = (bool)$meta['is_archived'];
                    }
                }

                if ($filter === 'unread' && (int)$r['unread'] === 0) continue;
                if ($filter === 'archived' && !$isArchived) continue;
                if ($filter === 'all' && $isArchived) continue;

                $conversations[] = [
                    'id'       => $pid,
                    'name'     => $r['name'],
                    'initial'  => mb_strtoupper(mb_substr($r['name'], 0, 1)),
                    'avatar_url' => $r['avatar_url'] ?: null,
                    'preview'  => (string)($r['last_msg'] ?? ''),
                    'outgoing' => ((int)$r['last_sender'] === $myId),
                    'at'       => $r['last_time'] ?? '',
                    'unread'   => (int)$r['unread'],
                    'pinned'   => $isPinned,
                    'muted'    => $isMuted,
                    'online'   => presence($r['last_seen_at'] ?? null)['online']
                ];
            }

            echo json_encode(['ok' => true, 'conversations' => $conversations]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'thread':
        $with = (int)($_GET['with'] ?? 0);
        if ($with <= 0) {
            echo json_encode(['ok' => false, 'error' => 'invalid_partner']);
            exit;
        }

        // Who they are talking to.
        $pStmt = $pdo->prepare("SELECT id, name, avatar_url, city, role, last_seen_at FROM users WHERE id = ?");
        $pStmt->execute([$with]);
        $partner = $pStmt->fetch();
        if (!$partner) {
            echo json_encode(['ok' => false, 'error' => 'not_found']);
            exit;
        }

        // Opening the thread marks it read.
        $up = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
        $up->execute([$with, $myId]);

        // Nobody sends anything if either side blocked the other.
        $iBlocked = false;
        $theyBlocked = false;
        if ($hasBlocks) {
            $b1 = $pdo->prepare("SELECT id FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?");
            $b1->execute([$myId, $with]);
            $iBlocked = (bool)$b1->fetch();

            $b2 = $pdo->prepare("SELECT id FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?");
            $b2->execute([$with, $myId]);
            $theyBlocked = (bool)$b2->fetch();
        }

        // The messages themselves, oldest first.
        $sql = "SELECT m.* FROM messages m WHERE ((m.sender_id = :me AND m.receiver_id = :with) OR (m.sender_id = :with2 AND m.receiver_id = :me2))";
        if ($hasDeleteCols) {
            $sql .= " AND NOT (m.sender_id = :me3 AND m.deleted_for_sender = 1) AND NOT (m.receiver_id = :me4 AND m.deleted_for_receiver = 1)";
        }
        $sql .= " ORDER BY m.id ASC";

        try {
            $mStmt = $pdo->prepare($sql);
            $params = ['me' => $myId, 'with' => $with, 'with2' => $with, 'me2' => $myId];
            if ($hasDeleteCols) {
                $params['me3'] = $myId;
                $params['me4'] = $myId;
            }
            $mStmt->execute($params);
            $raw = $mStmt->fetchAll();

            $messages = [];
            foreach ($raw as $m) {
                $mine = ((int)$m['sender_id'] === $myId);
                $msgItem = [
                    'id'      => (int)$m['id'],
                    'mine'    => $mine,
                    'body'    => (string)$m['body'],
                    'at'      => $m['created_at'],
                    'read'    => (bool)($m['is_read'] ?? false),
                    'kind'    => $hasKind ? ($m['kind'] ?? 'text') : 'text',
                    'media'   => $hasKind ? ($m['media_ref'] ?? null) : null,
                    'starred' => $hasStar ? (bool)($m['is_starred'] ?? false) : false,
                    'edited'  => $hasEdited ? (bool)($m['is_edited'] ?? false) : false,
                    'reply'   => null,
                    'reactions' => []
                ];

                if ($hasReactions) {
                    $rStmt = $pdo->prepare("SELECT emoji, user_id FROM message_reactions WHERE message_id = ?");
                    $rStmt->execute([(int)$m['id']]);
                    $msgItem['reactions'] = $rStmt->fetchAll();
                }

                $messages[] = $msgItem;
            }

            $seen = presence($partner['last_seen_at'] ?? null);

            echo json_encode([
                'ok' => true,
                'partner' => [
                    'id' => (int)$partner['id'],
                    'name' => $partner['name'],
                    'initial' => mb_strtoupper(mb_substr($partner['name'], 0, 1)),
                    'avatar_url' => $partner['avatar_url'] ?: null,
                    'city' => $partner['city'] ?? 'South Africa',
                    'online' => $seen['online'],
                    'last_seen' => $seen['last_seen'],
                    'is_admin' => (($partner['role'] ?? '') === 'admin'),
                    'rating' => 5.0,
                    'rating_count' => 0
                ],
                'blocked' => [
                    'i_blocked' => $iBlocked,
                    'they_blocked' => $theyBlocked
                ],
                'typing' => false,
                'messages' => $messages
            ]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'send':
        $to = (int)($_POST['to'] ?? 0);
        $body = trim((string)($_POST['body'] ?? ''));
        $kind = trim((string)($_POST['kind'] ?? 'text'));
        $media = trim((string)($_POST['media'] ?? ''));
        $replyTo = (int)($_POST['reply_to'] ?? 0);

        if (!verify_csrf($_POST['csrf'] ?? null)) {
            echo json_encode(['ok' => false, 'error' => 'Security token expired, please refresh.']);
            exit;
        }

        // A photo, video or voice note arrives as an uploaded file rather
        // than a ready made link the way a GIF does, so this has to be
        // stored on disk first before it can go in the message row.
        if (in_array($kind, ['image', 'video', 'voice'], true)) {
            // An upload bigger than post_max_size arrives with $_POST and
            // $_FILES both empty, so that has to be caught separately or
            // it just looks like an empty message.
            if (empty($_FILES['media_file'])) {
                $cap = round(max_upload_bytes() / 1048576, 1);
                echo json_encode(['ok' => false, 'error' => 'That file is too large for the server. The limit here is about ' . $cap . ' MB.']);
                exit;
            }
            $err = $_FILES['media_file']['error'] ?? UPLOAD_ERR_NO_FILE;
            if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
                $cap = round(max_upload_bytes() / 1048576, 1);
                echo json_encode(['ok' => false, 'error' => 'That file is too large. The limit here is about ' . $cap . ' MB.']);
                exit;
            }
            $result = upload_chat_media($_FILES['media_file'], $kind);
            if (isset($result['error'])) {
                echo json_encode(['ok' => false, 'error' => $result['error']]);
                exit;
            }
            $media = $result['url'];
        }

        if ($to <= 0 || ($body === '' && $media === '')) {
            echo json_encode(['ok' => false, 'error' => 'empty']);
            exit;
        }

        if ($hasKind) {
            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, receiver_id, body, kind, media_ref, reply_to, is_read, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$myId, $to, $body, $kind, $media ?: null, $replyTo ?: null]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, receiver_id, body, is_read, created_at)
                VALUES (?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$myId, $to, $body]);
        }

        echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
        break;

    case 'poll':
        $with = (int)($_GET['with'] ?? 0);
        $latest = 0;
        $unseen = 0;
        if ($with > 0) {
            $stmt = $pdo->prepare("
                SELECT id FROM messages 
                WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$myId, $with, $with, $myId]);
            $latest = (int)$stmt->fetchColumn();

            // How many of mine they still have not opened. Reading a message
            // does not create a new row, so without this the ticks would
            // never turn blue until somebody happened to send another one.
            $uStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
            $uStmt->execute([$myId, $with]);
            $unseen = (int)$uStmt->fetchColumn();
        }
        echo json_encode(['ok' => true, 'latest' => $latest, 'unseen' => $unseen, 'typing' => false]);
        break;

    case 'delete_messages':
        if (!verify_csrf($_POST['csrf'] ?? null)) {
            echo json_encode(['ok' => false, 'error' => 'Security token expired.']);
            exit;
        }
        $rawIds = trim((string)($_POST['ids'] ?? ''));
        if ($rawIds === '') {
            echo json_encode(['ok' => false, 'error' => 'No messages selected.']);
            exit;
        }
        // Only integers, nothing funny.
        $ids = array_filter(array_map('intval', explode(',', $rawIds)));
        if (!$ids) {
            echo json_encode(['ok' => false, 'error' => 'No valid message ids.']);
            exit;
        }

        // The database might have the per side delete columns from upgrade-011,
        // or it might not. If it does, soft delete. If not, hard delete
        // only the messages this member sent (you can't delete what someone
        // else wrote if there is no per-side soft delete column).
        if ($hasDeleteCols) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            // Messages I sent: hide them from my side only
            $pdo->prepare("UPDATE messages SET deleted_for_sender = 1 WHERE id IN ($in) AND sender_id = ?")
                ->execute(array_merge($ids, [$myId]));
            // Messages I received: hide them from my side only
            $pdo->prepare("UPDATE messages SET deleted_for_receiver = 1 WHERE id IN ($in) AND receiver_id = ?")
                ->execute(array_merge($ids, [$myId]));
        } else {
            // No soft delete columns, so only delete messages the user sent.
            $in = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM messages WHERE id IN ($in) AND sender_id = ?")
                ->execute(array_merge($ids, [$myId]));
        }

        echo json_encode(['ok' => true]);
        break;

    case 'archive':
        if (!verify_csrf($_POST['csrf'] ?? null)) {
            echo json_encode(['ok' => false, 'error' => 'Security token expired.']);
            exit;
        }
        $partner = (int)($_POST['partner'] ?? 0);
        if ($partner <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Invalid partner.']);
            exit;
        }
        if ($hasMeta) {
            $pdo->prepare("INSERT INTO conversation_state (owner_id, partner_id, is_archived) VALUES (?, ?, 1)
                           ON DUPLICATE KEY UPDATE is_archived = 1")
                ->execute([$myId, $partner]);
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Archive is not available on this database version.']);
        }
        break;

    case 'unarchive':
        if (!verify_csrf($_POST['csrf'] ?? null)) {
            echo json_encode(['ok' => false, 'error' => 'Security token expired.']);
            exit;
        }
        $partner = (int)($_POST['partner'] ?? 0);
        if ($partner <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Invalid partner.']);
            exit;
        }
        if ($hasMeta) {
            $pdo->prepare("UPDATE conversation_state SET is_archived = 0 WHERE owner_id = ? AND partner_id = ?")
                ->execute([$myId, $partner]);
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Archive is not available on this database version.']);
        }
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'unknown_action']);
        break;
}