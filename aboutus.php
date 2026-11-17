<?php
session_start();

$isLoggedIn    = isset($_SESSION['id']);
$isAdmin       = 0;
$userFirstName = 'אורח';
$userId        = $isLoggedIn ? $_SESSION['id'] : 0;

$conn = new mysqli('localhost', 'root', '', 'skillhub');

if ($isLoggedIn && !$conn->connect_error) {
    $s = $conn->prepare("SELECT name, isAdmin FROM users WHERE id = ?");
    if ($s) { $s->bind_param("i",$userId); $s->execute(); $r=$s->get_result(); if($r->num_rows===1){$u=$r->fetch_assoc();$isAdmin=$u['isAdmin'];$userFirstName=htmlspecialchars($u['name']);}$s->close(); }
}

$totalWorkshops = 0; $totalParticipants = 0; $totalLecturers = 0;
if (!$conn->connect_error) {
    $r = $conn->query("SELECT COUNT(*) AS cnt FROM workshop"); if($r) $totalWorkshops=$r->fetch_assoc()['cnt'];
    $r = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE isAdmin=0"); if($r) $totalParticipants=$r->fetch_assoc()['cnt'];
    $r = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE isAdmin=2"); if($r) $totalLecturers=$r->fetch_assoc()['cnt'];
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>אודות — SkillHub</title>
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
    --shadow-sm:0 1px 3px rgba(0,0,0,.06);
    --shadow:0 4px 16px rgba(0,0,0,.07),0 1px 3px rgba(0,0,0,.05);
    --shadow-md:0 8px 28px rgba(0,0,0,.09);
    --shadow-lg:0 20px 50px rgba(0,0,0,.11);
    --radius:16px;
    --radius-sm:10px;
}

html{scroll-behavior:smooth;}
body{
    font-family:'Heebo',sans-serif;direction:rtl;min-height:100vh;
    display:flex;flex-direction:column;background:var(--bg);color:var(--text);
}

/* ══ NAVBAR ══ */
.navbar{
    position:sticky;top:0;z-index:100;height:66px;padding:0 40px;
    display:flex;align-items:center;justify-content:space-between;
    background:rgba(255,255,255,.92);border-bottom:1px solid var(--border);
    backdrop-filter:blur(16px);box-shadow:var(--shadow-sm);
}
.nav-logo{
    font-family:'Frank Ruhl Libre',serif;font-size:1.65rem;font-weight:900;
    color:var(--navy);text-decoration:none;display:flex;align-items:center;gap:9px;
}
.nav-logo-dot{width:10px;height:10px;border-radius:50%;background:#1a1a1a;box-shadow:0 0 0 4px rgba(0,0,0,.1);}
.nav-links{display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
.nav-link{
    padding:8px 14px;border-radius:var(--radius-sm);
    color:var(--text3);text-decoration:none;font-size:.88rem;font-weight:700;
    border:1px solid transparent;transition:.2s ease;
}
.nav-link:hover{color:var(--navy);background:var(--surface3);border-color:var(--border);}
.nav-link.active,.nav-link.primary{color:#fff;background:#1a1a1a;border-color:transparent;}
.nav-link.danger{color:#dc2626;}
.nav-link.danger:hover{background:#fef2f2;border-color:#fecaca;}
.nav-sep{color:var(--border2);font-size:.7rem;}

/* ══ HERO STRIP ══ */
.hero-strip{
    background:
        linear-gradient(135deg,rgba(15,23,42,.80) 0%,rgba(30,45,74,.74) 100%),
        url('images/workshop.png') center/cover no-repeat;
    padding:72px 40px 100px;text-align:center;position:relative;overflow:hidden;
}
.hero-strip::before{
    content:"";position:absolute;inset:0;
    background:radial-gradient(circle at 15% 50%,rgba(255,255,255,.06),transparent 40%),
               radial-gradient(circle at 85% 20%,rgba(255,255,255,.04),transparent 35%);
    pointer-events:none;
}
.hero-inner{position:relative;z-index:2;}
.hero-eyebrow{
    display:inline-flex;align-items:center;gap:8px;margin-bottom:18px;
    padding:6px 16px;border-radius:999px;
    color:rgba(255,255,255,.65);background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.15);font-size:.8rem;font-weight:800;letter-spacing:1px;
}
.hero-title{
    font-family:'Frank Ruhl Libre',serif;
    font-size:clamp(2.6rem,6vw,4.4rem);font-weight:900;
    color:#fff;line-height:1.05;margin-bottom:20px;
}
.hero-title span{color:rgba(255,255,255,.5);}
.hero-desc{
    max-width:680px;margin:0 auto;padding:20px 24px;border-radius:var(--radius);
    color:rgba(255,255,255,.55);line-height:1.9;font-size:1rem;
    background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);
    backdrop-filter:blur(12px);
}

/* ══ STATS ROW ══ */
.stats-row{
    max-width:900px;margin:-50px auto 0;padding:0 28px;
    display:grid;grid-template-columns:repeat(3,1fr);gap:16px;
    position:relative;z-index:10;
}
.stat-card{
    background:var(--surface);border-radius:var(--radius);
    border:1px solid var(--border);box-shadow:var(--shadow-lg);
    padding:26px 20px;text-align:center;transition:.22s ease;
}
.stat-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-md);border-color:var(--border2);}
.stat-number{
    font-family:'Frank Ruhl Libre',serif;font-size:2.6rem;font-weight:900;
    color:var(--navy);line-height:1;margin-bottom:6px;
}
.stat-number span{color:var(--text3);}
.stat-label{color:var(--text3);font-weight:700;font-size:.88rem;}

/* ══ CONTENT ══ */
.content-wrap{max-width:1100px;margin:64px auto 0;padding:0 28px;}

.section-label{
    display:flex;align-items:center;gap:14px;margin:0 0 26px;
    color:var(--text3);font-size:.76rem;font-weight:800;
    letter-spacing:.8px;text-transform:uppercase;
}
.section-label::before,.section-label::after{content:"";height:1px;flex:1;background:var(--border);}

/* ══ MISSION ══ */
.mission-block{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:56px;}
.mission-card{
    background:var(--surface);border-radius:var(--radius);
    border:1px solid var(--border);box-shadow:var(--shadow);
    padding:32px 28px;transition:.22s ease;
}
.mission-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-md);border-color:var(--border2);}
.mission-icon{
    width:52px;height:52px;border-radius:16px;margin-bottom:16px;
    display:flex;align-items:center;justify-content:center;font-size:1.4rem;
    background:linear-gradient(135deg,#1a1a1a,#333);box-shadow:var(--shadow-md);
}
.mission-title{
    font-family:'Frank Ruhl Libre',serif;color:var(--navy);
    font-size:1.35rem;font-weight:900;margin-bottom:10px;
}
.mission-text{color:var(--text3);line-height:1.85;font-size:.94rem;}

/* ══ VALUES ══ */
.values-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:56px;}
.value-card{
    background:var(--surface);border-radius:var(--radius);
    border:1px solid var(--border);box-shadow:var(--shadow);
    padding:26px 20px;text-align:center;transition:.22s ease;
}
.value-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-md);border-color:var(--border2);}
.value-emoji{font-size:2.1rem;margin-bottom:12px;display:block;}
.value-title{
    font-family:'Frank Ruhl Libre',serif;color:var(--navy);
    font-size:1.1rem;font-weight:900;margin-bottom:8px;
}
.value-text{color:var(--text3);line-height:1.75;font-size:.87rem;}

/* ══ TEAM ══ */
.team-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:20px;margin-bottom:56px;}
.team-card{
    background:var(--surface);border-radius:var(--radius);
    border:1px solid var(--border);box-shadow:var(--shadow);
    padding:28px 20px;text-align:center;transition:.22s ease;
}
.team-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-md);border-color:var(--border2);}
.team-avatar{
    width:90px;height:90px;border-radius:50%;margin:0 auto 14px;
    display:flex;align-items:center;justify-content:center;overflow:hidden;
    border:3px solid var(--border2);background:var(--surface2);position:relative;
}
.team-avatar img{width:100%;height:100%;object-fit:cover;position:relative;z-index:2;}
.team-avatar-emoji{position:absolute;z-index:1;font-size:2rem;}
.team-name{font-family:'Frank Ruhl Libre',serif;color:var(--navy);font-size:1.1rem;font-weight:900;margin-bottom:4px;}
.team-role{color:var(--text3);font-size:.82rem;font-weight:700;}
.team-tag{
    display:inline-flex;margin-top:10px;padding:4px 12px;border-radius:999px;
    color:var(--text2);background:var(--surface2);border:1px solid var(--border2);
    font-size:.74rem;font-weight:800;
}

/* ══ CTA ══ */
.cta-block{
    background:linear-gradient(135deg,var(--navy) 0%,#2d3e5a 50%,#1e2d4a 100%);
    border-radius:20px;padding:52px 32px;text-align:center;margin-bottom:72px;
    box-shadow:var(--shadow-lg);position:relative;overflow:hidden;
}
.cta-block::before{
    content:"";position:absolute;inset:0;
    background:radial-gradient(circle at 20% 50%,rgba(255,255,255,.06),transparent 40%);
    pointer-events:none;
}
.cta-title{
    font-family:'Frank Ruhl Libre',serif;font-size:2.1rem;font-weight:900;
    color:#fff;margin-bottom:10px;position:relative;z-index:2;
}
.cta-title span{color:rgba(255,255,255,.5);}
.cta-sub{color:rgba(255,255,255,.55);margin-bottom:26px;position:relative;z-index:2;font-size:.95rem;}
.cta-btns{display:flex;justify-content:center;gap:12px;flex-wrap:wrap;position:relative;z-index:2;}
.cta-btn{
    padding:12px 24px;border-radius:var(--radius-sm);
    text-decoration:none;font-weight:800;font-size:.92rem;transition:.22s ease;
}
.cta-btn:hover{transform:translateY(-2px);}
.cta-btn-primary{color:#fff;background:#fff3;border:1.5px solid rgba(255,255,255,.3);}
.cta-btn-primary:hover{background:rgba(255,255,255,.2);}
.cta-btn-secondary{color:rgba(255,255,255,.6);background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.15);}
.cta-btn-secondary:hover{background:rgba(255,255,255,.12);}

.site-footer{
    text-align:center;padding:20px;color:var(--text3);
    border-top:1px solid var(--border);background:var(--surface);font-size:.85rem;
}

::-webkit-scrollbar{width:6px;}
::-webkit-scrollbar-track{background:var(--bg);}
::-webkit-scrollbar-thumb{background:var(--border2);border-radius:20px;}
::-webkit-scrollbar-thumb:hover{background:#1a1a1a;}

@media(max-width:780px){
    .navbar{height:auto;padding:14px 20px;flex-direction:column;gap:12px;}
    .hero-strip{padding:50px 20px 80px;}
    .stats-row,.mission-block,.values-grid{grid-template-columns:1fr;}
    .content-wrap{padding:0 18px;}
    .cta-title{font-size:1.7rem;}
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

<!-- ══ NAVBAR ══ -->
<nav class="navbar">
    <a href="index.php" class="nav-logo"><span class="nav-logo-dot"></span>SkillHub</a>
    <div class="nav-links">
        <a href="index.php" class="nav-link">דף הבית</a>
        <span class="nav-sep">·</span>
        <a href="aboutus.php" class="nav-link active">אודות</a>
        <span class="nav-sep">·</span>
        <a href="contact.php" class="nav-link">צור קשר</a>
        <span class="nav-sep">·</span>
        <?php if ($isLoggedIn): ?>
            <?php if ($isAdmin == 1): ?>
                <a href="admin.php" class="nav-link danger">ניהול מערכת</a><span class="nav-sep">·</span>
            <?php elseif ($isAdmin == 0): ?>
                <a href="profile.php" class="nav-link">הפרופיל שלי</a><span class="nav-sep">·</span>
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

<!-- ══ HERO STRIP ══ -->
<div class="hero-strip">
    <div class="hero-inner">
        <div class="hero-eyebrow">✦ מי אנחנו</div>
        <div class="hero-title">הסיפור מאחורי <span>SkillHub</span></div>
        <p class="hero-desc">פלטפורמה שנולדה מתוך אמונה אחת פשוטה — כל אדם ראוי לגישה לידע איכותי, למרצים מעולים, ולקהילה שתתמוך בצמיחתו המקצועית.</p>
    </div>
</div>

<!-- ══ STATS ══ -->
<div class="stats-row">
    <div class="stat-card">
        <div class="stat-number"><?= number_format($totalWorkshops) ?><span>+</span></div>
        <div class="stat-label">סדנאות זמינות</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= number_format($totalParticipants) ?><span>+</span></div>
        <div class="stat-label">משתתפים רשומים</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= number_format($totalLecturers) ?><span>+</span></div>
        <div class="stat-label">מרצים מומחים</div>
    </div>
</div>

<!-- ══ CONTENT ══ -->
<div class="content-wrap" style="margin-top:70px;">

    <div class="section-label">המשימה שלנו</div>
    <div class="mission-block">
        <div class="mission-card">
            <div class="mission-icon">🎯</div>
            <div class="mission-title">המשימה</div>
            <p class="mission-text">SkillHub נוסדה מתוך רצון לגשר על הפער בין ידע לשוק העבודה. אנו מחברים בין אנשי מקצוע מנוסים לבין לומדים שמחפשים לפתח כישורים רלוונטיים ומעשיים — בפורמט גמיש, איכותי ונגיש.</p>
        </div>
        <div class="mission-card">
            <div class="mission-icon">🔭</div>
            <div class="mission-title">החזון</div>
            <p class="mission-text">אנו חולמים על עולם שבו כל אדם — בלי קשר לרקע, מקום מגורים או תקציב — יכול לפתח את הכישורים שיובילו אותו להצלחה. SkillHub היא הצעד הראשון במסע הזה.</p>
        </div>
    </div>

    <div class="section-label">הערכים שמנחים אותנו</div>
    <div class="values-grid">
        <div class="value-card"><span class="value-emoji">🌱</span><div class="value-title">צמיחה מתמדת</div><p class="value-text">אנו מאמינים שלמידה היא תהליך שלא נגמר. בכל סדנה ואירוע — רוכשים ידע חדש.</p></div>
        <div class="value-card"><span class="value-emoji">🤝</span><div class="value-title">קהילה וחיבור</div><p class="value-text">מעבר לידע — SkillHub בונה קשרים. בין לומדים, בין מרצים, בין תעשיות.</p></div>
        <div class="value-card"><span class="value-emoji">⭐</span><div class="value-title">איכות ללא פשרות</div><p class="value-text">כל מרצה עובר תהליך בחירה קפדני. אנו מבטיחים תוכן מקצועי ועדכני בלבד.</p></div>
        <div class="value-card"><span class="value-emoji">🔓</span><div class="value-title">נגישות לכולם</div><p class="value-text">פלטפורמה פשוטה ואינטואיטיבית, עם מגוון אפשרויות מחיר כדי שאף אחד לא יישאר מאחור.</p></div>
        <div class="value-card"><span class="value-emoji">💡</span><div class="value-title">חדשנות מעשית</div><p class="value-text">אנחנו לא מלמדים תיאוריה בלבד. כל סדנה בנויה סביב יישום מיידי בשוק העבודה.</p></div>
        <div class="value-card"><span class="value-emoji">🛡️</span><div class="value-title">אמינות ושקיפות</div><p class="value-text">אנו פועלים בשקיפות מלאה — מחירים ברורים, תכנים אמיתיים, ציפיות ריאליסטיות.</p></div>
    </div>

    <div class="section-label">הצוות שמאחורי הקלעים</div>
    <div class="team-grid">
        <div class="team-card">
            <div class="team-avatar"><span class="team-avatar-emoji">👨‍💻</span><img src="images/admin5.jpeg" alt="Anan"></div>
            <div class="team-name">Anan Hussen</div>
            <div class="team-role">מנהל טכנולוגי</div>
            <span class="team-tag">Tech</span>
        </div>
        <div class="team-card">
            <div class="team-avatar"><span class="team-avatar-emoji">👨‍💻</span><img src="images/admin5.jpeg" alt="Mohhmed"></div>
            <div class="team-name">Mohhmed AbuSaleh</div>
            <div class="team-role">מנהל טכנולוגי</div>
            <span class="team-tag">Tech</span>
        </div>
    </div>

    <div class="cta-block">
        <div class="cta-title">מוכן להצטרף ל<span>SkillHub</span>?</div>
        <p class="cta-sub">הצטרף ל-<?= number_format($totalParticipants) ?> משתתפים שכבר משדרגים את הכישורים שלהם</p>
        <div class="cta-btns">
            <?php if (!$isLoggedIn): ?>
                <a href="signUp.php" class="cta-btn cta-btn-primary">הירשם בחינם ←</a>
                <a href="index.php" class="cta-btn cta-btn-secondary">צפה בסדנאות</a>
            <?php else: ?>
                <a href="index.php" class="cta-btn cta-btn-primary">צפה בסדנאות ←</a>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /content-wrap -->

<footer class="site-footer">&copy; 2026 SkillHub — כל הזכויות שמורות</footer>
</body>
</html>