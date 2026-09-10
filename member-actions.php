<?php
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in to perform this action.']);
    exit;
}

$me = current_user();
$myId = (int)$me['id'];
$pdo = db();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// The real schema (cshatelier.sql / upgrade-009) uses reported_id, not
// member_id, so I match that here. The CREATE TABLE IF NOT EXISTS is
// just a safety net for databases that have not run the migration yet.

// Handle Member Reports (custom reason or suggested)
if ($action === 'report') {
    $memberId = (int)($_POST['member_id'] ?? 0);
    $reason   = trim((string)($_POST['reason'] ?? ''));
    $details  = trim((string)($_POST['details'] ?? ''));

    if ($memberId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Invalid member selected.']);
        exit;
    }
    if ($memberId === $myId) {
        echo json_encode(['ok' => false, 'error' => 'You cannot report your own account.']);
        exit;
    }

    // Admins are the ones who read the reports queue, so there is nobody for a
    // report against them to go to. Blocked here on the server as well as in
    // the pages, because the pages can be worked around and this cannot.
    $roleStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $roleStmt->execute([$memberId]);
    if ($roleStmt->fetchColumn() === 'admin') {
        echo json_encode(['ok' => false, 'error' => 'This account cannot be reported.']);
        exit;
    }
    if ($reason === '') {
        echo json_encode(['ok' => false, 'error' => 'Please pick or state a reason for this report.']);
        exit;
    }

    try {
        $ins = $pdo->prepare("INSERT INTO member_reports (reporter_id, reported_id, reason, details, created_at) VALUES (?, ?, ?, ?, NOW())");
        $ins->execute([$myId, $memberId, $reason, $details]);
        echo json_encode(['ok' => true, 'message' => 'Report submitted to the administration team.']);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action.']);