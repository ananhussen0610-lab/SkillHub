<?php
session_start();

$error_message = '';
$totalWorkshops = 0;
$connStats = new mysqli('localhost','root','','skillhub');
if (!$connStats->connect_error) {
    $rs = $connStats->query("SELECT COUNT(*) AS cnt FROM workshop");
    if ($rs) $totalWorkshops = $rs->fetch_assoc()['cnt'];
    $connStats->close();
}

if (isset($_POST['submit'])) {
    $host        = 'localhost';
    $db_username = 'root';
    $db_password = '';
    $database    = 'skillhub';

    $conn = new mysqli($host, $db_username, $db_password, $database);

    if ($conn->connect_error) {
        error_log("DB Connection failed: " . $conn->connect_error);
        $error_message = "שגיאה: כשל בהתחברות למסד הנתונים.";
    } else {
        $email          = $_POST['email'];
        $plain_password = $_POST['password'];

        $stmt = $conn->prepare("SELECT id, name, email, password, isAdmin FROM users WHERE email = ? LIMIT 1");

        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $is_authenticated = false;

                if (password_verify($plain_password, $user['password'])) {
                    $is_authenticated = true;
                } elseif ($plain_password === $user['password']) {
                    // fallback לסיסמאות ישנות (plain text) — יש לשנות סיסמה אחרי כניסה
                    $is_authenticated = true;
                    // המרה אוטומטית ל-hash
                    $newHash = password_hash($plain_password, PASSWORD_DEFAULT);
                    $conn->query("UPDATE users SET password='$newHash' WHERE id={$user['id']}");
                }

                if ($is_authenticated) {
                    $_SESSION['id']    = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['name']  = $user['name'];
                    $_SESSION['role']  = (int)$user['isAdmin'];

                    // ✅ עדכון זמן כניסה אחרונה
                    $upd = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    if ($upd) { $upd->bind_param("i", $user['id']); $upd->execute(); $upd->close(); }

                    switch ((int)$user['isAdmin']) {
                        case 0: header("Location: profile.php"); exit();
                        case 1: header("Location: admin.php"); exit();
                        case 2: header("Location: lec.php"); exit();
                        default: $error_message = "שגיאה: קוד תפקיד לא חוקי."; break;
                    }
                } else {
                    $error_message = "שם משתמש או סיסמה לא נכונים";
                }
            } else {
                $error_message = "שם משתמש או סיסמה לא נכונים";
            }

            $stmt->close();
        } else {
            error_log("Prepare failed: (" . $conn->errno . ") " . $conn->error);
            $error_message = "שגיאה פנימית. אנא פנה למנהל המערכת.";
        }

        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>כניסה — SkillHub</title>
<link rel="icon" type="image/png" href="images/skillhub2.jpeg">
<link href="https://fonts.googleapis.com/css2?family=Frank+Ruhl+Libre:wght@300;400;500;700;900&family=Heebo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

:root {
    --navy: #1e2d4a; --dark-navy: #101827; --navy-soft: #16213a;
    --gold: #1a1a1a; --gold-light: #333333;
    --cream: #fdf8f0; --muted: rgba(253,248,240,.65);
    --border: rgba(253,248,240,.14); --glass: rgba(253,248,240,.06);
    --danger: #ef4444;
}

body { font-family: 'Heebo', sans-serif; min-height: 100vh; direction: rtl; background: var(--navy-soft); color: var(--cream); }

.login-page { min-height: 100vh; display: flex; overflow: hidden; }

.brand-side {
    width: 50%; position: relative; padding: 48px;
    background:
        linear-gradient(135deg, rgba(30,45,74,.95), rgba(42,63,95,.95), rgba(16,24,39,.98)),
        url("https://images.unsplash.com/photo-1522202176988-66273c2fd55f?w=1200&auto=format&fit=crop") center/cover;
    display: flex; flex-direction: column; justify-content: space-between; overflow: hidden;
}

.brand-side::before {
    content: ""; position: absolute; inset: 0;
    background:
        radial-gradient(circle at 20% 20%, rgba(0,0,0,.20), transparent 30%),
        radial-gradient(circle at 80% 80%, rgba(0,0,0,.14), transparent 35%);
    filter: blur(10px);
}

.brand-content, .brand-feature { position: relative; z-index: 2; }

.logo { display: inline-flex; align-items: center; gap: 12px; text-decoration: none; margin-bottom: 70px; }

.logo-icon {
    width: 50px; height: 50px; border-radius: 15px;
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    color: var(--navy); display: flex; align-items: center; justify-content: center;
    font-size: 1.6rem; box-shadow: 0 18px 40px rgba(0,0,0,.20);
}

.logo-text { font-family: 'Frank Ruhl Libre', serif; font-size: 2.1rem; font-weight: 900; color: var(--cream); }

.brand-title { font-family: 'Frank Ruhl Libre', serif; font-size: clamp(2.4rem, 5vw, 4.6rem); line-height: 1.08; max-width: 580px; margin-bottom: 22px; }

.brand-desc { color: var(--muted); font-size: 1.08rem; max-width: 500px; line-height: 1.8; }

.brand-feature {
    max-width: 520px; padding: 18px; border-radius: 22px;
    background: rgba(253,248,240,.07); border: 1px solid var(--border);
    backdrop-filter: blur(14px); display: flex; align-items: center; gap: 16px;
    box-shadow: 0 24px 60px rgba(0,0,0,.25);
}

.feature-icon {
    width: 54px; height: 54px; flex-shrink: 0; border-radius: 16px;
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    color: var(--navy); display: flex; align-items: center; justify-content: center; font-size: 1.45rem;
}

.feature-title { font-weight: 800; color: var(--cream); margin-bottom: 4px; }
.feature-text { color: var(--muted); font-size: .92rem; }

.form-side {
    width: 50%;
    background:
        radial-gradient(circle at top right, rgba(0,0,0,.07), transparent 30%),
        linear-gradient(180deg, #16213a, #101827);
    display: flex; align-items: center; justify-content: center; padding: 40px;
}

.form-wrap { width: 100%; max-width: 440px; animation: fadeIn .55s ease both; }

@keyframes fadeIn { from { opacity: 0; transform: translateY(22px); } to { opacity: 1; transform: translateY(0); } }

.mobile-logo { display: none; text-align: center; margin-bottom: 28px; }

.form-title { font-family: 'Frank Ruhl Libre', serif; font-size: 2.35rem; margin-bottom: 8px; }
.form-subtitle { color: var(--muted); margin-bottom: 30px; }

.error-box {
    background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.35);
    color: #fecaca; padding: 13px 15px; border-radius: 14px;
    margin-bottom: 22px; font-weight: 600; display: flex; align-items: center; gap: 10px;
}

.form-group { margin-bottom: 20px; }
.form-label { display: block; margin-bottom: 9px; color: var(--cream); font-weight: 600; }
.input-wrap { position: relative; }

.form-input {
    width: 100%; padding: 14px 46px 14px 48px; border-radius: 15px;
    border: 1px solid var(--border); background: var(--glass);
    color: var(--cream); font-size: 1rem; font-family: 'Heebo', sans-serif;
    outline: none; transition: .25s ease;
}

.form-input::placeholder { color: rgba(253,248,240,.38); }
.form-input:focus { border-color: var(--gold); box-shadow: 0 0 0 4px rgba(0,0,0,.08); background: rgba(253,248,240,.09); }

.input-icon { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: rgba(253,248,240,.45); }

.toggle-password {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    background: transparent; border: 0; color: rgba(253,248,240,.55);
    cursor: pointer; font-size: 1.1rem; transition: .2s;
}
.toggle-password:hover { color: var(--gold); }

.form-options { display: flex; justify-content: space-between; align-items: center; gap: 14px; margin: 8px 0 24px; }

.remember { display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: .92rem; cursor: pointer; }
.remember input { accent-color: var(--gold); }

.forgot-link { color: rgba(253,248,240,.6); text-decoration: none; font-size: .92rem; transition: .2s; }
.forgot-link:hover { color: #fff; }

.btn-submit {
    width: 100%; border: 0; border-radius: 15px; padding: 14px 22px;
    font-family: 'Heebo', sans-serif; font-size: 1.05rem; font-weight: 800;
    color: #fff; background: linear-gradient(90deg, #1a1a1a, #333333);
    cursor: pointer; box-shadow: 0 18px 35px rgba(0,0,0,.25); transition: .25s ease;
}
.btn-submit:hover { transform: translateY(-2px); box-shadow: 0 24px 45px rgba(0,0,0,.35); }

.register-text { margin-top: 28px; text-align: center; color: var(--muted); }
.register-text a { color: rgba(253,248,240,.8); text-decoration: none; font-weight: 700; }
.register-text a:hover { color: #fff; }

.terms { margin-top: 28px; padding-top: 22px; border-top: 1px solid var(--border); color: rgba(253,248,240,.55); font-size: .82rem; text-align: center; line-height: 1.6; }

.back-home { margin-top: 20px; text-align: center; }
.back-home a { color: rgba(253,248,240,.6); text-decoration: none; font-size: .9rem; }
.back-home a:hover { color: #fff; }

@media (max-width: 950px) {
    .login-page { display: block; }
    .brand-side { display: none; }
    .form-side { width: 100%; min-height: 100vh; padding: 28px 20px; }
    .mobile-logo { display: block; }
    .mobile-logo .logo { margin-bottom: 0; justify-content: center; }
}

@media (max-width: 480px) {
    .form-title { font-size: 2rem; }
    .form-options { flex-direction: column; align-items: flex-start; }
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
<div class="login-page">

    <section class="brand-side">
        <div class="brand-content">
            <a href="index.php" class="logo">
                <span class="logo-icon">📚</span>
                <span class="logo-text">SkillHub</span>
            </a>
            <h1 class="brand-title">ברוך הבא למסע הלמידה שלך</h1>
            <p class="brand-desc">המשך להתקדם עם סדנאות מקצועיות, מרצים מנוסים וחוויית למידה מודרנית שמתאימה בדיוק לך.</p>
        </div>
        <div class="brand-feature">
            <div class="feature-icon">🎓</div>
            <div>
                <div class="feature-title"><?= $totalWorkshops ?>+ סדנאות מקצועיות</div>
                <div class="feature-text">קורסים וסדנאות בהובלת מומחים בתחומים שונים</div>
            </div>
        </div>
    </section>

    <section class="form-side">
        <div class="form-wrap">

            <div class="mobile-logo">
                <a href="index.php" class="logo">
                    <span class="logo-icon">📚</span>
                    <span class="logo-text">SkillHub</span>
                </a>
            </div>

            <h2 class="form-title">כניסה למערכת</h2>
            <p class="form-subtitle">הזן את פרטי ההתחברות שלך כדי להמשיך</p>

            <?php if (!empty($error_message)): ?>
                <div class="error-box"><span>⚠</span><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <form action="" method="post">
                <div class="form-group">
                    <label class="form-label">דוא"ל</label>
                    <div class="input-wrap">
                        <input type="email" name="email" class="form-input" placeholder="israel@skillhub.co.il" required>
                        <span class="input-icon">✉</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">סיסמה</label>
                    <div class="input-wrap">
                        <input type="password" name="password" id="passwordInput" class="form-input" placeholder="הסיסמה שלך" required>
                        <span class="input-icon">🔒</span>
                        <button type="button" class="toggle-password" id="togglePassword">👁</button>
                    </div>
                </div>
                <div class="form-options">
                    <label class="remember"><input type="checkbox"><span>זכור אותי</span></label>
                    <a href="forgotPassword.php" class="forgot-link">שכחתי סיסמה?</a>
                </div>
                <button type="submit" name="submit" class="btn-submit">כניסה למערכת</button>
            </form>

            <p class="register-text">אין לך חשבון? <a href="signUp.php">הירשם עכשיו</a></p>
            <div class="terms">בהתחברות למערכת אתה מאשר את תנאי השימוש ומדיניות הפרטיות של SkillHub</div>
            <div class="back-home"><a href="index.php">← חזרה לדף הבית</a></div>
        </div>
    </section>

</div>

<script>
const togglePassword = document.getElementById('togglePassword');
const passwordInput  = document.getElementById('passwordInput');
togglePassword.addEventListener('click', function () {
    const isPassword = passwordInput.type === 'password';
    passwordInput.type = isPassword ? 'text' : 'password';
    togglePassword.textContent = isPassword ? '🙈' : '👁';
});
</script>
</body>
</html>