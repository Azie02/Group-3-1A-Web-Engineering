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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
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
            <center><h2>Welcome to FK Parking Management System</h2></center>
            <form action="SecurityStaffDashboard.php" method="get" class="searchbar">
                <input type="text" name="search" placeholder="Search..." value="<?php echo $search; ?>">
                <button type="submit">Search</button>
            </form>
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