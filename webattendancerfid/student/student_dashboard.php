<?php
session_start();
include '../db_config.php';

// Check if user is logged in and is a student (role_id = 3)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 3) {
    header("Location: ../index.php");
    exit();
}

// Fetch student's name and ID from database
$stmt = $conn->prepare("SELECT name, user_id FROM user WHERE id_number = ? AND role_id = 3");
$stmt->bind_param("s", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

// Add error handling if student not found
if (!$student) {
    session_destroy();
    header("Location: ../index.php");
    exit();
}

// Get total courses enrolled
$stmt = $conn->prepare("SELECT COUNT(DISTINCT s.course_id) as total_courses 
                       FROM enrollment e 
                       JOIN schedules s ON e.schedule_id = s.schedule_id 
                       WHERE e.user_id = ?");
$stmt->bind_param("i", $student['user_id']);
$stmt->execute();
$courses_result = $stmt->get_result();
$total_courses = $courses_result->fetch_assoc()['total_courses'];
$stmt->close();

// Get today's schedule
$today = date('l'); // Gets the current day name (Monday, Tuesday, etc.)
$stmt = $conn->prepare("SELECT s.*, c.course_name, u.name as instructor_name
                       FROM enrollment e
                       JOIN schedules s ON e.schedule_id = s.schedule_id
                       JOIN courses c ON s.course_id = c.course_id
                       JOIN user u ON s.instructor_id = u.user_id
                       WHERE e.user_id = ? AND s.day = ?
                       ORDER BY s.start_time");
$stmt->bind_param("is", $student['user_id'], $today);
$stmt->execute();
$today_schedule = $stmt->get_result();
$stmt->close();

// Get full schedule for all days
$stmt = $conn->prepare("SELECT s.*, c.course_name, u.name as instructor_name
                       FROM enrollment e
                       JOIN schedules s ON e.schedule_id = s.schedule_id
                       JOIN courses c ON s.course_id = c.course_id
                       JOIN user u ON s.instructor_id = u.user_id
                       WHERE e.user_id = ?
                       ORDER BY FIELD(s.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), 
                                s.start_time");
$stmt->bind_param("i", $student['user_id']);
$stmt->execute();
$full_schedule = $stmt->get_result();
$stmt->close();

// Get attendance statistics
$stmt = $conn->prepare("SELECT 
                         COUNT(*) as total_days,
                         SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
                         SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_days,
                         SUM(CASE WHEN status = 'Absent' OR status IS NULL THEN 1 ELSE 0 END) as absent_days
                       FROM attendance 
                       WHERE user_id = ?");
$stmt->bind_param("i", $student['user_id']);
$stmt->execute();
$attendance_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Calculate attendance rate
$total_days = $attendance_stats['total_days'] ?: 1; // Avoid division by zero
$attendance_rate = round(($attendance_stats['present_days'] / $total_days) * 100);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
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
                        <a href="student_dashboard.php" class="flex items-center px-4 py-3 text-sm font-medium text-white bg-indigo-700 rounded-lg">
                            <i class="fas fa-home w-6"></i>
                            <span>Dashboard</span>
                        </a>
                        <a href="student_attendance.php" class="flex items-center px-4 py-3 text-sm font-medium text-indigo-100 rounded-lg hover:bg-indigo-700">
                            <i class="fas fa-user-check w-6"></i>
                            <span>Attendance</span>
                        </a>
                    </nav>
                    
                    <!-- User Profile -->
                    <div class="flex items-center px-4 py-3 mt-auto mb-6 bg-indigo-950 bg-opacity-50 rounded-lg">
                        <img class="w-10 h-10 rounded-full" src="https://ui-avatars.com/api/?name=<?= urlencode($student['name']) ?>&background=6366F1&color=fff" alt="User">
                        <div class="ml-3">
                            <p class="text-sm font-medium text-white"><?= htmlspecialchars($student['name']) ?></p>
                            <p class="text-xs text-indigo-200">Student</p>
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
                            <div class="md:hidden">
                                <img class="w-8 h-8 rounded-full" src="https://ui-avatars.com/api/?name=<?= urlencode($student['name']) ?>&background=6366F1&color=fff" alt="User">
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <main class="flex-1 p-4 sm:px-6 lg:px-8 bg-gray-50">
                <div class="mb-6">
                    <h1 class="text-2xl font-semibold text-gray-900">Student Dashboard</h1>
                    <p class="mt-1 text-sm text-gray-600">Welcome back, <?= htmlspecialchars($student['name']) ?>!</p>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 mb-6">
                    <!-- Attendance Rate Card -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                    <i class="fas fa-user-check text-white text-xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Attendance Rate</dt>
                                        <dd class="flex items-baseline">
                                            <div class="text-2xl font-semibold text-gray-900"><?= $attendance_rate ?>%</div>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Total Courses Card -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                                    <i class="fas fa-book-open text-white text-xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Total Courses</dt>
                                        <dd class="flex items-baseline">
                                            <div class="text-2xl font-semibold text-gray-900"><?= $total_courses ?></div>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Summary Card -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                    <i class="fas fa-chart-pie text-white text-xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Attendance Summary</dt>
                                        <dd class="mt-2">
                                            <div class="flex items-center text-sm">
                                                <span class="text-green-600 mr-2">Present: <?= $attendance_stats['present_days'] ?></span>
                                                <span class="text-yellow-600 mr-2">Late: <?= $attendance_stats['late_days'] ?></span>
                                                <span class="text-red-600">Absent: <?= $attendance_stats['absent_days'] ?></span>
                                            </div>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Today's Schedule -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Today's Schedule</h3>
                        <button type="button" onclick="openScheduleModal()" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">View Full Schedule</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-black text-white">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Time</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Course</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Instructor</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Room</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($today_schedule->num_rows > 0): ?>
                                    <?php while ($class = $today_schedule->fetch_assoc()): 
                                        $start_time = strtotime($class['start_time']);
                                        $end_time = strtotime($class['end_time']);
                                    ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?= date('h:i A', $start_time) ?> - <?= date('h:i A', $end_time) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($class['course_name']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($class['section']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($class['instructor_name']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($class['room']) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No classes scheduled for today</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Full Schedule Modal -->
    <div id="scheduleModal" class="fixed inset-0 bg-gray-900 bg-opacity-90 flex items-center justify-center z-50 hidden" aria-modal="true" role="dialog" tabindex="-1">
        <div class="bg-white rounded-lg shadow-xl p-6 max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Full Schedule</h3>
                <button type="button" onclick="closeScheduleModal()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-black text-white">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Day</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Time</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Course</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Instructor</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Room</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php 
                        // Reset the result pointer to the beginning
                        $full_schedule->data_seek(0);
                        
                        // Group classes by day
                        $scheduleByDay = [];
                        while ($class = $full_schedule->fetch_assoc()) {
                            $scheduleByDay[$class['day']][] = $class;
                        }
                        
                        // Display classes for each day
                        $currentDay = null;
                        $dayCounter = 0;
                        
                        if (count($scheduleByDay) > 0):
                            foreach ($scheduleByDay as $day => $classes):
                                $dayCounter++;
                                $isEvenDay = $dayCounter % 2 === 0;
                                $bgColor = $day === $today ? 'bg-indigo-50' : ($isEvenDay ? 'bg-gray-50' : 'bg-white');
                                
                                foreach ($classes as $index => $class):
                                    $start_time = strtotime($class['start_time']);
                                    $end_time = strtotime($class['end_time']);
                                    $isFirstInDay = $index === 0;
                                    $isLastInDay = $index === count($classes) - 1;
                        ?>
                                    <tr class="<?= $bgColor ?> <?= $isFirstInDay ? 'border-t-2 border-gray-300' : '' ?>">
                                        <!-- Show day only for the first class of each day -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 <?= $isFirstInDay ? 'pt-5' : ($isLastInDay ? 'pb-5' : '') ?>">
                                            <?= $isFirstInDay ? htmlspecialchars($day) : '' ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 <?= $isFirstInDay ? 'pt-5' : ($isLastInDay ? 'pb-5' : '') ?>">
                                            <?= date('h:i A', $start_time) ?> - <?= date('h:i A', $end_time) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap <?= $isFirstInDay ? 'pt-5' : ($isLastInDay ? 'pb-5' : '') ?>">
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($class['course_name']) ?></div>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($class['section']) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 <?= $isFirstInDay ? 'pt-5' : ($isLastInDay ? 'pb-5' : '') ?>">
                                            <?= htmlspecialchars($class['instructor_name']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 <?= $isFirstInDay ? 'pt-5' : ($isLastInDay ? 'pb-5' : '') ?>">
                                            <?= htmlspecialchars($class['room']) ?>
                                        </td>
                                    </tr>
                        <?php 
                                endforeach;
                            endforeach;
                        else: 
                        ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No classes scheduled for the week</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- JavaScript for mobile menu toggle and modal -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuButton = document.querySelector('button.md\\:hidden');
            const sidebar = document.querySelector('.md\\:flex-shrink-0');
            
            menuButton.addEventListener('click', function() {
                sidebar.classList.toggle('hidden');
            });
        });

        // Modal functions
        function openScheduleModal() {
            const modal = document.getElementById('scheduleModal');
            modal.classList.remove('hidden');
            
            // Prevent background scrolling and interactions
            document.body.style.overflow = 'hidden';
            
            // Set focus on the modal to capture keyboard events
            modal.focus();
            
            // Temporarily disable tab navigation outside modal
            document.querySelectorAll('a, button, input, select, textarea').forEach(el => {
                if (!modal.contains(el)) {
                    el.setAttribute('tabindex', '-1');
                    el.setAttribute('aria-hidden', 'true');
                }
            });
        }

        function closeScheduleModal() {
            const modal = document.getElementById('scheduleModal');
            modal.classList.add('hidden');
            
            // Re-enable scrolling
            document.body.style.overflow = '';
            
            // Re-enable tab navigation
            document.querySelectorAll('[tabindex="-1"][aria-hidden="true"]').forEach(el => {
                el.removeAttribute('tabindex');
                el.removeAttribute('aria-hidden');
            });
        }

        // Remove event listeners that would close the modal
        // (The ESC key and clicking outside handlers are intentionally NOT added)
    </script>
</body>
</html>