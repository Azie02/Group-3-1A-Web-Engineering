<?php
 // Start the session
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "FKParkSystem", 3306);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Restrict access to logged-in student only
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'student') {
    header("Location: Login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$message = "";

// HANDLE VEHICLE REGISTRATION
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $vehicleID     = uniqid("V"); // auto-generate ID
    $vehicleType   = $_POST['vehicleType'];
    $plateNumber   = $_POST['plateNumber'];
    $vehicleModel  = $_POST['vehicleModel'];
    $vehicleColour = $_POST['vehicleColour'];

    // Handle vehicle grant upload
    $vehicleGrant = null;
    if (!empty($_FILES['vehicleGrant']['tmp_name'])) {
        $vehicleGrant = file_get_contents($_FILES['vehicleGrant']['tmp_name']);
    }

    $stmt = $conn->prepare("
        INSERT INTO Vehicle
        (VehicleID, StudentID, VehicleType, PlateNumber, VehicleModel, VehicleColour, VehicleGrant, VehicleApproval)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')
    ");

    $stmt->bind_param(
        "sssssss",
        $vehicleID,
        $student_id,
        $vehicleType,
        $plateNumber,
        $vehicleModel,
        $vehicleColour,
        $vehicleGrant
    );

    if ($stmt->execute()) {
        $success = "Vehicle registered successfully. Awaiting approval.";
    } else {
        $error = "Error registering vehicle.";
    }

    $stmt->close();
}

// FETCH STUDENT VEHICLES
$stmt = $conn->prepare("
    SELECT VehicleType, PlateNumber, VehicleModel, VehicleColour, VehicleApproval
    FROM Vehicle
    WHERE studentID = ?
");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Vehicle Registration</title>
    <style>
        body {
            font-family: Arial;
            background: #f4f6f8;
        }
        .container {
            width: 800px;
            margin: auto;
            background: #fff;
            padding: 20px;
        }
        h2 {
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: center;
        }
        th {
            background: #eee;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }
    </style>
</head>

<body>

<div class="container">

    <h2>Register Vehicle</h2>

    <?php if (isset($success)) echo "<p class='success'>$success</p>"; ?>
    <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>

    <form method="POST" enctype="multipart/form-data">

        <label>Vehicle Type</label><br>
        <select name="vehicleType" required>
            <option value="">-- Select --</option>
            <option value="Car">Car</option>
            <option value="Motorcycle">Motorcycle</option>
        </select><br><br>

        <label>Plate Number</label><br>
        <input type="text" name="plateNumber" required><br><br>

        <label>Vehicle Model</label><br>
        <input type="text" name="vehicleModel" required><br><br>

        <label>Vehicle Colour</label><br>
        <input type="text" name="vehicleColour" required><br><br>

        <label>Vehicle Grant (PDF/Image)</label><br>
        <input type="file" name="vehicleGrant" accept=".pdf,image/*" required><br><br>

        <button type="submit">Register Vehicle</button>
    </form>

    <h2>My Registered Vehicles</h2>

    <table>
        <tr>
            <th>Type</th>
            <th>Plate</th>
            <th>Model</th>
            <th>Colour</th>
            <th>Status</th>
        </tr>

        <?php while ($row = $result->fetch_assoc()) { ?>
            <tr>
                <td><?= htmlspecialchars($row['VehicleType']) ?></td>
                <td><?= htmlspecialchars($row['PlateNumber']) ?></td>
                <td><?= htmlspecialchars($row['VehicleModel']) ?></td>
                <td><?= htmlspecialchars($row['VehicleColour']) ?></td>
                <td><?= htmlspecialchars($row['VehicleApproval']) ?></td>
            </tr>
        <?php } ?>
    </table>

</div>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>