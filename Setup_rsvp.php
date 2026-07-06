<?php
// TURN ON ERROR REPORTING
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'db_connect.php';

// Create the rsvps table
$sql = "CREATE TABLE IF NOT EXISTS rsvps (
    rsvp_id    INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    event_id   INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rsvp (user_id, event_id),
    FOREIGN KEY (user_id)  REFERENCES users(id)        ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "✅ RSVP Table Created! The rsvps table is ready to go.\n";
} else {
    die("Error creating table: " . $conn->error . "\n");
}
?>
```

Then in your VS Code terminal run:
```
php setup_rsvp.php