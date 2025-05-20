<?php
session_start();
include '../db_config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header("Location: ../index.php");
    exit();
}

// Get filter parameters
$section_filter = $_POST['section'] ?? '';
$date_from = $_POST['date_from'] ?? '';
$date_to = $_POST['date_to'] ?? '';

// Function to get attendance summary data (same as in attendance_summary.php)
function getAttendanceSummary($conn, $section_filter, $date_from, $date_to) {
    $query = "SELECT 
                s.section,
                c.course_name,
                COUNT(DISTINCT a.user_id) as total_students,
                SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN a.status = 'Absent' OR a.status IS NULL THEN 1 ELSE 0 END) as absent_count
              FROM schedules s
              JOIN courses c ON s.course_id = c.course_id
              LEFT JOIN attendance a ON s.schedule_id = a.schedule_id
              WHERE 1=1";
    
    $types = "";
    $params = [];
    
    if (!empty($section_filter)) {
        $query .= " AND s.schedule_id = ?";
        $types .= "i";
        $params[] = $section_filter;
    }
    
    if (!empty($date_from) && !empty($date_to)) {
        $query .= " AND a.date BETWEEN ? AND ?";
        $types .= "ss";
        $params[] = $date_from;
        $params[] = $date_to;
    }
    
    $query .= " GROUP BY s.section, c.course_name";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $summary_data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $summary_data;
}

// Get the data
$summary_data = getAttendanceSummary($conn, $section_filter, $date_from, $date_to);

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="attendance_summary_' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add CSV headers
fputcsv($output, ['Section', 'Course', 'Total Students', 'Present', 'Late', 'Absent']);

// Add data rows
foreach ($summary_data as $row) {
    fputcsv($output, [
        $row['section'],
        $row['course_name'],
        $row['total_students'],
        $row['present_count'],
        $row['late_count'],
        $row['absent_count']
    ]);
}

// Close the output stream
fclose($output);
exit();
?>
