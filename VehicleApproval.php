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

$search = "";
if (isset($_GET['search'])) {
    $search = $_GET['search'];
}

$sql = "SELECT * FROM Vehicle";

if ($search != "") {
    $sql = "SELECT * FROM Vehicle WHERE StudentID LIKE '%$search%' OR PlateNumber LIKE '%$search%'";
}

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vehicle Approval</title>
    <meta name="description" content="Vehicle Approval Page">
    <meta name="author" content="Group1A3">
    <link rel="stylesheet" href="SecurityDashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .review {
            background-color: #eb9d43ff;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: background 0.3s;
        }

        .review:hover {
            background-color: #6d4e2aff;
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
            <a href="SecurityStaffProfile.php" class="profile">
                <i class="fas fa-user-circle"></i> My Profile
            </a>
            <a href="logout.php" class="logoutbutton" id="logoutBtn" onclick="return confirm('Are you sure you want to log out?');">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>
    
    <nav class="sidebar">
        <h1 class="sidebartitle">Security Staff Bar</h1>
        <ul class="menu">
            <li>
                <a href="SecurityStaffDashboard.php" class="menutext">Dashboard</a>
            </li>
            <li>
                <a href="VehicleApproval.php" class="menutext active">Vehicle Approval</a>
            </li>
            <li>
                <a href="RecordSummon.php" class="menutext">Record Summon</a>
            </li>
            <li>
                <a href="ManageSummon.php" class="menutext">Manage Summon</a>
            </li>
        </ul>
    </nav>

    <div class="maincontent">
        <div class="content">
            <center><h2>Vehicle Approval</h2></center>
            
            <form action="VehicleApproval.php" method="get" class="searchbar">
                <input type="text" name="search" placeholder="Search by ID or Plate.." value="<?php echo $search; ?>">
                <button type="submit">Search</button>
            </form>

        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Vehicle ID</th>
                        <th>Student ID</th>
                        <th>Type</th>
                        <th>Plate No.</th>
                        <th>Model</th>
                        <th>Colour</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            ?>
                            <tr>
                                <td><?php echo $row["VehicleID"]; ?></td>
                                <td><?php echo $row["StudentID"]; ?></td>
                                <td><?php echo $row["VehicleType"]; ?></td>
                                <td><?php echo $row["PlateNumber"]; ?></td>
                                <td><?php echo $row["VehicleModel"]; ?></td>
                                <td><?php echo $row["VehicleColour"]; ?></td>
                                <td><?php echo $row["VehicleStatus"]; ?></td>
                                <td>
                                    <a href="ReviewVehicle.php?id=<?php echo $row['VehicleID']; ?>" class="review">Review</a>
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