<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?error=unauthorized");
    exit();
}

$u_id = $_SESSION['user_id'];
$theme_data = $conn->query("SELECT first_name, theme_color FROM users WHERE id = $u_id")->fetch_assoc();
$f_name = $theme_data['first_name'] ?? "Admin";
$user_theme = $theme_data['theme_color'] ?? 'blue';

$theme_map = [
    'blue'  => 'linear-gradient(135deg, #0984e3, #6c5ce7)',
    'red'   => 'linear-gradient(135deg, #990000, #660000)',
    'gold'  => 'linear-gradient(135deg, #ceb888, #000000)',
    'black' => 'linear-gradient(135deg, #2d3436, #000000)'
];
$active_gradient = $theme_map[$user_theme] ?? $theme_map['blue'];
$colors = ['blue' => '#0984e3', 'red' => '#990000', 'gold' => '#ceb888', 'black' => '#2d3436'];
$accent = $colors[$user_theme] ?? '#0984e3';

$sql = "SELECT id, first_name, last_name, email FROM users ORDER BY id ASC";
$result = $conn->query($sql);
$user_count = $result ? $result->num_rows : 0;
?>

<?php include 'header.php'; ?>

    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; color: #2d3436; margin: 0; }

        .hero-banner {
            background: <?php echo $active_gradient; ?> !important;
            color: white !important;
            padding: 60px 20px 100px 20px;
            text-align: center;
            margin-bottom: -60px;
            transition: background 0.5s ease;
        }
        .hero-banner h1 { margin: 0; font-weight: 800; letter-spacing: -1px; }

        .main-container { width: 95%; max-width: 1100px; margin: 0 auto 50px auto; position: relative; z-index: 5; }
        .card { background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); padding: 30px; margin-bottom: 25px; }

        .admin-table th {
            padding: 12px;
            text-align: left;
            border-bottom: 3px solid <?php echo $accent; ?> !important;
            color: #2d3436;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 0.75em;
            letter-spacing: 1px;
        }

        .admin-table td { padding: 15px 12px; border-bottom: 1px solid #f1f2f6; }
        .user-id { color: #b2bec3; font-weight: 700; font-size: 0.9em; }

        .btn-back {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            color: <?php echo $accent; ?>;
            font-weight: 700;
            transition: 0.2s;
        }
        .btn-back:hover { opacity: 0.7; transform: translateX(-3px); }

        .search-box {
            padding: 10px 16px;
            border: 1.5px solid #dfe6e9;
            border-radius: 10px;
            font-size: 13px;
            width: 280px;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s;
        }
        .search-box:focus { border-color: <?php echo $accent; ?>; }

        .users-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        @media (max-width: 600px) {
            .search-box { width: 100%; }
        }
    </style>

    <div class="hero-banner">
        <h1>User Management</h1>
        <p>Administrative view of the FanFest directory.</p>
    </div>

    <div class="main-container">
        <div class="card">
            <div class="users-header">
                <div>
                    <h2 style="margin:0 0 4px;">Registered Fans</h2>
                    <p style="margin:0; color:#b2bec3; font-size:13px;"><?php echo $user_count; ?> users total</p>
                </div>
                <input type="text" id="userSearch" class="search-box" placeholder="Search by name or email...">
            </div>

            <?php if ($result && $result->num_rows > 0): ?>
                <table class="admin-table" id="userTable" style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="user-id">#<?php echo $row['id']; ?></td>
                                <td style="font-weight: 700;">
                                    <?php echo htmlspecialchars($row['first_name'] . " " . $row['last_name']); ?>
                                </td>
                                <td style="color: #636e72;"><?php echo htmlspecialchars($row['email']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #b2bec3; padding: 40px;">No users found in the database.</p>
            <?php endif; ?>

            <a href="index.php" class="btn-back">&larr; Back to Dashboard</a>
        </div>
    </div>

    <script>
        document.getElementById('userSearch').addEventListener('input', function() {
            const q = this.value.toLowerCase();
            document.querySelectorAll('#userTable tbody tr').forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(q) ? '' : 'none';
            });
        });
    </script>

</body>
</html>

<?php include 'footer.php'; ?>