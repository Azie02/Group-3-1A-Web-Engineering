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

$vehicleID = isset($_GET['id']) ? $_GET['id'] : null;
if (!$vehicleID) {
    echo "<script>alert('No vehicle ID provided.'); window.location.href='VehicleApproval.php';</script>";
    exit();
}

$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $status = ($_POST['action'] === 'approve') ? 'Approved' : 'Rejected';
    $remark = mysqli_real_escape_string($conn, $_POST['remark']);

    $updateSql = "UPDATE Vehicle SET VehicleStatus = '$status' WHERE VehicleID = '$vehicleID'";
    if ($conn->query($updateSql) === TRUE) {
        $message = "Vehicle registration has been updated to: " . $status;
    } else {
        $message = "Error updating record: " . $conn->error;
    }
}

// GET Vehicle Details with Student Table
$sql = "SELECT v.*, s.StudentName 
        FROM Vehicle v 
        LEFT JOIN Student s ON v.StudentID = s.StudentID 
        WHERE v.VehicleID = '$vehicleID'";

$result = $conn->query($sql);
$vehicle = $result->fetch_assoc();

if (!$vehicle) {
    echo "Vehicle not found.";
    exit();
}

$imageData = null;
if (!empty($vehicle['VehicleGrant'])) {
    $base64Image = base64_encode($vehicle['VehicleGrant']);
    $imageData = 'data:image/png;base64,' . $base64Image;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Review Vehicle</title>
    <link rel="stylesheet" href="SecurityDashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        .detail-item {
            padding: 15px;
            background: #fafafa;
            border: 1px solid #eee;
            border-radius: 5px;
        }
        .detail-item label {
            display: block;
            font-weight: bold;
            color: #555;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
        .detail-item span {
            font-size: 1.1rem;
            color: #333;
        }
        .status-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.9rem;
        }
        .Pending { background-color: #ffeeba; color: #856404; }
        .Approved { background-color: #d4edda; color: #155724; }
        .Rejected { background-color: #f8d7da; color: #721c24; }

        .grant-section {
            margin-top: 25px;
            padding: 20px;
            background: #fafafa;
            border: 1px solid #eee;
            border-radius: 5px;
            text-align: center;
        }
        .grant-section label {
            display: block;
            font-weight: bold;
            color: #555;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
        .grant-photo {
            max-width: 100%;
            max-height: 500px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            display: block;
            margin: 0 auto;
        }
        .no-photo {
            padding: 40px;
            color: #999;
            font-style: italic;
        }

        .action-container {
            margin-top: 30px;
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .action-container textarea {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            color: white;
            text-transform: uppercase;
        }
        .btn-approve { background-color: #28a745; }
        .btn-reject { background-color: #dc3545; }
        .alert {
            padding: 15px;
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
            margin-bottom: 20px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-left">
            <div class="logo">
                <img src="UMPLogo.png" alt="UMP Logo">
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
            <li><a href="SecurityStaffDashboard.php" class="menutext">Dashboard</a></li>
            <li><a href="VehicleApproval.php" class="menutext active">Vehicle Approval</a></li>
            <li><a href="RecordSummon.php" class="menutext">Record Summon</a></li>
            <li><a href="ManageSummon.php" class="menutext">Manage Summon</a></li>
        </ul>
    </nav>

    <div class="maincontent">
        <div class="content">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2>Registration Details</h2>
                <a href="VehicleApproval.php" style="color: #eb9d43ff; text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>

            <?php if ($message != ""): ?>
                <div class="alert"><?php echo $message; ?></div>
            <?php endif; ?>

            <div class="detail-grid">
                <div class="detail-item">
                    <label>Registration ID</label>
                    <span><?php echo $vehicle['VehicleID']; ?></span>
                </div>
                <div class="detail-item">
                    <label>Current Status</label>
                    <span class="status-badge <?php echo $vehicle['VehicleStatus']; ?>">
                        <?php echo $vehicle['VehicleStatus']; ?>
                    </span>
                </div>
                <div class="detail-item">
                    <label>Student ID</label>
                    <span><?php echo $vehicle['StudentID']; ?></span>
                </div>
                <div class="detail-item">
                    <label>Student Name</label>
                    <span><?php echo $vehicle['StudentName'] ? $vehicle['StudentName'] : 'N/A'; ?></span>
                </div>
                <div class="detail-item">
                    <label>Vehicle Type</label>
                    <span><?php echo $vehicle['VehicleType']; ?></span>
                </div>
                <div class="detail-item">
                    <label>Plate Number</label>
                    <span><?php echo $vehicle['PlateNumber']; ?></span>
                </div>
                <div class="detail-item">
                    <label>Vehicle Model</label>
                    <span><?php echo $vehicle['VehicleModel']; ?></span>
                </div>
                <div class="detail-item">
                    <label>Vehicle Colour</label>
                    <span><?php echo $vehicle['VehicleColour']; ?></span>
                </div>
            </div>

            <div class="grant-section">
                <label>Vehicle Grant</label>
                <?php if ($imageData): ?>
                    <img src="<?php echo $imageData; ?>" alt="Vehicle Grant" class="grant-photo">
                <?php else: ?>
                    <div class="no-photo">
                        <i class="fas fa-image fa-3x"></i><br><br>
                        No document found in the database.
                    </div>
                <?php endif; ?>
            </div>

            <div class="action-container">
                <form action="ReviewVehicle.php?id=<?php echo $vehicleID; ?>" method="POST">
                    <label for="remark">Notes/Reason:</label>
                    <textarea name="remark" id="remark" placeholder="Enter review notes.."></textarea>
                    
                    <div class="btn-group">
                        <button type="submit" name="action" value="approve" class="btn btn-approve" onclick="return confirm('Confirm Approval?');">Approve</button>
                        <button type="submit" name="action" value="reject" class="btn btn-reject" onclick="return confirm('Confirm Rejection?');">Reject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer>
        <center><p>© 2025 FKPark System</p></center>
    </footer>

    <script src="SecurityDashboard.js"></script>
</body>
</html>