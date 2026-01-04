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

if (!isset($_GET['id'])) {
    echo "No Summon ID specified.";
    exit();
}

$summonID = $_GET['id'];

$sql = "SELECT 
            ts.SummonID, 
            ts.SummonDate, 
            ts.SummonTime, 
            ts.SummonDescription, 
            ts.FineAmount, 
            ts.QRCodeID,
            ts.DemeritPointSnapshot,
            ts.EnforcementStatusSnapshot,
            s.StudentID, 
            s.StudentName, 
            s.StudentContact,
            veh.PlateNumber,
            v.ViolationType, 
            v.ViolationPoint as ViolationPoints,
            q.Image_URL
        FROM TrafficSummon ts
        JOIN Student s ON ts.StudentID = s.StudentID
        LEFT JOIN Vehicle veh ON ts.StudentID = veh.StudentID
        JOIN Violation v ON ts.ViolationID = v.ViolationID
        LEFT JOIN QRCode q ON ts.QRCodeID = q.QRCodeID
        WHERE ts.SummonID = '$summonID'
        LIMIT 1";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
} else {
    echo "Summon not found.";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Summon Details</title>
    <link rel="stylesheet" href="SecurityDashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <header class="header">
        <div class="header-left">
            <div class="logo">
                <img src="../UMPLogo.png" alt="UMPLogo">
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
            <li><a href="VehicleApproval.php" class="menutext">Vehicle Approval</a></li>
            <li><a href="TrafficSummon.php" class="menutext active">Trafic Summon</a></li>
        </ul>
    </nav>

    <div class="maincontent">
        <div class="content">
            <a href="TrafficSummon.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to List</a>
            
            <div class="details-container">
                <div class="header-section">
                    <div>
                        <h2>Traffic Summon Receipt</h2>
                        <span style="color: #666; font-size: 0.9rem;">Summon ID: <?php echo $row['SummonID']; ?></span>
                    </div>
                    <button onclick="window.print()" class="btn-print"><i class="fas fa-print"></i> Print</button>
                </div>

                <div class="info-grid">
                    <div>
                        <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 15px;">Student Details</h3>
                        <div class="info-group">
                            <label>Student Name</label>
                            <div class="value"><?php echo $row['StudentName']; ?></div>
                        </div>
                        <div class="info-group">
                            <label>Student ID</label>
                            <div class="value"><?php echo $row['StudentID']; ?></div>
                        </div>
                        <div class="info-group">
                            <label>Vehicle Plate No.</label>
                            <div class="value"><?php echo $row['PlateNumber'] ? $row['PlateNumber'] : 'N/A'; ?></div>
                        </div>
                        <div class="info-group">
                            <label>Total Demerit Points</label>
                            <div class="value"><?php echo $row['DemeritPointSnapshot'] !== null ? $row['DemeritPointSnapshot'] : 'N/A (Legacy Record)'; ?></div>
                        </div>
                        <div class="info-group">
                            <label>Enforcement Status</label>
                            <div class="value" style="color: #e67e22; font-weight: 500;">
                                <?php echo $row['EnforcementStatusSnapshot'] ? $row['EnforcementStatusSnapshot'] : 'N/A (Legacy Record)'; ?>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 15px;">Violation Details</h3>
                        <div class="info-group">
                            <label>Violation Type</label>
                            <div class="value"><?php echo $row['ViolationType']; ?></div>
                        </div>
                        <div class="info-group">
                            <label>Demerit Points</label>
                            <div class="value" style="color: #dc3545; font-weight: bold;">
                                <?php echo $row['ViolationPoints']; ?> Points
                            </div>
                        </div>
                        <div class="info-group">
                            <label>Date & Time</label>
                            <div class="value"><?php echo $row['SummonDate'] . ' ' . $row['SummonTime']; ?></div>
                        </div>
                        <div class="info-group">
                            <label>Description</label>
                            <div class="value"><?php echo $row['SummonDescription']; ?></div>
                        </div>
                        <div class="info-group">
                            <label>Fine Amount</label>
                            <div class="value" style="font-size: 1.3rem; color: #dc3545;">RM <?php echo number_format($row['FineAmount'], 2); ?></div>
                        </div>
                    </div>
                </div>

                <div class="qr-section">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($row['Image_URL']); ?>" 
                         alt="Summon QR Code" 
                         class="qr-image">
                </div>
            </div>
        </div>
    </div>

    <footer>
        <center><p> © 2025 FKPark System</p></center>
    </footer>
</body>
</html>