<?php
session_start();

/* =========================
   DATABASE CONNECTION
   ========================= */
$conn = new mysqli("localhost", "root", "", "fkparksystem", 3306);
if ($conn->connect_error) {
    die("Database connection failed");
}

/* =========================
   SECURITY CHECK
   Only Administrator allowed
   ========================= */
if (!isset($_SESSION['type_user']) || $_SESSION['type_user'] !== 'Administrator') {
    header("Location: Login.php");
    exit();
}

/* =========================
   GET USER INFO FROM URL
   ========================= */
if (!isset($_GET['id'], $_GET['type'])) {
    header("Location: ManageUser.php");
    exit();
}

$id   = $_GET['id'];
$type = $_GET['type'];

/* =========================
   FETCH USER DATA
   ========================= */
if ($type === "student") {

    $stmt = $conn->prepare(
        "SELECT StudentID AS id, StudentName AS name, 
                StudentContact AS contact, StudentEmail AS email
         FROM Student WHERE StudentID=?"
    );

} else {

    $stmt = $conn->prepare(
        "SELECT StaffID AS id, StaffName AS name, 
                StaffContact AS contact, StaffEmail AS email, Roles
         FROM Staff WHERE StaffID=?"
    );
}

$stmt->bind_param("s", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header("Location: ManageUser.php");
    exit();
}

/* =========================
   UPDATE USER DATA
   ========================= */
if (isset($_POST['update_user'])) {

    $name    = $_POST['name'];
    $contact = $_POST['contact'];
    $email   = $_POST['email'];

    // Password update is OPTIONAL
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    }

    if ($type === "student") {

        if (!empty($_POST['password'])) {
            $stmt = $conn->prepare(
                "UPDATE Student
                 SET StudentName=?, StudentContact=?, 
                     StudentEmail=?, StudentPassword=?
                 WHERE StudentID=?"
            );
            $stmt->bind_param("sssss", $name, $contact, $email, $password, $id);
        } else {
            $stmt = $conn->prepare(
                "UPDATE Student
                 SET StudentName=?, StudentContact=?, StudentEmail=?
                 WHERE StudentID=?"
            );
            $stmt->bind_param("ssss", $name, $contact, $email, $id);
        }

    } else {

        $role = $_POST['staff_role'];

        if (!empty($_POST['password'])) {
            $stmt = $conn->prepare(
                "UPDATE Staff
                 SET StaffName=?, StaffContact=?, StaffEmail=?, 
                     StaffPassword=?, Roles=?
                 WHERE StaffID=?"
            );
            $stmt->bind_param("ssssss", $name, $contact, $email, $password, $role, $id);
        } else {
            $stmt = $conn->prepare(
                "UPDATE Staff
                 SET StaffName=?, StaffContact=?, StaffEmail=?, Roles=?
                 WHERE StaffID=?"
            );
            $stmt->bind_param("sssss", $name, $contact, $email, $role, $id);
        }
    }

    $stmt->execute();

    header("Location: ManageUser.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Edit User</title>

<style>
body {
    font-family: Roboto, sans-serif;
    background:#f5f5f5;
}
.container {
    max-width:600px;
    margin:120px auto;
    background:white;
    padding:30px;
    border-radius:8px;
}
input, select, button {
    width:100%;
    padding:10px;
    margin:10px 0;
}
button {
    background:#6a22bdff;
    color:white;
    border:none;
    cursor:pointer;
}
a {
    text-decoration:none;
    display:inline-block;
    margin-bottom:15px;
}
</style>
</head>

<body>

<div class="container">

<a href="ManageUser.php">Back to Manage User</a>

<h2>Edit <?= ucfirst($type); ?> Profile</h2>

<form method="POST">

<input type="text" value="<?= htmlspecialchars($user['id']); ?>" disabled>

<input name="name" value="<?= htmlspecialchars($user['name']); ?>" required>

<input name="contact" value="<?= htmlspecialchars($user['contact']); ?>" required>

<input name="email" type="email" value="<?= htmlspecialchars($user['email']); ?>" required>

<input name="password" type="password" placeholder="New Password (optional)">

<?php if ($type === "staff"): ?>
<select name="staff_role">
    <option value="Administrator" <?= $user['Roles']=="Administrator" ? "selected" : "" ?>>Administrator</option>
    <option value="SecurityStaff" <?= $user['Roles']=="SecurityStaff" ? "selected" : "" ?>>Security Staff</option>
</select>
<?php endif; ?>

<button name="update_user">Update Profile</button>

</form>
</div>

</body>
</html>