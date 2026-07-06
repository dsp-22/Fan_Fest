<?php
// 1. Force Error Display
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. Prevent the script from dying silently on SQL errors
mysqli_report(MYSQLI_REPORT_OFF); 

echo "<h2 style='color:blue'>Step 1: Script Started</h2>";

// 3. Connect to DB
require 'db_connect.php'; 
if (!isset($conn)) {
    die("<h1 style='color:red'>FATAL: \$conn variable is missing!</h1>");
}
echo "<h2 style='color:blue'>Step 2: Database Connected</h2>";

// 4. Test a Simple Query
$test = $conn->query("SELECT 1");
if (!$test) {
    die("<h1 style='color:red'>FATAL: Simple Query Failed. Reason: " . $conn->error . "</h1>");
}
echo "<h2 style='color:blue'>Step 3: Simple Query OK</h2>";

// 5. Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "<h2 style='color:blue'>Step 4: Session Started</h2>";

// 6. Check User ID
if (!isset($_SESSION['user_id'])) {
    die("<h1 style='color:orange'>STOP: You are not logged in. Log in and try again.</h1>");
}
$uid = $_SESSION['user_id'];
echo "<h2 style='color:blue'>Step 5: User ID is $uid</h2>";

// 7. Fetch User Data (The likely crash point)
$sql = "SELECT * FROM users WHERE id = $uid";
$result = $conn->query($sql);

if (!$result) {
    die("<h1 style='color:red'>FATAL: User Query Failed. SQL Error: " . $conn->error . "</h1>");
}
echo "<h2 style='color:blue'>Step 6: Query Executed</h2>";

$user = $result->fetch_assoc();
if (!$user) {
    die("<h1 style='color:orange'>STOP: User ID $uid does not exist in the 'users' table.</h1>");
}
echo "<h2 style='color:blue'>Step 7: User Data Fetched</h2>";

// 8. Check for NULL values
$username = isset($user['username']) ? $user['username'] : 'Not Set';
echo "<h2 style='color:blue'>Step 8: Username is $username</h2>";
?>

<form>
    <label>Test Input:</label>
    <input type="text" value="<?php echo htmlspecialchars($username); ?>">
</form>

<?php echo "<h1 style='color:green'>SUCCESS: The code reached the end!</h1>"; ?>
