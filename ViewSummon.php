<?php
session_start();

// Single place to define login redirect header
define('LOGIN_REDIRECT', 'Location: Login.php');

/* --------- Permission check: student only --------- */
if (!isset($_SESSION['user_id']) || ($_SESSION['type_user'] ?? '') !== 'student') {
    header(LOGIN_REDIRECT);
    exit();
}

/* --------- DB connection --------- */
$conn = new mysqli("localhost", "root", "", "fkparksystem", 3306);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* --------- Accept either ?id= or ?view_summon= --------- */
$summonId = $_GET['id'] ?? $_GET['view_summon'] ?? '';

if (empty($summonId)) {
    header("Location: MeritStatus.php");
    exit();
}

$studentID = $_SESSION['user_id'];

// Get student data from database
$query = "SELECT * FROM student WHERE studentID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $studentID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    session_destroy();
    header(LOGIN_REDIRECT);
    exit();
}
$student = $result->fetch_assoc();

/* --------- Fetch summon details --------- */
$sql = "
    SELECT
        ts.SummonID,
        ts.SummonDate,
        ts.SummonTime,
        ts.SummonDescription,
        ts.FineAmount,
        v.ViolationName,
        v.ViolationType,
        v.ViolationCode,
        v.ViolationDescription,
        s.StudentName,
        s.StudentEmail,
        s.StudentContact
    FROM TrafficSummon ts
    LEFT JOIN Violation v ON ts.ViolationID = v.ViolationID
    LEFT JOIN Student s ON ts.StudentID = s.StudentID
    WHERE ts.SummonID = ? AND ts.StudentID = ?
    LIMIT 1
";

$stmtSummon = $conn->prepare($sql);
if (!$stmtSummon) {
    die("Prepare failed: " . $conn->error);
}
$stmtSummon->bind_param("ss", $summonId, $studentID);
$stmtSummon->execute();
$res = $stmtSummon->get_result();

if (!$res || $res->num_rows !== 1) {
    $message = "Summon not found or you are not authorized to view it.";
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>View Summon - Error</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body {
               background-color: #f5f5f5;
               font-family: 'Roboto', sans-serif;
               margin: 0;
               padding: 0;
               display: flex;
               align-items: center;
               justify-content: center;
               min-height: 100vh;
            }
            .card { 
                max-width: 600px; 
                background: white; 
                padding: 40px; 
                border-radius: 12px; 
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                text-align: center;
            }
            .alert { 
                padding: 20px; 
                background-color: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 8px; 
                color: #856404;
                font-size: 16px;
                margin-bottom: 20px;
                font-weight: 600;
            }
            .btn { 
                display: inline-block; 
                padding: 12px 24px; 
                background-color: #008080;
                color: white; 
                text-decoration: none; 
                border-radius: 6px;
                font-weight: 600;
                transition: all 0.2s ease;
            }
            .btn:hover {
                background-color: #006666;
            }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="alert">⚠️ <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <a class="btn" href="MeritStatus.php">← Back to Merit Status</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

$summon = $res->fetch_assoc();
$stmtSummon->close();

function e($v) { 
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); 
}

// 60 seconds inactivity timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 60) {
    session_unset();
    session_destroy();
    header(LOGIN_REDIRECT);
    exit();
}
$_SESSION['last_activity'] = time();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>View Summon - <?php echo e($summon['SummonID']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

        .maincontent{
           margin-left: 250px;
           margin-top: 120px;
           padding: 40px;
           box-sizing: border-box;
        }

        .content {
          background-color: white;
          padding: 35px;
          border-radius: 8px;
          box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }

        h2 {
            color: #2d3748;
            font-size: 26px;
            margin-bottom: 25px;
            font-weight: 600;
            padding-bottom: 15px;
            border-bottom: 3px solid #008080;
        }

        .field { 
            margin-bottom: 20px;
            display: flex;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 6px;
        }

        .label { 
            color: #008080; 
            font-weight: 700; 
            min-width: 180px;
            flex-shrink: 0;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .value { 
            color: #2d3748; 
            flex: 1;
            font-size: 15px;
            font-weight: 500;
        }

        .btn { 
            display: inline-block; 
            padding: 10px 20px; 
            border-radius: 6px; 
            background-color: #008080;
            color: white; 
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn:hover {
            background-color: #006666;
        }

        .btn-secondary { 
            background-color: #6c757d;
            margin-left: 10px;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        pre.desc { 
            white-space: pre-wrap; 
            word-wrap: break-word; 
            background: white; 
            padding: 15px; 
            border-radius: 6px; 
            border: 2px solid #e2e8f0;
            font-family: 'Courier New', monospace;
            color: #2d3748;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }

        .actions {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 2px solid #e9ecef;
            text-align: center;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-left: 8px;
        }

        .badge-danger {
            background-color: #dc3545;
            color: white;
        }

        .badge-warning {
            background-color: #ffc107;
            color: #856404;
        }

        .fine-amount {
            font-size: 32px;
            font-weight: 800;
            color: #dc3545;
            display: block;
            margin-top: 5px;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #008080;
            padding: 12px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 14px;
            color: #4a5568;
        }

        footer {
           background-color: #80cab1ff;
           color: white;
           padding: 15px 0;
           text-align: center;
           margin-top: auto;
        }

        @media print {
            .header, .sidebar, .actions, footer { display: none; }
            .maincontent { margin: 0; padding: 20px; }
        }
    </style>
</head>
<script>
let timeout = 60;
let warningTime = 10;
let countdown;

function startTimer() {
    clearTimeout(countdown);
    
    countdown = setTimeout(() => {
        let stay = confirm("Your session will expire soon.\n\nClick OK to continue or Cancel to logout.");
        
        if (stay) {
            fetch("keep_alive.php").then(() => {
                startTimer();
            });
        } else {
            window.location.href = "Logout.php";
        }
    }, (timeout - warningTime) * 1000);
}

["click", "mousemove", "keypress"].forEach(event => {
    document.addEventListener(event, startTimer);
});

startTimer();
</script>
<body>
    <header class="header">
        <div class="header_left">
            <div class="logo">
                <img src="UMPLogo.png" alt="UMPLogo">
            </div>
        </div>
        <div class="header-right">
            <span style="color:white; font-weight:500;">
                Welcome, <?php echo htmlspecialchars($student['StudentName']); ?>
            </span>
            <a href="StudentProfile.php" class="profile">
                <i class="fas fa-user-circle"></i> My Profile
            </a>
            <a href="logout.php" class="logoutbutton" onclick="return confirm('Are you sure you want to log out?');">
               <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>
    
    <nav class="sidebar">
        <h1 class="sidebartitle">Student Bar</h1>
        <ul class="menu">
            <li>
                <a href="StudentDashboard.php" class="menutext">Dashboard</a>
            </li>
            <li>
                <a href="VehicleRegistration.php" class="menutext">Vehicle Registration</a>
            </li>
            <li>
                <a href="Booking.php" class="menutext">Book Parking</a>
            </li>
            <li>
                <a href="MeritStatus.php" class="menutext active">Merit status</a>
            </li>
        </ul>
    </nav>

    <div class="maincontent">
        <div class="content">
            <h2>🚨 Traffic Summon Details - <?php echo e($summon['SummonID']); ?></h2>

            <div class="field">
                <span class="label">📅 Date & Time</span>
                <span class="value">
                    <?php
                        if ($summon['SummonDate']) {
                            $date = date('l, d F Y', strtotime($summon['SummonDate']));
                            echo e($date);
                        } else {
                            echo 'N/A';
                        }
                    ?>
                    <?php if ($summon['SummonTime']): ?>
                        <br>
                        <strong style="color: #008080;">⏰ <?php echo e($summon['SummonTime']); ?></strong>
                    <?php endif; ?>
                </span>
            </div>

            <div class="field">
                <span class="label">⚠️ Violation</span>
                <span class="value">
                    <strong style="font-size: 18px;"><?php echo e($summon['ViolationName'] ?? 'N/A'); ?></strong>
                    <?php if (!empty($summon['ViolationType'])): ?>
                        <span class="badge badge-danger"><?php echo e($summon['ViolationType']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($summon['ViolationCode'])): ?>
                        <div class="info-box" style="margin-top: 10px;">
                            <strong>Code:</strong> <?php echo e($summon['ViolationCode']); ?>
                        </div>
                    <?php endif; ?>
                </span>
            </div>

            <?php if (!empty($summon['ViolationDescription'])): ?>
            <div class="field">
                <span class="label">📖 Violation Info</span>
                <span class="value"><?php echo e($summon['ViolationDescription']); ?></span>
            </div>
            <?php endif; ?>

            <div class="field">
                <span class="label">📋 Description</span>
                <div class="value">
                    <pre class="desc"><?php echo e($summon['SummonDescription'] ?? 'No description provided.'); ?></pre>
                </div>
            </div>

            <div class="field">
                <span class="label">💰 Fine Amount</span>
                <span class="value">
                    <span class="fine-amount">RM <?php echo e(number_format($summon['FineAmount'] ?? 0, 2)); ?></span>
                </span>
            </div>

            <div class="field">
                <span class="label">👤 Student Name</span>
                <span class="value">
                    <strong><?php echo e($summon['StudentName'] ?? 'N/A'); ?></strong>
                    <?php if (!empty($summon['StudentEmail'])): ?>
                        <br><small style="color: #718096;">📧 <?php echo e($summon['StudentEmail']); ?></small>
                    <?php endif; ?>
                    <?php if (!empty($summon['StudentContact'])): ?>
                        <br><small style="color: #718096;">📱 <?php echo e($summon['StudentContact']); ?></small>
                    <?php endif; ?>
                </span>
            </div>

            <div class="actions">
                <a class="btn" href="MeritStatus.php">← Back to Merit Status</a>
                <a class="btn btn-secondary" href="javascript:window.print()">🖨️ Print Summon</a>
            </div>
        </div>
    </div>

    <footer>
        <p>© 2025 FKPark System</p>
    </footer>
</body>
</html>