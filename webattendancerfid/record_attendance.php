<?php
include 'db_config.php'; // Use the correct database connection file

$rfid_tag = $_POST['rfid_tag'] ?? null;

// Get current day and time (Adjusted +8 Hours for Timezone)
$date = new DateTime();
$date->modify('+8 hours');  // ✅ Adjusts time to GMT+8
$current_day = $date->format('l');  
$current_time = $date->format('H:i:s');  
$current_date = $date->format('Y-m-d'); 

if (!$rfid_tag) {
    echo "Error: No RFID tag received.";
    exit;
}

// Get user based on RFID tag
$stmt = $conn->prepare("SELECT user_id FROM user WHERE rfid_tag = ?");
$stmt->bind_param("s", $rfid_tag);
$stmt->execute();
$stmt->store_result(); // Store the result set

if ($stmt->num_rows === 0) {
    echo "ERROR: Tag not registered to any student.";
    $stmt->close();
    exit;
}

$stmt->bind_result($user_id);
$stmt->fetch();
$stmt->close(); // Close the statement to free the result set

// Get the user's enrolled sections
$stmt = $conn->prepare("
    SELECT e.schedule_id 
    FROM enrollment e
    WHERE e.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->store_result(); // Store the result set

if ($stmt->num_rows === 0) {
    echo "ERROR: Student not enrolled in any section.";
    $stmt->close();
    exit;
}

$stmt->bind_result($schedule_id);
$enrolled_schedules = [];
while ($stmt->fetch()) {
    $enrolled_schedules[] = $schedule_id;
}
$stmt->close(); // Close the statement to free the result set

// Find the current class based on the current time
$schedule = null;
foreach ($enrolled_schedules as $schedule_id) {
    // Fetch the schedule details
    $stmt = $conn->prepare("
        SELECT s.schedule_id, s.section, s.start_time, s.end_time, 
               s.checkin_grace_period, s.checkout_grace_period 
        FROM schedules s
        WHERE s.schedule_id = ? AND s.day = ?
    ");
    $stmt->bind_param("is", $schedule_id, $current_day);
    $stmt->execute();
    $stmt->store_result(); // Store the result set

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($schedule_id, $section, $start_time, $end_time, $checkin_grace_period, $checkout_grace_period);
        $stmt->fetch();
        
        // Set default grace periods if not specified (fallback to 10 minutes)
        $checkin_grace_period = $checkin_grace_period ?? 10;
        $checkout_grace_period = $checkout_grace_period ?? 10;

        // Check if the current time is within the class time (with grace period buffer)
        if (strtotime($current_time) >= strtotime($start_time) && strtotime($current_time) <= strtotime($end_time) + ($checkout_grace_period * 60)) {
            $schedule = [
                'schedule_id' => $schedule_id,
                'section' => $section,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'checkin_grace_period' => $checkin_grace_period,
                'checkout_grace_period' => $checkout_grace_period
            ];
            break; // Exit the loop once we find the current class
        }
    }
    $stmt->close(); // Close the statement to free the result set
}

if (!$schedule) {
    echo "No class scheduled right now.";
    exit;
}

// Check if the student already checked in today
$stmt = $conn->prepare("SELECT * FROM attendance WHERE user_id = ? AND schedule_id = ? AND date = ?");
$stmt->bind_param("iis", $user_id, $schedule['schedule_id'], $current_date);
$stmt->execute();
$stmt->store_result(); // Store the result set

if ($stmt->num_rows === 0) {
    // Determine status (Present, Late) using configured grace period
    $late_threshold = strtotime($schedule['start_time']) + ($schedule['checkin_grace_period'] * 60);
    $status = (strtotime($current_time) < $late_threshold) ? 'Present' : 'Late';

    // Insert new attendance record
    $stmt = $conn->prepare("INSERT INTO attendance (user_id, schedule_id, date, check_in_time, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $user_id, $schedule['schedule_id'], $current_date, $current_time, $status);
    $stmt->execute();

    echo "Check-in recorded. Status: $status";
} else {
    // Check if current time is within the configured grace period before or after class end time
    $checkout_start = date("H:i:s", strtotime($schedule['end_time']) - ($schedule['checkout_grace_period'] * 60)); 
    $checkout_end = date("H:i:s", strtotime($schedule['end_time']) + ($schedule['checkout_grace_period'] * 60));

    if ($current_time >= $checkout_start && $current_time <= $checkout_end) {
        // Update checkout time
        $stmt = $conn->prepare("UPDATE attendance SET check_out_time = ? WHERE user_id = ? AND schedule_id = ? AND date = ?");
        $stmt->bind_param("siis", $current_time, $user_id, $schedule['schedule_id'], $current_date);
        $stmt->execute();

        echo "Check-out recorded.";
    } else {
        echo "Check-out is only allowed " . $schedule['checkout_grace_period'] . " minutes before or after class ends.";
    }
}

$stmt->close(); // Close the statement to free the result set
?>