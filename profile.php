<?php
session_start();

if (!isset($_SESSION['id'])) { header("Location: login.php"); exit(); }

$userId    = $_SESSION['id'];
$firstName = 'משתמש';

$conn = new mysqli('localhost', 'root', '', 'skillhub');
if ($conn->connect_error) { error_log($conn->connect_error); http_response_code(500); echo "שגיאה פנימית."; exit(); }

// ─── 🛠️ טיפול בטופס כרטיס אשראי ───
// ─── מחיקת כרטיס אשראי ───
if (isset($_POST['delete_card'])) {
    $del = $conn->prepare("DELETE FROM user_cards WHERE user_id = ?");
    if ($del) { $del->bind_param("i",$userId); $del->execute(); $del->close(); }
    $_SESSION['msg']      = "כרטיס האשראי הוסר בהצלחה.";
    $_SESSION['msg_type'] = "success";
    header("Location: profile.php");
    exit();
}

if (isset($_POST['save_card'])) {
    $card_num  = str_replace(' ', '', $_POST['card_number']);
    $last_four = substr($card_num, -4);
    $expiry    = htmlspecialchars($_POST['expiry']);
    $holder    = htmlspecialchars($_POST['holder_name']);
    $fromType  = $_POST['from_type'] ?? '';
    $fromId    = intval($_POST['from_id'] ?? 0);

    $check = $conn->prepare("SELECT id FROM user_cards WHERE user_id = ?");
    $check->bind_param("i", $userId);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows > 0) {
        $upd = $conn->prepare("UPDATE user_cards SET card_holder = ?, last_four = ?, expiry_date = ? WHERE user_id = ?");
        $upd->bind_param("sssi", $holder, $last_four, $expiry, $userId);
        $upd->execute();
    } else {
        $ins = $conn->prepare("INSERT INTO user_cards (user_id, card_holder, last_four, expiry_date) VALUES (?, ?, ?, ?)");
        $ins->bind_param("isss", $userId, $holder, $last_four, $expiry);
        $ins->execute();
    }

    $_SESSION['msg']      = "כרטיס האשראי (סיומת $last_four) עודכן בהצלחה.";
    $_SESSION['msg_type'] = "success";

    // ✅ החזר ל-checkout אם הגיע משם
    if (!empty($fromType) && $fromId > 0) {
        header("Location: checkout.php?type=$fromType&id=$fromId");
    } else {
        header("Location: profile.php");
    }
    exit();
}

// ─── 🛠️ ביטול הרשמה + הורדת נקודות ───
if (isset($_POST['cancel_reg'])) {
    $item_id = (int)$_POST['item_id'];
    $type    = $_POST['type'];
    
    $conn->begin_transaction();
    try {
        $q = $type === 'course'
            ? "DELETE FROM event_registrations WHERE user_id = ? AND event_id = ?"
            : "DELETE FROM workshop_registrations WHERE user_id = ? AND workshop_id = ?";
        
        $sd = $conn->prepare($q);
        $sd->bind_param("ii", $userId, $item_id);
        
        if ($sd->execute() && $sd->affected_rows > 0) {
            $sp = $conn->prepare("UPDATE points SET point_num = GREATEST(0, point_num - 10) WHERE user_id = ?");
            $sp->bind_param("i", $userId);
            $sp->execute();
            $sp->close();
            
            $conn->commit();
            $_SESSION['msg'] = "ההרשמה בוטלה בהצלחה. 10 נקודות הוסרו.";
            $_SESSION['msg_type'] = "success";
        } else {
            $conn->rollback();
        }
        $sd->close();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['msg'] = "שגיאה בביטול ההרשמה.";
        $_SESSION['msg_type'] = "danger";
    }
    header("Location: profile.php"); exit();
}

// ─── עדכון פרטי פרופיל ───
if (isset($_POST['update_profile'])) {
    $newName     = trim($_POST['name'] ?? '');
    $newLastname = trim($_POST['lastname'] ?? '');
    $newEmail    = trim($_POST['email'] ?? '');
    $newPhone    = trim($_POST['phone'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPwd  = $_POST['confirm_password'] ?? '';

    $msg = ['type'=>'error','text'=>''];
    $valid = true;

    if (empty($newName)) { $msg['text']="יש להזין שם פרטי."; $valid=false; }
    if ($valid && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) { $msg['text']="אימייל לא תקין."; $valid=false; }

    // בדיקת אימייל כפול
    if ($valid) {
        $ce = $conn->prepare("SELECT id FROM users WHERE email=? AND id!=?");
        if ($ce) { $ce->bind_param("si",$newEmail,$userId); $ce->execute(); $ce->store_result(); if($ce->num_rows>0){$msg['text']="האימייל כבר בשימוש.";$valid=false;} $ce->close(); }
    }

    // טיפול בסיסמה
    if ($valid && !empty($newPassword)) {
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/',$newPassword)){$msg['text']="הסיסמה חלשה — 8 תווים, אות גדולה ומספר.";$valid=false;}
        elseif ($newPassword !== $confirmPwd){$msg['text']="הסיסמאות אינן תואמות.";$valid=false;}
    }

    // טיפול בתמונה
    $avatarPath = null;
    if ($valid && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
        $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
        if (!in_array($_FILES['avatar']['type'],$allowed)){$msg['text']="סוג קובץ לא נתמך.";$valid=false;}
        elseif ($_FILES['avatar']['size'] > 2*1024*1024){$msg['text']="הקובץ גדול מ-2MB.";$valid=false;}
        else {
            $ext = pathinfo($_FILES['avatar']['name'],PATHINFO_EXTENSION);
            $avatarPath = 'images/avatars/user_'.$userId.'.'.$ext;
            if (!is_dir('images/avatars')) @mkdir('images/avatars',0755,true);
            if (!move_uploaded_file($_FILES['avatar']['tmp_name'],$avatarPath)){$msg['text']="שגיאה בשמירת התמונה.";$valid=false;}
        }
    }

    if ($valid) {
        if (!empty($newPassword)) {
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            if ($avatarPath) {
                $su=$conn->prepare("UPDATE users SET name=?,lastname=?,email=?,phone=?,password=?,avatar=? WHERE id=?");
                $su->bind_param("ssssssi",$newName,$newLastname,$newEmail,$newPhone,$hashed,$avatarPath,$userId);
            } else {
                $su=$conn->prepare("UPDATE users SET name=?,lastname=?,email=?,phone=?,password=? WHERE id=?");
                $su->bind_param("sssssi",$newName,$newLastname,$newEmail,$newPhone,$hashed,$userId);
            }
        } else {
            if ($avatarPath) {
                $su=$conn->prepare("UPDATE users SET name=?,lastname=?,email=?,phone=?,avatar=? WHERE id=?");
                $su->bind_param("sssssi",$newName,$newLastname,$newEmail,$newPhone,$avatarPath,$userId);
            } else {
                $su=$conn->prepare("UPDATE users SET name=?,lastname=?,email=?,phone=? WHERE id=?");
                $su->bind_param("ssssi",$newName,$newLastname,$newEmail,$newPhone,$userId);
            }
        }
        if (isset($su) && $su->execute()){$msg=['type'=>'success','text'=>"הפרופיל עודכן בהצלחה!"];}
        else{$msg=['type'=>'error','text'=>"שגיאה בעדכון."];}
        if (isset($su)) $su->close();
    }
    $_SESSION['msg']      = $msg['text'];
    $_SESSION['msg_type'] = $msg['type'];
    header("Location: profile.php"); exit();
}

// ─── נתוני משתמש + נקודות ───
$isAdmin = 0; $totalPoints = 0; $userFullData = [];
$s = $conn->prepare("SELECT u.name, u.lastname, u.email, u.phone, u.isAdmin, u.avatar, IFNULL(p.point_num,0) AS points FROM users u LEFT JOIN points p ON u.id=p.user_id WHERE u.id=?");
if ($s) { $s->bind_param("i",$userId); $s->execute(); $r=$s->get_result(); if($r->num_rows===1){$u=$r->fetch_assoc();$isAdmin=$u['isAdmin'];$firstName=htmlspecialchars($u['name']);$totalPoints=$u['points'];$userFullData=$u;}$s->close(); }

if ($isAdmin == 1) { header('Location: admin.php'); $conn->close(); exit(); }

// ─── פרטי כרטיס אשראי ───
$userCard = ['last_four' => '••••', 'expiry_date' => 'MM/YY', 'card_holder' => $firstName];
$scard = $conn->prepare("SELECT last_four, expiry_date, card_holder FROM user_cards WHERE user_id = ?");
if ($scard) { 
    $scard->bind_param("i", $userId); 
    $scard->execute(); 
    $rcard = $scard->get_result(); 
    if($rcard->num_rows > 0) { $userCard = $rcard->fetch_assoc(); } 
    $scard->close(); 
}

// ─── קורסים עתידיים רשומים ───
$registeredCourses = [];
$sc = $conn->prepare("
    SELECT e.id, e.eventName, e.eventDate, e.eventTime,
           u.name AS lecturerName, u.lastname AS lecturerLastname
    FROM event_registrations r
    JOIN events e ON r.event_id = e.id
    JOIN users u ON e.lecturerId = u.id
    WHERE r.user_id = ?
      AND CONCAT(e.eventDate, ' ', e.eventTime) > NOW()
    ORDER BY e.eventDate ASC, e.eventTime ASC
");
if ($sc) { $sc->bind_param("i",$userId); $sc->execute(); $rc=$sc->get_result(); while($row=$rc->fetch_assoc()) $registeredCourses[]=$row; $sc->close(); }

// ─── סדנאות עתידיות רשומות ───
$registeredWorkshops = [];
$sw = $conn->prepare("
    SELECT w.wsnum, w.WorkshopName, w.Date, w.Time,
           u.name AS lecturerName, u.lastname AS lecturerLastname
    FROM workshop_registrations wr
    JOIN workshop w ON wr.workshop_id = w.wsnum
    JOIN users u ON w.lecId = u.id
    WHERE wr.user_id = ?
      AND CONCAT(w.Date, ' ', w.Time) > NOW()
    ORDER BY w.Date ASC, w.Time ASC
");
if ($sw) { $sw->bind_param("i",$userId); $sw->execute(); $rw=$sw->get_result(); while($row=$rw->fetch_assoc()) $registeredWorkshops[]=$row; $sw->close(); }

// ─── היסטוריה — אירועים שהסתיימו ───
$historyEvents = [];
$she = $conn->prepare("
    SELECT e.id, e.eventName, e.eventDate, e.eventTime,
           t.typeName, u.name AS lecturerName, u.lastname AS lecturerLastname
    FROM event_registrations r
    JOIN events e ON r.event_id = e.id
    JOIN types t ON e.typeId = t.typeId
    JOIN users u ON e.lecturerId = u.id
    WHERE r.user_id = ?
      AND CONCAT(e.eventDate, ' ', e.eventTime) <= NOW()
    ORDER BY e.eventDate DESC, e.eventTime DESC
");
if ($she) { $she->bind_param("i",$userId); $she->execute(); $rhe=$she->get_result(); while($row=$rhe->fetch_assoc()) $historyEvents[]=$row; $she->close(); }

// ─── היסטוריה — סדנאות שהסתיימו ───
$historyWorkshops = [];
$shw = $conn->prepare("
    SELECT w.wsnum, w.WorkshopName, w.Date, w.Time,
           w.price, u.name AS lecturerName, u.lastname AS lecturerLastname
    FROM workshop_registrations wr
    JOIN workshop w ON wr.workshop_id = w.wsnum
    JOIN users u ON w.lecId = u.id
    WHERE wr.user_id = ?
      AND CONCAT(w.Date, ' ', w.Time) <= NOW()
    ORDER BY w.Date DESC, w.Time DESC
");
if ($shw) { $shw->bind_param("i",$userId); $shw->execute(); $rhw=$shw->get_result(); while($row=$rhw->fetch_assoc()) $historyWorkshops[]=$row; $shw->close(); }

$totalHistory = count($historyEvents) + count($historyWorkshops);

// ─── קטגוריות לבחירת תחומי עניין ───
$all_types = [];
$rt = $conn->query("SELECT typeId, typeName FROM types ORDER BY typeName ASC");
if ($rt) while ($row = $rt->fetch_assoc()) $all_types[] = $row;

// ─── שמירת תחומי עניין ───
if (isset($_POST['save_interests'])) {
    $interestsRaw = $_POST['interests'] ?? [];
    $interests = implode(',', array_map('intval', $interestsRaw));
    $si = $conn->prepare("UPDATE users SET interests = ? WHERE id = ?");
    if ($si) { $si->bind_param("si", $interests, $userId); $si->execute(); $si->close(); }
    $_SESSION['msg'] = "תחומי העניין עודכנו בהצלחה!";
    $_SESSION['msg_type'] = "success";
    header("Location: profile.php"); exit();
}

// ─── שליפת תחומי עניין קיימים ───
$userInterests = [];
$sint = $conn->prepare("SELECT interests FROM users WHERE id = ?");
if ($sint) { $sint->bind_param("i", $userId); $sint->execute(); $ri = $sint->get_result(); if ($ri->num_rows === 1) { $row = $ri->fetch_assoc(); if (!empty($row['interests'])) $userInterests = explode(',', $row['interests']); } $sint->close(); }
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>הפרופיל שלי — SkillHub</title>
<link rel="icon" type="image/png" href="images/skillhub2.jpeg">
<link href="https://fonts.googleapis.com/css2?family=Frank+Ruhl+Libre:wght@300;400;500;700;900&family=Heebo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}

:root{
    --navy:#1e2d4a;
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
    --blue:#3b82f6;
    --blue-bg:#eff6ff;
    --blue-border:#93c5fd;
    --shadow-sm:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);
    --shadow:0 4px 16px rgba(0,0,0,.07),0 1px 3px rgba(0,0,0,.05);
    --shadow-md:0 8px 28px rgba(0,0,0,.09),0 2px 6px rgba(0,0,0,.05);
    --radius:16px;
    --radius-sm:10px;
}

html{scroll-behavior:smooth;}
body{font-family:'Heebo',sans-serif;direction:rtl;min-height:100vh;background:var(--bg);color:var(--text);}

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
    background:linear-gradient(135deg,#1a1a1a,#333333);
    box-shadow:0 0 0 4px rgba(0,0,0,.08);
}
.nav-links{display:flex;align-items:center;gap:6px;}
.nav-link{
    padding:8px 14px;border-radius:var(--radius-sm);
    color:var(--text3);text-decoration:none;font-size:.88rem;font-weight:700;
    border:1px solid transparent;transition:.2s ease;
}
.nav-link:hover{color:var(--navy);background:var(--surface3);border-color:var(--border);}
.nav-link.danger{color:var(--red);}
.nav-link.danger:hover{background:var(--red-bg);border-color:#fecaca;}
.nav-sep{color:var(--border2);font-size:.7rem;}

/* ══ HERO STRIP ══ */
.hero-strip{
    background:
        linear-gradient(135deg,rgba(15,23,42,.80) 0%,rgba(30,45,74,.74) 100%),
        url('images/image.png') center/cover no-repeat;
    padding:40px 40px 44px;position:relative;overflow:hidden;
}
.hero-strip::before{
    content:"";position:absolute;inset:0;
    background:radial-gradient(circle at 15% 50%,rgba(217,119,6,.2),transparent 40%),
               radial-gradient(circle at 85% 20%,rgba(0,0,0,.07),transparent 35%);
    pointer-events:none;
}
.hero-inner{
    position:relative;z-index:2;max-width:900px;margin:0 auto;
    display:flex;align-items:center;gap:20px;
}
.hero-avatar{
    width:72px;height:72px;border-radius:50%;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;font-size:1.8rem;
    background:linear-gradient(135deg,#1a1a1a,#333333);
    color:#fff;box-shadow:0 8px 24px rgba(0,0,0,.30);
    overflow:hidden;border:3px solid rgba(255,255,255,.2);
}
.hero-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.hero-name{font-family:'Frank Ruhl Libre',serif;font-size:1.9rem;font-weight:900;color:#fff;line-height:1.1;}
.hero-role{color:rgba(255,255,255,.55);font-size:.92rem;margin-top:4px;font-weight:600;}

/* ══ TOAST ══ */
.toast-msg{
    position:fixed;top:76px;left:24px;z-index:1000;
    padding:13px 18px;border-radius:var(--radius-sm);
    font-weight:700;border:1px solid;box-shadow:var(--shadow-md);
}
.toast-success{background:var(--green-bg);border-color:var(--green-border);color:var(--green);}
.toast-danger{background:var(--red-bg);border-color:#fecaca;color:var(--red);}

/* ══ MAIN ══ */
.main{max-width:900px;margin:0 auto;padding:32px 28px 72px;}

/* ══ POINTS BANNER ══ */
.points-banner{
    display:flex;align-items:center;justify-content:space-between;gap:16px;
    margin-bottom:22px;padding:20px 26px;border-radius:var(--radius);
    background:linear-gradient(90deg,#1a1a1a,#333333);
    color:#fff;box-shadow:0 8px 24px rgba(0,0,0,.16);
    direction:ltr;
}
.points-label{font-size:.98rem;font-weight:900;direction:rtl;}
.points-value{font-size:2rem;font-weight:900;direction:rtl;}

/* ══ SECTION LABEL ══ */
.section-label{
    display:flex;align-items:center;gap:12px;
    margin:28px 0 14px;color:var(--text3);
    font-size:.74rem;font-weight:800;letter-spacing:.8px;text-transform:uppercase;
}
.section-label::before,.section-label::after{content:"";height:1px;flex:1;background:var(--border);}

/* ══ CARD ══ */
.card{
    background:var(--surface);border-radius:var(--radius);
    border:1px solid var(--border);box-shadow:var(--shadow);
    overflow:hidden;margin-bottom:16px;transition:.2s ease;
}
.card:hover{box-shadow:var(--shadow-md);}
.card-header{
    display:flex;align-items:center;gap:14px;
    padding:18px 20px;border-bottom:1px solid var(--border);
    background:var(--surface2);
}
.card-icon{
    width:44px;height:44px;border-radius:13px;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;font-size:1.2rem;
    background:linear-gradient(135deg,#1a1a1a,#333333);color:#fff;
}
.card-icon.blue{background:linear-gradient(135deg,var(--blue),#6366f1);}
.card-icon.green{background:linear-gradient(135deg,var(--green),#22c55e);}
.card-icon.hist{background:linear-gradient(135deg,#6366f1,#8b5cf6);}
.card-title{font-family:'Frank Ruhl Libre',serif;font-size:1.1rem;font-weight:900;color:var(--navy);}
.card-subtitle{color:var(--text3);font-size:.78rem;margin-top:2px;}
.card-body{padding:20px;}

/* ══ INFO ROWS ══ */
.info-row{
    display:flex;align-items:center;gap:12px;padding:11px 0;
    border-bottom:1px solid var(--border);
}
.info-row:last-child{border-bottom:none;}
.info-label{color:var(--text3);font-weight:700;min-width:120px;font-size:.86rem;}
.info-value{color:var(--text2);font-weight:700;font-size:.9rem;}

/* ══ CREDIT CARD ══ */
.credit-card-preview{
    position:relative;overflow:hidden;
    margin-bottom:16px;padding:22px;border-radius:14px;
    color:white;
    background:radial-gradient(circle at top right,rgba(0,0,0,.20),transparent 30%),
               linear-gradient(135deg,#1e2d4a,#101827);
    border:1px solid rgba(255,255,255,.1);
    box-shadow:var(--shadow-md);
}
.card-chip{width:44px;height:32px;border-radius:7px;margin-bottom:20px;background:linear-gradient(135deg,#d1d5db,#1a1a1a);}
.card-number-hidden{font-family:'Courier New',monospace;font-size:1.15rem;letter-spacing:2px;margin-bottom:14px;}
.card-meta{display:flex;justify-content:space-between;font-size:.72rem;opacity:.7;margin-bottom:4px;}
.card-values{display:flex;justify-content:space-between;font-weight:700;text-transform:uppercase;font-size:.9rem;}

.btn-update-card{
    display:flex;align-items:center;justify-content:center;gap:7px;
    width:100%;padding:11px;border:none;border-radius:var(--radius-sm);cursor:pointer;
    font-family:'Heebo',sans-serif;font-size:.9rem;font-weight:700;
    color:#111827;background:var(--gold-pale);border:1px solid var(--gold-border);
    transition:.2s ease;
}
.btn-update-card:hover{background:#1a1a1a;color:#fff;border-color:#1a1a1a;}

/* ══ QUICK LINKS ══ */
.quick-links{list-style:none;display:flex;flex-wrap:wrap;gap:9px;}
.quick-links a{
    display:inline-flex;align-items:center;gap:7px;
    padding:9px 16px;border-radius:var(--radius-sm);text-decoration:none;
    color:var(--text2);background:var(--surface2);border:1px solid var(--border);
    font-weight:700;font-size:.86rem;transition:.2s ease;
}
.quick-links a:hover{background:var(--gold-pale);border-color:#d1d5db;color:#111827;}
.quick-links a.link-danger{color:var(--red);}
.quick-links a.link-danger:hover{background:var(--red-bg);border-color:#fecaca;}

/* ══ REG LIST ══ */
.reg-list{list-style:none;}
.reg-item{
    display:flex;justify-content:space-between;align-items:center;
    gap:14px;flex-wrap:wrap;
    padding:14px 0;border-bottom:1px solid var(--border);
}
.reg-item:last-child{border-bottom:none;}
.reg-info{flex:1;min-width:220px;}
.reg-name{color:var(--navy);font-weight:900;font-size:.96rem;margin-bottom:3px;}
.reg-lecturer{color:var(--text3);font-size:.83rem;}
.reg-right{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}

.reg-date{
    display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:999px;
    font-size:.74rem;font-weight:800;white-space:nowrap;
    color:#111827;background:var(--gold-pale);border:1px solid var(--gold-border);
}
.badge-event{
    display:inline-flex;padding:4px 10px;border-radius:999px;
    font-size:.72rem;font-weight:800;
    color:#1d4ed8;background:var(--blue-bg);border:1px solid var(--blue-border);
}
.badge-workshop{
    display:inline-flex;padding:4px 10px;border-radius:999px;
    font-size:.72rem;font-weight:800;
    color:var(--green);background:var(--green-bg);border:1px solid var(--green-border);
}
.badge-done{
    display:inline-flex;padding:4px 10px;border-radius:999px;
    font-size:.72rem;font-weight:700;
    color:var(--text3);background:var(--surface2);border:1px solid var(--border);
}
.count-badge{
    display:inline-flex;align-items:center;justify-content:center;
    min-width:22px;height:22px;padding:0 6px;border-radius:999px;
    background:linear-gradient(90deg,#1a1a1a,#333333);
    color:#fff;font-size:.7rem;font-weight:900;
}

/* ══ BUTTONS ══ */
.btn-details{
    display:inline-flex;align-items:center;gap:5px;
    padding:7px 12px;border-radius:8px;text-decoration:none;
    font-size:.8rem;font-weight:700;border:none;cursor:pointer;
    color:#111827;background:var(--gold-pale);border:1px solid var(--gold-border);
    transition:.2s ease;
}
.btn-details:hover{background:#1a1a1a;color:#fff;border-color:#1a1a1a;}
.btn-cancel{
    display:inline-flex;align-items:center;gap:5px;
    padding:7px 12px;border-radius:8px;
    font-size:.8rem;font-weight:700;
    color:var(--red);background:var(--red-bg);border:1px solid #fecaca;
    cursor:pointer;font-family:'Heebo',sans-serif;transition:.2s ease;
}
.btn-cancel:hover{background:var(--red);color:#fff;border-color:var(--red);}
.cancel-wrap{display:flex;flex-direction:column;align-items:flex-end;gap:3px;}
.cancel-points-note{color:var(--red);font-size:.7rem;font-weight:700;}

/* ══ EMPTY ══ */
.empty-reg{
    text-align:center;padding:32px 20px;border-radius:var(--radius-sm);
    color:var(--text3);font-weight:600;
    background:var(--surface2);border:1px dashed var(--border2);
}
.empty-reg-icon{font-size:2rem;display:block;margin-bottom:8px;opacity:.4;}

/* ══ MODAL ══ */
#cardModal{
    display:none;position:fixed;inset:0;z-index:1000;
    align-items:center;justify-content:center;
    background:rgba(15,23,42,.5);backdrop-filter:blur(6px);
}
.modal-content{
    width:90%;max-width:440px;padding:28px;border-radius:var(--radius);
    background:var(--surface);border:1px solid var(--border);
    box-shadow:var(--shadow-md);
}
.modal-title{font-family:'Frank Ruhl Libre',serif;font-size:1.2rem;font-weight:900;color:var(--navy);margin-bottom:18px;}
.form-group{margin-bottom:14px;}
.form-group label{display:block;margin-bottom:6px;color:var(--text);font-size:.83rem;font-weight:700;}
.form-group input{
    width:100%;padding:10px 13px;border-radius:var(--radius-sm);
    border:1.5px solid var(--border2);background:#fff;color:var(--text);
    font-family:'Heebo',sans-serif;font-size:.9rem;outline:none;transition:.2s ease;
}
.form-group input:focus{border-color:#1a1a1a;box-shadow:0 0 0 3px rgba(217,119,6,.1);}
.form-row{display:flex;gap:12px;}
.form-row .form-group{flex:1;}
.modal-btns{display:flex;gap:10px;margin-top:18px;}
.btn-save{
    flex:1;padding:10px 16px;border:none;border-radius:var(--radius-sm);cursor:pointer;
    font-family:'Heebo',sans-serif;font-weight:700;font-size:.9rem;
    color:#fff;background:linear-gradient(90deg,#1a1a1a,#333333);
    box-shadow:0 4px 12px rgba(0,0,0,.14);transition:.2s ease;
}
.btn-save:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(0,0,0,.20);}
.btn-close-modal{
    flex:1;padding:10px 16px;border-radius:var(--radius-sm);cursor:pointer;
    font-family:'Heebo',sans-serif;font-weight:700;font-size:.9rem;
    color:var(--text3);background:var(--surface2);border:1px solid var(--border);transition:.2s ease;
}
.btn-close-modal:hover{background:var(--border);}

/* ══ AVATAR UPLOAD ══ */
.avatar-wrap{display:flex;align-items:center;gap:18px;margin-bottom:18px;}
.avatar-preview{
    width:80px;height:80px;border-radius:50%;flex-shrink:0;
    object-fit:cover;border:3px solid var(--border2);
    background:var(--surface2);
    display:flex;align-items:center;justify-content:center;
    font-size:2rem;overflow:hidden;
}
.avatar-preview img{width:100%;height:100%;object-fit:cover;}
.avatar-upload-btn{
    display:inline-flex;align-items:center;gap:6px;
    padding:8px 14px;border-radius:var(--radius-sm);cursor:pointer;
    font-family:'Heebo',sans-serif;font-size:.84rem;font-weight:700;
    color:var(--text2);background:var(--surface2);border:1.5px solid var(--border2);
    transition:.2s ease;
}
.avatar-upload-btn:hover{background:var(--border);color:var(--navy);}
.avatar-hint{font-size:.74rem;color:var(--text4);margin-top:4px;}

/* ══ EDIT PROFILE ══ */
.edit-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 16px;}
.pwd-section{margin-top:14px;padding-top:14px;border-top:1px solid var(--border);}
.pwd-section-label{font-size:.78rem;font-weight:800;color:var(--text3);margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px;}

/* ══ COLLAPSIBLE SECTIONS ══ */
.collapse-toggle{
    width:100%;display:flex;align-items:center;justify-content:space-between;
    padding:13px 20px;border:none;border-radius:var(--radius-sm);
    background:var(--surface2);border:1.5px solid var(--border2);
    font-family:'Heebo',sans-serif;font-size:.9rem;font-weight:700;
    color:var(--navy);cursor:pointer;transition:.2s ease;margin-bottom:14px;
}
.collapse-toggle:hover{background:var(--border);border-color:var(--border2);}
.collapse-toggle .toggle-arrow{transition:transform .3s ease;font-size:.75rem;color:var(--text3);}
.collapse-toggle.open .toggle-arrow{transform:rotate(180deg);}
.collapse-body{display:none;animation:fadeSlide .25s ease;}
.collapse-body.open{display:block;}
@keyframes fadeSlide{from{opacity:0;transform:translateY(-6px);}to{opacity:1;transform:translateY(0);}}

/* ══ INTERESTS FILTER ══ */
.interests-search{
    width:100%;padding:9px 14px;border-radius:var(--radius-sm);
    border:1.5px solid var(--border2);background:#fff;
    font-family:'Heebo',sans-serif;font-size:.88rem;outline:none;
    transition:.2s ease;margin-bottom:14px;
}
.interests-search:focus{border-color:#1a1a1a;box-shadow:0 0 0 3px rgba(0,0,0,.07);}

/* ══ INTERESTS GRID ══ */
.interests-box{
    max-height:220px;overflow-y:auto;
    border:1.5px solid var(--border2);border-radius:var(--radius-sm);
    padding:12px;margin-bottom:14px;background:#fafafa;
}
.interests-box::-webkit-scrollbar{width:5px;}
.interests-box::-webkit-scrollbar-track{background:#f1f5f9;border-radius:4px;}
.interests-box::-webkit-scrollbar-thumb{background:var(--border2);border-radius:4px;}
.interests-box::-webkit-scrollbar-thumb:hover{background:#1a1a1a;}
.interests-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:8px;}
.interest-chip{position:relative;}
.interest-chip input[type=checkbox]{position:absolute;opacity:0;width:0;height:0;}
.interest-chip label{
    display:flex;align-items:center;gap:6px;
    padding:8px 12px;border-radius:var(--radius-sm);
    border:1.5px solid var(--border);
    background:var(--surface2);
    color:var(--text3);font-size:.82rem;font-weight:700;
    cursor:pointer;transition:.2s ease;user-select:none;
}
.interest-chip label:hover{border-color:#d1d5db;color:#111827;background:var(--gold-pale);}
.interest-chip input:checked+label{
    border-color:#1a1a1a;background:var(--gold-pale);color:#111827;
    box-shadow:0 0 0 2px rgba(0,0,0,.07);
}
.interest-chip input:checked+label::after{content:"✓";margin-right:auto;font-weight:900;color:#1a1a1a;}
.interests-hint{font-size:.78rem;color:var(--text4);margin-bottom:14px;}
.btn-save-interests{
    padding:9px 20px;border:none;border-radius:var(--radius-sm);cursor:pointer;
    font-family:'Heebo',sans-serif;font-weight:700;font-size:.88rem;
    color:#111827;background:var(--gold-pale);border:1px solid var(--gold-border);
    transition:.2s ease;
}
.btn-save-interests:hover{background:#1a1a1a;color:#fff;border-color:#1a1a1a;}

::-webkit-scrollbar{width:6px;}
::-webkit-scrollbar-track{background:var(--bg);}
::-webkit-scrollbar-thumb{background:var(--border2);border-radius:20px;}
::-webkit-scrollbar-thumb:hover{background:#1a1a1a;}
.footer-space{height:40px;}

@media(max-width:760px){
    .navbar{height:auto;padding:14px 20px;flex-direction:column;gap:12px;}
    .hero-strip{padding:28px 20px 32px;}
    .main{padding:22px 18px 60px;}
    .points-banner{flex-direction:column;text-align:center;}
    .reg-item{flex-direction:column;align-items:flex-start;}
    .reg-right{width:100%;}
    .btn-details,.btn-cancel{width:100%;justify-content:center;}
    .cancel-wrap{width:100%;}
    .modal-btns,.form-row{flex-direction:column;}
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

<?php if (isset($_SESSION['msg'])): ?>
    <div class="toast-msg toast-<?= $_SESSION['msg_type'] ?>" id="toastMsg">
        <?= htmlspecialchars($_SESSION['msg']) ?>
    </div>
    <script>setTimeout(()=>{const t=document.getElementById('toastMsg');if(t){t.style.transition='opacity .4s';t.style.opacity='0';setTimeout(()=>t.remove(),400);}},4000);</script>
    <?php unset($_SESSION['msg'], $_SESSION['msg_type']); ?>
<?php endif; ?>

<!-- ══ NAVBAR ══ -->
<nav class="navbar">
    <a href="index.php" class="nav-logo"><span class="nav-logo-dot"></span>SkillHub</a>
    <div class="nav-links">
        <a href="index.php" class="nav-link">🏠 דף הבית</a>
        <span class="nav-sep">·</span>
        <a href="logout.php" class="nav-link danger">← התנתק</a>
    </div>
</nav>

<!-- ══ HERO STRIP ══ -->
<div class="hero-strip">
    <div class="hero-inner">
        <div class="hero-avatar">
            <?php
            $av = $userFullData['avatar'] ?? '';
            if (!empty($av) && file_exists($av)):
            ?>
                <img src="<?= htmlspecialchars($av) ?>?v=<?= time() ?>" alt="avatar">
            <?php else: ?>
                👤
            <?php endif; ?>
        </div>
        <div>
            <div class="hero-name"><?= $firstName ?></div>
            <div class="hero-role">פרופיל משתמש · SkillHub</div>
        </div>
    </div>
</div>

<!-- ══ MAIN ══ -->
<div class="main">

    <!-- Points banner -->
    <div class="points-banner">
        <span class="points-value">🪙 <?= number_format($totalPoints) ?></span>
        <span class="points-label">🏅 מאזן SkillPoints שלך</span>
    </div>

    <!-- עדכון פרופיל -->
    <div class="section-label">עדכון פרופיל</div>
    <div class="card">
        <div class="card-header">
            <div class="card-icon" style="background:linear-gradient(135deg,#3b82f6,#6366f1)">✏️</div>
            <div>
                <div class="card-title">עדכון פרטים אישיים</div>
                <div class="card-subtitle">שם, אימייל, טלפון, סיסמה ותמונה</div>
            </div>
        </div>
        <div class="card-body">
            <button type="button" class="collapse-toggle" id="editProfileToggle" onclick="toggleCollapse('editProfileBody','editProfileToggle')">
                <span>✏️ לחץ לעריכת הפרטים שלך</span>
                <span class="toggle-arrow">▼</span>
            </button>
            <div class="collapse-body" id="editProfileBody">
            <form method="POST" enctype="multipart/form-data">
                <!-- תמונת פרופיל -->
                <div class="avatar-wrap">
                    <div class="avatar-preview" id="avatarPreview">
                        <?php if (!empty($userFullData['avatar']) && file_exists($userFullData['avatar'])): ?>
                            <img src="<?= htmlspecialchars($userFullData['avatar']) ?>?v=<?= time() ?>" alt="avatar" id="avatarImg">
                        <?php else: ?>
                            <span id="avatarEmoji">👤</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="avatar-upload-btn" for="avatarFile">📷 בחר תמונה</label>
                        <input type="file" name="avatar" id="avatarFile" accept="image/*" style="display:none" onchange="previewAvatar(this)">
                        <div class="avatar-hint">JPG, PNG, GIF עד 2MB</div>
                    </div>
                </div>

                <!-- שם ושם משפחה -->
                <div class="edit-form-grid">
                    <div class="form-group">
                        <label class="form-label">שם פרטי</label>
                        <input type="text" name="name" class="form-input" value="<?= htmlspecialchars($userFullData['name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">שם משפחה</label>
                        <input type="text" name="lastname" class="form-input" value="<?= htmlspecialchars($userFullData['lastname'] ?? '') ?>">
                    </div>
                </div>

                <!-- אימייל וטלפון -->
                <div class="edit-form-grid">
                    <div class="form-group">
                        <label class="form-label">אימייל</label>
                        <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($userFullData['email'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">טלפון</label>
                        <input type="text" name="phone" class="form-input" value="<?= htmlspecialchars($userFullData['phone'] ?? '') ?>">
                    </div>
                </div>

                <!-- שינוי סיסמה -->
                <div class="pwd-section">
                    <div class="pwd-section-label">🔒 שינוי סיסמה (השאר ריק לא לשנות)</div>
                    <div class="edit-form-grid">
                        <div class="form-group">
                            <label class="form-label">סיסמה חדשה</label>
                            <input type="password" name="new_password" class="form-input" placeholder="לפחות 8 תווים, אות גדולה ומספר">
                        </div>
                        <div class="form-group">
                            <label class="form-label">אימות סיסמה</label>
                            <input type="password" name="confirm_password" class="form-input" placeholder="הזן שוב">
                        </div>
                    </div>
                </div>

                <button type="submit" name="update_profile" class="btn-save-interests" style="margin-top:6px;width:100%;padding:11px;font-size:.92rem;">💾 שמור שינויים</button>
            </form>
            </div><!-- /collapse-body -->
        </div>
    </div>

    <!-- מידע כללי -->
    <div class="section-label">מידע כללי</div>
    <div class="card">
        <div class="card-header">
            <div class="card-icon">ℹ️</div>
            <div>
                <div class="card-title">פרטי החשבון</div>
                <div class="card-subtitle">מידע אישי ונקודות</div>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($userFullData['avatar']) && file_exists($userFullData['avatar'])): ?>
            <div class="info-row" style="align-items:center;">
                <span class="info-label">תמונת פרופיל</span>
                <img src="<?= htmlspecialchars($userFullData['avatar']) ?>?v=<?= time() ?>"
                     alt="avatar"
                     style="width:90px;height:90px;border-radius:50%;object-fit:cover;border:3px solid var(--border2);box-shadow:var(--shadow-md);cursor:pointer;"
                     onclick="document.getElementById('avatarModal').style.display='flex'"
                     title="לחץ להגדלה">
            </div>
            <?php endif; ?>
            <div class="info-row"><span class="info-label">שם פרטי</span><span class="info-value"><?= $firstName ?></span></div>
            <div class="info-row"><span class="info-label">הרשאה</span><span class="info-value">משתמש רגיל</span></div>
            <div class="info-row">
                <span class="info-label">SkillPoints</span>
                <span class="info-value" style="color:#1a1a1a;font-weight:800;">🪙 <?= number_format($totalPoints) ?> נקודות</span>
            </div>
        </div>
    </div>

    <!-- כרטיס אשראי -->
    <?php if (!empty($_GET['from']) && $_GET['from']==='checkout' && !empty($_GET['type']) && !empty($_GET['id'])): ?>
    <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:var(--radius-sm);margin-bottom:12px;font-size:.88rem;font-weight:700;color:#4338ca;">
        💳 כדי להירשם ל<?= $_GET['type']==='workshop' ? 'סדנה' : 'אירוע' ?> יש להוסיף כרטיס אשראי — לאחר השמירה תועבר/י חזרה להרשמה.
    </div>
    <?php endif; ?>
    <div class="section-label">אמצעי תשלום</div>
    <div class="card" id="card-section">
        <div class="card-header">
            <div class="card-icon blue">💳</div>
            <div>
                <div class="card-title">כרטיס אשראי</div>
                <div class="card-subtitle">ניהול אמצעי תשלום לרכישת סדנאות</div>
            </div>
        </div>
        <div class="card-body">
            <div class="credit-card-preview">
                <div class="card-chip"></div>
                <div class="card-number-hidden">•••• •••• •••• <?= $userCard['last_four'] ?></div>
                <div class="card-meta"><span>CARD HOLDER</span><span>EXPIRES</span></div>
                <div class="card-values">
                    <span><?= htmlspecialchars($userCard['card_holder']) ?></span>
                    <span><?= htmlspecialchars($userCard['expiry_date']) ?></span>
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:4px;">
                <button onclick="document.getElementById('cardModal').style.display='flex'" class="btn-update-card" style="flex:1;">
                    ✏️ עדכון כרטיס
                </button>
                <form method="POST" style="margin:0;" onsubmit="return confirm('האם למחוק את כרטיס האשראי?')">
                    <button type="submit" name="delete_card"
                            style="padding:10px 16px;border-radius:var(--radius-sm);border:1.5px solid #fecaca;background:#fef2f2;color:#dc2626;font-family:Heebo,sans-serif;font-size:.85rem;font-weight:700;cursor:pointer;transition:.2s ease;white-space:nowrap;">
                        🗑️ מחק כרטיס
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- תחומי עניין -->
    <div class="section-label" id="interests">תחומי עניין</div>
    <div class="card" id="interests-card">
        <div class="card-header">
            <div class="card-icon" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">🎯</div>
            <div>
                <div class="card-title">תחומי העניין שלי</div>
                <div class="card-subtitle">נציג לך תוכן מותאם לפי הבחירה שלך</div>
            </div>
        </div>
        <div class="card-body">
            <button type="button" class="collapse-toggle" id="interestsToggle" onclick="toggleCollapse('interestsBody','interestsToggle')">
                <span>🎯 לחץ לבחירת תחומי עניין (<?= count($userInterests) ?> נבחרו)</span>
                <span class="toggle-arrow">▼</span>
            </button>
            <div class="collapse-body" id="interestsBody">
            <form method="POST">
                <input type="text" id="interestsSearch" class="interests-search"
                       placeholder="🔍 חפש תחום עניין..."
                       oninput="filterInterests(this.value)">
                <p class="interests-hint">בחר את הנושאים שמעניינים אותך — ניתן לשנות בכל עת</p>
                <div class="interests-box">
                <div class="interests-grid" id="interestsGrid">
                    <?php foreach ($all_types as $type): ?>
                    <div class="interest-chip" data-name="<?= htmlspecialchars(mb_strtolower($type['typeName'])) ?>">
                        <input type="checkbox" name="interests[]"
                               id="int_<?= $type['typeId'] ?>"
                               value="<?= $type['typeId'] ?>"
                               <?= in_array((string)$type['typeId'], $userInterests) ? 'checked' : '' ?>>
                        <label for="int_<?= $type['typeId'] ?>">🏷️ <?= htmlspecialchars($type['typeName']) ?></label>
                    </div>
                    <?php endforeach; ?>
                </div><!-- /interests-grid -->
                </div><!-- /interests-box -->
                <div id="noInterestsFound" style="display:none;padding:16px;text-align:center;color:var(--text3);font-size:.88rem;">לא נמצאו תחומים תואמים</div>
                <button type="submit" name="save_interests" class="btn-save-interests">💾 שמור תחומי עניין</button>
            </form>
            </div><!-- /collapse-body -->
        </div>
    </div>

    <!-- קישורים מהירים -->
    <div class="section-label">קישורים מהירים</div>
    <div class="card">
        <div class="card-body">
            <ul class="quick-links">
                <li><a href="index.php">🏠 דף הבית</a></li>
                <li><a href="#my-courses">📖 הקורסים שלי</a></li>
                <li><a href="#my-workshops">🎓 הסדנאות שלי</a></li>
                <li><a href="#my-history">📜 ההיסטוריה שלי</a></li>
                <li><a href="logout.php" class="link-danger">← התנתקות</a></li>
            </ul>
        </div>
    </div>

    <!-- קורסים עתידיים -->
    <div id="my-courses" class="section-label">הקורסים שלי</div>
    <div class="card">
        <div class="card-header">
            <div class="card-icon green">📖</div>
            <div>
                <div class="card-title">אירועים עתידיים <span class="count-badge"><?= count($registeredCourses) ?></span></div>
                <div class="card-subtitle">אירועים שנרשמת אליהם</div>
            </div>
        </div>
        <div class="card-body">
        <?php if (!empty($registeredCourses)): ?>
            <ul class="reg-list">
                <?php foreach ($registeredCourses as $i => $course): ?>
                <li class="reg-item">
                    <div class="reg-info">
                        <div class="reg-name"><?= htmlspecialchars($course['eventName']) ?></div>
                        <div class="reg-lecturer">מרצה: <?= htmlspecialchars($course['lecturerName'].' '.$course['lecturerLastname']) ?></div>
                    </div>
                    <div class="reg-right">
                        <span class="reg-date">📆 <?= htmlspecialchars($course['eventDate']) ?></span>
                        <a href="event_details.php?event_id=<?= $course['id'] ?>&from=profile" class="btn-details">📄 פרטים</a>
                        <form method="POST" style="margin:0" onsubmit="return confirm('לבטל הרשמה לקורס זה?\n⚠️ יורדו 10 SkillPoints.')">
                            <input type="hidden" name="type"     value="course">
                            <input type="hidden" name="item_id" value="<?= $course['id'] ?>">
                            <div class="cancel-wrap">
                                <button type="submit" name="cancel_reg" class="btn-cancel">✕ בטל</button>
                                <span class="cancel-points-note">−10 נקודות</span>
                            </div>
                        </form>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="empty-reg"><span class="empty-reg-icon">📭</span><div>אין אירועים עתידיים</div></div>
        <?php endif; ?>
        </div>
    </div>

    <!-- סדנאות עתידיות -->
    <div id="my-workshops" class="section-label">הסדנאות שלי</div>
    <div class="card">
        <div class="card-header">
            <div class="card-icon" style="background:linear-gradient(135deg,#0f5c35,#22c55e)">🎓</div>
            <div>
                <div class="card-title">סדנאות עתידיות <span class="count-badge"><?= count($registeredWorkshops) ?></span></div>
                <div class="card-subtitle">סדנאות שנרשמת אליהן</div>
            </div>
        </div>
        <div class="card-body">
        <?php if (!empty($registeredWorkshops)): ?>
            <ul class="reg-list">
                <?php foreach ($registeredWorkshops as $i => $ws): ?>
                <li class="reg-item">
                    <div class="reg-info">
                        <div class="reg-name"><?= htmlspecialchars($ws['WorkshopName']) ?></div>
                        <div class="reg-lecturer">מרצה: <?= htmlspecialchars($ws['lecturerName'].' '.$ws['lecturerLastname']) ?></div>
                    </div>
                    <div class="reg-right">
                        <span class="reg-date">📆 <?= htmlspecialchars($ws['Date']) ?></span>
                        <a href="workshop_details.php?wsnum=<?= $ws['wsnum'] ?>&from=profile" class="btn-details">📄 פרטים</a>
                        <form method="POST" style="margin:0" onsubmit="return confirm('לבטל הרשמה לסדנה זו?\n⚠️ יורדו 10 SkillPoints.')">
                            <input type="hidden" name="type"     value="workshop">
                            <input type="hidden" name="item_id" value="<?= $ws['wsnum'] ?>">
                            <div class="cancel-wrap">
                                <button type="submit" name="cancel_reg" class="btn-cancel">✕ בטל</button>
                                <span class="cancel-points-note">−10 נקודות</span>
                            </div>
                        </form>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="empty-reg"><span class="empty-reg-icon">📭</span><div>אין סדנאות עתידיות</div></div>
        <?php endif; ?>
        </div>
    </div>

    <!-- היסטוריה -->
    <div id="my-history" class="section-label">ההיסטוריה שלי</div>
    <div class="card">
        <div class="card-header">
            <div class="card-icon hist">📜</div>
            <div>
                <div class="card-title">אירועים וסדנאות שהסתיימו <span class="count-badge"><?= $totalHistory ?></span></div>
                <div class="card-subtitle">פעילות עבר</div>
            </div>
        </div>
        <div class="card-body">
        <?php if ($totalHistory > 0):
            $allHistory = [];
            foreach ($historyEvents as $ev) {
                $allHistory[] = [
                    'type'     => 'event',
                    'name'     => $ev['eventName'],
                    'lecturer' => $ev['lecturerName'].' '.$ev['lecturerLastname'],
                    'date'     => $ev['eventDate'],
                    'time'     => substr($ev['eventTime'],0,5),
                    'link'     => 'event_details.php?event_id='.$ev['id'].'&from=profile',
                ];
            }
            foreach ($historyWorkshops as $ws) {
                $allHistory[] = [
                    'type'     => 'workshop',
                    'name'     => $ws['WorkshopName'],
                    'lecturer' => $ws['lecturerName'].' '.$ws['lecturerLastname'],
                    'date'     => $ws['Date'],
                    'time'     => substr($ws['Time'],0,5),
                    'link'     => 'workshop_details.php?wsnum='.$ws['wsnum'].'&from=profile',
                ];
            }
            usort($allHistory, fn($a,$b) => strcmp($b['date'].' '.$b['time'], $a['date'].' '.$a['time']));
        ?>
            <ul class="reg-list">
                <?php foreach ($allHistory as $item): ?>
                <li class="reg-item" style="opacity:.85;">
                    <div class="reg-info">
                        <div class="reg-name" style="color:var(--text2);"><?= htmlspecialchars($item['name']) ?></div>
                        <div class="reg-lecturer" style="display:flex;align-items:center;gap:6px;margin-top:4px;">
                            <?php if ($item['type'] === 'event'): ?>
                                <span class="badge-event">📅 אירוע</span>
                            <?php else: ?>
                                <span class="badge-workshop">🎓 סדנה</span>
                            <?php endif; ?>
                            <span>מרצה: <?= htmlspecialchars($item['lecturer']) ?></span>
                        </div>
                    </div>
                    <div class="reg-right">
                        <span class="reg-date" style="background:var(--surface2);border-color:var(--border);color:var(--text3);">
                            📆 <?= htmlspecialchars($item['date']) ?>
                        </span>
                        <span class="badge-done">✓ הסתיים</span>
                        <a href="<?= $item['link'] ?>" class="btn-details">📄 פרטים</a>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="empty-reg"><span class="empty-reg-icon">📭</span><div>עדיין לא השתתפת באירועים או סדנאות</div></div>
        <?php endif; ?>
        </div>
    </div>

    <div class="footer-space"></div>
</div><!-- /main -->

<?php if (!empty($userFullData['avatar']) && file_exists($userFullData['avatar'])): ?>
<!-- ══ AVATAR MODAL ══ -->
<div id="avatarModal" style="display:none;position:fixed;inset:0;z-index:2000;align-items:center;justify-content:center;background:rgba(15,23,42,.7);backdrop-filter:blur(6px);" onclick="this.style.display='none'">
    <div style="position:relative;max-width:420px;width:90%;" onclick="event.stopPropagation()">
        <img src="<?= htmlspecialchars($userFullData['avatar']) ?>?v=<?= time() ?>"
             alt="avatar"
             style="width:100%;border-radius:16px;box-shadow:0 30px 80px rgba(0,0,0,.4);display:block;">
        <button onclick="document.getElementById('avatarModal').style.display='none'"
                style="position:absolute;top:-14px;left:-14px;width:34px;height:34px;border-radius:50%;border:none;background:#fff;color:#1a1a1a;font-size:1rem;font-weight:900;cursor:pointer;box-shadow:0 4px 12px rgba(0,0,0,.2);">✕</button>
    </div>
</div>
<?php endif; ?>

<!-- ══ CARD MODAL ══ -->
<div id="cardModal">
    <div class="modal-content">
        <div class="modal-title">💳 הוספת אמצעי תשלום</div>
        <form method="POST">
            <div class="form-group">
                <label>שם בעל הכרטיס</label>
                <input type="text" name="holder_name" value="<?= $firstName ?>" required>
            </div>
            <div class="form-group">
                <label>מספר כרטיס</label>
                <input type="text" name="card_number" id="cardNumberInput" placeholder="1234 5678 1234 5678" maxlength="19" required autocomplete="cc-number">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>תוקף (MM/YY)</label>
                    <input type="text" name="expiry" id="expiryInput" placeholder="05/28" maxlength="5" required autocomplete="cc-exp">
                </div>
                <div class="form-group">
                    <label>CVV</label>
                    <input type="text" id="cvvInput" placeholder="123" maxlength="3" inputmode="numeric" required autocomplete="cc-csc">
                </div>
            </div>
            <div class="modal-btns">
                <button type="submit" name="save_card" class="btn-save">שמור כרטיס</button>
                <input type="hidden" name="from_type" value="<?= htmlspecialchars($_GET['type'] ?? '') ?>">
                <input type="hidden" name="from_id"   value="<?= intval($_GET['id'] ?? 0) ?>">
                <button type="button" onclick="document.getElementById('cardModal').style.display='none'" class="btn-close-modal">ביטול</button>
            </div>
        </form>
    </div>
</div>

<script>
// גלילה אוטומטית לכרטיס אשראי אם הגיע מ-checkout
<?php if (!empty($_GET['from']) && $_GET['from'] === 'checkout' && !empty($_GET['type']) && !empty($_GET['id'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    const cardSection = document.getElementById('card-section');
    if (cardSection) {
        setTimeout(() => cardSection.scrollIntoView({ behavior: 'smooth', block: 'center' }), 300);
        cardSection.style.outline = '2.5px solid #6366f1';
        cardSection.style.borderRadius = '16px';
        setTimeout(() => cardSection.style.outline = '', 2500);
    }
});
<?php endif; ?>

function toggleCollapse(bodyId, toggleId) {
    const body   = document.getElementById(bodyId);
    const toggle = document.getElementById(toggleId);
    const isOpen = body.classList.toggle('open');
    toggle.classList.toggle('open', isOpen);
}

function filterInterests(query) {
    const q     = query.trim().toLowerCase();
    const chips = document.querySelectorAll('#interestsGrid .interest-chip');
    let visible = 0;
    chips.forEach(chip => {
        const name = chip.dataset.name || '';
        const show = !q || name.includes(q);
        chip.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    const noRes = document.getElementById('noInterestsFound');
    if (noRes) noRes.style.display = (q && visible === 0) ? 'block' : 'none';
}

// פתח אוטומטית אם הגיע עם hash #interests
if (window.location.hash === '#interests') {
    document.addEventListener('DOMContentLoaded', function() {
        const body   = document.getElementById('interestsBody');
        const toggle = document.getElementById('interestsToggle');
        if (body)   body.classList.add('open');
        if (toggle) toggle.classList.add('open');
    });
}

function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('avatarPreview');
            preview.innerHTML = '<img src="'+e.target.result+'" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// ── Card number: digits only, auto-space every 4
document.getElementById('cardNumberInput').addEventListener('input', function(e) {
    let v = this.value.replace(/\D/g, '').slice(0, 16);
    this.value = v.match(/.{1,4}/g)?.join(' ') || v;
});
document.getElementById('cardNumberInput').addEventListener('keydown', function(e) {
    if (e.key === 'Backspace') {
        let v = this.value;
        if (v.endsWith(' ')) { this.value = v.slice(0, -2); e.preventDefault(); }
    }
});

// ── Expiry: auto-insert / after MM
document.getElementById('expiryInput').addEventListener('input', function(e) {
    let v = this.value.replace(/\D/g, '').slice(0, 4);
    if (v.length >= 3) {
        v = v.slice(0, 2) + '/' + v.slice(2);
    }
    this.value = v;
});
document.getElementById('expiryInput').addEventListener('keydown', function(e) {
    if (e.key === 'Backspace' && this.value.endsWith('/')) {
        this.value = this.value.slice(0, -1);
        e.preventDefault();
    }
});

// ── CVV: digits only
document.getElementById('cvvInput').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '').slice(0, 3);
});
</script>
</body>
</html>
<?php $conn->close(); ?>