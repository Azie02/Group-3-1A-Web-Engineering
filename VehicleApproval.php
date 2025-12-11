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

?>

<!DOCTYPE html>
<html>
<head>
    <title>Vehicle Approval - Security Staff</title>
    <meta name="description" content="Vehicle Approval Page">
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
            <a href="logout.php" class="logoutbutton" onclick="return confirm('Are you sure you want to log out?');">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>
    
    <nav class="sidebar">
        <h1 class="sidebartitle">Security Staff Bar</h1>
        <ul class="menu">
            <li>
                <a href="SecurityStaffDashboard.php" class="menutext">Dashboard</a>
            </li>
            <li>
                <a href="VehicleApproval.php" class="menutext active">Vehicle Approval</a>
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
        
        <div class="header" style="position: static; margin-bottom: 20px; height: auto;">
            <h1>Vehicle Approval Request</h1>

        </div>
    </div>

    <footer>
        <center><p> © 2025 FKPark System</p></center>
    </footer>

    <script src="SecurityDashboard.js"></script>
</body>
</html>