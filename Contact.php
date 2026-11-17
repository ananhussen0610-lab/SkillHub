<?php
session_start();

$isLoggedIn    = isset($_SESSION['id']);
$isAdmin       = 0;
$userFirstName = 'אורח';
$userId        = $isLoggedIn ? $_SESSION['id'] : 0;

if ($isLoggedIn) {
    $conn = new mysqli('localhost', 'root', '', 'skillhub');
    if (!$conn->connect_error) {
        $s = $conn->prepare("SELECT name, isAdmin FROM users WHERE id = ?");
        if ($s) { $s->bind_param("i",$userId); $s->execute(); $r=$s->get_result(); if($r->num_rows===1){$u=$r->fetch_assoc();$isAdmin=$u['isAdmin'];$userFirstName=htmlspecialchars($u['name']);}$s->close(); }
        $conn->close();
    }
}

$contact = [
    'instagram'        => 'skillhub',
    'whatsapp'         => '972500000000',
    'whatsapp_display' => '0500000000',
    'email'            => 'skillhub@s.com',
    'email_subject'    => 'פנייה דרך אתר SkillHub',
];
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>צור קשר — SkillHub</title>
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
    --shadow-sm:0 1px 3px rgba(0,0,0,.06);
    --shadow:0 4px 16px rgba(0,0,0,.07),0 1px 3px rgba(0,0,0,.05);
    --shadow-md:0 8px 28px rgba(0,0,0,.09);
    --shadow-lg:0 20px 50px rgba(0,0,0,.11);
    --radius:16px;
    --radius-sm:10px;
    --ig-start:#f09433;
    --ig-end:#bc1888;
    --wa:#25d366;
}

html{scroll-behavior:smooth;}
body{
    font-family:'Heebo',sans-serif;direction:rtl;min-height:100vh;
    display:flex;flex-direction:column;
    background:var(--bg);color:var(--text);
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
.nav-logo-dot{
    width:10px;height:10px;border-radius:50%;
    background:#1a1a1a;box-shadow:0 0 0 4px rgba(0,0,0,.1);
}
.nav-links{display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
.nav-link{
    padding:8px 14px;border-radius:var(--radius-sm);
    color:var(--text3);text-decoration:none;font-size:.88rem;font-weight:700;
    border:1px solid transparent;transition:.2s ease;
}
.nav-link:hover{color:var(--navy);background:var(--surface3);border-color:var(--border);}
.nav-link.active{color:#fff;background:#1a1a1a;border-color:transparent;}
.nav-link.primary{color:#fff;background:#1a1a1a;border-color:transparent;}
.nav-link.danger{color:#dc2626;}
.nav-link.danger:hover{background:#fef2f2;border-color:#fecaca;}
.nav-sep{color:var(--border2);font-size:.7rem;}

/* ══ HERO STRIP ══ */
.hero-strip{
    background:
        linear-gradient(135deg,rgba(15,23,42,.78) 0%,rgba(30,45,74,.72) 100%),
        url('images/contact.png') center/cover no-repeat;
    padding:64px 40px 68px;text-align:center;position:relative;overflow:hidden;
}
.hero-strip::before{
    content:"";position:absolute;inset:0;
    background:radial-gradient(circle at 20% 50%,rgba(255,255,255,.06),transparent 40%),
               radial-gradient(circle at 80% 30%,rgba(255,255,255,.04),transparent 35%);
    pointer-events:none;
}
.hero-inner{position:relative;z-index:2;}
.hero-title{
    font-family:'Frank Ruhl Libre',serif;
    font-size:clamp(2.2rem,5vw,3.4rem);font-weight:900;
    color:#fff;margin-bottom:10px;
    text-shadow:0 2px 12px rgba(0,0,0,.35);
}
.hero-sub{color:rgba(255,255,255,.7);font-size:.98rem;line-height:1.8;text-shadow:0 1px 6px rgba(0,0,0,.3);}

/* ══ MAIN ══ */
.main{flex:1;max-width:760px;margin:0 auto;width:100%;padding:36px 28px 72px;}

/* ══ BREADCRUMB ══ */
.breadcrumb{display:flex;align-items:center;gap:8px;margin-bottom:26px;color:var(--text3);font-size:.84rem;}
.breadcrumb a{color:var(--text3);text-decoration:none;transition:.2s;}
.breadcrumb a:hover{color:var(--navy);}
.breadcrumb-sep{color:var(--border2);}

/* ══ CONTACT CARD ══ */
.contact-card{
    background:var(--surface);border-radius:20px;
    border:1px solid var(--border);box-shadow:var(--shadow-lg);
    overflow:hidden;
}
.card-top-bar{height:4px;background:linear-gradient(90deg,#1a1a1a,#444,var(--wa));}

.card-header{
    padding:32px 32px 26px;border-bottom:1px solid var(--border);
    text-align:center;background:var(--surface2);
}
.card-header-icon{
    width:66px;height:66px;margin:0 auto 16px;border-radius:20px;
    display:flex;align-items:center;justify-content:center;font-size:1.9rem;
    background:linear-gradient(135deg,#1a1a1a,#333);
    box-shadow:var(--shadow-md);
}
.card-header-title{
    font-family:'Frank Ruhl Libre',serif;font-size:1.7rem;font-weight:900;
    color:var(--navy);margin-bottom:7px;
}
.card-header-sub{color:var(--text3);line-height:1.8;font-size:.9rem;}

/* ══ CONTACT ITEMS ══ */
.contact-list{padding:8px 0;}
.contact-item{
    display:flex;align-items:center;gap:18px;
    padding:22px 32px;text-decoration:none;
    transition:.22s ease;border-bottom:1px solid var(--border);
    color:var(--text);
}
.contact-item:last-child{border-bottom:none;}
.contact-item:hover{background:var(--surface2);transform:translateX(-3px);}

.contact-icon-wrap{
    width:54px;height:54px;flex-shrink:0;border-radius:16px;
    display:flex;align-items:center;justify-content:center;
    background:var(--surface2);border:1.5px solid var(--border);
    transition:.22s ease;
}
.contact-icon-wrap svg{width:24px;height:24px;}

/* Instagram */
.instagram .contact-icon-wrap svg{color:#bc1888;}
.instagram:hover .contact-icon-wrap{background:linear-gradient(135deg,var(--ig-start),var(--ig-end));border-color:transparent;}
.instagram:hover .contact-icon-wrap svg{color:white;}

/* WhatsApp */
.whatsapp .contact-icon-wrap svg{color:var(--wa);}
.whatsapp:hover .contact-icon-wrap{background:var(--wa);border-color:transparent;}
.whatsapp:hover .contact-icon-wrap svg{color:white;}

/* Email */
.email .contact-icon-wrap svg{color:#1a1a1a;}
.email:hover .contact-icon-wrap{background:linear-gradient(135deg,#1a1a1a,#333);border-color:transparent;}
.email:hover .contact-icon-wrap svg{color:white;}

.contact-info{flex:1;}
.contact-label{
    color:var(--text4);font-size:.72rem;font-weight:800;
    text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;
}
.contact-value{color:var(--navy);font-size:1rem;font-weight:800;}
.contact-desc{margin-top:3px;color:var(--text3);font-size:.81rem;}

.contact-arrow{
    color:var(--text4);font-size:1.1rem;transition:.22s ease;
}
.contact-item:hover .contact-arrow{transform:translateX(-4px);color:var(--navy);}

/* ══ FOOTER ══ */
.contact-footer{
    padding:22px 32px 28px;border-top:1px solid var(--border);
    text-align:center;background:var(--surface2);
}
.contact-footer p{color:var(--text3);line-height:1.9;font-size:.9rem;}
.contact-footer strong{color:var(--navy);}

.site-footer{
    text-align:center;padding:20px;
    color:var(--text3);border-top:1px solid var(--border);
    background:var(--surface);font-size:.85rem;
}

::-webkit-scrollbar{width:6px;}
::-webkit-scrollbar-track{background:var(--bg);}
::-webkit-scrollbar-thumb{background:var(--border2);border-radius:20px;}
::-webkit-scrollbar-thumb:hover{background:#1a1a1a;}

@media(max-width:640px){
    .navbar{height:auto;padding:14px 20px;flex-direction:column;gap:12px;}
    .hero-strip{padding:36px 20px 40px;}
    .main{padding:24px 18px 60px;}
    .contact-item{padding:18px 20px;}
    .card-header{padding:24px 20px 20px;}
    .contact-footer{padding:18px 20px 22px;}
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
        <a href="aboutus.php" class="nav-link">אודות</a>
        <span class="nav-sep">·</span>
        <a href="contact.php" class="nav-link active">צור קשר</a>
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
        <div class="hero-title">נשמח לשמוע ממך 👋</div>
        <p class="hero-sub">בחר את הדרך הנוחה לך ליצירת קשר — נחזור אליך בהקדם</p>
    </div>
</div>

<!-- ══ MAIN ══ -->
<div class="main">

    <div class="breadcrumb">
        <a href="index.php">🏠 דף הבית</a>
        <span class="breadcrumb-sep">›</span>
        <span>צור קשר</span>
    </div>

    <div class="contact-card">
        <div class="card-top-bar"></div>

        <div class="card-header">
            <div class="card-header-icon">📬</div>
            <div class="card-header-title">יצירת קשר עם SkillHub</div>
            <p class="card-header-sub">זמיני בימים א׳–ה׳ בין 9:00–18:00<br>נשתדל לחזור תוך 24 שעות</p>
        </div>

        <div class="contact-list">

            <!-- Instagram -->
            <a href="https://www.instagram.com/<?= $contact['instagram'] ?>"
               target="_blank" rel="noopener"
               class="contact-item instagram">
                <div class="contact-icon-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
                        <circle cx="12" cy="12" r="4"/>
                        <circle cx="17.5" cy="6.5" r=".8" fill="currentColor" stroke="none"/>
                    </svg>
                </div>
                <div class="contact-info">
                    <div class="contact-label">Instagram</div>
                    <div class="contact-value">@<?= $contact['instagram'] ?></div>
                    <div class="contact-desc">שלח הודעה ישירה או עקוב אחרינו</div>
                </div>
                <span class="contact-arrow">←</span>
            </a>

            <!-- WhatsApp -->
            <a href="https://wa.me/<?= $contact['whatsapp'] ?>?text=<?= urlencode('שלום, פניתי דרך אתר SkillHub') ?>"
               target="_blank" rel="noopener"
               class="contact-item whatsapp">
                <div class="contact-icon-wrap">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                </div>
                <div class="contact-info">
                    <div class="contact-label">WhatsApp</div>
                    <div class="contact-value"><?= $contact['whatsapp_display'] ?></div>
                    <div class="contact-desc">שלח הודעה — נחזור אליך מהר</div>
                </div>
                <span class="contact-arrow">←</span>
            </a>

            <!-- Email -->
            <a href="mailto:<?= $contact['email'] ?>?subject=<?= urlencode($contact['email_subject']) ?>"
               class="contact-item email">
                <div class="contact-icon-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="4" width="20" height="16" rx="3"/>
                        <polyline points="2,4 12,13 22,4"/>
                    </svg>
                </div>
                <div class="contact-info">
                    <div class="contact-label">דואר אלקטרוני</div>
                    <div class="contact-value"><?= $contact['email'] ?></div>
                    <div class="contact-desc">לפניות מפורטות — נחזור תוך 24 שעות</div>
                </div>
                <span class="contact-arrow">←</span>
            </a>

        </div>

        <div class="contact-footer">
            <p>אנחנו ב-<strong>SkillHub</strong> כאן בשבילך.<br>כל שאלה, הצעה או משוב — נשמח לשמוע!</p>
        </div>
    </div>

</div><!-- /main -->

<footer class="site-footer">
    &copy; 2026 SkillHub — כל הזכויות שמורות
</footer>

</body>
</html>