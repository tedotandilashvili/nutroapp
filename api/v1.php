<?php
/**
 * NutroApp REST API v1
 * Ready for Android/iOS mobile conversion
 *
 * All endpoints return JSON.
 * Auth via session (web) — extend with JWT tokens for mobile.
 *
 * Routes (via query param ?action=):
 *   POST ?action=login        { email, password }
 *   POST ?action=register     { name, email, password }
 *   GET  ?action=profile      (auth required)
 *   POST ?action=profile      { age, gender, weight_kg, height_cm, goal, activity_level, budget, allergies }
 *   POST ?action=generate     { days } (auth required)
 *   GET  ?action=plans        (auth required)
 *   GET  ?action=plan&id=N    (auth required)
 *   POST ?action=delete_plan  { plan_id } (auth required)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/claude.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$action = (isset($_GET['action']) ? $_GET['action'] : '');

// ── Helper ──────────────────────────────────────────────
function apiAuth() {
    if (!isLoggedIn()) {
        jsonResponse(array('error' => 'Unauthorized'), 401);
    }
    return $_SESSION['user_id'];
}

// ── Routes ──────────────────────────────────────────────
switch ($action) {

    // POST: register
    case 'register':
        $body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $name     = trim((isset($body['name']) ? $body['name'] : ''));
        $email    = trim((isset($body['email']) ? $body['email'] : ''));
        $password = (isset($body['password']) ? $body['password'] : '');

        if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
            jsonResponse(array('error' => 'Invalid input'), 422);
        }
        $db = getDB();
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute(array($email));
        if ($stmt->fetch()) jsonResponse(array('error' => 'Email already exists'), 409);

        $stmt = $db->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute(array($name, $email, hashPassword($password)));
        $user_id = $db->lastInsertId();
        $_SESSION['user_id'] = $user_id;
        jsonResponse(array('success' => true, 'user_id' => $user_id, 'name' => $name));
        break;

    // POST: login
    case 'login':
        $body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $email    = trim((isset($body['email']) ? $body['email'] : ''));
        $password = (isset($body['password']) ? $body['password'] : '');

        $db = getDB();
        $stmt = $db->prepare('SELECT id, name, password_hash FROM users WHERE email = ?');
        $stmt->execute(array($email));
        $user = $stmt->fetch();

        if (!$user || !verifyPassword($password, $user['password_hash'])) {
            jsonResponse(array('error' => 'Invalid credentials'), 401);
        }
        $_SESSION['user_id'] = $user['id'];
        jsonResponse(array('success' => true, 'user_id' => $user['id'], 'name' => $user['name']));
        break;

    // GET/POST: profile
    case 'profile':
        $user_id = apiAuth();
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $profile = getUserProfile($user_id);
            jsonResponse($profile ?: array());
        } else {
            $body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $db = getDB();
            $profile = getUserProfile($user_id);
            $fields = array(
                'age'            => (int)((isset($body['age']) ? $body['age'] : 0)),
                'gender'         => (isset($body['gender']) ? $body['gender'] : ''),
                'weight_kg'      => (float)((isset($body['weight_kg']) ? $body['weight_kg'] : 0)),
                'height_cm'      => (float)((isset($body['height_cm']) ? $body['height_cm'] : 0)),
                'goal'           => (isset($body['goal']) ? $body['goal'] : ''),
                'activity_level' => (isset($body['activity_level']) ? $body['activity_level'] : ''),
                'budget'         => (isset($body['budget']) ? $body['budget'] : ''),
                'allergies'      => (isset($body['allergies']) ? $body['allergies'] : ''),
            );
            if ($profile) {
                $stmt = $db->prepare(
                    'UPDATE user_profiles SET age=?,gender=?,weight_kg=?,height_cm=?,goal=?,activity_level=?,budget=?,allergies=? WHERE user_id=?'
                );
                $stmt->execute(array_merge(array_values($fields), array($user_id)));
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO user_profiles (user_id,age,gender,weight_kg,height_cm,goal,activity_level,budget,allergies) VALUES (?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute(array_merge(array($user_id), array_values($fields)));
            }
            jsonResponse(array('success' => true));
        }
        break;

    // POST: generate
    case 'generate':
        $user_id = apiAuth();
        $profile = getUserProfile($user_id);
        if (!$profile) jsonResponse(array('error' => 'Profile not found'), 404);

        $body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $days = (int)((isset($body['days']) ? $body['days'] : 5));
        if (!in_array($days, array(3,5,7))) $days = 5;
        $profile['days'] = $days;

        $plan = callClaude(buildDietPrompt($profile));
        if (isset($plan['error'])) jsonResponse(array('error' => $plan['error']), 500);

        $plan_id = savePlanToDB($user_id, $profile, $plan);
        $plan['plan_id'] = $plan_id;
        jsonResponse($plan);
        break;

    // GET: list plans
    case 'plans':
        $user_id = apiAuth();
        $db = getDB();
        $stmt = $db->prepare('SELECT id,title,days,target_calories,protein_g,carbs_g,fat_g,created_at FROM diet_plans WHERE user_id=? ORDER BY created_at DESC');
        $stmt->execute(array($user_id));
        jsonResponse($stmt->fetchAll());
        break;

    // GET: single plan
    case 'plan':
        $user_id = apiAuth();
        $plan_id = (int)((isset($_GET['id']) ? $_GET['id'] : 0));
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM diet_plans WHERE id=? AND user_id=?');
        $stmt->execute(array($plan_id, $user_id));
        $plan = $stmt->fetch();
        if (!$plan) jsonResponse(array('error' => 'Not found'), 404);

        $stmt = $db->prepare('SELECT * FROM plan_days WHERE plan_id=? ORDER BY day_number');
        $stmt->execute(array($plan_id));
        $plan_days = $stmt->fetchAll();
        foreach ($plan_days as &$d) {
            $stmt2 = $db->prepare('SELECT * FROM plan_meals WHERE day_id=?');
            $stmt2->execute(array($d['id']));
            $d['meals'] = $stmt2->fetchAll();
        }
        unset($d);
        $plan['days_detail'] = $plan_days;
        jsonResponse($plan);
        break;

    // POST: delete plan
    case 'delete_plan':
        $user_id = apiAuth();
        $body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $plan_id = (int)(isset($body['plan_id']) ? $body['plan_id'] : (isset($_GET['id']) ? $_GET['id'] : 0));
        $db = getDB();
        $stmt = $db->prepare('DELETE FROM diet_plans WHERE id=? AND user_id=?');
        $stmt->execute(array($plan_id, $user_id));
        jsonResponse(array('success' => true, 'deleted' => $stmt->rowCount()));
        break;

    default:
        jsonResponse(array('error' => 'Unknown action', 'available' => array('login','register','profile','generate','plans','plan','delete_plan')), 404);
}
