<?php
session_start();
include '../db_config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header("Location: ../index.php");
    exit();
}

$course_id = $_GET['id'] ?? null;

if (!$course_id) {
    $modal_type = "error";
    $modal_message = "Invalid course ID.";
} else {
    // Get course name for the message
    $stmt = $conn->prepare("SELECT course_name FROM courses WHERE course_id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $course = $result->fetch_assoc();
    $course_name = $course ? $course['course_name'] : 'Course';
    
    // Check if course is used in any schedules
    $check_stmt = $conn->prepare("SELECT COUNT(*) as schedule_count FROM schedules WHERE course_id = ?");
    $check_stmt->bind_param("i", $course_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $schedule_data = $check_result->fetch_assoc();
    
    if ($schedule_data['schedule_count'] > 0) {
        // Course is being used in schedules
        $modal_type = "error";
        $modal_message = "Cannot delete \"$course_name\". This course is currently used in " . $schedule_data['schedule_count'] . " schedule(s). Please remove all schedules using this course first.";
    } else {
        // Delete the course
        $stmt = $conn->prepare("DELETE FROM courses WHERE course_id = ?");
        $stmt->bind_param("i", $course_id);

        if ($stmt->execute()) {
            $modal_type = "success";
            $modal_message = "\"$course_name\" has been deleted successfully.";
        } else {
            $modal_type = "error";
            $modal_message = "Error deleting course: " . $conn->error;
        }
    }
}

// Fetch admin's name from database for the sidebar
$stmt = $conn->prepare("SELECT name FROM user WHERE id_number = ? AND role_id = 1");
$stmt->bind_param("s", $_SESSION['user_id']);
$stmt->execute();
$result_admin = $stmt->get_result();
$admin = $result_admin->fetch_assoc();
$stmt->close();

// Add error handling if admin not found
if (!$admin) {
    session_destroy();
    header("Location: ../index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Course</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/tab_logo.png">
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .modal {
            display: block;
            position: fixed;
            z-index: 50;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 90%;
            position: relative;
        }
        
        .modal-header {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .modal-header.success {
            background-color: #4f46e5;
            color: white;
            border-top-left-radius: 0.5rem;
            border-top-right-radius: 0.5rem;
        }
        
        .modal-header.error {
            background-color: #ef4444;
            color: white;
            border-top-left-radius: 0.5rem;
            border-top-right-radius: 0.5rem;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            padding: 1rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Result Modal -->
    <div id="resultModal" class="modal">
        <div class="modal-content">
            <div id="modalHeader" class="modal-header <?= $modal_type ?>">
                <h3 id="modalTitle" class="text-lg font-bold">
                    <?= $modal_type === "success" ? "Success" : "Error" ?>
                </h3>
            </div>
            <div class="modal-body">
                <p id="modalMessage"><?= htmlspecialchars($modal_message) ?></p>
            </div>
            <div class="modal-footer">
                <button id="modalButton" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">OK</button>
            </div>
        </div>
    </div>

    <script>
        // Modal functionality
        const modalButton = document.getElementById('modalButton');
        
        // Close modal when clicking the button
        if (modalButton) {
            modalButton.addEventListener('click', function() {
                window.location = 'display_course.php';
            });
        }
        
        // Prevent closing modal with ESC key
        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>