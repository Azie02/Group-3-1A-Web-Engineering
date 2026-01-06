<?php
session_start();

// Single place to define login redirect header
define('LOGIN_REDIRECT', 'Location: Login.php');

// Database connection
$conn = new mysqli("localhost", "root", "", "fkparksystem", 3306);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Restrict access to logged-in student only
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'student') {
    header(LOGIN_REDIRECT);
    exit();
}

$student_id = $_SESSION['user_id'];

// Get student data from database
$query = "SELECT * FROM student WHERE studentID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    session_destroy();
    header(LOGIN_REDIRECT);
    exit();
}
$student = $result->fetch_assoc();

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

    $stmtInsert = $conn->prepare("
        INSERT INTO Vehicle
        (VehicleID, StudentID, VehicleType, PlateNumber, VehicleModel, VehicleColour, VehicleGrant)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmtInsert->bind_param(
        "sssssss",
        $vehicleID,
        $student_id,
        $vehicleType,
        $plateNumber,
        $vehicleModel,
        $vehicleColour,
        $vehicleGrant
    );

    if ($stmtInsert->execute()) {
        $success = "Vehicle registered successfully. Awaiting approval.";
    } else {
        $error = "Error registering vehicle.";
    }

    $stmtInsert->close();
}

// FETCH STUDENT VEHICLES
$stmtVehicles = $conn->prepare("
    SELECT VehicleType, PlateNumber, VehicleModel, VehicleColour
    FROM Vehicle
    WHERE studentID = ?
");
$stmtVehicles->bind_param("s", $student_id);
$stmtVehicles->execute();
$resultVehicles = $stmtVehicles->get_result();

// 60 seconds inactivity timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 60) {
    session_unset();
    session_destroy();
    header(LOGIN_REDIRECT);
    exit();
}
$_SESSION['last_activity'] = time();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Vehicle Registration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

        .header{
            background-color: #008080; 
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            position: fixed;
            width: 100%;
            height: 120px;
            box-sizing: border-box;
            z-index: 1000;
        }

        .header-left{
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 0 35px;
        }

        .header-right{
            display: flex;
            align-items: center;
            gap: 20px;
            padding-right: 20px;
        }

        .logo{
            display: flex;
            gap: 20px;
            align-items: center;
            padding: 0 60px;
        }

        .logo img{
            height: 90px;
            width: auto;
        }

        .sidebar{
            background-color: #008080;
            width: 250px;
            color: white;
            position: fixed;
            top: 120px;
            left: 0;
            bottom: 0;
            padding: 20px 0;
            box-sizing: border-box;
            transition: transform 0.3s ease;
        }

        .sidebartitle{
            color: white;
            font-size: 1rem;
            margin-bottom: 20px;
            padding: 0 20px;
        }

        .menu{
            display: flex;
            flex-direction: column;
            gap: 18px;
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .menutext{
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            padding: 14px 18px;
            color: white;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .menu a {
            text-decoration: none;
            color: inherit;
        }
        
        .menutext:hover {
            background-color: #044747ff;
        }
        
        .menutext.active {
            background-color: #016161ff;
            font-weight: 500;
        }

        .profile{
            background-color: rgba(46, 204, 113, 0.2);
            color: white;
            border: 1px solid rgba(46, 204, 113, 0.3);
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            text-decoration: none;
        }

        .profile:hover {
            background-color: rgba(52, 152, 219, 0.3);
        }

        .logoutbutton {
           background-color: rgba(255, 0, 0, 0.2);
           color: white;
           border: 1px solid rgba(255, 0, 0, 0.3);
           padding: 8px 12px;
           border-radius: 4px;
           cursor: pointer;
           font-size: 1rem;
           display: flex;
           align-items: center;
           gap: 8px;
           text-decoration: none;
        }

        .maincontent{
           margin-left: 250px;
           margin-top: 120px;
           padding: 40px;
           box-sizing: border-box;
        }

        .content {
          background-color: white;
          padding: 30px;
          border-radius: 8px;
          margin-bottom: 25px;
          box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }

        h2 {
            color: #2d3748;
            font-size: 26px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #4a5568;
            font-size: 14px;
        }

        .form-group select,
        .form-group input {
            padding: 10px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-group input[type="file"] {
            padding: 8px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
        }

        .btn-primary {
            background-color: #008080;
            color: white;
        }

        .btn-primary:hover {
            background-color: #006666;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        th {
            background-color: #008080;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 13px;
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .badge-approved {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .text-muted {
            color: #a0aec0;
            text-align: center;
            padding: 40px;
        }

        footer {
           background-color: #80cab1ff;
           color: white;
           padding: 15px 0;
           text-align: center;
           margin-top: auto;
        }
    </style>
</head>
<script>
let timeout = 60;
let warningTime = 10;
let countdown;

function startTimer() {
    clearTimeout(countdown);
    
    countdown = setTimeout(() => {
        let stay = confirm("Your session will expire soon.\n\nClick OK to continue or Cancel to logout.");
        
        if (stay) {
            fetch("keep_alive.php").then(() => {
                startTimer();
            });
        } else {
            window.location.href = "Logout.php";
        }
    }, (timeout - warningTime) * 1000);
}

["click", "mousemove", "keypress"].forEach(event => {
    document.addEventListener(event, startTimer);
});

startTimer();
</script>
<body>
    <header class="header">
        <div class="header_left">
            <div class="logo">
                <img src="UMPLogo.png" alt="UMPLogo">
            </div>
        </div>
        <div class="header-right">
            <span style="color:white; font-weight:500;">
                Welcome, <?php echo htmlspecialchars($student['StudentName']); ?>
            </span>
            <a href="StudentProfile.php" class="profile">
                <i class="fas fa-user-circle"></i> My Profile
            </a>
            <a href="logout.php" class="logoutbutton" onclick="return confirm('Are you sure you want to log out?');">
               <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>
    
    <nav class="sidebar">
        <h1 class="sidebartitle">Student Bar</h1>
        <ul class="menu">
            <li>
                <a href="StudentDashboard.php" class="menutext">Dashboard</a>
            </li>
            <li>
                <a href="VehicleRegistration.php" class="menutext active">Vehicle Registration</a>
            </li>
            <li>
                <a href="Booking.php" class="menutext">Book Parking</a>
            </li>
            <li>
                <a href="MeritStatus.php" class="menutext">Merit status</a>
            </li>
        </ul>
    </nav>

    <div class="maincontent">
        <div class="content">
            <h2>🚗 Register Vehicle</h2>

            <?php if (isset($success)): ?>
                <div class="alert alert-success">✅ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">❌ <?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Vehicle Type *</label>
                        <select name="vehicleType" required>
                            <option value="">-- Select Type --</option>
                            <option value="Car">Car</option>
                            <option value="Motorcycle">Motorcycle</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Plate Number *</label>
                        <input type="text" name="plateNumber" placeholder="e.g., ABC1234" required>
                    </div>

                    <div class="form-group">
                        <label>Vehicle Model *</label>
                        <input type="text" name="vehicleModel" placeholder="e.g., Honda Civic" required>
                    </div>

                    <div class="form-group">
                        <label>Vehicle Colour *</label>
                        <input type="text" name="vehicleColour" placeholder="e.g., Black" required>
                    </div>

                    <div class="form-group full-width">
                        <label>Vehicle Grant (PDF/Image) *</label>
                        <input type="file" name="vehicleGrant" accept=".pdf,image/*" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Register Vehicle</button>
            </form>
        </div>

        <div class="content">
            <h2>📋 My Registered Vehicles</h2>

            <?php if ($resultVehicles->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Plate Number</th>
                            <th>Model</th>
                            <th>Colour</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $resultVehicles->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['VehicleType']) ?></td>
                                <td><strong><?= htmlspecialchars($row['PlateNumber']) ?></strong></td>
                                <td><?= htmlspecialchars($row['VehicleModel']) ?></td>
                                <td><?= htmlspecialchars($row['VehicleColour']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted">You have no registered vehicles yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <p>© 2025 FKPark System</p>
    </footer>
</body>
</html>

<?php
$stmtVehicles->close();
$conn->close();
?>