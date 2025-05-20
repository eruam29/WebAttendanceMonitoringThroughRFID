<?php
session_start();
include '../db_config.php';

// Check if user is logged in and is an instructor (role_id = 2)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 2) {
    header("Location: ../index.php");
    exit();
}

// Fetch instructor's name and ID from database
$stmt = $conn->prepare("SELECT name, user_id FROM user WHERE id_number = ? AND role_id = 2");
$stmt->bind_param("s", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$instructor = $result->fetch_assoc();
$stmt->close();

// Add error handling if instructor not found
if (!$instructor) {
    session_destroy();
    header("Location: ../index.php");
    exit();
}

// Get total active courses for this instructor
$stmt = $conn->prepare("SELECT COUNT(DISTINCT s.course_id) as total_courses 
                       FROM schedules s 
                       WHERE s.instructor_id = ?");
$stmt->bind_param("i", $instructor['user_id']);
$stmt->execute();
$courses_result = $stmt->get_result();
$total_courses = $courses_result->fetch_assoc()['total_courses'];
$stmt->close();

// Get total unique students across all courses
$stmt = $conn->prepare("SELECT COUNT(DISTINCT e.user_id) as total_students 
                       FROM enrollment e 
                       JOIN schedules s ON e.schedule_id = s.schedule_id 
                       WHERE s.instructor_id = ?");
$stmt->bind_param("i", $instructor['user_id']);
$stmt->execute();
$students_result = $stmt->get_result();
$total_students = $students_result->fetch_assoc()['total_students'];
$stmt->close();

// Get today's schedule
$today = date('l'); // Gets the current day name (Monday, Tuesday, etc.)
$stmt = $conn->prepare("SELECT s.*, c.course_name, 
                              (SELECT COUNT(e.user_id) FROM enrollment e WHERE e.schedule_id = s.schedule_id) as student_count
                       FROM schedules s 
                       JOIN courses c ON s.course_id = c.course_id 
                       WHERE s.instructor_id = ? AND s.day = ? 
                       ORDER BY s.start_time");
$stmt->bind_param("is", $instructor['user_id'], $today);
$stmt->execute();
$today_schedule = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Dashboard</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/tab_logo.png">
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                        <a href="instructor_dashboard.php" class="flex items-center px-4 py-3 text-sm font-medium text-white bg-indigo-700 rounded-lg hover:bg-indigo-600 transition duration-150">
                            <i class="fas fa-home w-6"></i>
                            <span>Dashboard</span>
                        </a>
                        <a href="ins_schedule.php" class="flex items-center px-4 py-3 mt-1 text-sm font-medium text-indigo-100 rounded-lg hover:bg-indigo-700 transition duration-150">
                            <i class="fas fa-calendar w-6"></i>
                            <span>Schedule</span>
                        </a>
                        <a href="ins_attendance.php" class="flex items-center px-4 py-3 mt-1 text-sm font-medium text-indigo-100 rounded-lg hover:bg-indigo-700 transition duration-150">
                            <i class="fas fa-user-check w-6"></i>
                            <span>Attendance</span>
                        </a>
                    </nav>
                    
                    <!-- User Profile -->
                    <div class="flex items-center px-4 py-3 mt-auto mb-6 bg-indigo-950 bg-opacity-50 rounded-lg">
                        <img class="w-10 h-10 rounded-full" src="https://ui-avatars.com/api/?name=<?= urlencode($instructor['name']) ?>&background=6366F1&color=fff" alt="User">
                        <div class="ml-3">
                            <p class="text-sm font-medium text-white"><?= htmlspecialchars($instructor['name']) ?></p>
                            <p class="text-xs text-indigo-200">Instructor</p>
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
                        
                        <!-- Right Elements -->
                        <div class="flex items-center space-x-4">
                            <!-- Notification Icon for Appeals -->
                            <div class="relative">
                                <a href="instructor_appeals.php" class="p-2 rounded-md text-gray-500 hover:text-gray-900 focus:outline-none">
                                    <i class="fas fa-bell"></i>
                                    <?php
                                    // Check for unread appeals
                                    $unreadAppealsQuery = $conn->prepare("SELECT COUNT(*) as unread_count FROM appeals 
                                                                         WHERE instructor_read = 0 AND instructor_id = ?");
                                    $unreadAppealsQuery->bind_param("i", $instructor['user_id']);
                                    $unreadAppealsQuery->execute();
                                    $unreadAppeals = $unreadAppealsQuery->get_result()->fetch_assoc()['unread_count'];
                                    if ($unreadAppeals > 0): 
                                    ?>
                                    <span class="absolute top-0 right-0 block h-5 w-5 rounded-full bg-red-500 text-white text-xs text-center">
                                        <?= $unreadAppeals ?>
                                    </span>
                                    <?php endif; ?>
                                </a>
                            </div>
                            <button class="p-2 rounded-md text-gray-500 hover:text-gray-900 focus:outline-none">
                                <i class="fas fa-envelope"></i>
                            </button>
                            <div class="md:hidden">
                                <img class="w-8 h-8 rounded-full" src="https://ui-avatars.com/api/?name=<?= urlencode($instructor['name']) ?>&background=6366F1&color=fff" alt="User">
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <main class="flex-1 p-4 sm:px-6 lg:px-8 bg-gray-50">
                <div class="mb-6">
                    <h1 class="text-2xl font-semibold text-gray-900">Instructor Dashboard</h1>
                    <p class="mt-1 text-sm text-gray-600">Welcome back, <?= htmlspecialchars($instructor['name']) ?>! Here's an overview of your classes and students.</p>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-6">
                    <!-- Active Courses Card -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                                    <i class="fas fa-book-open text-white text-xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Active Courses</dt>
                                        <dd class="flex items-baseline">
                                            <div class="text-2xl font-semibold text-gray-900"><?= $total_courses ?></div>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Total Students Card -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                                    <i class="fas fa-users text-white text-xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Total Students</dt>
                                        <dd class="flex items-baseline">
                                            <div class="text-2xl font-semibold text-gray-900"><?= $total_students ?></div>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Today's Schedule -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Today's Schedule</h3>
                        <a href="ins_schedule.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">View Full Schedule</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-black text-white">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Time</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Course</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Room</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Students</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($today_schedule->num_rows > 0): ?>
                                    <?php while ($class = $today_schedule->fetch_assoc()): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?= date('h:i A', strtotime($class['start_time'])) ?> - <?= date('h:i A', strtotime($class['end_time'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($class['course_name']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($class['section']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($class['room']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $class['student_count'] ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="take_attendance.php?id=<?= $class['schedule_id'] ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                                    <i class="fas fa-user-check"></i> Take Attendance
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No classes scheduled for today</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <a href="ins_attendance.php" class="flex flex-col items-center p-4 bg-green-50 hover:bg-green-100 rounded-lg text-center transition duration-150">
                            <i class="fas fa-user-check text-green-600 text-2xl mb-2"></i>
                            <p class="text-sm font-medium text-green-800">Take Attendance</p>
                        </a>
                        <a href="ins_attendance.php" class="flex flex-col items-center p-4 bg-amber-50 hover:bg-amber-100 rounded-lg text-center transition duration-150">
                            <i class="fas fa-file-export text-amber-600 text-2xl mb-2"></i>
                            <p class="text-sm font-medium text-amber-800">Export Grades</p>
                        </a>
                    </div>
                </div>
            </main>
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
        });
    </script>
</body>
</html>
