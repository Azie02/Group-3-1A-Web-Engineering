<?php
session_start();

// Single place to define login redirect header
define('LOGIN_REDIRECT', 'Location: Login.php');

// Database connection
$conn = new mysqli("localhost", "root", "", "fkparksystem", 3307);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Restrict access to logged-in student only
if (!isset($_SESSION['user_id']) || ($_SESSION['type_user'] ?? '') !== 'student') {
    header(LOGIN_REDIRECT);
    exit();
}

$student_id = $_SESSION['user_id'];
$feedback = null;
$selectedSpace = null;
$bookingStep = 'search';
$date = $time = $area = '';

// Get student data for header
$student_query = "SELECT * FROM student WHERE studentID = ?";
$student_stmt = $conn->prepare($student_query);
$student_stmt->bind_param("s", $student_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();

if ($student_result->num_rows !== 1) {
    header(LOGIN_REDIRECT);
    exit();
}
$student = $student_result->fetch_assoc();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['search_spaces'])) {
        // Step 1: Search for available spaces
        $date = $_POST['date'] ?? '';
        $time = $_POST['time'] ?? '';
        $area = $_POST['area'] ?? '';
        
        if ($date && $time) {
            $bookingStep = 'search';
        }
    }
    elseif (isset($_POST['select_space'])) {
        // Step 2: User selected a space
        $spaceID = $_POST['space_id'] ?? '';
        $date = $_POST['date'] ?? '';
        $time = $_POST['time'] ?? '';
        
        if ($spaceID && $date && $time) {
            // Get space details
            $sql = "SELECT ps.ParkingSpaceID, ps.SpaceNumber, ps.SpaceType, 
                           pa.AreaType, pa.AreaNumber, pa.ParkingAreaID
                    FROM ParkingSpace ps
                    JOIN ParkingArea pa ON ps.ParkingAreaID = pa.ParkingAreaID
                    WHERE ps.ParkingSpaceID = ?
                    LIMIT 1";
            $stmtSpace = $conn->prepare($sql);
            $stmtSpace->bind_param("s", $spaceID);
            $stmtSpace->execute();
            $spaceResult = $stmtSpace->get_result();
            
            if ($spaceResult->num_rows === 1) {
                $selectedSpace = $spaceResult->fetch_assoc();
                $bookingStep = 'confirm';
            }
            $stmtSpace->close();
        }
    }
    elseif (isset($_POST['confirm_booking'])) {
        // Step 3: Confirm booking
        $spaceID = $_POST['space_id'] ?? '';
        $date = $_POST['date'] ?? '';
        $time = $_POST['time'] ?? '';
        
        if ($spaceID && $date && $time) {
            $bookingID = 'B' . time() . rand(100,999);
            
            $insertSql = "INSERT INTO Booking (BookingID, StudentID, ParkingSpaceID, BookingDate, BookingTime, BookingStatus)
                          SELECT ?, ?, ?, ?, ?, 'Confirmed'
                          FROM DUAL
                          WHERE NOT EXISTS (
                            SELECT 1 FROM Booking b
                            WHERE b.ParkingSpaceID = ?
                              AND DATE(b.BookingDate) = ?
                              AND TIME(b.BookingTime) = ?
                              AND b.BookingStatus IN ('Pending','Confirmed')
                          )
                          LIMIT 1";
            $stmtBook = $conn->prepare($insertSql);
            $stmtBook->bind_param("ssssssss", $bookingID, $student_id, $spaceID, $date, $time, $spaceID, $date, $time);
            
            if ($stmtBook->execute()) {
                if ($stmtBook->affected_rows > 0) {
                    $feedback = ['type'=>'success','msg'=>"Booking confirmed successfully! (ID: $bookingID)"];
                    $bookingStep = 'success';
                } else {
                    $feedback = ['type'=>'warning','msg'=>"Space is no longer available for the selected date/time."];
                    $bookingStep = 'search';
                }
            } else {
                $feedback = ['type'=>'danger','msg'=>'Booking failed: ' . $stmtBook->error];
                $bookingStep = 'search';
            }
            $stmtBook->close();
        }
    }
}

// Handle GET parameters for direct linking
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['space']) && isset($_GET['date']) && isset($_GET['time'])) {
        $spaceID = $_GET['space'] ?? '';
        $date = $_GET['date'] ?? '';
        $time = $_GET['time'] ?? '';
        
        // Get space details
        $sql = "SELECT ps.ParkingSpaceID, ps.SpaceNumber, ps.SpaceType, 
                       pa.AreaType, pa.AreaNumber, pa.ParkingAreaID
                FROM ParkingSpace ps
                JOIN ParkingArea pa ON ps.ParkingAreaID = pa.ParkingAreaID
                WHERE ps.ParkingSpaceID = ?
                LIMIT 1";
        $stmtSpace = $conn->prepare($sql);
        $stmtSpace->bind_param("s", $spaceID);
        $stmtSpace->execute();
        $spaceResult = $stmtSpace->get_result();
        
        if ($spaceResult->num_rows === 1) {
            $selectedSpace = $spaceResult->fetch_assoc();
            $bookingStep = 'confirm';
        }
        $stmtSpace->close();
    }
}

// 20 seconds inactivity timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 60) {
    session_unset();
    session_destroy();
    header(LOGIN_REDIRECT);
    exit();
}

// Update activity time on every request
$_SESSION['last_activity'] = time();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Book Parking</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* KEEP ALL ORIGINAL BOOKING STYLES */
        body {
           background-color: #f5f5f5;
           font-family: 'Roboto', sans-serif;
           margin: 0;
           padding: 0;
           display: flex;
           flex-direction: column;
           min-height: 100vh;
        }

        h2 {
            color: #2d3748;
            font-size: 26px;
            margin-bottom: 25px;
            font-weight: 600;
            text-align: center;
            padding-bottom: 15px;
            border-bottom: 3px solid #008080;
        }

        .booking-steps {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }

        .step {
            display: flex;
            align-items: center;
            margin: 0 20px;
        }

        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e0e0e0;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }

        .step.active .step-number {
            background-color: #008080;
            color: white;
        }

        .step-text {
            color: #666;
            font-weight: 500;
        }

        .step.active .step-text {
            color: #008080;
            font-weight: 600;
        }

        .search-form {
            background: linear-gradient(135deg, #e0f2f1 0%, #b2dfdb 100%);
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            flex: 1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #008080;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            font-size: 14px;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            font-size: 14px;
            font-family: inherit;
        }

        .btn-primary {
            background-color: #008080;
            color: white;
        }

        .btn-primary:hover {
            background-color: #006666;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
            margin-left: 10px;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 13px;
        }

        .spaces-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .space-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s;
        }

        .space-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            border-color: #008080;
        }

        .space-card h4 {
            color: #008080;
            margin-bottom: 10px;
        }

        .space-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .space-label {
            color: #718096;
        }

        .space-value {
            color: #2d3748;
            font-weight: 500;
        }

        .detail-group {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .detail-label {
            color: #008080;
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            color: #2d3748;
            font-size: 16px;
            font-weight: 600;
        }

        .highlight-box {
            background: linear-gradient(135deg, #e0f2f1 0%, #b2dfdb 100%);
            border-left: 4px solid #008080;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
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

        .alert-warning {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }

        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .action-buttons {
            text-align: center;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 2px solid #e9ecef;
        }

        .info-icon {
            font-size: 60px;
            color: #008080;
            text-align: center;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .content {
                padding: 20px;
            }
            
            .detail-group {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 15px;
            }
            
            .booking-steps {
                flex-direction: column;
                align-items: center;
                gap: 15px;
            }
        }

        /* ADD ONLY THE HEADER AND SIDEBAR STYLES FROM STUDENTDASHBOARD */
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
            top: 0;
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

        /* Toggle Button - Make it ALWAYS visible (same as StudentDashboard.php) */
        .toggle-btn {
            display: flex;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 12px 15px;
            border-radius: 5px;
            transition: all 0.3s ease;
            z-index: 1001;
            align-items: center;
            justify-content: center;
        }

        .toggle-btn:hover {
            background: rgba(255, 255, 255, 0.3);
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
            z-index: 999;
        }

        /* Mobile sidebar style (same as StudentDashboard.php) */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .toggle-btn {
                display: block;
            }
            
            .maincontent {
                margin-left: 0;
            }
            
            .maincontent.shifted {
                margin-left: 250px;
            }
        }

        /* Adjust main content margin for collapsed sidebar (same as StudentDashboard.php) */
        .maincontent {
            margin-left: 250px; /* Default with sidebar open */
            margin-top: 120px;
            padding: 40px;
            box-sizing: border-box;
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - 120px);
        }

        /* Add this class for when sidebar is collapsed (same as StudentDashboard.php) */
        .maincontent.collapsed {
            margin-left: 0; /* When sidebar is collapsed */
        }

        /* Adjust sidebar for collapsed state (same as StudentDashboard.php) */
        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .sidebartitle{
            color: white;
            font-size: 1rem;
            margin-bottom: 20px;
            padding: 0 20px;
            text-align: center;
        }

        .menu{
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 0 20px;
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
            text-decoration: none;
            border: none;
            font-size: 16px;
            width: 100%;
            box-sizing: border-box;
        }

        .menu-icon {
            width: 20px;
            text-align: center;
        }

        .menutext:hover {
            background-color: #044747;
            transform: translateX(5px);
        }

        .menutext.active {
            background-color: #016161;
            font-weight: 500;
            border-left: 4px solid #00FFFF;
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
            background-color: rgba(46, 204, 113, 0.3);
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

        .logoutbutton:hover {
            background-color: rgba(255, 0, 0, 0.3);
        }

        /* KEEP ORIGINAL CONTENT STYLES */
        .content {
          background-color: white;
          padding: 35px;
          border-radius: 8px;
          box-shadow: 0 2px 15px rgba(0,0,0,0.05);
          max-width: 1200px;
          margin: 0 auto;
        }

        footer {
           background-color: #80cab1;
           color: white;
           padding: 15px 0;
           text-align: center;
           margin-top: 40px;
           width: 100%;
        }

        /* Additional mobile responsive adjustments */
        @media (max-width: 768px) {
            .header {
                height: 100px;
                padding: 0 10px;
            }
            
            .sidebar {
                top: 100px;
            }
            
            .maincontent {
                margin-top: 100px;
                padding: 20px;
            }
            
            .logo img {
                height: 70px;
            }
            
            .header-right {
                padding-right: 10px;
            }
            
            .profile, .logoutbutton {
                padding: 6px 10px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
<header class="header">
    <div class="header-left">
        <!-- This toggle button will now be visible on ALL screens (same as StudentDashboard.php) -->
        <button class="toggle-btn" onclick="toggleSidebar()" aria-label="Toggle navigation">
            <i class="fas fa-bars"></i>
        </button>
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
        <a href="Logout.php" class="logoutbutton" onclick="return confirm('Are you sure you want to log out?');">
           <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</header>

<nav class="sidebar">
    <h1 class="sidebartitle">Student Bar</h1>
    <ul class="menu">
        <li>
            <a href="StudentDashboard.php" class="menutext">
                <span class="menu-icon"><i class="fas fa-tachometer-alt"></i></span>
                Dashboard
            </a>
        </li>
        <li>
            <a href="VehicleRegistration.php" class="menutext">
                <span class="menu-icon"><i class="fas fa-car"></i></span>
                Vehicle Registration
            </a>
        </li>
        <li>
            <a href="Booking.php" class="menutext active">
                <span class="menu-icon"><i class="fas fa-calendar-check"></i></span>
                Book Parking
            </a>
        </li>
        <li>
            <a href="MeritStatus.php" class="menutext">
                <span class="menu-icon"><i class="fas fa-star"></i></span>
                Merit status
            </a>
        </li>
    </ul>
</nav>

<div class="maincontent">
    <!-- KEEP ALL ORIGINAL BOOKING CONTENT -->
    <div class="content">
        <div class="info-icon">🅿️</div>
        <h2>Book Parking Space</h2>

        <?php if (!empty($feedback)): ?>
            <div class="alert alert-<?php echo htmlspecialchars($feedback['type']); ?>">
                <?php echo htmlspecialchars($feedback['msg']); ?>
            </div>
        <?php endif; ?>

        <!-- Booking Steps Indicator -->
        <div class="booking-steps">
            <div class="step <?php echo $bookingStep === 'search' ? 'active' : ''; ?>">
                <div class="step-number">1</div>
                <div class="step-text">Search Spaces</div>
            </div>
            <div class="step <?php echo $bookingStep === 'confirm' ? 'active' : ''; ?>">
                <div class="step-number">2</div>
                <div class="step-text">Confirm Booking</div>
            </div>
            <div class="step <?php echo $bookingStep === 'success' ? 'active' : ''; ?>">
                <div class="step-number">3</div>
                <div class="step-text">Complete</div>
            </div>
        </div>

        <?php if ($bookingStep === 'search' || $bookingStep === 'search'): ?>
            <!-- Step 1: Search Form -->
            <div class="search-form">
                <h3 style="color: #008080; margin-top: 0;">Search Available Spaces</h3>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date">📅 Date</label>
                            <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($date); ?>" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="time">⏰ Time</label>
                            <input type="time" id="time" name="time" value="<?php echo htmlspecialchars($time); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="area">📍 Area Type</label>
                                <select id="area" name="area">
                                    <option value="">All Areas</option>
                                    <?php
                                    $areaResult = $conn->query("SELECT DISTINCT AreaType FROM ParkingArea ORDER BY AreaType");
                                    while ($areaRow = $areaResult->fetch_assoc()):
                                    ?>
                                        <option value="<?php echo htmlspecialchars($areaRow['AreaType']); ?>" <?php echo $area === $areaRow['AreaType'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($areaRow['AreaType']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                        </div>
                    </div>
                    <button type="submit" name="search_spaces" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search Available Spaces
                    </button>
                </form>
            </div>

            <!-- Available Spaces Grid -->
            <?php if ($date && $time): ?>
                <h3>Available Parking Spaces</h3>
                <div class="spaces-grid">
                    <?php
                    // Query available spaces
                    $availableSql = "SELECT ps.ParkingSpaceID, ps.SpaceNumber, ps.SpaceType, 
                                            pa.AreaType, pa.AreaNumber, pa.ParkingAreaID
                                     FROM ParkingSpace ps
                                     JOIN ParkingArea pa ON ps.ParkingAreaID = pa.ParkingAreaID
                                     WHERE ps.ParkingSpaceID NOT IN (
                                         SELECT b.ParkingSpaceID 
                                         FROM Booking b
                                         WHERE DATE(b.BookingDate) = ?
                                           AND TIME(b.BookingTime) = ?
                                           AND b.BookingStatus IN ('Pending','Confirmed')
                                     )";
                    
                    if ($area) {
                        $availableSql .= " AND pa.AreaType = ?";
                    }
                    
                    $availableSql .= " ORDER BY pa.AreaType, ps.SpaceNumber";
                    
                    $stmtAvailable = $conn->prepare($availableSql);
                    
                    if ($area) {
                        $stmtAvailable->bind_param("sss", $date, $time, $area);
                    } else {
                        $stmtAvailable->bind_param("ss", $date, $time);
                    }
                    
                    $stmtAvailable->execute();
                    $availableResult = $stmtAvailable->get_result();
                    
                    if ($availableResult->num_rows > 0):
                        while ($spaceRow = $availableResult->fetch_assoc()):
                    ?>
                        <div class="space-card">
                            <h4><?php echo htmlspecialchars($spaceRow['SpaceNumber']); ?></h4>
                            <div class="space-detail">
                                <span class="space-label">Area:</span>
                                <span class="space-value"><?php echo htmlspecialchars($spaceRow['AreaType'] . ' ' . $spaceRow['AreaNumber']); ?></span>
                            </div>
                            <div class="space-detail">
                                <span class="space-label">Type:</span>
                                <span class="space-value"><?php echo htmlspecialchars($spaceRow['SpaceType']); ?></span>
                            </div>
                            <form method="POST" style="margin-top: 15px;">
                                <input type="hidden" name="space_id" value="<?php echo htmlspecialchars($spaceRow['ParkingSpaceID']); ?>">
                                <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
                                <input type="hidden" name="time" value="<?php echo htmlspecialchars($time); ?>">
                                <button type="submit" name="select_space" class="btn btn-primary btn-small">
                                    <i class="fas fa-check"></i> Select This Space
                                </button>
                            </form>
                        </div>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                            <p style="color: #718096; font-size: 16px;">No available spaces found for the selected date and time.</p>
                            <p style="color: #a0aec0; font-size: 14px;">Please try a different date, time, or area.</p>
                        </div>
                    <?php
                    endif;
                    $stmtAvailable->close();
                    ?>
                </div>
            <?php endif; ?>

        <?php elseif ($bookingStep === 'confirm' && $selectedSpace): ?>
            <!-- Step 2: Confirm Booking -->
            <div class="highlight-box">
                <h3>📍 Confirm Your Booking</h3>
                <p>Please review the details below before confirming your booking.</p>
            </div>

            <div class="detail-group">
                <span class="detail-label">Parking Area</span>
                <span class="detail-value"><?php echo htmlspecialchars($selectedSpace['AreaType'] . ' - ' . $selectedSpace['AreaNumber']); ?></span>
            </div>

            <div class="detail-group">
                <span class="detail-label">Space Number</span>
                <span class="detail-value"><?php echo htmlspecialchars($selectedSpace['SpaceNumber']); ?></span>
            </div>

            <div class="detail-group">
                <span class="detail-label">Space Type</span>
                <span class="detail-value"><?php echo htmlspecialchars($selectedSpace['SpaceType']); ?></span>
            </div>

            <div class="detail-group">
                <span class="detail-label">📅 Date</span>
                <span class="detail-value"><?php echo htmlspecialchars(date('l, d F Y', strtotime($date))); ?></span>
            </div>

            <div class="detail-group">
                <span class="detail-label">⏰ Time</span>
                <span class="detail-value"><?php echo htmlspecialchars(date('h:i A', strtotime($time))); ?></span>
            </div>

            <div class="detail-group">
                <span class="detail-label">👤 Student</span>
                <span class="detail-value"><?php echo htmlspecialchars($student['StudentName']); ?></span>
            </div>

            <div class="action-buttons">
                <form method="POST" style="display: inline-block;">
                    <input type="hidden" name="space_id" value="<?php echo htmlspecialchars($selectedSpace['ParkingSpaceID']); ?>">
                    <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
                    <input type="hidden" name="time" value="<?php echo htmlspecialchars($time); ?>">
                    <button type="submit" name="confirm_booking" class="btn btn-primary" onclick="return confirm('Are you sure you want to confirm this booking?');">
                        <i class="fas fa-check-circle"></i> Confirm Booking
                    </button>
                </form>
                <form method="POST" style="display: inline-block;">
                    <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
                    <input type="hidden" name="time" value="<?php echo htmlspecialchars($time); ?>">
                    <button type="submit" name="search_spaces" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Search
                    </button>
                </form>
            </div>

        <?php elseif ($bookingStep === 'success'): ?>
            <!-- Step 3: Booking Success -->
            <div class="highlight-box" style="background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-left-color: #28a745;">
                <h3 style="color: #155724;">✅ Booking Confirmed!</h3>
                <p style="color: #155724;">Your parking space has been successfully booked.</p>
            </div>

            <div class="action-buttons">
                <a href="StudentDashboard.php" class="btn btn-primary">
                    <i class="fas fa-home"></i> Back to Dashboard
                </a>
                <form method="POST" style="display: inline-block;">
                    <button type="submit" name="search_spaces" class="btn btn-secondary">
                        <i class="fas fa-plus-circle"></i> Make Another Booking
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<footer>
    <center><p><i class="far fa-copyright"></i> 2025 FKPark System</p></center>
</footer>

<script>
// Toggle sidebar function (same as StudentDashboard.php)
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.maincontent');
    
    if (window.innerWidth <= 768) {
        // Mobile: use active/shifted classes
        sidebar.classList.toggle('active');
        mainContent.classList.toggle('shifted');
    } else {
        // Desktop: use collapsed classes
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('collapsed');
    }
}

// Close sidebar when clicking outside on mobile (same as StudentDashboard.php)
document.addEventListener('click', function(event) {
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.querySelector('.toggle-btn');
    const mainContent = document.querySelector('.maincontent');
    
    if (window.innerWidth <= 768 && 
        !sidebar.contains(event.target) && 
        !toggleBtn.contains(event.target) && 
        sidebar.classList.contains('active')) {
        sidebar.classList.remove('active');
        mainContent.classList.remove('shifted');
    }
});

// Set minimum date to today
document.getElementById('date').min = new Date().toISOString().split('T')[0];

// Session timeout functionality (same as StudentDashboard.php)
let timeout = 60;
let warningTime = 10;
let countdown;

function startTimer() {
    clearTimeout(countdown);
    
    countdown = setTimeout(() => {
        let stay = confirm(
            "Your session will expire soon.\n\nClick OK to continue or Cancel to logout."
        );
        
        if (stay) {
            // Ping server to refresh session
            fetch("keep_alive.php")
            .then(() => {
                startTimer(); // restart timer
            });
        } else {
            window.location.href = "Logout.php";
        }
    }, (timeout - warningTime) * 1000);
}

// Restart timer on user activity (same as StudentDashboard.php)
["click", "mousemove", "keypress"].forEach(event => {
    document.addEventListener(event, startTimer);
});

// Start timer on page load (same as StudentDashboard.php)
startTimer();
</script>
</body>
</html>
<?php
$conn->close();
$student_stmt->close();
?>