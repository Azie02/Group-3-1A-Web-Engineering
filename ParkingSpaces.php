<?php
// Start the session
session_start();

// Database connection parameters
$conn = new mysqli("localhost", "root", "", "FKParkSystem", 3307);

// Check if database connection failed
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$areaId = $_GET['area_id'] ?? '';

if (empty($areaId)) {
    echo '<div class="alert alert-danger">Error: Area ID is required</div>';
    exit;
}

// Get area details
$stmt = $conn->prepare("SELECT * FROM ParkingArea WHERE ParkingAreaID = ?");
$stmt->bind_param("s", $areaId);
$stmt->execute();
$result = $stmt->get_result();
$area = $result->fetch_assoc();

if (!$area) {
    echo '<div class="alert alert-danger">Error: Parking area not found</div>';
    exit;
}

// Get all spaces for this area
$stmt = $conn->prepare("
    SELECT ps.*, pst.SpaceStatus, pst.DateStatus
    FROM ParkingSpace ps
    LEFT JOIN ParkingStatus pst ON ps.ParkingSpaceID = pst.ParkingSpaceID
    WHERE ps.ParkingAreaID = ?
    ORDER BY CAST(SUBSTRING_INDEX(ps.SpaceNumber, '-', -1) AS UNSIGNED)
");
$stmt->bind_param("s", $areaId);
$stmt->execute();
$result = $stmt->get_result();
$spaces = $result->fetch_all(MYSQLI_ASSOC);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $spaceId = $_POST['space_id'];
    $newStatus = $_POST['new_status'];
    
    $stmt = $conn->prepare("
        UPDATE ParkingStatus 
        SET SpaceStatus = ?, DateStatus = CURDATE()
        WHERE ParkingSpaceID = ?
    ");
    $stmt->bind_param("ss", $newStatus, $spaceId);
    $stmt->execute();
    
    // Refresh the page
    header("Location: ParkingSpaces.php?area_id=" . $areaId);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FK Park System - Parking Spaces - Area <?php echo htmlspecialchars($area['AreaNumber'] ?? ''); ?></title>
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

        .sidebar-toggle:hover {
            background-color: rgba(106, 34, 189, 0.1);
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

        .form-container {
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

        .action-links a {
            margin-right: 10px;
            text-decoration: none;
            color: #0066cc;
        }

        /* Sidebar Toggle Button */
        .sidebar-toggle {
            background: none;
            border: none;
            color: #6a22bdff;
            font-size: 24px;
            cursor: pointer;
            padding: 10px;
            border-radius: 4px;
            transition: all 0.3s ease;
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
        
        .area-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            transition: box-shadow 0.3s ease;
        }
        
        .area-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
        }
        
        .btn-edit {
            background-color: #ffc107;
            color: #212529;
            border: 1px solid #ffc107;
        }
        
        .btn-edit:hover {
            background: #e0a800;
            border-color: #e0a800;
            color: #000;
        }
        
        .space-card {
            border-left: 4px solid;
            transition: all 0.3s;
            margin-bottom: 15px;
        }
        
        .space-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .back-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .back-btn:hover {
            background: #5a6268;
            color: white;
        }
        
        .hierarchy-indicator {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-left">
            <!-- Sidebar Toggle Button -->
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
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
        <h1 class="sidebartitle">Admin Bar</h1>
        <ul class="menu">
            <li>
                <a href="AdminDashboard.php" class="menutext">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="ManageUser.php" class="menutext">
                    <i class="fas fa-users"></i> Manage User
                </a>
            </li>
            <li>
                <a href="ParkingManagement.php" class="menutext active">
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
        <!-- Hierarchy Navigation -->
        <div class="hierarchy-indicator">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <a href="ParkingArea.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Parking Areas
                    </a>
                    <h4 class="mt-3 mb-0">
                        <i class="fas fa-map-marked-alt"></i> 
                        Parking Spaces - Area <?php echo htmlspecialchars($area['AreaNumber'] ?? ''); ?>
                        <small class="text-muted">(<?php echo htmlspecialchars($area['AreaType'] ?? ''); ?>)</small>
                    </h4>
                    <small class="text-muted">
                        Area ID: <?php echo $area['ParkingAreaID'] ?? ''; ?> | 
                        Total Capacity: <?php echo $area['TotalSpaces'] ?? 0; ?> spaces
                    </small>
                </div>
                <div>
                    <a href="ParkingArea.php" class="btn btn-primary">
                        <i class="fas fa-parking"></i> Parking Areas
                    </a>
                </div>
            </div>
        </div>

        <!-- Area Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo count($spaces); ?></h5>
                        <p class="card-text">Total Spaces</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?php 
                            $available = 0;
                            foreach($spaces as $space) {
                                if($space['SpaceStatus'] == 'Available') $available++;
                            }
                            echo $available;
                            ?>
                        </h5>
                        <p class="card-text text-success">Available</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?php 
                            $occupied = 0;
                            foreach($spaces as $space) {
                                if($space['SpaceStatus'] == 'Occupied') $occupied++;
                            }
                            echo $occupied;
                            ?>
                        </h5>
                        <p class="card-text text-danger">Occupied</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?php 
                            $maintenance = 0;
                            foreach($spaces as $space) {
                                if($space['SpaceStatus'] == 'Maintenance') $maintenance++;
                            }
                            echo $maintenance;
                            ?>
                        </h5>
                        <p class="card-text text-warning">Maintenance</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Spaces Grid -->
        <div class="row">
            <?php if(empty($spaces)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle fa-2x mb-2"></i>
                        <h5>No Spaces Found</h5>
                        <p>This parking area doesn't have any spaces yet.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach($spaces as $space): 
                    $statusColor = $space['SpaceStatus'] == 'Available' ? 'success' : 
                                  ($space['SpaceStatus'] == 'Occupied' ? 'danger' : 'warning');
                    $borderColor = $space['SpaceStatus'] == 'Available' ? '#28a745' : 
                                  ($space['SpaceStatus'] == 'Occupied' ? '#dc3545' : '#ffc107');
                ?>
                <div class="col-md-4 mb-3">
                    <div class="card space-card" style="border-left-color: <?php echo $borderColor; ?>;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="card-title mb-1">
                                        <i class="fas fa-parking"></i> 
                                        <?php echo htmlspecialchars($space['SpaceNumber']); ?>
                                    </h5>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            Type: <?php echo htmlspecialchars($space['SpaceType']); ?><br>
                                            ID: <?php echo $space['ParkingSpaceID']; ?>
                                        </small>
                                    </p>
                                </div>
                                <span class="badge bg-<?php echo $statusColor; ?>">
                                    <?php echo $space['SpaceStatus']; ?>
                                </span>
                            </div>
                            
                            <div class="mt-3">
                                <form method="POST" action="" class="d-flex gap-2">
                                    <input type="hidden" name="space_id" value="<?php echo $space['ParkingSpaceID']; ?>">
                                    <select name="new_status" class="form-select form-select-sm">
                                        <option value="Available" <?php echo $space['SpaceStatus'] == 'Available' ? 'selected' : ''; ?>>
                                            Available
                                        </option>
                                        <option value="Occupied" <?php echo $space['SpaceStatus'] == 'Occupied' ? 'selected' : ''; ?>>
                                            Occupied
                                        </option>
                                        <option value="Maintenance" <?php echo $space['SpaceStatus'] == 'Maintenance' ? 'selected' : ''; ?>>
                                            Maintenance
                                        </option>
                                    </select>
                                    <button type="submit" name="update_status" class="btn btn-sm btn-primary">
                                        <i class="fas fa-sync-alt"></i> Update
                                    </button>
                                </form>
                            </div>
                            
                            <?php if(!empty($space['DateStatus'])): ?>
                                <small class="text-muted mt-2 d-block">
                                    <i class="far fa-calendar-alt"></i> 
                                    Status since: <?php echo date('d/m/Y', strtotime($space['DateStatus'])); ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <footer id="footer">
        <div class="container">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> FK Park System. All rights reserved.</p>
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
                });
                
                // Load saved state
                const savedState = localStorage.getItem('sidebarCollapsed');
                if (savedState === 'true') {
                    sidebar.classList.add('collapsed');
                    mainContainer.classList.add('sidebar-collapsed');
                    footer.classList.add('sidebar-collapsed');
                }
            }
        });
    </script>
</body>
</html>