<?php
session_start();

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'skillhub';

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// בדיקה אם המשתמש מחובר
if (isset($_SESSION['id'])) {
    $isLoggedIn = true;
} else {
    $isLoggedIn = false;
}

$isAdmin       = 0;
$userFirstName = 'אורח';
$userInterests = [];

// שמירת ה-ID של המשתמש
if ($isLoggedIn) {
    $userId = $_SESSION['id'];
} else {
    $userId = 0;
}

// שליפת פרטי המשתמש המחובר מהטבלה
if ($isLoggedIn) {
    $stmt = $conn->prepare("SELECT name, isAdmin, interests FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user          = $result->fetch_assoc();
            $isAdmin       = $user['isAdmin'];
            $userFirstName = htmlspecialchars($user['name']);

            // interests שמור כ-"1,3,5" — הופכים אותו למערך [1,3,5]
            if (!empty($user['interests'])) {
                $userInterests = explode(',', $user['interests']);
            } else {
                $userInterests = [];
            }
        }
        $stmt->close();
    }
}

// ✅ Events — רק עתידיים לפי תאריך + שעה
$events = [];
$re = $conn->query("
    SELECT e.id AS eventID, e.eventName, e.eventDate, e.eventTime,
           e.maxParticipants, e.price, t.typeName,
           u.name AS lecturerName, u.lastname AS lecturerLastname
    FROM events e
    JOIN types t ON e.typeId = t.typeId
    JOIN users u ON e.lecturerId = u.id
    WHERE CONCAT(e.eventDate, ' ', e.eventTime) > NOW()
    ORDER BY e.eventDate ASC, e.eventTime ASC
");
if ($re && $re->num_rows > 0) {
    while ($row = $re->fetch_assoc()) {
        $events[] = $row;
    }
}

// ספירת אירועים שעברו — לכפתור "היסטוריה"
$pastEventsCount = 0;
$rpe = $conn->query("
    SELECT COUNT(*) AS cnt
    FROM events
    WHERE eventDate < CURDATE()
       OR (eventDate = CURDATE() AND eventTime <= CURTIME())
");
if ($rpe) {
    $row = $rpe->fetch_assoc();
    $pastEventsCount = $row['cnt'];
}

// שליפת מספר משתתפים לכל אירוע (לחישוב מקומות פנויים)
$participantsCount = [];
if (!empty($events)) {
    // בניית רשימת ID-ים: [1, 3, 7, ...]
    $ids = [];
    foreach ($events as $e) {
        $ids[] = $e['eventID'];
    }

    // יצירת "?,?,?" לפי כמות האירועים
    $ph = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $conn->prepare("
        SELECT event_id, COUNT(user_id) AS count
        FROM event_registrations
        WHERE event_id IN ($ph)
        GROUP BY event_id
    ");
    if ($stmt) {
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
        $rc = $stmt->get_result();
        while ($row = $rc->fetch_assoc()) {
            $participantsCount[$row['event_id']] = $row['count'];
        }
        $stmt->close();
    }
}

// ✅ Workshops — רק עתידיות לפי תאריך + שעה
$workshops = [];
$rw = $conn->query("
    SELECT w.wsnum AS workshopID, w.WorkshopName, w.date, w.time,
           w.maxParticipants, w.price, w.img_path, w.description,
           IFNULL(t.typeName, w.Type) AS wsType,
           u.name AS lecturerName, u.lastname AS lecturerLastname
    FROM workshop w
    JOIN users u ON w.lecid = u.id
    LEFT JOIN types t ON t.typeId = w.Type
    WHERE CONCAT(w.date, ' ', w.time) > NOW()
    ORDER BY w.date ASC, w.time ASC
");
if ($rw && $rw->num_rows > 0) {
    while ($row = $rw->fetch_assoc()) {
        $workshops[] = $row;
    }
}

// ספירת סדנאות שעברו
$pastWorkshopsCount = 0;
$rpw = $conn->query("
    SELECT COUNT(*) AS cnt
    FROM workshop
    WHERE date < CURDATE()
       OR (date = CURDATE() AND IFNULL(time,'23:59:59') <= CURTIME())
");
if ($rpw) {
    $row = $rpw->fetch_assoc();
    $pastWorkshopsCount = $row['cnt'];
}

// ✅ שליפת כל הקטגוריות לפילטר סוג
// שליפת קטגוריות לתפריט הסינון
$workshop_types = [];
$rtype = $conn->query("SELECT typeId, typeName FROM types ORDER BY typeName ASC");
if ($rtype) {
    while ($row = $rtype->fetch_assoc()) {
        $workshop_types[] = $row;
    }
}

// שליפת מספר משתתפים לכל סדנה
$workshopParticipantsCount = [];
if (!empty($workshops)) {
    $ids = [];
    foreach ($workshops as $w) {
        $ids[] = $w['workshopID'];
    }

    $ph = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $conn->prepare("
        SELECT workshop_id, COUNT(user_id) AS count
        FROM workshop_registrations
        WHERE workshop_id IN ($ph)
        GROUP BY workshop_id
    ");
    if ($stmt) {
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
        $rc = $stmt->get_result();
        while ($row = $rc->fetch_assoc()) {
            $workshopParticipantsCount[$row['workshop_id']] = $row['count'];
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SkillHub — דף הבית</title>
<link rel="icon" type="image/png" href="images/skillhub2.jpeg">
<link href="https://fonts.googleapis.com/css2?family=Frank+Ruhl+Libre:wght@300;400;500;700;900&family=Heebo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}

:root{
    --navy:#1e2d4a;
    --navy2:#2d3e5a;
    --gold:#1a1a1a;
    --gold-light:#333333;
    --gold-pale:#f5f5f5;
    --gold-border:#d1d5db;
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
    --shadow-sm:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);
    --shadow:0 4px 16px rgba(0,0,0,.07),0 1px 3px rgba(0,0,0,.05);
    --shadow-md:0 8px 28px rgba(0,0,0,.09),0 2px 6px rgba(0,0,0,.05);
    --shadow-lg:0 20px 50px rgba(0,0,0,.11),0 4px 12px rgba(0,0,0,.06);
    --radius:16px;
    --radius-sm:10px;
}

html{scroll-behavior:smooth;}
body{font-family:'Heebo',sans-serif;direction:rtl;min-height:100vh;background:var(--bg);color:var(--text);}

/* ══ NAVBAR ══ */
.navbar{
    position:sticky;top:0;z-index:100;
    height:66px;padding:0 40px;
    display:flex;align-items:center;justify-content:space-between;
    background:rgba(255,255,255,.92);
    border-bottom:1px solid var(--border);
    backdrop-filter:blur(16px);
    box-shadow:var(--shadow-sm);
}
.nav-logo{
    font-family:'Frank Ruhl Libre',serif;
    font-size:1.65rem;font-weight:900;
    color:var(--navy);text-decoration:none;
    display:flex;align-items:center;gap:9px;
}
.nav-logo-dot{
    width:10px;height:10px;border-radius:50%;
    background:linear-gradient(135deg,#1a1a1a,#333333);
    box-shadow:0 0 0 4px rgba(0,0,0,.08);
}
.nav-links{display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
.nav-link{
    padding:8px 14px;border-radius:var(--radius-sm);
    color:var(--text3);text-decoration:none;
    font-size:.88rem;font-weight:700;
    border:1px solid transparent;transition:.2s ease;
}
.nav-link:hover{color:var(--navy);background:var(--surface3);border-color:var(--border);}
.nav-link.primary{
    color:#fff;
    background:linear-gradient(90deg,#1a1a1a,#333333);
    border-color:transparent;
    box-shadow:0 4px 14px rgba(0,0,0,.16);
}
.nav-link.primary:hover{box-shadow:0 6px 20px rgba(0,0,0,.22);transform:translateY(-1px);}
.nav-link.danger{color:var(--red);}
.nav-link.danger:hover{background:var(--red-bg);border-color:#fecaca;}
.nav-sep{color:var(--border2);font-size:.7rem;}

/* ══ HERO ══ */
.hero{
    position:relative;
    background:
        linear-gradient(rgba(10,15,30,.65), rgba(10,15,30,.70)),
        url('images/aboutus.jpeg') center/cover no-repeat;
    padding:70px 40px 80px;
    text-align:center;
    overflow:hidden;
}
.hero::before{
    content:"";position:absolute;inset:0;
    background:radial-gradient(circle at 20% 50%,rgba(0,0,0,.15),transparent 40%),
               radial-gradient(circle at 80% 30%,rgba(0,0,0,.12),transparent 35%);
    pointer-events:none;
}
.hero-inner{position:relative;z-index:2;max-width:700px;margin:0 auto;}
.hero-badge{
    display:inline-flex;align-items:center;gap:6px;
    padding:6px 14px;border-radius:999px;
    background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);
    color:#fff;font-size:.82rem;font-weight:700;
    margin-bottom:22px;
}
.hero-title{
    font-family:'Frank Ruhl Libre',serif;
    font-size:clamp(2.8rem,6vw,4.2rem);
    font-weight:900;color:#fff;
    line-height:1.06;letter-spacing:-1px;
    margin-bottom:16px;
    text-shadow:0 2px 12px rgba(0,0,0,.4);
}
.hero-title span{
    color:rgba(255,255,255,.55);
    background:none;
    -webkit-text-fill-color:unset;
}
.hero-welcome{color:rgba(255,255,255,.75);font-size:1.02rem;font-weight:600;margin-bottom:10px;}
.hero-sub{color:rgba(255,255,255,.7);font-size:1rem;line-height:1.8;max-width:520px;margin:0 auto;text-shadow:0 1px 6px rgba(0,0,0,.3);}

/* ── ROTATING SENTENCES ── */
.hero-rotating{
    min-height:3.6rem;
    max-width:520px;margin:0 auto;
    position:relative;
}
.hero-sentence{
    position:absolute;
    top:0;left:0;right:0;
    color:rgba(255,255,255,.85);
    font-size:1.05rem;line-height:1.8;
    text-align:center;
    font-weight:600;
    opacity:0;
    transform:translateY(16px);
    transition:opacity .5s ease, transform .5s ease;
    pointer-events:none;
    text-shadow:0 1px 8px rgba(0,0,0,.35);
}
.hero-sentence.active{
    opacity:1;
    transform:translateY(0);
    pointer-events:auto;
}
.hero-sentence.exit{
    opacity:0;
    transform:translateY(-16px);
    transition:opacity .4s ease, transform .4s ease;
}
.hero-cta{
    display:inline-flex;align-items:center;gap:8px;margin-top:28px;
    padding:13px 24px;border-radius:14px;text-decoration:none;
    font-weight:800;font-size:.95rem;
    color:#fff;
    background:linear-gradient(90deg,#1a1a1a,#333333);
    box-shadow:0 12px 30px rgba(0,0,0,.18);
    transition:.22s ease;
}
.hero-cta:hover{transform:translateY(-2px);box-shadow:0 18px 38px rgba(0,0,0,.26);}

/* ══ MAIN CONTENT ══ */
.main{max-width:1240px;margin:0 auto;padding:40px 28px 80px;}

/* ══ TOAST ══ */
.reg-msg{margin-bottom:24px;}
.reg-msg-inner{padding:14px 18px;border-radius:var(--radius-sm);font-weight:700;text-align:center;}
.reg-success{color:var(--green);background:var(--green-bg);border:1px solid var(--green-border);}
.reg-error{color:var(--red);background:var(--red-bg);border:1px solid #fecaca;}

/* ══ SECTION ══ */
.section-head{display:flex;align-items:center;gap:14px;margin-bottom:20px;}
.section-icon{
    width:48px;height:48px;border-radius:14px;
    display:flex;align-items:center;justify-content:center;font-size:1.3rem;
    background:linear-gradient(135deg,#1a1a1a,#333333);
    color:#fff;box-shadow:0 6px 18px rgba(0,0,0,.14);
    flex-shrink:0;
}
.section-title{font-family:'Frank Ruhl Libre',serif;font-size:1.9rem;color:var(--navy);font-weight:900;}
.section-subtitle{color:var(--text3);font-size:.88rem;margin-top:3px;}
.section-divider{height:2px;margin-bottom:22px;border-radius:2px;background:linear-gradient(90deg,var(--gold),rgba(0,0,0,.05),transparent);}
.section-spacer{margin-top:52px;}

/* ══ HISTORY BANNER ══ */
.history-banner{
    display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;
    margin-bottom:20px;padding:14px 18px;
    border-radius:var(--radius-sm);
    background:var(--gold-pale);border:1px solid var(--gold-border);
}
.history-banner-text{color:#1f2937;font-weight:600;font-size:.9rem;}
.history-banner-text strong{color:var(--navy);}
.history-link{
    text-decoration:none;color:#111827;font-weight:800;font-size:.86rem;
    padding:7px 14px;border-radius:8px;
    background:#f3f4f6;border:1px solid var(--gold-border);
    transition:.2s ease;white-space:nowrap;
}
.history-link:hover{background:#1a1a1a;color:#fff;border-color:#1a1a1a;}

/* ══ PERSONALIZED SECTION ══ */
.for-you-banner{
    display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;
    padding:14px 18px;border-radius:var(--radius-sm);
    background:linear-gradient(90deg,#eff6ff,#f0fdf4);
    border:1px solid #bfdbfe;margin-bottom:22px;
}
.for-you-text{font-size:.9rem;color:#1d4ed8;font-weight:700;}
.for-you-tags{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;}
.for-you-tag{
    display:inline-flex;padding:3px 10px;border-radius:999px;
    font-size:.72rem;font-weight:800;
    color:#111827;background:var(--gold-pale);border:1px solid var(--gold-border);
}
.for-you-edit{
    text-decoration:none;font-size:.8rem;font-weight:700;
    color:var(--text3);padding:6px 12px;border-radius:8px;
    background:var(--surface3);border:1px solid var(--border);
    transition:.2s ease;white-space:nowrap;
}
.for-you-edit:hover{color:var(--navy);background:var(--border);}
.personalized-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:18px;margin-bottom:28px;}
/* ══ COLLABORATIVE FILTERING ══ */
.cf-section{margin-bottom:52px;}
.cf-header-wrap{
    position:relative;overflow:hidden;
    border-radius:20px;padding:26px 28px 22px;margin-bottom:22px;
    background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 50%,#6366f1 100%);
    box-shadow:0 8px 28px rgba(99,102,241,.25);
}
.cf-header-wrap::before{
    content:"";position:absolute;inset:0;
    background:radial-gradient(circle at 10% 50%,rgba(255,255,255,.08),transparent 40%),
               radial-gradient(circle at 90% 20%,rgba(255,255,255,.05),transparent 35%);
    pointer-events:none;
}
.cf-header{position:relative;z-index:2;display:flex;align-items:center;gap:14px;}
.cf-icon{
    width:48px;height:48px;border-radius:14px;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;font-size:1.3rem;
    background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);
    backdrop-filter:blur(8px);
}
.cf-title{font-family:'Frank Ruhl Libre',serif;font-size:1.6rem;font-weight:900;color:#fff;letter-spacing:-.2px;}
.cf-subtitle{color:rgba(255,255,255,.65);font-size:.85rem;margin-top:3px;font-weight:400;}
.cf-badge{
    display:inline-flex;align-items:center;gap:4px;padding:2px 9px;
    border-radius:999px;font-size:.68rem;font-weight:800;letter-spacing:.4px;
    color:#fff;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.3);
    margin-left:8px;vertical-align:middle;
}

/* CF Cards Grid */
.cf-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(260px,1fr));
    gap:16px;
}
.cf-card{
    background:#fff;border-radius:16px;
    border:1px solid #e2e8f0;
    box-shadow:0 2px 8px rgba(0,0,0,.06),0 0 0 0 rgba(99,102,241,0);
    overflow:hidden;display:flex;flex-direction:column;
    transition:.22s ease;position:relative;
}
.cf-card:hover{
    transform:translateY(-4px);
    box-shadow:0 12px 32px rgba(99,102,241,.14),0 2px 8px rgba(0,0,0,.08);
    border-color:#c7d2fe;
}
.cf-card-accent{height:3px;width:100%;}
.cf-card-accent.event{background:linear-gradient(90deg,#6366f1,#8b5cf6);}
.cf-card-accent.workshop{background:linear-gradient(90deg,#7c3aed,#a78bfa);}

.cf-card-body{padding:18px 18px 14px;flex:1;}
.cf-card-type{
    display:inline-flex;align-items:center;gap:5px;
    padding:3px 10px;border-radius:999px;margin-bottom:10px;
    font-size:.7rem;font-weight:800;letter-spacing:.3px;
}
.cf-card-type.event{color:#4f46e5;background:#eef2ff;border:1px solid #c7d2fe;}
.cf-card-type.workshop{color:#7c3aed;background:#f5f3ff;border:1px solid #ddd6fe;}

.cf-card-name{
    font-family:'Frank Ruhl Libre',serif;font-size:1.05rem;font-weight:900;
    color:#0f172a;line-height:1.3;margin-bottom:12px;
}
.cf-card-meta{display:flex;flex-direction:column;gap:5px;}
.cf-card-meta-row{
    display:flex;align-items:center;gap:6px;
    font-size:.8rem;color:#64748b;font-weight:500;
}
.cf-card-meta-row strong{color:#334155;font-weight:700;}

.cf-sim-bar{
    display:flex;align-items:center;gap:6px;
    margin-top:12px;padding:7px 10px;border-radius:8px;
    background:#eef2ff;border:1px solid #e0e7ff;
}
.cf-sim-text{font-size:.75rem;font-weight:700;color:#4f46e5;}

.cf-card-footer{
    display:flex;align-items:center;justify-content:space-between;
    padding:12px 18px 16px;border-top:1px solid #f1f5f9;
    background:#fafbff;gap:8px;
}
.cf-btn-details{
    padding:7px 13px;border-radius:8px;text-decoration:none;
    font-size:.78rem;font-weight:700;
    color:#64748b;background:#f1f5f9;border:1px solid #e2e8f0;
    transition:.2s ease;
}
.cf-btn-details:hover{background:#e2e8f0;color:#0f172a;}
.cf-btn-register{
    padding:7px 16px;border-radius:8px;text-decoration:none;
    font-size:.78rem;font-weight:800;
    color:#fff;background:linear-gradient(90deg,#6366f1,#7c3aed);
    box-shadow:0 3px 10px rgba(99,102,241,.3);transition:.2s ease;
}
.cf-btn-register:hover{box-shadow:0 5px 16px rgba(99,102,241,.4);transform:translateY(-1px);}

.cf-notice{
    text-align:center;padding:22px;border-radius:12px;
    background:#fafafa;border:1.5px dashed #c7d2fe;
    color:#64748b;font-size:.88rem;
}

@media(max-width:640px){
    .cf-grid{grid-template-columns:1fr;}
    .cf-header-wrap{padding:20px;}
    .cf-title{font-size:1.3rem;}
}

.pers-card{
    background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);
    box-shadow:var(--shadow);overflow:hidden;display:flex;flex-direction:column;
    transition:.2s ease;border-right:4px solid var(--gold);
}
.pers-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-md);}
.pers-card-body{padding:16px 18px;flex:1;}
.pers-type{font-size:.7rem;font-weight:800;color:#111827;background:var(--gold-pale);border:1px solid var(--gold-border);display:inline-flex;padding:2px 9px;border-radius:999px;margin-bottom:8px;}
.pers-name{font-family:'Frank Ruhl Libre',serif;font-size:1.1rem;font-weight:900;color:var(--navy);margin-bottom:8px;line-height:1.3;}
.pers-detail{font-size:.8rem;color:var(--text3);margin-bottom:3px;}
.pers-detail strong{color:var(--text2);}
.pers-footer{padding:10px 18px;background:var(--surface2);border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:8px;}
.pers-badge{font-size:.72rem;font-weight:700;}
.pers-badge.event{color:#1d4ed8;}
.pers-badge.ws{color:var(--green);}

/* ══ DATE FILTER ══ */
.date-filter-wrap{
    display:flex;align-items:center;gap:10px;flex-wrap:wrap;
    margin-bottom:14px;padding:12px 16px;
    border-radius:var(--radius-sm);
    background:var(--surface2);border:1px solid var(--border);
}
.date-filter-wrap label{font-size:.8rem;font-weight:700;color:var(--text3);white-space:nowrap;}
.date-filter-input{
    padding:8px 12px;border-radius:8px;
    border:1.5px solid var(--border2);background:#fff;color:var(--text);
    font-family:'Heebo',sans-serif;font-size:.85rem;outline:none;transition:.2s ease;
}
.date-filter-input:focus{border-color:#1a1a1a;box-shadow:0 0 0 3px rgba(0,0,0,.08);}
.date-filter-clear{
    padding:7px 13px;border-radius:8px;border:1.5px solid var(--border2);
    background:var(--surface);color:var(--text3);font-family:'Heebo',sans-serif;
    font-size:.8rem;font-weight:700;cursor:pointer;transition:.2s ease;white-space:nowrap;
}
.date-filter-clear:hover{background:var(--red-bg);border-color:#fecaca;color:var(--red);}

/* ══ SEARCH ══ */
.search-wrap{display:flex;align-items:center;gap:10px;margin-bottom:16px;}
.search-input{
    flex:1;padding:11px 16px;border-radius:var(--radius-sm);
    border:1.5px solid var(--border2);background:#fff;
    color:var(--text);font-family:'Heebo',sans-serif;font-size:.92rem;
    outline:none;transition:.2s ease;direction:rtl;
}
.search-input:focus{border-color:#1a1a1a;box-shadow:0 0 0 3px rgba(217,119,6,.1);}
.search-input::placeholder{color:var(--text4);}
.search-clear{
    padding:10px 14px;border-radius:var(--radius-sm);
    border:1.5px solid var(--border2);background:#fff;
    color:var(--text3);font-family:'Heebo',sans-serif;font-size:.84rem;
    font-weight:700;cursor:pointer;transition:.2s ease;display:none;white-space:nowrap;
}
.search-clear:hover{background:var(--red-bg);border-color:#fecaca;color:var(--red);}
.search-count{font-size:.82rem;color:var(--text3);margin-bottom:14px;display:none;}
.search-count strong{color:#1a1a1a;}
.no-results{
    text-align:center;padding:40px 20px;border-radius:var(--radius);
    background:var(--surface);border:1px solid var(--border);
    color:var(--text3);font-weight:600;display:none;
}
.no-results-icon{font-size:2.2rem;display:block;margin-bottom:10px;opacity:.5;}

/* ══ EVENT CARDS ══ */
.events-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:18px;}
.event-card{
    background:var(--surface);border-radius:var(--radius);
    border:1px solid var(--border);box-shadow:var(--shadow);
    overflow:hidden;display:flex;flex-direction:column;
    transition:.22s ease;
}
.event-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-md);border-color:var(--border2);}
.event-card-top{padding:20px 20px 0;}
.event-type-tag{
    display:inline-flex;padding:4px 10px;border-radius:999px;
    margin-bottom:12px;font-size:.72rem;font-weight:800;
    color:#111827;background:#f3f4f6;border:1px solid var(--gold-border);
    letter-spacing:.3px;
}
.event-name{
    font-family:'Frank Ruhl Libre',serif;color:var(--navy);
    font-size:1.25rem;font-weight:900;line-height:1.3;margin-bottom:16px;
}
.event-details{display:flex;flex-direction:column;gap:8px;margin-bottom:16px;}
.event-detail{display:flex;align-items:center;gap:10px;color:var(--text3);font-size:.86rem;}
.event-detail-ico{
    width:30px;height:30px;border-radius:8px;flex-shrink:0;
    background:var(--surface2);border:1px solid var(--border);
    display:flex;align-items:center;justify-content:center;font-size:.9rem;
}
.event-detail strong{color:var(--text2);}
.event-footer{
    margin-top:auto;padding:14px 20px;
    border-top:1px solid var(--border);background:var(--surface2);
    display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;
}
.spots-info{font-size:.82rem;color:var(--text3);}
.spots-info strong{color:var(--navy);}
.spots-info.full{color:var(--red);font-weight:700;}
.event-actions{display:flex;gap:7px;flex-wrap:wrap;}

/* ══ BUTTONS ══ */
.btn,.ws-btn{
    display:inline-flex;align-items:center;justify-content:center;gap:5px;
    text-decoration:none;border:0;cursor:pointer;
    font-family:'Heebo',sans-serif;font-weight:700;font-size:.83rem;
    padding:8px 14px;border-radius:9px;transition:.2s ease;white-space:nowrap;
}
.btn-register,.ws-btn-register{
    color:#fff;background:linear-gradient(90deg,#1a1a1a,#333333);
    box-shadow:0 4px 12px rgba(0,0,0,.13);
}
.btn-register:hover,.ws-btn-register:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(0,0,0,.20);}
.btn-login,.ws-btn-login{
    color:var(--navy);background:var(--surface3);border:1px solid var(--border2);
}
.btn-login:hover,.ws-btn-login:hover{background:var(--border);}
.btn-full,.ws-btn-full{
    color:var(--text4);background:var(--surface3);
    border:1px solid var(--border);cursor:not-allowed;
}
.btn-details,.ws-btn-details{
    color:var(--text2);background:#fff;border:1.5px solid var(--border2);
}
.btn-details:hover,.ws-btn-details:hover{
    background:var(--gold-pale);border-color:#d1d5db;color:#111827;
}

/* ══ WORKSHOPS TABLE ══ */
.table-card{
    background:var(--surface);border-radius:var(--radius);
    border:1px solid var(--border);box-shadow:var(--shadow);
    overflow:hidden;
}
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;min-width:760px;}
thead tr{background:linear-gradient(180deg,var(--surface2),#edf2f7);}
th{
    padding:12px 16px;text-align:right;
    color:var(--text3);font-size:.74rem;font-weight:800;
    border-bottom:2px solid var(--border);
    letter-spacing:.5px;text-transform:uppercase;white-space:nowrap;
}
td{
    padding:14px 16px;color:var(--text2);
    border-bottom:1px solid #f1f5f9;font-size:.88rem;vertical-align:middle;
}
tbody tr:last-child td{border-bottom:none;}
tbody tr{transition:.15s ease;}
tbody tr:hover{background:#f8fafc;}
.ws-name{font-family:'Frank Ruhl Libre',serif;font-size:1rem;font-weight:800;color:var(--navy);}
.ws-type-tag{display:inline-flex;padding:3px 9px;border-radius:999px;font-size:.7rem;font-weight:800;color:#166534;background:#dcfce7;border:1px solid #86efac;white-space:nowrap;}
.ws-price{
    display:inline-flex;padding:3px 9px;border-radius:6px;
    color:#166534;background:#dcfce7;border:1px solid #86efac;
    font-weight:800;font-size:.82rem;
}
.ws-spots-ok{color:var(--green);font-weight:700;font-size:.86rem;}
.ws-spots-full{color:var(--red);font-weight:700;font-size:.86rem;}
.td-actions{display:flex;gap:7px;flex-wrap:wrap;align-items:center;}

/* ══ EMPTY ══ */
.empty-state{
    text-align:center;padding:52px 24px;border-radius:var(--radius);
    background:var(--surface);border:1px solid var(--border);box-shadow:var(--shadow);
}
.empty-icon{font-size:2.8rem;opacity:.4;margin-bottom:12px;display:block;}
.empty-text{color:var(--text3);margin-bottom:18px;font-weight:700;}
.empty-history-link{
    display:inline-flex;text-decoration:none;
    color:#111827;font-weight:800;font-size:.88rem;
    padding:10px 18px;border-radius:10px;
    background:var(--gold-pale);border:1px solid var(--gold-border);
    transition:.2s ease;
}
.empty-history-link:hover{background:#1a1a1a;color:#fff;border-color:#1a1a1a;}

/* ══ DIVIDER ══ */
.page-divider{
    display:flex;align-items:center;gap:16px;
    margin:52px 0 0;
}
.page-divider::before,.page-divider::after{
    content:"";height:1px;flex:1;background:var(--border);
}
.page-divider-inner{
    color:var(--text3);font-weight:800;font-size:.8rem;
    letter-spacing:.8px;text-transform:uppercase;white-space:nowrap;
}

::-webkit-scrollbar{width:6px;height:6px;}
::-webkit-scrollbar-track{background:var(--bg);}
::-webkit-scrollbar-thumb{background:var(--border2);border-radius:20px;}
::-webkit-scrollbar-thumb:hover{background:#1a1a1a;}
.footer-space{height:60px;}

@media(max-width:850px){
    .navbar{height:auto;padding:14px 20px;flex-direction:column;gap:12px;}
    .nav-links{justify-content:center;}
    .hero{padding:50px 24px 60px;}
    .main{padding:28px 18px 60px;}
    .events-grid{grid-template-columns:1fr;}
    .section-title{font-size:1.6rem;}
}
@media(max-width:520px){
    .nav-link{padding:7px 10px;font-size:.82rem;}
    .hero-title{font-size:2.6rem;}
    .event-footer{flex-direction:column;align-items:flex-start;}
    .event-actions,.td-actions{width:100%;}
    .btn,.ws-btn{width:100%;justify-content:center;}
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
    <a href="index.php" class="nav-logo">
        <span class="nav-logo-dot"></span>SkillHub
    </a>
    <div class="nav-links">
        <a href="aboutus.php" class="nav-link">אודות</a>
        <span class="nav-sep">·</span>
        <a href="contact.php" class="nav-link">צור קשר</a>
        <span class="nav-sep">·</span>
        <?php if ($isLoggedIn): ?>
            <?php if ($isAdmin == 1): ?>
                <a href="admin.php" class="nav-link danger">ניהול מערכת</a><span class="nav-sep">·</span>
            <?php endif; ?>
            <?php if ($isAdmin == 0): ?>
                <a href="profile.php" class="nav-link">הפרופיל שלי</a><span class="nav-sep">·</span>
            <?php endif; ?>
            <?php if ($isAdmin == 2): ?>
                <a href="lec.php" class="nav-link">ניהול מרצה</a><span class="nav-sep">·</span>
            <?php endif; ?>
            <a href="logout.php" class="nav-link danger">← התנתק</a>
        <?php else: ?>
            <a href="login.php" class="nav-link">כניסה</a><span class="nav-sep">·</span>
            <a href="signUp.php" class="nav-link primary">הרשמה</a>
        <?php endif; ?>
    </div>
</nav>

<!-- ══ HERO ══ -->
<div class="hero">
    <div class="hero-inner">
        <?php if ($isLoggedIn): ?>
            <div class="hero-badge">👋 שלום, <?= $userFirstName ?>!</div>
        <?php else: ?>
            <div class="hero-badge">✦ ברוך הבא ל-SkillHub</div>
        <?php endif; ?>
        <h1 class="hero-title">Skill<span>Hub</span></h1>
        <div class="hero-rotating" id="heroRotating">
            <p class="hero-sentence active">הבית שלך ללמידה ופיתוח מקצועי 🚀</p>
            <p class="hero-sentence">גלה סדנאות ואירועים שיקדמו אותך קדימה ✦</p>
            <p class="hero-sentence">פתח כישורים חדשים עם המרצים הטובים ביותר 🎓</p>
            <p class="hero-sentence">הצטרף לקהילה של לומדים וצמח מקצועית 🌱</p>
            <p class="hero-sentence">כל הרשמה היא צעד אחד קדימה בקריירה שלך 💡</p>
        </div>
        <a href="aboutus.php" class="hero-cta">קרא עוד אודותינו ←</a>
    </div>
</div>

<!-- ══ MAIN ══ -->
<div class="main">

<?php if (isset($_SESSION['registration_message'])): ?>
<div class="reg-msg">
    <div class="reg-msg-inner <?= $_SESSION['registration_success'] ? 'reg-success' : 'reg-error' ?>">
        <?= $_SESSION['registration_message'] ?>
    </div>
</div>
<?php unset($_SESSION['registration_message'], $_SESSION['registration_success']); ?>
<?php endif; ?>

<?php
// ══ סקשן "עבורך" — מוצג רק למשתמשים מחוברים עם תחומי עניין ══
if ($isLoggedIn && !empty($userInterests) && $isAdmin == 0):
    // שליפת אירועים מתאימים לפי typeId
    // המרת תחומי העניין למספרים שלמים
    $intIds = [];
    foreach ($userInterests as $id) {
        $intIds[] = intval($id); // למספר שלם $id ממיר את intval (אבטח מידע)
    }
    //יצירת מחרוזת חדשה ph 
    // count($intIds)המערך מתחיל מאינדקס 0 ואורכו נקבע לפי 
    //"?,?,?,..." הופכת את המערך למחרוזת  implode ובגלל  ['?', '?', '?',...]
    $ph = implode(',', array_fill(0, count($intIds), '?'));  

    // שליפת אירועים שמתאימים לתחומי העניין ושלא נרשמנו אליהם
    $persEvents = []; //מערך ריק שיקבל את האירועים המותאמים אישית. 
    //מכין שאילתה בטוחה עם ? במקום הערכים האמיתיים. prepare היא מתודה של mysqli.
    $qpe = $conn->prepare("
        SELECT e.id AS eventID, e.eventName, e.eventDate, e.eventTime,
               e.price, t.typeName,
               u.name AS lecturerName, u.lastname AS lecturerLastname
        FROM events e
        JOIN types t ON e.typeId = t.typeId
        JOIN users u ON e.lecturerId = u.id
        WHERE e.typeId IN ($ph)
          AND CONCAT(e.eventDate,' ',e.eventTime) > NOW()
          AND e.id NOT IN (SELECT event_id FROM event_registrations WHERE user_id=?)
        ORDER BY e.eventDate ASC
        LIMIT 6
    ");
    if ($qpe) {// אם הייתה שגיאה בשאילתה, לא ממשיכים 
        $bindTypes = str_repeat('i', count($intIds)) . 'i'; // מחרוזת שכל הערכים הם מספרים
        //([$userId]) מאחד את שני המערכים קודם כל מערך ראשון ואז מערך שני 
        $bindVals  = array_merge($intIds, [$userId]);
        $qpe->bind_param($bindTypes, ...$bindVals); // ? ממלא את הערכים במקום 
        $qpe->execute(); // שולח שאילתה
        $rpe = $qpe->get_result(); // מקבל את התוצאה
        // עוברת שורה שורה על התוצאות ובונה מהן מערך
        while ($row = $rpe->fetch_assoc()) { // 
            $persEvents[] = $row; // מוסיף כל שורה למערך $persEvents — בסוף המערך מכיל את כל האירועים המותאמים.
        }
        $qpe->close();//סוגר את השאילתה ומנקה זיכרון.
    }

    // שליפת שמות הקטגוריות לתצוגה ("עבורך: AI, DevOps...")
    $selectedTypeNames = [];// מערך ריק שיקבל את שמות הקטגוריות — לדוגמה ["AI", "DevOps"].
    $qtn = $conn->prepare("SELECT typeName FROM types WHERE typeId IN ($ph)");
    if ($qtn) {
        $qtn->bind_param(str_repeat('i', count($intIds)), ...$intIds);
        $qtn->execute();
        $rtn = $qtn->get_result();
        while ($row = $rtn->fetch_assoc()) { // עובר שורה אחר שורה ומוסיף כל שם קטגוריה למערך:
            $selectedTypeNames[] = $row['typeName'];
        }
        $qtn->close();
    }

    // שליפת סדנאות מתאימות — משווה גם לפי typeId (מספר) וגם לפי שם הקטגוריה
    // שליפת סדנאות מתאימות לתחומי העניין
    $persWorkshops = []; //מערך ריק שיקבל את הסדנאות המותאמות אישית.
    $phw_id   = implode(',', array_fill(0, count($intIds), '?'));
    //"__none__" בדיקה אם יש שמות תחומי עניין יוצר מחרוזת ממערך אחרת יוצר מחרוזת עם ערך 
    $phw_name = !empty($selectedTypeNames)
        ? implode(',', array_fill(0, count($selectedTypeNames), '?'))
        : "'__none__'"; //כדי שהשאילתה לא תישבר
    $bindStr  = str_repeat('i', count($intIds)) . str_repeat('s', count($selectedTypeNames)) . 'i'; // (i=מספר,s= מחרוזת שמכילה את סוגי הנתונים של כל הפרמטרים (מחרוזת
    $bindVals = array_merge($intIds, $selectedTypeNames, [$userId]);//מחבר את כל הערכים לרשימה אחת לפי הסדר:
//CAST(t.typeId AS CHAR) = w.Type — משווה כאילו שניהם טקסט
//OR t.typeName = w.Type — או לפי שם הקטגוריה
//LEFT JOIN — כדי שסדנאות ללא קטגוריה לא יימחקו
    $qpw = $conn->prepare("
        SELECT w.wsnum AS workshopID, w.WorkshopName, w.date, w.time, w.price,
               IFNULL(t.typeName, w.Type) AS wsType,
               u.name AS lecturerName, u.lastname AS lecturerLastname
        FROM workshop w
        JOIN users u ON w.lecId = u.id
        LEFT JOIN types t ON CAST(t.typeId AS CHAR) = w.Type OR t.typeName = w.Type
        WHERE (w.Type IN ($phw_id) OR w.Type IN ($phw_name))
          AND CONCAT(w.date,' ',w.time) > NOW()
          AND w.wsnum NOT IN (SELECT workshop_id FROM workshop_registrations WHERE user_id=?)
        ORDER BY w.date ASC
        LIMIT 6
    ");
    //בדיקה כפולה — גם שהשאילתה הוכנה בהצלחה וגם שיש ערכים לשלוח.
    if ($qpw && !empty($bindVals)) {
        $qpw->bind_param($bindStr, ...$bindVals);
        $qpw->execute();
        $rpw = $qpw->get_result();
        while ($row = $rpw->fetch_assoc()) {
            $persWorkshops[] = $row;
        }
        $qpw->close();
    }
    //סופר כמה פריטים נמצאו בסך הכל. אם יוצא 0 — הסקשן "עבורך" לא מוצג בדף בכלל.
    $totalPers = count($persEvents) + count($persWorkshops);

    // ══ COLLABORATIVE FILTERING ══
    // מוצא משתמשים דומים (נרשמו לפחות ל-2 סדנאות/אירועים משותפים)
    $cfWorkshops = [];
    $cfEvents    = [];

    // רשימת סדנאות שהמשתמש כבר נרשם אליהן
    // שלב 1 ב-CF: מה המשתמש כבר נרשם אליו?
    $myWsIds = [];
    $rMy = $conn->query("SELECT workshop_id FROM workshop_registrations WHERE user_id=$userId");
    if ($rMy) {
        //כי fetch_assoc() מביאה שורה אחת בכל קריאה. ה-while ממשיך לקרוא שורות עד שנגמרות:
        while ($r = $rMy->fetch_assoc()) {
                    //בסוף המערך $myWsIds מכיל את כל מזהי הסדנאות שהמשתמש נרשם אליהן, ואז אפשר להשתמש בו בשאילתה הבאה עם NOT IN.
            $myWsIds[] = $r['workshop_id'];
        }
    }

    // רשימת אירועים שהמשתמש כבר נרשם אליהם
    $myEvIds = [];
    $rMyEv = $conn->query("SELECT event_id FROM event_registrations WHERE user_id=$userId");
    if ($rMyEv) {
        while ($r = $rMyEv->fetch_assoc()) {
            $myEvIds[] = $r['event_id'];
        }
    }   
    //על מנת לתת המלצות מותאמות נבצע אלגוריתם סינון שיתופי
    //https://he.wikipedia.org/wiki/%D7%A1%D7%99%D7%A0%D7%95%D7%9F_%D7%A9%D7%99%D7%AA%D7%95%D7%A4%D7%99
    if (!empty($myWsIds) || !empty($myEvIds)) {
        // מצא משתמשים שחולקים לפחות 2 הרשמות (סדנאות + אירועים)
        $unionParts = [];// נשמרים כאן
        if (!empty($myWsIds)) {
            $ph = implode(',', $myWsIds);// IMPLODE ממיר את המערך למחרוזת מופרדת בפסיקיםמםצלת את המערך דרך פסיק
            //שולפת את כל המשתמשים מטבלת ההרשמות של הסדנאות  שהמשתמש רשום אליהם 
            $unionParts[] = "SELECT user_id FROM workshop_registrations WHERE workshop_id IN ($ph) AND user_id != $userId";
        }
        if (!empty($myEvIds)) {
            $ph = implode(',', $myEvIds);
            $unionParts[] = "SELECT user_id FROM event_registrations WHERE event_id IN ($ph) AND user_id != $userId";
        }

        $unionSql = implode(' UNION ALL ', $unionParts); //מאחד את שתי התוצאות של השאילתות לאחת
    //מחפשים משתמשים דומים לפי הרשמות משותפות - לפחות 2
        //סופרת כמה פעמים הופיע המשתמש ואם יותר מ 2 שומרת אותו 
        $similarQuery = $conn->query("
            SELECT user_id, COUNT(*) AS shared
            FROM ($unionSql) AS combined
            GROUP BY user_id
            HAVING shared >= 2
            ORDER BY shared DESC
            LIMIT 5
        ");

        // fallback: אם אין משתמשים עם 2 משותפות — חפש עם 1
        if (!$similarQuery || $similarQuery->num_rows === 0) {
            $similarQuery = $conn->query("
                SELECT user_id, COUNT(*) AS shared
                FROM ($unionSql) AS combined
                GROUP BY user_id
                HAVING shared >= 1
                ORDER BY shared DESC
                LIMIT 5
            ");
        }
//מערך חדש

        $similarUsers = [];
        if ($similarQuery) {
            // הוסף את המשתמשים הדומים למערך
            while ($r = $similarQuery->fetch_assoc()) {
                $similarUsers[] = $r['user_id'];
            }
        }

        if (!empty($similarUsers)) {
            $phSim = implode(',', $similarUsers); // רשימת המשתמשים הדומים

            // סדנאות שמשתמשים דומים נרשמו אליהן — שהמשתמש הנוכחי עדיין לא נרשם
            // בדיקה אם המערך לא ריק ומספר הסדנה לא נמצא במחרוזת רשימת הסדנאות
            $excludeWs = !empty($myWsIds) ? 'AND w.wsnum NOT IN ('.implode(',',$myWsIds).')' : '';
                        //מריץ שאילתה למסד הנתונים. התוצאה נשמרת ב-$rCfWs.
            /*
            בוחר את הנתונים שרוצים להציג — מזהה הסדנה, שם, תאריך, שעה ומחיר.
            אם יש שם קטגוריה בטבלת types — תשתמש בו. אם לא — תשתמש ב-w.Type ישירות. התוצאה נקראת wsType.
            שולף את שם המרצה — פרטי ומשפחה — מטבלת users.
            סופר כמה מהמשתמשים הדומים נרשמו לאותה סדנה. ככל שהמספר גדול יותר — הסדנה פופולרית יותר בקרב המשתמשים הדומים לנו.
            מתחיל מטבלת ההרשמות לסדנאות — כי רוצים לדעת מה המשתמשים הדומים נרשמו.
            מחבר עם טבלת הסדנאות — כדי לקבל את פרטי הסדנה.
            מחבר עם טבלת המשתמשים — כדי לקבל את שם המרצה.
            מחבר עם טבלת הקטגוריות — LEFT JOIN כי יש סדנאות שאין להן קטגוריה ולא רוצים שיימחקו. OR כי הסוג יכול להיות שמור כמספר או כשם.
            מסנן רק הרשמות של המשתמשים הדומים שמצאנו קודם. $phSim הוא רשימת ה-IDs שלהם.
            רק סדנאות שעוד לא עברו — עתידיות בלבד.
            משתנה PHP שמכיל תנאי SQL — מסנן סדנאות שאנחנו כבר נרשמנו אליהן. ככה לא נראה המלצות על סדנאות שכבר ידועות לנו.
            מקבץ את כל השורות של אותה סדנה ביחד — כי כמה משתמשים דומים יכולים להיות רשומים לאותה סדנה.
            ממיין לפי simCount — הסדנה שהכי הרבה משתמשים דומים נרשמו אליה תוצג ראשונה.
            מציג מקסימום 6 המלצות בלבד.
            */
            $rCfWs = $conn->query("
                SELECT w.wsnum AS workshopID, w.WorkshopName, w.date, w.time, w.price,
                       IFNULL(t.typeName, w.Type) AS wsType,
                       u.name AS lecturerName, u.lastname AS lecturerLastname,
                       COUNT(wr.user_id) AS simCount
                FROM workshop_registrations wr
                JOIN workshop w ON wr.workshop_id = w.wsnum
                JOIN users u ON w.lecId = u.id
                LEFT JOIN types t ON t.typeId = w.Type OR t.typeName = w.Type
                WHERE wr.user_id IN ($phSim)
                  AND CONCAT(w.date,' ',w.time) > NOW()
                  $excludeWs
                GROUP BY w.wsnum
                ORDER BY simCount DESC
                LIMIT 6
            ");
            if ($rCfWs) {
                while ($r = $rCfWs->fetch_assoc()) {
                    $cfWorkshops[] = $r;
                }
            }

            // אירועים שמשתמשים דומים נרשמו אליהם — שהמשתמש עדיין לא נרשם
            $excludeEv = !empty($myEvIds) ? 'AND e.id NOT IN ('.implode(',',$myEvIds).')' : '';
            $rCfEv = $conn->query("
                SELECT e.id AS eventID, e.eventName, e.eventDate AS date, e.eventTime AS time,
                       t.typeName, u.name AS lecturerName, u.lastname AS lecturerLastname,
                       COUNT(er.user_id) AS simCount
                FROM event_registrations er
                JOIN events e ON er.event_id = e.id
                JOIN types t ON e.typeId = t.typeId
                JOIN users u ON e.lecturerId = u.id
                WHERE er.user_id IN ($phSim)
                  AND CONCAT(e.eventDate,' ',e.eventTime) > NOW()
                  $excludeEv
                GROUP BY e.id
                ORDER BY simCount DESC
                LIMIT 6
            ");
            if ($rCfEv) {
                while ($r = $rCfEv->fetch_assoc()) {
                    $cfEvents[] = $r;
                }
            }
        }
    }
    $totalCf = count($cfWorkshops) + count($cfEvents);
?>

<!-- ════ מותאם אישית ════ -->
<div class="for-you-banner">
    <div>
        <div class="for-you-text">✨ תוכן מותאם עבורך, <?= $userFirstName ?></div>
        <div class="for-you-tags">
            <?php foreach ($selectedTypeNames as $tn): ?>
                <span class="for-you-tag"><?= htmlspecialchars($tn) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <a href="profile.php#interests" class="for-you-edit">✏️ ערוך תחומים</a>
</div>

<?php if ($totalPers > 0): ?>
<div class="section-head" style="margin-top:0">
    <div class="section-icon">⭐</div>
    <div>
        <div class="section-title">עבורך</div>
        <div class="section-subtitle"><?= $totalPers ?> אירועים וסדנאות שעשויים לעניין אותך</div>
    </div>
</div>
<div class="section-divider"></div>

<div class="personalized-grid">
    <?php foreach ($persEvents as $ev): ?>
    <div class="pers-card">
        <div class="pers-card-body">
            <span class="pers-type"><?= htmlspecialchars($ev['typeName']) ?></span>
            <div class="pers-name"><?= htmlspecialchars($ev['eventName']) ?></div>
            <div class="pers-detail">📆 <strong><?= htmlspecialchars($ev['eventDate']) ?></strong> · 🕐 <?= htmlspecialchars(substr($ev['eventTime'],0,5)) ?></div>
            <div class="pers-detail">👤 <?= htmlspecialchars($ev['lecturerName'].' '.$ev['lecturerLastname']) ?></div>
            <div class="pers-detail">💰 <?= floatval($ev['price'])>0 ? number_format($ev['price'],2).' ₪' : 'חינמי' ?></div>
        </div>
        <div class="pers-footer">
            <span class="pers-badge event">📅 אירוע</span>
            <div style="display:flex;gap:7px;">
                <a href="event_details.php?event_id=<?= $ev['eventID'] ?>" class="btn btn-details">📄 פרטים</a>
                <?php if ($isAdmin == 0): ?>
                <a href="checkout.php?type=event&id=<?= $ev['eventID'] ?>" class="btn btn-register">הירשם ←</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php foreach ($persWorkshops as $ws): ?>
    <div class="pers-card" style="border-right-color:var(--green);">
        <div class="pers-card-body">
            <span class="pers-type" style="color:var(--green);background:var(--green-bg);border-color:var(--green-border);"><?= htmlspecialchars($ws['wsType'] ?? 'סדנה') ?></span>
            <div class="pers-name"><?= htmlspecialchars($ws['WorkshopName']) ?></div>
            <div class="pers-detail">📆 <strong><?= htmlspecialchars($ws['date']) ?></strong> · 🕐 <?= htmlspecialchars(substr($ws['time'],0,5)) ?></div>
            <div class="pers-detail">👤 <?= htmlspecialchars($ws['lecturerName'].' '.$ws['lecturerLastname']) ?></div>
            <div class="pers-detail">💰 <?= floatval($ws['price'])>0 ? number_format($ws['price'],2).' ₪' : 'חינמי' ?></div>
        </div>
        <div class="pers-footer">
            <span class="pers-badge ws">🎓 סדנה</span>
            <div style="display:flex;gap:7px;">
                <a href="workshop_details.php?wsnum=<?= $ws['workshopID'] ?>" class="btn btn-details">📄 פרטים</a>
                <?php if ($isAdmin == 0): ?>
                <a href="checkout.php?type=workshop&id=<?= $ws['workshopID'] ?>" class="btn btn-register">הירשם</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($isLoggedIn && $isAdmin == 0 && $totalCf > 0): ?>
<!-- ════ המלצות סינון שיתופי ════ -->
<div class="section-wrap cf-section">

    <div class="cf-header-wrap">
        <div class="cf-header">
            <div class="cf-icon">🤝</div>
            <div>
                <div class="cf-title">
                    <span class="cf-badge">✦ AI</span>משתמשים כמוך גם נרשמו ל...
                </div>
                <div class="cf-subtitle">המלצות אישיות מבוססות על הרשמות של משתמשים עם טעם דומה לשלך</div>
            </div>
        </div>
    </div>

    <div class="cf-grid">

        <?php foreach ($cfEvents as $ev): ?>
        <div class="cf-card">
            <div class="cf-card-accent event"></div>
            <div class="cf-card-body">
                <span class="cf-card-type event">📅 אירוע · <?= htmlspecialchars($ev['typeName']) ?></span>
                <div class="cf-card-name"><?= htmlspecialchars($ev['eventName']) ?></div>
                <div class="cf-card-meta">
                    <div class="cf-card-meta-row">📆 <strong><?= htmlspecialchars($ev['date']) ?></strong> &nbsp;·&nbsp; 🕐 <?= htmlspecialchars(substr($ev['time'],0,5)) ?></div>
                    <div class="cf-card-meta-row">👤 <?= htmlspecialchars($ev['lecturerName'].' '.$ev['lecturerLastname']) ?></div>
                </div>
                <div class="cf-sim-bar">
                    <span style="font-size:1rem;">🤝</span>
                    <span class="cf-sim-text"><?= $ev['simCount'] ?> משתמש<?= $ev['simCount']>1 ? 'ים' : '' ?> עם טעם דומה נרשמ<?= $ev['simCount']>1 ? 'ו' : '' ?> לזה</span>
                </div>
            </div>
            <div class="cf-card-footer">
                <a href="event_details.php?event_id=<?= $ev['eventID'] ?>" class="cf-btn-details">📄 פרטים</a>
                <a href="checkout.php?type=event&id=<?= $ev['eventID'] ?>" class="cf-btn-register">הירשם ←</a>
            </div>
        </div>
        <?php endforeach; ?>

        <?php foreach ($cfWorkshops as $ws): ?>
        <div class="cf-card">
            <div class="cf-card-accent workshop"></div>
            <div class="cf-card-body">
                <span class="cf-card-type workshop">🎓 סדנה · <?= htmlspecialchars($ws['wsType'] ?? 'כללי') ?></span>
                <div class="cf-card-name"><?= htmlspecialchars($ws['WorkshopName']) ?></div>
                <div class="cf-card-meta">
                    <div class="cf-card-meta-row">📆 <strong><?= htmlspecialchars($ws['date']) ?></strong> &nbsp;·&nbsp; 🕐 <?= htmlspecialchars(substr($ws['time'],0,5)) ?></div>
                    <div class="cf-card-meta-row">👤 <?= htmlspecialchars($ws['lecturerName'].' '.$ws['lecturerLastname']) ?></div>
                    <div class="cf-card-meta-row">💰 <strong><?= floatval($ws['price'])>0 ? number_format($ws['price'],2).' ₪' : 'חינמי' ?></strong></div>
                </div>
                <div class="cf-sim-bar">
                    <span style="font-size:1rem;">🤝</span>
                    <span class="cf-sim-text"><?= $ws['simCount'] ?> משתמש<?= $ws['simCount']>1 ? 'ים' : '' ?> עם טעם דומה נרשמ<?= $ws['simCount']>1 ? 'ו' : '' ?> לזה</span>
                </div>
            </div>
            <div class="cf-card-footer">
                <a href="workshop_details.php?wsnum=<?= $ws['workshopID'] ?>" class="cf-btn-details">📄 פרטים</a>
                <a href="checkout.php?type=workshop&id=<?= $ws['workshopID'] ?>" class="cf-btn-register">הירשם ←</a>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
</div>
<?php endif; ?>

<div class="page-divider" style="margin-bottom:28px;"><div class="page-divider-inner">כל האירועים והסדנאות</div></div>

<?php else: ?>
<div style="text-align:center;padding:20px;color:var(--text3);font-size:.9rem;margin-bottom:22px;">
    אין תוכן מתאים לתחומי העניין שלך כרגע — ראה את כל האירועים למטה
</div>
<?php endif; ?>
<?php endif; ?>

<!-- ═══ EVENTS ═══ -->
<div class="section-head">
    <div class="section-icon">📅</div>
    <div>
        <div class="section-title">אירועים קרובים</div>
        <div class="section-subtitle" id="evCountLabel"><?= count($events) ?> אירועים מתוכננים</div>
    </div>
</div>
<div class="section-divider"></div>

<?php if ($pastEventsCount > 0): ?>
<div class="history-banner">
    <div class="history-banner-text">🗂️ יש <strong><?= $pastEventsCount ?> אירועים</strong> שהסתיימו ועברו לארכיון</div>
    <a href="event_history.php" class="history-link">📜 צפה בהיסטוריה ←</a>
</div>
<?php endif; ?>

<?php if (!empty($events)): ?>
<div class="search-wrap">
    <input type="text" id="evSearch" class="search-input"
           placeholder="🔍 חפש אירוע לפי שם או מרצה..."
           oninput="applyEvFilters()">
    <button class="search-clear" id="evSearchClear" onclick="clearEvSearch()">✕ נקה</button>
</div>
<div class="date-filter-wrap">
    <label>📅 מתאריך:</label>
    <input type="date" id="evDateFrom" class="date-filter-input" onchange="applyEvFilters()">
    <label>עד:</label>
    <input type="date" id="evDateTo" class="date-filter-input" onchange="applyEvFilters()">
    <label>🏷️ סוג:</label>
    <select id="evTypeFilter" class="date-filter-input" onchange="applyEvFilters()" style="min-width:120px;">
        <option value="">הכל</option>
        <?php foreach ($workshop_types as $t): ?>
        <option value="<?= htmlspecialchars(mb_strtolower($t['typeName'])) ?>"><?= htmlspecialchars($t['typeName']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="date-filter-clear" onclick="clearEvFilters()">✕ נקה הכל</button>
</div>
<div class="search-count" id="evSearchCount">נמצאו <strong id="evSearchCountNum">0</strong> אירועים</div>

<div class="events-grid" id="eventsGrid">
    <?php foreach ($events as $i => $event):
        $cur    = $participantsCount[$event['eventID']] ?? 0;
        $left   = $event['maxParticipants'] - $cur;
        $isFull = $left <= 0;
    ?>
    <div class="event-card ev-card"
         data-name="<?= htmlspecialchars(mb_strtolower($event['eventName'])) ?>"
         data-type="<?= htmlspecialchars(mb_strtolower($event['typeName'])) ?>"
         data-lecturer="<?= htmlspecialchars(mb_strtolower($event['lecturerName'].' '.$event['lecturerLastname'])) ?>"
         data-date="<?= htmlspecialchars($event['eventDate']) ?>">
        <div class="event-card-top">
            <span class="event-type-tag"><?= htmlspecialchars($event['typeName']) ?></span>
            <div class="event-name"><?= htmlspecialchars($event['eventName']) ?></div>
            <div class="event-details">
                <div class="event-detail"><div class="event-detail-ico">📆</div><span>תאריך: <strong><?= htmlspecialchars($event['eventDate']) ?></strong></span></div>
                <div class="event-detail"><div class="event-detail-ico">🕐</div><span>שעה: <strong><?= htmlspecialchars(substr($event['eventTime'],0,5)) ?></strong></span></div>
                <div class="event-detail"><div class="event-detail-ico">👤</div><span>מרצה: <strong><?= htmlspecialchars($event['lecturerName'].' '.$event['lecturerLastname']) ?></strong></span></div>
                <div class="event-detail"><div class="event-detail-ico">💰</div><span>מחיר: <strong><?= floatval($event['price']) > 0 ? number_format($event['price'], 2).' ₪' : 'חינמי' ?></strong></span></div>
            </div>
        </div>
        <div class="event-footer">
            <?php if ($isFull): ?>
                <span class="spots-info full">🔴 האירוע מלא</span>
            <?php else: ?>
                <span class="spots-info">פנויים: <strong><?= $left ?></strong> / <?= $event['maxParticipants'] ?></span>
            <?php endif; ?>
            <div class="event-actions">
                <a href="event_details.php?event_id=<?= $event['eventID'] ?>" class="btn btn-details">📄 פרטים</a>
                <?php if ($isFull): ?>
                    <button class="btn btn-full" disabled>מלא</button>
                <?php elseif ($isLoggedIn && $isAdmin == 0): ?>
                    <a href="checkout.php?type=event&id=<?= $event['eventID'] ?>" class="btn btn-register">הירשם ←</a>
                <?php elseif (!$isLoggedIn): ?>
                    <a href="login.php" class="btn btn-login">התחבר</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="no-results" id="evNoResults">
    <span class="no-results-icon">🔍</span>
    לא נמצאו אירועים התואמים לחיפוש
</div>

<?php else: ?>
<div class="empty-state">
    <span class="empty-icon">📭</span>
    <div class="empty-text">אין אירועים מתוכננים כרגע</div>
    <?php if ($pastEventsCount > 0): ?>
        <a href="event_history.php" class="empty-history-link">📜 צפה באירועים שהסתיימו</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ═══ WORKSHOPS ═══ -->
<div class="page-divider section-spacer"><div class="page-divider-inner">📚 סדנאות</div></div>

<div class="section-head" style="margin-top:28px;">
    <div class="section-icon">🎓</div>
    <div>
        <div class="section-title">סדנאות זמינות</div>
        <div class="section-subtitle" id="wsCountLabel"><?= count($workshops) ?> סדנאות קרובות</div>
    </div>
</div>
<div class="section-divider"></div>

<?php if ($pastWorkshopsCount > 0): ?>
<div class="history-banner">
    <div class="history-banner-text">🗂️ יש <strong><?= $pastWorkshopsCount ?> סדנאות</strong> שהסתיימו ועברו לארכיון</div>
    <a href="Workshop_history.php" class="history-link">📜 צפה בהיסטוריה ←</a>
</div>
<?php endif; ?>

<?php if (!empty($workshops)): ?>
<div class="search-wrap">
    <input type="text" id="wsSearch" class="search-input"
           placeholder="🔍 חפש סדנה לפי שם, מרצה..."
           oninput="applyWsFilters()">
    <button class="search-clear" id="wsSearchClear" onclick="clearWsSearch()">✕ נקה</button>
</div>
<div class="date-filter-wrap">
    <label>📅 מתאריך:</label>
    <input type="date" id="wsDateFrom" class="date-filter-input" onchange="applyWsFilters()">
    <label>עד:</label>
    <input type="date" id="wsDateTo" class="date-filter-input" onchange="applyWsFilters()">
    <label>🏷️ סוג:</label>
    <select id="wsTypeFilter" class="date-filter-input" onchange="applyWsFilters()" style="min-width:120px;">
        <option value="">הכל</option>
        <?php foreach ($workshop_types as $t): ?>
        <option value="<?= htmlspecialchars(mb_strtolower($t['typeName'])) ?>"><?= htmlspecialchars($t['typeName']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="date-filter-clear" onclick="clearWsFilters()">✕ נקה הכל</button>
</div>
<div class="search-count" id="wsSearchCount">נמצאו <strong id="wsSearchCountNum">0</strong> סדנאות</div>

<div class="table-card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>שם הסדנה</th>
                    <th>סוג</th>
                    <th>מרצה</th>
                    <th>תאריך</th>
                    <th>שעה</th>
                    <th>מחיר</th>
                    <th>מקומות</th>
                    <th>פעולות</th>
                </tr>
            </thead>
            <tbody id="wsTableBody">
            <?php foreach ($workshops as $ws):
                $cur    = $workshopParticipantsCount[$ws['workshopID']] ?? 0;
                $left   = $ws['maxParticipants'] - $cur;
                $isFull = $left <= 0;
            ?>
            <tr class="ws-row"
                data-name="<?= htmlspecialchars(mb_strtolower($ws['WorkshopName'])) ?>"
                data-lecturer="<?= htmlspecialchars(mb_strtolower($ws['lecturerName'].' '.$ws['lecturerLastname'])) ?>"
                data-date="<?= htmlspecialchars($ws['date']) ?>"
                data-type="<?= htmlspecialchars(mb_strtolower($ws['wsType'] ?? '')) ?>">
                <td><span class="ws-name"><?= htmlspecialchars($ws['WorkshopName']) ?></span></td>
                <td><span class="ws-type-tag"><?= htmlspecialchars($ws['wsType'] ?? '—') ?></span></td>
                <td><?= htmlspecialchars($ws['lecturerName'].' '.$ws['lecturerLastname']) ?></td>
                <td><?= htmlspecialchars($ws['date']) ?></td>
                <td><?= htmlspecialchars(substr($ws['time'],0,5)) ?></td>
                <td><span class="ws-price"><?= number_format($ws['price'],2) ?> ₪</span></td>
                <td>
                    <?php if($isFull): ?>
                        <span class="ws-spots-full">מלא (<?= $ws['maxParticipants'] ?>)</span>
                    <?php else: ?>
                        <span class="ws-spots-ok"><?= $left ?> / <?= $ws['maxParticipants'] ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="td-actions">
                        <a href="workshop_details.php?wsnum=<?= $ws['workshopID'] ?>" class="ws-btn ws-btn-details">📄 פרטים</a>
                        <?php if ($isFull): ?>
                            <button class="ws-btn ws-btn-full" disabled>מלא</button>
                        <?php elseif ($isLoggedIn && $isAdmin == 0): ?>
                            <a href="checkout.php?type=workshop&id=<?= $ws['workshopID'] ?>" class="ws-btn ws-btn-register">הירשם</a>
                        <?php elseif (!$isLoggedIn): ?>
                            <a href="login.php" class="ws-btn ws-btn-login">התחבר</a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="no-results" id="wsNoResults">
    <span class="no-results-icon">🔍</span>
    לא נמצאו סדנאות התואמות לחיפוש
</div>

<?php else: ?>
<div class="empty-state">
    <span class="empty-icon">📭</span>
    <div class="empty-text">אין סדנאות זמינות כרגע</div>
    <?php if ($pastWorkshopsCount > 0): ?>
        <a href="Workshop_history.php" class="empty-history-link">📜 צפה בסדנאות שהסתיימו</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="footer-space"></div>
</div><!-- /main -->

<script>
// ── ROTATING HERO SENTENCES ──
// אנימציית משפטים מתחלפים ב-Hero — מחליף כל 3 שניות
(function() {
    var sentences = document.querySelectorAll('.hero-sentence');
    if (sentences.length < 2) {
        return;
    }
    var current = 0;

    function next() {
        var prev = current;
        current = (current + 1) % sentences.length;

        // מסתיר את המשפט הנוכחי
        sentences[prev].classList.remove('active');
        sentences[prev].classList.add('exit');

        // אחרי 450ms מציג את המשפט הבא
        setTimeout(function() {
            sentences[prev].classList.remove('exit');
            sentences[current].classList.add('active');
        }, 450);
    }

    setInterval(next, 3000);
})();

var totalEvents    = <?= count($events) ?>;
var totalWorkshops = <?= count($workshops) ?>;

/* ─── אירועים ─── */
// חיפוש וסינון אירועים בזמן אמת
function applyEvFilters() {
    // קבלת הערכים מהטופס
    //קורא את ערכי שדה החיפוש, תאריכים וסוג קטגוריה.
    var search   = document.getElementById('evSearch').value.trim().toLowerCase();
    var dateFrom = document.getElementById('evDateFrom').value;
    var dateTo   = document.getElementById('evDateTo').value;
    var typeVal  = document.getElementById('evTypeFilter').value;

    // האם יש פילטר פעיל?
    //שורה הזו בודקת האם המשתמש בכלל הזין משהו סינון, ומחזירה true/false בהתאם.
    var hasFilter = (search !== '' || dateFrom !== '' || dateTo !== '' || typeVal !== '');

    // הצגת כפתור "נקה" רק כשמקלידים
    document.getElementById('evSearchClear').style.display = (search !== '') ? 'block' : 'none';
    document.getElementById('evSearchCount').style.display = hasFilter ? 'block' : 'none';

    // עבור על כל כרטיסי האירועים
    //בוחר את כל כרטיסי האירועים ועובר עליהם אחד אחד.
    var cards   = document.querySelectorAll('.ev-card');//'.ev-card' מוצאיה את כל האלמנטים שמתאימים לתבניית חיפוש
    var visible = 0;

    for (var i = 0; i < cards.length; i++) {
        var card = cards[i];
//כל בדיקה מחזירה true או false. אם לא הוזן ערך — הבדיקה תמיד עוברת.
        // בדיקה 1: האם שם האירוע או המרצה מכיל את החיפוש?
        var nameOk     = card.dataset.name.includes(search);
        var lecturerOk = card.dataset.lecturer.includes(search);
        var textOk     = (search === '') || nameOk || lecturerOk;

        // בדיקה 2: האם הסוג מתאים?
        var typeOk = (typeVal === '') || card.dataset.type.includes(typeVal);

        // בדיקה 3: האם התאריך בטווח?
        var cardDate   = card.dataset.date;
        var dateFromOk = (dateFrom === '') || (cardDate >= dateFrom);
        var dateToOk   = (dateTo   === '') || (cardDate <= dateTo);
        var dateOk     = dateFromOk && dateToOk;

        // אם כל הבדיקות עברו — הצג, אחרת הסתר
        if (textOk && typeOk && dateOk) {
            card.style.display = '';
            visible++;
        } else {
            card.style.display = 'none';
        }
    }

    // עדכון מונה התוצאות
    //מציג כמה אירועים נמצאו.
    document.getElementById('evSearchCountNum').textContent = visible;

    // הצגת הודעת "לא נמצאו תוצאות" אם אין תוצאות
    var noRes = document.getElementById('evNoResults');
    if (noRes) {
        noRes.style.display = (hasFilter && visible === 0) ? 'block' : 'none';
    }

    var label = document.getElementById('evCountLabel');
    if (label) {
        if (hasFilter) {
            label.textContent = visible + ' אירועים נמצאו';
        } else {
            label.textContent = totalEvents + ' אירועים מתוכננים';
        }
    }
}

// ניקוי שדה החיפוש של האירועים
function clearEvSearch() {
    document.getElementById('evSearch').value = '';
    applyEvFilters();
    document.getElementById('evSearch').focus();//חוזרת את הפוקוס לשדה החיפוש לאחר הניקוי
}

// ניקוי כל הפילטרים של האירועים
function clearEvFilters() {//מעדכן את התוצאות לאחר הניקוי
    document.getElementById('evSearch').value     = '';
    document.getElementById('evDateFrom').value   = '';
    document.getElementById('evDateTo').value     = '';
    document.getElementById('evTypeFilter').value = '';
    applyEvFilters();
}

/* ─── סדנאות ─── */
// חיפוש וסינון סדנאות בזמן אמת
function applyWsFilters() {
    // קבלת הערכים מהטופס
    var search   = document.getElementById('wsSearch').value.trim().toLowerCase();
    var dateFrom = document.getElementById('wsDateFrom').value;
    var dateTo   = document.getElementById('wsDateTo').value;
    var typeVal  = document.getElementById('wsTypeFilter').value;

    var hasFilter = (search !== '' || dateFrom !== '' || dateTo !== '' || typeVal !== '');

    document.getElementById('wsSearchClear').style.display = (search !== '') ? 'block' : 'none';
    document.getElementById('wsSearchCount').style.display = hasFilter ? 'block' : 'none';

    // עבור על כל שורות טבלת הסדנאות
    var rows    = document.querySelectorAll('.ws-row');
    var visible = 0;

    for (var i = 0; i < rows.length; i++) {
        var row = rows[i];

        var nameOk     = row.dataset.name.includes(search);
        var lecturerOk = row.dataset.lecturer.includes(search);
        var textOk     = (search === '') || nameOk || lecturerOk;

        var typeOk = (typeVal === '') || row.dataset.type.includes(typeVal);

        var rowDate    = row.dataset.date;
        var dateFromOk = (dateFrom === '') || (rowDate >= dateFrom);
        var dateToOk   = (dateTo   === '') || (rowDate <= dateTo);
        var dateOk     = dateFromOk && dateToOk;

        if (textOk && typeOk && dateOk) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    }

    document.getElementById('wsSearchCountNum').textContent = visible;

    var noRes = document.getElementById('wsNoResults');
    if (noRes) {
        noRes.style.display = (hasFilter && visible === 0) ? 'block' : 'none';
    }

    var label = document.getElementById('wsCountLabel');
    if (label) {
        if (hasFilter) {
            label.textContent = visible + ' סדנאות נמצאו';
        } else {
            label.textContent = totalWorkshops + ' סדנאות קרובות';
        }
    }
}

// ניקוי שדה החיפוש של הסדנאות
function clearWsSearch() {
    document.getElementById('wsSearch').value = '';
    applyWsFilters();
    document.getElementById('wsSearch').focus();
}

// ניקוי כל הפילטרים של הסדנאות
function clearWsFilters() {
    document.getElementById('wsSearch').value     = '';
    document.getElementById('wsDateFrom').value   = '';
    document.getElementById('wsDateTo').value     = '';
    document.getElementById('wsTypeFilter').value = '';
    applyWsFilters();
}
</script>
</body>
</html>
<?php $conn->close(); ?>