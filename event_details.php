<?php
session_start();

$conn = new mysqli('localhost', 'root', '', 'skillhub');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$eventId = intval($_GET['event_id'] ?? 0);
if ($eventId <= 0) { header('Location: index.php'); exit(); }

$isLoggedIn = isset($_SESSION['id']);
$isAdmin    = 0;
$userId     = $isLoggedIn ? $_SESSION['id'] : 0;

if ($isLoggedIn) {
    $su = $conn->prepare("SELECT name, isAdmin FROM users WHERE id = ?");
    if ($su) { $su->bind_param("i",$userId); $su->execute(); $r=$su->get_result(); if($r->num_rows===1){$u=$r->fetch_assoc();$isAdmin=$u['isAdmin'];}$su->close(); }
}

$event = null;
$event = null;
$s = $conn->prepare("
    SELECT e.id AS eventID, e.eventName, e.eventDate, e.eventTime,
           e.maxParticipants, e.description,
           IFNULL(t.typeName,'—') AS typeName,
           IFNULL(u.name,'—') AS lecturerName, IFNULL(u.lastname,'') AS lecturerLastname
    FROM events e
    LEFT JOIN types t ON e.typeId = t.typeId
    LEFT JOIN users u ON e.lecturerId = u.id
    WHERE e.id = ?
");
if ($s) { $s->bind_param("i",$eventId); $s->execute(); $r=$s->get_result(); if($r->num_rows>0) $event=$r->fetch_assoc(); $s->close(); }
if (!$event) { header('Location: index.php'); exit(); }

$currentParticipants = 0;
$sc = $conn->prepare("SELECT COUNT(user_id) AS cnt FROM event_registrations WHERE event_id = ?");
if ($sc) { $sc->bind_param("i",$eventId); $sc->execute(); $rc=$sc->get_result(); if($rc->num_rows===1) $currentParticipants=$rc->fetch_assoc()['cnt']; $sc->close(); }

// בדיקה אם המשתמש כבר רשום
$isAlreadyRegistered = false;
if ($isLoggedIn) {
    $sr = $conn->prepare("SELECT 1 FROM event_registrations WHERE event_id=? AND user_id=?");
    if ($sr) { $sr->bind_param("ii",$eventId,$userId); $sr->execute(); $sr->store_result(); $isAlreadyRegistered = $sr->num_rows > 0; $sr->close(); }
}

$left   = $event['maxParticipants'] - $currentParticipants;
$isFull = $left <= 0;

$conn->close();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($event['eventName']) ?> — SkillHub</title>
<link rel="icon" type="image/png" href="images/skillhub2.jpeg">
<link href="https://fonts.googleapis.com/css2?family=Frank+Ruhl+Libre:wght@300;400;500;700;900&family=Heebo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}

:root{
    --navy:#1e2d4a;
    --bg:#f4f6fb;
    --surface:#ffffff;
    --surface2:#f8fafc;
    --surface3:#f1f5f9;
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
    --shadow-sm:0 1px 3px rgba(0,0,0,.06);
    --shadow:0 4px 16px rgba(0,0,0,.07),0 1px 3px rgba(0,0,0,.05);
    --shadow-md:0 8px 28px rgba(0,0,0,.09);
    --shadow-lg:0 20px 50px rgba(0,0,0,.11);
    --radius:16px;
    --radius-sm:10px;
}

html{scroll-behavior:smooth;}
body{font-family:'Heebo',sans-serif;direction:rtl;min-height:100vh;background:var(--bg);color:var(--text);display:flex;flex-direction:column;}

/* ══ NAVBAR ══ */
.navbar{
    position:sticky;top:0;z-index:100;height:66px;padding:0 40px;
    display:flex;align-items:center;justify-content:space-between;
    background:rgba(255,255,255,.92);border-bottom:1px solid var(--border);
    backdrop-filter:blur(16px);box-shadow:var(--shadow-sm);
}
.nav-logo{font-family:'Frank Ruhl Libre',serif;font-size:1.65rem;font-weight:900;color:var(--navy);text-decoration:none;display:flex;align-items:center;gap:9px;}
.nav-logo-dot{width:10px;height:10px;border-radius:50%;background:#1a1a1a;box-shadow:0 0 0 4px rgba(0,0,0,.1);}
.nav-links{display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
.nav-link{padding:8px 14px;border-radius:var(--radius-sm);color:var(--text3);text-decoration:none;font-size:.88rem;font-weight:700;border:1px solid transparent;transition:.2s ease;}
.nav-link:hover{color:var(--navy);background:var(--surface3);border-color:var(--border);}
.nav-link.primary{color:#fff;background:#1a1a1a;}
.nav-link.primary:hover{background:#333;}
.nav-link.danger{color:var(--red);}
.nav-link.danger:hover{background:var(--red-bg);border-color:#fecaca;}
.nav-sep{color:var(--border2);font-size:.7rem;}

/* ══ HERO STRIP ══ */
.hero-strip{
    background:linear-gradient(135deg,var(--navy) 0%,#2d3e5a 50%,#1e2d4a 100%);
    padding:40px 40px 44px;position:relative;overflow:hidden;
}
.hero-strip::before{
    content:"";position:absolute;inset:0;
    background:radial-gradient(circle at 15% 50%,rgba(255,255,255,.06),transparent 40%);
    pointer-events:none;
}
.hero-inner{position:relative;z-index:2;max-width:1200px;margin:0 auto;}
.event-badge{
    display:inline-flex;align-items:center;gap:6px;padding:5px 13px;border-radius:999px;
    margin-bottom:14px;color:rgba(255,255,255,.7);
    background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);
    font-size:.76rem;font-weight:800;
}
.event-title{font-family:'Frank Ruhl Libre',serif;font-size:clamp(2rem,4vw,3rem);font-weight:900;color:#fff;line-height:1.1;margin-bottom:16px;}
.event-lecturer{display:flex;align-items:center;gap:12px;}
.lecturer-avatar{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);font-size:1rem;}
.lecturer-name{color:#fff;font-weight:700;font-size:.93rem;}
.lecturer-label{color:rgba(255,255,255,.5);font-size:.8rem;}

/* ══ MAIN LAYOUT ══ */
.main{max-width:1200px;margin:0 auto;padding:36px 40px 72px;flex:1;}
.detail-layout{display:grid;grid-template-columns:1fr 340px;gap:28px;align-items:start;}

/* ══ LEFT COLUMN ══ */
.detail-left{display:flex;flex-direction:column;gap:20px;}

/* ══ META ══ */
.meta-card{background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;}
.meta-grid{display:grid;grid-template-columns:repeat(2,1fr);}
.meta-item{padding:18px 20px;display:flex;flex-direction:column;gap:5px;border-left:1px solid var(--border);border-bottom:1px solid var(--border);}
.meta-item:nth-child(2n){border-left:none;}
.meta-item:nth-last-child(-n+2){border-bottom:none;}
.meta-label{color:var(--text4);font-size:.67rem;font-weight:800;letter-spacing:.6px;text-transform:uppercase;}
.meta-value{color:var(--navy);font-size:.95rem;font-weight:800;}
.meta-value.green{color:var(--green);}
.meta-value.red{color:var(--red);}

/* ══ DESCRIPTION ══ */
.desc-card{background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;flex:1;}
.desc-header{display:flex;align-items:center;gap:12px;padding:16px 20px;background:var(--surface2);border-bottom:1px solid var(--border);}
.desc-icon{width:38px;height:38px;border-radius:10px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:1rem;background:linear-gradient(135deg,#1a1a1a,#333);}
.desc-title{font-family:'Frank Ruhl Libre',serif;font-size:1rem;font-weight:900;color:var(--navy);}
.desc-body{padding:22px;color:var(--text2);line-height:2;font-size:.93rem;white-space:pre-wrap;min-height:140px;}
.desc-empty{padding:22px;color:var(--text4);font-style:italic;font-size:.9rem;}

/* ══ RIGHT SIDEBAR ══ */
.detail-sidebar{display:flex;flex-direction:column;gap:18px;position:sticky;top:86px;}
.action-card{background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow-md);overflow:hidden;}
.action-card-header{padding:18px 20px;border-bottom:1px solid var(--border);background:var(--surface2);}
.action-card-title{font-family:'Frank Ruhl Libre',serif;font-size:1.1rem;font-weight:900;color:var(--navy);margin-bottom:4px;}
.action-card-body{padding:20px;}
.spots-chip{display:flex;align-items:center;gap:8px;padding:11px 14px;border-radius:var(--radius-sm);font-weight:800;font-size:.88rem;margin-bottom:14px;}
.spots-chip.full{color:var(--red);background:var(--red-bg);border:1px solid #fecaca;}
.spots-chip.avail{color:var(--green);background:var(--green-bg);border:1px solid var(--green-border);}
.action-btns{display:flex;flex-direction:column;gap:9px;}
.btn{display:flex;align-items:center;justify-content:center;gap:7px;padding:11px 18px;border-radius:var(--radius-sm);text-decoration:none;font-family:'Heebo',sans-serif;font-size:.88rem;font-weight:800;border:0;cursor:pointer;transition:.2s ease;text-align:center;}
.btn:hover{transform:translateY(-1px);}
.btn-register,.btn-login{color:#fff;background:linear-gradient(90deg,#1a1a1a,#333);box-shadow:0 4px 14px rgba(0,0,0,.2);}
.btn-register:hover,.btn-login:hover{box-shadow:0 6px 20px rgba(0,0,0,.3);}
.btn-back{color:var(--text2);background:var(--surface2);border:1px solid var(--border2);}
.btn-back:hover{background:var(--border);}
.btn-full{color:var(--text4);background:var(--surface2);border:1px solid var(--border);cursor:not-allowed;}

/* Share card */
.share-card{background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);padding:18px 20px;}
.share-title{font-size:.78rem;font-weight:800;color:var(--text3);letter-spacing:.5px;text-transform:uppercase;margin-bottom:12px;}
.share-btns{display:flex;gap:8px;}
.share-btn{flex:1;padding:8px;border-radius:8px;border:1px solid var(--border);background:var(--surface2);color:var(--text3);font-size:.78rem;font-weight:700;cursor:pointer;text-align:center;transition:.2s ease;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:4px;}
.share-btn:hover{background:var(--border);color:var(--navy);}

.site-footer{text-align:center;padding:20px;color:var(--text3);border-top:1px solid var(--border);background:var(--surface);font-size:.84rem;}
::-webkit-scrollbar{width:6px;}::-webkit-scrollbar-track{background:var(--bg);}::-webkit-scrollbar-thumb{background:var(--border2);border-radius:20px;}::-webkit-scrollbar-thumb:hover{background:#1a1a1a;}

@media(max-width:900px){
    .detail-layout{grid-template-columns:1fr;}
    .detail-sidebar{position:static;}
    .main{padding:22px 18px 60px;}
}
@media(max-width:640px){
    .navbar{height:auto;padding:14px 20px;flex-direction:column;gap:12px;}
    .hero-strip{padding:28px 20px 32px;}
    .meta-grid{grid-template-columns:1fr;}
    .meta-item{border-left:none!important;}
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

<nav class="navbar">
    <a href="index.php" class="nav-logo"><span class="nav-logo-dot"></span>SkillHub</a>
    <div class="nav-links">
        <a href="aboutus.php" class="nav-link">אודות</a>
        <span class="nav-sep">·</span>
        <?php if ($isLoggedIn): ?>
            <?php if ($isAdmin == 0): ?><a href="profile.php" class="nav-link">הפרופיל שלי</a><span class="nav-sep">·</span>
            <?php elseif ($isAdmin == 1): ?><a href="admin.php" class="nav-link danger">ניהול מערכת</a><span class="nav-sep">·</span>
            <?php elseif ($isAdmin == 2): ?><a href="lec.php" class="nav-link">ניהול מרצה</a><span class="nav-sep">·</span>
            <?php endif; ?>
            <a href="logout.php" class="nav-link danger">← התנתק</a>
        <?php else: ?>
            <a href="login.php" class="nav-link">כניסה</a><span class="nav-sep">·</span>
            <a href="signUp.php" class="nav-link primary">הרשמה</a>
        <?php endif; ?>
    </div>
</nav>

<div class="hero-strip">
    <div class="hero-inner">
        <div class="event-badge">📅 אירוע · <?= htmlspecialchars($event['typeName']) ?></div>
        <div class="event-title"><?= htmlspecialchars($event['eventName']) ?></div>
        <div class="event-lecturer">
            <div class="lecturer-avatar">👤</div>
            <div>
                <div class="lecturer-label">מרצה</div>
                <div class="lecturer-name"><?= htmlspecialchars($event['lecturerName'].' '.$event['lecturerLastname']) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="main">
    <div class="breadcrumb">
        <a href="index.php">🏠 דף הבית</a>
        <span class="breadcrumb-sep">›</span>
        <a href="index.php#events">אירועים</a>
        <span class="breadcrumb-sep">›</span>
        <span><?= htmlspecialchars($event['eventName']) ?></span>
    </div>

    <div class="detail-layout">

        <!-- ══ עמודה שמאל: מידע ותיאור ══ -->
        <div class="detail-left">

            <div class="meta-card">
                <div class="meta-grid">
                    <div class="meta-item"><span class="meta-label">📆 תאריך</span><span class="meta-value"><?= htmlspecialchars($event['eventDate']) ?></span></div>
                    <div class="meta-item"><span class="meta-label">🕐 שעה</span><span class="meta-value"><?= htmlspecialchars(substr($event['eventTime'],0,5)) ?></span></div>
                    <div class="meta-item"><span class="meta-label">🏷️ קטגוריה</span><span class="meta-value"><?= htmlspecialchars($event['typeName']) ?></span></div>
                    <div class="meta-item"><span class="meta-label">👥 קיבולת</span><span class="meta-value <?= $isFull ? 'red' : 'green' ?>"><?= $isFull ? "מלא ({$event['maxParticipants']})" : "{$left} / {$event['maxParticipants']}" ?></span></div>
                </div>
            </div>

            <div class="desc-card">
                <div class="desc-header"><div class="desc-icon">📝</div><div class="desc-title">אודות האירוע</div></div>
                <?php if (!empty(trim($event['description'] ?? ''))): ?>
                    <div class="desc-body"><?= nl2br(htmlspecialchars($event['description'])) ?></div>
                <?php else: ?>
                    <div class="desc-empty">לא הוזן תיאור לאירוע זה.</div>
                <?php endif; ?>
            </div>

        </div>

        <!-- ══ סיידבר ימין: פעולות ══ -->
        <div class="detail-sidebar">

            <div class="action-card">
                <div class="action-card-header">
                    <div class="action-card-title">הרשמה לאירוע</div>
                    <div style="color:var(--text3);font-size:.82rem;"><?= $event['eventName'] ?></div>
                </div>
                <div class="action-card-body">
                    <div class="spots-chip <?= $isFull ? 'full' : 'avail' ?>">
                        <?= $isFull ? '❌ האירוע מלא' : '✅ '.$left.' מקומות פנויים' ?>
                    </div>
                    <div class="action-btns">
                        <?php
                        $isPast = strtotime($event['eventDate'].' '.$event['eventTime']) <= time();
                        if ($isAlreadyRegistered): ?>
                            <div style="padding:10px 14px;border-radius:var(--radius-sm);background:#f0fdf4;border:1px solid #86efac;color:#16a34a;font-size:.85rem;font-weight:700;text-align:center;">✅ כבר רשום/ה לאירוע זה</div>
                        <?php elseif ($isPast): ?>
                            <div style="padding:10px 14px;border-radius:var(--radius-sm);background:var(--surface2);border:1px solid var(--border);color:var(--text3);font-size:.85rem;font-weight:600;text-align:center;">✓ האירוע הסתיים</div>
                        <?php elseif ($isFull): ?>
                            <button class="btn btn-full" disabled>מלא</button>
                        <?php elseif ($isLoggedIn && $isAdmin == 0): ?>
                            <a href="checkout.php?type=event&id=<?= $eventId ?>" class="btn btn-register">💳 לתשלום והרשמה ←</a>
                        <?php elseif (!$isLoggedIn): ?>
                            <a href="login.php" class="btn btn-login">התחבר להרשמה ←</a>
                        <?php endif; ?>
                        <?php
                        $backUrl   = 'index.php';
                        $backLabel = '← חזרה לדף הבית';
                        if (isset($_GET['from'])) {
                            if ($_GET['from'] === 'admin')   { $backUrl = 'admin.php';   $backLabel = '← חזרה לניהול'; }
                            if ($_GET['from'] === 'profile') { $backUrl = 'profile.php'; $backLabel = '← חזרה לפרופיל'; }
                        }
                        ?>
                        <a href="<?= $backUrl ?>" class="btn btn-back"><?= $backLabel ?></a>
                    </div>
                </div>
            </div>

            <div class="share-card">
                <div class="share-title">שתף אירוע</div>
                <div class="share-btns">
                    <a href="https://wa.me/?text=<?= urlencode($event['eventName'].' - http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']) ?>" target="_blank" class="share-btn">📱 WhatsApp</a>
                    <button onclick="navigator.clipboard.writeText(window.location.href).then(()=>alert('הקישור הועתק!'))" class="share-btn">🔗 העתק</button>
                </div>
            </div>

        </div>
    </div>
</div>

<footer class="site-footer">&copy; 2026 SkillHub — כל הזכויות שמורות</footer>
</body>
</html>