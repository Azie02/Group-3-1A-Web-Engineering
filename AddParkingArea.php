<?php
session_start();
$conn = new mysqli("localhost", "root", "", "FKParkSystem", 3307);

// Security check (UNCHANGED)
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'Administrator') {
    header("Location: Login.php");
    exit();
}

// Inactivity timeout (similar to your other pages)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 60) {
    session_unset();
    session_destroy();
    header("Location: Login.php");
    exit();
}
$_SESSION['last_activity'] = time();

$error = '';
$success = '';

if (isset($_POST['submit'])) {
    // Your original inputs (UNCHANGED)
    $areaNumber = trim($_POST['areaNumber']);
    $areaType   = $_POST['areaType'];
    $spaces     = $_POST['totalSpaces'];

    // Validation
    if (empty($areaNumber) || empty($areaType) || empty($spaces)) {
        $error = "All fields are required.";
    } else {
        /* ===== AUTO ParkingAreaID (PA01, PA02, ...) ===== */
        $result = $conn->query(
            "SELECT ParkingAreaID 
             FROM parkingarea 
             ORDER BY ParkingAreaID DESC 
             LIMIT 1"
        );

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $lastID = $row['ParkingAreaID'];   
            $number = intval(substr($lastID, 2)); 
            $number++;
        } else {
            $number = 1;
        }

        $parkingAreaID = "PA" . str_pad($number, 2, "0", STR_PAD_LEFT);
        /* ===== END AUTO ID ===== */

        // Check if AreaNumber already exists
        $checkStmt = $conn->prepare("SELECT 1 FROM parkingarea WHERE AreaNumber = ?");
        $checkStmt->bind_param("s", $areaNumber);
        $checkStmt->execute();
        
        if ($checkStmt->get_result()->num_rows > 0) {
            $error = "Area Number already exists!";
        } else {
            // Your original prepared statement (MINIMALLY UPDATED)
            $stmt = $conn->prepare(
                "INSERT INTO parkingarea (ParkingAreaID, AreaNumber, AreaType, TotalSpaces)
                 VALUES (?, ?, ?, ?)"
            );

            $stmt->bind_param("sssi", $parkingAreaID, $areaNumber, $areaType, $spaces);
            
            if ($stmt->execute()) {
                $success = "Parking Area $areaNumber added successfully!";
                // Clear form on success
                $areaNumber = $spaces = '';
            } else {
                $error = "Failed to add parking area: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Parking Area</title>
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

        .container {
            margin-left: 250px;
            margin-top: 140px;
            padding: 30px;
        }

        .form-box {
            background: white;
            padding: 30px;
            width: 520px;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }

        h2 {
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid #0066cc;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            font-weight: bold;
            display: block;
            margin-bottom: 8px;
            color: #444;
        }

        input[type="text"],
        input[type="number"],
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        input[type="text"]:focus,
        input[type="number"]:focus,
        select:focus {
            border-color: #0066cc;
            outline: none;
            box-shadow: 0 0 5px rgba(0,102,204,0.2);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-add {
            background: #0066cc;
            color: white;
        }

        .btn-add:hover {
            background: #0052a3;
        }

        .btn-back {
            background: #aaa;
            color: black;
            margin-left: 10px;
        }

        .btn-back:hover {
            background: #999;
        }

        .button-group {
            margin-top: 25px;
        }

        .error {
            color: #d32f2f;
            background: #ffebee;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            border-left: 4px solid #d32f2f;
        }

        .success {
            color: #2e7d32;
            background: #e8f5e9;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            border-left: 4px solid #2e7d32;
        }

        .form-note {
            color: #666;
            font-size: 12px;
            margin-top: 5px;
            font-style: italic;
        }

        /* Sidebar adjustment */
        .sidebar {
            background-color: #d890d8ff;
            width: 250px;
            color: black;
            position: fixed;
            top: 120px;
            left: 0;
            bottom: 0;
            padding: 20px 0;
            box-sizing: border-box;
        }

        .header {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            height: 120px;
        }
    </style>
</head>
<body>
    <!-- Include your header and sidebar if needed -->
    <!-- For consistency, you might want to include them like in ParkingSpaces.php -->
    
    <div class="container">
        <div class="form-box">
            <h2>Add New Parking Area</h2>
            
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="areaNumber">Area Number *</label>
                    <input type="text" 
                           id="areaNumber" 
                           name="areaNumber" 
                           value="<?= isset($areaNumber) ? htmlspecialchars($areaNumber) : '' ?>" 
                           placeholder="e.g., A1, B2"
                           required>
                    <div class="form-note">Unique identifier for the parking area</div>
                </div>

                <div class="form-group">
                    <label for="areaType">Area Type *</label>
                    <select id="areaType" name="areaType" required>
                        <option value="">-- Select Type --</option>
                        <option value="Staff" <?= (isset($areaType) && $areaType === 'Staff') ? 'selected' : '' ?>>Staff</option>
                        <option value="Student" <?= (isset($areaType) && $areaType === 'Student') ? 'selected' : '' ?>>Student</option>
                    </select>
                    <div class="form-note">Designated user type for this parking area</div>
                </div>

                <div class="form-group">
                    <label for="totalSpaces">Total Spaces *</label>
                    <input type="number" 
                           id="totalSpaces" 
                           name="totalSpaces" 
                           value="<?= isset($spaces) ? htmlspecialchars($spaces) : '' ?>" 
                           min="1" 
                           max="1000"
                           required>
                    <div class="form-note">Maximum number of parking spaces in this area</div>
                </div>

                <div class="button-group">
                    <button type="submit" name="submit" class="btn btn-add">Add Parking Area</button>
                    <a href="ParkingManagement.php" class="btn btn-back">Back to Management</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>