<?php
session_start();

/* ================= SESSION PROTECTION ================= */
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'student') {
    header("Location: Login.php");
    exit();
}

// DB connection
$conn = new mysqli("localhost", "root", "", "fkparksystem");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$studentID = $_SESSION['user_id'];

/* ================= FETCH LATEST MERIT ================= */
$stmt = $conn->prepare("
    SELECT MeritPoint, DemeritPoint, Date
    FROM StudentMerit
    WHERE StudentID = ?
    ORDER BY Date DESC
    LIMIT 1
");
$stmt->bind_param("s", $studentID);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

$merit     = $data['MeritPoint'] ?? 0;
$demerit   = $data['DemeritPoint'] ?? 0;
$total     = $merit - $demerit;
$date      = $data['Date'] ?? "-";

/* ================= ENFORCEMENT LOGIC (TABLE A) ================= */
if ($total < 20) {
    $status = "Warning Given";
} elseif ($total < 50) {
    $status = "Vehicle Permission Revoked (1 Semester)";
} elseif ($total < 80) {
    $status = "Vehicle Permission Revoked (2 Semesters)";
} else {
    $status = "Vehicle Permission Revoked (Entire Study)";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Merit Status</title>
    <meta name="description" content="Merit Status">
    <meta name="author" content="Group1A3">
    <style>
        body { font-family: Roboto, sans-serif; background:#f5f5f5; margin:0; }
        .maincontent { margin-left:250px; margin-top:120px; padding:40px; }
        .box {
            background:white;
            padding:30px;
            border-radius:10px;
            box-shadow:0 2px 10px rgba(0,0,0,0.08);
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
                background-color: #008080;
                width: 250px;
                color: white;
                position: fixed;
                top: 120px;
                left: 0;
                bottom: 0;
                padding: 20px 0;
                box-sizing: border-box;
                transition: transform 0.3s ease;
            }

            .sidebartitle{
                color: white;
                font-size: 1rem;
                margin-bottom: 20px;
                padding: 0 20px;
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
        table {
            width:100%;
            border-collapse:collapse;
            margin-top:20px;
        }
        th, td {
            padding:14px;
            border-bottom:1px solid #ddd;
            text-align:center;
        }
        th { background:#008080; color:white; }
        .status {
            margin-top:20px;
            padding:15px;
            border-radius:6px;
            background:#fff3cd;
            font-weight:600;
        }
                    .content {
              background-color: white;
              padding: 25px;
              border-radius: 8px;
              margin-bottom: 25px;
              box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            }

            .searchbar { 
                display: flex; 
                gap: 10px; 
                margin-top: 20px; 
            }

            .searchbar input {
                padding:10px 20px;
                border: 1px solid #ccc;
                border-radius: 5px;
                font-size: 1em;
                flex: 1;
            }

            .searchbar button {
                background: #229ee6;
                color: white;
                border: none;
                border-radius: 5px;
                padding: 10px 18px;
                cursor: pointer;
            }

            .search-results {
                margin-top: 20px;
                background: #fff5e9;
                border-radius: 7px;
                padding: 18px 22px;
                box-shadow: 0 2px 9px rgba(255,170,60,0.08);
            }

            .seccontent {
                background-color: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            }

            /* Cards */
            .cards {
                display: flex;
                gap: 20px;
                margin-bottom: 30px;
                align-items: center;
                text-align: center;
                background: #ffffffff;
                color: #130358ff;
                border-radius: 10px;
                padding: 0.8em 0.7em 0.8em 0.7em;
                min-width: 140px;
                min-height: 74px;
                box-shadow: 0 2px 9px rgba(0, 0, 0, 0.09);
                flex: 1 1 160px;
            }

            .card {
                background: #b2e9e9ff;
                padding: 50px;
                border: 1px solid #ccc;
                width: 180px;
                text-align: center;
                border-radius: 5px;
                font-weight: bold;
            }

            /* Charts */
            .charts {
               display: flex;
               gap: 20px;
            }

            .chart {
              flex: 1;
              background: white;
              padding: 30px;
              border: 1px solid #ccc;
              height: 200px;
              text-align: center;
              border-radius: 5px;
              font-weight: bold;
            }

            footer {
               background-color: #80cab1ff;
               color: white;
               padding: 15px 0;
            }
    </style>
    
</head>
<script>
    let timeout = 60;          // must match PHP
    let warningTime = 10;      // show warning 10s before timeout
    let countdown;
    
    function startTimer() {
        clearTimeout(countdown);
        
        countdown = setTimeout(() => {
            let stay = confirm(
                "Your session will expire soon.\n\nClick OK to continue or Cancel to logout."
            );
            
            if (stay) {
                // Ping server to refresh session
                    fetch("keep_alive.php")
                    .then(() => {
                        startTimer(); // restart timer
                });
        } else {
            window.location.href = "Logout.php";
        }

    }, (timeout - warningTime) * 1000);
}

// Restart timer on user activity
["click", "mousemove", "keypress"].forEach(event => {
    document.addEventListener(event, startTimer);
});

// Start timer on page load
startTimer();
</script>
<body>
    <header class="header">
        <div class="header-left">
            <div class="logo">
                <img src ="UMPLogo.png" alt="UMPLogo">
            </div>
            
            <button class="togglebutton" id="sidebarToggle">
                <i class="fas fa-bars"></i>Menu
            </button>
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
                    <i class="fas fa-users"></i> Vehicle Registration
                </a>
            </li>
            <li>
                <a href="Booking.php" class="menutext">
                    <i class="fas fa-parking"></i> Book Parking
                </a>
            </li>
            <li>
                <a href="MeritStatus.php" class="menutext active">
                    <i class="fas fa-chart-line"></i> Merit Status
                </a>
        </ul>
    </nav>

<div class="maincontent">

    <div class="box">
        <h2>My Merit Status</h2>

        <table>
            <tr>
                <th>Merit Point</th>
                <th>Demerit Point</th>
                <th>Total Point</th>
                <th>Last Updated</th>
            </tr>
            <tr>
                <td><?= $merit ?></td>
                <td><?= $demerit ?></td>
                <td><?= $total ?></td>
                <td><?= $date ?></td>
            </tr>
        </table>

                <div class="status">
                    Enforcement Status: <strong><?= $status ?></strong>
                </div>
            </div>
        </div>
        <footer>
            <center><p> © 2025 FKPark System</p></center>
        </footer>
    </body>
</html>
