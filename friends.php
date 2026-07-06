<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

// --- GUEST LOGIC HOOK ---
$is_guest = ($_SESSION['user_id'] === 'guest');
$current_user_id = $is_guest ? 0 : $_SESSION['user_id'];
// ------------------------

// ajax endpoint for autofill
if (isset($_GET['term'])) {
    $term = '%' . $_GET['term'] . '%';
    $stmt = $conn->prepare("SELECT id, first_name, last_name, username, profile_pic FROM users WHERE (first_name LIKE ? OR username LIKE ?) AND id != ? LIMIT 8");
    $stmt->bind_param("ssi", $term, $term, $current_user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $suggestions = [];
    while($row = $res->fetch_assoc()) { $suggestions[] = $row; }
    echo json_encode($suggestions);
    exit;
}

// theme
if ($is_guest) {
    $user_theme = 'blue';
} else {
    $theme_data = $conn->query("SELECT first_name, theme_color FROM users WHERE id = $current_user_id")->fetch_assoc();
    $f_name = $theme_data['first_name'] ?? "Fan";
    $user_theme = $theme_data['theme_color'] ?? 'blue';
}

$theme_map = [
    'blue'  => 'linear-gradient(135deg, #0984e3, #6c5ce7)',
    'red'   => 'linear-gradient(135deg, #990000, #660000)',
    'gold'  => 'linear-gradient(135deg, #ceb888, #000000)',
    'black' => 'linear-gradient(135deg, #2d3436, #000000)'
];
$active_gradient = $theme_map[$user_theme] ?? $theme_map['blue'];

$colors = ['blue' => '#0984e3', 'red' => '#990000', 'gold' => '#ceb888', 'black' => '#2d3436'];
$accent = $colors[$user_theme] ?? '#0984e3';

$search_query = isset($_GET['search']) ? trim($_GET['search']) : "";

// social actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$is_guest) {
    if (isset($_POST['send_request_to'])) {
        $stmt = $conn->prepare("INSERT INTO friends (user_id_1, user_id_2, status) VALUES (?, ?, 'pending')");
        $stmt->bind_param("ii", $current_user_id, $_POST['send_request_to']);
        $stmt->execute();
    }
    if (isset($_POST['accept_request_id'])) {
        $stmt = $conn->prepare("UPDATE friends SET status = 'accepted' WHERE id = ?");
        $stmt->bind_param("i", $_POST['accept_request_id']);
        $stmt->execute();
    }
    if (isset($_POST['remove_rel_id'])) {
        $stmt = $conn->prepare("DELETE FROM friends WHERE id = ? AND (user_id_1 = $current_user_id OR user_id_2 = $current_user_id)");
        $stmt->bind_param("i", $_POST['remove_rel_id']);
        $stmt->execute();
    }
}

$my_friends = []; $sent_requests = []; $incoming_requests = [];
$related_ids = [];

if (!$is_guest) {
    $rel_result = $conn->query("SELECT * FROM friends WHERE user_id_1 = $current_user_id OR user_id_2 = $current_user_id");
    while($row = $rel_result->fetch_assoc()) {
        $other = ($row['user_id_1'] == $current_user_id) ? $row['user_id_2'] : $row['user_id_1'];
        $related_ids[] = $other;

        if ($row['status'] == 'accepted') { $my_friends[$other] = $row['id']; }
        elseif ($row['user_id_1'] == $current_user_id) { $sent_requests[$other] = $row['id']; }
        else { $incoming_requests[] = ['request_id' => $row['id'], 'user_id' => $other]; }
    }
}

$all_users = [];
$sql = "";

if ($search_query) {
    $safe_search = $conn->real_escape_string($search_query);
    $sql = "SELECT id, first_name, last_name, username, profile_pic, seat_location, location_public FROM users WHERE id != $current_user_id AND (first_name LIKE '%$safe_search%' OR username LIKE '%$safe_search%')";
} elseif (!empty($related_ids)) {
    $ids_str = implode(',', array_unique($related_ids));
    $sql = "SELECT id, first_name, last_name, username, profile_pic, seat_location, location_public FROM users WHERE id IN ($ids_str)";
}

if (!empty($sql)) {
    $res = $conn->query($sql);
    while($row = $res->fetch_assoc()) {
        $row['rel_id'] = $my_friends[$row['id']] ?? ($sent_requests[$row['id']] ?? null);
        $row['is_friend'] = isset($my_friends[$row['id']]);
        $row['is_requested'] = isset($sent_requests[$row['id']]);
        $row['is_incoming'] = false;
        foreach($incoming_requests as $inc) {
            if($inc['user_id'] == $row['id']) {
                $row['is_incoming'] = true;
                $row['rel_id'] = $inc['request_id'];
                break;
            }
        }
        $all_users[] = $row;
    }

    usort($all_users, function($a, $b) {
        if ($a['is_friend'] != $b['is_friend']) return $b['is_friend'] - $a['is_friend'];
        if ($a['is_requested'] != $b['is_requested']) return $b['is_requested'] - $a['is_requested'];
        return strcmp($a['first_name'], $b['first_name']);
    });
}
?>

<?php include 'header.php'; ?>

<style>
    .hero-banner { background: <?php echo $active_gradient; ?> !important; color: white !important; padding: 60px 20px 100px 20px; text-align: center; margin-bottom: -60px; }
    .hero-banner h1 { margin: 0; font-weight: 800; letter-spacing: -1px; }

    .main-container { width: 95%; max-width: 800px; margin: 0 auto 50px auto; position: relative; z-index: 5; }
    .card { background: white; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); padding: 30px; margin-bottom: 25px; border: 1px solid #f1f2f6; }

    .search-wrapper { position: relative; max-width: 600px; margin: 0 auto 40px auto; }
    .search-input { width: 100%; padding: 18px 30px; border-radius: 50px; border: 2px solid rgba(255,255,255,0.4); background: rgba(255,255,255,0.15); color: white; font-size: 1.1em; outline: none; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.05); box-sizing: border-box; backdrop-filter: blur(5px); }
    .search-input::placeholder { color: rgba(255,255,255,0.8); }
    .search-input:focus { background: white; color: #2d3436; border-color: white; box-shadow: 0 12px 35px rgba(0,0,0,0.2); transform: translateY(-2px); }

    #autofill-results { position: absolute; top: 110%; left: 10px; right: 10px; background: white; border-radius: 16px; box-shadow: 0 15px 40px rgba(0,0,0,0.15); z-index: 1000; overflow: hidden; display: none; border: 1px solid #f1f2f6; }

    .suggestion-item { display: flex; align-items: center; padding: 12px 20px; text-decoration: none; color: inherit; border-bottom: 1px solid #f8f9fa; transition: background 0.2s; cursor: pointer; }
    .suggestion-item:hover { background: #f8f9fa; }

    .btn { padding: 10px 24px; border-radius: 30px; border: none; cursor: pointer; font-size: 0.85em; font-weight: 800; transition: all 0.2s ease; display: inline-flex; align-items: center; min-width: 140px; justify-content: center; text-decoration: none; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }

    .btn-friend { background: white; border: 2px solid #00b894; color: #00b894; }
    .btn-friend .hover-text { display: none; }
    .btn-friend:hover { background: #feeaea; border-color: #d63031; color: #d63031; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(0,0,0,0.1); }
    .btn-friend:hover .default-text { display: none; }
    .btn-friend:hover .hover-text { display: inline; }

    .btn-requested { background: white; border: 2px solid #b2bec3; color: #b2bec3; }
    .btn-requested .hover-text { display: none; }
    .btn-requested:hover { background: #f1f2f6; border-color: #636e72; color: #636e72; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(0,0,0,0.1); }
    .btn-requested:hover .default-text { display: none; }
    .btn-requested:hover .hover-text { display: inline; }

    .btn-add { background: white; border: 2px solid <?php echo $accent; ?>; color: <?php echo $accent; ?>; }
    .btn-add:hover { background: <?php echo $accent; ?>; color: white; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(0,0,0,0.15); }
        
    .btn-accept { background: <?php echo $accent; ?>; color: white; transition: all 0.2s ease; }
    .btn-accept:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(0,0,0,0.15); opacity: 0.9; }
    
    .friend-card { display: flex; justify-content: space-between; padding: 20px 15px; border-bottom: 1px solid #f8f9fa; align-items: center; transition: all 0.2s ease; border-radius: 12px; margin-bottom: 5px; }
    .friend-card:hover { background-color: #fcfcfd; transform: translateX(4px); box-shadow: -4px 4px 15px rgba(0,0,0,0.02); }
    .friend-card:last-child { border-bottom: none; }
        
    .avatar { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; margin-right: 20px; background: #eee; border: 3px solid #f8f9fa; box-shadow: 0 4px 10px rgba(0,0,0,0.08); transition: transform 0.2s, border-color 0.2s; }
    .friend-card:hover .avatar { transform: scale(1.05); border-color: <?php echo $accent; ?>; }
    .username-link { text-decoration: none; color: inherit; transition: opacity 0.2s; cursor: pointer; }
    .username-link:hover { opacity: 0.7; color: <?php echo $accent; ?>; }
</style>

<div class="hero-banner">
    <h1>Find Friends</h1>
    <p>Stay connected and see where your crew is sitting.</p>
</div>

<div class="main-container">
    <div class="search-wrapper">
        <form method="GET" autocomplete="off" onsubmit="return true;">
            <input type="text" id="user-search" name="search" class="search-input" placeholder="Search names or @usernames..." value="<?php echo htmlspecialchars($search_query); ?>">
        </form>
        <div id="autofill-results"></div>
    </div>

    <?php if (!empty($incoming_requests)): ?>
        <div class="card" style="border-left: 6px solid <?php echo $accent; ?>; background: #fffafa;">
            <h3 style="margin:0 0 15px 0; color: <?php echo $accent; ?>;">🔔 Pending Invites</h3>
            <?php foreach($incoming_requests as $req):
                $sender = $conn->query("SELECT first_name, username FROM users WHERE id = ".$req['user_id'])->fetch_assoc();
            ?>
            <div class="friend-card" style="border:none;">
                <span><strong><?php echo htmlspecialchars($sender['first_name'] ?? ''); ?></strong> wants to connect.</span>
                <div>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="accept_request_id" value="<?php echo $req['request_id']; ?>">
                        <button type="submit" class="btn btn-accept">Accept</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card" style="padding: 0 30px;">
        <?php if (empty($all_users) && empty($incoming_requests) && !$search_query): ?>
            <div style="text-align: center; padding: 40px 0;">
                <h3 style="color: #636e72; margin-bottom: 10px;">No friends here yet!</h3>
                <p style="color: #b2bec3; font-size: 0.9em;">Use the search bar above to find and add your crew.</p>
            </div>
        <?php elseif (empty($all_users) && $search_query): ?>
            <div style="text-align: center; padding: 40px 0;">
                <h3 style="color: #636e72; margin-bottom: 10px;">No users found.</h3>
                <p style="color: #b2bec3; font-size: 0.9em;">Try searching for a different name or username.</p>
            </div>
        <?php else: ?>
            <?php foreach($all_users as $row): ?>
                <div class="friend-card">
                    <div style="display: flex; align-items: center;">
                        <a href="profile.php?id=<?php echo $row['id']; ?>">
                            <img src="<?php echo !empty($row['profile_pic']) ? $row['profile_pic'] : 'default_avatar.png'; ?>" class="avatar">
                        </a>
                        <div>
                            <a href="profile.php?id=<?php echo $row['id']; ?>" class="username-link">
                                <strong style="color: #2d3436; font-size:1.1em;"><?php echo htmlspecialchars($row['first_name'] . " " . $row['last_name']); ?></strong>
                            </a>
                            <?php if($row['username']): ?><span style="color:<?php echo $accent; ?>; font-size:0.8em; font-weight:700;">@<?php echo htmlspecialchars($row['username']); ?></span><?php endif; ?>
                            <br>

                            <?php if ($row['is_friend'] && $row['location_public'] == 1 && !empty($row['seat_location'])): ?>
                                <span style="color: #00b894; font-weight: 700; font-size: 0.85em; display:block; margin-top:4px;">📍 <?php echo htmlspecialchars($row['seat_location']); ?></span>
                            <?php elseif ($row['is_friend'] && $row['location_public'] == 1): ?>
                                <span style="color: #b2bec3; font-size: 0.8em; display:block; margin-top:4px;">📍 Seat not tagged</span>
                            <?php elseif ($row['is_friend']): ?>
                                <span style="color: #b2bec3; font-size: 0.8em; display:block; margin-top:4px;">🔒 Location private</span>
                            <?php else: ?>
                                <span style="color: #b2bec3; font-size: 0.8em; display:block; margin-top:4px;">🔒 Seat hidden</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                    <?php if ($row['is_friend']): ?>
                        <form method="POST">
                            <input type="hidden" name="remove_rel_id" value="<?php echo $row['rel_id']; ?>">
                            <button type="submit" class="btn btn-friend" onclick="return confirm('Remove friend?')">
                                <span class="default-text">✓ Friends</span>
                                <span class="hover-text">Unfriend</span>
                            </button>
                        </form>
                    <?php elseif ($row['is_requested']): ?>
                        <form method="POST">
                            <input type="hidden" name="remove_rel_id" value="<?php echo $row['rel_id']; ?>">
                            <button type="submit" class="btn btn-requested">
                                <span class="default-text">Requested</span>
                                <span class="hover-text">Cancel</span>
                            </button>
                        </form>
                    <?php elseif ($row['is_incoming']): ?>
                        <form method="POST">
                            <input type="hidden" name="accept_request_id" value="<?php echo $row['rel_id']; ?>">
                            <button type="submit" class="btn btn-accept">Accept</button>
                        </form>
                    <?php else: ?>
                        <?php if ($is_guest): ?>
                            <button type="button" class="btn btn-add" onclick="requireLogin('add friends')">+ Add Friend</button>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="send_request_to" value="<?php echo $row['id']; ?>">
                                <button type="submit" class="btn btn-add">+ Add Friend</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
const searchInput = document.getElementById('user-search');
const resultsBox = document.getElementById('autofill-results');

searchInput.addEventListener('input', async () => {
    const val = searchInput.value;
    if (val.length < 2) {
        resultsBox.style.display = 'none';
        return;
    }

    try {
        const res = await fetch(`friends.php?term=${val}`);
        const data = await res.json();

        if (data.length > 0) {
            resultsBox.innerHTML = data.map(u => `
                <div onmousedown="window.location.href='profile.php?id=${u.id}';" class="suggestion-item">
                    <img src="${u.profile_pic || 'default_avatar.png'}" style="width:35px; height:35px; border-radius:50%; margin-right:15px; object-fit:cover;">
                    <div>
                        <strong style="font-size:0.95em;">${u.first_name} ${u.last_name}</strong><br>
                        <small style="color:<?php echo $accent; ?>; font-weight:700;">@${u.username}</small>
                    </div>
                </div>
            `).join('');
            resultsBox.style.display = 'block';
        } else {
            resultsBox.style.display = 'none';
        }
    } catch (e) { console.log(e); }
});

document.addEventListener('click', (e) => {
    if (e.target !== searchInput && !resultsBox.contains(e.target)) {
        resultsBox.style.display = 'none';
    }
});
</script>

<?php include 'footer.php'; ?>
