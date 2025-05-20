<?php
session_start();
include '../db_config.php';

// Check if user is logged in and is an admin (role_id = 1)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header("Location: ../index.php");
    exit();
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d') . '.csv"');

// Create a file handle for output
$output = fopen('php://output', 'w');

// Determine which columns to include
$include_passwords = isset($_GET['include_passwords']) && $_GET['include_passwords'] == 'on';

// Define CSV header row
$headers = ['id_number', 'email', 'name', 'role_id', 'rfid_tag', 'section'];
if ($include_passwords) {
    $headers = array_merge(['id_number', 'email', 'password', 'name', 'role_id', 'rfid_tag', 'section']);
}

// Write header row to CSV
fputcsv($output, $headers);

// Build the SQL query with filters
$sql = "SELECT id_number, email, " . ($include_passwords ? "password, " : "") . "name, role_id, rfid_tag, section FROM user WHERE 1=1";
$params = [];
$types = "";

// Add role filter if provided
if (!empty($_GET['role'])) {
    $sql .= " AND role_id = ?";
    $params[] = $_GET['role'];
    $types .= "i";
}

// Order by role_id and name
$sql .= " ORDER BY role_id, name";

// Prepare and execute the query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Write data rows
while ($row = $result->fetch_assoc()) {
    $row_data = [
        $row['id_number'],
        $row['email']
    ];
    
    if ($include_passwords) {
        $row_data[] = $row['password'];
    }
    
    $row_data[] = $row['name'];
    $row_data[] = $row['role_id'];
    $row_data[] = $row['rfid_tag'] ?? '';
    $row_data[] = $row['section'] ?? '';
    
    fputcsv($output, $row_data);
}

// Close the file handle
fclose($output);
exit;