<?php
 // Start the session
session_start();

// Database connection parameters
$conn = new mysqli("localhost", "root", "", "fkparksystem", 3306);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Restrict access to only logged-in staff
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'Administrator') {
    header("Location: Login.php");
    exit();
}

// ADD NEW USER (STUDENT / STAFF)
if (isset($_POST['add_user'])) {

    $role     = $_POST['role'];
    $id       = $_POST['id'];
    $name     = $_POST['name'];
    $contact  = $_POST['contact'];
    $email    = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    if ($role === "Student") {
        $stmt = $conn->prepare(
            "INSERT INTO Student
             (StudentID, StudentName, StudentContact, StudentEmail, StudentPassword)
             VALUES (?, ?, ?, ?, ?)"
        );
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO Staff
             (StaffID, StaffName, StaffContact, StaffEmail, StaffPassword, Roles)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
    }

    if ($role === "Student") {
        $stmt->bind_param("sssss", $id, $name, $contact, $email, $password);
    } else {
        $roleName = $_POST['staff_role'];
        $stmt->bind_param("ssssss", $id, $name, $contact, $email, $password, $roleName);
    }

    $stmt->execute();
}

//DELETE USER
if (isset($_GET['delete']) && isset($_GET['role'])) {

    $id   = $_GET['delete'];
    $role = $_GET['role'];

    if ($role === "Student") {
        $stmt = $conn->prepare("DELETE FROM Student WHERE StudentID=?");
    } else {
        $stmt = $conn->prepare("DELETE FROM Staff WHERE StaffID=?");
    }

    $stmt->bind_param("s", $id);
    $stmt->execute();
}

// FETCH USERS
$students = $conn->query("SELECT * FROM Student");
$admins   = $conn->query("SELECT * FROM Staff WHERE Roles='Administrator'");
$security = $conn->query("SELECT * FROM Staff WHERE Roles='SecurityStaff'");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage User</title>
    <style>
        body {
            font-family: Roboto, sans-serif;
            background:#f5f5f5;
        }

        .maincontent {
            margin-left:250px;
            margin-top:120px;
            padding:40px;
        }

        .box {
            background:white;
            padding:25px;
            border-radius:8px;
            margin-bottom:30px;
        }

        table {
            width:100%;
            border-collapse:collapse;
            margin-top:10px;
        }

        th, td {
            padding:12px;
            border-bottom:1px solid #ddd;
            text-align:center;
        }

        th {
            background:#6a22bdff;
            color:white;
        }

        input, select, button {
            padding:8px; margin:5px;
        }

        button {
            cursor:pointer;
        }

        a {
            text-decoration:none;
            margin: 0 5px;
        }
    </style>

    <script>
        function toggleStaffRole() {
            const role = document.getElementById("role").value;
            const staffRoleBox = document.getElementById("staffRoleBox");

            if (role === "Staff") {
                staffRoleBox.style.display = "inline-block";
            } else {
                staffRoleBox.style.display = "none";
            }
        }
    </script>

</head>

<body>
<div class="maincontent">

<a href="AdminDashboard.php">Back to Admin Dashboard</a>

<!-- ADD USER -->
<div class="box">
<h2>Add New User</h2>

<form method="POST">
    <input name="id" placeholder="ID" required>
    <input name="name" placeholder="Full Name" required>
    <input name="contact" placeholder="Contact No" required>
    <input name="email" type="email" placeholder="Email" required>
    <input name="password" type="password" placeholder="Password" required>

    <select name="role" id="role" onchange="toggleStaffRole()" required>
        <option value="Student">Student</option>
        <option value="Staff">Staff</option>
    </select>

    <span id="staffRoleBox" style="display:none;">
        <select name="staff_role">
            <option value="Administrator">Administrator</option>
            <option value="SecurityStaff">Security Staff</option>
        </select>
    </span>

    <button type="submit" name="add_user">Create User</button>
</form>
</div>

<!-- STUDENT TABLE -->
<div class="box">
<h2>Students</h2>
<table>
<tr><th>ID</th><th>Name</th><th>Contact</th><th>Email</th><th>Action</th></tr>
<?php while ($s = $students->fetch_assoc()): ?>
<tr>
<td><?= $s['StudentID']; ?></td>
<td><?= $s['StudentName']; ?></td>
<td><?= $s['StudentContact']; ?></td>
<td><?= $s['StudentEmail']; ?></td>
<td>
<a href="EditUser.php?id=<?= $s['StudentID']; ?>&type=student">Edit</a>
<a href="?delete=<?= $s['StudentID']; ?>&type=student"
onclick="return confirm('Delete student?')">Delete</a>
</td>
</tr>
<?php endwhile; ?>
</table>
</div>

<!-- ADMIN TABLE -->
<div class="box">
<h2>Administrators</h2>
<table>
<tr><th>ID</th><th>Name</th><th>Contact</th><th>Email</th><th>Action</th></tr>
<?php while ($a = $admins->fetch_assoc()): ?>
<tr>
<td><?= $a['StaffID']; ?></td>
<td><?= $a['StaffName']; ?></td>
<td><?= $a['StaffContact']; ?></td>
<td><?= $a['StaffEmail']; ?></td>
<td>
<a href="EditUser.php?id=<?= $a['StaffID']; ?>&type=staff">Edit</a>
<a href="?delete=<?= $a['StaffID']; ?>&type=staff"
onclick="return confirm('Delete admin?')">Delete</a>
</td>
</tr>
<?php endwhile; ?>
</table>
</div>

<!-- SECURITY TABLE -->
<div class="box">
<h2>Security Staff</h2>
<table>
<tr><th>ID</th><th>Name</th><th>Contact</th><th>Email</th><th>Action</th></tr>
<?php while ($sec = $security->fetch_assoc()): ?>
<tr>
<td><?= $sec['StaffID']; ?></td>
<td><?= $sec['StaffName']; ?></td>
<td><?= $sec['StaffContact']; ?></td>
<td><?= $sec['StaffEmail']; ?></td>
<td>
<a href="EditUser.php?id=<?= $sec['StaffID']; ?>&type=staff">Edit</a>
<a href="?delete=<?= $sec['StaffID']; ?>&type=staff"
onclick="return confirm('Delete staff?')">Delete</a>
</td>
</tr>
<?php endwhile; ?>
</table>

</div>

</div>
</body>
</html>