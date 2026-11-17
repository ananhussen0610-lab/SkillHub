<?php
session_start();
if (!isset($_SESSION['id'])) { header("Location: login.php"); exit(); }

$userId = $_SESSION['id'];
$firstName = 'מנהל המערכת';
$isAdmin = 0;

$conn = new mysqli('localhost', 'root', '', 'skillhub');
if ($conn->connect_error) { http_response_code(500); echo "שגיאה פנימית."; exit(); }

$s = $conn->prepare("SELECT name, isAdmin FROM users WHERE id = ?");
if ($s) { $s->bind_param("i",$userId); $s->execute(); $r=$s->get_result(); if($r->num_rows===1){$u=$r->fetch_assoc();$isAdmin=$u['isAdmin'];$firstName=htmlspecialchars($u['name']);}$s->close(); }

if ($isAdmin != 1) { header('Location: index.php?error=permission_denied'); $conn->close(); exit(); }

$status_message = '';
if (isset($_SESSION['status_message'])) {
    $msg = $_SESSION['status_message'];
    $class = $msg['type']=='success' ? 'toast-success' : 'toast-error';
    $icon = $msg['type']=='success' ? '✓' : '!';
    $text = htmlspecialchars($msg['text']);
    $status_message = "<div class='toast {$class}'><span class='toast-icon'>{$icon}</span><span>{$text}</span></div>";
    unset($_SESSION['status_message']);
}

// --- POST handlers ---
if (isset($_POST['submit'])) {
    $name=trim($_POST['name']); $lastname=trim($_POST['lastname']); $email=trim($_POST['email']); $phone=trim($_POST['phone']); $pwd=$_POST['password'];
    $sc=$conn->prepare("SELECT id FROM users WHERE email=?"); $sc->bind_param("s",$email); $sc->execute(); $rc=$sc->get_result();
    if($rc->num_rows>0){$_SESSION['status_message']=['type'=>'error','text'=>"דוא\"ל זה כבר רשום."];$sc->close();}
    else{$sc->close();$si=$conn->prepare("INSERT INTO users (name,lastname,email,phone,password,isAdmin) VALUES (?,?,?,?,?,?)");if($si){$ia=2;$si->bind_param("sssssi",$name,$lastname,$email,$phone,$pwd,$ia);if($si->execute())$_SESSION['status_message']=['type'=>'success','text'=>"המרצה נוסף בהצלחה!"];else$_SESSION['status_message']=['type'=>'error','text'=>"שגיאה: ".$si->error];$si->close();}}
    header("Location: admin.php"); exit;
}

if ($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['approveLecturer'])) {
    $uid = intval($_POST['pendingUserId']); $msg = ['type'=>'error','text'=>''];
    if ($uid > 0) { $su = $conn->prepare("UPDATE users SET isAdmin=2, pending_lecturer=0 WHERE id=? AND pending_lecturer=1"); if ($su) { $su->bind_param("i",$uid); $su->execute(); if ($su->affected_rows > 0) { $msg = ['type'=>'success','text'=>"המשתמש אושר כמרצה."]; } else { $msg['text'] = "לא נמצאה בקשה."; } $su->close(); } } else { $msg['text'] = "מזהה לא חוקי."; }
    $_SESSION['status_message'] = $msg; header("Location: admin.php"); exit;
}

if ($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['rejectLecturer'])) {
    $uid = intval($_POST['pendingUserId']); $msg = ['type'=>'error','text'=>''];
    if ($uid > 0) { $su = $conn->prepare("UPDATE users SET pending_lecturer=0 WHERE id=? AND pending_lecturer=1"); if ($su) { $su->bind_param("i",$uid); $su->execute(); if ($su->affected_rows > 0) { $msg = ['type'=>'success','text'=>"הבקשה נדחתה."]; } else { $msg['text'] = "לא נמצאה בקשה."; } $su->close(); } } else { $msg['text'] = "מזהה לא חוקי."; }
    $_SESSION['status_message'] = $msg; header("Location: admin.php"); exit;
}

if ($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['addType'])) {
    $tn=trim($_POST['typeName']); $msg=['type'=>'error','text'=>''];
    if(empty($tn)){$msg['text']="יש להזין שם.";}
    elseif(!preg_match('/^[א-תa-zA-Z0-9\s]+$/u',$tn)){$msg['text']="אותיות ומספרים בלבד.";}
    else{$sc=$conn->prepare("SELECT typeId FROM types WHERE typeName=?");$sc->bind_param("s",$tn);$sc->execute();$sc->store_result();
        if($sc->num_rows>0){$msg['text']="קטגוריה זו קיימת.";}
        else{$st=$conn->prepare("INSERT INTO types (typeName) VALUES (?)");$st->bind_param("s",$tn);if($st->execute()){$msg['type']='success';$msg['text']="קטגוריה נוספה!";}else{$msg['text']="שגיאה: ".$st->error;}$st->close();}
        $sc->close();}
    $_SESSION['status_message']=$msg; header("Location: admin.php"); exit;
}

if ($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['deleteType'])) {
    $tid=intval($_POST['typeId']); $tname=''; $msg=['type'=>'error','text'=>''];
    if($tid<=0){$msg['text']="מזהה לא חוקי.";}
    else{$sg=$conn->prepare("SELECT typeName FROM types WHERE typeId=?");if($sg){$sg->bind_param("i",$tid);$sg->execute();$rg=$sg->get_result();if($rg->num_rows===1)$tname=htmlspecialchars($rg->fetch_assoc()['typeName']);$sg->close();}
        $sce=$conn->prepare("SELECT id FROM events WHERE typeId=? LIMIT 1");if($sce){$sce->bind_param("i",$tid);$sce->execute();$sce->store_result();
            if($sce->num_rows>0){$msg['text']="לא ניתן למחוק — קיימים אירועים.";$sce->close();}
            else{$sce->close();$sd=$conn->prepare("DELETE FROM types WHERE typeId=?");if($sd){$sd->bind_param("i",$tid);if($sd->execute()&&$sd->affected_rows>0){$msg['type']='success';$msg['text']="'{$tname}' נמחקה.";}else{$msg['text']="לא נמצאה.";}$sd->close();}}}}
    $_SESSION['status_message']=$msg; header("Location: admin.php"); exit;
}

if ($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['deleteWorkshop'])) {
    $wid=intval($_POST['workshopId']); $wname=''; $msg=['type'=>'error','text'=>''];
    if($wid<=0){$msg['text']="מזהה לא חוקי.";}
    else{try{$sg=$conn->prepare("SELECT WorkshopName FROM workshop WHERE wsnum=?");if($sg){$sg->bind_param("i",$wid);$sg->execute();$rg=$sg->get_result();if($rg->num_rows===1)$wname=htmlspecialchars($rg->fetch_assoc()['WorkshopName']);$sg->close();}
        $sd=$conn->prepare("DELETE FROM workshop WHERE wsnum=?");if($sd){$sd->bind_param("i",$wid);$sd->execute();if($sd->affected_rows>0){$msg['type']='success';$msg['text']="'{$wname}' נמחקה.";}else{$msg['text']="לא נמצאה.";}$sd->close();}
    }catch(mysqli_sql_exception $e){$msg['text']="שגיאה: ".$e->getMessage();}}
    $_SESSION['status_message']=$msg; header("Location: admin.php"); exit;
}

if ($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['addEvent'])) {
    $en=trim($_POST['eventName']??'');$ed=trim($_POST['eventDate']??'');$et=trim($_POST['eventTime']??'');$tid=intval($_POST['eventTypeId']??0);$lid=intval($_POST['lecturerId']??0);$mp=intval($_POST['maxParticipants']??0);$desc=trim($_POST['eventDescription']??'');$price=floatval(str_replace(',','.',$_POST['eventPrice']??'0'));
    $msg=['type'=>'error','text'=>'']; $valid=true;
    if(empty($en)||empty($ed)||empty($et)||$tid<=0||$lid<=0||$mp<=0){$msg['text']="מלא את כל השדות.";$valid=false;}
    if($price<0){$msg['text']="מחיר לא יכול להיות שלילי.";$valid=false;}
    if($valid){$lname='';$sn=$conn->prepare("SELECT name,lastname FROM users WHERE id=? AND isAdmin=2");if($sn){$sn->bind_param("i",$lid);$sn->execute();$rn=$sn->get_result();if($rn->num_rows===1){$rw=$rn->fetch_assoc();$lname=htmlspecialchars($rw['name'].' '.$rw['lastname']);}$sn->close();}
        $si=$conn->prepare("INSERT INTO events (eventName,eventDate,eventTime,typeId,lecturerId,maxParticipants,description,price) VALUES (?,?,?,?,?,?,?,?)");
        if($si){$si->bind_param("sssiissd",$en,$ed,$et,$tid,$lid,$mp,$desc,$price);if($si->execute()){$priceText=$price>0?number_format($price,2).' ₪':'חינמי';$msg['type']='success';$msg['text']="'{$en}' עם {$lname} נוסף! מחיר: {$priceText}";}else{$msg['text']="שגיאה: ".$si->error;}$si->close();}}
    $_SESSION['status_message']=$msg; header("Location: admin.php"); exit;
}

if ($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['deleteRegistration'])) {
    $rid=intval($_POST['registrationId']); $msg=['type'=>'error','text'=>''];
    if($rid<=0){$msg['text']="מזהה לא חוקי.";}
    else{$sd=$conn->prepare("DELETE FROM event_registrations WHERE reg_id=?");if($sd){$sd->bind_param("i",$rid);if($sd->execute()&&$sd->affected_rows>0){$msg['type']='success';$msg['text']="ההרשמה בוטלה.";}else{$msg['text']="לא נמצאה.";}$sd->close();}}
    $_SESSION['status_message']=$msg; header("Location: admin.php"); exit;
}

if ($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['deleteEvent'])) {
    $eid=intval($_POST['eventId']); $ename=''; $msg=['type'=>'error','text'=>''];
    if($eid<=0){$msg['text']="מזהה לא חוקי.";}
    else{$sg=$conn->prepare("SELECT eventName FROM events WHERE id=?");if($sg){$sg->bind_param("i",$eid);$sg->execute();$rg=$sg->get_result();if($rg->num_rows===1)$ename=htmlspecialchars($rg->fetch_assoc()['eventName']);$sg->close();}
        $sdr=$conn->prepare("DELETE FROM event_registrations WHERE event_id=?");if($sdr){$sdr->bind_param("i",$eid);$sdr->execute();$sdr->close();}
        $sd=$conn->prepare("DELETE FROM events WHERE id=?");if($sd){$sd->bind_param("i",$eid);if($sd->execute()&&$sd->affected_rows>0){$msg['type']='success';$msg['text']="'{$ename}' וכל הרשמותיו נמחקו.";}else{$msg['text']="לא נמצא.";}$sd->close();}}
    $_SESSION['status_message']=$msg; header("Location: admin.php"); exit;
}

if ($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['deleteUser'])) {
    $uid=intval($_POST['userId']); $msg=['type'=>'error','text'=>''];
    if($uid<=0||$uid===$userId){$msg['text']="מזהה לא חוקי.";}
    else{
        // מחק הרשמות לאירועים ולסדנאות
        $conn->query("DELETE FROM event_registrations WHERE user_id=$uid");
        $conn->query("DELETE FROM workshop_registrations WHERE user_id=$uid");
        $sd=$conn->prepare("DELETE FROM users WHERE id=? AND isAdmin=0");
        if($sd){$sd->bind_param("i",$uid);if($sd->execute()&&$sd->affected_rows>0){$msg['type']='success';$msg['text']="המשתמש נמחק.";}else{$msg['text']="לא נמצא.";}$sd->close();}
    }
    $_SESSION['status_message']=$msg; header("Location: admin.php"); exit;
}

if ($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['deleteLecturer'])) {
    $uid=intval($_POST['lecturerId']); $msg=['type'=>'error','text'=>''];
    if($uid<=0||$uid===$userId){$msg['text']="מזהה לא חוקי.";}
    else{
        // מחק נתונים קשורים
        $conn->query("DELETE wr FROM workshop_registrations wr JOIN workshop w ON wr.workshop_id=w.wsnum WHERE w.lecId=$uid");
        $conn->query("DELETE FROM workshop WHERE lecId=$uid");
        $conn->query("DELETE er FROM event_registrations er JOIN events e ON er.event_id=e.id WHERE e.lecturerId=$uid");
        $conn->query("DELETE FROM events WHERE lecturerId=$uid");
        $sd=$conn->prepare("DELETE FROM users WHERE id=? AND isAdmin=2");
        if($sd){$sd->bind_param("i",$uid);if($sd->execute()&&$sd->affected_rows>0){$msg['type']='success';$msg['text']="המרצה נמחק.";}else{$msg['text']="לא נמצא.";}$sd->close();}
    }
    $_SESSION['status_message']=$msg; header("Location: admin.php"); exit;
}

if ($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['deleteWorkshopReg'])) {
    $wid=intval($_POST['wsRegWorkshopId']); $uid=intval($_POST['wsRegUserId']); $msg=['type'=>'error','text'=>''];
    if($wid<=0||$uid<=0){$msg['text']="נתונים לא חוקיים.";}
    else{$sd=$conn->prepare("DELETE FROM workshop_registrations WHERE workshop_id=? AND user_id=?");if($sd){$sd->bind_param("ii",$wid,$uid);if($sd->execute()&&$sd->affected_rows>0){$msg['type']='success';$msg['text']="הנרשם הוסר.";}else{$msg['text']="לא נמצאה.";}$sd->close();}}
    $_SESSION['status_message']=$msg; header("Location: admin.php"); exit;
}

// --- Data ---
$workshop_types=[]; $rt=$conn->query("SELECT typeId,typeName FROM types ORDER BY typeName ASC"); if($rt) while($row=$rt->fetch_assoc()) $workshop_types[]=$row;
$lecturers=[]; $rl=$conn->query("SELECT id,name,lastname FROM users WHERE isAdmin=2 ORDER BY name,lastname ASC"); if($rl) while($row=$rl->fetch_assoc()) $lecturers[]=$row;

// ✅ כל המשתמשים הרגילים
$allUsers=[];
try{$ru=$conn->query("SELECT id,name,lastname,email,phone,created_at,last_login FROM users WHERE isAdmin=0 AND (pending_lecturer IS NULL OR pending_lecturer=0) ORDER BY created_at DESC");if($ru) while($row=$ru->fetch_assoc()) $allUsers[]=$row;}catch(Exception $e){error_log($e->getMessage());}

// ✅ כל המרצים
$allLecturers=[];
try{$rll=$conn->query("SELECT u.id,u.name,u.lastname,u.email,u.phone,u.created_at,u.last_login,COUNT(DISTINCT er.reg_id) AS events_regs,COUNT(DISTINCT wr.id) AS ws_regs FROM users u LEFT JOIN events ev ON ev.lecturerId=u.id LEFT JOIN event_registrations er ON er.event_id=ev.id LEFT JOIN workshop w ON w.lecId=u.id LEFT JOIN workshop_registrations wr ON wr.workshop_id=w.wsnum WHERE u.isAdmin=2 GROUP BY u.id ORDER BY u.name ASC");if($rll) while($row=$rll->fetch_assoc()) $allLecturers[]=$row;}catch(Exception $e){error_log($e->getMessage());}

// ✅ אירועים עתידיים בלבד
$allEvents=[];
try{$re=$conn->query("SELECT e.id AS eventId,e.eventName,e.eventDate,e.eventTime,e.price,t.typeName,u.name AS lecturerName,u.lastname AS lecturerLastname FROM events e JOIN types t ON e.typeId=t.typeId JOIN users u ON e.lecturerId=u.id WHERE CONCAT(e.eventDate,' ',e.eventTime) > NOW() ORDER BY e.eventDate ASC,e.eventTime ASC");if($re) while($row=$re->fetch_assoc()) $allEvents[]=$row;}catch(mysqli_sql_exception $e){error_log($e->getMessage());}

// ✅ אירועים שהסתיימו
$pastEvents=[];
try{$rpe=$conn->query("SELECT e.id AS eventId,e.eventName,e.eventDate,e.eventTime,e.price,t.typeName,u.name AS lecturerName,u.lastname AS lecturerLastname FROM events e JOIN types t ON e.typeId=t.typeId JOIN users u ON e.lecturerId=u.id WHERE CONCAT(e.eventDate,' ',e.eventTime) <= NOW() ORDER BY e.eventDate DESC,e.eventTime DESC");if($rpe) while($row=$rpe->fetch_assoc()) $pastEvents[]=$row;}catch(mysqli_sql_exception $e){error_log($e->getMessage());}

// ✅ סדנאות עתידיות בלבד
$allWorkshops=[];
try{$rw=$conn->query("SELECT w.wsnum,w.WorkshopName,w.date,IFNULL(t.typeName,w.Type) AS wsType,u.name AS lecturerName,u.lastname AS lecturerLastname FROM workshop w JOIN users u ON w.lecId=u.id LEFT JOIN types t ON CAST(t.typeId AS CHAR)=w.Type OR t.typeName=w.Type WHERE w.date > CURDATE() OR (w.date=CURDATE() AND IFNULL(w.time,'23:59:59') > CURTIME()) ORDER BY w.date ASC");if($rw) while($row=$rw->fetch_assoc()) $allWorkshops[]=$row;}catch(mysqli_sql_exception $e){if($e->getCode()!=1146)error_log($e->getMessage());}

// ✅ סדנאות שהסתיימו
$pastWorkshops=[];
try{$rpw=$conn->query("SELECT w.wsnum,w.WorkshopName,w.date,IFNULL(t.typeName,w.Type) AS wsType,u.name AS lecturerName,u.lastname AS lecturerLastname FROM workshop w JOIN users u ON w.lecId=u.id LEFT JOIN types t ON CAST(t.typeId AS CHAR)=w.Type OR t.typeName=w.Type WHERE w.date < CURDATE() OR (w.date=CURDATE() AND IFNULL(w.time,'23:59:59') <= CURTIME()) ORDER BY w.date DESC");if($rpw) while($row=$rpw->fetch_assoc()) $pastWorkshops[]=$row;}catch(mysqli_sql_exception $e){if($e->getCode()!=1146)error_log($e->getMessage());}

// ✅ הרשמות לאירועים עתידיים בלבד
$allRegistrations=[]; try{$rr=$conn->query("SELECT r.reg_id AS registrationId,u.name AS userName,u.lastname AS userLastname,u.email AS userEmail,e.eventName,e.eventDate,e.eventTime,t.typeName FROM event_registrations r JOIN users u ON r.user_id=u.id JOIN events e ON r.event_id=e.id JOIN types t ON e.typeId=t.typeId WHERE CONCAT(e.eventDate,' ',e.eventTime) > NOW() ORDER BY e.eventDate ASC,e.eventTime ASC");if($rr) while($row=$rr->fetch_assoc()) $allRegistrations[]=$row;}catch(mysqli_sql_exception $e){error_log($e->getMessage());}

// ✅ הרשמות לאירועים שהסתיימו
$pastRegistrations=[]; try{$rpr=$conn->query("SELECT r.reg_id AS registrationId,u.name AS userName,u.lastname AS userLastname,u.email AS userEmail,e.eventName,e.eventDate,e.eventTime,IFNULL(t.typeName,'—') AS typeName FROM event_registrations r JOIN users u ON r.user_id=u.id JOIN events e ON r.event_id=e.id LEFT JOIN types t ON e.typeId=t.typeId WHERE e.eventDate < CURDATE() OR (e.eventDate = CURDATE() AND e.eventTime <= CURTIME()) ORDER BY e.eventDate DESC,e.eventTime DESC");if($rpr) while($row=$rpr->fetch_assoc()) $pastRegistrations[]=$row;}catch(mysqli_sql_exception $e){error_log($e->getMessage());}

// ✅ הרשמות לסדנאות עתידיות בלבד
$workshopRegistrations=[]; try{$rwr=$conn->query("SELECT wr.workshop_id,wr.user_id,u.name AS userName,u.lastname AS userLastname,u.email AS userEmail,w.WorkshopName,w.date AS workshopDate,IFNULL(t.typeName,w.Type) AS wsType FROM workshop_registrations wr JOIN users u ON wr.user_id=u.id JOIN workshop w ON wr.workshop_id=w.wsnum LEFT JOIN types t ON CAST(t.typeId AS CHAR)=w.Type OR t.typeName=w.Type WHERE w.date > CURDATE() OR (w.date = CURDATE() AND IFNULL(w.time,'23:59:59') > CURTIME()) ORDER BY w.date ASC,u.lastname ASC");if($rwr) while($row=$rwr->fetch_assoc()) $workshopRegistrations[]=$row;}catch(mysqli_sql_exception $e){error_log($e->getMessage());}

// ✅ הרשמות לסדנאות שהסתיימו
$pastWsRegistrations=[]; try{$rpwr=$conn->query("SELECT wr.workshop_id,wr.user_id,u.name AS userName,u.lastname AS userLastname,u.email AS userEmail,w.WorkshopName,w.date AS workshopDate,IFNULL(t.typeName,w.Type) AS wsType FROM workshop_registrations wr JOIN users u ON wr.user_id=u.id JOIN workshop w ON wr.workshop_id=w.wsnum LEFT JOIN types t ON CAST(t.typeId AS CHAR)=w.Type OR t.typeName=w.Type WHERE w.date < CURDATE() OR (w.date = CURDATE() AND IFNULL(w.time,'23:59:59') <= CURTIME()) ORDER BY w.date DESC,u.lastname ASC");if($rpwr) while($row=$rpwr->fetch_assoc()) $pastWsRegistrations[]=$row;}catch(mysqli_sql_exception $e){error_log($e->getMessage());}

$pendingLecturers = [];
try { $rp = $conn->query("SELECT id, name, lastname, email, phone FROM users WHERE pending_lecturer=1 AND isAdmin=0 ORDER BY id DESC"); if ($rp) while ($row=$rp->fetch_assoc()) $pendingLecturers[] = $row; } catch(Exception $e) { error_log($e->getMessage()); }

// Statistics
$heMonths=['01'=>'ינואר','02'=>'פברואר','03'=>'מרץ','04'=>'אפריל','05'=>'מאי','06'=>'יוני','07'=>'יולי','08'=>'אוגוסט','09'=>'ספטמבר','10'=>'אוקטובר','11'=>'נובמבר','12'=>'דצמבר'];
$evMonths=[];
try{$reM=$conn->query("SELECT DATE_FORMAT(e.eventDate,'%Y-%m') AS ym,COUNT(r.reg_id) AS cnt FROM events e LEFT JOIN event_registrations r ON r.event_id=e.id WHERE e.eventDate>=DATE_SUB(CURDATE(),INTERVAL 36 MONTH) GROUP BY ym ORDER BY ym ASC");if($reM) while($row=$reM->fetch_assoc()) $evMonths[$row['ym']]=(int)$row['cnt'];}catch(Exception $e){error_log($e->getMessage());}
$wsMonths=[];
try{$rwM=$conn->query("SELECT DATE_FORMAT(w.date,'%Y-%m') AS ym,COUNT(wr.user_id) AS cnt FROM workshop w LEFT JOIN workshop_registrations wr ON wr.workshop_id=w.wsnum WHERE w.date>=DATE_SUB(CURDATE(),INTERVAL 36 MONTH) GROUP BY ym ORDER BY ym ASC");if($rwM) while($row=$rwM->fetch_assoc()) $wsMonths[$row['ym']]=(int)$row['cnt'];}catch(Exception $e){error_log($e->getMessage());}
$evRevMonths=[];
try{$reR=$conn->query("SELECT DATE_FORMAT(e.eventDate,'%Y-%m') AS ym, SUM(e.price) AS rev FROM events e JOIN event_registrations r ON r.event_id = e.id GROUP BY ym ORDER BY ym ASC");if($reR) while($row=$reR->fetch_assoc()) $evRevMonths[$row['ym']]=round((float)$row['rev'],2);}catch(Exception $e){error_log($e->getMessage());}
$wsRevMonths=[];
try{$rwR=$conn->query("SELECT DATE_FORMAT(w.date,'%Y-%m') AS ym, SUM(w.price) AS rev FROM workshop w JOIN workshop_registrations wr ON wr.workshop_id = w.wsnum GROUP BY ym ORDER BY ym ASC");if($rwR) while($row=$rwR->fetch_assoc()) $wsRevMonths[$row['ym']]=round((float)$row['rev'],2);}catch(Exception $e){error_log($e->getMessage());}
$allMonths = [];
for($i=35;$i>=0;$i--){$ym=date('Y-m',strtotime("-$i months"));[$y,$m]=explode('-',$ym);$allMonths[]=['ym'=>$ym,'label'=>$heMonths[$m].' '.$y,'ev'=>$evMonths[$ym]??0,'ws'=>$wsMonths[$ym]??0,'evRev'=>$evRevMonths[$ym]??0,'wsRev'=>$wsRevMonths[$ym]??0];}
$categories=[];
try{$rCat=$conn->query("
    SELECT t.typeName AS name,
        (SELECT COUNT(r.reg_id) FROM events e LEFT JOIN event_registrations r ON r.event_id=e.id WHERE e.typeId=t.typeId) +
        (SELECT COUNT(wr.user_id) FROM workshop w LEFT JOIN workshop_registrations wr ON wr.workshop_id=w.wsnum WHERE CAST(w.Type AS CHAR)=CAST(t.typeId AS CHAR) OR w.Type=t.typeName) AS cnt
    FROM types t ORDER BY cnt DESC LIMIT 6
");if($rCat) while($row=$rCat->fetch_assoc()) $categories[]=['name'=>$row['name'],'count'=>(int)$row['cnt']];}catch(Exception $e){error_log($e->getMessage());}
$topLecturers=[];
try{$rLec=$conn->query("SELECT u.name,u.lastname,(SELECT COUNT(*) FROM event_registrations r JOIN events e2 ON r.event_id=e2.id WHERE e2.lecturerId=u.id) AS events_reg,(SELECT COUNT(*) FROM workshop_registrations wr JOIN workshop w2 ON wr.workshop_id=w2.wsnum WHERE w2.lecId=u.id) AS ws_reg FROM users u WHERE u.isAdmin=2 ORDER BY (events_reg+ws_reg) DESC LIMIT 5");if($rLec) while($row=$rLec->fetch_assoc()) $topLecturers[]=['name'=>htmlspecialchars($row['name'].' '.$row['lastname']),'events'=>(int)$row['events_reg'],'ws'=>(int)$row['ws_reg']];}catch(Exception $e){error_log($e->getMessage());}
$statsJson = json_encode(['allMonths'=>$allMonths,'categories'=>$categories,'lecturers'=>$topLecturers], JSON_UNESCAPED_UNICODE);
$defaultFrom = date('Y-m', strtotime('-11 months'));
$defaultTo   = date('Y-m');
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SkillHub — ניהול מערכת</title>
<link rel="icon" type="image/png" href="images/skillhub2.jpeg">
<link href="https://fonts.googleapis.com/css2?family=Frank+Ruhl+Libre:wght@400;500;700;900&family=Heebo:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

/* ── LIGHT THEME ── */
:root{
    --navy:#1e2d4a;
    --gold:#1a1a1a;
    --gold-light:#333333;
    --red:#dc2626;
    --green:#16a34a;
    --bg:#f0f4f8;
    --surface:#ffffff;
    --surface2:#f8fafc;
    --border:#e2e8f0;
    --border2:#cbd5e1;
    --text:#1e293b;
    --text2:#475569;
    --text3:#94a3b8;
    --shadow:0 1px 3px rgba(0,0,0,.08),0 4px 16px rgba(0,0,0,.06);
    --shadow-lg:0 4px 24px rgba(0,0,0,.10);
}

html{scroll-behavior:smooth}
body{font-family:'Heebo',sans-serif;direction:rtl;min-height:100vh;background:var(--bg);color:var(--text);}

.wrapper{max-width:1200px;margin:0 auto;padding:0 24px 80px;}

/* ── HEADER ── */
.site-header{
    display:flex;align-items:center;justify-content:space-between;
    padding:20px 0;margin-bottom:28px;
    border-bottom:1px solid var(--border);
    background:var(--surface);
    position:sticky;top:0;z-index:10;
    margin-left:-24px;margin-right:-24px;
    padding-left:24px;padding-right:24px;
    box-shadow:var(--shadow);
}
.logo{font-family:'Frank Ruhl Libre',serif;font-size:2rem;font-weight:900;color:var(--navy);display:flex;align-items:center;gap:10px;}
.logo-dot{width:10px;height:10px;border-radius:50%;background:linear-gradient(135deg,#1a1a1a,#333333);}
.header-right{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.welcome-chip{padding:8px 14px;border-radius:10px;color:var(--text2);background:var(--surface2);border:1px solid var(--border);font-size:.88rem;}
.welcome-chip strong{color:var(--navy);}
.nav-btn{padding:9px 16px;border-radius:10px;text-decoration:none;font-weight:700;font-size:.88rem;border:1px solid var(--border);transition:.2s ease;color:var(--text2);background:var(--surface);}
.nav-btn:hover{background:var(--surface2);border-color:var(--border2);}
.nav-btn.btn-home{background:var(--surface2);border-color:var(--border2);color:var(--navy);}
.nav-btn.btn-home:hover{background:var(--navy);color:#fff !important;border-color:var(--navy);}
.btn-logout{color:var(--red);border-color:#fecaca;background:#fff5f5;}
.btn-logout:hover{background:#fee2e2;}

/* ── TOAST ── */
.toast{display:flex;align-items:center;gap:10px;padding:14px 16px;border-radius:12px;margin-bottom:20px;font-weight:700;border:1px solid;}
.toast-success{background:#f0fdf4;border-color:#86efac;color:#15803d;}
.toast-error{background:#fef2f2;border-color:#fca5a5;color:#dc2626;}
.toast-icon{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.08);}

/* ── SECTION LABEL ── */
.section-label{display:flex;align-items:center;gap:12px;margin:32px 0 18px;color:var(--text3);font-size:.78rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;}
.section-label::before,.section-label::after{content:"";height:1px;flex:1;background:var(--border);}

/* ── GRIDS ── */
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:20px;}
.grid-1{display:grid;grid-template-columns:1fr;gap:18px;margin-bottom:20px;}
.col-stack{display:flex;flex-direction:column;gap:18px;}

/* ── CARD ── */
.card{overflow:hidden;border-radius:14px;background:var(--surface);border:1px solid var(--border);box-shadow:var(--shadow);transition:.2s ease;}
.card:hover{box-shadow:var(--shadow-lg);}
.card-header{
    display:flex;align-items:center;justify-content:space-between;gap:12px;
    padding:0;cursor:pointer;user-select:none;
    transition:.15s ease;
}
.card-header:hover{background:#fafbfc;}
.card-header.open{border-bottom:2px solid var(--border);}
.card-header-left{display:flex;align-items:center;gap:0;flex:1;}
.card-icon-wrap{
    width:56px;height:56px;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;
    font-size:1.3rem;
    background:linear-gradient(135deg,#1a1a1a,#333333);
    color:#fff;
}
.card-icon-wrap.pending{background:linear-gradient(135deg,#f97316,#ef4444);}
.card-icon-wrap.blue{background:linear-gradient(135deg,#3b82f6,#6366f1);}
.card-info{padding:14px 16px;flex:1;}
.card-title{font-family:'Frank Ruhl Libre',serif;color:var(--navy);font-size:1rem;font-weight:900;line-height:1.2;}
.card-subtitle{color:var(--text3);font-size:.76rem;margin-top:3px;display:flex;align-items:center;gap:6px;}
.toggle-btn{
    display:flex;align-items:center;gap:5px;
    padding:0 20px;height:56px;
    color:var(--text3);background:transparent;border:none;border-right:1px solid var(--border);
    font-weight:700;font-size:.8rem;white-space:nowrap;
    transition:.15s ease;flex-shrink:0;
}
.card-header:hover .toggle-btn{color:#1a1a1a;}
.card-header.open .toggle-btn{color:#1a1a1a;border-bottom:none;}
.chevron{transition:.25s ease;display:inline-block;font-size:.7rem;}
.card-header.open .chevron{transform:rotate(180deg);}
.card-body{display:none;padding:0;}
.card-body.open{display:block;animation:bodyOpen .2s ease;}
@keyframes bodyOpen{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:translateY(0)}}

/* card-body with padding (forms) */
.card-body-pad{padding:20px;}
.tb-label{font-size:.78rem;font-weight:700;}

/* count badge blue variant */
.count-badge.blue{background:linear-gradient(90deg,#3b82f6,#6366f1);}

/* search-users inside card — no padding, full-width bar */
.search-users{
    display:block;width:100%;
    padding:11px 16px;
    border-radius:0;border:none;border-bottom:1px solid var(--border);
    background:#fafbfd;color:var(--text);
    font-family:'Heebo',sans-serif;font-size:.87rem;outline:none;transition:.2s;
}
.search-users:focus{background:#fff;border-bottom-color:#1a1a1a;}

/* ── TABLE ── */
.table-wrap{overflow-x:auto;border-radius:0;}
table{width:100%;border-collapse:collapse;min-width:680px;}
thead tr{background:linear-gradient(180deg,#f1f5f9,#e8edf2);}
th{
    padding:11px 16px;text-align:right;
    color:#64748b;font-size:.72rem;font-weight:800;
    border-bottom:2px solid var(--border);
    letter-spacing:.4px;text-transform:uppercase;
    white-space:nowrap;
}
td{padding:13px 16px;color:var(--text2);border-bottom:1px solid #f1f5f9;font-size:.87rem;vertical-align:middle;}
tbody tr:last-child td{border-bottom:none;}
td strong{color:var(--navy);font-weight:700;}
tbody tr{transition:.12s ease;}
tbody tr:hover{background:#f8fafd;}
.td-c{text-align:center;}
.event-tag,.ws-tag,.price-tag-free,.price-tag-paid{display:inline-flex;padding:3px 9px;border-radius:20px;margin-top:3px;font-size:.7rem;font-weight:700;}
.event-tag,.price-tag-paid{color:#111827;background:#f3f4f6;border:1px solid #d1d5db;}
.ws-tag,.price-tag-free{color:#166534;background:#dcfce7;border:1px solid #86efac;}
.mono{font-family:'Courier New',monospace;color:var(--text3);font-size:.81rem;}
.note-warn{padding:10px 14px;border-radius:0;margin:0 0 0 0;color:#111827;background:#f9fafb;border-bottom:1px solid #d1d5db;font-weight:700;font-size:.84rem;}
.empty{text-align:center;padding:48px 20px;color:var(--text3);}
.empty-ico{font-size:2.5rem;margin-bottom:10px;opacity:.4;display:block;}
.history-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    margin-top: 0;
    padding: 11px 16px;
    border-radius: 0;
    border: none;
    border-top: 2px dashed var(--border2);
    background: #fafbfd;
    color: var(--text3);
    font-family: 'Heebo', sans-serif;
    font-size: .82rem;
    font-weight: 700;
    cursor: pointer;
    transition: .2s ease;
}
.history-btn:hover {
    background: #f9fafb;
    border-top-color: var(--gold);
    color: var(--gold);
}
.history-btn.open {
    background: #f9fafb;
    border-top-style: solid;
    border-top-color: #d1d5db;
    color: #111827;
}
.history-btn .hb-arrow {
    display: inline-block;
    transition: transform .25s ease;
    font-style: normal;
    font-size: .7rem;
}
.history-btn.open .hb-arrow { transform: rotate(180deg); }
.history-count-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 1px 8px;
    border-radius: 999px;
    background: var(--border2);
    color: var(--text2);
    font-size: .7rem;
    font-weight: 800;
}
.history-btn.open .history-count-pill {
    background: #d1d5db;
    color: #111827;
}

/* ── HISTORY SECTION ── */
.history-section {
    display: none;
    margin-top: 0;
    border-radius: 0;
    overflow: hidden;
    border-top: 1px solid #d1d5db;
}
.history-section.open {
    display: block;
    animation: bodyOpen .2s ease;
}
.history-section-header {
    padding: 8px 16px;
    background: #f9fafb;
    border-bottom: 1px solid #d1d5db;
    font-size: .72rem;
    font-weight: 800;
    color: #111827;
    display: flex;
    align-items: center;
    gap: 8px;
    letter-spacing: .3px;
    text-transform: uppercase;
}
.past-row td { opacity: .6; color: var(--text3); }
.past-row:hover td { opacity: 1; color: var(--text2); }

/* ── FORM ── */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 16px;}
.form-group{margin-bottom:14px;}
.form-label{display:block;margin-bottom:6px;color:var(--text);font-size:.84rem;font-weight:700;}
.form-input{width:100%;padding:10px 13px;border-radius:10px;border:1px solid var(--border2);background:#fff;color:var(--text);font-family:'Heebo',sans-serif;font-size:.93rem;outline:none;transition:.2s ease;}
.form-input::placeholder{color:var(--text3);}
.form-input:focus{border-color:#1a1a1a;box-shadow:0 0 0 3px rgba(0,0,0,.07);}
select.form-input option{color:var(--text);}
.price-hint{margin-top:5px;font-size:.76rem;color:var(--text3);}
.price-hint.free{color:var(--green);font-weight:700;}

/* ── BUTTONS ── */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;border:0;cursor:pointer;text-decoration:none;font-family:'Heebo',sans-serif;font-weight:700;border-radius:10px;transition:.2s ease;white-space:nowrap;font-size:.88rem;}
.btn-primary{width:100%;padding:11px 18px;color:#fff;background:linear-gradient(90deg,#1a1a1a,#333333);box-shadow:0 4px 14px rgba(0,0,0,.12);}
.btn-primary:hover{opacity:.9;transform:translateY(-1px);}
.btn-danger{padding:7px 12px;color:var(--red);background:#fef2f2;border:1px solid #fecaca;}
.btn-danger:hover{background:var(--red);color:#fff;border-color:var(--red);}
.btn-approve{padding:7px 12px;color:var(--green);background:#f0fdf4;border:1px solid #86efac;font-weight:700;border-radius:9px;cursor:pointer;font-family:'Heebo',sans-serif;transition:.2s ease;font-size:.88rem;}
.btn-approve:hover{background:var(--green);color:#fff;border-color:var(--green);}

/* ── TYPE LIST ── */
.type-list{display:flex;flex-direction:column;gap:6px;padding:4px 0;}
.type-item{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 14px;border-radius:8px;background:var(--surface2);border:1px solid var(--border);}
.type-left{display:flex;align-items:center;gap:8px;}
.type-bullet{width:7px;height:7px;border-radius:50%;background:#1a1a1a;}
.type-name{color:var(--navy);font-weight:700;font-size:.9rem;}
.count-badge{display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;padding:0 7px;border-radius:999px;background:linear-gradient(90deg,#1a1a1a,#333333);color:#fff;font-size:.72rem;font-weight:900;}
.count-badge.red{background:linear-gradient(90deg,#f97316,#ef4444);}

/* ── PENDING ── */
.pending-item{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-radius:12px;background:#fff7ed;border:1px solid #fed7aa;margin-bottom:8px;flex-wrap:wrap;}
.pending-info{flex:1;}
.pending-name{font-weight:800;color:var(--navy);font-size:.95rem;margin-bottom:2px;}
.pending-meta{font-size:.78rem;color:var(--text3);}
.pending-actions{display:flex;gap:8px;flex-shrink:0;}

/* ══ ADMIN HERO BANNER ══ */
.admin-hero{
    position:relative;overflow:hidden;
    background:linear-gradient(135deg,rgba(15,23,42,.82) 0%,rgba(30,45,74,.75) 100%),
               url('images/workshop1.png') center/cover no-repeat;
    padding:36px 40px 38px;
    display:flex;align-items:center;justify-content:space-between;gap:24px;
    flex-wrap:wrap;margin-bottom:24px;
}
.admin-hero::before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 80% 50%,rgba(255,255,255,.04),transparent 50%);pointer-events:none;}
.admin-hero-left{position:relative;z-index:2;}
.admin-hero-greeting{font-size:.78rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(255,255,255,.5);margin-bottom:6px;}
.admin-hero-title{font-family:'Frank Ruhl Libre',serif;font-size:clamp(1.6rem,3vw,2.2rem);font-weight:900;color:#fff;line-height:1.1;margin-bottom:6px;}
.admin-hero-sub{color:rgba(255,255,255,.5);font-size:.85rem;}
.admin-hero-stats{position:relative;z-index:2;display:flex;gap:10px;flex-wrap:wrap;}
.admin-hero-stat{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);backdrop-filter:blur(8px);border-radius:12px;padding:12px 16px;text-align:center;min-width:80px;}
.admin-hero-stat-num{font-family:'Frank Ruhl Libre',serif;font-size:1.5rem;font-weight:900;color:#fff;line-height:1;}
.admin-hero-stat-label{font-size:.68rem;font-weight:700;color:rgba(255,255,255,.5);letter-spacing:.4px;text-transform:uppercase;margin-top:3px;}

/* ── STATS WRAP ── */
.stats-wrap{border-radius:16px;background:var(--surface);border:1px solid var(--border);box-shadow:var(--shadow);padding:24px;margin-bottom:20px;}

/* ── DATE FILTER ── */
.stats-filter{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:14px 16px;border-radius:12px;margin-bottom:20px;background:var(--surface2);border:1px solid var(--border);}
.stats-filter label{font-size:.8rem;color:var(--text3);font-weight:700;white-space:nowrap;}
.filter-input{padding:8px 12px;border-radius:9px;border:1px solid var(--border2);background:#fff;color:var(--text);font-family:'Heebo',sans-serif;font-size:.85rem;outline:none;transition:.2s;}
.filter-input:focus{border-color:#1a1a1a;box-shadow:0 0 0 3px rgba(217,119,6,.1);}
.filter-presets{display:flex;gap:6px;flex-wrap:wrap;margin-right:auto;}
.preset-btn{padding:6px 12px;border-radius:8px;border:1px solid var(--border);background:#fff;color:var(--text3);font-family:'Heebo',sans-serif;font-size:.78rem;font-weight:700;cursor:pointer;transition:.2s;}
.preset-btn:hover,.preset-btn.active{background:#f9fafb;border-color:#d1d5db;color:#1a1a1a;}

/* ── KPI CARDS ── */
.stats-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:22px;}
.kpi-card{padding:16px 18px;border-radius:12px;background:var(--surface2);border:1px solid var(--border);}
.kpi-label{font-size:.74rem;color:var(--text3);margin-bottom:6px;font-weight:600;}
.kpi-val{font-size:1.7rem;font-weight:900;color:var(--navy);font-family:'Frank Ruhl Libre',serif;}
.kpi-sub{font-size:.72rem;color:var(--text3);margin-top:3px;}
.kpi-up{color:var(--green);}.kpi-down{color:var(--red);}

/* ── CHART BOXES ── */
.stats-charts{display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:20px;}
.chart-box{padding:18px;border-radius:12px;background:var(--surface2);border:1px solid var(--border);}
.chart-box-title{font-size:.78rem;color:var(--text3);margin-bottom:12px;font-weight:700;}
.chart-legend{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:8px;justify-content:flex-end;}
.chart-legend span{display:flex;align-items:center;gap:4px;font-size:.72rem;color:var(--text3);}
.legend-sq{width:8px;height:8px;border-radius:2px;flex-shrink:0;}
.stats-bottom{display:grid;grid-template-columns:1fr 1fr;gap:16px;}

/* ── MONTH TABLE ── */
.month-tbl{width:100%;border-collapse:collapse;font-size:.78rem;}
.month-tbl th{color:var(--text3);text-align:right;padding:6px 10px;border-bottom:1px solid var(--border);font-weight:600;font-size:.72rem;}
.month-tbl td{padding:7px 10px;color:var(--text2);border-bottom:1px solid var(--border);}
.month-tbl td strong{color:var(--navy);}
.month-tbl tr:last-child td{border-bottom:none;}
.month-tbl tbody tr:hover{background:#f8fafc;}

/* ── LECTURERS ── */
.lec-row{display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--border);font-size:.8rem;}
.lec-row:last-child{border-bottom:none;}
.lec-name{color:var(--navy);font-weight:700;}
.lec-badges{display:flex;gap:5px;align-items:center;}
.badge-ev{color:#111827;background:#f3f4f6;border:1px solid #d1d5db;border-radius:7px;padding:2px 8px;font-size:.7rem;font-weight:700;}
.badge-ws{color:#166534;background:#dcfce7;border:1px solid #86efac;border-radius:7px;padding:2px 8px;font-size:.7rem;font-weight:700;}
.badge-tot{color:#fff;background:linear-gradient(90deg,#1a1a1a,#333333);border-radius:999px;padding:2px 10px;font-size:.7rem;font-weight:900;}
.bar-spark{display:inline-block;height:5px;border-radius:3px;vertical-align:middle;margin-left:4px;}

/* ── REVENUE ── */
.rev-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:18px 0;}
.rev-card{padding:16px 18px;border-radius:12px;background:#f0fdf4;border:1px solid #86efac;}
.rev-card.ev{background:#eff6ff;border-color:#93c5fd;}
.rev-card.gold{background:#f9fafb;border-color:#d1d5db;}
.rev-card.purple{background:#faf5ff;border-color:#d8b4fe;}
.rev-label{font-size:.74rem;color:var(--text3);margin-bottom:5px;font-weight:600;}
.rev-val{font-size:1.4rem;font-weight:900;color:#166534;font-family:'Frank Ruhl Libre',serif;}
.rev-val.ev{color:#1d4ed8;}
.rev-val.gold{color:#111827;}
.rev-val.purple{color:#7c3aed;}
.rev-sub{font-size:.72rem;color:var(--text3);margin-top:3px;}
.rev-tbl{width:100%;border-collapse:collapse;font-size:.8rem;}
.rev-tbl th{color:var(--text3);text-align:right;padding:7px 10px;border-bottom:1px solid var(--border);font-weight:600;font-size:.73rem;}
.rev-tbl td{padding:8px 10px;color:var(--text2);border-bottom:1px solid var(--border);}
.rev-tbl td strong{color:var(--navy);}
.rev-tbl tr:last-child td{border-bottom:none;}
.rev-tbl tbody tr:hover{background:#f8fafc;}
.rev-positive{color:var(--green);font-weight:700;}
.rev-zero{color:var(--text3);}
.rev-separator{height:1px;background:var(--border);margin:20px 0;}

/* ── INVOICE BUTTON ── */
.btn-invoice{padding:9px 16px;border-radius:10px;border:1px solid #d1d5db;background:#f9fafb;color:#111827;font-family:'Heebo',sans-serif;font-size:.85rem;font-weight:700;cursor:pointer;transition:.2s ease;display:inline-flex;align-items:center;gap:6px;}
.btn-invoice:hover{background:linear-gradient(90deg,#1a1a1a,#333333);color:#fff;border-color:transparent;transform:translateY(-1px);box-shadow:0 6px 18px rgba(0,0,0,.12);}

/* ── PRINT ── */
@media print{
    body *{visibility:hidden!important;}
    #invoiceModal,#invoiceModal *{visibility:visible!important;}
    #invoiceModal{position:fixed!important;inset:0!important;background:white!important;z-index:99999!important;display:block!important;padding:0!important;}
    .invoice-close-btn,.inv-print-btn{display:none!important;}
}

/* ── INVOICE MODAL ── */
.invoice-overlay{position:fixed;inset:0;z-index:9998;display:none;align-items:center;justify-content:center;background:rgba(15,23,42,.6);backdrop-filter:blur(6px);}
.invoice-overlay.active{display:flex;}
#invoiceModal{width:90%;max-width:700px;max-height:90vh;overflow-y:auto;background:white;border-radius:16px;padding:0;box-shadow:0 20px 60px rgba(0,0,0,.25);font-family:'Heebo',sans-serif;direction:rtl;color:#1a1a1a;}
.inv-header{background:linear-gradient(135deg,#1e2d4a,#2c4165);padding:26px 32px;border-radius:16px 16px 0 0;display:flex;justify-content:space-between;align-items:flex-start;}
.inv-logo{font-family:'Frank Ruhl Libre',serif;font-size:1.7rem;font-weight:900;color:white;}
.inv-logo span{color:#333333;}
.inv-header-meta{text-align:left;color:rgba(255,255,255,.7);font-size:.8rem;line-height:1.7;}
.inv-header-meta strong{color:white;}
.inv-body{padding:26px 32px;}
.inv-title{font-size:1rem;font-weight:800;color:#1e2d4a;margin-bottom:18px;padding-bottom:10px;border-bottom:2px solid #f0e8d8;}
.inv-kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:22px;}
.inv-kpi{padding:12px 14px;border-radius:10px;background:#fdf8f0;border:1px solid #e8d8c0;text-align:center;}
.inv-kpi-label{font-size:.72rem;color:#9b856a;margin-bottom:4px;font-weight:600;}
.inv-kpi-val{font-size:1.2rem;font-weight:900;color:#1e2d4a;}
.inv-kpi-val.green{color:#4a7c59;}
.inv-kpi-val.gold{color:#111827;}
.inv-tbl{width:100%;border-collapse:collapse;font-size:.83rem;margin-bottom:20px;}
.inv-tbl th{padding:9px 12px;text-align:right;background:#1e2d4a;color:rgba(255,255,255,.85);font-size:.74rem;}
.inv-tbl th:first-child{border-radius:0 8px 0 0;}.inv-tbl th:last-child{border-radius:8px 0 0 0;}
.inv-tbl td{padding:8px 12px;border-bottom:1px solid #f0e8d8;color:#5c4a35;}
.inv-tbl td strong{color:#1e2d4a;}
.inv-tbl tr:nth-child(even) td{background:#fdf8f0;}
.inv-tbl tfoot td{padding:10px 12px;background:#1e2d4a;color:white;font-weight:800;}
.inv-footer{padding:14px 32px 24px;border-top:1px solid #f0e8d8;display:flex;justify-content:space-between;align-items:center;font-size:.76rem;color:#9b856a;}
.invoice-close-btn{padding:8px 16px;border-radius:9px;border:1px solid #d9c9b0;background:#fdf8f0;color:#5c4a35;font-weight:700;cursor:pointer;font-family:'Heebo',sans-serif;font-size:.82rem;transition:.2s;}
.invoice-close-btn:hover{background:#1e2d4a;color:white;border-color:#1e2d4a;}
.inv-print-btn{padding:8px 16px;border-radius:9px;border:none;background:linear-gradient(90deg,#1a1a1a,#333333);color:#fff;font-weight:800;cursor:pointer;font-family:'Heebo',sans-serif;font-size:.82rem;transition:.2s;}
.inv-print-btn:hover{opacity:.9;transform:translateY(-1px);}

/* ── DELETE MODAL ── */
.modal-overlay{position:fixed;inset:0;z-index:9999;display:none;align-items:center;justify-content:center;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);}
.modal-overlay.active{display:flex;}
.modal-box{width:90%;max-width:420px;padding:28px;border-radius:16px;background:#fff;border:1px solid var(--border);box-shadow:var(--shadow-lg);}
.modal-head{display:flex;align-items:center;gap:12px;margin-bottom:16px;}
.modal-ico{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#fef2f2;color:var(--red);font-size:1.3rem;}
.modal-title{font-family:'Frank Ruhl Libre',serif;font-size:1.2rem;color:var(--navy);font-weight:900;}
.modal-sub{color:var(--text3);font-size:.8rem;}
.modal-body{padding:13px;border-radius:10px;background:var(--surface2);border:1px solid var(--border);color:var(--text2);line-height:1.7;margin-bottom:12px;font-size:.9rem;}
.modal-extra{padding:10px;border-radius:10px;margin-bottom:16px;color:#111827;background:#f9fafb;border:1px solid #d1d5db;font-size:.84rem;font-weight:700;}
.modal-actions{display:flex;gap:10px;}
.btn-mc,.btn-mx{flex:1;padding:10px 16px;}
.btn-mc{color:#fff;background:var(--red);border-radius:10px;border:0;cursor:pointer;font-family:'Heebo',sans-serif;font-weight:700;}
.btn-mc:hover{background:#b91c1c;}
.btn-mx{color:var(--text2);background:var(--surface2);border:1px solid var(--border);border-radius:10px;cursor:pointer;font-family:'Heebo',sans-serif;font-weight:700;}
.btn-mx:hover{background:var(--border);}

::-webkit-scrollbar{width:6px;height:6px;}
::-webkit-scrollbar-track{background:var(--bg);}
::-webkit-scrollbar-thumb{background:var(--border2);border-radius:20px;}
::-webkit-scrollbar-thumb:hover{background:#1a1a1a;}

/* ── USER TABLE EXTRAS ── */
.user-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#1a1a1a,#333333);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:900;font-size:.82rem;flex-shrink:0;}
.user-avatar.lec{background:linear-gradient(135deg,#3b82f6,#6366f1);}
.user-info{display:flex;align-items:center;gap:10px;}
.user-name{font-weight:700;color:var(--navy);font-size:.9rem;}
.user-email{font-size:.76rem;color:var(--text3);margin-top:1px;}
.date-cell{font-size:.78rem;color:var(--text2);}
.date-cell .date-main{font-weight:600;color:var(--navy);}
.date-cell .date-sub{color:var(--text3);font-size:.72rem;}
.never-badge{display:inline-flex;padding:2px 8px;border-radius:6px;background:#f1f5f9;border:1px solid var(--border);color:var(--text3);font-size:.72rem;font-weight:600;}
.active-badge{display:inline-flex;padding:2px 8px;border-radius:6px;background:#f0fdf4;border:1px solid #86efac;color:#166534;font-size:.72rem;font-weight:700;}
.reg-count-badge{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:22px;padding:0 7px;border-radius:999px;font-size:.72rem;font-weight:800;}
.reg-count-badge.blue{background:#eff6ff;border:1px solid #93c5fd;color:#1d4ed8;}
.reg-count-badge.green{background:#f0fdf4;border:1px solid #86efac;color:#166534;}
.search-users{width:100%;padding:9px 14px;border-radius:10px;border:1px solid var(--border2);background:#fff;color:var(--text);font-family:'Heebo',sans-serif;font-size:.88rem;outline:none;margin-bottom:14px;transition:.2s;}
.search-users:focus{border-color:#1a1a1a;box-shadow:0 0 0 3px rgba(217,119,6,.1);}

@media(max-width:800px){
    .grid-2,.form-grid,.stats-charts,.stats-bottom,.stats-kpis,.rev-kpis{grid-template-columns:1fr;}
    .site-header{flex-direction:column;align-items:flex-start;gap:12px;}
    .stats-kpis{grid-template-columns:1fr 1fr;}
    .modal-actions{flex-direction:column;}
    .stats-filter{flex-direction:column;align-items:flex-start;}
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


.stats-toggle-btn {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 24px;
    border-radius: 12px;
    border: 1.5px solid var(--border2);
    background: var(--surface);
    color: var(--navy);
    font-family: 'Heebo', sans-serif;
    font-size: 1rem;
    font-weight: 800;
    cursor: pointer;
    transition: .2s ease;
    box-shadow: var(--shadow);
    width: 100%;
    margin-bottom: 16px;
}
.stats-toggle-btn:hover { background: var(--surface2); border-color: var(--navy); }
.stats-toggle-btn.open  { background: var(--navy); color: #fff; border-color: var(--navy); }

</style>
</head>
<body>
<div class="wrapper">

<header class="site-header">
    <div class="logo"><span class="logo-dot"></span> SkillHub</div>
    <div class="header-right">
        <div class="welcome-chip">שלום, <strong><?= $firstName ?></strong></div>
        <a href="index.php" class="nav-btn btn-home">🏠 דף הבית</a>
        <a href="logout.php" class="nav-btn btn-logout">← התנתק</a>
    </div>
</header>

<!-- ══ ADMIN HERO BANNER ══ -->
<div class="admin-hero">
    <div class="admin-hero-left">
        <div class="admin-hero-greeting">🛡️ לוח ניהול מערכת</div>
        <div class="admin-hero-title">שלום, <?= $firstName ?> 👋</div>
        <div class="admin-hero-sub">ברוך הבא לממשק הניהול של SkillHub</div>
    </div>
    <div class="admin-hero-stats">
        <?php
        $totalUsers = 0; $totalLecs = 0; $totalEvs = 0; $totalWs = 0;
        $ru2=$conn->query("SELECT COUNT(*) AS c FROM users WHERE isAdmin=0 AND (pending_lecturer IS NULL OR pending_lecturer=0)"); if($ru2) $totalUsers=$ru2->fetch_assoc()['c'];
        $rl2=$conn->query("SELECT COUNT(*) AS c FROM users WHERE isAdmin=2"); if($rl2) $totalLecs=$rl2->fetch_assoc()['c'];
        $re2=$conn->query("SELECT COUNT(*) AS c FROM events WHERE CONCAT(eventDate,' ',eventTime)>NOW()"); if($re2) $totalEvs=$re2->fetch_assoc()['c'];
        $rw2=$conn->query("SELECT COUNT(*) AS c FROM workshop WHERE date > CURDATE() OR (date=CURDATE() AND IFNULL(time,'23:59:59')>CURTIME())"); if($rw2) $totalWs=$rw2->fetch_assoc()['c'];
        ?>
        <div class="admin-hero-stat">
            <div class="admin-hero-stat-num"><?= $totalUsers ?></div>
            <div class="admin-hero-stat-label">משתמשים</div>
        </div>
        <div class="admin-hero-stat">
            <div class="admin-hero-stat-num"><?= $totalLecs ?></div>
            <div class="admin-hero-stat-label">מרצים</div>
        </div>
        <div class="admin-hero-stat">
            <div class="admin-hero-stat-num"><?= $totalEvs ?></div>
            <div class="admin-hero-stat-label">אירועים</div>
        </div>
        <div class="admin-hero-stat">
            <div class="admin-hero-stat-num"><?= $totalWs ?></div>
            <div class="admin-hero-stat-label">סדנאות</div>
        </div>
    </div>
</div>

<div style="padding-top:0">
<?= $status_message ?>

<?php if (!empty($pendingLecturers)): ?>
<div class="section-label">🔔 בקשות ממתינות לאישור</div>
<div class="grid-1">
    <div class="card" style="border-color:#fed7aa">
        <div class="card-header" onclick="toggleCard(this)" style="background:#fff7ed">
            <div class="card-header-left"><div class="card-icon-wrap pending">🎓</div><div class="card-info"><div class="card-title">בקשות מרצים</div><div class="card-subtitle">ממתינות לאישורך</div></div></div>
            <div class="toggle-btn" style="background:#fff7ed;border-color:#fed7aa;color:#ea580c"><span class="count-badge red"><?= count($pendingLecturers) ?></span><span>פתח</span><span class="chevron">▼</span></div>
        </div>
        <div class="card-body open">
            <?php foreach ($pendingLecturers as $pu): ?>
            <div class="pending-item">
                <div class="pending-info">
                    <div class="pending-name">👤 <?= htmlspecialchars($pu['name'].' '.$pu['lastname']) ?></div>
                    <div class="pending-meta">✉ <?= htmlspecialchars($pu['email']) ?><?php if ($pu['phone']): ?> · 📞 <?= htmlspecialchars($pu['phone']) ?><?php endif; ?></div>
                </div>
                <div class="pending-actions">
                    <form method="POST" action="admin.php" style="display:inline"><input type="hidden" name="pendingUserId" value="<?= $pu['id'] ?>"><button type="submit" name="approveLecturer" class="btn-approve">✓ אשר כמרצה</button></form>
                    <form method="POST" action="admin.php" style="display:inline"><input type="hidden" name="pendingUserId" value="<?= $pu['id'] ?>"><button type="submit" name="rejectLecturer" class="btn btn-danger">✗ דחה</button></form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- STATS -->
<div class="section-label">📊 סטטיסטיקות</div>
<button class="stats-toggle-btn" id="btnStats" onclick="toggleStats()">
    📊 הצג סטטיסטיקות ▼
</button>
<div id="statsBlock" style="display:none;">
<div class="stats-wrap">
    <div class="stats-filter">
        <label>מ:</label>
        <input type="month" id="filterFrom" class="filter-input" value="<?= $defaultFrom ?>" min="<?= $allMonths[0]['ym'] ?>" max="<?= $defaultTo ?>" onchange="applyFilter()">
        <label>עד:</label>
        <input type="month" id="filterTo" class="filter-input" value="<?= $defaultTo ?>" min="<?= $allMonths[0]['ym'] ?>" max="<?= date('Y-m') ?>" onchange="applyFilter()">
        <div class="filter-presets">
            <button class="preset-btn" onclick="setPreset(3)">3 חודשים</button>
            <button class="preset-btn active" onclick="setPreset(12)">שנה</button>
            <button class="preset-btn" onclick="setPreset(24)">2 שנים</button>
            <button class="preset-btn" onclick="setPreset(0)">הכל</button>
        </div>
    </div>

    <div class="stats-kpis">
        <div class="kpi-card"><div class="kpi-label">סה"כ הרשמות בטווח</div><div class="kpi-val" id="kTotal">—</div><div class="kpi-sub">אירועים + סדנאות</div></div>
        <div class="kpi-card"><div class="kpi-label">חודש מוביל</div><div class="kpi-val" id="kPeak">—</div><div class="kpi-sub" id="kPeakLabel">—</div></div>
        <div class="kpi-card"><div class="kpi-label">ממוצע חודשי</div><div class="kpi-val" id="kAvg">—</div><div class="kpi-sub">בטווח שנבחר</div></div>
        <div class="kpi-card"><div class="kpi-label">חודש אחרון בטווח</div><div class="kpi-val" id="kLast">—</div><div class="kpi-sub" id="kLastLabel">—</div></div>
    </div>

    <div class="stats-charts">
        <div class="chart-box">
            <div class="chart-box-title" id="chartMainTitle">הרשמות חודשיות</div>
            <div class="chart-legend"><span><span class="legend-sq" style="background:#3b82f6"></span>אירועים</span><span><span class="legend-sq" style="background:#22c55e"></span>סדנאות</span></div>
            <div style="position:relative;width:100%;height:220px"><canvas id="chartMain"></canvas></div>
        </div>
        <div class="chart-box">
            <div class="chart-box-title">פילוח לפי קטגוריה</div>
            <div style="position:relative;width:100%;height:195px"><canvas id="chartCat"></canvas></div>
            <div class="chart-legend" id="catLegend" style="justify-content:center;margin-top:8px;flex-direction:column;gap:4px;align-items:flex-start"></div>
        </div>
    </div>

    <div class="stats-bottom">
        <div class="chart-box">
            <div class="chart-box-title">פירוט חודשי</div>
            <div style="overflow-x:auto"><table class="month-tbl"><thead><tr><th>חודש</th><th style="text-align:center">אירועים</th><th style="text-align:center">סדנאות</th><th style="text-align:center">סה"כ</th><th>גרף</th></tr></thead><tbody id="monthTbody"></tbody></table></div>
        </div>
        <div class="chart-box"><div class="chart-box-title">מרצים מובילים</div><div id="lecList"></div></div>
    </div>

    <!-- הכנסות -->
    <div class="rev-separator"></div>
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:14px">
        <div class="chart-box-title" style="margin-bottom:0;color:var(--navy);font-size:.9rem">💰 סיכום הכנסות בטווח שנבחר</div>
        <button onclick="printInvoice()" class="btn-invoice">🖨️ הפק חשבונית PDF</button>
    </div>
    <div class="rev-kpis">
        <div class="rev-card ev"><div class="rev-label">הכנסות מאירועים</div><div class="rev-val ev" id="revEv">—</div><div class="rev-sub" id="revEvCount">—</div></div>
        <div class="rev-card"><div class="rev-label">הכנסות מסדנאות</div><div class="rev-val" id="revWs">—</div><div class="rev-sub" id="revWsCount">—</div></div>
        <div class="rev-card gold"><div class="rev-label">סה"כ הכנסות</div><div class="rev-val gold" id="revTotal">—</div><div class="rev-sub" id="revTotalSub">—</div></div>
        <div class="rev-card purple"><div class="rev-label">ממוצע חודשי</div><div class="rev-val purple" id="revAvg">—</div><div class="rev-sub">בטווח שנבחר</div></div>
    </div>
    <div class="chart-box" style="margin-top:16px">
        <div class="chart-box-title">פירוט הכנסות חודשי</div>
        <div style="overflow-x:auto">
            <table class="rev-tbl"><thead><tr><th>חודש</th><th style="text-align:center">הכנסות אירועים</th><th style="text-align:center">הכנסות סדנאות</th><th style="text-align:center">סה"כ</th><th>גרף</th></tr></thead><tbody id="revTbody"></tbody></table>
        </div>
    </div>
</div>
</div>

<!-- ניהול -->
<div class="section-label">ניהול משתמשים וקטגוריות</div>
<div class="grid-2">
    <div class="card">
        <div class="card-header" onclick="toggleCard(this)">
            <div class="card-header-left"><div class="card-icon-wrap">👤</div><div class="card-info"><div class="card-title">הרשמת מרצה</div><div class="card-subtitle">הוספת משתמש עם הרשאת מרצה</div></div></div>
            <div class="toggle-btn"><span class="tb-label">פתח</span> <span class="chevron">▼</span></div>
        </div>
        <div class="card-body">
            <div class="card-body-pad">
            <form method="post">
                <div class="form-grid">
                    <div class="form-group"><label class="form-label">שם פרטי</label><input type="text" name="name" class="form-input" placeholder="ישראל" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">שם משפחה</label><input type="text" name="lastname" class="form-input" placeholder="ישראלי" required value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>"></div>
                </div>
                <div class="form-group"><label class="form-label">דוא"ל</label><input type="email" name="email" class="form-input" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"></div>
                <div class="form-grid">
                    <div class="form-group"><label class="form-label">טלפון</label><input type="text" name="phone" class="form-input" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">סיסמה</label><input type="password" name="password" class="form-input" placeholder="לפחות 6 תווים" required minlength="6"></div>
                </div>
                <button type="submit" name="submit" class="btn btn-primary">הוסף מרצה ←</button>
            </form>
            </div>
        </div>
    </div>
    <div class="col-stack">
        <div class="card">
            <div class="card-header" onclick="toggleCard(this)">
                <div class="card-header-left"><div class="card-icon-wrap">🏷️</div><div class="card-info"><div class="card-title">הוספת קטגוריה</div><div class="card-subtitle">סוג סדנה / תחום</div></div></div>
                <div class="toggle-btn"><span class="tb-label">פתח</span> <span class="chevron">▼</span></div>
            </div>
            <div class="card-body">
                <div class="card-body-pad">
                <form method="post" action="admin.php">
                    <div class="form-group"><label class="form-label">שם הקטגוריה</label><input type="text" name="typeName" class="form-input" placeholder="AI, DevOps, React…" required></div>
                    <button type="submit" name="addType" class="btn btn-primary">+ הוסף קטגוריה</button>
                </form>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header" onclick="toggleCard(this)">
                <div class="card-header-left"><div class="card-icon-wrap">📂</div><div class="card-info"><div class="card-title">קטגוריות קיימות</div><div class="card-subtitle"><span class="count-badge"><?= count($workshop_types) ?></span> קטגוריות</div></div></div>
                <div class="toggle-btn"><span class="tb-label">פתח</span> <span class="chevron">▼</span></div>
            </div>
            <div class="card-body">
                <div class="card-body-pad">
                <?php if (!empty($workshop_types)): ?>
                    <div class="type-list"><?php foreach ($workshop_types as $t): ?><div class="type-item"><div class="type-left"><div class="type-bullet"></div><span class="type-name"><?= htmlspecialchars($t['typeName']) ?></span></div><button type="button" class="btn btn-danger delete-type-trigger" data-type-id="<?= $t['typeId'] ?>" data-type-name="<?= htmlspecialchars($t['typeName']) ?>">מחק</button></div><?php endforeach; ?></div>
                <?php else: ?><div class="empty"><div class="empty-ico">📭</div><p>אין קטגוריות</p></div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="section-label">הוספת אירועים</div>
<div class="grid-1">
    <div class="card">
        <div class="card-header" onclick="toggleCard(this)">
            <div class="card-header-left"><div class="card-icon-wrap">🗓️</div><div class="card-info"><div class="card-title">הוספת אירוע חדש</div><div class="card-subtitle">יצירת סדנה או הרצאה</div></div></div>
            <div class="toggle-btn"><span class="tb-label">פתח</span> <span class="chevron">▼</span></div>
        </div>
        <div class="card-body">
            <div class="card-body-pad">
            <form method="post" action="admin.php">
                <div class="form-grid">
                    <div class="form-group"><label class="form-label">שם האירוע</label><input type="text" name="eventName" class="form-input" placeholder="מבוא ל-Node.js" required></div>
                    <div class="form-group"><label class="form-label">מרצה</label><select name="lecturerId" class="form-input" required><option value="">בחר מרצה…</option><?php foreach ($lecturers as $l): ?><option value="<?= $l['id'] ?>"><?= htmlspecialchars("{$l['name']} {$l['lastname']}") ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label class="form-label">תאריך</label><input type="date" name="eventDate" class="form-input" required></div>
                    <div class="form-group"><label class="form-label">שעה</label><input type="time" name="eventTime" class="form-input" required></div>
                    <div class="form-group"><label class="form-label">קטגוריה</label><select name="eventTypeId" class="form-input" required><option value="">בחר קטגוריה…</option><?php foreach ($workshop_types as $t): ?><option value="<?= $t['typeId'] ?>"><?= htmlspecialchars($t['typeName']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label class="form-label">מקסימום משתתפים</label><input type="number" name="maxParticipants" class="form-input" placeholder="50" required min="1"></div>
                    <div class="form-group"><label class="form-label">💰 מחיר (₪)</label><input type="number" name="eventPrice" id="eventPrice" class="form-input" placeholder="0" min="0" step="0.01" value="0" oninput="updatePriceHint(this.value)"><div class="price-hint free" id="priceHint">✓ אירוע חינמי</div></div>
                    <div class="form-group" style="grid-column:1/-1"><label class="form-label">תיאור האירוע</label><textarea name="eventDescription" class="form-input" rows="3" placeholder="תאר את האירוע בקצרה…"></textarea></div>
                </div>
                <button type="submit" name="addEvent" class="btn btn-primary">צור אירוע ←</button>
            </form>
            </div>
        </div>
    </div>
</div>

<div class="section-label">ניהול נתונים</div>

<!-- ✅ אירועים עתידיים + כפתור היסטוריה -->
<div class="grid-1">
    <div class="card">
        <div class="card-header" onclick="toggleCard(this)">
            <div class="card-header-left"><div class="card-icon-wrap">📅</div><div>
                <div class="card-title">אירועים מתוכננים</div>
                <div class="card-subtitle"><span class="count-badge"><?= count($allEvents) ?></span> אירועים עתידיים</div>
            </div></div>
            <div class="toggle-btn"><span class="tb-label">פתח</span> <span class="chevron">▼</span></div>
        </div>
        <div class="card-body">
            <?php if (!empty($allEvents)): ?>
            <div class="table-wrap"><table><thead><tr><th>שם האירוע</th><th>מרצה</th><th>תאריך ושעה</th><th>מחיר</th><th class="td-c">פרטים</th><th class="td-c">פעולה</th></tr></thead><tbody>
            <?php foreach ($allEvents as $ev): ?><tr><td><strong><?= htmlspecialchars($ev['eventName']) ?></strong><div><span class="event-tag"><?= htmlspecialchars($ev['typeName']) ?></span></div></td><td><?= htmlspecialchars("{$ev['lecturerName']} {$ev['lecturerLastname']}") ?></td><td class="mono"><?= htmlspecialchars($ev['eventDate']) ?> · <?= htmlspecialchars(substr($ev['eventTime'],0,5)) ?></td><td><?php if(floatval($ev['price'])>0): ?><span class="price-tag-paid"><?= number_format($ev['price'],2) ?> ₪</span><?php else: ?><span class="price-tag-free">חינמי</span><?php endif; ?></td><td class="td-c"><a href="event_details.php?event_id=<?= $ev['eventId'] ?>&from=admin" style="padding:5px 10px;font-size:.76rem;font-weight:700;color:var(--text2);background:var(--surface2);border:1px solid var(--border2);border-radius:6px;text-decoration:none;">📄 פרטים</a></td><td class="td-c"><button type="button" class="btn btn-danger delete-event-trigger" data-event-id="<?= $ev['eventId'] ?>" data-event-name="<?= htmlspecialchars($ev['eventName']) ?>">מחק</button></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <?php else: ?><div class="empty"><div class="empty-ico">📭</div><p>אין אירועים עתידיים</p></div><?php endif; ?>

            <button class="history-btn" id="btnHistEv" onclick="toggleHistory('histEv','btnHistEv')">
                <span>🗂️</span>
                <span>הצג אירועים שהסתיימו</span>
                <span class="history-count-pill"><?= count($pastEvents) ?></span>
                <i class="hb-arrow">▼</i>
            </button>
            <div class="history-section" id="histEv">
                <div class="history-section-header">🗓️ אירועים שהסתיימו — <?= count($pastEvents) ?> רשומות</div>
                <div class="table-wrap" style="border:none;border-radius:0">
                    <?php if (!empty($pastEvents)): ?>
                    <table><thead><tr><th>שם האירוע</th><th>מרצה</th><th>תאריך ושעה</th><th>מחיר</th><th class="td-c">פרטים</th><th class="td-c">סטטוס</th></tr></thead><tbody>
                    <?php foreach ($pastEvents as $ev): ?><tr class="past-row"><td><strong><?= htmlspecialchars($ev['eventName']) ?></strong><div><span class="event-tag"><?= htmlspecialchars($ev['typeName']) ?></span></div></td><td><?= htmlspecialchars("{$ev['lecturerName']} {$ev['lecturerLastname']}") ?></td><td class="mono"><?= htmlspecialchars($ev['eventDate']) ?> · <?= htmlspecialchars(substr($ev['eventTime'],0,5)) ?></td><td><?php if(floatval($ev['price'])>0): ?><span class="price-tag-paid"><?= number_format($ev['price'],2) ?> ₪</span><?php else: ?><span class="price-tag-free">חינמי</span><?php endif; ?></td><td class="td-c"><a href="event_details.php?event_id=<?= $ev['eventId'] ?>&from=admin" style="padding:5px 10px;font-size:.76rem;font-weight:700;color:var(--text2);background:var(--surface2);border:1px solid var(--border2);border-radius:6px;text-decoration:none;">📄 פרטים</a></td><td class="td-c"><span style="font-size:.75rem;color:var(--text3);font-weight:600;">✓ הסתיים</span></td></tr><?php endforeach; ?>
                    </tbody></table>
                    <?php else: ?><div class="empty"><div class="empty-ico">📭</div><p>אין אירועים שהסתיימו</p></div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ✅ סדנאות עתידיות + כפתור היסטוריה -->
<?php if (!empty($allWorkshops) || !empty($pastWorkshops)): ?>
<div class="grid-1">
    <div class="card">
        <div class="card-header" onclick="toggleCard(this)">
            <div class="card-header-left"><div class="card-icon-wrap">🎓</div><div>
                <div class="card-title">סדנאות (WORKSHOP)</div>
                <div class="card-subtitle"><span class="count-badge"><?= count($allWorkshops) ?></span> סדנאות עתידיות</div>
            </div></div>
            <div class="toggle-btn"><span class="tb-label">פתח</span> <span class="chevron">▼</span></div>
        </div>
        <div class="card-body">
            <div class="note-warn">⚠️ טבלה זו נפרדת מטבלת האירועים</div>
            <?php if (!empty($allWorkshops)): ?>
            <div class="table-wrap"><table><thead><tr><th>שם הסדנה</th><th>מזהה</th><th>מרצה</th><th>תאריך</th><th class="td-c">פרטים</th><th class="td-c">פעולה</th></tr></thead><tbody>
            <?php foreach ($allWorkshops as $ws): ?><tr><td><strong><?= htmlspecialchars($ws['WorkshopName']) ?></strong><div><span class="ws-tag"><?= htmlspecialchars($ws['wsType'] ?? '—') ?></span></div></td><td class="mono">#<?= $ws['wsnum'] ?></td><td><?= htmlspecialchars("{$ws['lecturerName']} {$ws['lecturerLastname']}") ?></td><td class="mono"><?= htmlspecialchars($ws['date']) ?></td><td class="td-c"><a href="workshop_details.php?wsnum=<?= $ws['wsnum'] ?>&from=admin" style="padding:5px 10px;font-size:.76rem;font-weight:700;color:var(--text2);background:var(--surface2);border:1px solid var(--border2);border-radius:6px;text-decoration:none;">📄 פרטים</a></td><td class="td-c"><button type="button" class="btn btn-danger delete-workshop-trigger" data-workshop-id="<?= $ws['wsnum'] ?>" data-workshop-name="<?= htmlspecialchars($ws['WorkshopName']) ?>">מחק</button></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <?php else: ?><div class="empty"><div class="empty-ico">📭</div><p>אין סדנאות עתידיות</p></div><?php endif; ?>

            <button class="history-btn" id="btnHistWs" onclick="toggleHistory('histWs','btnHistWs')">
                <span>🗂️</span>
                <span>הצג סדנאות שהסתיימו</span>
                <span class="history-count-pill"><?= count($pastWorkshops) ?></span>
                <i class="hb-arrow">▼</i>
            </button>
            <div class="history-section" id="histWs">
                <div class="history-section-header">🎓 סדנאות שהסתיימו — <?= count($pastWorkshops) ?> רשומות</div>
                <div class="table-wrap" style="border:none;border-radius:0">
                    <?php if (!empty($pastWorkshops)): ?>
                    <table><thead><tr><th>שם הסדנה</th><th>מזהה</th><th>מרצה</th><th>תאריך</th><th class="td-c">פרטים</th><th class="td-c">סטטוס</th></tr></thead><tbody>
                    <?php foreach ($pastWorkshops as $ws): ?><tr class="past-row"><td><strong><?= htmlspecialchars($ws['WorkshopName']) ?></strong><div><span class="ws-tag"><?= htmlspecialchars($ws['wsType'] ?? '—') ?></span></div></td><td class="mono">#<?= $ws['wsnum'] ?></td><td><?= htmlspecialchars("{$ws['lecturerName']} {$ws['lecturerLastname']}") ?></td><td class="mono"><?= htmlspecialchars($ws['date']) ?></td><td class="td-c"><a href="workshop_details.php?wsnum=<?= $ws['wsnum'] ?>&from=admin" style="padding:5px 10px;font-size:.76rem;font-weight:700;color:var(--text2);background:var(--surface2);border:1px solid var(--border2);border-radius:6px;text-decoration:none;">📄 פרטים</a></td><td class="td-c"><span style="font-size:.75rem;color:var(--text3);font-weight:600;">✓ הסתיים</span></td></tr><?php endforeach; ?>
                    </tbody></table>
                    <?php else: ?><div class="empty"><div class="empty-ico">📭</div><p>אין סדנאות שהסתיימו</p></div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="grid-1">
    <div class="card">
        <div class="card-header" onclick="toggleCard(this)">
            <div class="card-header-left"><div class="card-icon-wrap">📋</div><div class="card-info"><div class="card-title">הרשמות לאירועים</div><div class="card-subtitle"><span class="count-badge"><?= count($allRegistrations) ?></span> הרשמות לאירועים עתידיים</div></div></div>
            <div class="toggle-btn"><span class="tb-label">פתח</span> <span class="chevron">▼</span></div>
        </div>
        <div class="card-body">
            <?php if (!empty($allRegistrations)): ?>
            <div class="table-wrap"><table><thead><tr><th>אירוע</th><th>תאריך ושעה</th><th>משתמש</th><th class="td-c">פעולה</th></tr></thead><tbody>
            <?php foreach ($allRegistrations as $reg): ?><tr><td><strong><?= htmlspecialchars($reg['eventName']) ?></strong><div><span class="event-tag"><?= htmlspecialchars($reg['typeName'] ?? '—') ?></span></div></td><td class="mono"><?= htmlspecialchars($reg['eventDate']) ?> · <?= htmlspecialchars(substr($reg['eventTime'],0,5)) ?></td><td><strong><?= htmlspecialchars("{$reg['userName']} {$reg['userLastname']}") ?></strong><div style="font-size:.78rem;color:var(--text3);margin-top:2px"><?= htmlspecialchars($reg['userEmail']) ?></div></td><td class="td-c"><button type="button" class="btn btn-danger delete-registration-trigger" data-reg-id="<?= $reg['registrationId'] ?>" data-reg-name="<?= htmlspecialchars($reg['userName'].' '.$reg['userLastname'].' — '.$reg['eventName']) ?>">בטל</button></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <?php else: ?><div class="empty"><div class="empty-ico">📭</div><p>אין הרשמות לאירועים עתידיים</p></div><?php endif; ?>

            <button class="history-btn" id="btnHistReg" onclick="toggleHistory('histReg','btnHistReg')">
                <span>🗂️</span>
                <span>הצג הרשמות לאירועים שהסתיימו</span>
                <span class="history-count-pill"><?= count($pastRegistrations) ?></span>
                <i class="hb-arrow">▼</i>
            </button>
            <div class="history-section" id="histReg">
                <div class="history-section-header">📋 הרשמות לאירועים שהסתיימו — <?= count($pastRegistrations) ?> רשומות</div>
                <div class="table-wrap" style="border:none;border-radius:0">
                    <?php if (!empty($pastRegistrations)): ?>
                    <table><thead><tr><th>אירוע</th><th>תאריך ושעה</th><th>משתמש</th><th class="td-c">סטטוס</th></tr></thead><tbody>
                    <?php foreach ($pastRegistrations as $reg): ?><tr class="past-row"><td><strong><?= htmlspecialchars($reg['eventName']) ?></strong><div><span class="event-tag"><?= htmlspecialchars($reg['typeName'] ?? '—') ?></span></div></td><td class="mono"><?= htmlspecialchars($reg['eventDate']) ?> · <?= htmlspecialchars(substr($reg['eventTime'],0,5)) ?></td><td><strong><?= htmlspecialchars("{$reg['userName']} {$reg['userLastname']}") ?></strong><div style="font-size:.78rem;color:var(--text3);margin-top:2px"><?= htmlspecialchars($reg['userEmail']) ?></div></td><td class="td-c"><span style="font-size:.75rem;color:var(--text3);font-weight:600;">✓ הסתיים</span></td></tr><?php endforeach; ?>
                    </tbody></table>
                    <?php else: ?><div class="empty"><div class="empty-ico">📭</div><p>אין הרשמות לאירועים שהסתיימו</p></div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="section-label">ניהול הרשמות לסדנאות</div>
<div class="grid-1">
    <div class="card">
        <div class="card-header" onclick="toggleCard(this)">
            <div class="card-header-left"><div class="card-icon-wrap">🎓</div><div class="card-info"><div class="card-title">נרשמים לסדנאות</div><div class="card-subtitle"><span class="count-badge"><?= count($workshopRegistrations) ?></span> הרשמות לסדנאות עתידיות</div></div></div>
            <div class="toggle-btn"><span class="tb-label">פתח</span> <span class="chevron">▼</span></div>
        </div>
        <div class="card-body">
            <?php if (!empty($workshopRegistrations)): ?>
            <div class="table-wrap"><table><thead><tr><th>שם הסדנה</th><th>תאריך</th><th>משתמש</th><th>אימייל</th><th class="td-c">פעולה</th></tr></thead><tbody>
            <?php foreach ($workshopRegistrations as $wr): ?><tr><td><strong><?= htmlspecialchars($wr['WorkshopName']) ?></strong><div><span class="ws-tag"><?= htmlspecialchars($wr['wsType'] ?? '—') ?></span></div></td><td class="mono"><?= htmlspecialchars($wr['workshopDate']) ?></td><td><strong><?= htmlspecialchars($wr['userName'].' '.$wr['userLastname']) ?></strong></td><td style="font-size:.8rem;color:var(--text3)"><?= htmlspecialchars($wr['userEmail']) ?></td><td class="td-c"><button type="button" class="btn btn-danger delete-wsreg-trigger" data-wid="<?= $wr['workshop_id'] ?>" data-uid="<?= $wr['user_id'] ?>" data-name="<?= htmlspecialchars($wr['userName'].' '.$wr['userLastname'].' — '.$wr['WorkshopName']) ?>">בטל</button></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <?php else: ?><div class="empty"><div class="empty-ico">📭</div><p>אין הרשמות לסדנאות עתידיות</p></div><?php endif; ?>

            <button class="history-btn" id="btnHistWsReg" onclick="toggleHistory('histWsReg','btnHistWsReg')">
                <span>🗂️</span>
                <span>הצג הרשמות לסדנאות שהסתיימו</span>
                <span class="history-count-pill"><?= count($pastWsRegistrations) ?></span>
                <i class="hb-arrow">▼</i>
            </button>
            <div class="history-section" id="histWsReg">
                <div class="history-section-header">🎓 הרשמות לסדנאות שהסתיימו — <?= count($pastWsRegistrations) ?> רשומות</div>
                <div class="table-wrap" style="border:none;border-radius:0">
                    <?php if (!empty($pastWsRegistrations)): ?>
                    <table><thead><tr><th>שם הסדנה</th><th>תאריך</th><th>משתמש</th><th>אימייל</th><th class="td-c">סטטוס</th></tr></thead><tbody>
                    <?php foreach ($pastWsRegistrations as $wr): ?><tr class="past-row"><td><strong><?= htmlspecialchars($wr['WorkshopName']) ?></strong><div><span class="ws-tag"><?= htmlspecialchars($wr['wsType'] ?? '—') ?></span></div></td><td class="mono"><?= htmlspecialchars($wr['workshopDate']) ?></td><td><strong><?= htmlspecialchars($wr['userName'].' '.$wr['userLastname']) ?></strong></td><td style="font-size:.8rem;color:var(--text3)"><?= htmlspecialchars($wr['userEmail']) ?></td><td class="td-c"><span style="font-size:.75rem;color:var(--text3);font-weight:600;">✓ הסתיים</span></td></tr><?php endforeach; ?>
                    </tbody></table>
                    <?php else: ?><div class="empty"><div class="empty-ico">📭</div><p>אין הרשמות לסדנאות שהסתיימו</p></div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ✅ טבלת מרצים -->
<div class="section-label">👨‍🏫 מרצים</div>
<div class="grid-1">
    <div class="card">
        <div class="card-header" onclick="toggleCard(this)">
            <div class="card-header-left"><div class="card-icon-wrap blue">🎓</div><div class="card-info"><div class="card-title">כל המרצים</div><div class="card-subtitle"><span class="count-badge blue"><?= count($allLecturers) ?></span> מרצים רשומים</div></div></div>
            <div class="toggle-btn"><span class="tb-label">פתח</span> <span class="chevron">▼</span></div>
        </div>
        <div class="card-body">
            <?php if (!empty($allLecturers)): ?>
            <input type="text" class="search-users" placeholder="🔍 חפש מרצה לפי שם או אימייל..." oninput="filterTable(this,'lecturersTable')">
            <div class="table-wrap"><table id="lecturersTable">
                <thead><tr><th>מרצה</th><th>טלפון</th><th>הרשמות לאירועים</th><th>הרשמות לסדנאות</th><th>תאריך הצטרפות</th><th>כניסה אחרונה</th><th class="td-c">פעולה</th></tr></thead>
                <tbody>
                <?php foreach ($allLecturers as $lec):
                    $initials = mb_substr($lec['name'],0,1).mb_substr($lec['lastname'],0,1);
                    $createdDate = $lec['created_at'] ? date('d/m/Y', strtotime($lec['created_at'])) : '—';
                    $createdTime = $lec['created_at'] ? date('H:i', strtotime($lec['created_at'])) : '';
                    $lastDate    = $lec['last_login'] ? date('d/m/Y', strtotime($lec['last_login'])) : null;
                    $lastTime    = $lec['last_login'] ? date('H:i', strtotime($lec['last_login'])) : null;
                ?>
                <tr data-search="<?= htmlspecialchars(mb_strtolower($lec['name'].' '.$lec['lastname'].' '.$lec['email'])) ?>">
                    <td><div class="user-info"><div class="user-avatar lec"><?= htmlspecialchars($initials) ?></div><div><div class="user-name"><?= htmlspecialchars($lec['name'].' '.$lec['lastname']) ?></div><div class="user-email"><?= htmlspecialchars($lec['email']) ?></div></div></div></td>
                    <td class="date-cell"><?= htmlspecialchars($lec['phone'] ?: '—') ?></td>
                    <td class="td-c"><span class="reg-count-badge blue"><?= (int)$lec['events_regs'] ?></span></td>
                    <td class="td-c"><span class="reg-count-badge green"><?= (int)$lec['ws_regs'] ?></span></td>
                    <td class="date-cell"><?php if ($lec['created_at']): ?><div class="date-main"><?= $createdDate ?></div><div class="date-sub"><?= $createdTime ?></div><?php else: ?><span class="never-badge">לא ידוע</span><?php endif; ?></td>
                    <td class="date-cell"><?php if ($lastDate): ?><span class="active-badge">✓ <?= $lastDate ?> <?= $lastTime ?></span><?php else: ?><span class="never-badge">טרם התחבר</span><?php endif; ?></td>
                    <td class="td-c"><button type="button" class="btn btn-danger delete-lecturer-trigger" data-lec-id="<?= $lec['id'] ?>" data-lec-name="<?= htmlspecialchars($lec['name'].' '.$lec['lastname']) ?>">מחק</button></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php else: ?><div class="empty"><div class="empty-ico">📭</div><p>אין מרצים רשומים</p></div><?php endif; ?>
        </div>
    </div>
</div>

<!-- ✅ טבלת משתמשים -->
<div class="section-label">👥 משתמשים</div>
<div class="grid-1">
    <div class="card">
        <div class="card-header" onclick="toggleCard(this)">
            <div class="card-header-left"><div class="card-icon-wrap">👤</div><div class="card-info"><div class="card-title">כל המשתמשים</div><div class="card-subtitle"><span class="count-badge"><?= count($allUsers) ?></span> משתמשים רשומים</div></div></div>
            <div class="toggle-btn"><span class="tb-label">פתח</span> <span class="chevron">▼</span></div>
        </div>
        <div class="card-body">
            <?php if (!empty($allUsers)): ?>
            <input type="text" class="search-users" placeholder="🔍 חפש משתמש לפי שם, אימייל או טלפון..." oninput="filterTable(this,'usersTable')">
            <div class="table-wrap"><table id="usersTable">
                <thead><tr><th>משתמש</th><th>טלפון</th><th>תאריך הצטרפות</th><th>כניסה אחרונה</th><th class="td-c">פעולה</th></tr></thead>
                <tbody>
                <?php foreach ($allUsers as $u):
                    $initials = mb_substr($u['name'],0,1).mb_substr($u['lastname'],0,1);
                    $createdDate = $u['created_at'] ? date('d/m/Y', strtotime($u['created_at'])) : '—';
                    $createdTime = $u['created_at'] ? date('H:i', strtotime($u['created_at'])) : '';
                    $lastDate    = $u['last_login'] ? date('d/m/Y', strtotime($u['last_login'])) : null;
                    $lastTime    = $u['last_login'] ? date('H:i', strtotime($u['last_login'])) : null;
                ?>
                <tr data-search="<?= htmlspecialchars(mb_strtolower($u['name'].' '.$u['lastname'].' '.$u['email'].' '.($u['phone']??''))) ?>">
                    <td><div class="user-info"><div class="user-avatar"><?= htmlspecialchars($initials) ?></div><div><div class="user-name"><?= htmlspecialchars($u['name'].' '.$u['lastname']) ?></div><div class="user-email"><?= htmlspecialchars($u['email']) ?></div></div></div></td>
                    <td class="date-cell"><?= htmlspecialchars($u['phone'] ?: '—') ?></td>
                    <td class="date-cell"><?php if ($u['created_at']): ?><div class="date-main"><?= $createdDate ?></div><div class="date-sub"><?= $createdTime ?></div><?php else: ?><span class="never-badge">לא ידוע</span><?php endif; ?></td>
                    <td class="date-cell"><?php if ($lastDate): ?><span class="active-badge">✓ <?= $lastDate ?> <?= $lastTime ?></span><?php else: ?><span class="never-badge">טרם התחבר</span><?php endif; ?></td>
                    <td class="td-c"><button type="button" class="btn btn-danger delete-user-trigger" data-user-id="<?= $u['id'] ?>" data-user-name="<?= htmlspecialchars($u['name'].' '.$u['lastname']) ?>">מחק</button></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php else: ?><div class="empty"><div class="empty-ico">📭</div><p>אין משתמשים רשומים</p></div><?php endif; ?>
        </div>
    </div>
</div>

</div><!-- /padding-top -->
</div><!-- /wrapper -->

<!-- Invoice Modal -->
<div class="invoice-overlay" id="invoiceOverlay">
    <div id="invoiceModal">
        <div class="inv-header">
            <div><div class="inv-logo">Skill<span>Hub</span></div><div style="color:rgba(255,255,255,.6);font-size:.78rem;margin-top:4px">פלטפורמת למידה ופיתוח מקצועי</div></div>
            <div class="inv-header-meta">
                <div>מסמך: <strong>סיכום הכנסות</strong></div>
                <div>תאריך הפקה: <strong id="invDate">—</strong></div>
                <div>טווח: <strong id="invRange">—</strong></div>
            </div>
        </div>
        <div class="inv-body">
            <div class="inv-title">📊 סיכום כספי — הכנסות לפי טווח</div>
            <div class="inv-kpis">
                <div class="inv-kpi"><div class="inv-kpi-label">הכנסות מאירועים</div><div class="inv-kpi-val" id="invEvRev">—</div></div>
                <div class="inv-kpi"><div class="inv-kpi-label">הכנסות מסדנאות</div><div class="inv-kpi-val green" id="invWsRev">—</div></div>
                <div class="inv-kpi"><div class="inv-kpi-label">סה"כ הכנסות</div><div class="inv-kpi-val gold" id="invTotalRev">—</div></div>
            </div>
            <table class="inv-tbl">
                <thead><tr><th>חודש</th><th style="text-align:center">הכנסות אירועים</th><th style="text-align:center">הכנסות סדנאות</th><th style="text-align:center">סה"כ</th></tr></thead>
                <tbody id="invTbody"></tbody>
                <tfoot><tr><td>סה"כ</td><td style="text-align:center" id="invFootEv">—</td><td style="text-align:center" id="invFootWs">—</td><td style="text-align:center" id="invFootTotal">—</td></tr></tfoot>
            </table>
        </div>
        <div class="inv-footer">
            <div>SkillHub &copy; <?= date('Y') ?> — מסמך זה הופק אוטומטית מהמערכת</div>
            <div style="display:flex;gap:8px">
                <button class="invoice-close-btn" onclick="closeInvoice()">✕ סגור</button>
                <button class="inv-print-btn" onclick="window.print()">🖨️ הדפס / שמור PDF</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head"><div class="modal-ico">🗑</div><div><div class="modal-title">אישור מחיקה</div><div class="modal-sub">פעולה זו אינה ניתנת לביטול</div></div></div>
        <div class="modal-body" id="modalBody">פריט זה יימחק לצמיתות.</div>
        <div id="modalExtra" class="modal-extra" style="display:none">⚠️ מחיקת האירוע תמחק גם את כל ההרשמות.</div>
        <div class="modal-actions"><button id="confirmBtn" class="btn-mc">מחק סופית</button><button id="cancelBtn" class="btn-mx">ביטול</button></div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js">
</script>
<script>

// נתוני הסטטיסטיקות מגיעים מה-PHP כ-JSON
// STATS מכיל: allMonths (חודשים), categories (קטגוריות), lecturers (מרצים)
var STATS = <?= $statsJson ?>;

// צבעים לגרף העוגה — כל קטגוריה מקבלת צבע אחר
var COLORS = ['#3b82f6','#22c55e','#333333','#a855f7','#ef4444','#64748b','#06b6d4','#f43f5e'];

// צבע הקווים ברקע הגרף וצבע הטקסט בצירים
var gridC = 'rgba(0,0,0,0.06)';
var tickC = '#94a3b8';

// משתנה גלובלי לגרף הראשי — נשמר כדי שנוכל לעדכן אותו בלי ליצור חדש
var mainChart = null;

// ══ פונקציה: initCat ══
// יוצרת את גרף העוגה של פילוח הרשמות לפי קטגוריה
// Chart.js היא ספרייה חיצונית שמצייירת גרפים בדפדפן
function initCat() {
    var cats = STATS.categories || [];

    // אם אין קטגוריות — מציג הודעה במקום גרף
    if (cats.length === 0) {
        var box = document.getElementById('chartCat').closest('.chart-box');
        box.innerHTML = '<div class="chart-box-title">פילוח לפי קטגוריה</div><div style="text-align:center;padding:30px;color:var(--text3)">אין נתונים</div>';
        return;
    }

    // יצירת מערכים של שמות וכמויות לגרף
    var catNames   = [];
    var catCounts  = [];
    var catColors  = [];
    for (var i = 0; i < cats.length; i++) {
        catNames.push(cats[i].name);
        catCounts.push(cats[i].count);
        catColors.push(COLORS[i]);
    }

    // יצירת גרף עוגה (doughnut) עם Chart.js
    new Chart(document.getElementById('chartCat'), {
        type: 'doughnut',
        data: {
            labels: catNames,
            datasets: [{
                data: catCounts,
                backgroundColor: catColors,
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',  // גודל החור באמצע
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        // טקסט שמוצג כשמרחפים על פלח
                        label: function(ctx) {
                            return ' ' + ctx.label + ': ' + ctx.raw + ' הרשמות';
                        }
                    }
                }
            }
        }
    });

    // בניית מקרא (legend) ידנית מתחת לגרף
    var leg = document.getElementById('catLegend');
    for (var j = 0; j < cats.length; j++) {
        var sp = document.createElement('span');
        sp.innerHTML = '<span class="legend-sq" style="background:' + COLORS[j] + '"></span>' + cats[j].name + ' (' + cats[j].count + ')';
        leg.appendChild(sp);
    }
}
initCat();

// ══ פונקציה: initLec ══
// בונה את רשימת המרצים המובילים לפי מספר הרשמות
function initLec() {
    var lecs = STATS.lecturers || [];
    var el   = document.getElementById('lecList');
    el.innerHTML = '';

    if (lecs.length === 0) {
        el.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text3)">אין נתונים</div>';
        return;
    }

    for (var i = 0; i < lecs.length; i++) {
        var l     = lecs[i];
        var total = l.events + l.ws;  // סה"כ הרשמות = אירועים + סדנאות
        var div   = document.createElement('div');
        div.className = 'lec-row';
        div.innerHTML =
            '<span class="lec-name">' + (i + 1) + '. ' + l.name + '</span>' +
            '<span class="lec-badges">' +
                '<span class="badge-ev">' + l.events + ' אירועים</span>' +
                '<span class="badge-ws">' + l.ws + ' סדנאות</span>' +
                '<span class="badge-tot">' + total + '</span>' +
            '</span>';
        el.appendChild(div);
    }
}
initLec();

// ══ פונקציה: toggleStats ══
// פותח/סוגר את בלוק הסטטיסטיקות כשלוחצים על הכפתור
function toggleStats() {
    var block = document.getElementById('statsBlock');
    var btn   = document.getElementById('btnStats');

    if (block.style.display === 'none') {
        // פתיחה
        block.style.display = 'block';
        btn.textContent = '📊 הסתר סטטיסטיקות ▲';
        btn.classList.add('open');

        // טוענים את הגרף רק בפעם הראשונה שפותחים
        if (!window.statsLoaded) {
            applyFilter();
            window.statsLoaded = true;
        }
    } else {
        // סגירה
        block.style.display = 'none';
        btn.textContent = '📊 הצג סטטיסטיקות ▼';
        btn.classList.remove('open');
    }
}

// ══ פונקציה: applyFilter ══
// מסנן את נתוני הסטטיסטיקות לפי טווח חודשים שנבחר
// ומעדכן את כל הגרפים, הכרטיסים והטבלאות בדף
function applyFilter() {
    // קריאת ערכי הפילטר מהטופס
    var from = document.getElementById('filterFrom').value;
    var to   = document.getElementById('filterTo').value;
    if (!from || !to || from > to) return;

    // סינון החודשים לפי הטווח שנבחר
    var filtered = [];
    for (var i = 0; i < STATS.allMonths.length; i++) {
        if (STATS.allMonths[i].ym >= from && STATS.allMonths[i].ym <= to) {
            filtered.push(STATS.allMonths[i]);
        }
    }

    // בניית מערכים נפרדים לאירועים, סדנאות ותוויות
    var evArr  = [];  // מספר הרשמות לאירועים לכל חודש
    var wsArr  = [];  // מספר הרשמות לסדנאות לכל חודש
    var labels = [];  // שמות החודשים לגרף
    var totals = [];  // סה"כ הרשמות לכל חודש

    for (var j = 0; j < filtered.length; j++) {
        evArr.push(filtered[j].ev);
        wsArr.push(filtered[j].ws);
        labels.push(filtered[j].label);
        totals.push(filtered[j].ev + filtered[j].ws);
    }

    // חישוב סטטיסטיקות כלליות
    var grand = 0;
    for (var k = 0; k < totals.length; k++) { grand += totals[k]; }
    var avg = totals.length ? Math.round(grand / totals.length) : 0;

    // מציאת החודש עם הכי הרבה הרשמות
    var maxVal = 0;
    var maxIdx = 0;
    for (var m = 0; m < totals.length; m++) {
        if (totals[m] > maxVal) { maxVal = totals[m]; maxIdx = m; }
    }
    var lastTotal = totals.length ? totals[totals.length - 1] : 0;

    // עדכון כרטיסי ה-KPI (4 המספרים הגדולים)
    document.getElementById('kTotal').textContent = grand;
    document.getElementById('kPeak').textContent  = maxVal;
    document.getElementById('kPeakLabel').textContent = filtered[maxIdx] ? filtered[maxIdx].label : '—';
    document.getElementById('kAvg').textContent   = avg;
    document.getElementById('kLast').textContent  = lastTotal;
    document.getElementById('kLastLabel').textContent = filtered.length ? filtered[filtered.length - 1].label : '—';

    // עדכון כותרת הגרף
    var titleFrom = filtered.length ? filtered[0].label : '';
    var titleTo   = filtered.length ? filtered[filtered.length - 1].label : '';
    document.getElementById('chartMainTitle').textContent = 'הרשמות חודשיות · ' + titleFrom + ' — ' + titleTo;

    // עדכון גרף העמודות — אם קיים מעדכנים, אחרת יוצרים חדש
    if (mainChart) {
        mainChart.data.labels           = labels;
        mainChart.data.datasets[0].data = evArr;
        mainChart.data.datasets[1].data = wsArr;
        mainChart.update();
    } else {
        mainChart = new Chart(document.getElementById('chartMain'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'אירועים', data: evArr, backgroundColor: '#3b82f6', borderRadius: 4, borderSkipped: false },
                    { label: 'סדנאות',  data: wsArr, backgroundColor: '#22c55e', borderRadius: 4, borderSkipped: false }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: {} },
                scales: {
                    x: { stacked: true, ticks: { color: tickC, font: { size: 10 }, maxRotation: 45, autoSkip: true, maxTicksLimit: 8 }, grid: { color: gridC } },
                    y: { stacked: true, beginAtZero: true, ticks: { color: tickC, font: { size: 10 }, callback: function(v) { return Math.round(v); } }, grid: { color: gridC } }
                }
            }
        });
    }

    // בניית טבלת פירוט חודשי
    var tbody = document.getElementById('monthTbody');
    tbody.innerHTML = '';
    var maxT = 0;
    for (var n = 0; n < totals.length; n++) { if (totals[n] > maxT) maxT = totals[n]; }
    if (maxT === 0) maxT = 1;

    // הצגה מהחדש לישן (reverse)
    var reversedFiltered = filtered.slice().reverse();
    for (var p = 0; p < reversedFiltered.length; p++) {
        var row = reversedFiltered[p];
        var t   = row.ev + row.ws;
        var w   = Math.round(t / maxT * 60);  // רוחב העמודה הקטנה בגרף
        var tr  = document.createElement('tr');
        tr.innerHTML =
            '<td>' + row.label + '</td>' +
            '<td style="text-align:center">' + row.ev + '</td>' +
            '<td style="text-align:center">' + row.ws + '</td>' +
            '<td style="text-align:center"><strong>' + t + '</strong></td>' +
            '<td><span class="bar-spark" style="width:' + w + 'px;background:linear-gradient(90deg,#3b82f6,#22c55e)"></span></td>';
        tbody.appendChild(tr);
    }

    // ══ חלק הכנסות ══
    var evRevArr  = [];
    var wsRevArr  = [];
    for (var q = 0; q < filtered.length; q++) {
        evRevArr.push(filtered[q].evRev || 0);
        wsRevArr.push(filtered[q].wsRev || 0);
    }

    // חישוב סיכומי הכנסות
    var totalEvRev = 0;
    var totalWsRev = 0;
    for (var r = 0; r < filtered.length; r++) {
        totalEvRev += evRevArr[r];
        totalWsRev += wsRevArr[r];
    }
    var grandRev = totalEvRev + totalWsRev;
    var avgRev   = filtered.length ? grandRev / filtered.length : 0;

    // פונקציית עיצוב מספר כסף — לדוגמה: 450.00 ₪
    function fmt(n) {
        return n.toFixed(2) + ' ₪';
    }

    // עדכון כרטיסי הכנסות
    var totalEvCount = 0;
    var totalWsCount = 0;
    for (var s = 0; s < filtered.length; s++) {
        totalEvCount += filtered[s].ev;
        totalWsCount += filtered[s].ws;
    }

    document.getElementById('revEv').textContent       = fmt(totalEvRev);
    document.getElementById('revEvCount').textContent  = 'מ-' + totalEvCount + ' הרשמות לאירועים';
    document.getElementById('revWs').textContent       = fmt(totalWsRev);
    document.getElementById('revWsCount').textContent  = 'מ-' + totalWsCount + ' הרשמות לסדנאות';
    document.getElementById('revTotal').textContent    = fmt(grandRev);
    document.getElementById('revTotalSub').textContent = 'אירועים + סדנאות';
    document.getElementById('revAvg').textContent      = fmt(avgRev);

    // בניית טבלת פירוט הכנסות חודשי
    var revTbody = document.getElementById('revTbody');
    revTbody.innerHTML = '';
    var maxRev = 0;
    for (var u = 0; u < filtered.length; u++) {
        var rowRev = (filtered[u].evRev || 0) + (filtered[u].wsRev || 0);
        if (rowRev > maxRev) maxRev = rowRev;
    }
    if (maxRev === 0) maxRev = 1;

    var reversedRev = filtered.slice().reverse();
    for (var v = 0; v < reversedRev.length; v++) {
        var revRow  = reversedRev[v];
        var totalR  = (revRow.evRev || 0) + (revRow.wsRev || 0);
        var wRev    = Math.round(totalR / maxRev * 70);
        var tr2     = document.createElement('tr');
        var evF     = revRow.evRev > 0 ? '<span class="rev-positive">' + fmt(revRow.evRev) + '</span>' : '<span class="rev-zero">—</span>';
        var wsF     = revRow.wsRev > 0 ? '<span class="rev-positive">' + fmt(revRow.wsRev) + '</span>' : '<span class="rev-zero">—</span>';
        var totF    = totalR > 0 ? '<strong>' + fmt(totalR) + '</strong>' : '<span class="rev-zero">—</span>';
        tr2.innerHTML =
            '<td>' + revRow.label + '</td>' +
            '<td style="text-align:center">' + evF + '</td>' +
            '<td style="text-align:center">' + wsF + '</td>' +
            '<td style="text-align:center">' + totF + '</td>' +
            '<td><span class="bar-spark" style="width:' + wRev + 'px;background:linear-gradient(90deg,#333333,#22c55e)"></span></td>';
        revTbody.appendChild(tr2);
    }

    // מסיר סימון כפתור preset פעיל
    var presetBtns = document.querySelectorAll('.preset-btn');
    for (var x = 0; x < presetBtns.length; x++) {
        presetBtns[x].classList.remove('active');
    }
}

// ══ פונקציה: setPreset ══
// קובעת טווח חודשים קבוע מראש — 3 חודשים, שנה, 2 שנים, הכל
// months=0 = הצג הכל
function setPreset(months) {
    var toDate = new Date();
    var toYM   = toDate.toISOString().slice(0, 7);  // "2026-09"
    var fromYM;

    if (months === 0) {
        // הכל — מהחודש הראשון שיש במסד הנתונים
        fromYM = STATS.allMonths.length ? STATS.allMonths[0].ym : toYM;
    } else {
        // מחשב X חודשים אחורה
        var d = new Date(toDate);
        d.setMonth(d.getMonth() - months + 1);
        fromYM = d.toISOString().slice(0, 7);
    }

    document.getElementById('filterFrom').value = fromYM;
    document.getElementById('filterTo').value   = toYM;

    var presetBtns = document.querySelectorAll('.preset-btn');
    for (var i = 0; i < presetBtns.length; i++) {
        presetBtns[i].classList.remove('active');
    }
    event.target.classList.add('active');
    applyFilter();
}

// הרצת הפילטר בטעינת הדף כדי לאתחל את הגרפים
applyFilter();

// ══ פונקציה: printInvoice ══
// מכין את נתוני החשבונית ופותח חלון הדפסה
function printInvoice() {
    var from     = document.getElementById('filterFrom').value;
    var to       = document.getElementById('filterTo').value;
    var filtered = [];
    for (var i = 0; i < STATS.allMonths.length; i++) {
        if (STATS.allMonths[i].ym >= from && STATS.allMonths[i].ym <= to) {
            filtered.push(STATS.allMonths[i]);
        }
    }

    function fmt(n) { return n.toFixed(2) + ' ₪'; }

    // חישוב סיכומים
    var totalEv  = 0;
    var totalWs  = 0;
    for (var j = 0; j < filtered.length; j++) {
        totalEv += filtered[j].evRev || 0;
        totalWs += filtered[j].wsRev || 0;
    }
    var grandRev = totalEv + totalWs;

    // מילוי שדות החשבונית
    document.getElementById('invDate').textContent     = new Date().toLocaleDateString('he-IL');
    document.getElementById('invRange').textContent    = (filtered.length ? filtered[0].label : from) + ' — ' + (filtered.length ? filtered[filtered.length - 1].label : to);
    document.getElementById('invEvRev').textContent    = fmt(totalEv);
    document.getElementById('invWsRev').textContent    = fmt(totalWs);
    document.getElementById('invTotalRev').textContent = fmt(grandRev);
    document.getElementById('invFootEv').textContent   = fmt(totalEv);
    document.getElementById('invFootWs').textContent   = fmt(totalWs);
    document.getElementById('invFootTotal').textContent = fmt(grandRev);

    // בניית שורות הטבלה בחשבונית
    var tbody = document.getElementById('invTbody');
    tbody.innerHTML = '';
    for (var k = 0; k < filtered.length; k++) {
        var row  = filtered[k];
        var evR  = row.evRev || 0;
        var wsR  = row.wsRev || 0;
        var tot  = evR + wsR;
        if (evR === 0 && wsR === 0) continue;  // דלג על חודשים ללא הכנסות
        var tr   = document.createElement('tr');
        tr.innerHTML =
            '<td><strong>' + row.label + '</strong></td>' +
            '<td style="text-align:center">' + (evR > 0 ? fmt(evR) : '—') + '</td>' +
            '<td style="text-align:center">' + (wsR > 0 ? fmt(wsR) : '—') + '</td>' +
            '<td style="text-align:center"><strong>' + fmt(tot) + '</strong></td>';
        tbody.appendChild(tr);
    }
    if (tbody.children.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px;color:#9b856a">אין הכנסות בטווח שנבחר</td></tr>';
    }

    // פתיחת חלון החשבונית
    document.getElementById('invoiceOverlay').classList.add('active');
}

// ══ פונקציה: closeInvoice ══
// סוגר את חלון החשבונית
function closeInvoice() {
    document.getElementById('invoiceOverlay').classList.remove('active');
}

// סגירת חשבונית בלחיצה על הרקע
document.getElementById('invoiceOverlay').addEventListener('click', function(e) {
    if (e.target === this) { closeInvoice(); }
});

// ══ פונקציה: toggleHistory ══
// פותח/סוגר סקשן היסטוריה (אירועים/סדנאות שעברו)
function toggleHistory(sectionId, btnId) {
    var section = document.getElementById(sectionId);
    var btn     = document.getElementById(btnId);
    // toggle — אם פתוח סגור, אם סגור פתח
    if (section.classList.contains('open')) {
        section.classList.remove('open');
        btn.classList.remove('open');
    } else {
        section.classList.add('open');
        btn.classList.add('open');
    }
}

// ══ פונקציה: filterTable ══
// מסנן שורות בטבלה לפי חיפוש טקסט
// input = שדה הקלט, tableId = id של הטבלה
function filterTable(input, tableId) {
    var q    = input.value.trim().toLowerCase();
    var rows = document.querySelectorAll('#' + tableId + ' tbody tr');
    for (var i = 0; i < rows.length; i++) {
        var searchVal = rows[i].dataset.search || '';
        if (!q || searchVal.includes(q)) {
            rows[i].style.display = '';      // מציג
        } else {
            rows[i].style.display = 'none';  // מסתיר
        }
    }
}

// ══ פונקציה: toggleCard ══
// פותח/סוגר כרטיסי ניהול (accordion)
function toggleCard(header) {
    var body   = header.nextElementSibling;  // ה-div שאחרי הכותרת
    var isOpen = body.classList.contains('open');
    var lbl    = header.querySelector('.tb-label');
    body.classList.toggle('open', !isOpen);
    header.classList.toggle('open', !isOpen);
    if (lbl) { lbl.textContent = isOpen ? 'פתח' : 'סגור'; }
}

// ══ פונקציה: updatePriceHint ══
// מציג טקסט עזר מתחת לשדה מחיר האירוע
function updatePriceHint(val) {
    var hint  = document.getElementById('priceHint');
    if (!hint) return;
    var price = parseFloat(val) || 0;
    if (price <= 0) {
        hint.textContent = '✓ אירוע חינמי';
        hint.className   = 'price-hint free';
    } else {
        hint.textContent = 'מחיר האירוע: ' + price.toFixed(2) + ' ₪';
        hint.className   = 'price-hint';
    }
}

// ══ modal מחיקה ══
// modal = חלון קופץ לאישור מחיקה
var modal       = document.getElementById('deleteModal');
var modalBody   = document.getElementById('modalBody');
var modalExtra  = document.getElementById('modalExtra');
var confirmBtn  = document.getElementById('confirmBtn');
var cancelBtn   = document.getElementById('cancelBtn');
var deleteAction = null;  // שומר את הפעולה שתבוצע אחרי אישור

// ══ פונקציה: openDeleteModal ══
// פותח חלון אישור מחיקה
// text = טקסט ההסבר, action = מה לבצע אם מאשרים, showExtra = הצג אזהרה נוספת
function openDeleteModal(text, action, showExtra) {
    modalBody.innerHTML = text;
    deleteAction = action;
    if (modalExtra) { modalExtra.style.display = showExtra ? 'flex' : 'none'; }
    modal.classList.add('active');
}

// ══ פונקציה: closeDeleteModal ══
// סוגר את חלון האישור
function closeDeleteModal() {
    modal.classList.remove('active');
    deleteAction = null;
}

// ══ פונקציה: submitDeleteForm ══
// שולח טופס POST עם השדות הנדרשים לביצוע המחיקה
// fields = אובייקט עם שמות שדות וערכים
function submitDeleteForm(fields) {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'admin.php';
    var keys = Object.keys(fields);
    for (var i = 0; i < keys.length; i++) {
        var input = document.createElement('input');
        input.type  = 'hidden';
        input.name  = keys[i];
        input.value = fields[keys[i]];
        form.appendChild(input);
    }
    document.body.appendChild(form);
    form.submit();
}

// חיבור כפתורי מחיקה לפונקציות המתאימות
// querySelectorAll מחזיר את כל האלמנטים עם ה-class הזה
var delTypeButtons = document.querySelectorAll('.delete-type-trigger');
for (var i = 0; i < delTypeButtons.length; i++) {
    delTypeButtons[i].addEventListener('click', function() {
        openDeleteModal(
            'האם למחוק את הקטגוריה <strong>' + this.dataset.typeName + '</strong>?',
            function() { submitDeleteForm({ deleteType: '1', typeId: this.dataset.typeId }); }.bind(this)
        );
    });
}

var delWsButtons = document.querySelectorAll('.delete-workshop-trigger');
for (var i = 0; i < delWsButtons.length; i++) {
    delWsButtons[i].addEventListener('click', function() {
        openDeleteModal(
            'האם למחוק את הסדנה <strong>' + this.dataset.workshopName + '</strong>?',
            function() { submitDeleteForm({ deleteWorkshop: '1', workshopId: this.dataset.workshopId }); }.bind(this)
        );
    });
}

var delEvButtons = document.querySelectorAll('.delete-event-trigger');
for (var i = 0; i < delEvButtons.length; i++) {
    delEvButtons[i].addEventListener('click', function() {
        openDeleteModal(
            'האם למחוק את האירוע <strong>' + this.dataset.eventName + '</strong>?',
            function() { submitDeleteForm({ deleteEvent: '1', eventId: this.dataset.eventId }); }.bind(this),
            true
        );
    });
}

var delRegButtons = document.querySelectorAll('.delete-registration-trigger');
for (var i = 0; i < delRegButtons.length; i++) {
    delRegButtons[i].addEventListener('click', function() {
        openDeleteModal(
            'האם לבטל את ההרשמה של <strong>' + this.dataset.regName + '</strong>?',
            function() { submitDeleteForm({ deleteRegistration: '1', registrationId: this.dataset.regId }); }.bind(this)
        );
    });
}

var delWsRegButtons = document.querySelectorAll('.delete-wsreg-trigger');
for (var i = 0; i < delWsRegButtons.length; i++) {
    delWsRegButtons[i].addEventListener('click', function() {
        openDeleteModal(
            'האם לבטל את הרשמת <strong>' + this.dataset.name + '</strong>?',
            function() { submitDeleteForm({ deleteWorkshopReg: '1', wsRegWorkshopId: this.dataset.wid, wsRegUserId: this.dataset.uid }); }.bind(this)
        );
    });
}

var delUserButtons = document.querySelectorAll('.delete-user-trigger');
for (var i = 0; i < delUserButtons.length; i++) {
    delUserButtons[i].addEventListener('click', function() {
        openDeleteModal(
            'האם למחוק את המשתמש <strong>' + this.dataset.userName + '</strong>?<br><small style="color:var(--text3)">יימחקו גם כל ההרשמות שלו.</small>',
            function() { submitDeleteForm({ deleteUser: '1', userId: this.dataset.userId }); }.bind(this)
        );
    });
}

var delLecButtons = document.querySelectorAll('.delete-lecturer-trigger');
for (var i = 0; i < delLecButtons.length; i++) {
    delLecButtons[i].addEventListener('click', function() {
        openDeleteModal(
            'האם למחוק את המרצה <strong>' + this.dataset.lecName + '</strong>?<br><small style="color:var(--text3)">יימחקו גם כל האירועים, הסדנאות וההרשמות שלו.</small>',
            function() { submitDeleteForm({ deleteLecturer: '1', lecturerId: this.dataset.lecId }); }.bind(this),
            true
        );
    });
}

// כפתור אישור מחיקה — מבצע את הפעולה שנשמרה
if (confirmBtn) {
    confirmBtn.addEventListener('click', function() {
        if (typeof deleteAction === 'function') { deleteAction(); }
    });
}

// כפתור ביטול — סוגר את ה-modal
if (cancelBtn) {
    cancelBtn.addEventListener('click', closeDeleteModal);
}

// סגירת modal בלחיצה על הרקע
if (modal) {
    modal.addEventListener('click', function(e) {
        if (e.target === modal) { closeDeleteModal(); }
    });
}

</script>
</body>
</html>