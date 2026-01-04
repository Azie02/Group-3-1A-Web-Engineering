<?php
// scan.php - public scan handler for QR codes (space or booking)
// Edit DB credentials:
$DB_HOST='127.0.0.1'; $DB_NAME='fkparksystem'; $DB_USER='root'; $DB_PASS='';
$mysqli = new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if ($mysqli->connect_errno) die("DB error");

// Simple session for logged-in user (optional)
session_start();
$currentStudent = $_SESSION['user_id'] ?? null;

// Utility
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Handle POST actions: checkin / checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'start_session') {
        // inputs: parking_space_id OR booking_id, plate number optional
        $space = $_POST['parking_space_id'] ?? null;
        $booking = $_POST['booking_id'] ?? null;
        // Determine student: if logged in, use session; if booking, use booking student
        if (!$space && !$booking) { $error = "Missing identifiers"; }
        else {
            if ($booking) {
                // fetch booking student and space
                $stmt = $mysqli->prepare("SELECT StudentID, ParkingSpaceID FROM Booking WHERE BookingID = ?");
                $stmt->bind_param("s", $booking);
                $stmt->execute(); $res = $stmt->get_result(); $b = $res->fetch_assoc(); $stmt->close();
                if (!$b) $error = "Invalid booking";
                else {
                    $studentID = $b['StudentID'];
                    $space = $b['ParkingSpaceID'];
                }
            } else {
                // walk-in: must be logged in
                if (!$currentStudent) $error = "Please login to start a parking session";
                else $studentID = $currentStudent;
            }
            if (empty($error)) {
                // check if active session exists for this booking/space/student
                $check = $mysqli->prepare("SELECT SessionID FROM ParkingSession WHERE ParkingSpaceID = ? AND Status = 'Active' LIMIT 1");
                $check->bind_param("s", $space);
                $check->execute(); $check->bind_result($existingSession); $check->fetch(); $check->close();
                if ($existingSession) $error = "Space already in active session";
                else {
                    // create session
                    $sessionID = 'S' . time() . rand(100,999);
                    $plate = $_POST['plate'] ?? null;
                    $now = date('Y-m-d H:i:s');
                    $ins = $mysqli->prepare("INSERT INTO ParkingSession (SessionID, BookingID, ParkingSpaceID, StudentID, PlateNumber, StartAt, Status) VALUES (?, ?, ?, ?, ?, ?, 'Active')");
                    $ins->bind_param("ssssss", $sessionID, $booking, $space, $studentID, $plate, $now);
                    $ok = $ins->execute();
                    $ins->close();
                    if ($ok) {
                        $success = "Session started. Session ID: $sessionID";
                    } else $error = "Failed to create session";
                }
            }
        }
    } elseif ($action === 'end_session') {
        $session = $_POST['session_id'] ?? null;
        if (!$session) $error = "Session ID missing";
        else {
            $now = date('Y-m-d H:i:s');
            $upd = $mysqli->prepare("UPDATE ParkingSession SET EndAt = ?, Status = 'Ended' WHERE SessionID = ? AND Status = 'Active'");
            $upd->bind_param("ss", $now, $session);
            $upd->execute();
            if ($upd->affected_rows > 0) $success = "Session ended";
            else $error = "Session not found or already ended";
            $upd->close();
        }
    }
}

// Show page for type=space or type=booking
$type = $_GET['type'] ?? 'space';
$id = $_GET['id'] ?? null;
?>
<!doctype html><html><head><meta charset="utf-8"><title>Scan</title></head><body>
<?php if (!empty($error)): ?><div style="color:#b33"><?php echo h($error); ?></div><?php endif; ?>
<?php if (!empty($success)): ?><div style="color:#080"><?php echo h($success); ?></div><?php endif; ?>

<?php if ($type === 'space' && $id): 
    // Fetch space info
    $stmt = $mysqli->prepare("SELECT ps.ParkingSpaceID, ps.SpaceNumber, ps.SpaceType, pa.AreaType, pa.AreaNumber
                              FROM ParkingSpace ps JOIN ParkingArea pa ON ps.ParkingAreaID = pa.ParkingAreaID
                              WHERE ps.ParkingSpaceID = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute(); $res = $stmt->get_result(); $space = $res->fetch_assoc(); $stmt->close();
    if (!$space) { echo "<h2>Unknown space</h2>"; }
    else {
        echo "<h2>Space " . h($space['SpaceNumber']) . " (" . h($space['SpaceType']) . ")</h2>";
        echo "<p>Area: " . h($space['AreaType']) . " " . h($space['AreaNumber']) . "</p>";

        // Show today's bookings for this space
        $today = date('Y-m-d');
        $bstmt = $mysqli->prepare("SELECT BookingID, StudentID, BookingTime, BookingStatus FROM Booking WHERE ParkingSpaceID = ? AND DATE(BookingDate) = ? AND BookingStatus IN ('Pending','Confirmed') ORDER BY BookingTime");
        $bstmt->bind_param("ss", $id, $today);
        $bstmt->execute(); $bres = $bstmt->get_result();
        echo "<h4>Today's bookings</h4>";
        if ($bres->num_rows === 0) echo "<p>None</p>"; else {
            echo "<ul>";
            while ($bb = $bres->fetch_assoc()) {
                echo "<li>" . h($bb['BookingTime']) . " - " . h($bb['BookingStatus']) . " (by " . h($bb['StudentID']) . ")</li>";
            }
            echo "</ul>";
        }
        $bstmt->close();

        // show active session if exists
        $sstmt = $mysqli->prepare("SELECT SessionID, StudentID, StartAt FROM ParkingSession WHERE ParkingSpaceID = ? AND Status = 'Active' LIMIT 1");
        $sstmt->bind_param("s", $id);
        $sstmt->execute(); $sres = $sstmt->get_result(); $active = $sres->fetch_assoc(); $sstmt->close();
        if ($active) {
            echo "<h4>Space Currently Occupied</h4>";
            echo "<p>By " . h($active['StudentID']) . " since " . h($active['StartAt']) . "</p>";
            // End session form
            echo '<form method="post"><input type="hidden" name="action" value="end_session"><input type="hidden" name="session_id" value="' . h($active['SessionID']) . '"><button type="submit">End Session</button></form>';
        } else {
            // show start session form (walk-in); require login or plate
            echo '<h4>Start Parking (Walk-in)</h4>';
            echo '<form method="post"><input type="hidden" name="action" value="start_session"><input type="hidden" name="parking_space_id" value="' . h($id) . '">';
            if (!$currentStudent) echo '<p>Please enter plate & start (or login first)</p>';
            echo 'Plate: <input name="plate"><br><br>';
            echo '<button type="submit">Start Session</button></form>';
        }
    }
?>

<?php elseif ($type === 'booking' && $id):
    $stmt = $mysqli->prepare("SELECT b.BookingID, b.StudentID, b.ParkingSpaceID, b.BookingDate, b.BookingTime, b.BookingStatus, ps.SpaceNumber FROM Booking b LEFT JOIN ParkingSpace ps ON b.ParkingSpaceID = ps.ParkingSpaceID WHERE b.BookingID = ?");
    $stmt->bind_param("s",$id); $stmt->execute(); $binfo = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$binfo) { echo "<h2>Booking not found</h2>"; }
    else {
        echo "<h2>Booking " . h($binfo['BookingID']) . "</h2>";
        echo "<p>Student: " . h($binfo['StudentID']) . "</p>";
        echo "<p>Space: " . h($binfo['SpaceNumber']) . " (" . h($binfo['ParkingSpaceID']) . ")</p>";
        echo "<p>Date: " . h($binfo['BookingDate']) . " Time: " . h($binfo['BookingTime']) . "</p>";
        echo "<p>Status: " . h($binfo['BookingStatus']) . "</p>";

        // If current logged-in student is the owner and booking is confirmed or pending allow check-in
        if ($currentStudent && $currentStudent === $binfo['StudentID'] && in_array($binfo['BookingStatus'], ['Pending','Confirmed'])) {
            // check if already active session for the booking/space
            $scheck = $mysqli->prepare("SELECT SessionID FROM ParkingSession WHERE BookingID = ? AND Status = 'Active' LIMIT 1");
            $scheck->bind_param("s", $id); $scheck->execute(); $scheck->bind_result($existing); $scheck->fetch(); $scheck->close();
            if ($existing) {
                echo "<p>Session active: " . h($existing) . "</p>";
                echo '<form method="post"><input type="hidden" name="action" value="end_session"><input type="hidden" name="session_id" value="' . h($existing) . '"><button type="submit">End Session</button></form>';
            } else {
                echo '<form method="post"><input type="hidden" name="action" value="start_session"><input type="hidden" name="booking_id" value="' . h($id) . '"><button type="submit">Check-in (Start Parking)</button></form>';
            }
        } else {
            echo '<p>Login as the booking owner to check-in.</p>';
        }
    }
?>

<?php else: ?>
    <h2>Invalid request</h2>
    <p>QR links must contain type=space|booking and id=...</p>
<?php endif; ?>

</body></html>