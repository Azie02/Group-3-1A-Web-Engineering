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

$staff_id = $_SESSION['user_id'];
$message = "";

// 1. Handle Profile Update
if (isset($_POST['update_profile'])) {
    $name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    
    $stmt = $conn->prepare("UPDATE Staff SET StaffName=?, StaffEmail=?, StaffContact=? WHERE StaffID=?");
    $stmt->bind_param("ssss", $name, $email, $phone, $staff_id);
    if ($stmt->execute()) $message = "Profile updated successfully!";
    else $message = "Error updating profile.";
}

// 2. Handle Password Change
if (isset($_POST['change_password'])) {
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];
    
    if ($new_pass === $confirm_pass && strlen($new_pass) >= 8) {
        $stmt = $conn->prepare("UPDATE Staff SET StaffPassword=? WHERE StaffID=?");
        $stmt->bind_param("ss", $new_pass, $staff_id);
        if ($stmt->execute()) $message = "Password changed successfully!";
        else $message = "Error changing password.";
    } else {
        $message = "Passwords do not match.";
    }
}

// 3. Handle Picture Upload
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
    $file = $_FILES['profile_picture'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = "profile_" . $staff_id . "_" . time() . "." . $ext;
    $target = "../uploads/profile_pictures/" . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target)) {
        $conn->query("UPDATE Staff SET StaffPic='$filename' WHERE StaffID='$staff_id'");
        $message = "Profile picture updated!";
    }
}

$staff = $conn->query("SELECT * FROM Staff WHERE StaffID='$staff_id'")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile</title>
    <link rel="stylesheet" href="SecurityDashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <header class="header">
        <div class="header-left">
            <div class="logo"><img src="../UMPLogo.png" alt="UMP Logo"></div>
        </div>
        <a href="../logout.php" class="logoutbutton" onclick="return confirm('Are you sure you want to log out?');">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </header>
    <nav class="sidebar">
        <h1 class="sidebartitle">Security Staff Bar</h1>
        <ul class="menu">
            <li><a href="SecurityStaffDashboard.php" class="menutext">Dashboard</a></li>
            <li><a href="VehicleApproval.php" class="menutext">Vehicle Approval</a></li>
            <li><a href="TrafficSummon.php" class="menutext">Traffic Summon</a></li>
        </ul>
    </nav>

    <div class="maincontent">
        <div class="content">
            <h2 style="border-bottom: 2px solid #eee; padding-bottom: 10px;">My Profile</h2>
            
            <?php if ($message): ?>
                <div class="alert"><?php echo $message; ?></div>
            <?php endif; ?>

            <div class="profile-wrapper">
                <div class="profile-left">
                    <div class="avatar-box">
                        <?php if (!empty($staff['StaffPic']) && file_exists("../uploads/profile_pictures/" . $staff['StaffPic'])): ?>
                            <img src="../uploads/profile_pictures/<?php echo $staff['StaffPic']; ?>" alt="Profile">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    
                    <form action="" method="POST" enctype="multipart/form-data">
                        <input type="file" name="profile_picture" required style="max-width: 200px;">
                        <button type="submit" class="btn-upload">Update Photo</button>
                    </form>
                    
                    <p><strong>ID:</strong> <?php echo $staff['StaffID']; ?></p>
                    <p style="color: #eb9d43; font-weight: bold;">Security Staff</p>
                </div>

                <div class="profile-right">
                    <form action="" method="POST" class="form-section">
                        <h3>Personal Information</h3>
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($staff['StaffName']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($staff['StaffEmail']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="phone" value="<?php echo htmlspecialchars($staff['StaffContact']); ?>">
                        </div>
                        <button type="submit" name="update_profile" class="btn-save">Save Changes</button>
                    </form>

                    <form action="" method="POST" class="form-section">
                        <h3>Change Password</h3>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" required minlength="8">
                        </div>
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <input type="password" name="confirm_password" required minlength="8">
                        </div>
                        <button type="submit" name="change_password" class="btn-save" style="background:#007bff;">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <center><p>© 2025 FKPark System</p></center>
    </footer>
</body>
</html>