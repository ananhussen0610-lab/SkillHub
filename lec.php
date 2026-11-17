<?php
session_start();

if (!isset($_SESSION['id'])) { header('Location: login.php'); exit(); }

$userId    = $_SESSION['id'];
$firstName = 'מרצה';
$isAdmin   = 0;
$workshopsData    = [];
$editWorkshopData = null;

$registrationTable          = 'workshop_registrations';
$itemIdColumnInRegistration = 'workshop_id';
$userIdColumnInRegistration = 'user_id';
$workshopTable              = 'workshop';

$conn = new mysqli('localhost', 'root', '', 'skillhub');
if ($conn->connect_error) { error_log($conn->connect_error); http_response_code(500); echo "שגיאה פנימית."; exit(); }

$stmt = $conn->prepare("SELECT name, isAdmin FROM users WHERE id = ?");
if (!$stmt) { http_response_code(500); echo "שגיאה קריטית."; $conn->close(); exit(); }
$stmt->bind_param("i", $userId); $stmt->execute(); $r = $stmt->get_result();
if ($r->num_rows === 1) { $u = $r->fetch_assoc(); $isAdmin = $u['isAdmin']; $firstName = htmlspecialchars(trim($u['name'])); }
else { $stmt->close(); $conn->close(); header('Location: index.php?error=user_not_found'); exit(); }
$stmt->close();

if ($isAdmin != 2) { header('Location: index.php?error=permission_denied'); $conn->close(); exit(); }

$status_message = '';
if (isset($_SESSION['status_message'])) {
    $msg   = $_SESSION['status_message'];
    $cls   = $msg['type'] === 'success' ? 'toast-success' : ($msg['type'] === 'warning' ? 'toast-warning' : 'toast-error');
    $icon  = $msg['type'] === 'success' ? '✓' : ($msg['type'] === 'warning' ? '⚠' : '✕');
    $text  = htmlspecialchars($msg['text']);
    $status_message = "<div class='toast {$cls}'><span class='toast-icon'>{$icon}</span><span>{$text}</span></div>";
    unset($_SESSION['status_message']);
}

// ══ הוספת סדנה ══
if (isset($_POST['addWorkshop'])) {
    $wn   = trim($_POST['WorkshopName']    ?? '');
    $desc = trim($_POST['description']     ?? '');
    $dur  = trim($_POST['duration']        ?? '');
    $loc  = trim($_POST['location']        ?? '');
    $dt   = trim($_POST['date']            ?? '');
    $tm   = trim($_POST['time']            ?? '');
    $mx   = trim($_POST['maxParticipants'] ?? '');
    $tp   = trim($_POST['type']            ?? '');
    $pr   = trim($_POST['price']           ?? '');
    // ── סילבוס (שדה חדש — אופציונלי) ──
    $syllabus = trim($_POST['syllabus']    ?? '');

    if (empty($wn)||empty($desc)||empty($dur)||empty($loc)||empty($dt)||empty($tm)||empty($mx)||empty($tp)||empty($pr)) {
        $_SESSION['status_message'] = ['type'=>'error','text'=>'יש למלא את כל השדות.'];
    } elseif (($dt.' '.$tm) <= date('Y-m-d H:i')) {
        $_SESSION['status_message'] = ['type'=>'error','text'=>'לא ניתן לקבוע סדנה לתאריך בעבר.'];
    } else {
        $img = '';
        $lid = $userId;
        // ── INSERT כולל עמודת syllabus ──
        $si = $conn->prepare("INSERT INTO {$workshopTable}
            (WorkshopName, Description, Duration, Location, Date, Time, maxParticipants, Type, img_path, Price, lecId, syllabus)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        if ($si) {
            $si->bind_param("ssisssiisdis", $wn, $desc, $dur, $loc, $dt, $tm, $mx, $tp, $img, $pr, $lid, $syllabus);
            if ($si->execute()) {
                $_SESSION['status_message'] = ['type'=>'success','text'=>'הסדנה נוספה בהצלחה!'];
            } else {
                $_SESSION['status_message'] = ['type'=>'error','text'=>'שגיאה: '.$si->error];
            }
            $si->close();
        }
    }
    header("Location: lec.php"); exit;
}

// ══ עריכת סדנה ══
if (isset($_POST['editWorkshopSubmit'])) {
    $wsnum = intval($_POST['wsnum_to_edit'] ?? 0);
    $wn    = trim($_POST['WorkshopName']    ?? '');
    $desc  = trim($_POST['description']     ?? '');
    $dur   = trim($_POST['duration']        ?? '');
    $loc   = trim($_POST['location']        ?? '');
    $dt    = trim($_POST['date']            ?? '');
    $tm    = trim($_POST['time']            ?? '');
    $mx    = trim($_POST['maxParticipants'] ?? '');
    $tp    = trim($_POST['type']            ?? '');
    $pr    = trim($_POST['price']           ?? '');
    // ── סילבוס (שדה חדש — אופציונלי) ──
    $syllabus = trim($_POST['syllabus']     ?? '');

    $msg   = ['type'=>'error','text'=>''];
    $valid = true;

    if ($wsnum <= 0) { $msg['text'] = "מזהה לא חוקי."; $valid = false; }
    if ($valid && (empty($wn)||empty($desc)||empty($dur)||empty($loc)||empty($dt)||empty($tm)||empty($mx)||empty($tp)||empty($pr))) {
        $msg['text'] = 'יש למלא את כל השדות.'; $valid = false;
    }
    if ($valid) {
        // ── UPDATE כולל עמודת syllabus ──
        $su = $conn->prepare("UPDATE {$workshopTable}
            SET WorkshopName=?, Description=?, Duration=?, Location=?, Date=?, Time=?, maxParticipants=?, Type=?, Price=?, syllabus=?
            WHERE wsnum=? AND lecId=?");
        if ($su) {
            $su->bind_param("ssisssiisdii", $wn, $desc, $dur, $loc, $dt, $tm, $mx, $tp, $pr, $syllabus, $wsnum, $userId);
            if ($su->execute()) {
                if ($su->affected_rows > 0) { $msg['type']='success'; $msg['text']="'{$wn}' עודכנה!"; }
                else { $msg['text']="לא בוצעו שינויים."; $msg['type']='warning'; }
            } else {
                $msg['text'] = 'שגיאה: '.$su->error;
            }
            $su->close();
        }
    }
    $_SESSION['status_message'] = $msg;
    header("Location: lec.php"); exit;
}

// ══ מחיקת משתתף ══
if (isset($_POST['deleteParticipant'])) {
    $wsnum = intval($_POST['wsnum_to_delete']    ?? 0);
    $uid   = intval($_POST['user_id_to_delete']  ?? 0);
    $msg   = ['type'=>'error','text'=>''];
    $valid = true;
    if ($wsnum <= 0 || $uid <= 0) { $msg['text']="נתונים לא חוקיים."; $valid=false; }
    if ($valid) {
        $ck = $conn->prepare("SELECT wsnum FROM {$workshopTable} WHERE wsnum=? AND lecId=?");
        $ck->bind_param("ii",$wsnum,$userId); $ck->execute(); $cr=$ck->get_result(); $ck->close();
        if ($cr->num_rows===0) { $msg['text']="אין הרשאה."; $valid=false; }
    }
    if ($valid) {
        $sd = $conn->prepare("DELETE FROM {$registrationTable} WHERE {$itemIdColumnInRegistration}=? AND {$userIdColumnInRegistration}=?");
        if ($sd) {
            $sd->bind_param("ii",$wsnum,$uid);
            if ($sd->execute()) {
                if ($sd->affected_rows>0) { $msg['type']='success'; $msg['text']="הנרשם נמחק."; }
                else { $msg['text']="לא נמצא."; $msg['type']='warning'; }
            } else { $msg['text']='שגיאה: '.$sd->error; }
            $sd->close();
        }
    }
    $_SESSION['status_message'] = $msg;
    header("Location: lec.php"); exit;
}

// ══ טעינת נתוני עריכה ══
if (isset($_GET['edit_wsnum'])) {
    $en = intval($_GET['edit_wsnum']);
    if ($en > 0) {
        // ── SELECT כולל עמודת syllabus ──
        $se = $conn->prepare("SELECT wsnum, WorkshopName, Description, Duration, Location, Date, Time, maxParticipants, Type, Price, img_path, syllabus
            FROM {$workshopTable} WHERE wsnum=? AND lecId=?");
        if ($se) {
            $se->bind_param("ii",$en,$userId); $se->execute(); $re=$se->get_result();
            if ($re->num_rows===1) { $editWorkshopData=$re->fetch_assoc(); }
            else { $_SESSION['status_message']=['type'=>'error','text'=>'סדנה לא נמצאה.']; header("Location: lec.php"); exit; }
            $se->close();
        }
    }
}

// ══ סדנאות עתידיות ══
$sw = $conn->prepare("
    SELECT w.wsnum, w.WorkshopName, w.maxParticipants, w.Date, w.Time, w.Location,
           IFNULL(t.typeName, w.Type) AS wsType,
           COUNT(wr.{$itemIdColumnInRegistration}) AS currentParticipants
    FROM {$workshopTable} w
    LEFT JOIN {$registrationTable} wr ON w.wsnum = wr.{$itemIdColumnInRegistration}
    LEFT JOIN types t ON t.typeId = w.Type
    WHERE w.lecId=? AND CONCAT(w.Date,' ',w.Time) > NOW()
    GROUP BY w.wsnum
    ORDER BY w.Date ASC, w.Time ASC
");
$sw->bind_param("i",$userId); $sw->execute(); $rw=$sw->get_result(); $sw->close();
if ($rw->num_rows > 0) {
    while ($workshop = $rw->fetch_assoc()) {
        $wid = $workshop['wsnum'];
        $workshop['participants'] = [];
        $sp = $conn->prepare("SELECT u.id AS user_id, u.name, u.lastname, u.email
            FROM {$registrationTable} wr JOIN users u ON wr.user_id=u.id
            WHERE wr.{$itemIdColumnInRegistration}=? ORDER BY u.lastname ASC");
        $sp->bind_param("i",$wid); $sp->execute(); $rp=$sp->get_result();
        while ($p=$rp->fetch_assoc()) $workshop['participants'][]=$p;
        $sp->close();
        $workshopsData[] = $workshop;
    }
}

// ══ סדנאות שהסתיימו ══
$pastWorkshopsData = [];
$spw = $conn->prepare("
    SELECT w.wsnum, w.WorkshopName, w.maxParticipants, w.Date, w.Time, w.Location,
           IFNULL(t.typeName, w.Type) AS wsType,
           COUNT(wr.{$itemIdColumnInRegistration}) AS currentParticipants
    FROM {$workshopTable} w
    LEFT JOIN {$registrationTable} wr ON w.wsnum = wr.{$itemIdColumnInRegistration}
    LEFT JOIN types t ON t.typeId = w.Type
    WHERE w.lecId=? AND CONCAT(w.Date,' ',w.Time) <= NOW()
    GROUP BY w.wsnum
    ORDER BY w.Date DESC, w.Time DESC
");
$spw->bind_param("i",$userId); $spw->execute(); $rpw=$spw->get_result(); $spw->close();
if ($rpw->num_rows > 0) {
    while ($workshop = $rpw->fetch_assoc()) {
        $wid = $workshop['wsnum'];
        $workshop['participants'] = [];
        $sp = $conn->prepare("SELECT u.id AS user_id, u.name, u.lastname, u.email
            FROM {$registrationTable} wr JOIN users u ON wr.user_id=u.id
            WHERE wr.{$itemIdColumnInRegistration}=? ORDER BY u.lastname ASC");
        $sp->bind_param("i",$wid); $sp->execute(); $rp=$sp->get_result();
        while ($p=$rp->fetch_assoc()) $workshop['participants'][]=$p;
        $sp->close();
        $pastWorkshopsData[] = $workshop;
    }
}

$types = [];
$rt = $conn->query("SELECT typeId, typeName FROM types");
if ($rt) while ($row=$rt->fetch_assoc()) $types[]=$row;


// ══ שמירת שאלות מבחן ══
if (isset($_POST['save_quiz'])) {
    $ws_quiz = intval($_POST['ws_quiz_id'] ?? 0);
    $ck = $conn->prepare("SELECT wsnum FROM workshop WHERE wsnum=? AND lecId=?");
    $ck->bind_param("ii", $ws_quiz, $userId);
    $ck->execute();
    $ck->store_result();
    if ($ck->num_rows > 0 && $ws_quiz > 0) {
        $ck->close();
        $qtexts   = $_POST['question_text']  ?? [];
        $ans_a    = $_POST['answer_a']        ?? [];
        $ans_b    = $_POST['answer_b']        ?? [];
        $ans_c    = $_POST['answer_c']        ?? [];
        $ans_d    = $_POST['answer_d']        ?? [];
        $corrects = $_POST['correct_answer']  ?? [];
        $saved = 0;
        foreach ($qtexts as $i => $qt) {
            $qt = trim($qt);
            if (empty($qt)) continue;
            $a   = trim($ans_a[$i]   ?? '');
            $b   = trim($ans_b[$i]   ?? '');
            $c   = trim($ans_c[$i]   ?? '');
            $d   = trim($ans_d[$i]   ?? '');
            $cor = in_array($corrects[$i] ?? '', ['a','b','c','d']) ? $corrects[$i] : 'a';
            $si2 = $conn->prepare("INSERT INTO quiz_questions (wsnum,question_text,answer_a,answer_b,answer_c,answer_d,correct_answer) VALUES (?,?,?,?,?,?,?)");
            $si2->bind_param("issssss", $ws_quiz, $qt, $a, $b, $c, $d, $cor);
            $si2->execute();
            $si2->close();
            $saved++;
        }
        $_SESSION['status_message'] = ['type'=>'success','text'=>"נשמרו {$saved} שאלות!"];
    } else {
        $ck->close();
        $_SESSION['status_message'] = ['type'=>'error','text'=>'שגיאה — אין הרשאה.'];
    }
    header("Location: lec.php");
    exit;
}

// ══ מחיקת שאלה ══
if (isset($_POST['delete_question'])) {
    $qid = intval($_POST['question_id'] ?? 0);
    $ckq = $conn->prepare("SELECT qq.id FROM quiz_questions qq JOIN workshop w ON qq.wsnum=w.wsnum WHERE qq.id=? AND w.lecId=?");
    $ckq->bind_param("ii", $qid, $userId);
    $ckq->execute();
    $ckq->store_result();
    if ($ckq->num_rows > 0) {
        $ckq->close();
        $dq = $conn->prepare("DELETE FROM quiz_questions WHERE id=?");
        $dq->bind_param("i", $qid);
        $dq->execute();
        $dq->close();
        $_SESSION['status_message'] = ['type'=>'success','text'=>'השאלה נמחקה.'];
    } else {
        $ckq->close();
    }
    header("Location: lec.php");
    exit;
}


?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>לוח בקרה — מרצה · SkillHub</title>
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
    --amber-bg:#f9fafb;
    --shadow-sm:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);
    --shadow:0 4px 16px rgba(0,0,0,.07),0 1px 3px rgba(0,0,0,.05);
    --shadow-md:0 8px 28px rgba(0,0,0,.09),0 2px 6px rgba(0,0,0,.05);
    --shadow-lg:0 20px 50px rgba(0,0,0,.11),0 4px 12px rgba(0,0,0,.06);
    --radius:16px;
    --radius-sm:10px;
}

html{scroll-behavior:smooth;}
body{font-family:'Heebo',sans-serif;direction:rtl;min-height:100vh;background:var(--bg);color:var(--text);}

.navbar{position:sticky;top:0;z-index:100;height:66px;padding:0 40px;display:flex;align-items:center;justify-content:space-between;background:rgba(255,255,255,.92);border-bottom:1px solid var(--border);backdrop-filter:blur(16px);box-shadow:var(--shadow-sm);}
.nav-logo{font-family:'Frank Ruhl Libre',serif;font-size:1.65rem;font-weight:900;color:var(--navy);text-decoration:none;display:flex;align-items:center;gap:9px;}
.nav-logo-dot{width:10px;height:10px;border-radius:50%;background:linear-gradient(135deg,#1a1a1a,#333333);box-shadow:0 0 0 4px rgba(0,0,0,.08);}
.nav-links{display:flex;align-items:center;gap:6px;}
.nav-link{padding:8px 14px;border-radius:var(--radius-sm);color:var(--text3);text-decoration:none;font-size:.88rem;font-weight:700;border:1px solid transparent;transition:.2s ease;}
.nav-link:hover{color:var(--navy);background:var(--surface3);border-color:var(--border);}
.nav-link.danger{color:var(--red);}
.nav-link.danger:hover{background:var(--red-bg);border-color:#fecaca;}
.nav-sep{color:var(--border2);font-size:.7rem;}

.hero-strip{background:linear-gradient(135deg,rgba(15,23,42,.75) 0%,rgba(30,45,74,.68) 100%),url('images/admin5.jpeg') center/cover no-repeat;padding:200px 40px 210px;position:relative;overflow:hidden;}
.hero-strip::before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 15% 50%,rgba(217,119,6,.2),transparent 40%),radial-gradient(circle at 85% 20%,rgba(0,0,0,.07),transparent 35%);pointer-events:none;}
.hero-inner{position:relative;z-index:2;max-width:1050px;margin:0 auto;display:flex;align-items:center;gap:20px;}
.hero-avatar{width:60px;height:60px;border-radius:18px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:1.7rem;background:linear-gradient(135deg,#1a1a1a,#333333);color:#fff;box-shadow:0 8px 24px rgba(0,0,0,.20);}
.hero-name{font-family:'Frank Ruhl Libre',serif;font-size:1.9rem;font-weight:900;color:#fff;line-height:1.1;}
.hero-role{color:rgba(255,255,255,.55);font-size:.92rem;margin-top:4px;font-weight:600;}

.main{max-width:1050px;margin:0 auto;padding:36px 28px 72px;}

.toast{display:flex;align-items:center;gap:12px;padding:13px 16px;border-radius:var(--radius-sm);margin-bottom:22px;font-weight:700;border:1px solid;box-shadow:var(--shadow);}
.toast-success{background:var(--green-bg);border-color:var(--green-border);color:var(--green);}
.toast-error{background:var(--red-bg);border-color:#fecaca;color:var(--red);}
.toast-warning{background:var(--amber-bg);border-color:#d1d5db;color:#111827;}
.toast-icon{width:26px;height:26px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.06);font-size:.9rem;}

.section-label{display:flex;align-items:center;gap:12px;margin:32px 0 16px;color:var(--text3);font-size:.76rem;font-weight:800;letter-spacing:.8px;text-transform:uppercase;}
.section-label::before,.section-label::after{content:"";height:1px;flex:1;background:var(--border);}

.card,.ws-card{background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;margin-bottom:14px;transition:.2s ease;}
.card:hover,.ws-card:hover{box-shadow:var(--shadow-md);}
.card-header,.ws-card-header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:0;cursor:pointer;user-select:none;transition:.15s ease;}
.card-header:hover,.ws-card-header:hover{background:#fafbfc;}
.card-header.open,.ws-card-header.open{border-bottom:2px solid var(--border);}
.card-header-left,.ws-card-title-wrap{display:flex;align-items:center;gap:0;flex:1;}
.card-icon-wrap{width:56px;height:56px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:1.25rem;background:linear-gradient(135deg,#1a1a1a,#333333);color:#fff;}
.card-icon-wrap.green{background:linear-gradient(135deg,#16a34a,#22c55e);}
.card-icon-wrap.blue{background:linear-gradient(135deg,#3b82f6,#6366f1);}
.card-info{padding:14px 16px;flex:1;}
.card-title,.ws-card-title{font-family:'Frank Ruhl Libre',serif;color:var(--navy);font-size:.98rem;font-weight:900;line-height:1.2;}
.card-subtitle{color:var(--text3);font-size:.74rem;margin-top:2px;}
.toggle-btn,.ws-toggle-btn{display:flex;align-items:center;gap:5px;padding:0 20px;height:56px;color:var(--text3);background:transparent;border:none;border-right:1px solid var(--border);font-weight:700;font-size:.8rem;white-space:nowrap;transition:.15s ease;flex-shrink:0;}
.card-header:hover .toggle-btn,.ws-card-header:hover .ws-toggle-btn{color:#1a1a1a;}
.card-header.open .toggle-btn,.ws-card-header.open .ws-toggle-btn{color:#1a1a1a;}
.chevron{transition:.25s ease;display:inline-block;font-size:.68rem;}
.card-header.open .chevron,.ws-card-header.open .chevron{transform:rotate(180deg);}
.ws-num-badge{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;padding:0 8px;border-radius:7px;background:var(--surface2);border:1px solid var(--border);color:var(--text3);font-size:.72rem;font-weight:800;margin:0 16px 0 12px;flex-shrink:0;}
.ws-participants-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;background:var(--surface2);border:1px solid var(--border);color:var(--text3);font-size:.74rem;font-weight:700;margin-right:10px;}
.ws-header-actions{display:flex;align-items:center;gap:8px;padding-left:12px;}
.card-body,.ws-card-body{display:none;padding:22px;}
.card-body.open,.ws-card-body.open{display:block;animation:fadeIn .2s ease;}
@keyframes fadeIn{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:translateY(0)}}

.form-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:0 16px;}
.form-group{margin-bottom:14px;}
.form-label{display:block;margin-bottom:6px;color:var(--text);font-size:.82rem;font-weight:700;}
.form-input{width:100%;padding:10px 13px;border-radius:var(--radius-sm);border:1.5px solid var(--border2);background:#fff;color:var(--text);font-family:'Heebo',sans-serif;font-size:.9rem;outline:none;transition:.2s ease;}
.form-input::placeholder{color:var(--text4);}
.form-input:focus{border-color:#1a1a1a;box-shadow:0 0 0 3px rgba(217,119,6,.1);}
select.form-input option{color:var(--text);}
textarea.form-input{resize:vertical;}

/* ══ סילבוס — עיצוב מיוחד ══ */
.syllabus-group{
    margin-bottom:14px;
    background:#f8fafc;
    border:1.5px solid #e2e8f0;
    border-radius:var(--radius-sm);
    overflow:hidden;
}
.syllabus-group-header{
    display:flex;align-items:center;gap:10px;
    padding:10px 14px;
    background:linear-gradient(90deg,#eff6ff,#f8fafc);
    border-bottom:1px solid #e2e8f0;
}
.syllabus-group-icon{font-size:1.1rem;}
.syllabus-group-title{font-size:.82rem;font-weight:800;color:var(--navy);}
.syllabus-group-hint{font-size:.74rem;color:var(--text3);margin-right:auto;}
.syllabus-textarea{
    width:100%;padding:12px 14px;
    border:none;background:#fff;
    color:var(--text);font-family:'Heebo',sans-serif;font-size:.9rem;
    outline:none;resize:vertical;min-height:130px;
    line-height:1.8;direction:rtl;
}
.syllabus-textarea::placeholder{color:var(--text4);}
.syllabus-textarea:focus{background:#fefefe;}

.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;border:0;cursor:pointer;text-decoration:none;font-family:'Heebo',sans-serif;font-weight:700;border-radius:var(--radius-sm);transition:.2s ease;white-space:nowrap;font-size:.88rem;}
.btn-primary,.btn-amber{width:100%;margin-top:6px;padding:11px 18px;color:#fff;background:linear-gradient(135deg,#1a1a1a,#333333);box-shadow:0 4px 14px rgba(0,0,0,.14);}
.btn-primary:hover,.btn-amber:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,0,0,.20);}
.btn-cancel-link{display:block;text-align:center;margin-top:10px;padding:10px;border-radius:var(--radius-sm);text-decoration:none;color:var(--text3);background:var(--surface3);border:1px solid var(--border);font-weight:700;font-size:.87rem;transition:.2s ease;}
.btn-cancel-link:hover{color:var(--text2);background:var(--border);}
.btn-edit{display:inline-flex;align-items:center;gap:5px;padding:7px 12px;border-radius:8px;text-decoration:none;font-size:.78rem;font-weight:700;color:#111827;background:var(--amber-bg);border:1px solid var(--gold-border);transition:.2s ease;}
.btn-edit:hover{background:#1a1a1a;color:#fff;border-color:#1a1a1a;}
.btn-del{padding:6px 11px;border-radius:7px;font-size:.76rem;font-weight:700;color:var(--red);background:var(--red-bg);border:1px solid #fecaca;cursor:pointer;font-family:'Heebo',sans-serif;transition:.2s ease;}
.btn-del:hover{background:var(--red);color:#fff;border-color:var(--red);}

.ws-details{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:12px;padding:16px;background:var(--surface2);border-radius:var(--radius-sm);border:1px solid var(--border);margin-bottom:18px;}
.ws-detail-item{display:flex;flex-direction:column;gap:3px;}
.ws-detail-label{color:var(--text4);font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.4px;}
.ws-detail-value{color:var(--text2);font-weight:700;font-size:.88rem;}
.ws-type-pill{display:inline-flex;padding:2px 9px;border-radius:999px;font-size:.72rem;font-weight:800;color:#166534;background:#dcfce7;border:1px solid #86efac;}

.ws-participants-header{display:flex;align-items:center;gap:10px;margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid var(--border);}
.ws-participants-title{font-weight:800;color:var(--navy);font-size:.92rem;}
.count-badge{display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;padding:0 7px;border-radius:999px;background:linear-gradient(90deg,#1a1a1a,#333333);color:#fff;font-size:.7rem;font-weight:900;}

.table-wrap{overflow-x:auto;border-radius:var(--radius-sm);border:1px solid var(--border);}
table{width:100%;border-collapse:collapse;min-width:600px;}
thead tr{background:linear-gradient(180deg,var(--surface2),#edf2f7);}
th{padding:10px 14px;text-align:right;color:var(--text3);font-size:.72rem;font-weight:800;border-bottom:2px solid var(--border);letter-spacing:.4px;text-transform:uppercase;}
td{padding:12px 14px;color:var(--text2);border-bottom:1px solid #f1f5f9;font-size:.87rem;vertical-align:middle;}
tbody tr:last-child td{border-bottom:none;}
td strong{color:var(--navy);}
tbody tr{transition:.12s ease;}
tbody tr:hover{background:#f8fafc;}
.td-c{text-align:center;}

.empty-participants,.no-workshops{text-align:center;padding:32px 20px;color:var(--text3);font-weight:600;}
.empty-participants-icon,.no-workshops-icon{font-size:2rem;display:block;margin-bottom:8px;opacity:.4;}
.no-workshops{background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);}

.history-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:11px 16px;margin-top:0;border:none;border-top:2px dashed var(--border2);background:#fafbfd;color:var(--text3);font-family:'Heebo',sans-serif;font-size:.83rem;font-weight:700;cursor:pointer;transition:.2s ease;}
.history-btn:hover{background:var(--amber-bg);border-top-color:var(--gold-border);color:#111827;}
.history-btn.open{background:var(--amber-bg);border-top-style:solid;border-top-color:var(--gold-border);color:#111827;}
.history-btn .hb-arrow{display:inline-block;transition:transform .25s;font-style:normal;font-size:.7rem;}
.history-btn.open .hb-arrow{transform:rotate(180deg);}
.history-count-pill{display:inline-flex;align-items:center;justify-content:center;padding:1px 8px;border-radius:999px;background:var(--border2);color:var(--text2);font-size:.7rem;font-weight:800;}
.history-btn.open .history-count-pill{background:var(--gold-border);color:#111827;}
.history-section{display:none;border-top:1px solid var(--gold-border);}
.history-section.open{display:block;animation:fadeIn .2s ease;}
.history-section-header{padding:8px 22px;background:var(--amber-bg);border-bottom:1px solid var(--gold-border);font-size:.72rem;font-weight:800;color:#111827;display:flex;align-items:center;gap:8px;letter-spacing:.3px;text-transform:uppercase;}
.past-ws-card{background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;margin-bottom:10px;opacity:.75;transition:.2s ease;}
.past-ws-card:hover{opacity:1;box-shadow:var(--shadow-md);}

::-webkit-scrollbar{width:6px;height:6px;}
::-webkit-scrollbar-track{background:var(--bg);}
::-webkit-scrollbar-thumb{background:var(--border2);border-radius:20px;}
::-webkit-scrollbar-thumb:hover{background:#1a1a1a;}

@media(max-width:760px){
    .navbar{height:auto;padding:14px 20px;flex-direction:column;gap:12px;}
    .hero-strip{padding:28px 20px 32px;}
    .main{padding:24px 18px 60px;}
    .form-grid-2,.ws-details{grid-template-columns:1fr;}
}

/* ══ מבחן ══ */
.quiz-ws-select{width:100%;padding:10px 13px;border-radius:var(--radius-sm);border:1.5px solid var(--border2);background:#fff;color:var(--text);font-family:'Heebo',sans-serif;font-size:.9rem;outline:none;transition:.2s;margin-bottom:14px;}
.quiz-ws-select:focus{border-color:#1a1a1a;}
.qblock{background:var(--surface2);border:1.5px solid var(--border);border-radius:var(--radius-sm);padding:16px;margin-bottom:12px;}
.qblock-title{font-size:.74rem;font-weight:800;color:var(--text3);letter-spacing:.4px;text-transform:uppercase;margin-bottom:10px;}
.answers-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;}
.ans-label-sm{font-size:.74rem;font-weight:800;color:var(--text3);margin-bottom:3px;}
.ans-input{width:100%;padding:8px 10px;border-radius:8px;border:1.5px solid var(--border2);background:#fff;font-family:'Heebo',sans-serif;font-size:.87rem;outline:none;transition:.2s;}
.ans-input:focus{border-color:#1a1a1a;}
.correct-wrap{margin-top:10px;padding:10px 12px;background:var(--green-bg);border:1px solid var(--green-border);border-radius:8px;}
.correct-label{font-size:.74rem;font-weight:800;color:var(--green);margin-bottom:4px;}
.correct-select{width:100%;padding:7px 10px;border-radius:7px;border:1.5px solid var(--green-border);background:#fff;font-family:'Heebo',sans-serif;font-size:.87rem;outline:none;}
.btn-add-q{display:flex;align-items:center;justify-content:center;gap:7px;width:100%;padding:10px;border-radius:var(--radius-sm);border:2px dashed var(--border2);background:var(--surface2);color:var(--text3);font-family:'Heebo',sans-serif;font-size:.87rem;font-weight:700;cursor:pointer;transition:.2s;margin-bottom:14px;}
.btn-add-q:hover{border-color:var(--navy);color:var(--navy);background:#f0f4ff;}
.btn-del-q{padding:5px 10px;border-radius:6px;border:1px solid #fecaca;background:var(--red-bg);color:var(--red);font-size:.76rem;font-weight:700;cursor:pointer;font-family:'Heebo',sans-serif;transition:.2s;}
.btn-del-q:hover{background:var(--red);color:#fff;}
.existing-q{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:11px 14px;margin-bottom:8px;display:flex;align-items:center;gap:12px;}
.existing-q-num{width:26px;height:26px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:var(--navy);color:#fff;font-size:.74rem;font-weight:900;}
.existing-q-text{flex:1;font-size:.9rem;color:var(--text2);font-weight:600;}

</style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="nav-logo"><span class="nav-logo-dot"></span>SkillHub</a>
    <div class="nav-links">
        <a href="index.php" class="nav-link">🏠 דף הבית</a>
        <span class="nav-sep">·</span>
        <a href="logout.php" class="nav-link danger">← התנתק</a>
    </div>
</nav>

<div class="hero-strip">
    <div class="hero-inner">
        <div class="hero-avatar">🎓</div>
        <div>
            <div class="hero-name">שלום, <?= $firstName ?>!</div>
            <div class="hero-role">לוח בקרה מרצה · SkillHub</div>
        </div>
    </div>
</div>

<div class="main">

<?= $status_message ?>

<?php if ($editWorkshopData):
    $w = $editWorkshopData; ?>

<div class="section-label">עריכת סדנה</div>
<div class="card">
    <div class="card-header open" onclick="toggleCard(this)">
        <div class="card-header-left">
            <div class="card-icon-wrap" style="background:linear-gradient(135deg,#3b82f6,#6366f1)">✏️</div>
            <div class="card-info">
                <div class="card-title">עריכת: <?= htmlspecialchars($w['WorkshopName']) ?></div>
                <div class="card-subtitle">מזהה סדנה #<?= $w['wsnum'] ?></div>
            </div>
        </div>
        <div class="toggle-btn"><span class="tb-label">סגור</span><span class="chevron" style="transform:rotate(180deg)">▼</span></div>
    </div>
    <div class="card-body open">
        <form method="post">
            <input type="hidden" name="wsnum_to_edit" value="<?= $w['wsnum'] ?>">
            <div class="form-group"><label class="form-label">שם הסדנה</label><input type="text" name="WorkshopName" class="form-input" value="<?= htmlspecialchars($w['WorkshopName']) ?>" required></div>
            <div class="form-group"><label class="form-label">תיאור</label><textarea name="description" class="form-input" rows="3" required><?= htmlspecialchars($w['Description']) ?></textarea></div>
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">משך (דקות)</label><input type="number" name="duration" class="form-input" value="<?= htmlspecialchars($w['Duration']) ?>" required></div>
                <div class="form-group"><label class="form-label">מיקום</label><input type="text" name="location" class="form-input" value="<?= htmlspecialchars($w['Location']) ?>" required></div>
                <div class="form-group"><label class="form-label">תאריך</label><input type="date" name="date" class="form-input" value="<?= htmlspecialchars($w['Date']) ?>" required></div>
                <div class="form-group"><label class="form-label">שעה</label><input type="time" name="time" class="form-input" value="<?= htmlspecialchars(substr($w['Time'],0,5)) ?>" required></div>
                <div class="form-group"><label class="form-label">מקסימום משתתפים</label><input type="number" name="maxParticipants" class="form-input" value="<?= htmlspecialchars($w['maxParticipants']) ?>" required></div>
                <div class="form-group"><label class="form-label">מחיר (₪)</label><input type="number" step="0.01" name="price" class="form-input" value="<?= htmlspecialchars($w['Price']) ?>" required></div>
            </div>
            <div class="form-group"><label class="form-label">סוג סדנה</label>
                <select name="type" class="form-input" required>
                    <option value="">— בחר סוג —</option>
                    <?php foreach ($types as $t): ?>
                    <option value="<?= $t['typeId'] ?>" <?= $t['typeId']==$w['Type']?'selected':'' ?>><?= htmlspecialchars($t['typeName']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ══ שדה סילבוס ══ -->
            <div class="syllabus-group">
                <div class="syllabus-group-header">
                    <span class="syllabus-group-icon">📋</span>
                    <span class="syllabus-group-title">סילבוס הסדנה</span>
                    <span class="syllabus-group-hint">אופציונלי — יוצג בדף הסדנה</span>
                </div>
                <textarea
                    name="syllabus"
                    class="syllabus-textarea"
                    placeholder="לדוגמה:&#10;שיעור 1 — מבוא לנושא&#10;שיעור 2 — עמקות בנושא&#10;שיעור 3 — תרגול מעשי&#10;..."
                ><?= htmlspecialchars($w['syllabus'] ?? '') ?></textarea>
            </div>

            <button type="submit" name="editWorkshopSubmit" class="btn btn-amber">💾 שמור שינויים</button>
            <a href="lec.php" class="btn-cancel-link">ביטול עריכה</a>
        </form>
    </div>
</div>

<?php else: ?>

<div class="section-label">ניהול סדנאות</div>
<div class="card">
    <div class="card-header" onclick="toggleCard(this)">
        <div class="card-header-left">
            <div class="card-icon-wrap">➕</div>
            <div class="card-info">
                <div class="card-title">הוספת סדנה חדשה</div>
                <div class="card-subtitle">לחץ לפתיחת הטופס</div>
            </div>
        </div>
        <div class="toggle-btn"><span class="tb-label">פתח</span><span class="chevron">▼</span></div>
    </div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <div class="form-group"><label class="form-label">שם הסדנה</label><input type="text" name="WorkshopName" class="form-input" placeholder="לדוגמה: מבוא ל-Python" required></div>
            <div class="form-group"><label class="form-label">תיאור</label><textarea name="description" class="form-input" rows="3" placeholder="תיאור קצר של הסדנה…" required></textarea></div>
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">משך (דקות)</label><input type="number" name="duration" class="form-input" placeholder="90" required></div>
                <div class="form-group"><label class="form-label">מיקום</label><input type="text" name="location" class="form-input" placeholder="חדר 301" required></div>
                <div class="form-group"><label class="form-label">תאריך</label><input type="date" name="date" class="form-input" required></div>
                <div class="form-group"><label class="form-label">שעה</label><input type="time" name="time" class="form-input" required></div>
                <div class="form-group"><label class="form-label">מקסימום משתתפים</label><input type="number" name="maxParticipants" class="form-input" placeholder="30" required></div>
                <div class="form-group"><label class="form-label">מחיר (₪)</label><input type="number" step="0.01" name="price" class="form-input" placeholder="150.00" required></div>
            </div>
            <div class="form-group"><label class="form-label">סוג סדנה</label>
                <select name="type" class="form-input" required>
                    <option value="">— בחר סוג —</option>
                    <?php foreach ($types as $t): ?>
                    <option value="<?= $t['typeId'] ?>"><?= htmlspecialchars($t['typeName']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ══ שדה סילבוס ══ -->
            <div class="syllabus-group">
                <div class="syllabus-group-header">
                    <span class="syllabus-group-icon">📋</span>
                    <span class="syllabus-group-title">סילבוס הסדנה</span>
                    <span class="syllabus-group-hint">אופציונלי — יוצג בדף הסדנה</span>
                </div>
                <textarea
                    name="syllabus"
                    class="syllabus-textarea"
                    placeholder="לדוגמה:&#10;שיעור 1 — מבוא לנושא&#10;שיעור 2 — עמקות בנושא&#10;שיעור 3 — תרגול מעשי&#10;..."
                ></textarea>
            </div>

            <button type="submit" name="addWorkshop" class="btn btn-primary">✦ הוסף סדנה</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ─── Workshop list ─── -->
<div class="section-label">הסדנאות שלי</div>

<?php if (!empty($workshopsData)): ?>
    <?php foreach ($workshopsData as $i => $ws):
        $cnt = count($ws['participants']); ?>
    <div class="ws-card">
        <div class="ws-card-header" onclick="toggleWsCard(this)">
            <div class="ws-card-title-wrap">
                <span class="ws-num-badge">#<?= $ws['wsnum'] ?></span>
                <div class="card-info" style="padding:14px 8px 14px 0;">
                    <div class="ws-card-title"><?= htmlspecialchars($ws['WorkshopName']) ?></div>
                    <div class="card-subtitle">📆 <?= htmlspecialchars($ws['Date']) ?> · 📍 <?= htmlspecialchars($ws['Location']) ?></div>
                </div>
                <span class="ws-participants-badge">👥 <?= $cnt ?>/<?= $ws['maxParticipants'] ?></span>
            </div>
            <div class="ws-header-actions">
                <a href="lec.php?edit_wsnum=<?= $ws['wsnum'] ?>" class="btn-edit" onclick="event.stopPropagation()">✏️ ערוך</a>
                <div class="ws-toggle-btn"><span class="tb-ws-label">פתח</span><span class="chevron">▼</span></div>
            </div>
        </div>
        <div class="ws-card-body">
            <div class="ws-details">
                <div class="ws-detail-item"><span class="ws-detail-label">תאריך</span><span class="ws-detail-value">📆 <?= htmlspecialchars($ws['Date']) ?></span></div>
                <div class="ws-detail-item"><span class="ws-detail-label">שעה</span><span class="ws-detail-value">🕐 <?= htmlspecialchars(substr($ws['Time'],0,5)) ?></span></div>
                <div class="ws-detail-item"><span class="ws-detail-label">מיקום</span><span class="ws-detail-value">📍 <?= htmlspecialchars($ws['Location']) ?></span></div>
                <div class="ws-detail-item"><span class="ws-detail-label">קיבולת</span><span class="ws-detail-value">👥 <?= $cnt ?> / <?= $ws['maxParticipants'] ?></span></div>
                <?php if (!empty($ws['wsType'])): ?><div class="ws-detail-item"><span class="ws-detail-label">סוג</span><span class="ws-detail-value"><span class="ws-type-pill"><?= htmlspecialchars($ws['wsType']) ?></span></span></div><?php endif; ?>
            </div>
            <div class="ws-participants-header">
                <span class="ws-participants-title">משתתפים רשומים</span>
                <span class="count-badge"><?= $cnt ?></span>
            </div>
            <?php if (!empty($ws['participants'])): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>שם פרטי</th><th>שם משפחה</th><th>אימייל</th><th class="td-c">פעולה</th></tr></thead>
                    <tbody>
                    <?php foreach ($ws['participants'] as $p): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                            <td><?= htmlspecialchars($p['lastname']) ?></td>
                            <td><?= htmlspecialchars($p['email']) ?></td>
                            <td class="td-c">
                                <form method="post" onsubmit="return confirm('למחוק את <?= htmlspecialchars($p['name'].' '.$p['lastname'],ENT_QUOTES) ?> מהסדנה?')">
                                    <input type="hidden" name="wsnum_to_delete" value="<?= $ws['wsnum'] ?>">
                                    <input type="hidden" name="user_id_to_delete" value="<?= $p['user_id'] ?>">
                                    <button type="submit" name="deleteParticipant" class="btn-del">✕ הסר</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-participants">
                    <span class="empty-participants-icon">👤</span>
                    <div>אין משתתפים רשומים עדיין</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="no-workshops">
        <span class="no-workshops-icon">🗂️</span>
        <div>לא יצרת עדיין סדנאות עתידיות — לחץ על "הוספת סדנה חדשה" למעלה!</div>
    </div>
<?php endif; ?>

<?php if (!empty($pastWorkshopsData)): ?>
<div style="margin-top:8px;">
    <button class="history-btn" id="btnHistWs" onclick="toggleHistory('histWs','btnHistWs')">
        <span>🗂️</span>
        <span>הצג סדנאות שהסתיימו</span>
        <span class="history-count-pill"><?= count($pastWorkshopsData) ?></span>
        <i class="hb-arrow">▼</i>
    </button>
    <div class="history-section" id="histWs">
        <div class="history-section-header">🕐 סדנאות שהסתיימו — <?= count($pastWorkshopsData) ?> רשומות</div>
        <div style="padding:16px;display:flex;flex-direction:column;gap:10px;">
        <?php foreach ($pastWorkshopsData as $ws):
            $cnt = count($ws['participants']); ?>
        <div class="past-ws-card">
            <div class="ws-card-header" onclick="toggleWsCard(this)">
                <div class="ws-card-title-wrap">
                    <span class="ws-num-badge">#<?= $ws['wsnum'] ?></span>
                    <div class="card-info" style="padding:12px 8px 12px 0;">
                        <div class="ws-card-title" style="color:var(--text3);"><?= htmlspecialchars($ws['WorkshopName']) ?></div>
                        <div class="card-subtitle">📆 <?= htmlspecialchars($ws['Date']) ?> · 📍 <?= htmlspecialchars($ws['Location']) ?></div>
                    </div>
                    <span class="ws-participants-badge">👥 <?= $cnt ?>/<?= $ws['maxParticipants'] ?></span>
                </div>
                <div class="ws-toggle-btn"><span class="tb-ws-label">פתח</span><span class="chevron">▼</span></div>
            </div>
            <div class="ws-card-body">
                <div class="ws-details">
                    <div class="ws-detail-item"><span class="ws-detail-label">תאריך</span><span class="ws-detail-value">📆 <?= htmlspecialchars($ws['Date']) ?></span></div>
                    <div class="ws-detail-item"><span class="ws-detail-label">שעה</span><span class="ws-detail-value">🕐 <?= htmlspecialchars(substr($ws['Time'],0,5)) ?></span></div>
                    <div class="ws-detail-item"><span class="ws-detail-label">מיקום</span><span class="ws-detail-value">📍 <?= htmlspecialchars($ws['Location']) ?></span></div>
                    <div class="ws-detail-item"><span class="ws-detail-label">סה"כ נרשמו</span><span class="ws-detail-value">👥 <?= $cnt ?> / <?= $ws['maxParticipants'] ?></span></div>
                    <?php if (!empty($ws['wsType'])): ?><div class="ws-detail-item"><span class="ws-detail-label">סוג</span><span class="ws-detail-value"><span class="ws-type-pill"><?= htmlspecialchars($ws['wsType']) ?></span></span></div><?php endif; ?>
                </div>
                <div class="ws-participants-header">
                    <span class="ws-participants-title">משתתפים שנרשמו</span>
                    <span class="count-badge"><?= $cnt ?></span>
                </div>
                <?php if (!empty($ws['participants'])): ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>שם פרטי</th><th>שם משפחה</th><th>אימייל</th></tr></thead>
                        <tbody>
                        <?php foreach ($ws['participants'] as $p): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                                <td><?= htmlspecialchars($p['lastname']) ?></td>
                                <td><?= htmlspecialchars($p['email']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="empty-participants">
                        <span class="empty-participants-icon">👤</span>
                        <div>לא היו משתתפים רשומים</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── מבחן סיום ── -->
<?php if (!empty($workshopsData)):
    $allQuestions = [];
    foreach ($workshopsData as $wsItem) {
        $wid = $wsItem['wsnum'];
        $qr  = $conn->query("SELECT id, question_text FROM quiz_questions WHERE wsnum={$wid} ORDER BY id ASC");
        $allQuestions[$wid] = [];
        if ($qr) { while ($qrow = $qr->fetch_assoc()) $allQuestions[$wid][] = $qrow; }
    }
?>
<div class="section-label" style="margin-top:32px;">מבחני סיום</div>
<div class="card">
    <div class="card-header" onclick="toggleCard(this)">
        <div class="card-header-left">
            <div class="card-icon-wrap" style="background:linear-gradient(135deg,#7c3aed,#6366f1)">📝</div>
            <div class="card-info">
                <div class="card-title">ניהול מבחן סיום</div>
                <div class="card-subtitle">בחר סדנה והוסף שאלות אמריקאיות</div>
            </div>
        </div>
        <div class="toggle-btn"><span class="tb-label">פתח</span><span class="chevron">▼</span></div>
    </div>
    <div class="card-body">
        <form method="post" id="quizForm">
            <label class="form-label">בחר סדנה</label>
            <select name="ws_quiz_id" class="quiz-ws-select" id="quizWsSelect"
                    onchange="loadQuizData(this.value)">
                <option value="">— בחר סדנה —</option>
                <?php foreach ($workshopsData as $ws): ?>
                <option value="<?= $ws['wsnum'] ?>">
                    <?= htmlspecialchars($ws['WorkshopName']) ?> (#<?= $ws['wsnum'] ?>)
                </option>
                <?php endforeach; ?>
            </select>

            <div id="existingWrap" style="display:none;margin-bottom:16px;">
                <div style="font-size:.74rem;font-weight:800;color:var(--text3);margin-bottom:8px;letter-spacing:.4px;text-transform:uppercase;">שאלות קיימות</div>
                <div id="existingList"></div>
            </div>

            <div id="newQsWrap"></div>

            <button type="button" class="btn-add-q" id="btnAddQ"
                    style="display:none;" onclick="addQuestion()">
                ➕ הוסף שאלה
            </button>

            <button type="submit" name="save_quiz" class="btn btn-primary"
                    id="btnSaveQuiz" style="display:none;margin-top:6px;">
                💾 שמור שאלות
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

</div>

<script>

// ══ ניהול מבחן ══
var allQ = <?php echo isset($allQuestions) ? json_encode($allQuestions) : '{}'; ?>;
var qCount = 0;

function loadQuizData(wsnum) {
    var existWrap = document.getElementById('existingWrap');
    var existList = document.getElementById('existingList');
    var newWrap   = document.getElementById('newQsWrap');
    var btnAdd    = document.getElementById('btnAddQ');
    var btnSave   = document.getElementById('btnSaveQuiz');

    existList.innerHTML = '';
    newWrap.innerHTML   = '';
    qCount = 0;

    if (!wsnum) {
        existWrap.style.display = 'none';
        btnAdd.style.display    = 'none';
        btnSave.style.display   = 'none';
        return;
    }
    btnAdd.style.display = 'block';

    var questions = allQ[wsnum] || [];
    if (questions.length > 0) {
        existWrap.style.display = 'block';
        questions.forEach(function(q, i) {
            var row = document.createElement('div');
            row.className = 'existing-q';

            var num = document.createElement('div');
            num.className = 'existing-q-num';
            num.textContent = i + 1;
            row.appendChild(num);

            var txt = document.createElement('div');
            txt.className = 'existing-q-text';
            txt.textContent = q.question_text;
            row.appendChild(txt);

            var frm = document.createElement('form');
            frm.method = 'post';
            frm.style.display = 'inline';
            frm.onsubmit = function() { return confirm('למחוק שאלה זו?'); };

            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'question_id';
            inp.value = q.id;
            frm.appendChild(inp);

            var btn = document.createElement('button');
            btn.type = 'submit';
            btn.name = 'delete_question';
            btn.className = 'btn-del';
            btn.textContent = '✕';
            frm.appendChild(btn);

            row.appendChild(frm);
            existList.appendChild(row);
        });
    } else {
        existWrap.style.display = 'none';
    }
}

function addQuestion() {
    var wsnum = document.getElementById('quizWsSelect').value;
    if (!wsnum) { alert('בחר סדנה קודם'); return; }
    document.getElementById('btnSaveQuiz').style.display = 'block';
    qCount++;
    var n = qCount;
    var letters = ['א', 'ב', 'ג', 'ד'];
    var keys    = ['a', 'b', 'c', 'd'];

    var div = document.createElement('div');
    div.className = 'qblock';
    div.id = 'qb_' + n;

    var titleDiv = document.createElement('div');
    titleDiv.className = 'qblock-title';
    titleDiv.textContent = 'שאלה ' + n;
    div.appendChild(titleDiv);

    var qInput = document.createElement('input');
    qInput.type = 'text';
    qInput.name = 'question_text[]';
    qInput.className = 'form-input';
    qInput.placeholder = 'הכנס שאלה...';
    qInput.required = true;
    qInput.style.marginBottom = '8px';
    div.appendChild(qInput);

    var grid = document.createElement('div');
    grid.className = 'answers-grid';
    for (var i = 0; i < 4; i++) {
        var wrap = document.createElement('div');
        var lbl = document.createElement('div');
        lbl.className = 'ans-label-sm';
        lbl.textContent = 'תשובה ' + letters[i];
        wrap.appendChild(lbl);
        var inp = document.createElement('input');
        inp.type = 'text';
        inp.name = 'answer_' + keys[i] + '[]';
        inp.className = 'ans-input';
        inp.placeholder = 'תשובה ' + letters[i] + '...';
        inp.required = true;
        wrap.appendChild(inp);
        grid.appendChild(wrap);
    }
    div.appendChild(grid);

    var correctWrap = document.createElement('div');
    correctWrap.className = 'correct-wrap';
    correctWrap.innerHTML = '<div class="correct-label">✓ תשובה נכונה</div>' +
        '<select name="correct_answer[]" class="correct-select">' +
        '<option value="a">תשובה א</option>' +
        '<option value="b">תשובה ב</option>' +
        '<option value="c">תשובה ג</option>' +
        '<option value="d">תשובה ד</option>' +
        '</select>';
    div.appendChild(correctWrap);

    var delWrap = document.createElement('div');
    delWrap.style.textAlign = 'left';
    delWrap.style.marginTop = '8px';
    var delBtn = document.createElement('button');
    delBtn.type = 'button';
    delBtn.className = 'btn-del-q';
    delBtn.textContent = '✕ הסר שאלה';
    delBtn.setAttribute('data-n', n);
    delBtn.onclick = function() { removeQuestion(this.getAttribute('data-n')); };
    delWrap.appendChild(delBtn);
    div.appendChild(delWrap);

    document.getElementById('newQsWrap').appendChild(div);
}

function removeQuestion(n) {
    var block = document.getElementById('qb_' + n);
    if (block) { block.remove(); }
}

function toggleHistory(sectionId, btnId) {
    var section = document.getElementById(sectionId);
    var btn = document.getElementById(btnId);
    var isOpen = section.classList.toggle('open');
    btn.classList.toggle('open', isOpen);
}

function toggleCard(header) {
    var body = header.nextElementSibling;
    var isOpen = body.classList.contains('open');
    var lbl = header.querySelector('.tb-label');
    body.classList.toggle('open', !isOpen);
    header.classList.toggle('open', !isOpen);
    if (lbl) lbl.textContent = isOpen ? 'פתח' : 'סגור';
}

function toggleWsCard(header) {
    var body = header.nextElementSibling;
    var isOpen = body.classList.contains('open');
    var lbl = header.querySelector('.tb-ws-label');
    body.classList.toggle('open', !isOpen);
    header.classList.toggle('open', !isOpen);
    if (lbl) lbl.textContent = isOpen ? 'פתח' : 'סגור';
}

document.addEventListener('DOMContentLoaded', function() {
    var toast = document.querySelector('.toast');
    if (toast) {
        setTimeout(function() {
            toast.style.transition = 'opacity .5s, transform .5s';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-6px)';
            setTimeout(function() { toast.remove(); }, 500);
        }, 4500);
    }
});
</script>
</body>
</html>
<?php if (isset($conn)) $conn->close(); ?>