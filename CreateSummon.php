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

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Get Plate Number 
    $plateNumber = $_POST['plateNumber'];
    $violationID = $_POST['violationID'];
    $summonDate = $_POST['summonDate'];
    $summonTime = $_POST['summonTime'];
    $summonDesc = $_POST['summonDesc'];
    $fineAmount = $_POST['fineAmount'];

    // 2. Lookup Student ID associated with the Plate Number
    $checkVehicle = $conn->query("SELECT StudentID FROM Vehicle WHERE PlateNumber = '$plateNumber'");
    
    if ($checkVehicle->num_rows == 0) {
        $error = "Vehicle Plate Number '$plateNumber' not found in the system.";
    } else {
        // Retrieve the StudentID found
        $vehicleRow = $checkVehicle->fetch_assoc();
        $studentID = $vehicleRow['StudentID'];

        // Get points based on violation
        $vResult = $conn->query("SELECT ViolationPoint FROM Violation WHERE ViolationID = $violationID");
        $vRow = $vResult->fetch_assoc();
        $pointsToAdd = intval($vRow['ViolationPoint']);
        
        // Check current points for the student from StudentMerit
        $mResult = $conn->query("SELECT * FROM StudentMerit WHERE StudentID = '$studentID'");
        $currentDemerit = 0; 
        
        if ($mResult->num_rows > 0) {
            $mRow = $mResult->fetch_assoc();
            $currentDemerit = intval($mRow['DemeritPoint']);
        }

        // Calculate new total
        $newDemerit = $currentDemerit + $pointsToAdd;
        $enforcementStatus = "None";
        if ($newDemerit < 20) {
            $enforcementStatus = "Warning given";
        } elseif ($newDemerit < 50) {
            $enforcementStatus = "Revoke of in campus vehicle permission for 1 semester";
        } elseif ($newDemerit < 80) {
            $enforcementStatus = "Revoke of in campus vehicle permission for 2 semesters";
        } else {
            $enforcementStatus = "Revoke of in campus vehicle permission for the entire study duration";
        }

        // 3. Insert Placeholder QRCode
        $insertQR = "INSERT INTO QRCode (Image_URL, QR_Description) VALUES ('Placeholder', 'Pending')";
        if ($conn->query($insertQR) === TRUE) {
            $newQRCodeID = $conn->insert_id;
            
            // 4. Insert Summon (Still links to StudentID in database)
            $insertSummon = "INSERT INTO TrafficSummon (StudentID, ViolationID, QRCodeID, SummonDescription, SummonDate, SummonTime, FineAmount, DemeritPointSnapshot, EnforcementStatusSnapshot) 
                             VALUES ('$studentID', $violationID, $newQRCodeID, '$summonDesc', '$summonDate', '$summonTime', '$fineAmount', '$newDemerit', '$enforcementStatus')";
            
            if ($conn->query($insertSummon) === TRUE) {
                $newSummonID = $conn->insert_id;

                // 5. Update QR Code details
                $targetUrl = "http://localhost/fkparksystem/StudentViewSummon.php?summonID=" . $newSummonID;
                $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($targetUrl);
                $qrDesc = "QR code for Summon " . $newSummonID;
                
                $updateQR = "UPDATE QRCode SET Image_URL = '$qrUrl', QR_Description = '$qrDesc' WHERE QRCodeID = $newQRCodeID";
                $conn->query($updateQR);

                // 6. Update StudentMerit (Live Table)
                if ($mResult->num_rows > 0) {
                    $updateMerit = "UPDATE StudentMerit 
                                    SET DemeritPoint = $newDemerit, 
                                        EnforcementStatus = '$enforcementStatus', 
                                        Date = '$summonDate' 
                                    WHERE StudentID = '$studentID'";
                    $conn->query($updateMerit);
                } else {
                    $insertMerit = "INSERT INTO StudentMerit (StudentID, DemeritPoint, EnforcementStatus, Date) 
                                    VALUES ('$studentID', $newDemerit, '$enforcementStatus', '$summonDate')";
                    $conn->query($insertMerit);
                }

                $message = "Summon ID $newSummonID created successfully for vehicle $plateNumber ($studentID).";
            } else {
                $error = "Error creating Summon: " . $conn->error;
            }
        } else {
            $error = "Error creating QR Code: " . $conn->error;
        }
    }
}

$violations = $conn->query("SELECT * FROM Violation");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Traffic Summon</title>
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
            <a href="TrafficSummon.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Summon List</a>
            <center><h2>Issue New Traffic Summon</h2></center>

            <div class="form-container">
                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>

                <form action="CreateSummon.php" method="POST">
                    <!-- Changed from Student ID to Vehicle Plate Number -->
                    <div class="form-group">
                        <label for="plateNumber">Vehicle Plate Number</label>
                        <input type="text" id="plateNumber" name="plateNumber" placeholder="ABC1234" required>
                    </div>

                    <div class="form-group">
                        <label for="violationID">Violation Type</label>
                        <select id="violationID" name="violationID" required>
                            <option value="">-- Select Violation --</option>
                            <?php while($v = $violations->fetch_assoc()): ?>
                                <option value="<?php echo $v['ViolationID']; ?>">
                                    <?php echo $v['ViolationType'] . " (" . $v['ViolationPoint'] . " Points)"; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fineAmount">Fine Amount (RM)</label>
                        <input type="number" id="fineAmount" name="fineAmount" step="0.01" placeholder="50.00" required>
                    </div>

                    <div class="form-group">
                        <label for="summonDate">Date</label>
                        <input type="date" id="summonDate" name="summonDate" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="summonTime">Time</label>
                        <input type="time" id="summonTime" name="summonTime" value="<?php echo date('H:i'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="summonDesc">Description</label>
                        <textarea id="summonDesc" name="summonDesc" placeholder="Enter violation details..." required></textarea>
                    </div>

                    <button type="submit" class="btn-submit">Issue Summon</button>
                </form>
            </div>
        </div>
    </div>

    <footer>
        <center><p> © 2025 FKPark System</p></center>
    </footer>

    <script src="SecurityDashboard.js"></script>
</body>
</html>