<!DOCTYPE html>
<html>
    <head>
        <title>AdminDashboard</title>
        <meta name="desription" content="AdminDashboard">
        <meta name="author" content="Group1A3">
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
                background-color:rgba(196, 89, 56, 0.87); 
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0 20px;
                position: fixed;
                width: 100%;
                box-sizing: border-box;
                height: 120px;
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
                background-color: rgba(196, 89, 56, 0.87);;
                width: 200px;
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
                background-color: rgba(161, 66, 37, 0.87);;
            }
            
            .menutext.active {
                background-color: rgba(185, 79, 47, 0.87);;
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

            .footer {
               background-color: #e67e22;
               color: white;
               padding: 15px 0;
            }
        </style>
    </head>
    <body>
        <header class="header">
            <div class="header_left">
                <div class="logo">
                <img src="UMPLogo.png" alt="UMPLogo">
                </div>
            </div>
            <div class="header-right">
                <a href="AdminProfile.php" class="profile">
                    <i class="fas fa-user-circle"></i> My Profile
                </a>
                <a href="logout.php" class="logoutbutton" onclick="return confirm('Are you sure you want to log out?');">
                   <i class="fas fa-sign-out-alt"></i> Logout
                </a>

            </div>
        </header>
  >
        <nav class="sidebar">
            <h1 class="sidebartitle">Admin Bar</h1>
            <ul class="menu">
                <li>
                    <a href="AdminDashboard.php" class="menutext active">Dashboard</a>
                </li>
                <li>
                    <a href="ManageUser.php" class="menutext">Manage User</a>
                </li>
                <li>
                    <a href="ParkingManagement.php" class="menutext">Parking Management</a>
                </li>
                <li>
                    <a href="Report.php" class="menutext">Report</a>
                </li>
            </ul>
        </nav>


    </body>
    <div class="footer">
          <footer>
            <center><p>&copy; 2025 FKPark System</p></center>
          </footer>
        </div>

</html>

<?php

?>
