<?php
require_once 'includes/db.php';

$errors  = [];
$success = false;
$refNo   = '';
$isDup   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['citizen_name']    ?? '');
    $contact = trim($_POST['citizen_contact'] ?? '');
    $catId   = (int)($_POST['category_id']   ?? 0);
    $loc     = trim($_POST['location']        ?? '');
    $desc    = trim($_POST['description']     ?? '');

    if (!$name)              $errors['name']    = 'Please enter your full name.';
    if (!$contact)           $errors['contact'] = 'Please enter a phone number or email.';
    if (!$catId)             $errors['cat']     = 'Please select a complaint category.';
    if (!$loc)               $errors['loc']     = 'Please enter your location.';
    if (strlen($desc) < 10)  $errors['desc']    = 'Please describe your complaint (minimum 10 characters).';

    if (empty($errors)) {
        $pdo = db();

        // Get dept_id from category (auto-routing SO2)
        $cat  = $pdo->prepare("SELECT * FROM categories WHERE category_id = ?");
        $cat->execute([$catId]);
        $catRow = $cat->fetch();
        $deptId = $catRow['dept_id'] ?? 1;

        // Duplicate detection (SO4)
        $dupCheck = $pdo->prepare(
            "SELECT COUNT(*) as cnt FROM complaints
             WHERE category_id = ? AND location = ?
             AND submitted_at >= (CURRENT_TIMESTAMP - INTERVAL '7 days')
             AND status NOT IN ('resolved','closed')"
        );
        $dupCheck->execute([$catId, $loc]);
        $isDup = ($dupCheck->fetch()['cnt'] > 0);

        // Generate reference number (SO1)
        $refNo = generateRefNo();

        // Save complaint
        $ins = $pdo->prepare(
            "INSERT INTO complaints
             (reference_no, citizen_name, citizen_contact, category_id, dept_id,
              location, description, status, is_duplicate_flag, submitted_at)
             VALUES (?,?,?,?,?,?,?,'submitted',?::boolean,NOW())"
        );
        $ins->execute([$refNo, $name, $contact, $catId, $deptId,
                       $loc, $desc, ($isDup ? 'true' : 'false')]);

        $success = true;
    }
}

// Load categories for dropdown
$pdo  = db();
$cats = $pdo->query(
    "SELECT c.*, d.dept_name FROM categories c
     JOIN departments d ON c.dept_id = d.dept_id ORDER BY d.dept_name, c.category_name"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Submit a Complaint — KCC</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<style>
.layout{max-width:860px;margin:28px auto;padding:0 24px;display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start}
.sidebar-info{display:flex;flex-direction:column;gap:14px}
.step-item{display:flex;gap:10px;align-items:flex-start;margin-bottom:8px}
.step-num{width:22px;height:22px;border-radius:50%;background:var(--navy);color:white;
  font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.step-text{font-size:12px;color:#444;line-height:1.4}
.cat-item{display:flex;justify-content:space-between;padding:7px 10px;
  background:var(--slate);border-radius:5px;font-size:12px;margin-bottom:5px}
.cat-dept{font-size:11px;color:var(--muted)}
.char-count{font-size:11px;color:var(--muted);text-align:right;margin-top:3px}
.ref-note{display:flex;align-items:flex-start;gap:8px;padding:11px 13px;
  background:var(--lgray);border-radius:6px;font-size:12px;color:var(--muted);
  line-height:1.5;margin-top:12px}
@media(max-width:768px){.layout{grid-template-columns:1fr}.sidebar-info{display:none}}
</style>
</head>
<body>

<header class="topbar">
  <div class="topbar-brand">
    <div class="topbar-logo">
      <svg viewBox="0 0 24 24" fill="none" stroke="#0F2A52" stroke-width="2.5">
        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/>
      </svg>
    </div>
    <div><div class="topbar-name">Kiambu County Government</div><div class="topbar-sub">Complaint Management System</div></div>
  </div>
  <div class="topbar-links">
    <a href="track.php">Track complaint</a>
    <a href="login.php">Staff login</a>
  </div>
</header>

<div class="hero">
  <h1>Submit a Complaint</h1>
  <p>Report a concern to your county government. You will receive a reference number to track your complaint — no account needed.</p>
</div>

<div class="layout">
  <div>
    <!-- Success message -->
    <?php if ($success): ?>
    <div class="ref-box">
      <h3>✓ Complaint submitted successfully</h3>
      <div class="ref-no"><?= e($refNo) ?></div>
      <p>Write down this reference number. <a href="track.php?ref=<?= urlencode($refNo) ?>" style="color:var(--green);font-weight:600">Track your complaint status →</a></p>
    </div>
    <?php if ($isDup): ?>
    <div class="alert alert-warn">⚠ A similar complaint was recently submitted for this location and category. Your complaint has been saved and flagged for officer review.</div>
    <?php endif; ?>
    <?php endif; ?>

    <div class="card">
      <div class="card-head"><h2>Complaint Details</h2></div>
      <div class="card-body">
        <form method="POST" action="submit.php">

          <div class="form-row">
            <div class="form-field">
              <label>Full Name <span class="req">*</span></label>
              <input type="text" name="citizen_name"
                     value="<?= e($_POST['citizen_name'] ?? '') ?>"
                     placeholder="e.g. Jane Wanjiru Kamau">
              <?php if (isset($errors['name'])): ?>
                <div class="field-err"><?= e($errors['name']) ?></div>
              <?php endif; ?>
            </div>
            <div class="form-field">
              <label>Phone Number or Email <span class="req">*</span></label>
              <input type="text" name="citizen_contact"
                     value="<?= e($_POST['citizen_contact'] ?? '') ?>"
                     placeholder="e.g. 0712 345 678">
              <?php if (isset($errors['contact'])): ?>
                <div class="field-err"><?= e($errors['contact']) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="form-row">
            <div class="form-field">
              <label>Sub-County / Location <span class="req">*</span></label>
              <input type="text" name="location"
                     value="<?= e($_POST['location'] ?? '') ?>"
                     placeholder="e.g. Githunguri, Kiambu County">
              <?php if (isset($errors['loc'])): ?>
                <div class="field-err"><?= e($errors['loc']) ?></div>
              <?php endif; ?>
            </div>
            <div class="form-field">
              <label>Complaint Category <span class="req">*</span></label>
              <select name="category_id">
                <option value="">— Select a category —</option>
                <?php
                $currentDept = '';
                foreach ($cats as $c):
                    if ($c['dept_name'] !== $currentDept) {
                        if ($currentDept) echo '</optgroup>';
                        echo '<optgroup label="' . e($c['dept_name']) . '">';
                        $currentDept = $c['dept_name'];
                    }
                    $sel = (isset($_POST['category_id']) && $_POST['category_id'] == $c['category_id']) ? 'selected' : '';
                    echo "<option value=\"{$c['category_id']}\" $sel>" . e($c['category_name']) . "</option>";
                endforeach;
                if ($currentDept) echo '</optgroup>';
                ?>
              </select>
              <?php if (isset($errors['cat'])): ?>
                <div class="field-err"><?= e($errors['cat']) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="form-row single">
            <div class="form-field">
              <label>Complaint Description <span class="req">*</span></label>
              <textarea name="description" id="descArea" maxlength="1000"
                        placeholder="Describe your complaint in detail — what happened, where exactly, and when it started…"><?= e($_POST['description'] ?? '') ?></textarea>
              <div class="char-count"><span id="charCount">0</span> / 1000 characters</div>
              <?php if (isset($errors['desc'])): ?>
                <div class="field-err"><?= e($errors['desc']) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="ref-note">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6b7a99" stroke-width="2.2">
              <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
              <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            A unique reference number will be generated on submission. No account required.
          </div>

          <button type="submit" class="btn btn-primary btn-full" style="margin-top:14px">
            Submit Complaint
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Sidebar -->
  <div class="sidebar-info">
    <div class="card">
      <div class="card-head"><h2 style="font-size:13px">How this works</h2></div>
      <div class="card-body">
        <div class="step-item"><div class="step-num">1</div><div class="step-text"><strong>Fill in the form</strong> and describe your complaint clearly.</div></div>
        <div class="step-item"><div class="step-num">2</div><div class="step-text"><strong>Get a reference number</strong> — save it to track progress.</div></div>
        <div class="step-item"><div class="step-num">3</div><div class="step-text"><strong>Auto-routed</strong> to the relevant department immediately.</div></div>
        <div class="step-item"><div class="step-num">4</div><div class="step-text"><strong>Track anytime</strong> using your reference number.</div></div>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h2 style="font-size:13px">Categories</h2></div>
      <div class="card-body" style="padding:14px">
        <?php foreach ($cats as $c): ?>
        <div class="cat-item">
          <span><?= e($c['category_name']) ?></span>
          <span class="cat-dept"><?= e(explode(' & ',$c['dept_name'])[0]) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script>
const area = document.getElementById('descArea');
const cnt  = document.getElementById('charCount');
function updateCount(){ cnt.textContent = area.value.length; cnt.style.color = area.value.length > 900 ? '#B91C1C' : ''; }
area.addEventListener('input', updateCount);
updateCount();
</script>
</body>
</html>
