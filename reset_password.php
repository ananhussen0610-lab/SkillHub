<?php
session_start();
date_default_timezone_set('Asia/Jerusalem');

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'skillhub';

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

if (!isset($_GET['token']) || empty(trim($_GET['token']))) {
    die("קישור לא תקין.");
}

$token = trim($_GET['token']);

// ✅ בדיקה: token קיים + לא פג תוקף
$stmt = $conn->prepare("
    SELECT id FROM users
    WHERE reset_token = ?
      AND reset_expires IS NOT NULL
      AND reset_expires > NOW()
");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // debug: בדוק אם token קיים בכלל (בלי תנאי תאריך)
    $dbg = $conn->prepare("SELECT id, reset_token, reset_expires FROM users WHERE reset_token = ?");
    $dbg->bind_param("s", $token);
    $dbg->execute();
    $dbgRes = $dbg->get_result();
    if ($dbgRes->num_rows === 0) {
        $errMsg = "הקישור אינו תקין — לא נמצא token במסד הנתונים.";
    } else {
        $row = $dbgRes->fetch_assoc();
        $errMsg = "הקישור פג תוקף. תוקף ב: " . $row['reset_expires'] . " | עכשיו: " . date('Y-m-d H:i:s');
    }
    $dbg->close();
    ?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<title>קישור לא תקין — SkillHub</title>
<link href="https://fonts.googleapis.com/css2?family=Frank+Ruhl+Libre:wght@900&family=Heebo:wght@400;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Heebo',sans-serif;direction:rtl;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f4f6fb;padding:24px;}
.card{background:#fff;border-radius:16px;border:1px solid #e2e8f0;box-shadow:0 8px 28px rgba(0,0,0,.09);padding:36px 32px;max-width:440px;width:100%;text-align:center;}
.icon{font-size:2.5rem;margin-bottom:14px;}
h2{font-family:'Frank Ruhl Libre',serif;font-size:1.7rem;color:#1e2d4a;margin-bottom:10px;}
p{color:#64748b;line-height:1.7;margin-bottom:8px;}
.err{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:10px 14px;border-radius:8px;font-size:.82rem;margin:14px 0;text-align:right;}
.btn{display:inline-block;margin-top:18px;padding:10px 22px;border-radius:10px;text-decoration:none;font-weight:700;color:#fff;background:#1a1a1a;}

/* ══ TYPOGRAPHY SYSTEM ══ */
html{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;text-rendering:optimizeLegibility;}

/* Headings */
h1,h2,h3,h4,h5,h6{font-family:'Frank Ruhl Libre',serif;font-weight:900;line-height:1.15;letter-spacing:-.3px;color:var(--navy,#1e2d4a);}

/* Body text */
p,li,td,th,label,span,div{font-family:'Heebo',sans-serif;line-height:1.75;}

/* Form labels - sharper */
.form-label,.section-label{font-size:.78rem;font-weight:800;letter-spacing:.6px;text-transform:uppercase;}

/* Table */
table{font-size:.88rem;}
th{font-size:.72rem;font-weight:800;letter-spacing:.5px;text-transform:uppercase;}
td{line-height:1.5;}

/* Cards titles */
.card-title,.mission-title,.value-title,.team-name,.heading-title{letter-spacing:-.2px;}

/* Buttons - tighter tracking */
.btn,.nav-link,.btn-save,.btn-pay,.btn-free,.btn-back,.btn-submit,.btn-register{letter-spacing:.2px;font-weight:800;}

/* Nav links */
.nav-link{font-size:.85rem;font-weight:700;letter-spacing:.1px;}

/* Section subtitles */
.card-subtitle,.section-subtitle,.hero-sub,.heading-sub{font-size:.88rem;line-height:1.75;font-weight:400;}

/* Meta labels */
.meta-label,.info-label{font-size:.68rem;font-weight:800;letter-spacing:.7px;text-transform:uppercase;opacity:.7;}
.meta-value,.info-value{font-size:.92rem;font-weight:700;letter-spacing:0;}

/* Breadcrumb */
.breadcrumb{font-size:.8rem;font-weight:500;letter-spacing:.1px;}

/* Tags & badges */
.event-tag,.ws-tag,.ws-type-pill,.badge-ended,.pers-badge,.team-tag{font-size:.7rem;font-weight:800;letter-spacing:.3px;}

/* Price */
.meta-value.price,.price-val{font-size:1rem;font-weight:900;letter-spacing:-.2px;}

/* Empty states */
.empty-text,.empty-state p{font-size:.95rem;font-weight:500;line-height:1.8;}

/* Section dividers */
.section-label{font-size:.72rem;letter-spacing:1px;}

</style>
</head>
<body>
<div class="card">
    <div class="icon">⚠️</div>
    <h2>הקישור אינו תקין</h2>
    <p>ייתכן שהקישור פג תוקף או כבר נעשה בו שימוש.</p>
    <div class="err"><?= htmlspecialchars($errMsg) ?></div>
    <p>בקש קישור חדש לאיפוס סיסמה.</p>
    <a href="forgotPassword.php" class="btn">← בקש קישור חדש</a>
</div>
</body>
</html>
<?php
    $conn->close();
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $newPassword     = trim($_POST['password']);
    $confirmPassword = trim($_POST['confirm_password']);

    if (strlen($newPassword) < 6) {
        $message = "הסיסמה חייבת להכיל לפחות 6 תווים.";
        $messageType = "error";
    } elseif ($newPassword !== $confirmPassword) {
        $message = "הסיסמאות אינן תואמות.";
        $messageType = "error";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $update = $conn->prepare("
            UPDATE users
            SET password = ?,
                reset_token = NULL,
                reset_expires = NULL
            WHERE id = ?
        ");
        $update->bind_param("si", $hashedPassword, $user['id']);

        if ($update->execute()) {
            $_SESSION['login_message'] = "הסיסמה עודכנה בהצלחה! ניתן להתחבר עכשיו.";
            header("Location: login.php");
            exit();
        } else {
            $message = "שגיאה בעדכון הסיסמה. נסה שוב.";
            $messageType = "error";
        }
        $update->close();
    }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>איפוס סיסמה — SkillHub</title>
<link rel="icon" type="image/png" href="images/skillhub2.jpeg">
<link href="https://fonts.googleapis.com/css2?family=Frank+Ruhl+Libre:wght@300;400;500;700;900&family=Heebo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

:root{
    --navy:#1e2d4a;
    --bg:#f4f6fb;
    --surface:#ffffff;
    --surface2:#f8fafc;
    --border:#e2e8f0;
    --border2:#cbd5e1;
    --text:#0f172a;
    --text2:#334155;
    --text3:#64748b;
    --text4:#94a3b8;
    --green:#16a34a;
    --green-bg:#f0fdf4;
    --green-border:#86efac;
    --red:#dc2626;
    --red-bg:#fef2f2;
    --shadow-md:0 8px 28px rgba(0,0,0,.09);
    --shadow-lg:0 20px 50px rgba(0,0,0,.11);
    --radius:16px;
    --radius-sm:10px;
}

html{scroll-behavior:smooth}
body{
    font-family:'Heebo',sans-serif;direction:rtl;min-height:100vh;
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    padding:24px;background:var(--bg);color:var(--text);
}

.card{
    width:100%;max-width:430px;
    background:var(--surface);border:1px solid var(--border);
    border-radius:24px;box-shadow:var(--shadow-lg);overflow:hidden;
}
.card-top-bar{height:5px;background:linear-gradient(90deg,#1a1a1a,#333333);}
.card-inner{padding:36px 32px 30px;}

.card-logo{text-align:center;margin-bottom:20px;}
.logo-text{
    font-family:'Frank Ruhl Libre',serif;font-size:1.9rem;font-weight:900;
    color:var(--navy);text-decoration:none;display:inline-flex;align-items:center;gap:9px;
}
.logo-dot{
    width:10px;height:10px;border-radius:50%;
    background:#1a1a1a;box-shadow:0 0 0 4px rgba(0,0,0,.1);
}

.icon-badge{
    width:60px;height:60px;border-radius:18px;margin:0 auto 16px;
    display:flex;align-items:center;justify-content:center;
    font-size:1.7rem;background:linear-gradient(135deg,#1a1a1a,#333333);
    box-shadow:var(--shadow-md);
}

.card-title{
    font-family:'Frank Ruhl Libre',serif;font-size:1.8rem;font-weight:900;
    color:var(--navy);text-align:center;margin-bottom:6px;
}
.card-subtitle{color:var(--text3);text-align:center;line-height:1.7;margin-bottom:22px;font-size:.9rem;}

.alert{
    padding:12px 15px;border-radius:var(--radius-sm);
    margin-bottom:18px;font-size:.87rem;font-weight:700;border:1px solid;
}
.alert.error{background:var(--red-bg);border-color:#fecaca;color:var(--red);}
.alert.success{background:var(--green-bg);border-color:var(--green-border);color:var(--green);}

.form-group{margin-bottom:16px;}
.form-label{display:block;margin-bottom:6px;color:var(--text);font-size:.85rem;font-weight:700;}
.form-input{
    width:100%;padding:11px 14px;border-radius:var(--radius-sm);
    border:1.5px solid var(--border2);background:#fff;color:var(--text);
    font-family:'Heebo',sans-serif;font-size:.95rem;outline:none;transition:.2s ease;
}
.form-input::placeholder{color:var(--text4);}
.form-input:focus{border-color:#1a1a1a;box-shadow:0 0 0 3px rgba(0,0,0,.08);}

.btn-submit{
    width:100%;margin-top:6px;padding:12px 20px;
    border:none;border-radius:var(--radius-sm);
    font-family:'Heebo',sans-serif;font-size:1rem;font-weight:800;
    color:#fff;background:linear-gradient(90deg,#1a1a1a,#333333);
    cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.2);transition:.2s ease;
}
.btn-submit:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,0,0,.28);}

.back-home{margin-top:14px;text-align:center;}
.back-home a{color:var(--text3);text-decoration:none;font-weight:700;font-size:.86rem;transition:.2s;}
.back-home a:hover{color:var(--navy);}

@media(max-width:480px){
    .card-inner{padding:28px 20px 24px;}
    .card-title{font-size:1.5rem;}
}

/* ══ SIZE & SPACING FIXES ══ */

/* Hero titles - clamp for responsive */
.hero-title{font-size:clamp(2.4rem,5vw,3.8rem)!important;}
.page-hero-title,.heading-title{font-size:clamp(1.8rem,4vw,2.8rem)!important;}
.card-title,.section-title{font-size:clamp(1.3rem,3vw,1.9rem)!important;}
.checkout-title{font-size:clamp(1.4rem,3vw,1.8rem)!important;}

/* Body font size consistent */
body{font-size:15px;}

/* Tighten up cards */
.card-header{padding:20px 24px!important;}
.card-body{padding:18px 24px!important;}

/* Table cells uniform */
th,td{padding:11px 14px!important;}

/* Nav link size uniform */
.nav-link{font-size:.85rem!important;padding:7px 12px!important;}
.nav-logo{font-size:1.55rem!important;}

/* Form inputs */
.form-input,.search-input,.date-filter-input{font-size:.9rem!important;}
.form-label{font-size:.76rem!important;margin-bottom:5px!important;}

/* Section labels */
.section-title{font-size:1.75rem!important;}
.section-subtitle{font-size:.88rem!important;}

/* Breadcrumb */
.breadcrumb{font-size:.8rem!important;}

/* Meta items */
.meta-label{font-size:.67rem!important;}
.meta-value{font-size:.9rem!important;}

/* Buttons consistent */
.btn,.btn-register,.btn-details,.btn-back{font-size:.84rem!important;padding:9px 16px!important;}
.btn-pay,.btn-free,.btn-submit{font-size:.96rem!important;}

/* Pers cards */
.pers-name{font-size:1rem!important;font-weight:800!important;}
.pers-detail{font-size:.8rem!important;}
.pers-type{font-size:.7rem!important;}

/* Stat cards */
.stat-number{font-size:2.4rem!important;}
.stat-label{font-size:.83rem!important;}

/* Contact items */
.contact-value{font-size:.97rem!important;}
.contact-desc{font-size:.8rem!important;}
.contact-label{font-size:.68rem!important;}

/* Footer */
.site-footer{font-size:.82rem!important;}

</style>
</head>
<body>

<div class="card">
    <div class="card-top-bar"></div>
    <div class="card-inner">

        <div class="card-logo">
            <a href="index.php" class="logo-text">
                <span class="logo-dot"></span>SkillHub
            </a>
        </div>

        <div class="icon-badge">🔒</div>
        <div class="card-title">איפוס סיסמה</div>
        <div class="card-subtitle">הזן סיסמה חדשה לחשבונך</div>

        <?php if (!empty($message)): ?>
            <div class="alert <?= htmlspecialchars($messageType) ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">סיסמה חדשה</label>
                <input type="password" name="password" class="form-input"
                       placeholder="לפחות 6 תווים" required minlength="6">
            </div>
            <div class="form-group">
                <label class="form-label">אימות סיסמה</label>
                <input type="password" name="confirm_password" class="form-input"
                       placeholder="הזן שוב את הסיסמה" required>
            </div>
            <button type="submit" class="btn-submit">💾 שמור סיסמה חדשה</button>
        </form>

    </div>
</div>

<div class="back-home">
    <a href="forgotPassword.php">← בקש קישור חדש</a>
</div>

</body>
</html>
<?php $conn->close(); ?>