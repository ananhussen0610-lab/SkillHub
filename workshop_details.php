<?php
session_start();

$conn = new mysqli('localhost', 'root', '', 'skillhub');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$wsnum = intval($_GET['wsnum'] ?? $_GET['workshop_id'] ?? 0);
if ($wsnum <= 0) { header('Location: index.php'); exit(); }

$isLoggedIn    = isset($_SESSION['id']);
$isAdmin       = 0;
$userFirstName = 'אורח';
$userId        = $isLoggedIn ? $_SESSION['id'] : 0;

if ($isLoggedIn) {
    $su = $conn->prepare("SELECT name, isAdmin FROM users WHERE id = ?");
    if ($su) { $su->bind_param("i",$userId); $su->execute(); $r=$su->get_result(); if($r->num_rows===1){$u=$r->fetch_assoc();$isAdmin=$u['isAdmin'];$userFirstName=htmlspecialchars($u['name']);}$su->close(); }
}

$workshop = null;
$s = $conn->prepare("
    SELECT w.wsnum, w.WorkshopName, w.description, w.date, w.time,
           w.duration, w.location, w.price, w.maxParticipants, w.type, w.img_path,
           w.syllabus,
           IFNULL((SELECT typeName FROM types WHERE CAST(typeId AS CHAR)=w.type OR typeName=w.type LIMIT 1), w.type) AS wsTypeName,
           IFNULL(u.name,'—') AS lecturerName, IFNULL(u.lastname,'') AS lecturerLastname
    FROM workshop w
    LEFT JOIN users u ON w.lecId = u.id
    WHERE w.wsnum = ?
");
if ($s) { $s->bind_param("i",$wsnum); $s->execute(); $r=$s->get_result(); if($r->num_rows>0) $workshop=$r->fetch_assoc(); $s->close(); }
if (!$workshop) { header('Location: index.php'); exit(); }

$currentParticipants = 0;
$sc = $conn->prepare("SELECT COUNT(user_id) AS cnt FROM workshop_registrations WHERE workshop_id = ?");
if ($sc) { $sc->bind_param("i",$wsnum); $sc->execute(); $rc=$sc->get_result(); if($rc->num_rows===1) $currentParticipants=$rc->fetch_assoc()['cnt']; $sc->close(); }

$isAlreadyRegistered = false;
if ($isLoggedIn) {
    $sr = $conn->prepare("SELECT 1 FROM workshop_registrations WHERE workshop_id=? AND user_id=?");
    if ($sr) { $sr->bind_param("ii",$wsnum,$userId); $sr->execute(); $sr->store_result(); $isAlreadyRegistered = $sr->num_rows > 0; $sr->close(); }
}

$left   = $workshop['maxParticipants'] - $currentParticipants;
$isFull = $left <= 0;


// שליפת מספר שאלות
$quizCount = 0;
$sqc = $conn->prepare("SELECT COUNT(*) AS cnt FROM quiz_questions WHERE wsnum = ?");
if ($sqc) {
    $sqc->bind_param("i", $wsnum);
    $sqc->execute();
    $quizCount = (int)$sqc->get_result()->fetch_assoc()['cnt'];
    $sqc->close();
}

// האם המשתמש כבר ענה?
$quizDone  = false;
$quizScore = null;
if ($isLoggedIn && $quizCount > 0) {
    $sqr = $conn->prepare("SELECT score, total FROM quiz_results WHERE wsnum=? AND user_id=?");
    if ($sqr) {
        $sqr->bind_param("ii", $wsnum, $userId);
        $sqr->execute();
        $rqr = $sqr->get_result();
        if ($rqr->num_rows > 0) {
            $quizDone  = true;
            $quizScore = $rqr->fetch_assoc();
        }
        $sqr->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($workshop['WorkshopName']) ?> — SkillHub</title>
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
    --radius:16px;
    --radius-sm:10px;
}

html{scroll-behavior:smooth;}
body{font-family:'Heebo',sans-serif;direction:rtl;min-height:100vh;background:var(--bg);color:var(--text);display:flex;flex-direction:column;}

.navbar{position:sticky;top:0;z-index:100;height:66px;padding:0 40px;display:flex;align-items:center;justify-content:space-between;background:rgba(255,255,255,.92);border-bottom:1px solid var(--border);backdrop-filter:blur(16px);box-shadow:var(--shadow-sm);}
.nav-logo{font-family:'Frank Ruhl Libre',serif;font-size:1.65rem;font-weight:900;color:var(--navy);text-decoration:none;display:flex;align-items:center;gap:9px;}
.nav-logo-dot{width:10px;height:10px;border-radius:50%;background:#1a1a1a;box-shadow:0 0 0 4px rgba(0,0,0,.1);}
.nav-links{display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
.nav-link{padding:8px 14px;border-radius:var(--radius-sm);color:var(--text3);text-decoration:none;font-size:.88rem;font-weight:700;border:1px solid transparent;transition:.2s ease;}
.nav-link:hover{color:var(--navy);background:var(--surface3);border-color:var(--border);}
.nav-link.primary{color:#fff;background:#1a1a1a;border-color:transparent;}
.nav-link.primary:hover{background:#333;}
.nav-link.danger{color:var(--red);}
.nav-link.danger:hover{background:var(--red-bg);border-color:#fecaca;}
.nav-sep{color:var(--border2);font-size:.7rem;}

.hero-strip{background:linear-gradient(135deg,var(--navy) 0%,#2d3e5a 50%,#1e2d4a 100%);padding:40px 40px 44px;position:relative;overflow:hidden;}
.hero-strip::before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 15% 50%,rgba(255,255,255,.06),transparent 40%);pointer-events:none;}
.hero-inner{position:relative;z-index:2;max-width:1200px;margin:0 auto;}
.workshop-badge{display:inline-flex;align-items:center;gap:6px;padding:5px 13px;border-radius:999px;margin-bottom:14px;color:rgba(255,255,255,.7);background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);font-size:.76rem;font-weight:800;}
.workshop-title{font-family:'Frank Ruhl Libre',serif;font-size:2.4rem;font-weight:900;color:#fff;line-height:1.1;margin-bottom:16px;}
.workshop-lecturer{display:flex;align-items:center;gap:12px;}
.lecturer-avatar{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);font-size:1rem;}
.lecturer-name{color:#fff;font-weight:700;font-size:.93rem;}
.lecturer-label{color:rgba(255,255,255,.5);font-size:.8rem;}

.main{max-width:1200px;margin:0 auto;padding:36px 40px 72px;flex:1;}
.detail-layout{display:grid;grid-template-columns:1fr 340px;gap:28px;align-items:start;}
.detail-left{display:flex;flex-direction:column;gap:20px;}
.detail-sidebar{display:flex;flex-direction:column;gap:18px;position:sticky;top:86px;}

.breadcrumb{display:flex;align-items:center;gap:8px;margin-bottom:24px;color:var(--text3);font-size:.84rem;}
.breadcrumb a{color:var(--text3);text-decoration:none;transition:.2s;}
.breadcrumb a:hover{color:var(--navy);}
.breadcrumb-sep{color:var(--border2);}

.meta-card{background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;margin-bottom:18px;}
.meta-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));}
.meta-item{padding:20px;display:flex;flex-direction:column;gap:5px;border-left:1px solid var(--border);}
.meta-item:last-child{border-left:none;}
.meta-label{color:var(--text4);font-size:.7rem;font-weight:800;letter-spacing:.5px;text-transform:uppercase;}
.meta-value{color:var(--navy);font-size:.95rem;font-weight:800;}
.meta-value.green{color:var(--green);}
.meta-value.red{color:var(--red);}

.desc-card{background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;margin-bottom:18px;}
.desc-header{display:flex;align-items:center;gap:12px;padding:16px 20px;background:var(--surface2);border-bottom:1px solid var(--border);}
.desc-icon{width:40px;height:40px;border-radius:12px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:1.1rem;background:linear-gradient(135deg,#1a1a1a,#333);}
.desc-title{font-family:'Frank Ruhl Libre',serif;font-size:1.05rem;font-weight:900;color:var(--navy);}
.desc-body{padding:22px;color:var(--text2);line-height:2;font-size:.95rem;white-space:pre-wrap;}
.desc-empty{padding:22px;color:var(--text4);font-style:italic;font-size:.9rem;}

/* ══ סילבוס — כרטיס ייעודי ══ */
.syllabus-card{
    background:var(--surface);
    border-radius:var(--radius);
    border:1px solid #bfdbfe;   /* כחול עדין */
    box-shadow:var(--shadow);
    overflow:hidden;
    margin-bottom:18px;
}
.syllabus-header{
    display:flex;align-items:center;gap:12px;
    padding:16px 20px;
    background:linear-gradient(90deg,#eff6ff,#f8fafc);
    border-bottom:1px solid #bfdbfe;
}
.syllabus-icon{
    width:40px;height:40px;border-radius:12px;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;font-size:1.1rem;
    background:linear-gradient(135deg,#3b82f6,#6366f1);
}
.syllabus-title{font-family:'Frank Ruhl Libre',serif;font-size:1.05rem;font-weight:900;color:var(--navy);}
.syllabus-body{
    padding:22px;
    color:var(--text2);
    line-height:2;
    font-size:.95rem;
    white-space:pre-wrap;
    /* שורות עם נקודות */
    counter-reset: syllabus-line;
}
/* כל שורה קבלה עיצוב נקודה */
.syllabus-line{
    display:flex;
    align-items:flex-start;
    gap:10px;
    padding:6px 0;
    border-bottom:1px solid #f1f5f9;
}
.syllabus-line:last-child{border-bottom:none;}
.syllabus-bullet{
    width:22px;height:22px;border-radius:50%;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;
    background:#eff6ff;border:1px solid #bfdbfe;
    color:#3b82f6;font-size:.7rem;font-weight:900;
    margin-top:3px;
}
.syllabus-text{flex:1;color:var(--text2);font-size:.93rem;line-height:1.7;}

.action-card{background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);padding:22px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.spots-chip{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:var(--radius-sm);font-weight:800;font-size:.88rem;}
.spots-chip.full{color:var(--red);background:var(--red-bg);border:1px solid #fecaca;}
.spots-chip.avail{color:var(--green);background:var(--green-bg);border:1px solid var(--green-border);}
.action-btns{display:flex;gap:10px;flex-wrap:wrap;}

/* sidebar action card */
.action-card-sidebar{background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;}
.action-card-header{padding:18px 20px;border-bottom:1px solid var(--border);background:var(--surface2);}
.action-card-title{font-family:'Frank Ruhl Libre',serif;font-size:1.1rem;font-weight:900;color:var(--navy);margin-bottom:4px;}
.action-card-body{padding:20px;}
.price-highlight-box{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-radius:var(--radius-sm);margin-bottom:14px;background:var(--surface2);border:1px solid var(--border);}
.price-highlight-label{font-size:.78rem;font-weight:700;color:var(--text3);}
.price-highlight-val{font-size:1.3rem;font-weight:900;color:var(--navy);}

.share-card{background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);padding:18px 20px;}
.share-title{font-size:.78rem;font-weight:800;color:var(--text3);letter-spacing:.5px;text-transform:uppercase;margin-bottom:12px;}
.share-btns{display:flex;gap:8px;}
.share-btn{flex:1;padding:8px;border-radius:8px;border:1px solid var(--border);background:var(--surface2);color:var(--text3);font-size:.78rem;font-weight:700;cursor:pointer;transition:.2s ease;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:4px;}
.share-btn:hover{background:var(--border);color:var(--navy);}

.btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:11px 20px;border-radius:var(--radius-sm);text-decoration:none;font-family:'Heebo',sans-serif;font-size:.9rem;font-weight:800;border:0;cursor:pointer;transition:.2s ease;white-space:nowrap;}
.btn:hover{transform:translateY(-1px);}
.btn-back{color:var(--text2);background:var(--surface2);border:1px solid var(--border2);}
.btn-back:hover{background:var(--border);}
.btn-register{color:#fff;background:linear-gradient(90deg,#1a1a1a,#333333);box-shadow:0 4px 14px rgba(0,0,0,.22);}
.btn-register:hover{box-shadow:0 6px 20px rgba(0,0,0,.32);}
.btn-login{color:#fff;background:linear-gradient(90deg,#1a1a1a,#333333);box-shadow:0 4px 14px rgba(0,0,0,.2);}
.btn-login:hover{box-shadow:0 6px 18px rgba(0,0,0,.3);}
.btn-full{color:var(--text4);background:var(--surface2);border:1px solid var(--border);cursor:not-allowed;}

::-webkit-scrollbar{width:6px;}
::-webkit-scrollbar-track{background:var(--bg);}
::-webkit-scrollbar-thumb{background:var(--border2);border-radius:20px;}
::-webkit-scrollbar-thumb:hover{background:#1a1a1a;}
.site-footer{text-align:center;padding:22px;color:var(--text3);border-top:1px solid var(--border);background:var(--surface);font-size:.85rem;}

@media(max-width:900px){
    .detail-layout{grid-template-columns:1fr;}
    .detail-sidebar{position:static;}
    .main{padding:22px 18px 60px;}
}
@media(max-width:760px){
    .navbar{height:auto;padding:14px 20px;flex-direction:column;gap:12px;}
    .hero-strip{padding:28px 20px 32px;}
    .workshop-title{font-size:1.8rem;}
    .meta-grid{grid-template-columns:1fr 1fr;}
    .action-card{flex-direction:column;align-items:flex-start;}
    .action-btns{width:100%;}
    .btn{flex:1;justify-content:center;}
}

.quiz-card{background:linear-gradient(135deg,#f5f3ff,#ede9fe);border-radius:var(--radius);border:1.5px solid #ddd6fe;box-shadow:var(--shadow);padding:20px;text-align:center;}
.quiz-card-icon{font-size:2rem;display:block;margin-bottom:8px;}
.quiz-card-title{font-family:'Frank Ruhl Libre',serif;font-size:1.05rem;font-weight:900;color:#4c1d95;margin-bottom:4px;}
.quiz-card-sub{font-size:.82rem;color:#7c3aed;margin-bottom:14px;}
.quiz-score-chip{background:#f5f3ff;border:1px solid #ddd6fe;border-radius:8px;padding:8px 12px;margin-bottom:12px;font-size:.85rem;font-weight:700;color:#7c3aed;}
.btn-quiz{display:flex;align-items:center;justify-content:center;gap:7px;width:100%;padding:11px;background:linear-gradient(90deg,#7c3aed,#6366f1);color:#fff;border:0;border-radius:var(--radius-sm);font-family:'Heebo',sans-serif;font-size:.9rem;font-weight:800;text-decoration:none;transition:.2s;box-shadow:0 4px 14px rgba(124,58,237,.28);}
.btn-quiz:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(124,58,237,.38);}
.quiz-locked{display:flex;align-items:center;justify-content:center;gap:7px;width:100%;padding:11px;background:var(--surface2);color:var(--text3);border:1px solid var(--border);border-radius:var(--radius-sm);font-size:.87rem;font-weight:600;}

</style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="nav-logo"><span class="nav-logo-dot"></span>SkillHub</a>
    <div class="nav-links">
        <a href="index.php" class="nav-link">דף הבית</a>
        <span class="nav-sep">·</span>
        <a href="aboutus.php" class="nav-link">אודות</a>
        <span class="nav-sep">·</span>
        <?php if ($isLoggedIn): ?>
            <?php if ($isAdmin == 0): ?>
                <a href="profile.php" class="nav-link">הפרופיל שלי</a><span class="nav-sep">·</span>
            <?php elseif ($isAdmin == 1): ?>
                <a href="admin.php" class="nav-link danger">ניהול מערכת</a><span class="nav-sep">·</span>
            <?php elseif ($isAdmin == 2): ?>
                <a href="lec.php" class="nav-link">ניהול מרצה</a><span class="nav-sep">·</span>
            <?php endif; ?>
            <a href="logout.php" class="nav-link danger">← התנתק</a>
        <?php else: ?>
            <a href="login.php" class="nav-link">כניסה</a>
            <span class="nav-sep">·</span>
            <a href="signUp.php" class="nav-link primary">הרשמה</a>
        <?php endif; ?>
    </div>
</nav>

<div class="hero-strip">
    <div class="hero-inner">
        <div class="workshop-badge">🎓 סדנה</div>
        <div class="workshop-title"><?= htmlspecialchars($workshop['WorkshopName']) ?></div>
        <div class="workshop-lecturer">
            <div class="lecturer-avatar">👤</div>
            <div>
                <div class="lecturer-label">מרצה</div>
                <div class="lecturer-name"><?= htmlspecialchars($workshop['lecturerName'].' '.$workshop['lecturerLastname']) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="main">

    <div class="breadcrumb">
        <a href="index.php">🏠 דף הבית</a>
        <span class="breadcrumb-sep">›</span>
        <a href="index.php#workshops">סדנאות</a>
        <span class="breadcrumb-sep">›</span>
        <span><?= htmlspecialchars($workshop['WorkshopName']) ?></span>
    </div>

    <div class="detail-layout">

        <!-- ══ עמודה שמאל ══ -->
        <div class="detail-left">

            <!-- מטא-נתונים -->
            <div class="meta-card">
                <div class="meta-grid">
                    <div class="meta-item"><span class="meta-label">📆 תאריך</span><span class="meta-value"><?= htmlspecialchars($workshop['date']) ?></span></div>
                    <div class="meta-item"><span class="meta-label">🕐 שעה</span><span class="meta-value"><?= htmlspecialchars(substr($workshop['time'],0,5)) ?></span></div>
                    <div class="meta-item"><span class="meta-label">📍 מיקום</span><span class="meta-value"><?= htmlspecialchars($workshop['location']) ?></span></div>
                    <div class="meta-item"><span class="meta-label">⏱ משך</span><span class="meta-value"><?= htmlspecialchars($workshop['duration']) ?> דק'</span></div>
                    <div class="meta-item"><span class="meta-label">👥 קיבולת</span><span class="meta-value <?= $isFull ? 'red' : 'green' ?>"><?= $isFull ? "מלא ({$workshop['maxParticipants']})" : "{$left} / {$workshop['maxParticipants']}" ?></span></div>
                    <div class="meta-item"><span class="meta-label">🎓 סוג</span><span class="meta-value"><?= htmlspecialchars($workshop['wsTypeName'] ?? '—') ?></span></div>
                </div>
            </div>

            <!-- תיאור -->
            <div class="desc-card">
                <div class="desc-header">
                    <div class="desc-icon">📝</div>
                    <div class="desc-title">אודות הסדנה</div>
                </div>
                <?php if (!empty($workshop['description'])): ?>
                    <div class="desc-body"><?= nl2br(htmlspecialchars($workshop['description'])) ?></div>
                <?php else: ?>
                    <div class="desc-empty">אין תיאור לסדנה זו.</div>
                <?php endif; ?>
            </div>

            <!-- ══ סילבוס — מוצג רק אם יש תוכן ══ -->
            <?php if (!empty(trim($workshop['syllabus'] ?? ''))): ?>
            <div class="syllabus-card">
                <div class="syllabus-header">
                    <div class="syllabus-icon">📋</div>
                    <div class="syllabus-title">סילבוס הסדנה</div>
                </div>
                <div style="padding:18px 22px;">
                    <?php
                    // פיצול לפי שורות והצגה עם עיצוב
                    $lines = explode("\n", trim($workshop['syllabus']));
                    $lineNum = 1;
                    foreach ($lines as $line):
                        $line = trim($line);
                        if ($line === '') continue;
                    ?>
                    <div class="syllabus-line">
                        <div class="syllabus-bullet"><?= $lineNum ?></div>
                        <div class="syllabus-text"><?= htmlspecialchars($line) ?></div>
                    </div>
                    <?php $lineNum++; endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- ══ סיידבר ימין ══ -->
        <div class="detail-sidebar">

            <div class="action-card-sidebar">
                <div class="action-card-header">
                    <div class="action-card-title">הרשמה לסדנה</div>
                    <div style="color:var(--text3);font-size:.82rem;"><?= htmlspecialchars($workshop['WorkshopName']) ?></div>
                </div>
                <div class="action-card-body">
                    <div class="price-highlight-box">
                        <span class="price-highlight-label">💰 מחיר</span>
                        <span class="price-highlight-val"><?= floatval($workshop['price'])>0 ? number_format($workshop['price'],2).' ₪' : 'חינמי' ?></span>
                    </div>
                    <div class="spots-chip <?= $isFull ? 'full' : 'avail' ?>" style="width:100%;margin-bottom:14px;">
                        <?= $isFull ? '❌ הסדנה מלאה' : '✅ '.$left.' מקומות פנויים' ?>
                    </div>
                    <div class="action-btns" style="flex-direction:column;">
                        <?php
                        $isPast = strtotime($workshop['date'].' '.($workshop['time'] ?? '23:59:59')) <= time();
                        if ($isAlreadyRegistered): ?>
                            <div style="padding:10px 14px;border-radius:var(--radius-sm);background:#f0fdf4;border:1px solid #86efac;color:#16a34a;font-size:.85rem;font-weight:700;text-align:center;">✅ כבר רשום/ה לסדנה זו</div>
                        <?php elseif ($isPast): ?>
                            <div style="padding:10px 14px;border-radius:var(--radius-sm);background:var(--surface2);border:1px solid var(--border);color:var(--text3);font-size:.85rem;font-weight:600;text-align:center;">✓ הסדנה הסתיימה</div>
                        <?php elseif ($isFull): ?>
                            <button class="btn btn-full" disabled style="width:100%;">מלא</button>
                        <?php elseif ($isLoggedIn && $isAdmin == 0): ?>
                            <a href="checkout.php?type=workshop&id=<?= $wsnum ?>" class="btn btn-register" style="width:100%;justify-content:center;">💳 לתשלום והרשמה ←</a>
                        <?php elseif (!$isLoggedIn): ?>
                            <a href="login.php" class="btn btn-login" style="width:100%;justify-content:center;">התחבר להרשמה ←</a>
                        <?php endif; ?>
                        <?php
                        $backUrl   = 'index.php';
                        $backLabel = '← חזרה לדף הבית';
                        if (isset($_GET['from'])) {
                            if ($_GET['from'] === 'admin')   { $backUrl = 'admin.php';   $backLabel = '← חזרה לניהול'; }
                            if ($_GET['from'] === 'profile') { $backUrl = 'profile.php'; $backLabel = '← חזרה לפרופיל'; }
                        }
                        ?>
                        <a href="<?= $backUrl ?>" class="btn btn-back" style="width:100%;justify-content:center;"><?= $backLabel ?></a>
                    </div>
                </div>
            </div>

            <!-- כרטיס מבחן -->
            <?php if ($quizCount > 0):
                $isPastQuiz = strtotime($workshop['date'].' '.($workshop['time'] ?? '23:59:59')) <= time();
            ?>
            <div class="quiz-card">
                <span class="quiz-card-icon">📝</span>
                <div class="quiz-card-title">מבחן סיום</div>
                <div class="quiz-card-sub"><?= $quizCount ?> שאלות</div>
                <?php if ($quizDone && $quizScore): ?>
                    <div class="quiz-score-chip">✓ ציון: <?= $quizScore['score'] ?>/<?= $quizScore['total'] ?></div>
                    <a href="quiz.php?wsnum=<?= $wsnum ?>" class="btn-quiz">📋 צפה במבחן</a>
                <?php elseif ($isPastQuiz && $isLoggedIn && $isAlreadyRegistered): ?>
                    <a href="quiz.php?wsnum=<?= $wsnum ?>" class="btn-quiz">📝 עבור למבחן</a>
                <?php elseif (!$isPastQuiz): ?>
                    <div class="quiz-locked">🔒 זמין אחרי סיום הסדנה</div>
                <?php elseif (!$isLoggedIn): ?>
                    <a href="login.php" class="btn-quiz">התחבר לגשת למבחן</a>
                <?php else: ?>
                    <div class="quiz-locked">🔒 רק לנרשמים לסדנה</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="share-card">
                <div class="share-title">שתף סדנה</div>
                <div class="share-btns">
                    <a href="https://wa.me/?text=<?= urlencode($workshop['WorkshopName'].' - http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']) ?>" target="_blank" class="share-btn">📱 WhatsApp</a>
                    <button onclick="navigator.clipboard.writeText(window.location.href).then(function(){alert('הקישור הועתק!')})" class="share-btn">🔗 העתק</button>
                </div>
            </div>

        </div>
    </div>

</div>

<footer class="site-footer">&copy; 2026 SkillHub — כל הזכויות שמורות</footer>
</body>
</html>