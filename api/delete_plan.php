<?php
// NutroApp - API: Delete Plan
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$plan_id = (int)((isset($_GET['id']) ? $_GET['id'] : 0));
if ($plan_id) {
    $db = getDB();
    $stmt = $db->prepare('DELETE FROM diet_plans WHERE id = ? AND user_id = ?');
    $stmt->execute(array($plan_id, $_SESSION['user_id']));
}

setFlash('success', 'გეგმა წარმატებით წაიშალა.');
redirect('/history.php');
