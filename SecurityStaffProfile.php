<?php
// Start session
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "FKParkSystem", 3306);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Restrict access to only logged-in staff
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'SecurityStaff') {
    header("Location: Login.php");
    exit();
}

$staff_id = $_SESSION['user_id'];
$message = "";

// FETCH STAFF DATA
$stmt = $conn->prepare("SELECT * FROM staff WHERE staffID = ?");
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    session_destroy();
    header("Location: Login.php");
    exit();
}

$staff = $result->fetch_assoc();

// UPDATE PROFILE
if (isset($_POST['update_profile'])) {
    $name  = trim($_POST['name']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contact']);

    if ($name === "" || $email === "") {
        $message = "Name and Email are required.";
    } else {
        $stmt = $conn->prepare("
            UPDATE staff 
            SET StaffName = ?, StaffEmail = ?, StaffContact = ?
            WHERE staffID = ?
        ");
        $stmt->bind_param("ssss", $name, $email, $contact, $staff_id);

        if ($stmt->execute()) {
            $message = "Profile updated successfully.";

            // Refresh data
            $staff['StaffName']  = $name;
            $staff['StaffEmail'] = $email;
            $staff['StaffContact'] = $contact;
        } else {
            $message = "Failed to update profile.";
        }
    }
}

// DELETE ACCOUNT
if (isset($_POST['delete_account'])) {
    $stmt = $conn->prepare("DELETE FROM staff WHERE staffID = ?");
    $stmt->bind_param("s", $staff_id);

    if ($stmt->execute()) {
        session_destroy();
        header("Location: Login.php");
        exit();
    } else {
        $message = "Failed to delete account.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>SecurityStaff Profile</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
        }

        .container {
            max-width: 600px;
            background: white;
            padding: 30px;
            margin: 80px auto;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
        }

        .btn {
            margin-top: 20px;
            padding: 10px;
            width: 100%;
            border: none;
            cursor: pointer;
            border-radius: 4px;
        }

        .update {
            background: #008080;
            color: white;
        }

        .delete {
            background: #cc0000;
            color: white;
        }

        .back {
            background: #777;
            color: white;
            text-decoration: none;
            display: block;
            text-align: center;
            margin-top: 15px;
            padding: 10px;
            border-radius: 4px;
        }

        .msg {
            text-align: center;
            color: #006666;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>My Profile</h2>

    <?php if ($message): ?>
        <p class="msg"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <!-- UPDATE PROFILE FORM -->
    <form method="POST">
        <label>Staff ID</label>
        <input type="text" value="<?php echo htmlspecialchars($staff['StaffID']); ?>" disabled>

        <label>Name</label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($staff['StaffName']); ?>">

        <label>Email</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($staff['StaffEmail']); ?>">

        <label>Contact</label>
        <input type="text" name="contact" value="<?php echo htmlspecialchars($staff['StaffContact']); ?>">

        <button type="submit" name="update_profile" class="btn update">
            Update Profile
        </button>
    </form>

    <!-- DELETE ACCOUNT -->
    <form method="POST" onsubmit="return confirm('Are you sure you want to delete your account? This action cannot be undone.');">
        <button type="submit" name="delete_account" class="btn delete">
            Delete Account
        </button>
    </form>

    <a href="SecurityStaffDashboard.php" class="back">Back to Dashboard</a>
</div>

</body>
</html>
