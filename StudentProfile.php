<?php
// Start session
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "FKParkSystem", 3306);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Restrict access to logged-in student only
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'student') {
    header("Location: Login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$message = "";

// FETCH STUDENT DATA
$stmt = $conn->prepare("SELECT * FROM student WHERE studentID = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    session_destroy();
    header("Location: Login.php");
    exit();
}

$student = $result->fetch_assoc();

// UPDATE PROFILE
if (isset($_POST['update_profile'])) {
    $name  = trim($_POST['name']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contact']);

    if ($name === "" || $email === "") {
        $message = "Name and Email are required.";
    } else {
        $stmt = $conn->prepare("
            UPDATE student 
            SET StudentName = ?, StudentEmail = ?, StudentContact = ?
            WHERE studentID = ?
        ");
        $stmt->bind_param("ssss", $name, $email, $contact, $student_id);

        if ($stmt->execute()) {
            $message = "Profile updated successfully.";

            // Refresh data
            $student['StudentName']  = $name;
            $student['StudentEmail'] = $email;
            $student['StudentContact'] = $contact;
        } else {
            $message = "Failed to update profile.";
        }
    }
}

// DELETE ACCOUNT
if (isset($_POST['delete_account'])) {
    $stmt = $conn->prepare("DELETE FROM student WHERE studentID = ?");
    $stmt->bind_param("s", $student_id);

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
    <title>Student Profile</title>
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
        <label>Student ID</label>
        <input type="text" value="<?php echo htmlspecialchars($student['StudentID']); ?>" disabled>

        <label>Name</label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($student['StudentName']); ?>">

        <label>Email</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($student['StudentEmail']); ?>">

        <label>Contact</label>
        <input type="text" name="contact" value="<?php echo htmlspecialchars($student['StudentContact']); ?>">

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

    <a href="StudentDashboard.php" class="back">Back to Dashboard</a>
</div>

</body>
</html>
