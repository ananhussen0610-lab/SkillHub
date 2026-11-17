<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'skillhub');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$isLoggedIn = isset($_SESSION['id']); $isAdmin = 0; $userId = $isLoggedIn ? $_SESSION['id'] : 0;
if ($isLoggedIn) { $s=$conn->prepare("SELECT name,isAdmin FROM users WHERE id=?"); if($s){$s->bind_param("i",$userId);$s->execute();$r=$s->get_result();if($r->num_rows===1){$u=$r->fetch_assoc();$isAdmin=$u['isAdmin'];}$s->close();} }
$pastWorkshops = [];
$rw = $conn->query("SELECT w.wsnum AS workshopID,w.WorkshopName,w.date,w.time,w.maxParticipants,w.price,w.description,IFNULL(u.name,'—') AS lecturerName,IFNULL(u.lastname,'') AS lecturerLastname,COUNT(wr.user_id) AS totalRegistered FROM workshop w LEFT JOIN users u ON w.lecid=u.id LEFT JOIN workshop_registrations wr ON w.wsnum=wr.workshop_id WHERE w.date < CURDATE() OR (w.date = CURDATE() AND IFNULL(w.time,'23:59:59') <= CURTIME()) GROUP BY w.wsnum ORDER BY w.date DESC,w.time DESC");
if ($rw === false) {
    echo "<!-- DB Error: " . $conn->error . " -->";
}
if ($rw && $rw->num_rows > 0) while($row=$rw->fetch_assoc()) $pastWorkshops[]=$row;
$conn->close();
?><!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>היסטוריית סדנאות — SkillHub</title>
<link rel="icon" type="image/png" href="images/skillhub2.jpeg">
<link href="https://fonts.googleapis.com/css2?family=Frank+Ruhl+Libre:wght@300;400;500;700;900&family=Heebo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{--navy:#1e2d4a;--bg:#f4f6fb;--surface:#fff;--surface2:#f8fafc;--surface3:#f1f5f9;--border:#e2e8f0;--border2:#cbd5e1;--text:#0f172a;--text2:#334155;--text3:#64748b;--text4:#94a3b8;--green:#16a34a;--red:#dc2626;--red-bg:#fef2f2;--shadow-sm:0 1px 3px rgba(0,0,0,.06);--shadow:0 4px 16px rgba(0,0,0,.07);--shadow-md:0 8px 28px rgba(0,0,0,.09);--shadow-lg:0 20px 50px rgba(0,0,0,.11);--radius:16px;--radius-sm:10px;}
html{scroll-behavior:smooth;}
body{font-family:'Heebo',sans-serif;direction:rtl;min-height:100vh;background:var(--bg);color:var(--text);display:flex;flex-direction:column;}
.navbar{position:sticky;top:0;z-index:100;height:66px;padding:0 40px;display:flex;align-items:center;justify-content:space-between;background:rgba(255,255,255,.92);border-bottom:1px solid var(--border);backdrop-filter:blur(16px);box-shadow:var(--shadow-sm);}
.nav-logo{font-family:'Frank Ruhl Libre',serif;font-size:1.65rem;font-weight:900;color:var(--navy);text-decoration:none;display:flex;align-items:center;gap:9px;}
.nav-logo-dot{width:10px;height:10px;border-radius:50%;background:#1a1a1a;box-shadow:0 0 0 4px rgba(0,0,0,.1);}
.nav-links{display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
.nav-link{padding:8px 14px;border-radius:var(--radius-sm);color:var(--text3);text-decoration:none;font-size:.88rem;font-weight:700;border:1px solid transparent;transition:.2s ease;}
.nav-link:hover{color:var(--navy);background:var(--surface3);border-color:var(--border);}
.nav-link.primary{color:#fff;background:#1a1a1a;}
.nav-link.danger{color:var(--red);}
.nav-link.danger:hover{background:var(--red-bg);border-color:#fecaca;}
.nav-sep{color:var(--border2);font-size:.7rem;}
.hero-strip{background:linear-gradient(135deg,var(--navy) 0%,#2d3e5a 50%,#1e2d4a 100%);padding:36px 40px 40px;position:relative;overflow:hidden;}
.hero-strip::before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 15% 50%,rgba(255,255,255,.06),transparent 40%);pointer-events:none;}
.hero-inner{position:relative;z-index:2;max-width:1200px;margin:0 auto;display:flex;align-items:center;gap:16px;}
.heading-icon{width:54px;height:54px;border-radius:16px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:1.4rem;background:linear-gradient(135deg,#1a1a1a,#333);box-shadow:var(--shadow-md);}
.heading-title{font-family:'Frank Ruhl Libre',serif;font-size:1.85rem;font-weight:900;color:#fff;}
.heading-sub{color:rgba(255,255,255,.5);margin-top:4px;font-size:.86rem;}
.main{flex:1;max-width:1200px;margin:0 auto;width:100%;padding:28px 28px 72px;}
.breadcrumb{display:flex;align-items:center;gap:8px;margin-bottom:18px;color:var(--text3);font-size:.84rem;}
.breadcrumb a{color:var(--text3);text-decoration:none;transition:.2s;}
.breadcrumb a:hover{color:var(--navy);}
.breadcrumb-sep{color:var(--border2);}
.table-card{background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow-lg);overflow:hidden;margin-bottom:18px;}
.table-wrap{overflow-x:auto;}
table{width:100%;min-width:860px;border-collapse:collapse;}
thead tr{background:linear-gradient(180deg,var(--surface2),#edf2f7);}
th{padding:11px 16px;text-align:right;color:var(--text3);font-size:.72rem;font-weight:800;border-bottom:2px solid var(--border);letter-spacing:.4px;text-transform:uppercase;white-space:nowrap;}
tbody tr{transition:.15s ease;}
tbody tr:hover{background:#f8fafc;}
td{padding:13px 16px;color:var(--text2);border-bottom:1px solid #f1f5f9;font-size:.88rem;vertical-align:middle;}
tbody tr:last-child td{border-bottom:none;}
.ws-name{font-family:'Frank Ruhl Libre',serif;font-size:1rem;font-weight:900;color:var(--navy);}
.reg-count{display:inline-flex;align-items:center;gap:5px;color:var(--text3);font-weight:700;}
.reg-count strong{color:var(--navy);}
.badge-ended{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;color:var(--text3);background:var(--surface2);border:1px solid var(--border);font-size:.72rem;font-weight:700;}
.ws-btn-details{display:inline-flex;align-items:center;justify-content:center;padding:7px 13px;border-radius:8px;text-decoration:none;font-family:'Heebo',sans-serif;font-size:.8rem;font-weight:700;color:var(--text2);background:var(--surface2);border:1.5px solid var(--border2);transition:.2s ease;}
.ws-btn-details:hover{background:var(--border);color:var(--navy);}
.price-tag{display:inline-flex;padding:3px 9px;border-radius:6px;font-size:.78rem;font-weight:800;color:var(--green);background:#f0fdf4;border:1px solid #86efac;}
.empty-state{text-align:center;padding:60px 24px;border-radius:var(--radius);background:var(--surface);border:1px solid var(--border);box-shadow:var(--shadow);}
.empty-icon{font-size:2.8rem;display:block;margin-bottom:12px;opacity:.4;}
.empty-text{color:var(--text3);font-weight:600;margin-bottom:16px;}
.back-btn,.btn-back-empty{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:var(--radius-sm);text-decoration:none;font-weight:700;font-size:.88rem;transition:.2s ease;}
.back-btn{color:var(--text2);background:var(--surface2);border:1px solid var(--border2);}
.back-btn:hover{background:var(--border);}
.btn-back-empty{color:#fff;background:#1a1a1a;}
.btn-back-empty:hover{background:#333;}
.site-footer{text-align:center;padding:18px;color:var(--text3);border-top:1px solid var(--border);background:var(--surface);font-size:.84rem;}
::-webkit-scrollbar{width:6px;height:6px;}::-webkit-scrollbar-track{background:var(--bg);}::-webkit-scrollbar-thumb{background:var(--border2);border-radius:20px;}::-webkit-scrollbar-thumb:hover{background:#1a1a1a;}
@media(max-width:760px){.navbar{height:auto;padding:14px 20px;flex-direction:column;gap:12px;}.hero-strip{padding:26px 20px 30px;}.main{padding:18px 16px 60px;}.hero-inner{flex-direction:column;text-align:center;}}

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
        <a href="index.php" class="nav-link">🏠 דף הבית</a><span class="nav-sep">·</span>
        <?php if ($isLoggedIn): ?>
            <?php if ($isAdmin==0): ?><a href="profile.php" class="nav-link">הפרופיל שלי</a><span class="nav-sep">·</span>
            <?php elseif ($isAdmin==1): ?><a href="admin.php" class="nav-link danger">ניהול מערכת</a><span class="nav-sep">·</span>
            <?php elseif ($isAdmin==2): ?><a href="lec.php" class="nav-link">ניהול מרצה</a><span class="nav-sep">·</span>
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
        <div class="heading-icon">📜</div>
        <div><div class="heading-title">היסטוריית סדנאות</div><div class="heading-sub"><?= count($pastWorkshops) ?> סדנאות שהסתיימו · מיון מהאחרונה לראשונה</div></div>
    </div>
</div>
<div class="main">
    <div class="breadcrumb"><a href="index.php">🏠 דף הבית</a><span class="breadcrumb-sep">›</span><span>היסטוריית סדנאות</span></div>
    <?php if (!empty($pastWorkshops)): ?>
    <div class="table-card"><div class="table-wrap"><table>
        <thead><tr><th>שם הסדנה</th><th>מרצה</th><th>תאריך</th><th>שעה</th><th>מחיר</th><th>נרשמו</th><th>סטטוס</th><th style="text-align:center">פרטים</th></tr></thead>
        <tbody>
        <?php foreach ($pastWorkshops as $ws): ?>
        <tr>
            <td><span class="ws-name"><?= htmlspecialchars($ws['WorkshopName']) ?></span></td>
            <td><?= htmlspecialchars($ws['lecturerName'].' '.$ws['lecturerLastname']) ?></td>
            <td style="font-family:'Courier New',monospace;font-size:.84rem"><?= htmlspecialchars($ws['date']) ?></td>
            <td style="font-family:'Courier New',monospace;font-size:.84rem"><?= htmlspecialchars(substr($ws['time'],0,5)) ?></td>
            <td><span class="price-tag"><?= number_format($ws['price'],2) ?> ₪</span></td>
            <td><span class="reg-count">👥 <strong><?= $ws['totalRegistered'] ?></strong> / <?= $ws['maxParticipants'] ?></span></td>
            <td><span class="badge-ended">✓ הסתיימה</span></td>
            <td style="text-align:center"><a href="workshop_details.php?wsnum=<?= $ws['workshopID'] ?>" class="ws-btn-details">📄 פרטים</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div></div>
    <a href="index.php" class="back-btn">← חזרה לדף הבית</a>
    <?php else: ?>
    <div class="empty-state"><span class="empty-icon">🗂️</span><div class="empty-text">אין סדנאות שהסתיימו עדיין</div><a href="index.php" class="btn-back-empty">← חזרה לדף הבית</a></div>
    <?php endif; ?>
</div>
<footer class="site-footer">&copy; 2026 SkillHub — כל הזכויות שמורות</footer>
</body>
</html>