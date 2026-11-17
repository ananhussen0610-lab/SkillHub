<?php
ob_start();
session_start();

$error_message   = '';
$success_message = '';

// שליפת קטגוריות לטופס
$conn_types = new mysqli('localhost', 'root', '', 'skillhub');
$all_types = [];
if (!$conn_types->connect_error) {
    $rt = $conn_types->query("SELECT typeId, typeName FROM types ORDER BY typeName ASC");
    if ($rt) while ($row = $rt->fetch_assoc()) $all_types[] = $row;
    $conn_types->close();
}

if (isset($_POST['submit'])) {
    $conn = new mysqli('localhost', 'root', '', 'skillhub');

    if ($conn->connect_error) {
        $error_message = "שגיאה: כשל בהתחברות למסד הנתונים.";
    } else {
        $name             = trim($_POST['name']);
        $lastname         = trim($_POST['lastname']);
        $email            = trim($_POST['email']);
        $phone            = trim($_POST['phone']);
        $plain_password   = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $wantsLecturer    = isset($_POST['wants_lecturer']) ? 1 : 0;
        // ✅ תחומי עניין — מערך של typeId-ים
        $interestsRaw = $_POST['interests'] ?? [];
        $interests = implode(',', array_map('intval', $interestsRaw));

        $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        if ($stmt_check === false) {
            $error_message = "שגיאת הכנת שאילתה.";
        } else {
            $stmt_check->bind_param("s", $email);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows > 0) {
                $error_message = "כתובת הדוא\"ל הזו כבר רשומה במערכת.";
            }
            elseif (empty($phone) || !preg_match('/^0(5[0-9]|[2-4789])\d{7}$/', preg_replace('/[\s\-]/', '', $phone))) {
                $error_message = "מספר טלפון לא תקין. יש להזין מספר ישראלי כגון 0501234567.";
            }
            elseif ($plain_password !== $confirm_password) {
                $error_message = "הסיסמאות אינן תואמות.";
            }
            elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $plain_password)) {
                $error_message = "הסיסמה חלשה מדי. עליה להכיל 8 תווים, אות גדולה ומספר.";
            }
            else {
                $isAdmin_default = 0;
                // ✅ שמירת interests בעמודה החדשה
                $hashed_password  = password_hash($plain_password, PASSWORD_DEFAULT);
                $stmt_insert = $conn->prepare("INSERT INTO users (name, lastname, email, phone, password, isAdmin, pending_lecturer, interests) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt_insert === false) {
                    $error_message = "שגיאת הכנת שאילתת הוספה: " . $conn->error;
                } else {
                    $stmt_insert->bind_param("sssssii" . "s", $name, $lastname, $email, $phone, $hashed_password, $isAdmin_default, $wantsLecturer, $interests);
                    if ($stmt_insert->execute()) {
                        if ($wantsLecturer) {
                            $_SESSION['login_message'] = "הרישום בוצע בהצלחה! בקשתך להיות מרצה נשלחה למנהל לאישור.";
                        } else {
                            $_SESSION['login_message'] = "הרישום בוצע בהצלחה! ניתן להתחבר כעת.";
                        }
                        $stmt_insert->close();
                        $conn->close();
                        header("Location: login.php");
                        exit();
                    } else {
                        $error_message = "שגיאה ברישום המשתמש: " . $stmt_insert->error;
                    }
                    $stmt_insert->close();
                }
            }
            $stmt_check->close();
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
<title>הרשמה — SkillHub</title>
<link rel="icon" type="image/png" href="images/skillhub2.jpeg">
<link href="https://fonts.googleapis.com/css2?family=Frank+Ruhl+Libre:wght@300;400;500;700;900&family=Heebo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}

:root{
    --navy:#1e2d4a;--dark:#101827;--dark2:#16213a;--gold:#1a1a1a;--gold-light:#333333;
    --cream:#fdf8f0;--muted:rgba(253,248,240,.65);--border:rgba(253,248,240,.14);
    --glass:rgba(253,248,240,.06);--danger:#ef4444;--success:#22c55e;
}

body{
    font-family:'Heebo',sans-serif;direction:rtl;min-height:100vh;color:var(--cream);
    background:radial-gradient(circle at top right,rgba(0,0,0,.08),transparent 30%),linear-gradient(135deg,#101827,#16213a,#1e2d4a);
    display:flex;align-items:center;justify-content:center;padding:32px 20px;
}
body::before{content:"";position:fixed;inset:0;pointer-events:none;background-image:linear-gradient(rgba(253,248,240,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(253,248,240,.025) 1px,transparent 1px);background-size:34px 34px;}

.card{position:relative;width:100%;max-width:520px;padding:38px;border-radius:26px;background:var(--glass);border:1px solid var(--border);backdrop-filter:blur(18px);box-shadow:0 30px 90px rgba(0,0,0,.35);animation:cardIn .55s ease both;}
@keyframes cardIn{from{opacity:0;transform:translateY(24px);}to{opacity:1;transform:translateY(0);}}
.card::before{content:"";position:absolute;inset:0 0 auto 0;height:5px;background:linear-gradient(90deg,#1a1a1a,#333333);border-radius:26px 26px 0 0;}

.card-logo{text-align:center;margin-bottom:24px;}
.logo-text{font-family:'Frank Ruhl Libre',serif;font-size:2.1rem;font-weight:900;color:var(--cream);text-decoration:none;display:inline-flex;align-items:center;gap:10px;}
.logo-dot{width:12px;height:12px;border-radius:50%;background:linear-gradient(135deg,#1a1a1a,#333333);box-shadow:0 0 0 5px rgba(0,0,0,.08);}

.card-title{font-family:'Frank Ruhl Libre',serif;font-size:2rem;color:var(--cream);text-align:center;margin-bottom:8px;}
.card-subtitle{text-align:center;color:var(--muted);margin-bottom:26px;}

.msg-box{padding:13px 16px;border-radius:14px;margin-bottom:20px;font-weight:700;border:1px solid;}
.msg-error{background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.35);color:#fecaca;}
.msg-success{background:rgba(34,197,94,.12);border-color:rgba(34,197,94,.35);color:#bbf7d0;}

.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 16px;}
.form-group{margin-bottom:17px;}
.form-label{display:block;margin-bottom:8px;font-weight:700;color:var(--cream);font-size:.9rem;}
.input-wrap{position:relative;}
.form-input{width:100%;padding:13px 44px 13px 46px;border-radius:15px;border:1px solid var(--border);background:rgba(253,248,240,.055);color:var(--cream);font-family:'Heebo',sans-serif;font-size:.96rem;outline:none;transition:.25s ease;}
.form-input::placeholder{color:rgba(253,248,240,.38);}
.form-input:focus{border-color:#1a1a1a;background:rgba(253,248,240,.09);box-shadow:0 0 0 4px rgba(0,0,0,.08);}
.input-icon{position:absolute;right:15px;top:50%;transform:translateY(-50%);color:rgba(253,248,240,.45);pointer-events:none;}
.toggle-password{position:absolute;left:13px;top:50%;transform:translateY(-50%);cursor:pointer;color:rgba(253,248,240,.55);z-index:5;transition:.2s;}
.toggle-password:hover{color:#1a1a1a;}

/* ─── LECTURER CHECKBOX ─── */
.lecturer-box {
    margin-bottom: 20px;
    padding: 16px 18px;
    border-radius: 16px;
    border: 1.5px solid rgba(0,0,0,.16);
    background: rgba(217,119,6,.07);
    display: flex;
    align-items: flex-start;
    gap: 13px;
    cursor: pointer;
    transition: .22s ease;
    user-select: none;
}
.lecturer-box:hover { background: rgba(0,0,0,.07); border-color: rgba(0,0,0,.28); }
.lecturer-box.checked { background: rgba(0,0,0,.08); border-color: var(--gold); }
.lecturer-checkbox { width: 20px; height: 20px; border-radius: 6px; border: 2px solid rgba(217,119,6,.5); background: transparent; appearance: none; -webkit-appearance: none; cursor: pointer; flex-shrink: 0; margin-top: 2px; transition: .2s ease; }
.lecturer-checkbox:checked { background: var(--gold); border-color: var(--gold); }
.lecturer-checkbox:checked::after { content: '✓'; display: flex; align-items: center; justify-content: center; color: white; font-size: .85rem; font-weight: 900; height: 100%; }
.lecturer-info { flex: 1; }
.lecturer-label { font-weight: 800; color: var(--gold-light); font-size: .95rem; display: block; margin-bottom: 4px; }
.lecturer-desc { font-size: .78rem; color: rgba(253,248,240,.55); line-height: 1.5; }
.lecturer-icon { font-size: 1.5rem; flex-shrink: 0; }

.strength-meter{height:6px;background:rgba(253,248,240,.12);margin-top:8px;border-radius:10px;overflow:hidden;display:none;}
.strength-bar{height:100%;width:0;transition:.35s ease;border-radius:10px;}
#strengthText{display:block;margin-top:6px;color:var(--muted)!important;}
.match-status{font-size:.78rem;margin-top:6px;display:block;font-weight:700;}

.btn-submit{width:100%;margin-top:8px;margin-bottom:22px;padding:14px 22px;border-radius:15px;border:0;font-family:'Heebo',sans-serif;font-size:1.05rem;font-weight:900;color:#fff;background:linear-gradient(90deg,#1a1a1a,#333333);cursor:pointer;box-shadow:0 18px 35px rgba(0,0,0,.14);transition:.25s ease;}
.btn-submit:hover{transform:translateY(-2px);box-shadow:0 25px 48px rgba(0,0,0,.20);}

/* ── INTERESTS ── */
.interests-label{display:block;margin-bottom:10px;color:var(--cream);font-size:.88rem;font-weight:800;}
.interests-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px;margin-bottom:18px;}
.interest-chip{position:relative;}
.interest-chip input[type=checkbox]{position:absolute;opacity:0;width:0;height:0;}
.interest-chip label{
    display:flex;align-items:center;gap:7px;
    padding:9px 13px;border-radius:12px;
    border:1.5px solid rgba(253,248,240,.18);
    background:rgba(253,248,240,.055);
    color:rgba(253,248,240,.7);
    font-size:.83rem;font-weight:700;
    cursor:pointer;transition:.2s ease;
    user-select:none;
}
.interest-chip label:hover{border-color:rgba(217,119,6,.5);color:#1a1a1a;background:rgba(0,0,0,.05);}
.interest-chip input:checked+label{
    border-color:#1a1a1a;
    background:rgba(0,0,0,.09);
    color:#1a1a1a;
    box-shadow:0 0 0 3px rgba(0,0,0,.08);
}
.interest-chip input:checked+label::after{content:"✓";margin-right:auto;font-weight:900;color:#1a1a1a;}
.interests-hint{font-size:.75rem;color:rgba(253,248,240,.42);margin-bottom:16px;}

.card-link{display:block;text-align:center;text-decoration:none;color:#1a1a1a;font-weight:800;transition:.2s;}
.card-link:hover{color:#1a1a1a;}

@media(max-width:560px){
    .card{padding:32px 22px;}
    .form-grid{grid-template-columns:1fr;}
    .card-title{font-size:1.7rem;}
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
    <div class="card-logo">
        <a href="index.php" class="logo-text"><span class="logo-dot"></span>SkillHub</a>
    </div>

    <div class="card-title">הרשמת משתמש חדש 👋</div>
    <div class="card-subtitle">אנא מלא את פרטיך ליצירת חשבון</div>

    <?php if ($error_message): ?>
        <div class="msg-box msg-error"><?= htmlspecialchars($error_message) ?></div>
    <?php elseif ($success_message): ?>
        <div class="msg-box msg-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <form action="" method="post" id="regForm">
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">שם פרטי</label>
                <div class="input-wrap">
                    <input type="text" name="name" class="form-input" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    <span class="input-icon">👤</span>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">שם משפחה</label>
                <div class="input-wrap">
                    <input type="text" name="lastname" class="form-input" required value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>">
                    <span class="input-icon">👤</span>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">דוא"ל</label>
            <div class="input-wrap">
                <input type="email" name="email" class="form-input" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                <span class="input-icon">✉</span>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">טלפון</label>
            <div class="input-wrap">
                <input type="tel" name="phone" id="phoneInput" class="form-input" required
                       placeholder="050-123-4567" maxlength="12"
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                       oninput="formatPhone(this)">
                <span class="input-icon">📞</span>
            </div>
            <div style="font-size:.74rem;color:var(--text3);margin-top:4px;padding-right:4px;">מספר ישראלי בלבד, לדוגמה: 050-123-4567</div>
        </div>

        <div class="form-group">
            <label class="form-label">סיסמה</label>
            <div class="input-wrap">
                <input type="password" name="password" id="password" class="form-input" required placeholder="8+ תווים, אות גדולה ומספר">
                <span class="input-icon">🔑</span>
                <span class="toggle-password" onclick="togglePass('password', this)">👁️</span>
            </div>
            <div class="strength-meter" id="meter"><div class="strength-bar" id="bar"></div></div>
            <small id="strengthText" style="font-size:.7rem;color:var(--muted)"></small>
        </div>

        <div class="form-group">
            <label class="form-label">אימות סיסמה</label>
            <div class="input-wrap">
                <input type="password" name="confirm_password" id="confirm_password" class="form-input" required>
                <span class="input-icon">🔄</span>
                <span class="toggle-password" onclick="togglePass('confirm_password', this)">👁️</span>
            </div>
            <span id="matchText" class="match-status"></span>
        </div>

        <!-- ✅ תחומי עניין -->
        <div style="margin-bottom:6px;">
            <span class="interests-label">🎯 תחומי עניין (אופציונלי)</span>
            <p class="interests-hint">בחר את הנושאים שמעניינים אותך — נציג לך תוכן מותאם</p>
            <div class="interests-grid">
                <?php foreach ($all_types as $type): ?>
                <div class="interest-chip">
                    <input type="checkbox" name="interests[]"
                           id="int_<?= $type['typeId'] ?>"
                           value="<?= $type['typeId'] ?>"
                           <?= in_array($type['typeId'], array_map('intval', $_POST['interests'] ?? [])) ? 'checked' : '' ?>>
                    <label for="int_<?= $type['typeId'] ?>">🏷️ <?= htmlspecialchars($type['typeName']) ?></label>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ✅ Lecturer checkbox -->
        <label class="lecturer-box" id="lecturerBox">
            <input type="checkbox" name="wants_lecturer" class="lecturer-checkbox" id="lecturerCheck"
                   onchange="toggleLecturerBox(this)">
            <span class="lecturer-icon">🎓</span>
            <div class="lecturer-info">
                <span class="lecturer-label">אני רוצה להיות מרצה</span>
                <span class="lecturer-desc">בקשתך תישלח למנהל לאישור. עד לאישור תיכנס כמשתמש רגיל.</span>
            </div>
        </label>

        <button type="submit" name="submit" class="btn-submit">✦ הירשם עכשיו</button>

        <a href="login.php" class="card-link">← כבר יש לך חשבון? התחבר כאן</a>
    </form>
</div>

<script>
function togglePass(id, el) {
    const input = document.getElementById(id);
    const isPassword = input.type === "password";
    input.type = isPassword ? "text" : "password";
    el.textContent = isPassword ? "🙈" : "👁️";
}

function toggleLecturerBox(cb) {
    document.getElementById('lecturerBox').classList.toggle('checked', cb.checked);
}

const password     = document.getElementById('password');
const confirmInput = document.getElementById('confirm_password');
const bar          = document.getElementById('bar');
const meter        = document.getElementById('meter');
const strengthText = document.getElementById('strengthText');
const matchText    = document.getElementById('matchText');

password.addEventListener('input', () => {
    const val = password.value;
    let strength = 0;
    meter.style.display = val.length > 0 ? 'block' : 'none';
    if (val.length >= 8) strength++;
    if (/[A-Z]/.test(val)) strength++;
    if (/[a-z]/.test(val)) strength++;
    if (/[0-9]/.test(val)) strength++;
    if (/[^A-Za-z0-9]/.test(val)) strength++;
    const colors = ['#ef4444','#f97316','#333333','#84cc16','#22c55e'];
    const texts  = ['חלשה מאוד','חלשה','בינונית','חזקה','חזקה מאוד'];
    bar.style.width      = (strength * 20) + '%';
    bar.style.background = colors[strength - 1] || 'transparent';
    strengthText.textContent = val.length > 0 ? 'חוזק: ' + texts[strength - 1] : '';
    checkMatch();
});

function checkMatch() {
    if (confirmInput.value === '') { matchText.textContent = ''; return; }
    if (password.value === confirmInput.value) {
        matchText.textContent = '✓ הסיסמאות תואמות';
        matchText.style.color = '#22c55e';
    } else {
        matchText.textContent = '✗ הסיסמאות אינן תואמות';
        matchText.style.color = '#fecaca';
    }
}

confirmInput.addEventListener('input', checkMatch);

document.getElementById('regForm').onsubmit = function () {
    if (password.value !== confirmInput.value) {
        alert('הסיסמאות אינן זהות!');
        return false;
    }
    return true;
};

function formatPhone(input) {
    let v = input.value.replace(/\D/g, '').slice(0, 10);
    if (v.length > 6)      v = v.slice(0,3) + '-' + v.slice(3,6) + '-' + v.slice(6);
    else if (v.length > 3) v = v.slice(0,3) + '-' + v.slice(3);
    input.value = v;
}
</script>

</body>
</html>