<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db_connect.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$my_id    = $_SESSION['user_id'];
$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
if (!$event) { die("Event not found."); }

// Get attendees AND their seats
$att_stmt = $conn->prepare("
    SELECT u.id, u.first_name, u.last_name, u.username, u.profile_pic,
           r.seat_number, r.share_seat
    FROM checkins c
    JOIN users u ON c.user_id = u.id
    LEFT JOIN rsvps r ON r.user_id = u.id AND r.event_id = c.event_id
    WHERE c.event_id = ?
");

// Safety Net: If the query fails, tell us exactly why
if (!$att_stmt) {
    die("<div style='padding:40px; font-family:sans-serif;'><h2>Database Error 🚨</h2><p>It looks like the seat columns don't exist yet! Run <b>php setup_seat.php</b> first.</p><p>Technical details: " . $conn->error . "</p></div>");
}

$att_stmt->bind_param("i", $event_id);
$att_stmt->execute();
$attendees = $att_stmt->get_result();

// Get my friends list so we know who to show seats for
$friends_set = [];
$fr = $conn->prepare("
    SELECT IF(user_id_1 = ?, user_id_2, user_id_1) AS friend_id
    FROM friends
    WHERE (user_id_1 = ? OR user_id_2 = ?) AND status = 'accepted'
");
$fr->bind_param("iii", $my_id, $my_id, $my_id);
$fr->execute();
$fr_res = $fr->get_result();
while ($row = $fr_res->fetch_assoc()) {
    $friends_set[$row['friend_id']] = true;
}
$fr->close();

$espn_date = date("Ymd", strtotime($event['event_date']));
$league    = $event['league'] ?? '';
?>
<?php include 'header.php'; ?>

<style>
    .box-score-card { background: white; border-radius: 16px; box-shadow: 0 4px 16px rgba(0,0,0,0.06); margin-bottom: 30px; padding: 25px; text-align: center; display: none; border-top: 5px solid #000; }
    .score-container { display: flex; justify-content: center; align-items: center; gap: 30px; margin-top: 15px; }
    .team-col { display: flex; flex-direction: column; align-items: center; width: 120px; }
    .team-logo { width: 70px; height: 70px; object-fit: contain; margin-bottom: 10px; }
    .team-name { font-weight: 800; font-size: 16px; color: #2d3436; text-align: center; }
    .team-score { font-size: 36px; font-weight: 900; color: #1a1a2e; margin-top: 5px; }
    .score-divider { font-size: 20px; font-weight: 800; color: #b2bec3; }
    .game-result { margin-top: 20px; display: inline-block; background: #f1f2f6; padding: 8px 20px; border-radius: 20px; font-size: 14px; font-weight: 700; color: #636e72; }
    .attendee-card { display: flex; align-items: center; gap: 15px; padding: 15px; background: white; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); text-decoration: none; color: #2d3436; transition: all 0.2s; }
    .attendee-card:hover { box-shadow: 0 6px 16px rgba(0,0,0,0.1); transform: translateY(-2px); }
    .attendee-avatar { width: 40px; height: 40px; background: #e0e0e0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; overflow: hidden; color: #555; flex-shrink: 0; }
    .attendee-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .seat-badge { display: inline-block; background: #f0f9ff; color: #0984e3; border: 1px solid #bfdbfe; border-radius: 6px; padding: 3px 8px; font-size: 11px; font-weight: 700; margin-top: 4px; }
    .seat-private { display: inline-block; color: #b2bec3; font-size: 11px; font-weight: 600; margin-top: 4px; padding: 3px 0; }
</style>

<div style="padding: 60px 20px; text-align: center; background: #f8f9fa;">
    <h1 style="margin:0; font-weight:800; color: #2d3436;"><?php echo htmlspecialchars($event['event_name']); ?></h1>
    <p style="color:#636e72; font-weight:700;">📍 <?php echo htmlspecialchars($event['location_name']); ?> | 📅 <?php echo date("M j, Y", strtotime($event['event_date'])); ?></p>
</div>

<div style="max-width: 900px; margin: 40px auto; padding: 20px; min-height: 50vh;">

    <div id="box-score-container" class="box-score-card">
        <h3 id="box-score-loading" style="color: #636e72; font-weight: 600; margin: 0;">Loading Box Score... 🔄</h3>
        <div id="box-score-content" style="display: none;"></div>
    </div>

    <h2 style="border-bottom: 2px solid #f1f2f6; padding-bottom: 10px; color: #2d3436;">Fans Who Attended</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 15px; margin-top: 20px;">
        <?php if ($attendees->num_rows > 0): ?>
            <?php while($user = $attendees->fetch_assoc()):
                $is_me_row  = ($user['id'] == $my_id);
                $is_friend  = isset($friends_set[$user['id']]);
                
                // Privacy Logic
                $can_see_seat = ($is_me_row || $is_friend) && !empty($user['seat_number']) && $user['share_seat'];
                $seat_private = ($is_me_row || $is_friend) && !empty($user['seat_number']) && !$user['share_seat'];
            ?>
                <a href="profile.php?id=<?php echo $user['id']; ?>" class="attendee-card">
                    <div class="attendee-avatar">
                        <?php if(!empty($user['profile_pic']) && $user['profile_pic'] !== 'default_avatar.png'): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="">
                        <?php else: ?>
                            <?php echo strtoupper(substr($user['first_name'],0,1) . substr($user['last_name'],0,1)); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div style="font-weight: 700; font-size: 14px;"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div style="font-size: 12px; color: #b2bec3;">@<?php echo htmlspecialchars($user['username']); ?></div>
                        
                        <?php if ($can_see_seat): ?>
                            <div class="seat-badge"><?php echo htmlspecialchars($user['seat_number']); ?></div>
                        <?php elseif ($seat_private): ?>
                            <div class="seat-private">🔒 Seat private</div>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="color: #636e72;">No attendees recorded for this game yet.</p>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", async function() {
    const rawLeague = "<?php echo addslashes($league); ?>";
    const eventDate = "<?php echo $espn_date; ?>";
    const eventName = "<?php echo strtolower(addslashes($event['event_name'])); ?>";

    let sport = '', espnLeague = '';
    if (rawLeague === 'NBA') { sport = 'basketball'; espnLeague = 'nba'; }
    else if (rawLeague === 'NFL') { sport = 'football'; espnLeague = 'nfl'; }
    else if (rawLeague === 'NCAAF') { sport = 'football'; espnLeague = 'college-football'; }
    else if (rawLeague === 'NCAAB') { sport = 'basketball'; espnLeague = 'mens-college-basketball'; }

    if (sport && espnLeague) {
        document.getElementById('box-score-container').style.display = 'block';
        try {
            const url = `https://site.api.espn.com/apis/site/v2/sports/${sport}/${espnLeague}/scoreboard?dates=${eventDate}`;
            const response = await fetch(url);
            const data = await response.json();
            let matchedGame = null;
            for (const game of data.events) {
                const t1 = game.competitions[0].competitors[0].team.shortDisplayName.toLowerCase();
                const t2 = game.competitions[0].competitors[1].team.shortDisplayName.toLowerCase();
                const t1Full = game.competitions[0].competitors[0].team.name.toLowerCase();
                const t2Full = game.competitions[0].competitors[1].team.name.toLowerCase();
                if (eventName.includes(t1) || eventName.includes(t2) || eventName.includes(t1Full) || eventName.includes(t2Full)) {
                    matchedGame = game; break;
                }
            }
            const loadingEl = document.getElementById('box-score-loading');
            const contentEl = document.getElementById('box-score-content');
            if (matchedGame && matchedGame.competitions[0].status.type.completed) {
                const comp = matchedGame.competitions[0];
                const home = comp.competitors.find(c => c.homeAway === 'home') || comp.competitors[0];
                const away = comp.competitors.find(c => c.homeAway === 'away') || comp.competitors[1];
                const homeScore = parseInt(home.score);
                const awayScore = parseInt(away.score);
                let resultText = homeScore > awayScore
                    ? `🏆 ${home.team.shortDisplayName} won by ${homeScore - awayScore}`
                    : awayScore > homeScore
                        ? `🏆 ${away.team.shortDisplayName} won by ${awayScore - homeScore}`
                        : "🤝 Game ended in a tie";
                const winnerColor = homeScore > awayScore ? home.team.color : away.team.color;
                document.getElementById('box-score-container').style.borderTopColor = `#${winnerColor}`;
                loadingEl.style.display = 'none';
                contentEl.style.display = 'block';
                contentEl.innerHTML = `
                    <div style="font-size:12px;font-weight:800;color:#b2bec3;text-transform:uppercase;letter-spacing:1px;">Final Score</div>
                    <div class="score-container">
                        <div class="team-col">
                            <img src="${away.team.logo}" class="team-logo" alt="${away.team.name}">
                            <div class="team-name">${away.team.shortDisplayName}</div>
                            <div class="team-score" style="color:${awayScore > homeScore ? '#2d3436' : '#b2bec3'}">${awayScore}</div>
                        </div>
                        <div class="score-divider">@</div>
                        <div class="team-col">
                            <img src="${home.team.logo}" class="team-logo" alt="${home.team.name}">
                            <div class="team-name">${home.team.shortDisplayName}</div>
                            <div class="team-score" style="color:${homeScore > awayScore ? '#2d3436' : '#b2bec3'}">${homeScore}</div>
                        </div>
                    </div>
                    <div class="game-result">${resultText}</div>
                `;
            } else if (matchedGame && !matchedGame.competitions[0].status.type.completed) {
                loadingEl.innerText = "Game has not finished yet! ⏳";
            } else {
                loadingEl.innerText = "Box score not available for this game.";
            }
        } catch (error) {
            document.getElementById('box-score-loading').innerText = "Could not load box score data.";
        }
    }
});
</script>

<?php include 'footer.php'; ?>