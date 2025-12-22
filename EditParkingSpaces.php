<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "FKParkSystem", 3307);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Restrict access to Administrator
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'Administrator') {
    header("Location: Login.php");
    exit();
}

// Inactivity timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 60) {
    session_unset();
    session_destroy();
    header("Location: Login.php");
    exit();
}
$_SESSION['last_activity'] = time();

// Get ParkingSpaceID
$parkingSpaceID = $_GET['id'] ?? '';
if ($parkingSpaceID === '') {
    header("Location: ParkingManagement.php");
    exit();
}

// Fetch parking space + status
$query = "
    SELECT 
        ps.ParkingSpaceID,
        ps.SpaceNumber,
        ps.SpaceType,
        ps.ParkingAreaID,
        pa.AreaNumber,
        st.SpaceStatus,
        st.DateStatus
    FROM parkingspace ps
    JOIN parkingarea pa ON ps.ParkingAreaID = pa.ParkingAreaID
    LEFT JOIN parkingstatus st ON ps.ParkingSpaceID = st.ParkingSpaceID
    WHERE ps.ParkingSpaceID = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $parkingSpaceID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: ParkingManagement.php");
    exit();
}
$data = $result->fetch_assoc();

$success = '';
$error = '';

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $spaceNumber = trim($_POST['SpaceNumber']);
    $spaceType   = trim($_POST['SpaceType']);
    $spaceStatus = trim($_POST['SpaceStatus']);
    $dateStatus  = $_POST['DateStatus'];

    if ($spaceNumber === '' || $spaceType === '' || $spaceStatus === '') {
        $error = "All fields except Date Status are required.";
    } else {

        // Update parkingspace
        $updateSpace = "
            UPDATE parkingspace
            SET SpaceNumber = ?, SpaceType = ?
            WHERE ParkingSpaceID = ?
        ";
        $stmt = $conn->prepare($updateSpace);
        $stmt->bind_param("sss", $spaceNumber, $spaceType, $parkingSpaceID);
        $stmt->execute();

        // Check if status already exists
        $checkStatus = $conn->prepare(
            "SELECT * FROM parkingstatus WHERE ParkingSpaceID = ?"
        );
        $checkStatus->bind_param("s", $parkingSpaceID);
        $checkStatus->execute();
        $statusResult = $checkStatus->get_result();

        if ($statusResult->num_rows > 0) {
            // Update parkingstatus
            $updateStatus = "
                UPDATE parkingstatus
                SET SpaceStatus = ?, DateStatus = ?
                WHERE ParkingSpaceID = ?
            ";
            $stmt = $conn->prepare($updateStatus);
            $stmt->bind_param("sss", $spaceStatus, $dateStatus, $parkingSpaceID);
        } else {
            // Insert parkingstatus if not exists
            $insertStatus = "
                INSERT INTO parkingstatus (ParkingSpaceID, SpaceStatus, DateStatus)
                VALUES (?, ?, ?)
            ";
            $stmt = $conn->prepare($insertStatus);
            $stmt->bind_param("sss", $parkingSpaceID, $spaceStatus, $dateStatus);
        }

        if ($stmt->execute()) {
            $success = "Parking space updated successfully.";
        } else {
            $error = "Failed to update parking status.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Parking Space</title>
    <style>
        body {
            font-family: Roboto, sans-serif;
            background: #f5f5f5;
        }
        .container {
            margin-left: 250px;
            margin-top: 140px;
            padding: 30px;
        }
        .box {
            background: white;
            padding: 25px;
            width: 520px;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }
        label {
            font-weight: bold;
            margin-top: 15px;
            display: block;
        }
        input, select {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
        }
        .btn {
            margin-top: 20px;
            padding: 10px 15px;
            border: none;
            cursor: pointer;
        }
        .btn-save {
            background: #0066cc;
            color: white;
        }
        .btn-back {
            background: #aaa;
            color: black;
            text-decoration: none;
            padding: 10px 15px;
        }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>

<body>

<div class="container">
    <div class="box">
        <h2>Edit Parking Space</h2>
        <p><strong>Area:</strong> <?= htmlspecialchars($data['AreaNumber']) ?></p>

        <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error"><?= $error ?></p><?php endif; ?>

        <form method="POST">
            <label>Space No</label>
            <input type="text" name="SpaceNumber"
                   value="<?= htmlspecialchars($data['SpaceNumber']) ?>" required>

            <label>Space Type</label>
            <select name="SpaceType" required>
                <option value="Car(Sedan, Hatchback, SUV, EV, Coupe)"<?= $data['SpaceType']=='Car(Sedan, Hatchback, SUV, EV, Coupe)'?'selected':'' ?>>Car(Sedan, Hatchback, SUV, EV, Coupe)</option>
                <option value="Motor(Motorcycle, Scooter)" <?= $data['SpaceType']=='Motor(Motorcycle, Scooter)'?'selected':'' ?>>Motor(Motorcycle, Scooter)</option>
            </select>

            <label>Space Status</label>
            <select name="SpaceStatus" required>
                <option value="Available" <?= $data['SpaceStatus']=='Available'?'selected':'' ?>>Available</option>
                <option value="Full"  <?= $data['SpaceStatus']=='Full'?'selected':'' ?>>Full</option>

            <label>Date Status</label>
            <input type="datetime-local" name="DateStatus"
                   value="<?= $data['DateStatus'] ? date('Y-m-d\TH:i', strtotime($data['DateStatus'])) : '' ?>">

            <br>
            <button type="submit" class="btn btn-save">Save Changes</button>
            <a href="ParkingSpaces.php?id=<?= $data['ParkingAreaID'] ?>" class="btn btn-back">Back</a>
        </form>
    </div>
</div>

</body>
</html>

