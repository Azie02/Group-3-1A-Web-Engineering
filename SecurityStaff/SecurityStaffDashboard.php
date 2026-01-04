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