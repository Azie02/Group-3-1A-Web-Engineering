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

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $studentID = $_POST['studentID'];
    $violationID = $_POST['violationID'];
    $summonDate = $_POST['summonDate'];
    $summonTime = $_POST['summonTime'];
    $summonDesc = $_POST['summonDesc'];
    $fineAmount = $_POST['fineAmount'];

    // 1. Verify Student Exists
    $checkStudent = $conn->query("SELECT * FROM Student WHERE StudentID = '$studentID'");
    if ($checkStudent->num_rows == 0) {
        $error = "Student ID not found.";
    } else {
        $conn->begin_transaction();

        try {
            // 2. Insert Placeholder QRCode
            $insertQR = "INSERT INTO QRCode (Image_URL, QR_Description) VALUES ('Placeholder', 'Pending')";
            if (!$conn->query($insertQR)) {
                throw new Exception("Error creating QR Code: " . $conn->error);
            }
            $newQRCodeID = $conn->insert_id;

            $insertSummon = "INSERT INTO TrafficSummon (StudentID, ViolationID, QRCodeID, SummonDescription, SummonDate, SummonTime, FineAmount) 
                             VALUES ('$studentID', $violationID, $newQRCodeID, '$summonDesc', '$summonDate', '$summonTime', '$fineAmount')";
            
            if (!$conn->query($insertSummon)) {
                throw new Exception("Error creating Summon: " . $conn->error);
            }
            $newSummonID = $conn->insert_id;

            // 4. Update QR Code details
            $qrUrl = "Https.qr.summon." . $newSummonID . ".com";
            $qrDesc = "QR code for Summon " . $newSummonID;
            $updateQR = "UPDATE QRCode SET Image_URL = '$qrUrl', QR_Description = '$qrDesc' WHERE QRCodeID = $newQRCodeID";
            $conn->query($updateQR);

            // 5. Update Student Demerit Points
            
            // Get points for this specific violation
            $vResult = $conn->query("SELECT DemeritPoint FROM Violation WHERE ViolationID = $violationID");
            $vRow = $vResult->fetch_assoc();
            $pointsToAdd = intval($vRow['DemeritPoint']);

            // Check current points for the student
            $mResult = $conn->query("SELECT * FROM StudentMerit WHERE StudentID = '$studentID'");
            
            $currentDemerit = 0; // Default start from 0
            
            if ($mResult->num_rows > 0) {
                $mRow = $mResult->fetch_assoc();
                $currentDemerit = intval($mRow['DemeritPoint']);
            }

            // Calculate new total
            $newDemerit = $currentDemerit + $pointsToAdd;

            // Determine Enforcement Status based on accumulated points
            $enforcementStatus = "None";
            
            if ($newDemerit == 0) {
                 $enforcementStatus = "None";
            } elseif ($newDemerit < 20) {
                $enforcementStatus = "Warning given";
            } elseif ($newDemerit < 50) {
                $enforcementStatus = "Revoke of in campus vehicle permission for 1 semester";
            } elseif ($newDemerit < 80) {
                $enforcementStatus = "Revoke of in campus vehicle permission for 2 semesters";
            } else {
                $enforcementStatus = "Revoke of in campus vehicle permission for the entire study duration";
            }

            // Update or Insert into StudentMerit
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

            // Commit Transaction
            $conn->commit();
            $message = "Summon ID $newSummonID created successfully. Total Demerit Points: $newDemerit ($enforcementStatus)";

        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to create summon: " . $e->getMessage();
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
    <style>
        .form-container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }

        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            box-sizing: border-box; 
        }

        .form-group textarea {
            resize: vertical;
            height: 100px;
        }

        .btn-submit {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 1rem;
            font-weight: bold;
            transition: background 0.3s;
        }

        .btn-submit:hover {
            background-color: #218838;
        }

        .back-link {
            display: block;
            margin-bottom: 20px;
            color: #666;
            text-decoration: none;
        }

        .back-link:hover {
            color: #333;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
            <a href="SecurityStaffProfile.php" class="profile"></i> My Profile</a>
            <a href="logout.php" class="logoutbutton" id="logoutBtn" onclick="return confirm('Are you sure you want to log out?');"></i> Logout
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
                    <div class="form-group">
                        <label for="studentID">Student ID</label>
                        <input type="text" id="studentID" name="studentID" placeholder="e.g. CB23067" required>
                    </div>

                    <div class="form-group">
                        <label for="violationID">Violation Type</label>
                        <select id="violationID" name="violationID" required>
                            <option value="">-- Select Violation --</option>
                            <?php while($v = $violations->fetch_assoc()): ?>
                                <option value="<?php echo $v['ViolationID']; ?>">
                                    <?php echo $v['ViolationType'] . " (" . $v['DemeritPoint'] . " Points)"; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fineAmount">Fine Amount (RM)</label>
                        <input type="number" id="fineAmount" name="fineAmount" step="0.01" placeholder="e.g. 50.00" required>
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