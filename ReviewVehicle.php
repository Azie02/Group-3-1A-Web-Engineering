<?php
session_start();
$conn = new mysqli("localhost", "root", "", "fkparksystem", 3306);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'SecurityStaff') {
    header("Location: ../Login.php");
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
</head>
<body>
    <header class="header">
        <div class="header-left">
            <div class="logo">
                <img src="../UMPLogo.png" alt="UMP Logo">
            </div>
        </div>
        <div class="header-right">
            <a href="SecurityStaffProfile.php" class="profile">
                <i class="fas fa-user-circle"></i> My Profile
            </a>
            <a href="../logout.php" class="logoutbutton" onclick="return confirm('Are you sure you want to log out?');">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <nav class="sidebar">
        <h1 class="sidebartitle">Security Staff Bar</h1>
        <ul class="menu">
            <li><a href="SecurityStaffDashboard.php" class="menutext">Dashboard</a></li>
            <li><a href="VehicleApproval.php" class="menutext active">Vehicle Approval</a></li>
            <li><a href="TrafficSummon.php" class="menutext">Trafic Summon</a></li>
        </ul>
    </nav>

    <div class="maincontent">
        <div class="content">
            <a href="VehicleApproval.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to List</a>

            <div class="details-container">
                <?php if ($message != ""): ?>
                    <div class="alert <?php echo strpos($message, 'Error') === false ? 'alert-success' : 'alert-error'; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div class="header-section">
                    <div>
                        <h2>Vehicle Registration Details</h2>
                        <span style="color: #666; font-size: 0.9rem;">Ref ID: <?php echo $vehicle['VehicleID']; ?></span>
                    </div>
                    <span class="status-badge <?php echo $vehicle['VehicleStatus']; ?>">
                        <?php echo $vehicle['VehicleStatus']; ?>
                    </span>
                </div>

                <div class="info-grid">
                    <div>
                        <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 15px;">Applicant Details</h3>
                        <div class="info-group">
                            <label>Student Name</label>
                            <div class="value"><?php echo $vehicle['StudentName'] ? $vehicle['StudentName'] : 'N/A'; ?></div>
                        </div>
                        <div class="info-group">
                            <label>Student ID</label>
                            <div class="value"><?php echo $vehicle['StudentID']; ?></div>
                        </div>
                    </div>

                    <div>
                        <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 15px;">Vehicle Details</h3>
                        <div class="info-group">
                            <label>Plate Number</label>
                            <div class="value"><?php echo $vehicle['PlateNumber']; ?></div>
                        </div>
                        <div class="info-group">
                            <label>Vehicle Type</label>
                            <div class="value"><?php echo $vehicle['VehicleType']; ?></div>
                        </div>
                        <div class="info-group">
                            <label>Model & Colour</label>
                            <div class="value"><?php echo $vehicle['VehicleModel'] . " (" . $vehicle['VehicleColour'] . ")"; ?></div>
                        </div>
                    </div>
                </div>

                <div class="grant-section">
                    <h3 style="color: #666; font-size: 1rem; margin-bottom: 15px;">Vehicle Grant Document</h3>
                    <?php if ($imageData): ?>
                        <img src="<?php echo $imageData; ?>" alt="Vehicle Grant" class="grant-photo">
                    <?php else: ?>
                        <div class="no-photo">
                            <i class="fas fa-file-image fa-2x"></i><br>
                            No document image found in database.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="action-container">
                    <form action="ReviewVehicle.php?id=<?php echo $vehicleID; ?>" method="POST">
                        <div class="btn-group">
                            <button type="submit" name="action" value="approve" class="btn btn-approve" onclick="return confirm('Confirm Approval?');">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-reject" onclick="return confirm('Confirm Rejection?');">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <center><p>© 2025 FKPark System</p></center>
    </footer>

    <script src="SecurityDashboard.js"></script>
</body>
</html>