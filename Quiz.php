<?php
session_start();

$conn = new mysqli('localhost', 'root', '', 'skillhub');
if ($conn->connect_error) { die("שגיאת חיבור"); }

// בדיקת התחברות
if (!isset($_SESSION['id'])) { header('Location: login.php'); exit(); }
$userId = $_SESSION['id'];
$wsnum  = intval($_GET['wsnum'] ?? 0);
if ($wsnum <= 0) { header('Location: index.php'); exit(); }

// שליפת פרטי הסדנה
$sw = $conn->prepare("SELECT wsnum, WorkshopName, date, time FROM workshop WHERE wsnum = ?");
$sw->bind_param("i", $wsnum);
$sw->execute();
$rw = $sw->get_result();
if ($rw->num_rows === 0) { header('Location: index.php'); exit(); }
$workshop = $rw->fetch_assoc();
$sw->close();

// בדיקה שהסדנה הסתיימה
$isPast = strtotime($workshop['date'].' '.($workshop['time'] ?? '23:59:59')) <= time();
if (!$isPast) { header('Location: workshop_details.php?wsnum='.$wsnum); exit(); }

// בדיקה שהמשתמש נרשם לסדנה
$sr = $conn->prepare("SELECT 1 FROM workshop_registrations WHERE workshop_id=? AND user_id=?");
$sr->bind_param("ii", $wsnum, $userId);
$sr->execute();
$sr->store_result();
if ($sr->num_rows === 0) { header('Location: workshop_details.php?wsnum='.$wsnum); exit(); }
$sr->close();

// שליפת שאלות
$questions = [];
$sq = $conn->prepare("
    SELECT id, question_text, answer_a, answer_b, answer_c, answer_d
    FROM quiz_questions
    WHERE wsnum = ?
    ORDER BY id ASC
");
$sq->bind_param("i", $wsnum);
$sq->execute();
$rq = $sq->get_result();
while ($row = $rq->fetch_assoc()) { $questions[] = $row; }
$sq->close();

// האם כבר ענה?
$alreadyDone = false;
$lastScore   = null;
$sa = $conn->prepare("SELECT score, total FROM quiz_results WHERE wsnum=? AND user_id=?");
$sa->bind_param("ii", $wsnum, $userId);
$sa->execute();
$ra = $sa->get_result();
if ($ra->num_rows > 0) { $alreadyDone = true; $lastScore = $ra->fetch_assoc(); }
$sa->close();

// טיפול בשליחת מבחן
$resultType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz']) && !$alreadyDone) {
    $correct = 0;
    $total   = count($questions);
    foreach ($questions as $q) {
        $selected = $_POST['q_'.$q['id']] ?? '';
        $sc2 = $conn->prepare("SELECT correct_answer FROM quiz_questions WHERE id=?");
        $sc2->bind_param("i", $q['id']);
        $sc2->execute();
        $ans = $sc2->get_result()->fetch_assoc();
        $sc2->close();
        if ($selected === $ans['correct_answer']) { $correct++; }
    }
    $si = $conn->prepare("INSERT INTO quiz_results (wsnum, user_id, score, total) VALUES (?,?,?,?)");
    $si->bind_param("iiii", $wsnum, $userId, $correct, $total);
    $si->execute();
    $si->close();
    $lastScore   = ['score' => $correct, 'total' => $total];
    $alreadyDone = true;
    if ($correct === $total)        { $resultType = 'perfect'; }
    elseif ($correct >= $total*0.6) { $resultType = 'pass'; }
    else                            { $resultType = 'fail'; }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>מבחן — <?= htmlspecialchars($workshop['WorkshopName']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Frank+Ruhl+Libre:wght@400;700;900&family=Heebo:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
    --navy:#1e2d4a;--bg:#f4f6fb;--surface:#fff;--surface2:#f8fafc;
    --border:#e2e8f0;--border2:#cbd5e1;--text:#0f172a;--text2:#334155;--text3:#64748b;
    --green:#16a34a;--green-bg:#f0fdf4;--green-border:#86efac;
    --red:#dc2626;--red-bg:#fef2f2;
    --blue:#2563eb;--blue-bg:#eff6ff;--blue-border:#bfdbfe;
    --shadow:0 4px 16px rgba(0,0,0,.07);--radius:16px;--radius-sm:10px;
}
body{font-family:'Heebo',sans-serif;direction:rtl;background:var(--bg);color:var(--text);min-height:100vh;}
.navbar{position:sticky;top:0;z-index:100;height:62px;padding:0 32px;display:flex;align-items:center;justify-content:space-between;background:rgba(255,255,255,.94);border-bottom:1px solid var(--border);backdrop-filter:blur(14px);box-shadow:0 1px 3px rgba(0,0,0,.06);}
.nav-logo{font-family:'Frank Ruhl Libre',serif;font-size:1.5rem;font-weight:900;color:var(--navy);text-decoration:none;display:flex;align-items:center;gap:8px;}
.nav-logo-dot{width:9px;height:9px;border-radius:50%;background:#1a1a1a;}
.nav-back{padding:7px 14px;border-radius:var(--radius-sm);color:var(--text3);text-decoration:none;font-size:.86rem;font-weight:700;border:1px solid var(--border);transition:.2s;}
.nav-back:hover{background:var(--surface2);color:var(--navy);}
.hero{background:linear-gradient(135deg,var(--navy) 0%,#2d3e5a 100%);padding:34px 32px 38px;}
.hero-inner{max-width:760px;margin:0 auto;}
.hero-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);color:rgba(255,255,255,.75);font-size:.74rem;font-weight:800;margin-bottom:12px;}
.hero-title{font-family:'Frank Ruhl Libre',serif;font-size:1.9rem;font-weight:900;color:#fff;margin-bottom:6px;}
.hero-sub{color:rgba(255,255,255,.6);font-size:.88rem;}
.main{max-width:760px;margin:0 auto;padding:30px 20px 68px;}
.result-banner{padding:22px 24px;border-radius:var(--radius);margin-bottom:26px;display:flex;align-items:center;gap:18px;box-shadow:var(--shadow);}
.result-banner.perfect{background:var(--green-bg);border:1.5px solid var(--green-border);}
.result-banner.pass{background:var(--blue-bg);border:1.5px solid var(--blue-border);}
.result-banner.fail{background:var(--red-bg);border:1.5px solid #fecaca;}
.res-icon{font-size:2.2rem;flex-shrink:0;}
.res-score{font-family:'Frank Ruhl Libre',serif;font-size:2rem;font-weight:900;}
.result-banner.perfect .res-score{color:var(--green);}
.result-banner.pass .res-score{color:var(--blue);}
.result-banner.fail .res-score{color:var(--red);}
.res-label{font-size:.82rem;color:var(--text3);margin-top:2px;}
.res-msg{font-weight:700;font-size:.93rem;margin-top:2px;}
.result-banner.perfect .res-msg{color:var(--green);}
.result-banner.pass .res-msg{color:var(--blue);}
.result-banner.fail .res-msg{color:var(--red);}
.q-card{background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);margin-bottom:16px;overflow:hidden;}
.q-header{display:flex;align-items:center;gap:12px;padding:14px 18px;background:var(--surface2);border-bottom:1px solid var(--border);}
.q-num{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:var(--navy);color:#fff;font-size:.8rem;font-weight:900;flex-shrink:0;}
.q-text{font-weight:700;font-size:.98rem;color:var(--navy);}
.q-answers{padding:14px 18px;display:flex;flex-direction:column;gap:9px;}
.ans-opt{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:var(--radius-sm);border:1.5px solid var(--border);cursor:pointer;transition:.18s;user-select:none;}
.ans-opt:hover{border-color:var(--navy);background:var(--surface2);}
.ans-opt input[type="radio"]{display:none;}
.ans-opt.selected{border-color:var(--navy);background:#f0f4ff;}
.radio-ring{width:20px;height:20px;border-radius:50%;border:2px solid var(--border2);flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:.18s;}
.ans-opt.selected .radio-ring{border-color:var(--navy);background:var(--navy);}
.radio-dot{width:8px;height:8px;border-radius:50%;background:#fff;display:none;}
.ans-opt.selected .radio-dot{display:block;}
.ans-letter{font-size:.74rem;font-weight:900;color:var(--text3);width:16px;text-align:center;}
.ans-text{font-size:.92rem;color:var(--text2);}
.btn-submit{width:100%;padding:13px;background:linear-gradient(90deg,#1a1a1a,#333);color:#fff;border:0;border-radius:var(--radius-sm);font-family:'Heebo',sans-serif;font-size:.97rem;font-weight:800;cursor:pointer;transition:.2s;box-shadow:0 4px 14px rgba(0,0,0,.18);margin-top:22px;}
.btn-submit:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,0,0,.26);}
.btn-back-link{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:var(--radius-sm);background:var(--surface2);color:var(--text2);border:1.5px solid var(--border2);text-decoration:none;font-weight:700;font-size:.88rem;margin-top:18px;transition:.2s;}
.btn-back-link:hover{background:var(--border);}
.empty-box{text-align:center;padding:50px 24px;background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);}
.empty-icon{font-size:2.5rem;display:block;margin-bottom:10px;opacity:.4;}
</style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="nav-logo"><span class="nav-logo-dot"></span>SkillHub</a>
    <a href="workshop_details.php?wsnum=<?= $wsnum ?>" class="nav-back">← חזרה לסדנה</a>
</nav>

<div class="hero">
    <div class="hero-inner">
        <div class="hero-badge">📝 מבחן סיום</div>
        <div class="hero-title"><?= htmlspecialchars($workshop['WorkshopName']) ?></div>
        <div class="hero-sub"><?= count($questions) ?> שאלות · בחר תשובה אחת לכל שאלה</div>
    </div>
</div>

<div class="main">

<?php
// תוצאה אחרי הגשה עכשיו
if ($resultType):
    $icons = ['perfect'=>'🏆','pass'=>'✅','fail'=>'❌'];
    $msgs  = ['perfect'=>'מושלם! ענית נכון על הכל','pass'=>'כל הכבוד, עברת את המבחן!','fail'=>'לא עברת הפעם'];
?>
<div class="result-banner <?= $resultType ?>">
    <div class="res-icon"><?= $icons[$resultType] ?></div>
    <div>
        <div class="res-score"><?= $lastScore['score'] ?>/<?= $lastScore['total'] ?></div>
        <div class="res-label">ניקוד</div>
        <div class="res-msg"><?= $msgs[$resultType] ?></div>
    </div>
</div>
<?php endif; ?>

<?php
// תוצאה קודמת
if ($alreadyDone && !$resultType && $lastScore):
    $pct  = $lastScore['total'] > 0 ? round($lastScore['score'] / $lastScore['total'] * 100) : 0;
    $type = $lastScore['score'] === $lastScore['total'] ? 'perfect' : ($pct >= 60 ? 'pass' : 'fail');
    $icons = ['perfect'=>'🏆','pass'=>'✅','fail'=>'❌'];
    $msgs  = ['perfect'=>'ענית נכון על הכל!','pass'=>'עברת את המבחן','fail'=>'לא עברת'];
?>
<div class="result-banner <?= $type ?>">
    <div class="res-icon"><?= $icons[$type] ?></div>
    <div>
        <div class="res-score"><?= $lastScore['score'] ?>/<?= $lastScore['total'] ?></div>
        <div class="res-label">תוצאה קודמת</div>
        <div class="res-msg"><?= $msgs[$type] ?> — כבר ענית על המבחן</div>
    </div>
</div>
<?php endif; ?>

<?php if (empty($questions)): ?>
    <div class="empty-box">
        <span class="empty-icon">📭</span>
        <div style="color:var(--text3);font-weight:600;">אין שאלות למבחן זה עדיין</div>
    </div>

<?php elseif ($alreadyDone): ?>
    <?php foreach ($questions as $i => $q): ?>
    <div class="q-card">
        <div class="q-header">
            <div class="q-num"><?= $i+1 ?></div>
            <div class="q-text"><?= htmlspecialchars($q['question_text']) ?></div>
        </div>
        <div class="q-answers">
            <?php
            $opts = ['a'=>$q['answer_a'],'b'=>$q['answer_b'],'c'=>$q['answer_c'],'d'=>$q['answer_d']];
            $ltrs = ['a'=>'א','b'=>'ב','c'=>'ג','d'=>'ד'];
            foreach ($opts as $k => $v):
                if (empty($v)) continue;
            ?>
            <div class="ans-opt">
                <div class="radio-ring"><div class="radio-dot"></div></div>
                <span class="ans-letter"><?= $ltrs[$k] ?></span>
                <span class="ans-text"><?= htmlspecialchars($v) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

<?php else: ?>
    <form method="post" id="qForm">
        <?php foreach ($questions as $i => $q): ?>
        <div class="q-card">
            <div class="q-header">
                <div class="q-num"><?= $i+1 ?></div>
                <div class="q-text"><?= htmlspecialchars($q['question_text']) ?></div>
            </div>
            <div class="q-answers">
                <?php
                $opts = ['a'=>$q['answer_a'],'b'=>$q['answer_b'],'c'=>$q['answer_c'],'d'=>$q['answer_d']];
                $ltrs = ['a'=>'א','b'=>'ב','c'=>'ג','d'=>'ד'];
                foreach ($opts as $k => $v):
                    if (empty($v)) continue;
                ?>
                <label class="ans-opt" onclick="pick(this)">
                    <input type="radio" name="q_<?= $q['id'] ?>" value="<?= $k ?>">
                    <div class="radio-ring"><div class="radio-dot"></div></div>
                    <span class="ans-letter"><?= $ltrs[$k] ?></span>
                    <span class="ans-text"><?= htmlspecialchars($v) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <button type="submit" name="submit_quiz" class="btn-submit"
                onclick="return confirm('לשלוח את המבחן?')">
            ✓ שלח מבחן
        </button>
    </form>
<?php endif; ?>

    <a href="workshop_details.php?wsnum=<?= $wsnum ?>" class="btn-back-link">
        ← חזרה לדף הסדנה
    </a>

</div>

<script>
function pick(label) {
    var answers = label.closest('.q-answers').querySelectorAll('.ans-opt');
    answers.forEach(function(a) { a.classList.remove('selected'); });
    label.classList.add('selected');
}
</script>
</body>
</html>