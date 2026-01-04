<?php
session_start();

/*
  BookingDashboard.php
  - Single-file: Bootstrap-styled search + results table (Book Now / Details).
  - Edit DB config below if needed.
*/

/* ---------- DB config ---------- */
$dbHost = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "FKParkSystem";
$dbPort = 3306;
/* ------------------------------- */

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Simple access control (use your real login logic)
if (!isset($_SESSION['user_id']) || ($_SESSION['type_user'] ?? '') !== 'student') {
    header("Location: Login.php");
    exit();
}
$studentID = $_SESSION['user_id'];

/* Utility: safe output */
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* Normalize incoming date and time to Y-m-d and H:i:s */
function normalizeDate($rawDate) {
    $rawDate = trim($rawDate);
    if (!$rawDate) return null;
    // If browser date input used, it will usually be Y-m-d already
    $dt = DateTime::createFromFormat('Y-m-d', $rawDate);
    if ($dt && $dt->format('Y-m-d') === $rawDate) return $rawDate;
    // Common other formats
    $formats = ['d/m/Y','m/d/Y','d-m-Y','Y/m/d'];
    foreach ($formats as $fmt) {
        $d = DateTime::createFromFormat($fmt, $rawDate);
        if ($d) return $d->format('Y-m-d');
    }
    // Fallback parse
    try { $d = new DateTime($rawDate); return $d->format('Y-m-d'); } catch (Exception $e) {}
    return null;
}
function normalizeTime($rawTime) {
    $rawTime = trim($rawTime);
    if (!$rawTime) return null;
    $formats = ['H:i','H:i:s','g:i A','g:iA','h:i A','h:iA'];
    foreach ($formats as $fmt) {
        $t = DateTime::createFromFormat($fmt, $rawTime);
        if ($t) return $t->format('H:i:s');
    }
    try { $t = new DateTime($rawTime); return $t->format('H:i:s'); } catch (Exception $e) {}
    return null;
}

/* Fetch parking areas for the select */
$areaQuery = "SELECT ParkingAreaID, AreaType, AreaNumber FROM ParkingArea ORDER BY ParkingAreaID";
$areaResult = $conn->query($areaQuery);

/* Handle search */
$spaces = null;
$searchPerformed = false;
if (isset($_GET['area']) && isset($_GET['date']) && isset($_GET['time'])) {
    $searchPerformed = true;
    $area = $_GET['area'];
    $rawDate = $_GET['date'];
    $rawTime = $_GET['time'];

    $date = normalizeDate($rawDate);
    $time = normalizeTime($rawTime);

    if (!$date || !$time) {
        $error = "Unable to parse date or time. Please use the date/time controls.";
    } else {
        // Query: select all spaces in the area that are NOT blocked by a booking for that date/time
        // We treat Pending/Confirmed as blocking; if you use ExpiresAt for Pending holds, add a check.
        $sql = "SELECT ps.ParkingSpaceID, ps.SpaceNumber, ps.SpaceType
                FROM ParkingSpace ps
                WHERE ps.ParkingAreaID = ?
                  AND NOT EXISTS (
                      SELECT 1 FROM Booking b
                      WHERE b.ParkingSpaceID = ps.ParkingSpaceID
                        AND DATE(b.BookingDate) = ?
                        AND TIME(b.BookingTime) = ?
                        AND b.BookingStatus IN ('Pending','Confirmed')
                        -- If you use ExpiresAt for holds, uncomment the next line to ignore expired holds:
                        -- AND (b.ExpiresAt IS NULL OR b.ExpiresAt > NOW())
                  )
                ORDER BY ps.SpaceNumber ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $area, $date, $time);
        $stmt->execute();
        $spaces = $stmt->get_result();
        $stmt->close();
    }
}

/* Handle booking POST (Book Now) */
$feedback = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book_now') {
    $spaceID = $_POST['space_id'] ?? '';
    $bookDate = $_POST['book_date'] ?? '';
    $bookTime = $_POST['book_time'] ?? '';

    $bookDate = normalizeDate($bookDate);
    $bookTime = normalizeTime($bookTime);

    if (!$spaceID || !$bookDate || !$bookTime) {
        $feedback = ['type'=>'danger','msg'=>'Missing booking information.'];
    } else {
        // Use an atomic INSERT ... SELECT ... WHERE NOT EXISTS to avoid race conditions.
        $bookingID = 'B' . time() . rand(100,999);
        $insertSql = "INSERT INTO Booking (BookingID, StudentID, ParkingSpaceID, BookingDate, BookingTime, BookingStatus)
                      SELECT ?, ?, ?, ?, ?, 'Confirmed'
                      FROM DUAL
                      WHERE NOT EXISTS (
                        SELECT 1 FROM Booking b
                        WHERE b.ParkingSpaceID = ?
                          AND DATE(b.BookingDate) = ?
                          AND TIME(b.BookingTime) = ?
                          AND b.BookingStatus IN ('Pending','Confirmed')
                          -- If using ExpiresAt for holds, consider ignoring expired holds here
                      )
                      LIMIT 1";
        $stmt = $conn->prepare($insertSql);
        $stmt->bind_param("ssssssss", $bookingID, $studentID, $spaceID, $bookDate, $bookTime, $spaceID, $bookDate, $bookTime);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $feedback = ['type'=>'success','msg'=>"Booking confirmed (ID: $bookingID)."];
            } else {
                $feedback = ['type'=>'warning','msg'=>"Space is no longer available for the selected date/time."];
            }
        } else {
            $feedback = ['type'=>'danger','msg'=>'Booking failed: ' . $stmt->error];
        }
        $stmt->close();
    }
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Book Parking</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <!-- Bootstrap 5 CDN -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f3f6fb; }
    .card { border-radius:12px; }
    .btn-primary { background:#0d6efd; border-color:#0d6efd; }
    .btn-success { background:#20c997; border-color:#20c997; }
    .btn-details { background:#10b981; border-color:#10b981; }
    table th { background:#f1f5fb; }
  </style>
</head>
<body>
  <div class="container py-4">
    <div class="card p-4 shadow-sm">
      <h3 class="mb-3 text-primary">Book Parking</h3>

      <?php if (!empty($feedback)): ?>
        <div class="alert alert-<?php echo h($feedback['type']); ?>"><?php echo h($feedback['msg']); ?></div>
      <?php endif; ?>

      <form class="row g-3 align-items-end mb-3" method="GET" id="searchForm">
        <div class="col-md-4">
          <label class="form-label">Parking Area</label>
          <select name="area" id="area" class="form-select" required>
            <option value="">-- Select Area --</option>
            <?php while ($areaRow = $areaResult->fetch_assoc()): ?>
              <?php $sel = (isset($_GET['area']) && $_GET['area'] === $areaRow['ParkingAreaID']) ? 'selected' : ''; ?>
              <option value="<?php echo h($areaRow['ParkingAreaID']); ?>" <?php echo $sel; ?>>
                <?php echo h($areaRow['AreaType'] . (isset($areaRow['AreaNumber']) ? ' - ' . $areaRow['AreaNumber'] : '')); ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Date</label>
          <input type="date" name="date" id="date" class="form-control" required value="<?php echo isset($_GET['date']) ? h($_GET['date']) : ''; ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">Time</label>
          <input type="time" name="time" id="time" class="form-control" required value="<?php echo isset($_GET['time']) ? h($_GET['time']) : ''; ?>">
        </div>

        <div class="col-md-1 text-end">
          <button type="submit" class="btn btn-primary">Search Available Space</button>
        </div>
      </form>

      <hr>

      <?php if ($searchPerformed): ?>
        <?php if (isset($error)): ?>
          <div class="alert alert-danger"><?php echo h($error); ?></div>
        <?php else: ?>
          <?php if ($spaces && $spaces->num_rows > 0): ?>
            <h5 class="mb-3">Available Parking Spaces</h5>
            <div class="table-responsive">
              <table class="table table-bordered align-middle">
                <thead>
                  <tr>
                    <th style="width:40%;">Space Number</th>
                    <th style="width:30%;">Type</th>
                    <th style="width:30%;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($row = $spaces->fetch_assoc()): ?>
                    <tr>
                      <td><?php echo h($row['SpaceNumber']); ?></td>
                      <td><?php echo h($row['SpaceType']); ?></td>
                      <td>
                        <div class="d-flex gap-2">
                          <form method="POST" style="display:inline-block" onsubmit="return confirm('Confirm booking for <?php echo h($row['SpaceNumber']); ?> on <?php echo h($_GET['date']); ?> at <?php echo h($_GET['time']); ?>?');">
                            <input type="hidden" name="action" value="book_now">
                            <input type="hidden" name="space_id" value="<?php echo h($row['ParkingSpaceID']); ?>">
                            <input type="hidden" name="book_date" value="<?php echo h($_GET['date']); ?>">
                            <input type="hidden" name="book_time" value="<?php echo h($_GET['time']); ?>">
                            <button type="submit" class="btn btn-primary btn-sm">Book Now</button>
                          </form>

                          <a class="btn btn-success btn-sm" href="BookingConfirm.php?space=<?php echo urlencode($row['ParkingSpaceID']); ?>&date=<?php echo urlencode($_GET['date']); ?>&time=<?php echo urlencode($_GET['time']); ?>">Details</a>
                        </div>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="text-muted">No available parking spaces found for the selected area/date/time.</p>
          <?php endif; ?>
        <?php endif; ?>
      <?php else: ?>
        <p class="text-muted">Please choose area, date and time, then click <strong>Search Available Space</strong>.</p>
      <?php endif; ?>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>