<?php
session_start();
include '../db_config.php';

// Check if user is logged in and is an instructor (role_id = 2)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 2) {
    header("Location: ../index.php");
    exit();
}

// Fetch instructor's name from database
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

// Initialize filter variables
$search = $_GET['search'] ?? '';
$day_filter = $_GET['day'] ?? '';
$room_filter = $_GET['room'] ?? '';

// Build schedule query with filtering
$query = "SELECT s.*, c.course_name, c.course_id, u.name AS instructor_name 
          FROM schedules s
          JOIN courses c ON s.course_id = c.course_id
          LEFT JOIN user u ON s.instructor_id = u.user_id
          WHERE s.instructor_id = ?";

// Add filters if they are set
if (!empty($search)) {
    $query .= " AND (c.course_name LIKE ? OR s.section LIKE ? OR s.room LIKE ?)";
}
if (!empty($day_filter)) {
    $query .= " AND s.day = ?";
}
if (!empty($room_filter)) {
    $query .= " AND s.room = ?";
}

$query .= " ORDER BY s.day, s.start_time";

// Prepare and execute the query
$stmt = $conn->prepare($query);

// Bind parameters
$types = "i"; // First parameter is always instructor_id (integer)
$params = [$instructor['user_id']];

if (!empty($search) || !empty($day_filter) || !empty($room_filter)) {
    if (!empty($search)) {
        $search_param = "%$search%";
        $types .= "sss";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($day_filter)) {
        $types .= "s";
        $params[] = $day_filter;
    }
    
    if (!empty($room_filter)) {
        $types .= "s";
        $params[] = $room_filter;
    }
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get unique rooms for filter dropdown
$rooms_query = "SELECT DISTINCT room FROM schedules WHERE instructor_id = ? ORDER BY room";
$stmt = $conn->prepare($rooms_query);
$stmt->bind_param("i", $instructor['user_id']);
$stmt->execute();
$rooms = $stmt->get_result();

// Define days of the week for filter dropdown
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Schedule</title>
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
                        <a href="instructor_dashboard.php" class="flex items-center px-4 py-3 text-sm font-medium text-indigo-100 rounded-lg hover:bg-indigo-700 transition duration-150">
                            <i class="fas fa-home w-6"></i>
                            <span>Dashboard</span>
                        </a>
                        <a href="ins_schedule.php" class="flex items-center px-4 py-3 mt-1 text-sm font-medium text-white bg-indigo-700 rounded-lg hover:bg-indigo-600 transition duration-150">
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
                        
                        <h1 class="text-lg font-medium text-gray-900">My Schedule</h1>
                        
                        <!-- Right Elements -->
                        <div class="flex items-center space-x-4">
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
                    <h1 class="text-2xl font-semibold text-gray-900">My Class Schedule</h1>
                    <p class="mt-1 text-sm text-gray-600">View and manage your assigned classes</p>
                </div>

                <!-- Search and Filter Panel -->
                <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Search and Filter</h3>
                        <form action="" method="GET" class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <!-- Search -->
                                <div>
                                    <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                                    <input type="text" name="search" id="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by course, section, room..." 
                                           class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md p-2 border">
                                </div>
                                
                                <!-- Day Filter -->
                                <div>
                                    <label for="day" class="block text-sm font-medium text-gray-700">Day</label>
                                    <select name="day" id="day" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        <option value="">All Days</option>
                                        <?php foreach ($days_of_week as $day): ?>
                                            <option value="<?= htmlspecialchars($day) ?>" <?= $day_filter == $day ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($day) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Room Filter -->
                                <div>
                                    <label for="room" class="block text-sm font-medium text-gray-700">Room</label>
                                    <select name="room" id="room" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        <option value="">All Rooms</option>
                                        <?php while ($room = $rooms->fetch_assoc()): ?>
                                            <option value="<?= htmlspecialchars($room['room']) ?>" <?= $room_filter == $room['room'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($room['room']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="flex justify-end">
                                <a href="ins_schedule.php" class="bg-gray-100 py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 mr-2">
                                    Reset
                                </a>
                                <button type="submit" class="bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Apply Filters
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Class Schedules Table -->
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">My Class Schedules</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-black text-white">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Course</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Section</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Room</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Day</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Time</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($row['course_name']) ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['section']) ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['room']) ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['day']) ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= htmlspecialchars($row['start_time']) ?> - <?= htmlspecialchars($row['end_time']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <a href="ins_attendance.php?id=<?= $row['schedule_id'] ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                                        <i class="fas fa-user-check"></i> Take Attendance
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No schedules found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Weekly Schedule Overview -->
                <div class="bg-white shadow overflow-hidden sm:rounded-lg mt-6">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Weekly Schedule Overview</h3>
                        
                        <?php
                        // Get current date and time
                        $current_datetime = new DateTime();
                        $current_date = $current_datetime->format('Y-m-d');
                        $current_time = $current_datetime->format('H:i:s');
                        
                        // Calculate current week's start and end dates (Monday to Sunday)
                        $week_start_date = clone $current_datetime;
                        $week_start_date->modify('monday this week');
                        $week_end_date = clone $week_start_date;
                        $week_end_date->modify('+6 days');
                        
                        // Format dates for display
                        $week_start = $week_start_date->format('Y-m-d');
                        $week_end = $week_end_date->format('Y-m-d');
                        ?>
                        
                        <div class="grid grid-cols-1 md:grid-cols-7 gap-4">
                            <?php
                            // Loop through days of the current week
                            for ($i = 0; $i < 7; $i++):
                                $day_date_obj = clone $week_start_date;
                                $day_date_obj->modify("+$i days");
                                $day_date = $day_date_obj->format('Y-m-d');
                                $day_name = $day_date_obj->format('l');
                                $is_today = ($day_date == $current_date);
                                
                                // Format the display date
                                $display_date = $day_date_obj->format('M d');
                            ?>
                                <div class="<?= $is_today ? 'bg-indigo-50 border-indigo-200' : 'bg-gray-50' ?> p-4 rounded-lg border <?= $is_today ? 'border-indigo-200' : 'border-gray-200' ?>">
                                    <h4 class="font-medium <?= $is_today ? 'text-indigo-800' : 'text-gray-900' ?> mb-1"><?= $day_name ?></h4>
                                    <div class="text-xs <?= $is_today ? 'text-indigo-600' : 'text-gray-500' ?> mb-3"><?= $display_date ?></div>
                                    
                                    <?php
                                    // Track course occurrences for this day to identify duplicates
                                    $course_occurrences = [];
                                    $has_classes = false;
                                    
                                    // PART 1: Get classes from attendance records for this date
                                    $date_classes_query = "SELECT a.attendance_id, a.date, s.schedule_id, s.section, s.room, s.start_time, s.end_time, 
                                                        c.course_name, c.course_id, c.start_date, c.end_date
                                                        FROM attendance a
                                                        JOIN schedules s ON a.schedule_id = s.schedule_id
                                                        JOIN courses c ON s.course_id = c.course_id
                                                        WHERE s.instructor_id = ? AND a.date = ?
                                                        AND ? BETWEEN c.start_date AND c.end_date
                                                        GROUP BY s.schedule_id, s.start_time, s.end_time
                                                        ORDER BY s.start_time";
                                    
                                    $stmt_date = $conn->prepare($date_classes_query);
                                    $stmt_date->bind_param("iss", $instructor['user_id'], $day_date, $day_date);
                                    $stmt_date->execute();
                                    $date_classes = $stmt_date->get_result();
                                    
                                    // Track which schedules we've already shown
                                    $shown_schedule_ids = [];
                                    
                                    // Show classes from attendance records for this date
                                    if ($date_classes->num_rows > 0) {
                                        while ($class = $date_classes->fetch_assoc()) {
                                            // Track course occurrences
                                            $course_id = $class['course_id'];
                                            if (!isset($course_occurrences[$course_id])) {
                                                $course_occurrences[$course_id] = 1;
                                            } else {
                                                $course_occurrences[$course_id]++;
                                            }
                                            
                                            $has_classes = true;
                                            $shown_schedule_ids[] = $class['schedule_id'];
                                            $class_bg = $is_today ? 'bg-white border-indigo-200' : 'bg-white border-gray-200';
                                            
                                            echo '<div class="mb-2 p-2 rounded border ' . $class_bg . '">';
                                            echo '<div class="text-sm font-medium text-indigo-700">' . htmlspecialchars($class['course_name']) . '</div>';
                                            echo '<div class="text-xs text-gray-500">' . htmlspecialchars($class['section']) . '</div>';
                                            echo '<div class="text-xs text-gray-500">' . htmlspecialchars($class['room']) . '</div>';
                                            echo '<div class="text-xs text-gray-500">' . date('h:i A', strtotime($class['start_time'])) . ' - ' . date('h:i A', strtotime($class['end_time'])) . '</div>';
                                            echo '</div>';
                                        }
                                    }
                                    
                                    // PART 2: Get regular classes scheduled for this day that fall within current week and course date range
                                    $schedule_query = "SELECT s.schedule_id, s.section, s.room, s.start_time, s.end_time, 
                                                     c.course_name, c.course_id, c.start_date, c.end_date
                                                     FROM schedules s
                                                     JOIN courses c ON s.course_id = c.course_id
                                                     WHERE s.instructor_id = ? AND s.day = ?
                                                     AND ? BETWEEN c.start_date AND c.end_date
                                                     ORDER BY s.start_time";
                                    
                                    $stmt_schedule = $conn->prepare($schedule_query);
                                    $stmt_schedule->bind_param("iss", $instructor['user_id'], $day_name, $day_date);
                                    $stmt_schedule->execute();
                                    $regular_schedules = $stmt_schedule->get_result();
                                    
                                    // Show regular classes that haven't been shown yet
                                    if ($regular_schedules->num_rows > 0) {
                                        while ($class = $regular_schedules->fetch_assoc()) {
                                            // Skip if already shown 
                                            if (in_array($class['schedule_id'], $shown_schedule_ids)) {
                                                continue;
                                            }
                                            
                                            // Track course occurrences
                                            $course_id = $class['course_id'];
                                            if (!isset($course_occurrences[$course_id])) {
                                                $course_occurrences[$course_id] = 1;
                                            } else {
                                                $course_occurrences[$course_id]++;
                                            }
                                            
                                            $has_classes = true;
                                            $class_bg = $is_today ? 'bg-white border-indigo-200' : 'bg-white border-gray-200';
                                            
                                            echo '<div class="mb-2 p-2 rounded border ' . $class_bg . '">';
                                            echo '<div class="text-sm font-medium text-indigo-700">' . htmlspecialchars($class['course_name']) . '</div>';
                                            echo '<div class="text-xs text-gray-500">' . htmlspecialchars($class['section']) . '</div>';
                                            echo '<div class="text-xs text-gray-500">' . htmlspecialchars($class['room']) . '</div>';
                                            echo '<div class="text-xs text-gray-500">' . date('h:i A', strtotime($class['start_time'])) . ' - ' . date('h:i A', strtotime($class['end_time'])) . '</div>';
                                            
                                            // If multiple occurrences of this course on this day, highlight it
                                            if ($course_occurrences[$course_id] > 1) {
                                                echo '<div class="mt-1 text-xs font-medium bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full inline-block">
                                                Multiple classes for this course today</div>';
                                            }
                                            
                                        echo '</div>';
                                        }
                                    }
                                    
                                    // If no classes were shown, display the "No classes" message
                                    if (!$has_classes) {
                                        echo '<div class="text-sm text-gray-500">No classes</div>';
                                    }
                                    ?>
                                </div>
                            <?php endfor; ?>
                        </div>
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
