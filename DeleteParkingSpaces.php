<?php
// Start the session
session_start();

// Database connection parameters
$conn = new mysqli("localhost", "root", "", "FKParkSystem", 3307);

// Check if database connection failed
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Restrict access to only logged-in staff
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'Administrator') {
    header("Location: Login.php");
    exit();
}

// Get staff data from database
$staff_id = $_SESSION['user_id'];
$query = "SELECT * FROM staff WHERE staffID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    // Staff not found in database
    session_destroy();
    header("Location: Login.php");
    exit();
}
$staff = $result->fetch_assoc();

// 60 seconds inactivity timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 60) {
    session_unset();
    session_destroy();
    header("Location: Login.php");
    exit();
}

// Update activity time on every request
$_SESSION['last_activity'] = time();

// Existing security check (keep this if you already have it)
if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}

// Get the parking space ID to delete
$spaceID = $_GET['id'];

// First, get the ParkingAreaID for this space so we know which area it belongs to
$areaQuery = "SELECT ParkingAreaID FROM parkingspace WHERE ParkingSpaceID = ?";
$stmt = $conn->prepare($areaQuery);
$stmt->bind_param("s", $spaceID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Parking space not found.");
}

$row = $result->fetch_assoc();
$areaID = $row['ParkingAreaID'];

// Delete from parkingstatus first (due to foreign key constraint)
$stmt = $conn->prepare("DELETE FROM parkingstatus WHERE ParkingSpaceID = ?");
$stmt->bind_param("s", $spaceID);
$stmt->execute();

// Delete from parkingspace
$stmt = $conn->prepare("DELETE FROM parkingspace WHERE ParkingSpaceID = ?");
$stmt->bind_param("s", $spaceID);
if ($stmt->execute()) {
    $_SESSION['message'] = "Parking space deleted successfully.";
    $_SESSION['message_type'] = "success";
} else {
    $_SESSION['message'] = "Error deleting parking space.";
    $_SESSION['message_type'] = "error";
}

// Close statement
$stmt->close();

// Close database connection
$conn->close();

// Redirect back to ParkingSpaces.php with the areaID parameter
header("Location: ParkingSpaces.php?id=" . urlencode($areaID));
exit();
?>
