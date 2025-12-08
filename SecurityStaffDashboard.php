<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "mypetakom");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Restrict access to only logged-in admins
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'coordinator') {
    header("Location: Login.php");
    exit();
}

// Get admin data from database
$admin_id = $_SESSION['user_id'];
$query = "SELECT * FROM staff WHERE StaffID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    session_destroy();
    header("Location: Login.php");
    exit();
}
$staff = $result->fetch_assoc();

// --- SINGLE TABLE DATA REPRESENTATION ---
$totalMembership = 0;
$res = $conn->query("SELECT COUNT(*) AS cnt FROM memberapplication");
if ($row = $res->fetch_assoc()) $totalMembership = $row['cnt'];

$totalAttendance = 0;
$res = $conn->query("SELECT COUNT(*) AS cnt FROM attendance");
if ($row = $res->fetch_assoc()) $totalAttendance = $row['cnt'];

// --- MULTIPLE TABLE DATA REPRESENTATION ---
$totalUser = 0;
$res1 = $conn->query("SELECT COUNT(*) AS cnt FROM student");
$res2 = $conn->query("SELECT COUNT(*) AS cnt FROM staff");
$cnt1 = $res1->fetch_assoc()['cnt'] ?? 0;
$cnt2 = $res2->fetch_assoc()['cnt'] ?? 0;
$totalUser = $cnt1 + $cnt2;

$totalAttendanceSlot = 0;
$res1 = $conn->query("SELECT COUNT(*) AS cnt FROM attendance");
$res2 = $conn->query("SELECT COUNT(*) AS cnt FROM attendanceslot");
$cnt1 = $res1->fetch_assoc()['cnt'] ?? 0;
$cnt2 = $res2->fetch_assoc()['cnt'] ?? 0;
$totalAttendanceSlot = $cnt1 + $cnt2;

// --- SEARCH FUNCTIONALITY ---
$searchResults = [];
$searchTerm = "";
if (isset($_GET['search']) && strlen(trim($_GET['search'])) > 0) {
    $searchTerm = trim($_GET['search']);
    // Simple search in student, staff, and event (name/title fields)
    $like = "%".$conn->real_escape_string($searchTerm)."%";
    // Student search
    $sql = "SELECT StudentID AS id, StudentName AS name, StudentEmail AS email, 'Student' AS type FROM student WHERE StudentName LIKE ? OR StudentID LIKE ? OR StudentEmail LIKE ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while($r = $res->fetch_assoc()) $searchResults[] = $r;
    // Staff search
    $sql = "SELECT StaffID AS id, StaffName AS name, StaffEmail AS email, 'Staff' AS type FROM staff WHERE StaffName LIKE ? OR StaffID LIKE ? OR StaffEmail LIKE ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while($r = $res->fetch_assoc()) $searchResults[] = $r;
    // Event search
    $sql = "SELECT EventID AS id, EventTitle AS name, NULL AS email, 'Event' AS type FROM event WHERE EventTitle LIKE ? OR EventID LIKE ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while($r = $res->fetch_assoc()) $searchResults[] = $r;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .header {
            background-color:rgb(222, 116, 24); 
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            position: fixed;
            width: 100%;
            box-sizing: border-box;
            height: 120px;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 0 35px;
        }
        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-right: 20px;
        }
        .Logo {
            display: flex;
            gap: 20px;
            align-items: center;
            padding: 0 60px;
        }
        .Logo img {
            height: 90px; 
            width: auto; 
        }
        .sidebar {
            width: 200px;
            background-color: #d35400; 
            color: white;
            position: fixed;
            top: 120px;
            left: 0;
            bottom: 0;
            padding: 20px 0;
            box-sizing: border-box;
            transition: transform 0.3s ease;
        }
        .sidebar.collapsed { transform: translateX(-200px); }
        .sidebartitle {
            color: white;
            font-size: 1.4rem;
            margin-bottom: 20px;
            padding: 0 20px;
        }
        .menuitems {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 0;
            margin: 0;
            list-style: none;
        }
        .menuitem {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            padding: 14px 18px;
            color: white;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .menuitems a {
            text-decoration: none;
            color: inherit;
        }
        .menuitem:hover {
            background-color: #a04000;
        }
        .menuitem.active {
            background-color: #e67e22;
            font-weight: 500;
        }
        .togglebutton {
            background-color: #e67e22;
            color: white;
            border: 1px solid rgba(230, 126, 34, 0.3);
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .togglebutton:hover {
            background-color: #d35400;
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
        .profilebutton {
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
        .profilebutton:hover {
            background-color: rgba(52, 152, 219, 0.3);
        }
        .maincontent {
            margin-left: 200px;
            margin-top: 120px;
            padding: 40px;
            flex: 1;
            box-sizing: border-box;
            gap: 40px;
            transition: margin-left 0.3s ease;
            justify-content: space-between;
        }
        .maincontent.expanded { margin-left: 0; }
        .content {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }
        .content h1 {
            font-size: 1.5rem;
            margin: 0;
            color: black;
            font-weight: 600;
        }
        .search-bar {
            margin: 16px 0 0 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .search-bar input[type="text"] {
            padding: 7px 14px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1em;
        }
        .search-bar button {
            background: #e67e22;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 1em;
            padding: 7px 18px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .search-bar button:hover { background: #d35400; }
        .search-results {
            margin: 18px 0 0 0;
            background: #fef9f4;
            border-radius: 7px;
            padding: 18px 22px;
            box-shadow: 0 2px 9px rgba(255,170,60,0.08);
        }
        .search-results table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .search-results th, .search-results td {
            padding: 7px 10px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }
        .search-results th {
            background: #fbe6d1;
            font-weight: 600;
        }
        .search-results tr:last-child td {
            border-bottom: none;
        }
        .seccontent {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            margin-top: 30px;
        }
        .stats-row {
            display: flex;
            gap: 18px;
            justify-content: start;
            margin: 0 0 26px 0;
            flex-wrap: wrap;
        }
        .stat-card {
            background: #ffe5c2;
            color: #00b3d3ff;
            border-radius: 10px;
            padding: 0.8em 0.7em 0.8em 0.7em;
            min-width: 140px;
            min-height: 74px;
            text-align: center;
            box-shadow: 0 2px 9px rgba(255,170,60,0.09);
            flex: 1 1 160px;
            margin: 0 4px 0 0;
            max-width: 220px;
        }
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 0.04em;
        }
        .stat-label {
            font-size: 1.0em;
            font-weight: 500;
            margin-top: 2px;
            word-break: break-word;
        }
        .graphs-row {
            display: flex;
            gap: 30px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .graph-card {
            background: #faf9f6;
            border-radius: 7px;
            padding: 25px;
            min-width: 340px;
            min-height: 320px;
            box-shadow: 0 2px 10px rgba(220,130,20,0.07);
            margin-bottom: 15px;
        }
        .footer {
            background-color: #e67e22;
            color: white;
            padding: 15px 0;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-left">
            <button class="togglebutton" id="togglebutton">
                <i class="fas fa-bars"></i>Menu 
            </button>
            <div class="Logo">    
                <img src="image/UMPSALogo.png" alt="LogoUMP">
                <img src="image/PetakomLogo.png" alt="LogoPetakom">
            </div>
        </div>
        <div class="header-right">
            <a href="AdminProfile.php" class="profilebutton">
                <i class="fas fa-user-circle"></i> My Profile
            </a>
            <a href="logout.php" class="logoutbutton" onclick="return confirm('Are you sure you want to log out?');">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <nav class="sidebar" id="sidebar">
        <h2 class="sidebartitle">Admin</h2>
        <ul class="menuitems">
            <li>
                <a href="AdminDashboard.php" class="menuitem active">
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="ManageUser.php" class="menuitem">
                    <span>Manage User</span>
                </a>
            </li>
            <li>
                <a href="MeritApplicationApproval.php" class="menuitem">
                    <span>Merit Application Approval</span>
                </a>
            </li>
            <li>
                <a href="MembershipApproval.php" class="menuitem">
                    <span>Membership Approval</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="maincontent" id="maincontent">
        <div class="content">
            <h1>Welcome to MyPetakom</h1>
            <!-- Search Bar -->
            <form class="search-bar" method="get" action="">
                <input type="text" name="search" placeholder="Search Student, Staff, or Event..." value="<?=htmlspecialchars($searchTerm)?>">
                <button type="submit"><i class="fas fa-search"></i> Search</button>
            </form>
            <?php if(isset($_GET['search'])): ?>
            <div class="search-results">
                <b>Search Results for "<?=htmlspecialchars($searchTerm)?>":</b>
                <?php if(empty($searchResults)): ?>
                    <div style="margin-top:7px;color:#d35400;">No results found.</div>
                <?php else: ?>
                    <table>
                        <tr>
                            <th>Type</th>
                            <th>ID</th>
                            <th>Name/Title</th>
                            <th>Email</th>
                        </tr>
                        <?php foreach($searchResults as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['type']) ?></td>
                            <td><?= htmlspecialchars($r['id']) ?></td>
                            <td><?= htmlspecialchars($r['name']) ?></td>
                            <td><?= htmlspecialchars($r['email'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="seccontent">
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-value"><?= $totalMembership ?></div>
                    <div class="stat-label">Total Membership</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $totalAttendance ?></div>
                    <div class="stat-label">Total Attendance</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $totalUser ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $totalAttendanceSlot ?></div>
                    <div class="stat-label">Total Attendance Slot</div>
                </div>
            </div>
            <div class="graphs-row">
                <div class="graph-card">
                    <canvas id="MembershipStatusChart"></canvas>
                </div>
                <div class="graph-card">
                    <canvas id="RegisteredUserChart"></canvas>
                </div>
                <div class="graph-card">
                    <canvas id="AttendanceChart"></canvas>
                </div>
                <div class="graph-card">
                    <canvas id="MonthlyEventChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleButton = document.getElementById('togglebutton');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('maincontent');
            toggleButton.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                const icon = toggleButton.querySelector('i');
                if (sidebar.classList.contains('collapsed')) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                    toggleButton.innerHTML = '<i class="fas fa-bars"></i> Menu';
                } else {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                    toggleButton.innerHTML = '<i class="fas fa-times"></i> Menu';
                }
            });
        });
    </script>
    <script>
    // Membership by Status (approved/rejected)
    document.addEventListener("DOMContentLoaded", function() {
        fetch('Get_Membership_Status_Data.php')
            .then(response => response.json())
            .then(data => {
                const ctx = document.getElementById('MembershipStatusChart').getContext('2d');
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: ['Approved', 'Rejected'],
                        datasets: [{
                            data: [data.approved, data.rejected],
                            backgroundColor: ['#43a047', '#e53935']
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Membership by Status'
                            }
                        }
                    }
                });
            });
    });

    // Registered Users (Student/Staff)
    document.addEventListener("DOMContentLoaded", function() {
        fetch('Get_Registered_User_Data.php')
            .then(response => response.json())
            .then(data => {
                const ctx = document.getElementById('RegisteredUserChart').getContext('2d');
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Student', 'Staff'],
                        datasets: [{
                            data: [data.student, data.staff],
                            backgroundColor: ['#1976d2', '#ffa726']
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Registered User (Student/Staff)'
                            }
                        }
                    }
                });
            });
    });

    // Attendance submissions per event
    document.addEventListener("DOMContentLoaded", function() {
        fetch('Get_Attendance_Data.php')
            .then(response => response.json())
            .then(data => {
                const ctx = document.getElementById('AttendanceChart').getContext('2d');
                const eventNames = data.map(item => item.EventTitle);
                const attendanceCounts = data.map(item => parseInt(item.AttendanceCount));
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: eventNames,
                        datasets: [{
                            label: 'Attendance Submissions',
                            data: attendanceCounts,
                            backgroundColor: 'rgba(230, 126, 34, 0.7)',
                            borderColor: 'rgba(230, 126, 34, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Attendance Submissions Per Event'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { stepSize: 1 }
                            }
                        }
                    }
                });
            });
    });

    // Events per Month (2025)
    document.addEventListener("DOMContentLoaded", function () {
        fetch('Get_Events_Per_Month.php')
            .then(response => response.json())
            .then(data => {
                const ctx = document.getElementById('MonthlyEventChart').getContext('2d');
                const monthLabels = [
                    'January', 'February', 'March', 'April', 'May', 'June',
                    'July', 'August', 'September', 'October', 'November', 'December'
                ];
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: monthLabels,
                        datasets: [{
                            label: 'Events',
                            data: data,
                            backgroundColor: 'rgba(52, 152, 219, 0.2)',
                            borderColor: 'rgba(41, 128, 185, 1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3,
                            pointBackgroundColor: 'rgba(41, 128, 185, 1)'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Events per Month (2025)'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { stepSize: 1 }
                            }
                        }
                    }
                });
            });
    });
    </script>
    <div class="footer">
        <footer>
            <center><p>&copy; 2025 MyPetakom</p></center>
        </footer>
    </div>
</body>
</html>

