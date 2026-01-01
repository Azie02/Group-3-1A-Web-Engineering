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
if (!isset($_SESSION['user_id']) || ($_SESSION['type_user'] ?? '') !== 'Administrator') {
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

// ================== DASHBOARD STATISTICS ==================
// 1. Total Parking Areas
$area_query = "SELECT COUNT(*) as total_areas FROM ParkingArea";
$area_result = $conn->query($area_query);
$total_areas = $area_result->fetch_assoc()['total_areas'] ?? 0;

// 2. Total Parking Spaces
$space_query = "SELECT COUNT(*) as total_spaces FROM ParkingSpace";
$space_result = $conn->query($space_query);
$total_spaces = $space_result->fetch_assoc()['total_spaces'] ?? 0;

// 3. Total Available Spaces
$available_query = "SELECT COUNT(*) as available_spaces FROM ParkingStatus WHERE SpaceStatus = 'Available'";
$available_result = $conn->query($available_query);
$available_spaces = $available_result->fetch_assoc()['available_spaces'] ?? 0;

// 4. Total Users (Students + Staff)
$student_query = "SELECT COUNT(*) as total_students FROM student";
$student_result = $conn->query($student_query);
$total_students = $student_result->fetch_assoc()['total_students'] ?? 0;

// Assuming staff table doesn't have type_user column - count all staff
$staff_query = "SELECT COUNT(*) as total_staff FROM staff";
$staff_result = $conn->query($staff_query);
$total_staff = $staff_result->fetch_assoc()['total_staff'] ?? 0;
$total_users = $total_students + $total_staff;

// 5. Total Bookings (today)
$today = date('Y-m-d');
$booking_query = "SELECT COUNT(*) as today_bookings FROM Booking WHERE DATE(BookingDate) = ?";
$booking_stmt = $conn->prepare($booking_query);
$booking_stmt->bind_param("s", $today);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result();
$today_bookings = $booking_result->fetch_assoc()['today_bookings'] ?? 0;

// 6. Total Traffic Summons (this month)
$summon_query = "SELECT COUNT(*) as total_summons FROM TrafficSummon WHERE MONTH(SummonDate) = MONTH(CURDATE())";
$summon_result = $conn->query($summon_query);
$month_summons = $summon_result->fetch_assoc()['total_summons'] ?? 0;

// ================== CHART DATA ==================
// 1. Parking Space Status Distribution
$space_status_query = "
    SELECT SpaceStatus, COUNT(*) as count 
    FROM ParkingStatus 
    GROUP BY SpaceStatus
    ORDER BY 
        CASE SpaceStatus 
            WHEN 'Available' THEN 1
            WHEN 'Occupied' THEN 2
            WHEN 'Maintenance' THEN 3
            ELSE 4
        END
";
$space_status_result = $conn->query($space_status_query);
$space_status_labels = [];
$space_status_counts = [];
while($row = $space_status_result->fetch_assoc()) {
    $space_status_labels[] = $row['SpaceStatus'];
    $space_status_counts[] = $row['count'];
}

// 2. Parking Areas by Type
$area_type_query = "SELECT AreaType, COUNT(*) as count FROM ParkingArea GROUP BY AreaType";
$area_type_result = $conn->query($area_type_query);
$area_type_labels = [];
$area_type_counts = [];
while($row = $area_type_result->fetch_assoc()) {
    $area_type_labels[] = $row['AreaType'];
    $area_type_counts[] = $row['count'];
}

// 3. Traffic Summons (Last 6 months) - Simplified version
$summon_month_query = "
    SELECT 
        MONTHNAME(SummonDate) as month,
        COUNT(*) as count
    FROM TrafficSummon 
    WHERE SummonDate >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY MONTH(SummonDate), YEAR(SummonDate)
    ORDER BY YEAR(SummonDate), MONTH(SummonDate)
    LIMIT 6
";
$summon_month_result = $conn->query($summon_month_query);
$summon_month_labels = [];
$summon_month_counts = [];
while($row = $summon_month_result->fetch_assoc()) {
    $summon_month_labels[] = $row['month'];
    $summon_month_counts[] = $row['count'];
}

// If no data, show placeholder
if(empty($summon_month_labels)) {
    $months = ['January', 'February', 'March', 'April', 'May', 'June'];
    $current_month = date('n') - 1; // 0-based index
    $summon_month_labels = [];
    $summon_month_counts = [];
    
    for($i = 5; $i >= 0; $i--) {
        $month_index = ($current_month - $i + 12) % 12;
        $summon_month_labels[] = $months[$month_index];
        $summon_month_counts[] = 0;
    }
}

// 4. Violation Types Distribution (simplified)
$violation_query = "
    SELECT ViolationName, COUNT(*) as count 
    FROM Violation 
    GROUP BY ViolationName 
    ORDER BY count DESC 
    LIMIT 5
";
$violation_result = $conn->query($violation_query);
$violation_labels = [];
$violation_counts = [];
while($row = $violation_result->fetch_assoc()) {
    $violation_labels[] = $row['ViolationName'];
    $violation_counts[] = $row['count'];
}

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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FK Park System - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            background-color: #d373d3ff; 
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            position: fixed;
            width: 100%;
            height: 120px;
            box-sizing: border-box;
            z-index: 1000;
            top: 0;
            left: 0;
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
        .togglebutton {
            background-color: #daa5dad7;
            color: white;
            border: 1px solid #d890d89c;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .togglebutton:hover {
            background-color: #864281ff;
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
            transition: all 0.3s ease;
            z-index: 999;
            overflow-y: auto;
        }
        .sidebar.collapsed {
            transform: translateX(-250px);
            opacity: 0;
            width: 0;
            padding: 0;
        }
        .sidebartitle{
            color: black;
            font-size: 1rem;
            margin-bottom: 20px;
            padding: 0 20px;
        }
        /* Main Content Adjustment */
        .main-container.sidebar-collapsed {
            margin-left: 0;
            transition: margin-left 0.3s ease;
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
            background-color: #a03198d5;
        }   
        .menutext.active {
            background-color: #a03198d5;
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
        .main-container {
            margin-left: 250px;
            margin-top: 120px;
            padding: 40px;
            box-sizing: border-box;
            flex: 1;
            transition: margin-left 0.3s ease;
            width: calc(100% - 250px);
        }
        .main-container.sidebar-collapsed {
            margin-left: 0;
            width: 100%;
        }
        .content {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }
        /* Cards */
        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .card {
            background: linear-gradient(135deg, #6a22bdff, #3f1174ff);
            color: white;
            padding: 25px;
            text-align: center;
            border-radius: 10px;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: none;
            position: relative;
            overflow: hidden;
        }
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: rgba(255,255,255,0.3);
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .card h3 {
            margin: 0;
            font-size: 2.8rem;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
            line-height: 1;
        }
        .card p {
            margin: 10px 0 0 0;
            font-size: 1rem;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
        }
        .card i {
            font-size: 1.1rem;
        }
        /* Charts */
        .charts-container {
           display: grid;
           grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
           gap: 25px;
           margin-top: 30px;
        }
        .chart-container {
          background: white;
          padding: 20px;
          border-radius: 10px;
          box-shadow: 0 3px 10px rgba(0,0,0,0.08);
          border: 1px solid #e8e8e8;
          transition: transform 0.3s ease;
        }
        .chart-container:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .chart-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
            text-align: center;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        .chart-wrapper {
            position: relative;
            height: 300px;
            width: 100%;
        }
        /* Search Bar */
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
        footer {
            background-color: #b8a6ccff;
            color: white;
            padding: 15px 0;
            text-align: center;
            width: 100%;
            margin-top: auto;
            position: relative;
            bottom: 0;
            left: 0;
            transition: margin-left 0.3s ease;
        }
        footer.sidebar-collapsed {
            margin-left: 0;
        }
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }
        .stat-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #6a22bdff;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 5px;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
        }
        @media (max-width: 1200px) {
            .cards-container {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        @media (max-width: 992px) {
            .cards-container {
                grid-template-columns: repeat(2, 1fr);
            }
            .charts-container {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 768px) {
            .cards-container {
                grid-template-columns: 1fr;
            }
            .chart-container {
                padding: 15px;
            }
            .chart-wrapper {
                height: 250px;
            }
            .main-container {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }
            .card h3 {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-left">
            <button class="togglebutton" id="sidebarToggle">
                <i class="fas fa-bars"></i>Menu
            </button>
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
    
    <nav class="sidebar" id="sidebar">
        <h1 class="sidebartitle"><strong>Admin</strong></h1>
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

    <div class="container main-container" id="mainContainer">
        <h1 class="mb-4"><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
        
        <!-- Welcome Message -->
        <div class="content">
            <h2 class="text-center mb-3">Welcome to FK Parking Management System</h2>
            <p class="text-center text-muted mb-4">
                Hello, <strong><?php echo htmlspecialchars($staff['staffName'] ?? 'Admin'); ?></strong>! Here's your dashboard overview.
            </p>
    
            <!-- Statistics Cards -->
            <div class="cards-container">
                <div class="card">
                    <h3><?php echo $total_areas; ?></h3>
                    <p><i class="fas fa-map-marked-alt"></i> Parking Areas</p>
                </div>
                <div class="card">
                    <h3><?php echo $total_spaces; ?></h3>
                    <p><i class="fas fa-parking"></i> Total Spaces</p>
                </div>
                <div class="card">
                    <h3><?php echo $available_spaces; ?></h3>
                    <p><i class="fas fa-car"></i> Available Now</p>
                </div>
                <div class="card">
                    <h3><?php echo $total_users; ?></h3>
                    <p><i class="fas fa-users"></i> Total Users</p>
                </div>
                <div class="card">
                    <h3><?php echo $today_bookings; ?></h3>
                    <p><i class="fas fa-calendar-check"></i> Today's Bookings</p>
                </div>
                <div class="card">
                    <h3><?php echo $month_summons; ?></h3>
                    <p><i class="fas fa-exclamation-triangle"></i> Monthly Summons</p>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-label">Students</div>
                    <div class="stat-value"><?php echo $total_students; ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Staff</div>
                    <div class="stat-value"><?php echo $total_staff; ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Occupied Spaces</div>
                    <div class="stat-value"><?php echo $total_spaces - $available_spaces; ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Utilization Rate</div>
                    <div class="stat-value">
                        <?php 
                            $utilization = $total_spaces > 0 ? round((($total_spaces - $available_spaces) / $total_spaces) * 100) : 0;
                            echo $utilization . '%';
                        ?>
                    </div>
                </div>
            </div>

            <!-- Search Form -->
            <form class="searchbar" method="GET" action="">
                <input name="fsrch" id="fsrch" placeholder="Type Search... (Vehicle, Booking, Summon, etc.)">
                <button type="submit">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>
            
            <?php if (!empty($_GET['fsrch'])): ?>
            <div class="search-results">
                <h3>Search Results:</h3>
                <?php if ($search_results->num_rows > 0): ?>
                    <ul class="list-group">
                        <?php while ($row = $search_results->fetch_assoc()): ?>
                            <li class="list-group-item">
                                <strong><?php echo $row['Type']; ?>:</strong>
                                ID: <?php echo $row['ID']; ?> —
                                Info: <?php echo $row['info']; ?>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">No results found.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div> 

        <!-- Charts Section -->
        <div class="content">
            <h3 class="mb-4"><i class="fas fa-chart-line"></i> System Analytics</h3>
            
            <div class="charts-container">
                <!-- Chart 1: Parking Space Status -->
                <div class="chart-container">
                    <div class="chart-title">Parking Space Status Distribution</div>
                    <div class="chart-wrapper">
                        <canvas id="spaceStatusChart"></canvas>
                    </div>
                </div>

                <!-- Chart 2: Traffic Summons by Month -->
                <div class="chart-container">
                    <div class="chart-title">Traffic Summons (Last 6 Months)</div>
                    <div class="chart-wrapper">
                        <canvas id="summonChart"></canvas>
                    </div>
                </div>

                <!-- Chart 3: Parking Areas by Type -->
                <div class="chart-container">
                    <div class="chart-title">Parking Areas by Type</div>
                    <div class="chart-wrapper">
                        <canvas id="areaTypeChart"></canvas>
                    </div>
                </div>

                <!-- Chart 4: Top Violations -->
                <div class="chart-container">
                    <div class="chart-title">Top 5 Violation Types</div>
                    <div class="chart-wrapper">
                        <canvas id="violationChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <footer id="footer">
        <div class="container">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> FK Park System. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar Toggle Functionality (same as ParkingArea.php)
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const mainContainer = document.getElementById('mainContainer');
            const footer = document.getElementById('footer');
            
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function() {
                    // Toggle collapsed class
                    sidebar.classList.toggle('collapsed');
                    mainContainer.classList.toggle('sidebar-collapsed');
                    footer.classList.toggle('sidebar-collapsed');
                    
                    // Save state to localStorage
                    const isCollapsed = sidebar.classList.contains('collapsed');
                    localStorage.setItem('sidebarCollapsed', isCollapsed);
                });
                
                // Load saved state
                const savedState = localStorage.getItem('sidebarCollapsed');
                if (savedState === 'true') {
                    sidebar.classList.add('collapsed');
                    mainContainer.classList.add('sidebar-collapsed');
                    footer.classList.add('sidebar-collapsed');
                }
            }
            
            // Initialize Charts
            initializeCharts();
        });
        
        function initializeCharts() {
            // Chart 1: Parking Space Status (Doughnut Chart)
            const spaceStatusCtx = document.getElementById('spaceStatusChart');
            if (spaceStatusCtx) {
                new Chart(spaceStatusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode($space_status_labels); ?>,
                        datasets: [{
                            data: <?php echo json_encode($space_status_counts); ?>,
                            backgroundColor: ['#28a745', '#dc3545', '#ffc107'],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    font: {
                                        size: 12
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Chart 2: Traffic Summons (Line Chart)
            const summonCtx = document.getElementById('summonChart');
            if (summonCtx) {
                new Chart(summonCtx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($summon_month_labels); ?>,
                        datasets: [{
                            label: 'Traffic Summons',
                            data: <?php echo json_encode($summon_month_counts); ?>,
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#dc3545',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 7
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0,0,0,0.05)'
                                }
                            },
                            x: {
                                grid: {
                                    color: 'rgba(0,0,0,0.05)'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                labels: {
                                    font: {
                                        size: 13
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Chart 3: Parking Areas by Type (Bar Chart)
            const areaTypeCtx = document.getElementById('areaTypeChart');
            if (areaTypeCtx) {
                new Chart(areaTypeCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($area_type_labels); ?>,
                        datasets: [{
                            label: 'Number of Areas',
                            data: <?php echo json_encode($area_type_counts); ?>,
                            backgroundColor: '#6a22bd',
                            borderColor: '#4a1899',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                },
                                grid: {
                                    color: 'rgba(0,0,0,0.05)'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            }
                        }
                    }
                });
            }

            // Chart 4: Violation Types (Bar Chart)
            const violationCtx = document.getElementById('violationChart');
            if (violationCtx) {
                new Chart(violationCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($violation_labels); ?>,
                        datasets: [{
                            label: 'Number of Occurrences',
                            data: <?php echo json_encode($violation_counts); ?>,
                            backgroundColor: '#ffc107',
                            borderColor: '#e0a800',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0,0,0,0.05)'
                                }
                            },
                            y: {
                                grid: {
                                    display: false
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }
        }
    </script>
</body>
</html>
