<?php
// PHP LOGIC FOR register_workshop.php
session_start();

// 1. הגנה: ודא שהמשתמש מחובר
if (!isset($_SESSION['id'])) {
    $_SESSION['registration_message'] = "יש להתחבר כדי להירשם לסדנה.";
    $_SESSION['registration_success'] = false;
    header("Location: login.php");
    exit();
}

// 2. פרטי התחברות למסד נתונים
$host = 'localhost';
$username = 'root'; 
$password = ''; 
$database = 'skillhub';

// 3. התחברות למסד הנתונים
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 4. איסוף נתונים
$userId     = $_SESSION['id'];
$workshopId = isset($_GET['workshop_id']) ? (int)$_GET['workshop_id'] : 0;
// ✅ נקודות שנוצלו מדף checkout
$pointsUsed = isset($_GET['points_used']) ? (int)$_GET['points_used'] : 0;

// 5. אימות מזהה סדנה
if ($workshopId <= 0) {
    $_SESSION['registration_message'] = "שגיאה: מזהה סדנה לא חוקי.";
    $_SESSION['registration_success'] = false;
    header("Location: index.php");
    exit();
}

// --- 6. בדיקות אימות (Validation Checks) ---

// 6א. בדיקה: האם הסדנה קיימת, מלאה, ואם המשתמש כבר רשום
$stmt_check = $conn->prepare("
    SELECT 
        w.maxParticipants, 
        (SELECT COUNT(user_id) FROM workshop_registrations WHERE workshop_id = ?) AS currentParticipants,
        (SELECT COUNT(*) FROM workshop_registrations WHERE workshop_id = ? AND user_id = ?) AS isAlreadyRegistered
    FROM 
        workshop w
    WHERE 
        w.wsnum = ?
");

if ($stmt_check) {
    $stmt_check->bind_param("iiii", $workshopId, $workshopId, $userId, $workshopId);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        $_SESSION['registration_message'] = "הסדנה לא נמצאה.";
        $_SESSION['registration_success'] = false;
        $stmt_check->close();
        header("Location: index.php");
        exit();
    }
    
    $data = $result_check->fetch_assoc();
    $maxParticipants     = $data['maxParticipants'];
    $currentParticipants = $data['currentParticipants'];
    $isAlreadyRegistered = $data['isAlreadyRegistered'];
    $stmt_check->close();

    // 6ב. בדיקה: האם המשתמש כבר רשום?
    if ($isAlreadyRegistered > 0) {
        $_SESSION['registration_message'] = "אתה כבר רשום לסדנה זו.";
        $_SESSION['registration_success'] = false;
        header("Location: index.php");
        exit();
    }
    
    // 6ג. בדיקה: האם הסדנה מלאה?
    if ($currentParticipants >= $maxParticipants) {
        $_SESSION['registration_message'] = "ההרשמה נכשלה: הסדנה מלאה (מקסימום משתתפים: {$maxParticipants}).";
        $_SESSION['registration_success'] = false;
        header("Location: index.php");
        exit();
    }

} else {
    $_SESSION['registration_message'] = "שגיאת מערכת בהכנת השאילתה. נסה שוב מאוחר יותר.";
    $_SESSION['registration_success'] = false;
    header("Location: index.php");
    exit();
}


// --- 7. ביצוע ההרשמה (Insert Registration) ---

$stmt_insert = $conn->prepare("INSERT INTO workshop_registrations (workshop_id, user_id) VALUES (?, ?)");

if ($stmt_insert) {
    $stmt_insert->bind_param("ii", $workshopId, $userId);
    
    if ($stmt_insert->execute()) {
        
        // --- תוספת לוגיקת נקודות ---
        $stmt_points = $conn->prepare("INSERT INTO points (user_id, point_num) VALUES (?, 10) ON DUPLICATE KEY UPDATE point_num = point_num + 10");
        if ($stmt_points) {
            $stmt_points->bind_param("i", $userId);
            $stmt_points->execute();
            $stmt_points->close();
        }

        // ✅ הורד נקודות שנוצלו לתשלום מדף checkout
        if ($pointsUsed > 0) {
            $stmt_deduct = $conn->prepare("UPDATE points SET point_num = GREATEST(0, point_num - ?) WHERE user_id = ?");
            if ($stmt_deduct) {
                $stmt_deduct->bind_param("ii", $pointsUsed, $userId);
                $stmt_deduct->execute();
                $stmt_deduct->close();
            }
        }

        $_SESSION['registration_message'] = "✅ נרשמת בהצלחה לסדנה! קיבלת 10 נקודות.";
        if ($pointsUsed > 0) {
            $_SESSION['registration_message'] .= " נוצלו {$pointsUsed} נקודות לתשלום.";
        }
        $_SESSION['registration_success'] = true;

    } else {
        $_SESSION['registration_message'] = "שגיאה בהרשמה: " . $stmt_insert->error;
        $_SESSION['registration_success'] = false;
    }
    $stmt_insert->close();
} else {
    $_SESSION['registration_message'] = "שגיאת מערכת בהכנת שאילתת ההרשמה.";
    $_SESSION['registration_success'] = false;
}

// 8. ניתוק והפניה חזרה לדף הבית
$conn->close();
header("Location: index.php");
exit();
?>