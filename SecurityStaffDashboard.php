<?php
session_start();
$conn = new mysqli("localhost", "root", "", "fkparksystem", 3306);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'SecurityStaff') {
    header("Location: Login.php");
    exit();
}

// Get staff data from database
$staff_id = $_SESSION['user_id'];
$query = "SELECT * FROM staff WHERE staffID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    // Staff not found in database
    session_destroy();
    header("Location: Login.php");
    exit();
}
$staff = $result->fetch_assoc();
<<<<<<< HEAD
=======

// SEARCH FUNCTIONALITY
$search_results = [];

if (isset($_GET['fsrch']) && $_GET['fsrch'] !== "") {
    $search = "%" . htmlspecialchars($_GET['fsrch']) . "%";

$sql = "
    /* VEHICLE */
    SELECT 'Vehicle' AS Type,
           VehicleID AS ID,
           CONCAT('Plate: ', PlateNumber, ', Model: ', VehicleModel) AS info
    FROM Vehicle
    WHERE VehicleID LIKE ?
       OR PlateNumber LIKE ?
       OR VehicleModel LIKE ?
       OR VehicleType LIKE ?

    UNION

    /* BOOKING */
    SELECT 'Booking' AS Type,
           BookingID AS ID,
           CONCAT('Date: ', BookingDate, ', Status: ', BookingStatus) AS info
    FROM Booking
    WHERE BookingID LIKE ?
       OR BookingStatus LIKE ?
       OR BookingDate LIKE ?

    UNION

    /* MERIT */
    SELECT 'StudentMerit' AS Type,
           MeritID AS ID,
           CONCAT('Merit: ', MeritPoint, ', Demerit: ', DemeritPoint,
                  ', Total: ', TotalMeritPoint) AS info
    FROM StudentMerit
    WHERE MeritID LIKE ?
       OR MeritPoint LIKE ?
       OR DemeritPoint LIKE ?

    UNION

    /* TRAFFIC SUMMON */
    SELECT 'TrafficSummon' AS Type,
           SummonID AS ID,
           CONCAT('Violation: ', ViolationID, ', Date: ', SummonDate) AS info
    FROM TrafficSummon
    WHERE SummonID LIKE ?
       OR ViolationID LIKE ?
       OR SummonDescription LIKE ?

    UNION

    /* VIOLATION */
    SELECT 'Violation' AS Type,
           ViolationID AS ID,
           CONCAT('Name: ', ViolationName, ', Type: ', ViolationType) AS info
    FROM Violation
    WHERE ViolationID LIKE ?
       OR ViolationName LIKE ?
       OR ViolationType LIKE ?

    UNION

    /* PARKING SPACE */
    SELECT 'ParkingSpace' AS Type,
           ParkingSpaceID AS ID,
           CONCAT('Space: ', SpaceNumber, ', Type: ', SpaceType) AS info
    FROM ParkingSpace
    WHERE ParkingSpaceID LIKE ?
       OR SpaceNumber LIKE ?
       OR SpaceType LIKE ?

    UNION

    /* PARKING AREA */
    SELECT 'ParkingArea' AS Type,
           ParkingAreaID AS ID,
           CONCAT('Area: ', AreaType, ', No: ', AreaNumber) AS info
    FROM ParkingArea
    WHERE ParkingAreaID LIKE ?
       OR AreaType LIKE ?
       OR AreaNumber LIKE ?
";


    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
    "ssssssssssssssssssssss",

    // Vehicle (4 fields)
    $search, $search, $search, $search,

    // Booking (3 fields)
    $search, $search, $search,

    // Merit (3 fields)
    $search, $search, $search,

    // Traffic Summon (3 fields)
    $search, $search, $search,

    // Violation (3 fields)
    $search, $search, $search,

    // ParkingSpace (3 fields)
    $search, $search, $search,

    // ParkingArea (3 fields)
    $search, $search, $search
);

    $stmt->execute();
    $search_results = $stmt->get_result();
}

// 20 seconds inactivity timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 20) {
    session_unset();
    session_destroy();
    header("Location: Login.php");
    exit();
}

// Update activity time on every request
$_SESSION['last_activity'] = time();

// Existing security check (keep this if you already have it)
if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}

>>>>>>> module_2
?>

<!DOCTYPE html>
<html>
<head>
    <title>Security Staff Dashboard</title>
    <meta name="desription" content="SecurityStaffDashboard">
    <meta name="author" content="Group1A3">
    <link rel="stylesheet" href="SecurityDashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <header class="header">
        <div class="header_left">
            <div class="logo">
                <img src="UMPLogo.png" alt="UMPLogo">
            </div>
        </div>
        <div class="header-right">
            <a href="SecurityStaffProfile.php" class="profile">
                <i class="fas fa-user-circle"></i> My Profile
            </a>
            <a href="logout.php" class="logoutbutton">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <nav class="sidebar">
        <h1 class="sidebartitle">Security Staff Bar</h1>
        <ul class="menu">
            <li>
                <a href="SecurityStaffDashboard.php" class="menutext active">Dashboard</a>
            </li>
            <li>
                <a href="VehicleApproval.php" class="menutext">Vehicle Approval</a>
            </li>
            <li>
                <a href="RecordSummon.php" class="menutext">Record Summon</a>
            </li>
            <li>
                <a href="ManageSummon.php" class="menutext">Manage Summon</a>
            </li>
        </ul>
    </nav>

    <div class="maincontent">
        <div class="content">
            <center>
                <h2>Welcome to FK Parking Management System</h2>
            </center>
            </div>

        <div class="seccontent">
            <div class="cards">
                <div class="card">Parking Areas</div>
                <div class="card">Total Spaces</div>
                <div class="card">Total Available</div>
            </div>

            <div class="charts">
                <div class="chart">Traffic Summon Chart</div>
                <div class="chart">Violation Chart</div>
            </div>
        </div>
    </div>
    <footer>
        <center>
            <p> © 2025 FKPark System</p>
        </center>
    </footer>

    <script src="SecurityDashboard.js"></script>
</body>

</html>