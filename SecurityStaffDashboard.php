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

$totalSummonsQuery = "SELECT COUNT(*) as count FROM TrafficSummon";
$totalSummons = $conn->query($totalSummonsQuery)->fetch_assoc()['count'];

$pendingVehiclesQuery = "SELECT COUNT(*) as count FROM Vehicle WHERE VehicleStatus = 'Pending'";
$pendingVehicles = $conn->query($pendingVehiclesQuery)->fetch_assoc()['count'];

$violationData = [];
$violationLabels = [];
$vQuery = "SELECT v.ViolationType, COUNT(ts.SummonID) as count 
           FROM TrafficSummon ts 
           JOIN Violation v ON ts.ViolationID = v.ViolationID 
           GROUP BY v.ViolationType";
$vResult = $conn->query($vQuery);
while($row = $vResult->fetch_assoc()) {
    $violationLabels[] = $row['ViolationType'];
    $violationData[] = $row['count'];
}

$trendData = [];
$trendLabels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $trendLabels[] = date('M d', strtotime($date));
    
    $tQuery = "SELECT COUNT(*) as count FROM TrafficSummon WHERE SummonDate = '$date'";
    $trendData[] = $conn->query($tQuery)->fetch_assoc()['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Security Staff Dashboard</title>
    <link rel="stylesheet" href="SecurityDashboard.css">
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
    background-color: #f0b26aff;
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

.logo {
    display: flex;
    gap: 20px;
    align-items: center;
    padding: 0 60px;
}

.logo img {
    height: 90px;
    width: auto;
}

.sidebar {
    background-color: #eb9d43ff;
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

.sidebartitle {
    color: white;
    font-size: 1rem;
    margin-bottom: 20px;
    padding: 0 20px;
}

.menu {
    display: flex;
    flex-direction: column;
    gap: 18px;
    padding: 0;
    margin: 0;
    list-style: none;
}

.menutext {
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
    background-color: #6d4e2aff;
}

.menutext.active {
    background-color: #79542aff;
    font-weight: 500;
}

/* =========================================
   4. BUTTONS & LINKS (Global)
   ========================================= */
.profile {
    background-color: #ff8800ff;
    color: white;
    border: 1px solid rgba(0, 0, 0, 0.3);
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
    background-color: #462e12ff;
}

.logoutbutton {
    background-color: rgba(255, 0, 0, 0.81);
    color: white;
    border: 1px solid rgba(0, 0, 0, 0.3);
    padding: 8px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-submit, .btn-save {
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

.btn-submit:hover, .btn-save:hover {
    background-color: #218838;
}

.back-link {
    display: inline-block;
    margin-bottom: 20px;
    color: #666;
    text-decoration: none;
    font-weight: 500;
}

.back-link:hover {
    color: #333;
}

.view-btn, .review {
    background-color: #eb9d43ff;
    color: white;
    padding: 6px 12px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    transition: background 0.3s;
}

.view-btn:hover, .review:hover {
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

.maincontent {
    margin-left: 250px;
    margin-top: 120px;
    padding: 40px;
    box-sizing: border-box;
}

.content, .seccontent {
    background-color: white;
    padding: 25px;
    border-radius: 8px;
    margin-bottom: 25px;
    box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
}

footer {
    background-color: #f0b26aff;
    color: white;
    padding: 15px 0;
    margin-top: auto;
}

/* FORMS & INPUTS */
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

.searchbar {
    display: flex;
    gap: 10px;
    margin-top: 20px;
    margin-bottom: 20px;
}

.searchbar input {
    padding: 10px 20px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 1em;
    flex: 1;
}

.searchbar button {
    background: #f0b26aff;
    color: white;
    border: none;
    border-radius: 5px;
    padding: 10px 18px;
    cursor: pointer;
}

/* ALERTS MESSAGES */
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

/* TABLES */
.table-container {
    background-color: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    font-size: 0.95rem;
}

th, td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

th {
    background-color: #f0b26aff;
    color: white;
    font-weight: 600;
}

tr:hover {
    background-color: #f9f9f9;
}

.header-wrapper {
    position: relative;
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 20px;
    padding: 0 10px;
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

/* DETAILS PAGE (ViewSummon & ReviewVehicle) */
.details-container {
    background-color: white;
    padding: 40px;
    border-radius: 8px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.1);
    max-width: 800px;
    margin: 0 auto;
    position: relative;
}

.header-section {
    border-bottom: 2px solid #eee;
    padding-bottom: 20px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-section h2 {
    margin: 0;
    color: #333;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 30px;
}

.info-group {
    margin-bottom: 20px;
}

.info-group label {
    display: block;
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 5px;
}

.info-group .value {
    font-size: 1.1rem;
    font-weight: 500;
    color: #333;
}

.status-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 0.9rem;
    display: inline-block;
}
.Pending { background-color: #ffeeba; color: #856404; }
.Approved { background-color: #d4edda; color: #155724; }
.Rejected { background-color: #f8d7da; color: #721c24; }

/* QR Code & Grant Section */
.qr-section, .grant-section {
    text-align: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid #eee;
}

.qr-image {
    width: 150px;
    height: 150px;
    margin-bottom: 10px;
    border: 1px solid #ddd;
    padding: 5px;
}

.grant-photo {
    max-width: 100%;
    max-height: 400px;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 5px;
    margin-top: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.no-photo {
    padding: 30px;
    color: #999;
    font-style: italic;
    background: #f9f9f9;
    border-radius: 4px;
}

/* FORMS (Review Vehicle) */
.action-container {
    margin-top: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.action-container label {
    font-weight: bold;
    color: #333;
    margin-bottom: 10px;
    display: block;
}

.btn-group {
    display: flex;
    gap: 15px;
}

.btn {
    flex: 1;
    padding: 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
    color: white;
    text-transform: uppercase;
    font-size: 0.9rem;
    transition: opacity 0.3s;
}

.btn:hover {
    opacity: 0.9; 
}

.btn-approve { background-color: #28a745; }
.btn-reject { background-color: #dc3545; }
.btn-print {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 1rem;
    transition: background 0.3s;
    text-decoration: none;
    display: inline-block;
}
.btn-print:hover { background-color: #0056b3; }

/* DASHBOARD WIDGETS */
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-info { text-align: center; }
.stat-info h3 { margin: 0; font-size: 2rem; color: #333; }
.stat-info p { margin: 5px 0 0; color: #666; font-size: 0.9rem; }

.charts-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.chart-box {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.chart-box h3 {
    margin-top: 0;
    color: #444;
    font-size: 1.1rem;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

/* PROFILE PAGE*/
.profile-wrapper {
    display: flex;
    gap: 30px;
}

.profile-left { flex: 1; text-align: center; }
.profile-right { flex: 3; }

.avatar-box {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: #eb9d43;
    margin: 0 auto 15px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 50px;
    color: white;
    border: 4px solid white;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.avatar-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.btn-upload {
    background: #eb9d43;
    color: white;
    padding: 5px 10px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    margin-top: 10px;
}

.form-section {
    background: #fff; 
    padding: 25px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #eee;
}


@media (max-width: 900px) {
    .charts-container { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
    .profile-wrapper { flex-direction: column; }
}

@media print {
    body * { visibility: hidden; }
    .details-container, .details-container * { visibility: visible; }
    .details-container {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        box-shadow: none;
    }
    .back-link, .btn-print, .sidebar, .header { display: none !important; }
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
            <li><a href="SecurityStaffDashboard.php" class="menutext active">Dashboard</a></li>
            <li><a href="VehicleApproval.php" class="menutext">Vehicle Approval</a></li>
            <li><a href="TrafficSummon.php" class="menutext">Trafic Summon</a></li>
        </ul>
    </nav>

    <div class="maincontent">
        <div class="content" style="background: transparent; box-shadow: none; padding: 0;">
            <h2 style="margin-bottom: 25px; color: #333;">Dashboard Overview</h2>

            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo $totalSummons; ?></h3>
                        <p>Total Summons Issued</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo $pendingVehicles; ?></h3>
                        <p>Pending Approvals</p>
                    </div>
                </div>
            </div>

            <div class="charts-container">
                <div class="chart-box">
                    <h3>Summons by Violation Type</h3>
                    <canvas id="violationChart"></canvas>
                </div>
                <div class="chart-box">
                    <h3>Summons Issued (Last 7 Days)</h3>
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <center><p> © 2025 FKPark System</p></center>
    </footer>

    <script>
        const violationLabels = <?php echo json_encode($violationLabels); ?>;
        const violationData = <?php echo json_encode($violationData); ?>;
        const trendLabels = <?php echo json_encode($trendLabels); ?>;
        const trendData = <?php echo json_encode($trendData); ?>;

        // Bar Chart
        const ctx1 = document.getElementById('violationChart').getContext('2d');
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: violationLabels,
                datasets: [{
                    label: 'Count',
                    data: violationData,
                    backgroundColor: '#eb9d43ff',
                    borderColor: '#d68a35',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });

        // Line Chart
        const ctx2 = document.getElementById('trendChart').getContext('2d');
        new Chart(ctx2, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'Summons Issued',
                    data: trendData,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    </script>
</body>
</html>