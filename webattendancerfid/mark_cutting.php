<?php
include 'db_config.php';

// Get current timestamp with 15-minute grace period
$date = new DateTime();
$date->modify('+8 hours');  // Adjust to GMT+8
$current_time = $date->format('H:i:s');
$current_date = $date->format('Y-m-d');
$current_day = $date->format('l'); // Get current day of the week

// Find classes that ended 10+ minutes ago
$query = "SELECT schedule_id, section, end_time FROM schedules 
          WHERE day = ? 
          AND TIMESTAMPDIFF(MINUTE, end_time, DATE_ADD(NOW(), INTERVAL 8 HOUR)) >= 10";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $current_day);
$stmt->execute();
$schedules_result = $stmt->get_result();

while ($schedule = $schedules_result->fetch_assoc()) {
    $schedule_id = $schedule['schedule_id'];
    $section_id = $schedule['section'];
    $end_time = $schedule['end_time'];

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
        $attendance_check_query = "SELECT check_out_time 
                                   FROM attendance 
                                   WHERE user_id = ? 
                                   AND schedule_id = ? 
                                   AND date = ?";
        $stmt_attendance_check = $conn->prepare($attendance_check_query);
        $stmt_attendance_check->bind_param("iis", $user_id, $schedule_id, $current_date);
        $stmt_attendance_check->execute();
        $attendance_check_result = $stmt_attendance_check->get_result();

        if ($attendance_check_result->num_rows > 0) {
            $attendance_record = $attendance_check_result->fetch_assoc();
            $check_out_time = $attendance_record['check_out_time'];

            // If the student didn't check out after the end_time, mark them as "Cutting"
            if (empty($check_out_time) || $check_out_time > $end_time) {
                $status = 'Absent'; // Store the status in a variable
                $mark_cutting_query = "UPDATE attendance 
                                        SET status = ? 
                                        WHERE user_id = ? 
                                        AND schedule_id = ? 
                                        AND date = ?";
                $stmt_mark_cutting = $conn->prepare($mark_cutting_query);
                $stmt_mark_cutting->bind_param("siis", $status, $user_id, $schedule_id, $current_date);
                $stmt_mark_cutting->execute();
            }
        }
    }
}

echo "Cutting students marked successfully";
?>