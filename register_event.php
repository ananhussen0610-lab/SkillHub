<?php
session_start();

// --- 1. הגדרות מסד נתונים ---
$host = 'localhost';
$username = 'root'; 
$password = ''; 
$database = 'skillhub';

$eventId    = isset($_GET['event_id'])    ? (int)$_GET['event_id']    : 0;
$pointsUsed = isset($_GET['points_used']) ? (int)$_GET['points_used'] : 0;

// --- 2. בדיקה: האם המשתמש מחובר? ---
if (!isset($_SESSION['id'])) {
    $_SESSION['registration_message'] = "יש להתחבר כדי להירשם לאירוע.";
    $_SESSION['registration_success'] = false;
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['id'];

if ($eventId <= 0) {
    $_SESSION['registration_message'] = "שגיאה: מזהה אירוע לא חוקי.";
    $_SESSION['registration_success'] = false;
    header("Location: index.php");
    exit();
}

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->begin_transaction();

try {
    // --- 3. בדיקה אם כבר רשום ---
    $stmt_check = $conn->prepare("SELECT 1 FROM event_registrations WHERE user_id = ? AND event_id = ?");
    $stmt_check->bind_param("ii", $userId, $eventId);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        throw new Exception("כבר נרשמת לאירוע זה.");
    }
    $stmt_check->close();

    // --- 4. בדיקת יתרת נקודות (אבטחה) ---
    if ($pointsUsed > 0) {
        $stmt_p = $conn->prepare("SELECT point_num FROM points WHERE user_id = ? FOR UPDATE");
        $stmt_p->bind_param("i", $userId);
        $stmt_p->execute();
        $res_p = $stmt_p->get_result();
        $currentPoints = ($res_p->num_rows > 0) ? $res_p->fetch_assoc()['point_num'] : 0;
        
        if ($currentPoints < $pointsUsed) {
            throw new Exception("אין לך מספיק נקודות לניצול.");
        }
        $stmt_p->close();
    }

    // --- 5. בדיקת קיבולת אירוע ---
    $stmt_cap = $conn->prepare("SELECT maxParticipants FROM events WHERE id = ? FOR UPDATE");
    $stmt_cap->bind_param("i", $eventId);
    $stmt_cap->execute();
    $res_cap = $stmt_cap->get_result();
    if ($res_cap->num_rows === 0) throw new Exception("האירוע לא נמצא.");
    $maxParticipants = $res_cap->fetch_assoc()['maxParticipants'];
    $stmt_cap->close();

    $stmt_count = $conn->prepare("SELECT COUNT(*) as cnt FROM event_registrations WHERE event_id = ?");
    $stmt_count->bind_param("i", $eventId);
    $stmt_count->execute();
    $currentParticipants = $stmt_count->get_result()->fetch_assoc()['cnt'];
    $stmt_count->close();

    if ($currentParticipants >= $maxParticipants) {
        throw new Exception("האירוע מלא.");
    }

    // --- 6. ביצוע הרישום ---
    $stmt_ins = $conn->prepare("INSERT INTO event_registrations (user_id, event_id) VALUES (?, ?)");
    $stmt_ins->bind_param("ii", $userId, $eventId);
    if (!$stmt_ins->execute()) throw new Exception("שגיאה ברישום.");
    $stmt_ins->close();

    // --- 7. עדכון נקודות (חיסור ניצול + הוספת בונוס רישום) ---
    // קודם מחסירים את מה שנוצל
    if ($pointsUsed > 0) {
        $stmt_deduct = $conn->prepare("UPDATE points SET point_num = point_num - ? WHERE user_id = ?");
        $stmt_deduct->bind_param("ii", $pointsUsed, $userId);
        $stmt_deduct->execute();
        $stmt_deduct->close();
    }

    // הוספת 10 נקודות בונוס על עצם ההרשמה
    $stmt_bonus = $conn->prepare("INSERT INTO points (user_id, point_num) VALUES (?, 10) ON DUPLICATE KEY UPDATE point_num = point_num + 10");
    $stmt_bonus->bind_param("i", $userId);
    $stmt_bonus->execute();
    $stmt_bonus->close();

    $conn->commit();
    $_SESSION['registration_message'] = "✅ נרשמת בהצלחה! קיבלת 10 נקודות בונוס.";
    if ($pointsUsed > 0) $_SESSION['registration_message'] .= " (נוצלו $pointsUsed נקודות הנחה)";
    $_SESSION['registration_success'] = true;

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['registration_message'] = $e->getMessage();
    $_SESSION['registration_success'] = false;
}

$conn->close();
header("Location: index.php");
exit();