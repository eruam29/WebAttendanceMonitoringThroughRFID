<?php
session_start();

// Check if user is logged in and is an admin (role_id = 1)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header("Location: ../index.php");
    exit();
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="user_import_template.csv"');

// Create a file handle for output
$output = fopen('php://output', 'w');

// Define CSV header row
$headers = ['id_number', 'email', 'password', 'name', 'role_id', 'rfid_tag', 'section'];

// Write header row to CSV
fputcsv($output, $headers);

// Add a sample row for student with section
$sample_row = [
    'student123',
    'student@example.com',
    'password123',
    'John Doe',
    '3',
    '123456789',
    '401I'
];
fputcsv($output, $sample_row);

// Close the file handle
fclose($output);
exit;