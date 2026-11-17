<?php
session_start();

if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['id'];
$conn   = new mysqli('localhost', 'root', '', 'skillhub');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$type = $_GET['type'] ?? '';
$id   = intval($_GET['id'] ?? 0);

if (!in_array($type, ['workshop','event']) || $id <= 0) {
    header("Location: index.php");
    exit();
}

$isAdmin = 0;
$su = $conn->prepare("SELECT isAdmin FROM users WHERE id = ?");
if ($su) { $su->bind_param("i",$userId); $su->execute(); $r=$su->get_result(); if($r->num_rows===1) $isAdmin=$r->fetch_assoc()['isAdmin']; $su->close(); }
if ($isAdmin != 0) { header("Location: index.php"); exit(); }

$hasCard = false;
$checkCard = $conn->prepare("SELECT 1 FROM user_cards WHERE user_id = ?");
if ($checkCard) { $checkCard->bind_param("i",$userId); $checkCard->execute(); $checkCard->store_result(); if($checkCard->num_rows>0) $hasCard=true; $checkCard->close(); }

$userPoints = 0;
$sp = $conn->prepare("SELECT IFNULL(point_num,0) AS pts FROM points WHERE user_id=?");
if ($sp) { $sp->bind_param("i",$userId); $sp->execute(); $r=$sp->get_result(); if($r->num_rows===1) $userPoints=$r->fetch_assoc()['pts']; $sp->close(); }

$item = null; $itemName = ''; $itemDate = ''; $itemTime = ''; $itemPrice = 0; $lecturer = '';
$registerUrl = ''; $registerIdName = '';

if ($type === 'workshop') {
    $sq = $conn->prepare("SELECT w.wsnum,w.WorkshopName,w.date,w.time,w.price,w.maxParticipants,u.name AS ln,u.lastname AS ll FROM workshop w JOIN users u ON w.lecId=u.id WHERE w.wsnum=?");
    if ($sq) { $sq->bind_param("i",$id); $sq->execute(); $r=$sq->get_result(); if($r->num_rows===1) $item=$r->fetch_assoc(); $sq->close(); }
    if (!$item) { header("Location: index.php"); exit(); }
    $itemName=$item['WorkshopName']; $itemDate=$item['date']; $itemTime=substr($item['time'],0,5); $itemPrice=(float)$item['price']; $lecturer=$item['ln'].' '.$item['ll'];
    $registerUrl="register_workshop.php"; $registerIdName="workshop_id";
    $ck=$conn->prepare("SELECT 1 FROM workshop_registrations WHERE user_id=? AND workshop_id=?");
    if ($ck){$ck->bind_param("ii",$userId,$id);$ck->execute();$ck->store_result();if($ck->num_rows>0){$_SESSION['registration_message']="כבר נרשמת לסדנה זו.";$_SESSION['registration_success']=false;header("Location: index.php");exit();}$ck->close();}
    $cc=$conn->prepare("SELECT COUNT(*) AS cnt FROM workshop_registrations WHERE workshop_id=?");
    if ($cc){$cc->bind_param("i",$id);$cc->execute();$r=$cc->get_result();if($r->fetch_assoc()['cnt']>=$item['maxParticipants']){$_SESSION['registration_message']="הסדנה מלאה.";$_SESSION['registration_success']=false;header("Location: index.php");exit();}$cc->close();}
} else {
    $sq=$conn->prepare("SELECT e.id,e.eventName,e.eventDate,e.eventTime,e.maxParticipants,e.price,t.typeName,u.name AS ln,u.lastname AS ll FROM events e JOIN types t ON e.typeId=t.typeId JOIN users u ON e.lecturerId=u.id WHERE e.id=?");
    if ($sq){$sq->bind_param("i",$id);$sq->execute();$r=$sq->get_result();if($r->num_rows===1) $item=$r->fetch_assoc();$sq->close();}
    if (!$item){header("Location: index.php");exit();}
    $itemName=$item['eventName'];$itemDate=$item['eventDate'];$itemTime=substr($item['eventTime'],0,5);$itemPrice=(float)($item['price']??0);$lecturer=$item['ln'].' '.$item['ll'];
    $registerUrl="register_event.php";$registerIdName="event_id";
    $ck=$conn->prepare("SELECT 1 FROM event_registrations WHERE user_id=? AND event_id=?");
    if ($ck){$ck->bind_param("ii",$userId,$id);$ck->execute();$ck->store_result();if($ck->num_rows>0){$_SESSION['registration_message']="כבר נרשמת לאירוע זה.";$_SESSION['registration_success']=false;header("Location: index.php");exit();}$ck->close();}
    $cc=$conn->prepare("SELECT COUNT(*) AS cnt FROM event_registrations WHERE event_id=?");
    if ($cc){$cc->bind_param("i",$id);$cc->execute();$r=$cc->get_result();if($r->fetch_assoc()['cnt']>=$item['maxParticipants']){$_SESSION['registration_message']="האירוע מלא.";$_SESSION['registration_success']=false;header("Location: index.php");exit();}$cc->close();}
}

$conn->close();
$maxPointsUsable = min($userPoints, (int)$itemPrice);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>תשלום — SkillHub</title>
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
.nav-links{display:flex;align-items:center;gap:6px;}
.nav-link{padding:8px 14px;border-radius:var(--radius-sm);color:var(--text3);text-decoration:none;font-size:.88rem;font-weight:700;border:1px solid transparent;transition:.2s ease;}
.nav-link:hover{color:var(--navy);background:var(--surface3);border-color:var(--border);}
.nav-link.danger{color:var(--red);}
.nav-link.danger:hover{background:var(--red-bg);border-color:#fecaca;}
.nav-sep{color:var(--border2);font-size:.7rem;}

/* ══ MAIN ══ */
.page-main{flex:1;display:flex;justify-content:center;padding:44px 24px 72px;}
.checkout-wrap{width:100%;max-width:560px;}

/* ══ BREADCRUMB ══ */
.breadcrumb{display:flex;align-items:center;gap:8px;margin-bottom:22px;color:var(--text3);font-size:.84rem;}
.breadcrumb a{color:var(--text3);text-decoration:none;transition:.2s;}
.breadcrumb a:hover{color:var(--navy);}
.breadcrumb-sep{color:var(--border2);}

/* ══ CARD ══ */
.card{background:var(--surface);border-radius:20px;border:1px solid var(--border);box-shadow:var(--shadow-lg);overflow:hidden;}
.card-top-bar{height:4px;background:linear-gradient(90deg,#1a1a1a,#444,var(--green));}

/* ══ HEADER ══ */
.checkout-header{padding:28px 30px 22px;border-bottom:1px solid var(--border);background:var(--surface2);}
.checkout-title{font-family:'Frank Ruhl Libre',serif;font-size:1.8rem;font-weight:900;color:var(--navy);}

/* ══ ITEM SUMMARY ══ */
.item-summary{padding:24px 30px;border-bottom:1px solid var(--border);}
.item-name{font-family:'Frank Ruhl Libre',serif;font-size:1.35rem;font-weight:900;color:var(--navy);margin-bottom:14px;}
.item-meta{display:flex;flex-wrap:wrap;gap:8px;}
.item-meta-chip{
    display:inline-flex;align-items:center;gap:5px;padding:7px 12px;
    border-radius:var(--radius-sm);background:var(--surface2);border:1px solid var(--border);
    color:var(--text2);font-size:.8rem;font-weight:700;
}

/* ══ PRICE ══ */
.price-section{padding:22px 30px;border-bottom:1px solid var(--border);}
.price-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;color:var(--text3);font-size:.93rem;font-weight:700;}
.price-row.total{margin-top:6px;padding-top:14px;border-top:1px solid var(--border);color:var(--navy);font-size:1.05rem;font-weight:900;}
.price-val.discount{color:var(--green);font-weight:800;}

/* ══ POINTS BOX ══ */
.points-box{
    margin:20px 30px 0;padding:20px;border-radius:var(--radius-sm);
    background:var(--surface2);border:1px solid var(--border2);
}
.points-title{color:var(--navy);font-size:.9rem;font-weight:800;margin-bottom:12px;}
.points-range{width:100%;accent-color:#1a1a1a;cursor:pointer;height:4px;border-radius:2px;}
.points-stats{display:flex;justify-content:space-between;margin-top:10px;color:var(--text2);font-size:.82rem;font-weight:700;}
.points-stats span{color:var(--text3);}
.points-stats strong{color:var(--navy);}

/* ══ ACTION FOOTER ══ */
.action-footer{padding:22px 30px 28px;display:flex;flex-direction:column;gap:10px;}

.btn-pay,.btn-free,.btn-back{
    width:100%;padding:13px 20px;border-radius:var(--radius-sm);border:none;
    font-family:'Heebo',sans-serif;font-size:.98rem;font-weight:800;
    text-align:center;text-decoration:none;transition:.2s ease;cursor:pointer;
    display:block;
}
.btn-pay{color:#fff;background:linear-gradient(90deg,#1a1a1a,#333);box-shadow:0 4px 14px rgba(0,0,0,.2);}
.btn-pay:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,0,0,.28);}
.btn-pay.add-card{background:linear-gradient(90deg,#1a1a1a,#333);color:#fff;}
.btn-free{color:#fff;background:linear-gradient(90deg,var(--green),#22c55e);box-shadow:0 4px 14px rgba(22,163,74,.2);}
.btn-free:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(22,163,74,.3);}
.btn-back{color:var(--text2);background:var(--surface2);border:1px solid var(--border2);}
.btn-back:hover{background:var(--border);}

.warning-box{
    padding:12px 16px;border-radius:var(--radius-sm);
    background:var(--red-bg);border:1px solid #fecaca;
    color:var(--red);font-size:.85rem;font-weight:700;text-align:center;
}

.site-footer{text-align:center;padding:18px;color:var(--text3);border-top:1px solid var(--border);background:var(--surface);font-size:.84rem;}
::-webkit-scrollbar{width:6px;}::-webkit-scrollbar-track{background:var(--bg);}::-webkit-scrollbar-thumb{background:var(--border2);border-radius:20px;}::-webkit-scrollbar-thumb:hover{background:#1a1a1a;}

@media(max-width:580px){
    .navbar{height:auto;padding:12px 18px;flex-direction:column;gap:10px;}
    .page-main{padding:24px 16px 60px;}
    .checkout-header,.item-summary,.price-section,.action-footer{padding:20px 20px;}
    .points-box{margin:16px 20px 0;}
    .checkout-title{font-size:1.5rem;}
    .item-name{font-size:1.15rem;}
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
        <a href="index.php" class="nav-link">🏠 דף הבית</a>
        <span class="nav-sep">·</span>
        <a href="profile.php" class="nav-link">הפרופיל שלי</a>
        <span class="nav-sep">·</span>
        <a href="logout.php" class="nav-link danger">← התנתק</a>
    </div>
</nav>

<main class="page-main">
<div class="checkout-wrap">

    <div class="breadcrumb">
        <a href="index.php">🏠 דף הבית</a>
        <span class="breadcrumb-sep">›</span>
        <span>תשלום</span>
    </div>

    <div class="card">
        <div class="card-top-bar"></div>

        <!-- Header -->
        <div class="checkout-header">
            <div class="checkout-title"><?= $itemPrice > 0 ? '💳 סיכום הזמנה' : '🎉 הרשמה חינמית' ?></div>
        </div>

        <!-- Item summary -->
        <div class="item-summary">
            <div class="item-name"><?= htmlspecialchars($itemName) ?></div>
            <div class="item-meta">
                <span class="item-meta-chip">📆 <?= htmlspecialchars($itemDate) ?></span>
                <span class="item-meta-chip">🕐 <?= htmlspecialchars($itemTime) ?></span>
                <span class="item-meta-chip">👤 <?= htmlspecialchars($lecturer) ?></span>
            </div>
        </div>

        <?php if ($itemPrice > 0): ?>

        <!-- Price -->
        <div class="price-section">
            <div class="price-row">
                <span>מחיר מקורי</span>
                <span><?= number_format($itemPrice,2) ?> ₪</span>
            </div>
            <div class="price-row" id="discountRow" style="display:none">
                <span>הנחת נקודות</span>
                <span class="price-val discount" id="discountVal">−0 ₪</span>
            </div>
            <div class="price-row total">
                <span>סה"כ לתשלום</span>
                <span id="totalPrice"><?= number_format($itemPrice,2) ?> ₪</span>
            </div>
        </div>

        <?php if ($maxPointsUsable > 0): ?>
        <!-- Points slider -->
        <div class="points-box">
            <div class="points-title">🪙 השתמש בנקודות להנחה</div>
            <input type="range" class="points-range" id="pointsSlider"
                   min="0" max="<?= $maxPointsUsable ?>" step="1" value="0"
                   oninput="updatePoints(this.value)">
            <div class="points-stats">
                <span>נקודות: <strong id="pointsUsed">0</strong></span>
                <span>הנחה: <strong id="pointsDiscount">0 ₪</strong></span>
                <span>יתרה: <strong><?= $userPoints ?> נקודות</strong></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="action-footer">
            <?php if ($hasCard): ?>
                <form method="GET" action="<?= $registerUrl ?>" style="margin:0">
                    <input type="hidden" name="<?= $registerIdName ?>" value="<?= $id ?>">
                    <input type="hidden" name="points_used" id="pointsUsedInput" value="0">
                    <button type="submit" class="btn-pay" id="payBtn">
                        ✔ אשר תשלום — <?= number_format($itemPrice,2) ?> ₪
                    </button>
                </form>
            <?php else: ?>
                <div class="warning-box">⚠️ יש להוסיף כרטיס אשראי כדי להמשיך</div>
                <a href="profile.php?from=checkout&type=<?= $type ?>&id=<?= $id ?>" class="btn-pay add-card">💳 הוסף כרטיס אשראי</a>
            <?php endif; ?>
            <a href="index.php" class="btn-back">← ביטול וחזרה</a>
        </div>

        <?php else: ?>

        <!-- Free registration -->
        <div class="action-footer">
            <form method="GET" action="<?= $registerUrl ?>" style="margin:0">
                <input type="hidden" name="<?= $registerIdName ?>" value="<?= $id ?>">
                <input type="hidden" name="points_used" value="0">
                <button type="submit" class="btn-free">✔ הירשם בחינם</button>
            </form>
            <a href="index.php" class="btn-back">← חזרה</a>
        </div>

        <?php endif; ?>
    </div>

</div>
</main>

<footer class="site-footer">&copy; 2026 SkillHub — כל הזכויות שמורות</footer>

<script>
const originalPrice = <?= $itemPrice ?>;
function updatePoints(val) {
    val = parseInt(val) || 0;
    const total = Math.max(0, originalPrice - val);
    document.getElementById('pointsUsed').textContent = val;
    document.getElementById('pointsDiscount').textContent = val + ' ₪';
    document.getElementById('discountVal').textContent = '−' + val + ' ₪';
    document.getElementById('discountRow').style.display = val > 0 ? 'flex' : 'none';
    document.getElementById('totalPrice').textContent = total.toFixed(2) + ' ₪';
    const payBtn = document.getElementById('payBtn');
    if (payBtn) payBtn.textContent = '✔ אשר תשלום — ' + total.toFixed(2) + ' ₪';
    const inp = document.getElementById('pointsUsedInput');
    if (inp) inp.value = val;
}
</script>
</body>
</html>