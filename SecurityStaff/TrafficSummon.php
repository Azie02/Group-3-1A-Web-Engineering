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

if (isset($_GET['delete_id'])) {
    $deleteID = $_GET['delete_id'];
    //Delete with Qrcode of traffic summon
    $qrSql = "SELECT QRCodeID FROM TrafficSummon WHERE SummonID = '$deleteID'";
    $qrResult = $conn->query($qrSql);

    if ($qrResult->num_rows > 0) {
        $qrRow = $qrResult->fetch_assoc();
        $qrID = $qrRow['QRCodeID'];

        $deleteSql = "DELETE FROM TrafficSummon WHERE SummonID = '$deleteID'";
        if ($conn->query($deleteSql) === TRUE) {

            $conn->query("DELETE FROM QRCode WHERE QRCodeID = '$qrID'");
            
            echo "<script>alert('Summon deleted successfully.'); window.location.href='TrafficSummon.php';</script>";
        } else {
            echo "<script>alert('Error deleting summon: " . $conn->error . "');</script>";
        }
    } else {
         $deleteSql = "DELETE FROM TrafficSummon WHERE SummonID = '$deleteID'";
         if ($conn->query($deleteSql) === TRUE) {
            echo "<script>alert('Summon deleted successfully.'); window.location.href='TrafficSummon.php';</script>";
         } else {
            echo "<script>alert('Error deleting summon: " . $conn->error . "');</script>";
         }
    }
}

$search = "";
if (isset($_GET['search'])) {
    $search = $_GET['search'];
}

$sql = "SELECT ts.*, v.ViolationType, veh.PlateNumber
        FROM TrafficSummon ts 
        LEFT JOIN Violation v ON ts.ViolationID = v.ViolationID
        LEFT JOIN Vehicle veh ON ts.StudentID = veh.StudentID";

if ($search != "") {
    $sql .= " WHERE ts.StudentID LIKE '%$search%' OR ts.SummonID LIKE '%$search%'";
}


$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Traffic Summon Management</title>
    <meta name="description" content="Traffic Summon Page">
    <meta name="author" content="Group1A3">
    <link rel="stylesheet" href="SecurityDashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .view-btn {
            background-color: #eb9d43ff;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: background 0.3s;
        }

        .view-btn:hover {
            background-color: #6d4e2aff;
        }

        .delete-btn {
            background-color: #dc3545;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: background 0.3s;
            margin-left: 8px;
        }

        .delete-btn:hover {
            background-color: #c82333;
        }

        .header-wrapper {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 20px;
            padding: 0 10px;
        }

        .header-wrapper h2 {
            margin: 0;
        }

        .add-btn {
            position: absolute;
            right: 0;
            background-color: #28a745;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.3s;
            font-size: 0.9rem;
        }

        .add-btn:hover {
            background-color: #218838;
        }

        .searchbar {
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="header-left">
            <div class="logo">
                <img src="../UMPLogo.png" alt="UMPLogo">
            </div>
        </div>
        <div class="header-right">
            <a href="SecurityStaffProfile.php" class="profile"></i>My Profile</a>
            <a href="logout.php" class="logoutbutton" id="logoutBtn" onclick="return confirm('Are you sure you want to log out?');"></i>Logout</a>
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
            <div class="header-wrapper">
                <h2>Traffic Summon List</h2>
                <a href="CreateSummon.php" class="add-btn">
                    <i class="fas fa-plus"></i> Create New Summon
                </a>
            </div>
            
            <form action="TrafficSummon.php" method="get" class="searchbar">
                <input type="text" name="search" placeholder="Search by Summon ID or Student ID.." value="<?php echo $search; ?>">
                <button type="submit">Search</button>
            </form>

        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Summon ID</th>
                        <th>Student ID</th>
                        <th>Plate No.</th>
                        <th>Violation Type</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Fine (RM)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            ?>
                            <tr>
                                <td><?php echo $row["SummonID"]; ?></td>
                                <td><?php echo $row["StudentID"]; ?></td>
                                <td><?php echo $row["PlateNumber"]; ?></td>
                                <td><?php echo $row["ViolationType"]; ?></td>
                                <td><?php echo $row["SummonDate"]; ?></td>
                                <td><?php echo $row["SummonTime"]; ?></td>
                                <td><?php echo $row["FineAmount"]; ?></td>
                                <td>
                                    <a href="ViewSummon.php?id=<?php echo $row['SummonID']; ?>" class="view-btn">View</a>
                                    <a href="TrafficSummon.php?delete_id=<?php echo $row['SummonID']; ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this summon?');">Delete</a>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo "<tr><td colspan='8' style='text-align:center;'>0 results found</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <footer>
        <center><p> © 2025 FKPark System</p></center>
    </footer>

    <script src="SecurityDashboard.js"></script>
</body>
</html>