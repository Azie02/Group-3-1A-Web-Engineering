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

// 60-second inactivity timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 60) {
    session_unset();
    session_destroy();
    header("Location: Login.php");
    exit();
}
$_SESSION['last_activity'] = time();

// Get selected ParkingAreaID
if (!isset($_GET['areaID'])) {
    die("Parking Area not specified.");
}
$areaID = $_GET['areaID'];

// Get AreaNumber
$areaNumber = '';
$areaQuery = "SELECT AreaNumber FROM parkingarea WHERE ParkingAreaID = ?";
$stmt = $conn->prepare($areaQuery);
$stmt->bind_param("s", $areaID);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $areaNumber = $result->fetch_assoc()['AreaNumber'];
} else {
    die("Invalid Parking Area.");
}

/* ===================== FUNCTIONS (ALL KEPT) ===================== */

// Generate ParkingSpaceID
function generateParkingSpaceID($conn) {
    $query = "SELECT ParkingSpaceID FROM parkingspace ORDER BY ParkingSpaceID DESC LIMIT 1";
    $result = $conn->query($query);
    $num = ($result->num_rows > 0)
        ? (int) substr($result->fetch_assoc()['ParkingSpaceID'], 2) + 1
        : 1;
    return "PS" . str_pad($num, 2, "0", STR_PAD_LEFT);
}

// Generate ParkingStatusID
function generateParkingStatusID($conn) {
    $query = "SELECT ParkingStatusID FROM parkingstatus ORDER BY ParkingStatusID DESC LIMIT 1";
    $result = $conn->query($query);
    $num = ($result->num_rows > 0)
        ? (int) substr($result->fetch_assoc()['ParkingStatusID'], 3) + 1
        : 1;
    return "PST" . str_pad($num, 2, "0", STR_PAD_LEFT);
}

// Generate NEXT NUMBER ONLY
function generateNextSpaceNumber($conn, $areaID) {
    $query = "
        SELECT SpaceNumber 
        FROM parkingspace 
        WHERE ParkingAreaID = ?
        ORDER BY SpaceNumber DESC 
        LIMIT 1
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $areaID);
    $stmt->execute();
    $result = $stmt->get_result();

    return ($result->num_rows > 0)
        ? ((int)$result->fetch_assoc()['SpaceNumber'] + 1)
        : 101;
}

/* ===================== FORM HANDLING ===================== */
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $spaceNumber = trim($_POST['spaceNumber']);
    $spaceType   = $_POST['spaceType'];
    $spaceStatus = $_POST['spaceStatus'];
    $dateStatus  = $_POST['dateStatus'];

    if ($spaceNumber === '' || $spaceType === '' || $spaceStatus === '' || $dateStatus === '') {
        $error = "All fields are required.";
    } else {
        // Prevent duplicate number in same area
        $check = "SELECT 1 FROM parkingspace WHERE ParkingAreaID = ? AND SpaceNumber = ?";
        $stmt = $conn->prepare($check);
        $stmt->bind_param("ss", $areaID, $spaceNumber);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            $error = "Space number already exists in this area.";
        } else {
            $parkingSpaceID  = generateParkingSpaceID($conn);
            $parkingStatusID = generateParkingStatusID($conn);

            // Insert parkingspace
            $insertSpace = "
                INSERT INTO parkingspace 
                (ParkingSpaceID, ParkingAreaID, SpaceNumber, SpaceType)
                VALUES (?, ?, ?, ?)
            ";
            $stmt = $conn->prepare($insertSpace);
            $stmt->bind_param("ssss", $parkingSpaceID, $areaID, $spaceNumber, $spaceType);
            $stmt->execute();

            // Insert parkingstatus
            $insertStatus = "
                INSERT INTO parkingstatus 
                (ParkingStatusID, ParkingAreaID, ParkingSpaceID, SpaceStatus, DateStatus)
                VALUES (?, ?, ?, ?, ?)
            ";
            $stmt = $conn->prepare($insertStatus);
            $stmt->bind_param("sssss", $parkingStatusID, $areaID, $parkingSpaceID, $spaceStatus, $dateStatus);
            $stmt->execute();

            $success = "Parking space {$areaNumber}-{$spaceNumber} added successfully!";
        }
    }
}

$nextSpaceNumber = generateNextSpaceNumber($conn, $areaID);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Parking Space</title>
    <style>
        body {
            background-color: #f5f5f5;
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .container {
            margin-left: 250px;
            margin-top: 140px;
            padding: 30px;
        }

        .form-box {
            background: white;
            padding: 30px;
            width: 520px;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }

        h2 {
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid #0066cc;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            font-weight: bold;
            display: block;
            margin-bottom: 8px;
            color: #444;
        }

        input[type="number"],
        input[type="date"],
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        input[type="number"]:focus,
        input[type="date"]:focus,
        select:focus {
            border-color: #0066cc;
            outline: none;
            box-shadow: 0 0 5px rgba(0,102,204,0.2);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-save {
            background: #0066cc;
            color: white;
        }

        .btn-save:hover {
            background: #0052a3;
        }

        .btn-back {
            background: #aaa;
            color: black;
            margin-left: 10px;
        }

        .btn-back:hover {
            background: #999;
        }

        .button-group {
            margin-top: 25px;
        }

        .error {
            color: #d32f2f;
            background: #ffebee;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            border-left: 4px solid #d32f2f;
        }

        .success {
            color: #2e7d32;
            background: #e8f5e9;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            border-left: 4px solid #2e7d32;
        }

        .form-note {
            color: #666;
            font-size: 12px;
            margin-top: 5px;
            font-style: italic;
        }

        /* Sidebar and header styling to match ParkingSpaces.php */
        .sidebar {
            background-color: #d890d8ff;
            width: 250px;
            color: black;
            position: fixed;
            top: 120px;
            left: 0;
            bottom: 0;
            padding: 20px 0;
            box-sizing: border-box;
        }

        .header {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            height: 120px;
        }
    </style>
</head>
<body>
    <!-- Note: If you want the full header/sidebar like in ParkingSpaces.php, 
         you would need to include them here. For simplicity, I'm keeping just the form. -->
    
    <div class="container">
        <div class="form-box">
            <h2>Add Parking Space – Area <?= htmlspecialchars($areaNumber) ?></h2>
            
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Space Number</label>
                    <input type="number"
                           name="spaceNumber"
                           value="<?= htmlspecialchars($nextSpaceNumber) ?>"
                           min="1"
                           required>
                    <div class="form-note">Auto-generated next available number for this area</div>
                </div>

                <div class="form-group">
                    <label>Space Type</label>
                    <select name="spaceType" required>
                        <option value="">-- Select Type --</option>
                        <option value="Car(Sedan, Hatchback, SUV, EV, Coupe)">Car(Sedan, Hatchback, SUV, EV, Coupe)</option>
                        <option value="Motor(Motorcycle, Scooter)">Motor(Motorcycle, Scooter)</option>
                    </select>
                    <div class="form-note">Type of vehicle allowed in this space</div>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="spaceStatus" required>
                        <option value="">-- Select Status --</option>
                        <option value="Available">Available</option>
                        <option value="Full">Full</option>
                    </select>
                    <div class="form-note">Current availability status</div>
                </div>

                <div class="form-group">
                    <label>Date Status</label>
                    <input type="date"
                           name="dateStatus"
                           value="<?= date('Y-m-d') ?>"
                           required>
                    <div class="form-note">Date when this status was set</div>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn btn-save">Add Parking Space</button>
                    <a href="ParkingSpaces.php?id=<?= htmlspecialchars($areaID) ?>" class="btn btn-back">Back to Parking Spaces</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>


