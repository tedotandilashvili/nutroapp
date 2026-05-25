<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$db      = getDB();
$db->exec("SET NAMES utf8mb4");
$user_id = (int)$_SESSION['user_id'];
$profile = getUserProfile($user_id);

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $file   = $_FILES['photo'];
    $note   = trim(isset($_POST['note']) ? $_POST['note'] : '');
    $weight = isset($_POST['weight_kg']) && $_POST['weight_kg'] !== '' ? (float)$_POST['weight_kg'] : null;

    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = array('jpg','jpeg','png','heic','heif','webp');
        if (!in_array($ext, $allowed)) {
            setFlash('error', 'მხოლოდ JPG/PNG/HEIC ფორმატია დაშვებული.');
        } else {
            $dir = __DIR__ . '/uploads/progress/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fname = $user_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $dir . $fname)) {
                $db->prepare(
                    'INSERT INTO progress_photos (user_id, filename, note, weight_kg, created_at) VALUES (?,?,?,?,?)'
                )->execute(array($user_id, $fname, $note, $weight, time()));
                if ($weight) {
                    $db->prepare('INSERT INTO weight_logs (user_id,weight_kg,note,logged_at) VALUES (?,?,?,?)')
                       ->execute(array($user_id, $weight, 'Progress photo', time()));
                    $db->prepare('UPDATE user_profiles SET weight_kg=? WHERE user_id=?')->execute(array($weight, $user_id));
                }
                setFlash('success', 'ფოტო შენახულია!');
            } else {
                setFlash('error', 'ფოტო ვერ შეინახა.');
            }
        }
    }
    header('Location: /progress.php'); exit;
}

// Get photos
$photos = $db->prepare('SELECT * FROM progress_photos WHERE user_id=? ORDER BY created_at DESC');
$photos->execute(array($user_id));
$photos = $photos->fetchAll();

renderHeader('Progress ფოტოები', 'progress');
?>
<style>
.photo-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-top:1rem;}
.photo-card{background:#fff;border:1px solid var(--gray-200);border-radius:12px;overflow:hidden;position:relative;}
.photo-card img{width:100%;aspect-ratio:3/4;object-fit:cover;display:block;}
.photo-card-info{padding:8px 10px;}
.photo-card-date{font-size:11px;color:var(--gray-400);}
.photo-card-weight{font-size:14px;font-weight:500;color:#1D9E75;}
.upload-area{border:2px dashed var(--gray-200);border-radius:14px;padding:2rem;text-align:center;cursor:pointer;transition:border-color .15s;background:#fff;}
.upload-area:hover{border-color:#1D9E75;}
.compare-btn{position:absolute;top:6px;right:6px;background:rgba(0,0,0,.5);color:#fff;border:none;border-radius:6px;padding:3px 8px;font-size:11px;cursor:pointer;}
</style>

<div class="page-header">
  <div class="page-title">📸 Progress ფოტოები</div>
  <div class="page-subtitle">ვიზუალური პროგრესის თვალყური</div>
</div>

<!-- Upload form -->
<div class="card">
  <div class="card-title">ახალი ფოტო</div>
  <form method="POST" enctype="multipart/form-data" id="photo-form">
    <div class="upload-area" onclick="document.getElementById('photo-input').click();" id="upload-area">
      <div id="upload-preview" style="display:none;margin-bottom:1rem;">
        <img id="preview-img" style="max-height:200px;border-radius:8px;margin:0 auto;display:block;">
      </div>
      <div id="upload-placeholder">
        <div style="font-size:32px;margin-bottom:.5rem;">📷</div>
        <div style="font-size:14px;color:var(--gray-400);">ფოტო ასარჩევად დააჭირე</div>
        <div style="font-size:12px;color:var(--gray-400);margin-top:4px;">JPG, PNG, HEIC</div>
      </div>
      <input type="file" id="photo-input" name="photo" accept="image/*,image/heic" style="display:none;"
             onchange="previewPhoto(this)">
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px;">
      <div class="form-group" style="margin:0;">
        <label>წონა (კგ) — სურვილისამებრ</label>
        <input type="number" step="0.1" name="weight_kg" class="form-control"
               placeholder="<?php echo $profile ? $profile['weight_kg'] : '70'; ?>">
      </div>
      <div class="form-group" style="margin:0;">
        <label>შენიშვნა</label>
        <input type="text" name="note" class="form-control" placeholder="მაგ. 1 თვის შემდეგ">
      </div>
    </div>
    <button type="submit" class="btn btn-primary btn-full" style="margin-top:10px;" id="upload-btn" disabled>
      ატვირთვა
    </button>
  </form>
</div>

<!-- Photo grid -->
<?php if (empty($photos)): ?>
  <div class="empty-state"><p>ფოტო ჯერ არ არის. ატვირთე პირველი!</p></div>
<?php else: ?>

<?php if (count($photos) >= 2): ?>
<div class="card" style="margin-bottom:1rem;">
  <div class="card-title">შედარება</div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
    <div>
      <div style="font-size:11px;color:var(--gray-400);margin-bottom:4px;">პირველი (<?php echo date('d/m/Y', $photos[count($photos)-1]['created_at']); ?>)</div>
      <img src="/uploads/progress/<?php echo sanitize($photos[count($photos)-1]['filename']); ?>"
           style="width:100%;border-radius:10px;aspect-ratio:3/4;object-fit:cover;">
      <?php if ($photos[count($photos)-1]['weight_kg']): ?>
        <div style="text-align:center;font-size:13px;font-weight:500;margin-top:4px;"><?php echo $photos[count($photos)-1]['weight_kg']; ?> კგ</div>
      <?php endif; ?>
    </div>
    <div>
      <div style="font-size:11px;color:var(--gray-400);margin-bottom:4px;">ბოლო (<?php echo date('d/m/Y', $photos[0]['created_at']); ?>)</div>
      <img src="/uploads/progress/<?php echo sanitize($photos[0]['filename']); ?>"
           style="width:100%;border-radius:10px;aspect-ratio:3/4;object-fit:cover;">
      <?php if ($photos[0]['weight_kg']): ?>
        <div style="text-align:center;font-size:13px;font-weight:500;margin-top:4px;color:#1D9E75;"><?php echo $photos[0]['weight_kg']; ?> კგ
          <?php if ($photos[count($photos)-1]['weight_kg'] && $photos[0]['weight_kg']):
            $diff = round($photos[0]['weight_kg'] - $photos[count($photos)-1]['weight_kg'], 1);
          ?>
            <span style="font-size:12px;color:<?php echo $diff<0?'#0F6E56':'#A32D2D'; ?>;">
              (<?php echo $diff>0?'+':''; ?><?php echo $diff; ?>კგ)
            </span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="photo-grid">
  <?php foreach ($photos as $p): ?>
  <div class="photo-card">
    <img src="/uploads/progress/<?php echo sanitize($p['filename']); ?>"
         loading="lazy" alt="progress">
    <div class="photo-card-info">
      <?php if ($p['weight_kg']): ?>
        <div class="photo-card-weight"><?php echo $p['weight_kg']; ?> კგ</div>
      <?php endif; ?>
      <div class="photo-card-date"><?php echo date('d/m/Y', $p['created_at']); ?></div>
      <?php if ($p['note']): ?>
        <div style="font-size:11px;color:var(--gray-400);"><?php echo sanitize($p['note']); ?></div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
function previewPhoto(input) {
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('preview-img').src = e.target.result;
      document.getElementById('upload-preview').style.display = 'block';
      document.getElementById('upload-placeholder').style.display = 'none';
      document.getElementById('upload-btn').disabled = false;
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
<?php renderFooter(); ?>
