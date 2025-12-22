<?php
 // Start the session
session_start();

// Database connection parameters
$conn = new mysqli("localhost", "root", "", "FKParkSystem", 3307);

// Check if database connection failed
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Restrict access to only logged-in staff
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'Administrator') {
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

// 60 seconds inactivity timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 60) {
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

// Fetch parking areas from database
$parkingQuery = "SELECT * FROM parkingarea";
$parkingResult = $conn->query($parkingQuery);

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Parking Management</title>
        <meta name="desription" content="ParkingManagement">
        <meta name="author" content="Group1A3">
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
                background-color: #DAB1DA; 
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
                background-color: #d890d8ff;
                width: 250px;
                color: black;
                position: fixed;
                top: 120px;
                left: 0;
                bottom: 0;
                padding: 20px 0;
                box-sizing: border-box;
                transition: transform 0.3s ease;
            }

            .sidebartitle{
                color: black;
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
                color: black;
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
                background-color: #6a22bdff;
            }
            
            .menutext.active {
                background-color: #6a22bdff;
                font-weight: 500;
            }

            .profile{
                background-color: #7405f1ff;
                color: white;
                border: 1px solid rgba(0, 0, 0, 0.3);
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
                background-color: #2e0c55ff;
            }

            .logoutbutton {
                background-color: rgba(255, 0, 0, 0.81);
                color: white;
                border: 1px solid rgba(0, 0, 0, 0.3);
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
                padding: 25px;
                border-radius: 8px;
                margin-bottom: 25px;
                box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            }

            .btn-add {
                background: #0066cc;
                padding: 8px 12px;
                color: white;
                text-decoration: none;
                border-radius: 4px;
            }

             table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            }
        
            th, td {
            border: 1px solid #ccc;
            padding: 12px;
            text-align: left;
            }
          
            th {
            background: #eee;
            }
        
            .action-links a {
            margin-right: 10px;
            text-decoration: none;
            color: #0066cc;
            } 

            footer {
               background-color: #b8a6ccff;
               color: white;
               padding: 15px 0;
            }
        </style>
    </head>
    <body>
        <header class="header">
            <div class="header_left">
                <div class="logo">
                <img src="UMPLogo.png" alt="UMPLogo">
                </div>
            </div>
            <div class="header-right">
                <a href="AdminProfile.php" class="profile">
                    <i class="fas fa-user-circle"></i> My Profile
                </a>
                <a href="logout.php" class="logoutbutton" onclick="return confirm('Are you sure you want to log out?');">
                   <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </header>
        
        <nav class="sidebar">
            <h1 class="sidebartitle">Admin Bar</h1>
            <ul class="menu">
                <li>
                    <a href="AdminDashboard.php" class="menutext">Dashboard</a>
                </li>
                <li>
                    <a href="ManageUser.php" class="menutext">Manage User</a>
                </li>
                <li>
                    <a href="ParkingManagement.php" class="menutext active">Parking Management</a>
                </li>
                <li>
                    <a href="Report.php" class="menutext">Report</a>
                </li>
            </ul>
        </nav>

        <div class="maincontent">
            <div class="content">
                <center><h2>Parking Area</h2></center>
                <a class="btn-add" href="AddParkingArea.php">Add Areas</a>
                <br><br>

                <table>
                <tr>
                    <th>Area Number</th>
                    <th>Type</th>
                    <th>Total Spaces</th>
                    <th>Action</th>
                </tr>

                <?php while ($row = $parkingResult->fetch_assoc()) { ?>
                <tr>
                    <td><?= htmlspecialchars($row['AreaNumber']) ?></td>
                    <td><?= htmlspecialchars($row['AreaType']) ?></td>
                    <td><?= htmlspecialchars($row['TotalSpaces']) ?></td>
                    <td class="action-links">
                    <a href="ParkingSpaces.php?id=<?= $row['ParkingAreaID'] ?>">View</a>
                    <a href="DeleteParkingArea.php?id=<?= $row['ParkingAreaID'] ?>"
                    onclick="return confirm('Are you sure you want to delete this parking area?');">
                    Delete
                    </a>
                    </td>
                </tr>
                <?php } ?>
            </table>
            </div>
        </div>
        <footer>
            <center><p> © 2025 FKPark System</p></center>
        </footer>
    </body>
</html>

