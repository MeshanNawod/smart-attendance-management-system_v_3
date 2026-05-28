<?php
/**
 * Ratings & Forum Comments API — Sentinel Swarm AMS v3
 * Handles star-ratings (1–5) and threaded forum comments for
 * teachers, courses, and sessions.
 *
 * GET  ?action=get_ratings&target_type=teacher&target_id=3
 * GET  ?action=get_comments&target_type=course&target_id=CSC2052&limit=50&offset=0
 * GET  ?action=get_summary&target_type=teacher&target_id=3
 * GET  ?action=get_board   (all targets overview)
 * POST action=submit_rating  | submit_comment | delete_comment | toggle_like | pin_comment | edit_comment
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'GET' ? ($_GET['action'] ?? '') : ($_POST['action'] ?? '');

$me      = currentUser();
$userId  = (int)($me['id'] ?? 0);
$role    = $me['role'] ?? '';

if (!$userId) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated.']); exit;
}

// ── helpers ────────────────────────────────────────────────────────────────
function sanitizeTargetType(string $t): string {
    return in_array($t, ['teacher','course','session'], true) ? $t : '';
}
function sanitizeStars($s): int {
    $s = (int)$s; return max(1, min(5, $s));
}

// ── ROUTER ─────────────────────────────────────────────────────────────────
switch ($action) {

// ────────────────────────────────────────────────────────────────────────────
// GET: aggregate summary for one target
// ────────────────────────────────────────────────────────────────────────────
case 'get_summary': {
    $tt  = sanitizeTargetType($_GET['target_type'] ?? '');
    $tid = trim($_GET['target_id'] ?? '');
    if (!$tt || !$tid) { echo json_encode(['status'=>'error','message'=>'Missing params']); exit; }

    try {
        // Average rating + count
        $stmt = $pdo->prepare(
            "SELECT ROUND(AVG(stars),1) as avg_stars, COUNT(*) as total_ratings,
                    SUM(stars=1) as s1, SUM(stars=2) as s2, SUM(stars=3) as s3,
                    SUM(stars=4) as s4, SUM(stars=5) as s5
             FROM ratings WHERE target_type=? AND target_id=?"
        );
        $stmt->execute([$tt, $tid]);
        $rating = $stmt->fetch();

        // My own rating
        $myRating = null;
        $stmt2 = $pdo->prepare("SELECT stars FROM ratings WHERE user_id=? AND target_type=? AND target_id=?");
        $stmt2->execute([$userId, $tt, $tid]);
        $r = $stmt2->fetch();
        if ($r) $myRating = (int)$r['stars'];

        // Comment count
        $stmt3 = $pdo->prepare("SELECT COUNT(*) FROM forum_comments WHERE target_type=? AND target_id=? AND is_deleted=0");
        $stmt3->execute([$tt, $tid]);
        $commentCount = (int)$stmt3->fetchColumn();

        echo json_encode([
            'status'        => 'success',
            'avg_stars'     => $rating['avg_stars'] ? (float)$rating['avg_stars'] : null,
            'total_ratings' => (int)$rating['total_ratings'],
            'distribution'  => ['1'=>(int)$rating['s1'],'2'=>(int)$rating['s2'],'3'=>(int)$rating['s3'],'4'=>(int)$rating['s4'],'5'=>(int)$rating['s5']],
            'my_rating'     => $myRating,
            'comment_count' => $commentCount,
        ]);
    } catch (PDOException $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    break;
}

// ────────────────────────────────────────────────────────────────────────────
// GET: forum board overview (all targets with ratings + recent comment)
// ────────────────────────────────────────────────────────────────────────────
case 'get_board': {
    try {
        // Teachers with avg rating
        $teachers = $pdo->query(
            "SELECT t.id, t.teacher_name, t.department,
                    ROUND(AVG(r.stars),1) as avg_stars, COUNT(DISTINCT r.id) as rating_count,
                    COUNT(DISTINCT fc.id) as comment_count
             FROM teachers t
             LEFT JOIN ratings r ON r.target_type='teacher' AND r.target_id=CAST(t.id AS CHAR)
             LEFT JOIN forum_comments fc ON fc.target_type='teacher' AND fc.target_id=CAST(t.id AS CHAR) AND fc.is_deleted=0
             GROUP BY t.id, t.teacher_name, t.department
             ORDER BY avg_stars DESC, t.teacher_name ASC"
        )->fetchAll();

        // Courses with avg rating
        $courses = $pdo->query(
            "SELECT c.course_code, c.course_name,
                    ROUND(AVG(r.stars),1) as avg_stars, COUNT(DISTINCT r.id) as rating_count,
                    COUNT(DISTINCT fc.id) as comment_count
             FROM courses c
             LEFT JOIN ratings r ON r.target_type='course' AND r.target_id=c.course_code
             LEFT JOIN forum_comments fc ON fc.target_type='course' AND fc.target_id=c.course_code AND fc.is_deleted=0
             GROUP BY c.course_code, c.course_name
             ORDER BY avg_stars DESC, c.course_code ASC"
        )->fetchAll();

        echo json_encode(['status'=>'success','teachers'=>$teachers,'courses'=>$courses]);
    } catch (PDOException $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    break;
}

// ────────────────────────────────────────────────────────────────────────────
// GET: paginated comments for one target (threaded)
// ────────────────────────────────────────────────────────────────────────────
case 'get_comments': {
    $tt     = sanitizeTargetType($_GET['target_type'] ?? '');
    $tid    = trim($_GET['target_id'] ?? '');
    $limit  = min((int)($_GET['limit'] ?? 30), 100);
    $offset = max((int)($_GET['offset'] ?? 0), 0);
    if (!$tt || !$tid) { echo json_encode(['status'=>'error','message'=>'Missing params']); exit; }

    try {
        // Top-level comments
        $stmt = $pdo->prepare(
            "SELECT fc.id, fc.user_id, fc.user_role, fc.body, fc.is_pinned,
                    fc.created_at, fc.updated_at, fc.parent_id,
                    CASE WHEN fc.user_role='student' THEN COALESCE(u.full_name, 'Student') ELSE COALESCE(u.full_name, u.username) END as author_name,
                    (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id=fc.id) as like_count,
                    (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id=fc.id AND cl.user_id=?) as i_liked
             FROM forum_comments fc
             LEFT JOIN users u ON u.id = fc.user_id
             WHERE fc.target_type=? AND fc.target_id=? AND fc.parent_id IS NULL AND fc.is_deleted=0
             ORDER BY fc.is_pinned DESC, fc.created_at DESC
             LIMIT " . (int)$limit . " OFFSET " . (int)$offset
        );
        $stmt->execute([$userId, $tt, $tid]);
        $topLevel = $stmt->fetchAll();

        // Replies for those top-level comments
        $ids = array_column($topLevel, 'id');
        $replies = [];
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt2 = $pdo->prepare(
                "SELECT fc.id, fc.user_id, fc.user_role, fc.body, fc.parent_id,
                        fc.created_at, fc.updated_at,
                        CASE WHEN fc.user_role='student' THEN COALESCE(u.full_name, 'Student') ELSE COALESCE(u.full_name, u.username) END as author_name,
                        (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id=fc.id) as like_count,
                        (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id=fc.id AND cl.user_id=?) as i_liked
                 FROM forum_comments fc
                 LEFT JOIN users u ON u.id = fc.user_id
                 WHERE fc.parent_id IN ($in) AND fc.is_deleted=0
                 ORDER BY fc.created_at ASC"
            );
            $params = array_merge([$userId], $ids);
            $stmt2->execute($params);
            $rawReplies = $stmt2->fetchAll();
            foreach ($rawReplies as $rep) {
                $replies[(int)$rep['parent_id']][] = $rep;
            }
        }

        // Embed replies
        foreach ($topLevel as &$c) {
            $c['replies'] = $replies[(int)$c['id']] ?? [];
            $c['is_mine'] = ((int)$c['user_id'] === $userId);
        }
        unset($c);

        // Total count
        $stmt3 = $pdo->prepare("SELECT COUNT(*) FROM forum_comments WHERE target_type=? AND target_id=? AND parent_id IS NULL AND is_deleted=0");
        $stmt3->execute([$tt, $tid]);
        $total = (int)$stmt3->fetchColumn();

        echo json_encode(['status'=>'success','comments'=>$topLevel,'total'=>$total,'limit'=>$limit,'offset'=>$offset]);
    } catch (PDOException $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    break;
}

// ────────────────────────────────────────────────────────────────────────────
// POST: submit or update a star rating
// ────────────────────────────────────────────────────────────────────────────
case 'submit_rating': {
    requireCsrfIfMutating();
    $tt    = sanitizeTargetType($_POST['target_type'] ?? '');
    $tid   = trim($_POST['target_id'] ?? '');
    $stars = sanitizeStars($_POST['stars'] ?? 0);
    if (!$tt || !$tid) { echo json_encode(['status'=>'error','message'=>'Missing params']); exit; }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO ratings (user_id, user_role, target_type, target_id, stars)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE stars=VALUES(stars), updated_at=NOW()"
        );
        $stmt->execute([$userId, $role, $tt, $tid, $stars]);

        // Return new summary
        $stmt2 = $pdo->prepare("SELECT ROUND(AVG(stars),1) as avg, COUNT(*) as n FROM ratings WHERE target_type=? AND target_id=?");
        $stmt2->execute([$tt, $tid]);
        $s = $stmt2->fetch();
        echo json_encode(['status'=>'success','my_stars'=>$stars,'avg_stars'=>(float)$s['avg'],'total'=>(int)$s['n']]);
    } catch (PDOException $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    break;
}

// ────────────────────────────────────────────────────────────────────────────
// POST: submit a new comment or reply
// ────────────────────────────────────────────────────────────────────────────
case 'submit_comment': {
    requireCsrfIfMutating();
    $tt       = sanitizeTargetType($_POST['target_type'] ?? '');
    $tid      = trim($_POST['target_id'] ?? '');
    $body     = trim($_POST['body'] ?? '');
    $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    if (!$tt || !$tid || strlen($body) < 2) {
        echo json_encode(['status'=>'error','message'=>'Comment too short or missing params']); exit;
    }
    if (strlen($body) > 2000) {
        echo json_encode(['status'=>'error','message'=>'Comment too long (max 2000 chars)']); exit;
    }

    // Verify parent exists and matches same target
    if ($parentId) {
        $chk = $pdo->prepare("SELECT id FROM forum_comments WHERE id=? AND target_type=? AND target_id=? AND is_deleted=0");
        $chk->execute([$parentId, $tt, $tid]);
        if (!$chk->fetch()) { echo json_encode(['status'=>'error','message'=>'Invalid parent comment']); exit; }
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO forum_comments (user_id, user_role, target_type, target_id, parent_id, body)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $role, $tt, $tid, $parentId, $body]);
        $newId = (int)$pdo->lastInsertId();

        $stmt2 = $pdo->prepare(
            "SELECT fc.id, fc.user_id, fc.user_role, fc.body, fc.is_pinned, fc.parent_id,
                    fc.created_at, COALESCE(u.full_name, u.username) as author_name,
                    0 as like_count, 0 as i_liked
             FROM forum_comments fc LEFT JOIN users u ON u.id=fc.user_id WHERE fc.id=?"
        );
        $stmt2->execute([$newId]);
        $comment = $stmt2->fetch();
        $comment['is_mine'] = true;
        $comment['replies'] = [];

        echo json_encode(['status'=>'success','comment'=>$comment]);
    } catch (PDOException $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    break;
}

// ────────────────────────────────────────────────────────────────────────────
// POST: edit own comment
// ────────────────────────────────────────────────────────────────────────────
case 'edit_comment': {
    requireCsrfIfMutating();
    $commentId = (int)($_POST['comment_id'] ?? 0);
    $body      = trim($_POST['body'] ?? '');
    if (!$commentId || strlen($body) < 2) { echo json_encode(['status'=>'error','message'=>'Invalid']); exit; }

    try {
        // Only own comment OR admin
        $clause = isAdmin() ? "id=?" : "id=? AND user_id={$userId}";
        $stmt = $pdo->prepare("UPDATE forum_comments SET body=? WHERE $clause AND is_deleted=0");
        $stmt->execute([$body, $commentId]);
        $affected = $stmt->rowCount();
        echo json_encode(['status'=> $affected ? 'success' : 'error', 'message'=> $affected ? 'Updated' : 'Not found or forbidden']);
    } catch (PDOException $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    break;
}

// ────────────────────────────────────────────────────────────────────────────
// POST: soft-delete a comment
// ────────────────────────────────────────────────────────────────────────────
case 'delete_comment': {
    requireCsrfIfMutating();
    $commentId = (int)($_POST['comment_id'] ?? 0);
    if (!$commentId) { echo json_encode(['status'=>'error','message'=>'Invalid']); exit; }

    try {
        // Admin can delete any; others only own
        if (isAdmin()) {
            $stmt = $pdo->prepare("UPDATE forum_comments SET is_deleted=1 WHERE id=?");
            $stmt->execute([$commentId]);
        } else {
            $stmt = $pdo->prepare("UPDATE forum_comments SET is_deleted=1 WHERE id=? AND user_id=?");
            $stmt->execute([$commentId, $userId]);
        }
        $affected = $stmt->rowCount();
        echo json_encode(['status'=> $affected ? 'success' : 'error']);
    } catch (PDOException $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    break;
}

// ────────────────────────────────────────────────────────────────────────────
// POST: toggle like on a comment
// ────────────────────────────────────────────────────────────────────────────
case 'toggle_like': {
    requireCsrfIfMutating();
    $commentId = (int)($_POST['comment_id'] ?? 0);
    if (!$commentId) { echo json_encode(['status'=>'error','message'=>'Invalid']); exit; }

    try {
        // Check if already liked
        $stmt = $pdo->prepare("SELECT 1 FROM comment_likes WHERE user_id=? AND comment_id=?");
        $stmt->execute([$userId, $commentId]);
        if ($stmt->fetch()) {
            $pdo->prepare("DELETE FROM comment_likes WHERE user_id=? AND comment_id=?")->execute([$userId, $commentId]);
            $liked = false;
        } else {
            $pdo->prepare("INSERT IGNORE INTO comment_likes (user_id, comment_id) VALUES (?,?)")->execute([$userId, $commentId]);
            $liked = true;
        }
        $stmt2 = $pdo->prepare("SELECT COUNT(*) as n FROM comment_likes WHERE comment_id=?");
        $stmt2->execute([$commentId]); 
        $count = (int)$stmt2->fetch()['n'];
        echo json_encode(['status'=>'success','liked'=>$liked,'like_count'=>$count]);
    } catch (PDOException $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    break;
}

// ────────────────────────────────────────────────────────────────────────────
// POST: pin / unpin a comment (admin only)
// ────────────────────────────────────────────────────────────────────────────
case 'pin_comment': {
    requireCsrfIfMutating();
    if (!isAdmin()) { echo json_encode(['status'=>'error','message'=>'Admin only']); exit; }
    $commentId = (int)($_POST['comment_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("UPDATE forum_comments SET is_pinned = NOT is_pinned WHERE id=?");
        $stmt->execute([$commentId]);
        $pinStmt = $pdo->prepare("SELECT is_pinned FROM forum_comments WHERE id=?");
        $pinStmt->execute([$commentId]);
        $pinned = (bool)$pinStmt->fetchColumn();
        echo json_encode(['status'=>'success','pinned'=>$pinned]);
    } catch (PDOException $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    break;
}

default:
    echo json_encode(['status'=>'error','message'=>'Unknown action: '.$action]);
}
