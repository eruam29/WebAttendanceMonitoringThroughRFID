<?php
include 'db_config.php'; // Use the database connection file

$rfid_tag = $_POST['rfid_tag'] ?? null;

// Get current time (Adjusted +8 Hours for Timezone)
$date = new DateTime();
$date->modify('+8 hours');  // Adjusts time to GMT+8
$current_datetime = $date->format('Y-m-d H:i:s');  

if (!$rfid_tag) {
    echo "Error: No RFID tag received.";
    exit;
}

// Check if the tag already exists in the system
$stmt = $conn->prepare("SELECT user_id, name FROM user WHERE rfid_tag = ?");
$stmt->bind_param("s", $rfid_tag);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    // Tag already registered
    $stmt->bind_result($user_id, $name);
    $stmt->fetch();
    echo "Tag already registered to user: $name (ID: $user_id)";
    $stmt->close();
    exit;
}
$stmt->close();

// Check if the tag exists in pending_tags table
$stmt = $conn->prepare("SELECT id FROM pending_tags WHERE rfid_tag = ?");
$stmt->bind_param("s", $rfid_tag);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    // Update the timestamp if already pending
    $stmt->close();
    $stmt = $conn->prepare("UPDATE pending_tags SET registration_time = ? WHERE rfid_tag = ?");
    $stmt->bind_param("ss", $current_datetime, $rfid_tag);
    $stmt->execute();
    echo "Tag already in registration queue. Timestamp updated.";
} else {
    // Insert new tag into pending_tags table
    $stmt->close();
    $stmt = $conn->prepare("INSERT INTO pending_tags (rfid_tag, registration_time) VALUES (?, ?)");
    $stmt->bind_param("ss", $rfid_tag, $current_datetime);
    
    if ($stmt->execute()) {
        echo "SUCCESS: New tag registered successfully! Tag ID: $rfid_tag";
    } else {
        echo "ERROR: Failed to register tag. " . $conn->error;
    }
}

$stmt->close();
$conn->close();
?>