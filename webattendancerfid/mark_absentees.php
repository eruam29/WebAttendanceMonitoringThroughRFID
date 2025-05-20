<?php
include 'db_config.php';

// Get current timestamp with 15-minute grace period
$date = new DateTime();
$date->modify('+8 hours');  // Adjust to GMT+8
$current_time = $date->format('H:i:s');
$current_date = $date->format('Y-m-d');
$current_day = $date->format('l'); // Get current day of the week

// Find classes that ended 10+ minutes ago
$query = "SELECT schedule_id, section FROM schedules 
          WHERE day = ? 
          AND TIMESTAMPDIFF(MINUTE, end_time, DATE_ADD(NOW(), INTERVAL 8 HOUR)) >= 10";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $current_day);
$stmt->execute();
$schedules_result = $stmt->get_result();

while ($schedule = $schedules_result->fetch_assoc()) {
    $schedule_id = $schedule['schedule_id'];
    $section_id = $schedule['section'];

    // Fetch all students enrolled in the current section
    $enrollment_query = "SELECT e.user_id 
                         FROM enrollment e 
                         WHERE e.schedule_id = ?";
    $stmt_enrollment = $conn->prepare($enrollment_query);
    $stmt_enrollment->bind_param("i", $schedule_id);
    $stmt_enrollment->execute();
    $enrolled_students = $stmt_enrollment->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($enrolled_students as $student) {
        $user_id = $student['user_id'];

        // Check if the student has an attendance record for this schedule and date
        $attendance_check_query = "SELECT 1 
                                   FROM attendance 
                                   WHERE user_id = ? 
                                   AND schedule_id = ? 
                                   AND date = ?";
        $stmt_attendance_check = $conn->prepare($attendance_check_query);
        $stmt_attendance_check->bind_param("iis", $user_id, $schedule_id, $current_date);
        $stmt_attendance_check->execute();
        $attendance_check_result = $stmt_attendance_check->get_result();

        // If no attendance record exists, mark the student as absent
        if ($attendance_check_result->num_rows === 0) {
            $status = 'Absent'; // Store the status in a variable
            $mark_absent_query = "INSERT INTO attendance (user_id, schedule_id, date, status)
                                  VALUES (?, ?, ?, ?)";
            $stmt_mark_absent = $conn->prepare($mark_absent_query);
            $stmt_mark_absent->bind_param("iiss", $user_id, $schedule_id, $current_date, $status);
            $stmt_mark_absent->execute();
        }
    }
}

echo "Absent students marked successfully.";
?>