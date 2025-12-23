<?php
 // Start the session
session_start();

// Database connection parameters
$conn = new mysqli("localhost", "root", "", "FKParkSystem", 3306);

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

    /* MERIT - FIXED: Removed TotalMeritPoint */
    SELECT 'StudentMerit' AS Type,
           MeritID AS ID,
           CONCAT('Merit: ', MeritPoint, ', Demerit: ', DemeritPoint) AS info
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

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Admin Dashboard</title>
        <meta name="desription" content="AdminDashboard">
        <meta name="author" content="Group1A3">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        
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
                height: calc(100vh - 120px);
                padding: 20px 0;
                box-sizing: border-box;
                transition: transform 0.3s ease;
                overflow-y: auto;
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
                gap: 15px;
            }

            .menu a {
                text-decoration: none;
                color: inherit;
                display: flex;
                align-items: center;
                gap: 15px;
                width: 100%;
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
               min-height: calc(100vh - 120px);
               width: calc(100% - 250px);
            }

            .content {
              background-color: white;
              padding: 25px;
              border-radius: 8px;
              margin-bottom: 25px;
              box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            }

            .searchbar { 
                display: flex; 
                gap: 10px; 
                margin-top: 20px; 
            }

            .searchbar input {
                padding:10px 20px;
                border: 1px solid #ccc;
                border-radius: 5px;
                font-size: 1em;
                flex: 1;
            }

            .searchbar button {
                background: #572096ff;
                color: white;
                border: none;
                border-radius: 5px;
                padding: 10px 18px;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .search-results {
                margin-top: 20px;
                background: #fff5e9;
                border-radius: 7px;
                padding: 18px 22px;
                box-shadow: 0 2px 9px rgba(255,170,60,0.08);
            }

            .seccontent {
                background-color: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            }

            /* Cards */
            .cards-container {
                display: flex;
                gap: 20px;
                margin-bottom: 30px;
            }

            .card {
                background: #3f1174ff;
                color: white;
                padding: 30px;
                text-align: center;
                border-radius: 8px;
                font-weight: bold;
                flex: 1;
                min-height: 120px;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            }

            /* Charts */
            .charts-container {
               display: flex;
               gap: 20px;
            }

            .chart {
              flex: 1;
              background: white;
              padding: 30px;
              border: 1px solid #ccc;
              min-height: 200px;
              text-align: center;
              border-radius: 5px;
              font-weight: bold;
              display: flex;
              justify-content: center;
              align-items: center;
            }

            footer {
               background-color: #b8a6ccff;
               color: white;
               padding: 15px 0;
               text-align: center;
               width: 100%;
               margin-top: auto;
               position: relative;
               z-index: 800;
            }
        </style>
    </head>
    <body>
        <header class="header">
            <div class="header-left">
                <div class="logo">
                <img src="UMPLogo.png" alt="UMPLogo">
                </div>
            </div>
            <div class="header-right">
                <span style="color:white; font-weight:500;">
                    Welcome, <?php echo htmlspecialchars($staff['StaffName']); ?>
                </span>
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
                    <a href="AdminDashboard.php" class="menutext active">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="ManageUser.php" class="menutext">
                        <i class="fas fa-users"></i> Manage User
                    </a>
                </li>
                <li>
                    <a href="ParkingManagement.php" class="menutext">
                        <i class="fas fa-parking"></i> Parking Management
                    </a>
                </li>
                <li>
                    <a href="Report.php" class="menutext">
                        <i class="fas fa-chart-bar"></i> Report
                    </a>
                </li>
            </ul>
        </nav>

        <div class="maincontent">
            <div class="content">
                <center><h2>Welcome to FK Parking Management System</h2></center>
        
                <form class="searchbar" method="GET" action="">
                    <input name="fsrch" id="fsrch" placeholder="Type Search...">
                    <button type="submit">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
                
                <?php if (!empty($_GET['fsrch'])): ?>
                <div class="search-results">
                    <h3>Search Results:</h3>

                    <?php if ($search_results->num_rows > 0): ?>
                        <ul>
                            <?php while ($row = $search_results->fetch_assoc()): ?>
                                <li>
                                    <strong><?php echo $row['Type']; ?>:</strong>
                                    ID: <?php echo $row['ID']; ?> —
                                    Info: <?php echo $row['info']; ?>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <p>No results found.</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div> 

            <div class="seccontent">
                <div class="cards-container">
                   <div class="card">Parking Areas</div>
                   <div class="card">Total Spaces</div>
                   <div class="card">Total Available</div>
                </div>

               <div class="charts-container">
                   <div class="chart">Traffic Summon Chart</div>
                   <div class="chart">Violation Chart</div>
               </div>
           </div>
        </div>
        
        <footer>
            <center><p> © 2025 FKPark System</p></center>
        </footer>
    </body>
</html>