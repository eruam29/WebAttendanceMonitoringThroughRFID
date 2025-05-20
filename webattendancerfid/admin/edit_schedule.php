<?php
session_start();
include '../db_config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header("Location: ../index.php");
    exit();
}

// Fetch admin's name from database
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

$schedule_id = $_GET['id'];
$stmt = $conn->prepare("SELECT * FROM schedules WHERE schedule_id = ?");
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$schedule = $stmt->get_result()->fetch_assoc();

// Fetch courses and instructors for dropdowns
$courses = $conn->query("SELECT course_id, course_name FROM courses");
$instructors = $conn->query("SELECT user_id, name FROM user WHERE role_id = 2"); // Assuming role_id = 2 is for instructors

// Fetch unique sections from the user table
$sections_query = "SELECT DISTINCT section FROM user WHERE section IS NOT NULL AND section != '' ORDER BY section";
$sections_result = $conn->query($sections_query);
$sections = [];
while ($section = $sections_result->fetch_assoc()) {
    $sections[] = $section['section'];
}

$show_modal = false;
$modal_type = "";
$modal_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $section = $_POST['section'];
    $course_id = $_POST['course_id'];
    $instructor_id = $_POST['instructor_id'];
    $room = $_POST['room'];
    $day = $_POST['day'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $checkin_grace_period = $_POST['checkin_grace_period'] ?? 10;
    $checkout_grace_period = $_POST['checkout_grace_period'] ?? 10;

    $stmt = $conn->prepare("UPDATE schedules SET section = ?, course_id = ?, instructor_id = ?, room = ?, day = ?, start_time = ?, end_time = ?, checkin_grace_period = ?, checkout_grace_period = ? WHERE schedule_id = ?");
    $stmt->bind_param("siissssiii", $section, $course_id, $instructor_id, $room, $day, $start_time, $end_time, $checkin_grace_period, $checkout_grace_period, $schedule_id);
    
    if ($stmt->execute()) {
        $show_modal = true;
        $modal_type = "success";
        $modal_message = "Schedule updated successfully.";
    } else {
        $show_modal = true;
        $modal_type = "error";
        $modal_message = "Error updating schedule: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Schedule</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/tab_logo.png">
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
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
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <div class="hidden md:flex md:flex-shrink-0">
            <div class="flex flex-col w-64 bg-gradient-to-b from-indigo-800 to-indigo-900">
                <!-- Logo -->
<div class="flex items-center justify-center h-16 px-4 bg-indigo-950">
    <div class="text-xl font-semibold text-white flex items-center">
        <img src="../images/dashboard_logo.png" alt="Dashboard Logo" class="h-8 w-8 mr-2">
        <img src="../images/TECHTRACK.png" alt="TECHTRACK" class="h-6">
    </div>
</div>
                
                <!-- Sidebar Navigation -->
                <div class="flex flex-col flex-grow px-4 mt-5">
                    <nav class="flex-1 space-y-1">
                        <a href="admin_dashboard.php" class="flex items-center px-4 py-3 text-sm font-medium text-indigo-100 rounded-lg hover:bg-indigo-700 transition duration-150">
                            <i class="fas fa-home w-6"></i>
                            <span>Dashboard</span>
                        </a>
                        <a href="display_user.php" class="flex items-center px-4 py-3 mt-1 text-sm font-medium text-indigo-100 rounded-lg hover:bg-indigo-700 transition duration-150">
                            <i class="fas fa-users w-6"></i>
                            <span>Users</span>
                        </a>
                        <a href="display_course.php" class="flex items-center px-4 py-3 mt-1 text-sm font-medium text-indigo-100 rounded-lg hover:bg-indigo-700 transition duration-150">
                            <i class="fas fa-chart-bar w-6"></i>
                            <span>Course</span>
                        </a>
                        <a href="display_schedule.php" class="flex items-center px-4 py-3 mt-1 text-sm font-medium text-white bg-indigo-700 rounded-lg hover:bg-indigo-600 transition duration-150">
                            <i class="fas fa-calendar w-6"></i>
                            <span>Schedule</span>
                        </a>
                        <a href="display_attendance.php" class="flex items-center px-4 py-3 mt-1 text-sm font-medium text-indigo-100 rounded-lg hover:bg-indigo-700 transition duration-150">
                            <i class="fas fa-clipboard-check w-6"></i>
                            <span>Reports</span>
                        </a>
                    </nav>
                    
                    <!-- User Profile -->
                    <div class="flex items-center px-4 py-3 mt-auto mb-6 bg-indigo-950 bg-opacity-50 rounded-lg">
                        <img class="w-10 h-10 rounded-full" src="https://ui-avatars.com/api/?name=<?= urlencode($admin['name']) ?>&background=6366F1&color=fff" alt="User">
                        <div class="ml-3">
                            <p class="text-sm font-medium text-white"><?= htmlspecialchars($admin['name']) ?></p>
                            <p class="text-xs text-indigo-200">Administrator</p>
                        </div>
                        <a href="../logout.php" class="ml-auto text-indigo-300 hover:text-white">
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 overflow-y-auto">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm z-10">
                <div class="px-4 sm:px-6 lg:px-8 py-4">
                    <div class="flex items-center justify-between">
                        <!-- Mobile Menu Button -->
                        <button class="md:hidden p-2 rounded-md text-gray-500 hover:text-gray-900 focus:outline-none">
                            <i class="fas fa-bars"></i>
                        </button>
                        
                        <h1 class="text-lg font-medium text-gray-900">Edit Schedule</h1>
                        
                        <!-- Right Elements -->
                        <div class="flex items-center space-x-4">
                            <div class="md:hidden">
                                <img class="w-8 h-8 rounded-full" src="https://ui-avatars.com/api/?name=<?= urlencode($admin['name']) ?>&background=6366F1&color=fff" alt="User">
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Form Content -->
            <main class="flex-1 p-4 sm:px-6 lg:px-8 bg-gray-50">
                <div class="mb-6">
                    <h1 class="text-2xl font-semibold text-gray-900">Edit Schedule</h1>
                    <p class="mt-1 text-sm text-gray-600">Update the schedule information</p>
                </div>

                <!-- Schedule Form -->
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <form method="POST" class="p-6">
                        <input type="hidden" name="schedule_id" value="<?= $schedule_id ?>">
                        
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <!-- Section -->
                            <div>
                                <label for="section" class="block text-sm font-medium text-gray-700">Section</label>
                                <select id="section" name="section" required
                                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">Select a section</option>
                                    <?php foreach ($sections as $existingSection): ?>
                                        <option value="<?= htmlspecialchars($existingSection) ?>" <?= $existingSection == $schedule['section'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($existingSection) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Course -->
                            <div>
                                <label for="course_id" class="block text-sm font-medium text-gray-700">Course</label>
                                <select id="course_id" name="course_id" required
                                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="" disabled>Select a course</option>
                                    <?php while ($course = $courses->fetch_assoc()) { ?>
                                        <option value="<?= $course['course_id'] ?>" <?= $course['course_id'] == $schedule['course_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($course['course_name']) ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>

                            <!-- Instructor -->
                            <div>
                                <label for="instructor_id" class="block text-sm font-medium text-gray-700">Instructor</label>
                                <select id="instructor_id" name="instructor_id" required
                                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="" disabled>Select an instructor</option>
                                    <?php while ($instructor = $instructors->fetch_assoc()) { ?>
                                        <option value="<?= $instructor['user_id'] ?>" <?= $instructor['user_id'] == $schedule['instructor_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($instructor['name']) ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>

                            <!-- Room -->
                            <div>
                                <label for="room" class="block text-sm font-medium text-gray-700">Room</label>
                                <input type="text" id="room" name="room" value="<?= htmlspecialchars($schedule['room']) ?>" required
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md p-2 border">
                            </div>

                            <!-- Day -->
                            <div>
                                <label for="day" class="block text-sm font-medium text-gray-700">Day</label>
                                <select name="day" id="day" required
                                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">Select Day</option>
                                    <option value="Monday" <?= $schedule['day'] == 'Monday' ? 'selected' : '' ?>>Monday</option>
                                    <option value="Tuesday" <?= $schedule['day'] == 'Tuesday' ? 'selected' : '' ?>>Tuesday</option>
                                    <option value="Wednesday" <?= $schedule['day'] == 'Wednesday' ? 'selected' : '' ?>>Wednesday</option>
                                    <option value="Thursday" <?= $schedule['day'] == 'Thursday' ? 'selected' : '' ?>>Thursday</option>
                                    <option value="Friday" <?= $schedule['day'] == 'Friday' ? 'selected' : '' ?>>Friday</option>
                                    <option value="Saturday" <?= $schedule['day'] == 'Saturday' ? 'selected' : '' ?>>Saturday</option>
                                    <option value="Sunday" <?= $schedule['day'] == 'Sunday' ? 'selected' : '' ?>>Sunday</option>
                                </select>
                            </div>

                            <!-- Start Time -->
                            <div>
                                <label for="start_time" class="block text-sm font-medium text-gray-700">Start Time</label>
                                <input type="time" id="start_time" name="start_time" value="<?= htmlspecialchars($schedule['start_time']) ?>" required
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md p-2 border">
                            </div>

                            <!-- End Time -->
                            <div>
                                <label for="end_time" class="block text-sm font-medium text-gray-700">End Time</label>
                                <input type="time" id="end_time" name="end_time" value="<?= htmlspecialchars($schedule['end_time']) ?>" required
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md p-2 border">
                            </div>

                            <!-- Check-in Grace Period (minutes) -->
                            <div>
                                <label for="checkin_grace_period" class="block text-sm font-medium text-gray-700">Check-in Grace Period (minutes)</label>
                                <input type="number" id="checkin_grace_period" name="checkin_grace_period" 
                                       value="<?= htmlspecialchars($schedule['checkin_grace_period'] ?? 10) ?>" min="0" max="60" required
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md p-2 border">
                                <p class="mt-1 text-xs text-gray-500">Minutes after class start time before marking as "Late"</p>
                            </div>

                            <!-- Check-out Grace Period (minutes) -->
                            <div>
                                <label for="checkout_grace_period" class="block text-sm font-medium text-gray-700">Check-out Grace Period (minutes)</label>
                                <input type="number" id="checkout_grace_period" name="checkout_grace_period" 
                                       value="<?= htmlspecialchars($schedule['checkout_grace_period'] ?? 10) ?>" min="0" max="60" required
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md p-2 border">
                                <p class="mt-1 text-xs text-gray-500">Minutes before/after class end time to allow check-out</p>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-end space-x-3">
                            <a href="display_schedule.php" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Cancel
                            </a>
                            <button type="submit" class="bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Update Schedule
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <!-- Result Modal -->
    <div id="resultModal" class="modal" style="display: <?= $show_modal ? 'block' : 'none' ?>;">
        <div class="modal-content">
            <div class="modal-header <?= $modal_type ?>">
                <h3 class="text-lg font-bold">
                    <?= $modal_type === "success" ? "Success" : "Error" ?>
                </h3>
            </div>
            <div class="modal-body">
                <p><?= htmlspecialchars($modal_message) ?></p>
            </div>
            <div class="modal-footer">
                <button id="modalButton" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">OK</button>
            </div>
        </div>
    </div>

    <!-- JavaScript for mobile menu toggle -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuButton = document.querySelector('button.md\\:hidden');
            const sidebar = document.querySelector('.md\\:flex-shrink-0');
            
            menuButton.addEventListener('click', function() {
                sidebar.classList.toggle('hidden');
            });
            
            // Modal functionality
            const modalButton = document.getElementById('modalButton');
            const resultModal = document.getElementById('resultModal');
            
            // Close modal when clicking the button
            if (modalButton) {
                modalButton.addEventListener('click', function() {
                    <?php if ($modal_type === "success"): ?>
                    window.location = 'display_schedule.php';
                    <?php else: ?>
                    resultModal.style.display = 'none';
                    <?php endif; ?>
                });
            }
            
            // Prevent closing modal with ESC key
            window.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && resultModal.style.display === 'block') {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>