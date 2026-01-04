<?php
// generate_qr.php - admin tool (quick Google Chart method)
// Edit DB credentials and base URL
$DB_HOST='127.0.0.1'; $DB_NAME='fkparksystem'; $DB_USER='root'; $DB_PASS='';
$BASE_URL = 'https://yourdomain.example/scan.php'; // change to your site root
$QR_FOLDER = __DIR__ . '/qrcodes'; // must be writable by PHP

// ensure folder
if (!is_dir($QR_FOLDER)) mkdir($QR_FOLDER, 0755, true);

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) die("DB connection failed");

// Simple admin guard - replace with real auth
session_start();
if (!($_SESSION['type_user'] ?? '') || $_SESSION['type_user'] !== 'staff') {
    // for testing you can comment the exit below
    // exit("Admin only");
}

// Helper to create QR (Google Chart method) and save locally
function generate_qr_google($targetUrl, $filePath) {
    $api = 'https://chart.googleapis.com/chart?cht=qr&chs=400x400&chl=' . urlencode($targetUrl);
    $img = @file_get_contents($api);
    if ($img === false) return false;
    file_put_contents($filePath, $img);
    return true;
}

// Handle generate for a single space when form posted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['space_id'])) {
    $spaceID = $_POST['space_id'];
    // Build scan URL for space
    $url = $BASE_URL . '?type=space&id=' . urlencode($spaceID);
    $filename = $spaceID . '.png';
    $path = $QR_FOLDER . '/' . $filename;
    if (generate_qr_google($url, $path)) {
        // store or update QRCode table
        $img_url = 'qrcodes/' . $filename; // relative path to serve via web
        // Upsert: if QRCode exists for this ParkingSpaceID update; else insert
        $stmt = $mysqli->prepare("SELECT QRCodeID FROM QRCode WHERE ParkingSpaceID = ?");
        $stmt->bind_param("s", $spaceID);
        $stmt->execute();
        $stmt->bind_result($existing);
        $stmt->fetch();
        $stmt->close();
        if ($existing) {
            $u = $mysqli->prepare("UPDATE QRCode SET Image_URL = ?, QR_Description = ? WHERE ParkingSpaceID = ?");
            $desc = "QR for space $spaceID";
            $u->bind_param("sss", $img_url, $desc, $spaceID);
            $u->execute();
            $u->close();
        } else {
            $id = 'QR' . time() . rand(100,999);
            $ins = $mysqli->prepare("INSERT INTO QRCode (QRCodeID, Image_URL, QR_Description, ParkingSpaceID) VALUES (?, ?, ?, ?)");
            $desc = "QR for space $spaceID";
            $ins->bind_param("ssss", $id, $img_url, $desc, $spaceID);
            $ins->execute();
            $ins->close();
        }
        $msg = "QR generated and saved to $img_url";
    } else {
        $msg = "Failed to generate QR (Google API unreachable)";
    }
}

// Fetch spaces for admin list
$spaces = $mysqli->query("SELECT ParkingSpaceID, SpaceNumber, ParkingAreaID FROM ParkingSpace ORDER BY ParkingAreaID, SpaceNumber");
?>
<!doctype html><html><head><meta charset="utf-8"><title>Generate QR - Admin</title></head><body>
<h2>Generate ParkingSpace QR</h2>
<?php if (!empty($msg)) echo "<p><strong>$msg</strong></p>"; ?>
<table border="1" cellpadding="6" cellspacing="0">
<tr><th>ParkingSpaceID</th><th>SpaceNumber</th><th>Area</th><th>QR</th></tr>
<?php while ($r = $spaces->fetch_assoc()): ?>
<tr>
  <td><?php echo htmlspecialchars($r['ParkingSpaceID']); ?></td>
  <td><?php echo htmlspecialchars($r['SpaceNumber']); ?></td>
  <td><?php echo htmlspecialchars($r['ParkingAreaID']); ?></td>
  <td>
    <form method="post" style="display:inline-block">
      <input type="hidden" name="space_id" value="<?php echo htmlspecialchars($r['ParkingSpaceID']); ?>">
      <button type="submit">Generate QR</button>
    </form>
    <!-- show QR image if exists -->
    <?php
      $qrImg = __DIR__ . '/qrcodes/' . $r['ParkingSpaceID'] . '.png';
      if (file_exists($qrImg)) {
        echo ' <a href="qrcodes/' . rawurlencode($r['ParkingSpaceID']) . '.png" target="_blank">View QR</a>';
      }
    ?>
  </td>
</tr>
<?php endwhile; ?>
</table>
<p>QR images saved in the qrcodes/ folder. Ensure your web server can serve that folder.</p>
</body></html>