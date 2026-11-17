<?php
session_start();
date_default_timezone_set('Asia/Jerusalem');

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'skillhub';

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

require 'src/PHPMailer.php';
require 'src/SMTP.php';
require 'src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = "";
$messageType = "info";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"]);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "כתובת הדוא״ל אינה תקינה.";
        $messageType = "error";
    } else {

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");

        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {

                $token   = bin2hex(random_bytes(32));
                $expires = date("Y-m-d H:i:s", strtotime("+30 minutes"));

                $update = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");

                if ($update) {
                    $update->bind_param("sss", $token, $expires, $email);
                    $update->execute();
                    $update->close();

                    $resetLink = "http://localhost/SkillHub/reset_password.php?token=" . $token;

                    $mail = new PHPMailer(true);

                    try {
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'mohamadabosalh7@gmail.com';
                        $mail->Password   = 'bgje ulzl yyxm mgmd';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;
                        $mail->CharSet    = 'UTF-8';

                        // ✅ תיקון שגיאת SSL certificate
                        $mail->SMTPOptions = [
                            'ssl' => [
                                'verify_peer'       => false,
                                'verify_peer_name'  => false,
                                'allow_self_signed' => true,
                            ]
                        ];

                        $mail->setFrom('mohamadabosalh7@gmail.com', 'SkillHub');
                        $mail->addAddress($email);
                        $mail->isHTML(true);
                        $mail->Subject = 'איפוס סיסמה - SkillHub';
                        $mail->Body    = "
                            <div style='font-family:Arial;direction:rtl;text-align:right;padding:20px;'>
                                <h2 style='color:#1e2d4a;'>שחזור סיסמה — SkillHub</h2>
                                <p>שלום,</p>
                                <p>קיבלנו בקשה לאיפוס הסיסמה שלך.</p>
                                <p>לחץ על הכפתור הבא כדי לאפס את הסיסמה:</p>
                                <a href='$resetLink'
                                   style='background:#1a1a1a;color:#fff;padding:12px 22px;
                                          text-decoration:none;border-radius:8px;
                                          display:inline-block;font-weight:bold;margin:16px 0;'>
                                    איפוס סיסמה
                                </a>
                                <p style='margin-top:20px;color:#666;'>הקישור תקף ל-30 דקות בלבד.</p>
                                <p style='color:#666;'>אם לא ביקשת איפוס סיסמה, אפשר להתעלם מהודעה זו.</p>
                            </div>
                        ";

                        $mail->send();
                        $message     = "אם הדוא״ל נמצא במערכת, נשלח אליו קישור לשחזור הסיסמה.";
                        $messageType = "success";

                    } catch (Exception $e) {
                        $message     = "שגיאה בשליחת המייל: " . $mail->ErrorInfo;
                        $messageType = "error";
                    }

                } else {
                    $message     = "שגיאת מערכת בהכנת שאילתת העדכון.";
                    $messageType = "error";
                }

            } else {
                // אבטחה — אותה הודעה גם אם מייל לא קיים
                $message     = "אם הדוא״ל נמצא במערכת, נשלח אליו קישור לשחזור הסיסמה.";
                $messageType = "success";
            }

            $stmt->close();

        } else {
            $message     = "שגיאת מערכת בהכנת השאילתה.";
            $messageType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>שחזור סיסמה — SkillHub</title>
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
    --shadow:0 4px 16px rgba(0,0,0,.07),0 1px 3px rgba(0,0,0,.05);
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

/* ── CARD ── */
.card{
    position:relative;width:100%;max-width:440px;
    background:var(--surface);border:1px solid var(--border);
    border-radius:24px;box-shadow:var(--shadow-lg);
    overflow:hidden;
}
.card-top-bar{height:5px;background:linear-gradient(90deg,#1a1a1a,#333333);}
.card-inner{padding:36px 34px 30px;}

/* ── LOGO ── */
.card-logo{text-align:center;margin-bottom:22px;}
.logo-text{
    font-family:'Frank Ruhl Libre',serif;font-size:2rem;font-weight:900;
    color:var(--navy);text-decoration:none;display:inline-flex;align-items:center;gap:9px;
}
.logo-dot{
    width:10px;height:10px;border-radius:50%;
    background:linear-gradient(135deg,#1a1a1a,#333333);
    box-shadow:0 0 0 4px rgba(0,0,0,.1);
}

/* ── ICON BADGE ── */
.icon-badge{
    width:62px;height:62px;border-radius:18px;margin:0 auto 16px;
    display:flex;align-items:center;justify-content:center;
    font-size:1.8rem;background:linear-gradient(135deg,#1a1a1a,#333333);
    box-shadow:var(--shadow-md);
}

/* ── TITLES ── */
.card-title{
    font-family:'Frank Ruhl Libre',serif;font-size:1.9rem;font-weight:900;
    color:var(--navy);text-align:center;margin-bottom:7px;
}
.card-subtitle{color:var(--text3);text-align:center;line-height:1.7;margin-bottom:22px;font-size:.93rem;}

/* ── INFO BOX ── */
.info-box{
    display:flex;align-items:flex-start;gap:9px;
    padding:12px 15px;border-radius:var(--radius-sm);
    margin-bottom:20px;font-size:.87rem;font-weight:700;line-height:1.6;
    background:var(--surface2);border:1px solid var(--border);color:var(--text3);
}
.info-box.success{background:var(--green-bg);border-color:var(--green-border);color:var(--green);}
.info-box.error{background:var(--red-bg);border-color:#fecaca;color:var(--red);}

/* ── FORM ── */
.form-group{margin-bottom:18px;}
.form-label{display:block;margin-bottom:7px;color:var(--text);font-size:.86rem;font-weight:700;}
.input-wrap{position:relative;}
.input-icon{position:absolute;right:14px;top:50%;transform:translateY(-50%);color:var(--text4);font-size:.95rem;}
.form-input{
    width:100%;padding:12px 42px 12px 14px;border-radius:var(--radius-sm);
    border:1.5px solid var(--border2);background:#fff;color:var(--text);
    font-family:'Heebo',sans-serif;font-size:.95rem;outline:none;transition:.2s ease;
}
.form-input::placeholder{color:var(--text4);}
.form-input:focus{border-color:#1a1a1a;box-shadow:0 0 0 3px rgba(0,0,0,.08);}

/* ── BUTTON ── */
.btn-submit{
    width:100%;margin-top:4px;margin-bottom:18px;
    padding:12px 20px;border:none;border-radius:var(--radius-sm);
    font-family:'Heebo',sans-serif;font-size:1rem;font-weight:800;
    color:#fff;background:linear-gradient(90deg,#1a1a1a,#333333);
    cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.2);transition:.2s ease;
}
.btn-submit:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,0,0,.28);}

/* ── LINKS ── */
.card-links{
    display:flex;flex-direction:column;gap:8px;
    padding-top:16px;border-top:1px solid var(--border);
}
.card-link{
    display:flex;align-items:center;justify-content:center;gap:6px;
    padding:10px 14px;border-radius:var(--radius-sm);text-decoration:none;
    font-size:.88rem;font-weight:700;
    color:var(--text2);background:var(--surface2);border:1px solid var(--border);
    transition:.2s ease;
}
.card-link:hover{background:var(--border);color:var(--navy);}

/* ── BACK HOME ── */
.back-home{margin-top:16px;text-align:center;}
.back-home a{color:var(--text3);text-decoration:none;font-weight:700;font-size:.88rem;transition:.2s ease;}
.back-home a:hover{color:var(--navy);}

@media(max-width:480px){
    .card-inner{padding:28px 22px 24px;}
    .card-title{font-size:1.6rem;}
}

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

        <div class="icon-badge">🔑</div>

        <div class="card-title">שחזור סיסמה</div>
        <div class="card-subtitle">
            הזן את כתובת הדוא"ל שלך ונשלח<br>קישור לאיפוס הסיסמה
        </div>

        <?php if (!empty($message)): ?>
            <div class="info-box <?= htmlspecialchars($messageType) ?>">
                <span><?= htmlspecialchars($message) ?></span>
            </div>
        <?php else: ?>
            <div class="info-box">
                <span>✉️</span>
                <span>אם הדוא"ל נמצא במערכת, נשלח אליו קישור לשחזור הסיסמה בתוך מספר דקות.</span>
            </div>
        <?php endif; ?>

        <form method="POST" action="forgotPassword.php">
            <div class="form-group">
                <label class="form-label">דוא"ל רשום</label>
                <div class="input-wrap">
                    <input type="email" name="email" id="email" class="form-input"
                           placeholder="israel@skillhub.co.il" required>
                    <span class="input-icon">✉</span>
                </div>
            </div>

            <button type="submit" class="btn-submit">📨 שלח קישור לשחזור</button>

            <div class="card-links">
                <a href="login.php" class="card-link">← חזרה לדף ההתחברות</a>
            </div>
        </form>

    </div>
</div>

<div class="back-home">
    <a href="index.php">← חזרה לדף הבית</a>
</div>

</body>
</html>
<?php $conn->close(); ?>