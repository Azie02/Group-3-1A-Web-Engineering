<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "FKParkSystem", 3306);
if ($conn->connect_error) {
    die("Database connection failed");
}

// Access control: student only
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'student') {
    header("Location: Login.php");
    exit();
}

$studentID = $_SESSION['user_id'];

// Fetch parking areas
$areaQuery = "SELECT * FROM ParkingArea WHERE AreaStatus='Open'";
$areaResult = $conn->query($areaQuery);

// Handle search
$spaces = [];
if (isset($_GET['area']) && isset($_GET['date']) && isset($_GET['time'])) {
    $area = $_GET['area'];

    $stmt = $conn->prepare("
        SELECT * FROM ParkingSpace 
        WHERE ParkingAreaID = ? AND BookingID IS NULL
    ");
    $stmt->bind_param("s", $area);
    $stmt->execute();
    $spaces = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Book Parking</title>
</head>
<body>

<h2>Book Parking</h2>

<form method="GET">
    <label>Parking Area</label><br>
    <select name="area" required>
        <option value="">-- Select Area --</option>
        <?php while ($area = $areaResult->fetch_assoc()): ?>
            <option value="<?= $area['ParkingAreaID'] ?>">
                <?= $area['AreaType'] ?>
            </option>
        <?php endwhile; ?>
    </select><br><br>

    <label>Date</label><br>
    <input type="date" name="date" required><br><br>

    <label>Time</label><br>
    <input type="time" name="time" required><br><br>

    <button type="submit">Search Available Space</button>
</form>

<hr>

<?php if (!empty($spaces)): ?>
<h3>Available Parking Spaces</h3>

<table border="1" cellpadding="10">
    <tr>
        <th>Space Number</th>
        <th>Type</th>
        <th>Status</th>
        <th>Action</th>
    </tr>

    <?php while ($row = $spaces->fetch_assoc()): ?>
    <tr>
        <td><?= $row['SpaceNumber'] ?></td>
        <td><?= $row['SpaceType'] ?></td>
        <td>Available</td>
        <td>
            <a href="BookingConfirm.php?
                space=<?= $row['ParkingSpaceID'] ?>&
                date=<?= $_GET['date'] ?>&
                time=<?= $_GET['time'] ?>">
                Book
            </a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

<?php elseif (isset($_GET['area'])): ?>
<p>No available parking spaces found.</p>
<?php endif; ?>

</body>
</html>
