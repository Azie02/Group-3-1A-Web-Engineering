<?php
// Start the session
session_start();

// Database connection parameters
$conn = new mysqli("localhost", "root", "", "fkparksystem", 3307);

// Check if database connection failed
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Restrict access to only logged-in student
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'student') {
    header("Location: Login.php");
    exit();
}

// Get student data from database
$student_id = $_SESSION['user_id'];
$query = "SELECT * FROM student WHERE StudentID = ?"; // Fixed: uppercase StudentID
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    // Student not found in database
    session_destroy();
    header("Location: Login.php");
    exit();
}
$student = $result->fetch_assoc();
$student_number = $student['StudentID']; // Fixed: uppercase StudentID

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get student's approved vehicles
$approved_vehicles = [];
$vehiclesStmt = $conn->prepare("
    SELECT VehicleID, VehicleType, PlateNumber, VehicleModel, VehicleColour 
    FROM Vehicle 
    WHERE StudentID = ? AND VehicleApproval = 'Approved'
    ORDER BY VehicleID DESC
");
if ($vehiclesStmt) {
    $vehiclesStmt->bind_param("s", $student_number);
    $vehiclesStmt->execute();
    $result = $vehiclesStmt->get_result();
    if ($result) {
        $approved_vehicles = $result->fetch_all(MYSQLI_ASSOC);
    }
    $vehiclesStmt->close();
}

// Get available parking spaces
$parking_spaces = [];
$spacesStmt = $conn->prepare("
    SELECT 
        ps.ParkingSpaceID,
        ps.SpaceNumber,
        ps.SpaceType,
        pa.AreaType,
        pa.AreaNumber,
        pst.SpaceStatus
    FROM ParkingSpace ps
    INNER JOIN ParkingArea pa ON ps.ParkingAreaID = pa.ParkingAreaID
    LEFT JOIN ParkingStatus pst ON ps.ParkingSpaceID = pst.ParkingSpaceID 
        AND DATE(pst.DateStatus) = DATE(NOW())
    WHERE pst.SpaceStatus IS NULL OR pst.SpaceStatus = 'Available'
    ORDER BY ps.SpaceType, ps.SpaceNumber
");
if ($spacesStmt) {
    $spacesStmt->execute();
    $result = $spacesStmt->get_result();
    if ($result) {
        $parking_spaces = $result->fetch_all(MYSQLI_ASSOC);
    }
    $spacesStmt->close();
}

// Handle form submission for booking
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security token invalid. Please try again.";
    } else {
        $vehicle_id = $_POST['vehicle_id'];
        $space_id = $_POST['space_id'];
        $booking_date = $_POST['booking_date'];
        $booking_time = $_POST['booking_time'];
        $duration = $_POST['duration'];
        
        // Validation
        if (empty($vehicle_id) || empty($space_id) || empty($booking_date) || empty($booking_time) || empty($duration)) {
            $error = "Please fill in all required fields";
        } else {
            // Check if vehicle belongs to student and is approved
            $checkVehicleStmt = $conn->prepare("SELECT VehicleID FROM Vehicle WHERE VehicleID = ? AND StudentID = ? AND VehicleApproval = 'Approved'");
            $checkVehicleStmt->bind_param("ss", $vehicle_id, $student_number);
            $checkVehicleStmt->execute();
            $checkVehicleResult = $checkVehicleStmt->get_result();
            
            if ($checkVehicleResult->num_rows !== 1) {
                $error = "Invalid vehicle selected";
            } else {
                // Check if space exists and matches vehicle type
                $checkSpaceStmt = $conn->prepare("
                    SELECT ps.ParkingSpaceID, ps.SpaceType 
                    FROM ParkingSpace ps
                    WHERE ps.ParkingSpaceID = ?
                ");
                $checkSpaceStmt->bind_param("i", $space_id);
                $checkSpaceStmt->execute();
                $checkSpaceResult = $checkSpaceStmt->get_result();
                
                if ($checkSpaceResult->num_rows !== 1) {
                    $error = "The selected parking space does not exist";
                } else {
                    $space_data = $checkSpaceResult->fetch_assoc();
                    $vehicleStmt = $conn->prepare("SELECT VehicleType FROM Vehicle WHERE VehicleID = ?");
                    $vehicleStmt->bind_param("s", $vehicle_id);
                    $vehicleStmt->execute();
                    $vehicleResult = $vehicleStmt->get_result();
                    $vehicle_data = $vehicleResult->fetch_assoc();
                    
                    if ($space_data['SpaceType'] !== $vehicle_data['VehicleType']) {
                        $error = "Vehicle type does not match parking space type";
                    } else {
                        // Check if space is available for the selected date
                        $checkStatusStmt = $conn->prepare("
                            SELECT ParkingStatusID 
                            FROM ParkingStatus 
                            WHERE ParkingSpaceID = ? 
                            AND DATE(DateStatus) = ? 
                            AND SpaceStatus IN ('Occupied', 'Maintenance')
                        ");
                        $checkStatusStmt->bind_param("is", $space_id, $booking_date);
                        $checkStatusStmt->execute();
                        $checkStatusResult = $checkStatusStmt->get_result();
                        
                        if ($checkStatusResult->num_rows > 0) {
                            $error = "The selected parking space is not available on this date";
                        } else {
                            // Check if vehicle already has active booking for this date
                            $checkBookingStmt = $conn->prepare("
                                SELECT BookingID FROM Booking 
                                WHERE StudentID = ? AND BookingDate = ? AND BookingStatus = 'Active'
                            ");
                            $checkBookingStmt->bind_param("ss", $student_number, $booking_date);
                            $checkBookingStmt->execute();
                            $checkBookingResult = $checkBookingStmt->get_result();
                            
                            if ($checkBookingResult->num_rows > 0) {
                                $error = "You already have an active booking for this date";
                            } else {
                                // Generate next BookingID (format: B001, B002, etc.)
                                $result = $conn->query("SELECT MAX(BookingID) as max_id FROM Booking");
                                if ($result) {
                                    $row = $result->fetch_assoc();
                                    $max_id = $row['max_id'];
                                    
                                    if ($max_id) {
                                        // Extract numeric part from BookingID (e.g., B001 -> 1)
                                        $num = (int) substr($max_id, 1);
                                        $next_num = $num + 1;
                                        $next_booking_id = 'B' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
                                    } else {
                                        $next_booking_id = 'B001';
                                    }
                                    
                                    // Calculate end time for display
                                    $start_time_obj = DateTime::createFromFormat('H:i', $booking_time);
                                    $start_time_obj->modify("+{$duration} hours");
                                    $end_time = $start_time_obj->format('H:i');
                                    
                                    // Check if Booking table has VehicleID column
                                    $checkColumn = $conn->query("SHOW COLUMNS FROM Booking LIKE 'VehicleID'");
                                    
                                    if ($checkColumn->num_rows > 0) {
                                        // Booking table has VehicleID column
                                        $stmt = $conn->prepare("
                                            INSERT INTO Booking 
                                            (BookingID, StudentID, VehicleID, ParkingSpaceID, BookingDate, BookingTime, BookingStatus) 
                                            VALUES (?, ?, ?, ?, ?, ?, 'Active')
                                        ");
                                        
                                        if ($stmt) {
                                            $stmt->bind_param("sssiss", 
                                                $next_booking_id,
                                                $student_number,
                                                $vehicle_id,
                                                $space_id,
                                                $booking_date,
                                                $booking_time
                                            );
                                        }
                                    } else {
                                        // Booking table doesn't have VehicleID column
                                        $stmt = $conn->prepare("
                                            INSERT INTO Booking 
                                            (BookingID, StudentID, ParkingSpaceID, BookingDate, BookingTime, BookingStatus) 
                                            VALUES (?, ?, ?, ?, ?, 'Active')
                                        ");
                                        
                                        if ($stmt) {
                                            $stmt->bind_param("ssiss", 
                                                $next_booking_id,
                                                $student_number,
                                                $space_id,
                                                $booking_date,
                                                $booking_time
                                            );
                                        }
                                    }
                                    
                                    if ($stmt && $stmt->execute()) {
                                        // Update parking status to Occupied
                                        $updateStatusStmt = $conn->prepare("
                                            INSERT INTO ParkingStatus 
                                            (ParkingSpaceID, ParkingAreaID, SpaceStatus, DateStatus) 
                                            VALUES (?, (SELECT ParkingAreaID FROM ParkingSpace WHERE ParkingSpaceID = ?), 'Occupied', ?)
                                        ");
                                        $updateStatusStmt->bind_param("iis", $space_id, $space_id, $booking_date);
                                        $updateStatusStmt->execute();
                                        $updateStatusStmt->close();
                                        
                                        $message = "Booking #$next_booking_id confirmed successfully! Parking from $booking_time to $end_time";
                                        $_POST = array();
                                        
                                        // Refresh parking spaces
                                        $spacesStmt->execute();
                                        $result = $spacesStmt->get_result();
                                        $parking_spaces = $result->fetch_all(MYSQLI_ASSOC);
                                    } else {
                                        $error = "Database error: " . $conn->error;
                                    }
                                    if ($stmt) $stmt->close();
                                } else {
                                    $error = "Database error: " . $conn->error;
                                }
                            }
                            $checkBookingStmt->close();
                        }
                        $checkStatusStmt->close();
                        $vehicleStmt->close();
                    }
                }
                $checkSpaceStmt->close();
            }
            $checkVehicleStmt->close();
        }
    }
}

// Get student's active bookings
$active_bookings = [];
// Check if Booking table has VehicleID column
$checkColumn = $conn->query("SHOW COLUMNS FROM Booking LIKE 'VehicleID'");
if ($checkColumn->num_rows > 0) {
    // Booking table has VehicleID column
    $bookingsStmt = $conn->prepare("
        SELECT 
            b.BookingID, 
            v.PlateNumber, 
            v.VehicleType, 
            ps.SpaceNumber,
            pa.AreaType,
            pa.AreaNumber,
            b.BookingDate, 
            b.BookingTime, 
            b.BookingStatus
        FROM Booking b
        LEFT JOIN Vehicle v ON b.VehicleID = v.VehicleID
        JOIN ParkingSpace ps ON b.ParkingSpaceID = ps.ParkingSpaceID
        JOIN ParkingArea pa ON ps.ParkingAreaID = pa.ParkingAreaID
        WHERE b.StudentID = ? AND b.BookingStatus = 'Active'
        ORDER BY b.BookingDate DESC, b.BookingTime DESC
    ");
} else {
    // Booking table doesn't have VehicleID column
    $bookingsStmt = $conn->prepare("
        SELECT 
            b.BookingID, 
            ps.SpaceNumber,
            pa.AreaType,
            pa.AreaNumber,
            b.BookingDate, 
            b.BookingTime, 
            b.BookingStatus
        FROM Booking b
        JOIN ParkingSpace ps ON b.ParkingSpaceID = ps.ParkingSpaceID
        JOIN ParkingArea pa ON ps.ParkingAreaID = pa.ParkingAreaID
        WHERE b.StudentID = ? AND b.BookingStatus = 'Active'
        ORDER BY b.BookingDate DESC, b.BookingTime DESC
    ");
}

if ($bookingsStmt) {
    $bookingsStmt->bind_param("s", $student_number);
    $bookingsStmt->execute();
    $result = $bookingsStmt->get_result();
    if ($result) {
        $active_bookings = $result->fetch_all(MYSQLI_ASSOC);
    }
    $bookingsStmt->close();
}

// Get student's past bookings
$past_bookings = [];
// Check if Booking table has VehicleID column
$checkColumn2 = $conn->query("SHOW COLUMNS FROM Booking LIKE 'VehicleID'");
if ($checkColumn2->num_rows > 0) {
    $pastBookingsStmt = $conn->prepare("
        SELECT 
            b.BookingID, 
            v.PlateNumber, 
            v.VehicleType, 
            ps.SpaceNumber,
            pa.AreaType,
            pa.AreaNumber,
            b.BookingDate, 
            b.BookingTime, 
            b.BookingStatus
        FROM Booking b
        LEFT JOIN Vehicle v ON b.VehicleID = v.VehicleID
        JOIN ParkingSpace ps ON b.ParkingSpaceID = ps.ParkingSpaceID
        JOIN ParkingArea pa ON ps.ParkingAreaID = pa.ParkingAreaID
        WHERE b.StudentID = ? AND b.BookingStatus IN ('Completed', 'Cancelled')
        ORDER BY b.BookingDate DESC, b.BookingTime DESC
        LIMIT 10
    ");
} else {
    $pastBookingsStmt = $conn->prepare("
        SELECT 
            b.BookingID, 
            ps.SpaceNumber,
            pa.AreaType,
            pa.AreaNumber,
            b.BookingDate, 
            b.BookingTime, 
            b.BookingStatus
        FROM Booking b
        JOIN ParkingSpace ps ON b.ParkingSpaceID = ps.ParkingSpaceID
        JOIN ParkingArea pa ON ps.ParkingAreaID = pa.ParkingAreaID
        WHERE b.StudentID = ? AND b.BookingStatus IN ('Completed', 'Cancelled')
        ORDER BY b.BookingDate DESC, b.BookingTime DESC
        LIMIT 10
    ");
}

if ($pastBookingsStmt) {
    $pastBookingsStmt->bind_param("s", $student_number);
    $pastBookingsStmt->execute();
    $result = $pastBookingsStmt->get_result();
    if ($result) {
        $past_bookings = $result->fetch_all(MYSQLI_ASSOC);
    }
    $pastBookingsStmt->close();
}

// Get parking area statistics
$area_stats = [];
$statsStmt = $conn->prepare("
    SELECT 
        pa.AreaType,
        pa.AreaNumber,
        COUNT(ps.ParkingSpaceID) as TotalSpaces,
        SUM(CASE WHEN pst.SpaceStatus = 'Available' OR pst.SpaceStatus IS NULL THEN 1 ELSE 0 END) as AvailableSpaces
    FROM ParkingArea pa
    LEFT JOIN ParkingSpace ps ON pa.ParkingAreaID = ps.ParkingAreaID
    LEFT JOIN ParkingStatus pst ON ps.ParkingSpaceID = pst.ParkingSpaceID 
        AND DATE(pst.DateStatus) = DATE(NOW())
    GROUP BY pa.AreaType, pa.AreaNumber
");
if ($statsStmt) {
    $statsStmt->execute();
    $result = $statsStmt->get_result();
    if ($result) {
        $area_stats = $result->fetch_all(MYSQLI_ASSOC);
    }
    $statsStmt->close();
}

// 60 seconds inactivity timeout
//if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 60) {
  //  session_unset();
  //  session_destroy();
  //  header("Location: Login.php");
  //  exit();
//}

// Update activity time on every request
//$_SESSION['last_activity'] = time();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FK Park System - Book Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .togglebutton:hover {
            background-color: #044747ff;
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
            transition: all 0.3s ease;
            z-index: 999;
        }
        .sidebar.collapsed {
            transform: translateX(-250px);
            opacity: 0;
            width: 0;
            padding: 0;
        }
        .sidebartitle{
            color: white;
            font-size: 1rem;
            margin-bottom: 20px;
            padding: 0 20px;
        }
        /* Main Content Adjustment */
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
        
        /* Custom styles for booking */
        .status-badge {
            font-size: 0.8rem;
            padding: 0.35em 0.65em;
        }
        .vehicle-icon {
            font-size: 1.5rem;
            margin-right: 10px;
        }
        .car-icon { color: #007bff; }
        .motorcycle-icon { color: #28a745; }
        .alert {
            margin-top: 20px;
        }
        .card {
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            border: none;
            border-radius: 10px;
        }
        .card-header {
            font-weight: bold;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        .form-label {
            font-weight: 500;
        }
        .form-text {
            font-size: 0.85rem;
        }
        .table th {
            font-weight: 600;
            background-color: #f8f9fa;
            border-top: none;
        }
        .table td {
            vertical-align: middle;
        }
        .required:after {
            content: " *";
            color: #dc3545;
        }
        .info-box {
            background-color: #e9f7fe;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 15px 0;
            border-radius: 0 4px 4px 0;
        }
        
        /* Cards styling */
        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        /* Parking space grid */
        .parking-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .space-card {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .space-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .space-card.selected {
            border-color: #28a745;
            background-color: rgba(40, 167, 69, 0.1);
        }
        .space-card.available {
            border-color: #28a745;
        }
        .space-card.occupied {
            border-color: #dc3545;
            background-color: rgba(220, 53, 69, 0.1);
            cursor: not-allowed;
        }
        .space-card.maintenance {
            border-color: #ffc107;
            background-color: rgba(255, 193, 7, 0.1);
            cursor: not-allowed;
        }
        .space-number {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .space-type {
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        .area-info {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        .space-status {
            font-size: 0.8rem;
            padding: 2px 8px;
            border-radius: 10px;
        }
        
        footer {
            background-color: #80cab1ff;
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
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .main-container {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }
            .sidebar.collapsed {
                transform: translateX(-250px);
            }
            .sidebar:not(.collapsed) {
                transform: translateX(0);
            }
            .cards-container {
                grid-template-columns: 1fr;
            }
            .parking-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
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
                <img src="UMPLogo.png" alt="UMPLogo" onerror="this.style.display='none'">
            </div>
        </div>
        <div class="header-right">
            <a href="StudentProfile.php" class="profile">
                <i class="fas fa-user-circle"></i> My Profile
            </a>
            <a href="logout.php" class="logoutbutton" onclick="return confirm('Are you sure you want to log out?');">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>
    
    <nav class="sidebar" id="sidebar">
        <h1 class="sidebartitle"><strong>Student</strong></h1>
        <ul class="menu">
            <li>
                <a href="StudentDashboard.php" class="menutext">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="VehicleRegistration.php" class="menutext">
                    <i class="fas fa-car"></i> Vehicle Registration
                </a>
            </li>
            <li>
                <a href="Booking.php" class="menutext active">
                    <i class="fas fa-parking"></i> Book Parking
                </a>
            </li>
            <li>
                <a href="meritStatus.php" class="menutext">
                    <i class="fas fa-exclamation-triangle"></i> Merit Status
                </a>
            </li>
        </ul>
    </nav>

    <div class="main-container" id="mainContainer">
        <div class="content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="fas fa-parking"></i> Book Parking
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <span class="badge bg-primary p-2">
                        <i class="fas fa-id-card"></i> Student ID: <?php echo htmlspecialchars($student_number); ?>
                    </span>
                </div>
            </div>      
            
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Check if student has approved vehicles -->
            <?php if (empty($approved_vehicles)): ?>
                <div class="alert alert-warning" role="alert">
                    <h4 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>No Approved Vehicles</h4>
                    <p>You need to have at least one approved vehicle before you can book parking.</p>
                    <hr>
                    <p class="mb-0">
                        <a href="VehicleRegistration.php" class="btn btn-primary">
                            <i class="fas fa-car"></i> Register Vehicle Now
                        </a>
                    </p>
                </div>
            <?php else: ?>
            <div class="row">
                <!-- Booking Form -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-plus-circle"></i> New Booking
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" id="bookingForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" id="space_id" name="space_id" value="">
                                
                                <div class="mb-3">
                                    <label for="vehicle_id" class="form-label required">Select Vehicle</label>
                                    <select class="form-select" id="vehicle_id" name="vehicle_id" required>
                                        <option value="">Select Vehicle</option>
                                        <?php foreach ($approved_vehicles as $vehicle): ?>
                                        <option value="<?php echo htmlspecialchars($vehicle['VehicleID']); ?>">
                                            <?php echo htmlspecialchars($vehicle['PlateNumber'] . ' - ' . $vehicle['VehicleModel'] . ' (' . $vehicle['VehicleType'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="booking_date" class="form-label required">Booking Date</label>
                                        <input type="date" class="form-control" id="booking_date" name="booking_date" 
                                               min="<?php echo date('Y-m-d'); ?>" 
                                               max="<?php echo date('Y-m-d', strtotime('+7 days')); ?>"
                                               value="<?php echo isset($_POST['booking_date']) ? htmlspecialchars($_POST['booking_date']) : date('Y-m-d'); ?>"
                                               required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="booking_time" class="form-label required">Booking Time</label>
                                        <input type="time" class="form-control" id="booking_time" name="booking_time" 
                                               min="08:00" max="22:00" 
                                               value="<?php echo isset($_POST['booking_time']) ? htmlspecialchars($_POST['booking_time']) : '08:00'; ?>"
                                               required>
                                        <div class="form-text">Parking available from 8:00 AM to 10:00 PM</div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="duration" class="form-label required">Duration (hours)</label>
                                    <select class="form-select" id="duration" name="duration" required>
                                        <option value="1" <?php echo (isset($_POST['duration']) && $_POST['duration'] == '1') ? 'selected' : ''; ?>>1 hour</option>
                                        <option value="2" <?php echo (isset($_POST['duration']) && $_POST['duration'] == '2') ? 'selected' : ''; ?>>2 hours</option>
                                        <option value="3" <?php echo (isset($_POST['duration']) && $_POST['duration'] == '3') ? 'selected' : ''; ?>>3 hours</option>
                                        <option value="4" <?php echo (isset($_POST['duration']) && $_POST['duration'] == '4') ? 'selected' : ''; ?>>4 hours</option>
                                        <option value="6" <?php echo (isset($_POST['duration']) && $_POST['duration'] == '6') ? 'selected' : ''; ?>>6 hours</option>
                                        <option value="8" <?php echo (isset($_POST['duration']) && $_POST['duration'] == '8') ? 'selected' : ''; ?>>8 hours</option>
                                    </select>
                                </div>
                                
                                <div class="info-box">
                                    <small>
                                        <strong><i class="fas fa-info-circle"></i> Parking Information:</strong>
                                        <ul class="mb-0 mt-2">
                                            <li>Parking hours: 8:00 AM - 10:00 PM</li>
                                            <li>Maximum booking duration: 8 hours</li>
                                            <li>You can book up to 7 days in advance</li>
                                            <li>One active booking per vehicle per day</li>
                                            <li>Cancel bookings at least 1 hour before start time</li>
                                        </ul>
                                    </small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary mt-3">
                                    <i class="fas fa-search"></i> Check Availability & Book
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Available Parking Spaces -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-parking"></i> Available Parking Spaces
                                <span class="badge bg-secondary float-end">
                                    <?php echo count($parking_spaces); ?> spaces
                                </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($parking_spaces)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-parking fa-3x text-muted mb-3"></i>
                                    <h5>No Available Spaces</h5>
                                    <p class="text-muted">All parking spaces are currently occupied or under maintenance</p>
                                </div>
                            <?php else: ?>
                                <div class="parking-grid" id="parkingGrid">
                                    <?php foreach ($parking_spaces as $space): 
                                        $status_class = '';
                                        $status_text = '';
                                        $is_available = ($space['SpaceStatus'] == 'Available' || $space['SpaceStatus'] === null);
                                        
                                        if ($space['SpaceStatus'] === null) {
                                            $status_class = 'available';
                                            $status_text = 'Available';
                                        } elseif ($space['SpaceStatus'] == 'Available') {
                                            $status_class = 'available';
                                            $status_text = 'Available';
                                        } elseif ($space['SpaceStatus'] == 'Occupied') {
                                            $status_class = 'occupied';
                                            $status_text = 'Occupied';
                                        } elseif ($space['SpaceStatus'] == 'Maintenance') {
                                            $status_class = 'maintenance';
                                            $status_text = 'Maintenance';
                                        }
                                    ?>
                                    <div class="space-card <?php echo $status_class; ?>" 
                                         data-space-id="<?php echo $space['ParkingSpaceID']; ?>"
                                         data-space-type="<?php echo $space['SpaceType']; ?>"
                                         data-space-number="<?php echo $space['SpaceNumber']; ?>"
                                         data-area-type="<?php echo $space['AreaType']; ?>"
                                         data-area-number="<?php echo $space['AreaNumber']; ?>"
                                         onclick="selectSpace(this)">
                                        <div class="space-number"><?php echo htmlspecialchars($space['SpaceNumber']); ?></div>
                                        <div class="area-info">
                                            <?php echo htmlspecialchars($space['AreaType'] . ' ' . $space['AreaNumber']); ?>
                                        </div>
                                        <div class="space-type">
                                            <?php if ($space['SpaceType'] == 'Car'): ?>
                                                <i class="fas fa-car"></i> Car
                                            <?php else: ?>
                                                <i class="fas fa-motorcycle"></i> Motorcycle
                                            <?php endif; ?>
                                        </div>
                                        <div class="space-status badge bg-<?php 
                                            echo $is_available ? 'success' : 
                                                 ($space['SpaceStatus'] == 'Occupied' ? 'danger' : 'warning'); ?>">
                                            <?php echo $status_text; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- My Bookings -->
                <div class="col-lg-6">
                    <!-- Active Bookings -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-calendar-check"></i> Active Bookings
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($active_bookings)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-calendar fa-3x text-muted mb-3"></i>
                                    <h5>No Active Bookings</h5>
                                    <p class="text-muted">Make your first booking using the form</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Booking ID</th>
                                                <th>Parking Space</th>
                                                <th>Date & Time</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($active_bookings as $booking): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?php echo htmlspecialchars($booking['BookingID']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <strong><?php echo htmlspecialchars($booking['SpaceNumber']); ?></strong><br>
                                                        <span class="text-muted"><?php echo htmlspecialchars($booking['AreaType'] . ' ' . $booking['AreaNumber']); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <strong><?php echo date('d/m/Y', strtotime($booking['BookingDate'])); ?></strong><br>
                                                        <?php echo htmlspecialchars(date('h:i A', strtotime($booking['BookingTime']))); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success status-badge">
                                                        <i class="fas fa-check-circle me-1"></i>
                                                        <?php echo htmlspecialchars($booking['BookingStatus']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Past Bookings -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-history"></i> Recent Bookings History
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($past_bookings)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                    <h5>No Past Bookings</h5>
                                    <p class="text-muted">Your booking history will appear here</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Booking ID</th>
                                                <th>Parking Space</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($past_bookings as $booking): 
                                                $status_class = '';
                                                $icon = '';
                                                switch($booking['BookingStatus']) {
                                                    case 'Completed':
                                                        $status_class = 'bg-success';
                                                        $icon = 'fa-check-circle';
                                                        break;
                                                    case 'Cancelled':
                                                        $status_class = 'bg-danger';
                                                        $icon = 'fa-times-circle';
                                                        break;
                                                    default:
                                                        $status_class = 'bg-secondary';
                                                        $icon = 'fa-clock';
                                                }
                                            ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?php echo htmlspecialchars($booking['BookingID']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <strong><?php echo htmlspecialchars($booking['SpaceNumber']); ?></strong><br>
                                                        <span class="text-muted"><?php echo htmlspecialchars($booking['AreaType'] . ' ' . $booking['AreaNumber']); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php echo date('d/m/Y', strtotime($booking['BookingDate'])); ?><br>
                                                    <small class="text-muted"><?php echo date('h:i A', strtotime($booking['BookingTime'])); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $status_class; ?> status-badge">
                                                        <i class="fas <?php echo $icon; ?> me-1"></i>
                                                        <?php echo htmlspecialchars($booking['BookingStatus']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Booking Guidelines -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-book"></i> Booking Guidelines
                            </h6>
                        </div>
                        <div class="card-body">
                            <ul class="small mb-0">
                                <li>Bookings can be made up to 7 days in advance</li>
                                <li>Parking hours: 8:00 AM - 10:00 PM</li>
                                <li>Maximum booking duration: 8 hours</li>
                                <li>Only approved vehicles can book parking</li>
                                <li>Vehicle type must match parking space type</li>
                                <li>One active booking per vehicle per day</li>
                                <li>Cancel at least 1 hour before booking time</li>
                                <li>No-show may affect future booking privileges</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; // End check for approved vehicles ?>
        </div>
    </div>

    <footer id="footer">
        <div class="container">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> FK Park System. All rights reserved.</p>
            <small>Current time: <?php echo date('d/m/Y H:i:s'); ?></small>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar Toggle Functionality
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
                    
                    // Update button icon
                    const icon = sidebarToggle.querySelector('i');
                    if (isCollapsed) {
                        icon.className = 'fas fa-bars';
                    } else {
                        icon.className = 'fas fa-times';
                    }
                });
                
                // Load saved state
                const savedState = localStorage.getItem('sidebarCollapsed');
                if (savedState === 'true') {
                    sidebar.classList.add('collapsed');
                    mainContainer.classList.add('sidebar-collapsed');
                    footer.classList.add('sidebar-collapsed');
                    const icon = sidebarToggle.querySelector('i');
                    icon.className = 'fas fa-bars';
                }
            }
            
            // Form validation
            const bookingForm = document.getElementById('bookingForm');
            if (bookingForm) {
                bookingForm.addEventListener('submit', function(e) {
                    const vehicleSelect = document.getElementById('vehicle_id');
                    const bookingDate = document.getElementById('booking_date');
                    const bookingTime = document.getElementById('booking_time');
                    const spaceId = document.getElementById('space_id');
                    
                    // Check if vehicle is selected
                    if (!vehicleSelect.value) {
                        e.preventDefault();
                        alert('Please select a vehicle');
                        return false;
                    }
                    
                    // Check if date is valid
                    const selectedDate = new Date(bookingDate.value);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    
                    if (selectedDate < today) {
                        e.preventDefault();
                        alert('Booking date cannot be in the past');
                        return false;
                    }
                    
                    // Check if parking space is selected
                    if (!spaceId.value) {
                        e.preventDefault();
                        alert('Please select an available parking space');
                        return false;
                    }
                    
                    // Show loading
                    const submitBtn = this.querySelector('button[type="submit"]');
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    submitBtn.disabled = true;
                    
                    return true;
                });
            }
            
            // Add required asterisk to labels
            document.querySelectorAll('.required').forEach(function(label) {
                if (!label.innerHTML.includes('*')) {
                    label.innerHTML += ' *';
                }
            });
            
            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('booking_date').min = today;
            
            // Set maximum date to 7 days from today
            const maxDate = new Date();
            maxDate.setDate(maxDate.getDate() + 7);
            document.getElementById('booking_date').max = maxDate.toISOString().split('T')[0];
            
            // Auto-refresh every 30 seconds to update space availability
            setTimeout(function() {
                window.location.reload();
            }, 30000);
        });
        
        // Parking space selection
        function selectSpace(spaceElement) {
            const spaceId = spaceElement.getAttribute('data-space-id');
            const spaceType = spaceElement.getAttribute('data-space-type');
            const spaceNumber = spaceElement.getAttribute('data-space-number');
            const areaType = spaceElement.getAttribute('data-area-type');
            const areaNumber = spaceElement.getAttribute('data-area-number');
            
            // Check if space is available
            if (!spaceElement.classList.contains('available')) {
                alert('This parking space is not available for booking');
                return;
            }
            
            // Check if vehicle is selected
            const vehicleSelect = document.getElementById('vehicle_id');
            if (!vehicleSelect.value) {
                alert('Please select a vehicle first');
                return;
            }
            
            // Check if selected vehicle type matches space type
            const selectedOption = vehicleSelect.options[vehicleSelect.selectedIndex];
            const vehicleText = selectedOption.text;
            const vehicleType = vehicleText.includes('Car') ? 'Car' : 
                              vehicleText.includes('Motorcycle') ? 'Motorcycle' : '';
            
            if (vehicleType && vehicleType !== spaceType) {
                alert(`Please select a ${spaceType} parking space for your ${vehicleType.toLowerCase()}`);
                return;
            }
            
            // Remove selected class from all spaces
            document.querySelectorAll('.space-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked space
            spaceElement.classList.add('selected');
            
            // Update hidden input
            document.getElementById('space_id').value = spaceId;
            
            // Show confirmation message
            console.log(`Selected parking space: ${spaceNumber} in ${areaType} ${areaNumber} (ID: ${spaceId})`);
        }
        
        // Handle vehicle selection change to filter spaces
        document.getElementById('vehicle_id').addEventListener('change', function() {
            const vehicleSelect = this;
            const selectedOption = vehicleSelect.options[vehicleSelect.selectedIndex];
            const vehicleText = selectedOption.text;
            const vehicleType = vehicleText.includes('Car') ? 'Car' : 
                              vehicleText.includes('Motorcycle') ? 'Motorcycle' : '';
            
            // Reset space selection
            document.querySelectorAll('.space-card').forEach(card => {
                card.classList.remove('selected');
            });
            document.getElementById('space_id').value = '';
            
            // If a vehicle is selected, highlight matching spaces
            if (vehicleType) {
                document.querySelectorAll('.space-card').forEach(card => {
                    const spaceType = card.getAttribute('data-space-type');
                    if (spaceType === vehicleType) {
                        card.style.opacity = '1';
                        card.style.pointerEvents = 'auto';
                    } else {
                        card.style.opacity = '0.5';
                        card.style.pointerEvents = 'none';
                    }
                });
            } else {
                // Reset all spaces to normal
                document.querySelectorAll('.space-card').forEach(card => {
                    card.style.opacity = '1';
                    card.style.pointerEvents = 'auto';
                });
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth <= 768) {
                document.getElementById('sidebar').classList.add('collapsed');
                document.getElementById('mainContainer').classList.add('sidebar-collapsed');
                document.getElementById('footer').classList.add('sidebar-collapsed');
            }
        });
        
        // Initialize on load for mobile
        if (window.innerWidth <= 768) {
            document.getElementById('sidebar').classList.add('collapsed');
            document.getElementById('mainContainer').classList.add('sidebar-collapsed');
            document.getElementById('footer').classList.add('sidebar-collapsed');
        }
        
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>
<?php 
// Close database connection
$conn->close();
?>