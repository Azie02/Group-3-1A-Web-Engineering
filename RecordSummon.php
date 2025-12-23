<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Record Summon - FKPark System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="SecurityDashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .record-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .form-card {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border-top: 5px solid #eb9d43;
        }

        .form-header {
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }

        .form-header h2 {
            color: #444;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .input-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .input-group label {
            font-weight: 600;
            color: #555;
            font-size: 0.95rem;
        }

        .input-group input, 
        .input-group select {
            padding: 12px 15px;
            border: 1.5px solid #e0e0e0;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .input-group input:focus, 
        .input-group select:focus {
            outline: none;
            border-color: #eb9d43;
            box-shadow: 0 0 0 3px rgba(235, 157, 67, 0.1);
        }

        .full-width {
            grid-column: span 2;
        }

        .action-bar {
            margin-top: 30px;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }

        .btn-submit {
            background: #eb9d43;
            color: white;
            padding: 14px 40px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background 0.3s;
        }

        .btn-submit:hover {
            background: #d48a35;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-info {
            background: #e3f2fd;
            color: #1976d2;
            border-left: 5px solid #2196f3;
        }

        @media (max-width: 768px) {
            .input-grid {
                grid-template-columns: 1fr;
            }
            .full-width {
                grid-column: span 1;
            }
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
            <a href="logout.php" class="logoutbutton" id="logoutBtn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>
    
    <nav class="sidebar">
        <h1 class="sidebartitle">Security Staff Bar</h1>
        <ul class="menu">
            <li><a href="SecurityStaffDashboard.php" class="menutext">Dashboard</a></li>
            <li><a href="VehicleApproval.php" class="menutext">Vehicle Approval</a></li>
            <li><a href="RecordSummon.php" class="menutext active">Record Summon</a></li>
            <li><a href="ManageSummon.php" class="menutext">Manage Summon</a></li>
        </ul>
    </nav>

    <div class="maincontent">
        <div class="record-container">
            <div class="form-card">
                <div class="form-header">
                    <h2><i class="fas fa-file-invoice"></i> Traffic Violation Entry</h2>
                </div>

                <form action="#" method="POST">
                    <div class="input-grid">
                        
                        <div class="input-group">
                            <label for="plate_number">Vehicle Plate Number</label>
                            <input type="text" id="plate_number" name="plate_number" placeholder="DCL 6345" required>
                        </div>

                        
                        <div class="input-group">
                            <label for="violation_id">Violation Type</label>
                            <select id="violation_id" name="violation_id" required>
                                <option value="" disabled selected>Select Violation</option>
                                <option value="V001">Violation 1</option>
                                <option value="V002">Violation 2</option>
                                <option value="V003">Violation 3</option>
                                <option value="V004">Violation 4</option>
                            </select>
                        </div>

                        
                        <div class="input-group full-width">
                            <label for="location">Summon Location</label>
                            <input type="text" id="location" name="location" placeholder="AREA A" required>
                        </div>

                        
                        <div class="input-group">
                            <label for="summon_date">Violation Date</label>
                            <input type="date" id="summon_date" name="summon_date" required>
                        </div>
                        <div class="input-group">
                            <label for="summon_time">Violation Time</label>
                            <input type="time" id="summon_time" name="summon_time" required>
                        </div>
                    </div>

                    <div class="action-bar">
                        <button type="reset" class="profile" style="background:#eee; color:#444; border:none;">Clear Form</button>
                        <button type="submit" class="btn-submit">Record Summon</button>
                    </div>
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