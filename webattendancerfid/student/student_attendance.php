<?php
session_start();
include '../db_config.php';

// Check if user is logged in and is a student
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

// Initialize filter variables
$search = $_GET['search'] ?? '';
$section_filter = $_GET['section'] ?? '';
$date_filter = $_GET['date'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Get sections for filter dropdown
$sections_query = "SELECT DISTINCT s.schedule_id, s.section, c.course_name 
                  FROM schedules s 
                  JOIN enrollment e ON s.schedule_id = e.schedule_id 
                  JOIN courses c ON s.course_id = c.course_id 
                  WHERE e.user_id = ?";
$stmt = $conn->prepare($sections_query);
$stmt->bind_param("i", $student['user_id']);
$stmt->execute();
$sections = $stmt->get_result();

// Get statuses for filter dropdown
$statuses = array('Present', 'Late', 'Absent');

// Build attendance query with filtering
$query = "SELECT a.*, c.course_name, s.section, s.day, s.start_time, s.end_time, s.room
          FROM attendance a
          JOIN schedules s ON a.schedule_id = s.schedule_id
          JOIN courses c ON s.course_id = c.course_id
          WHERE a.user_id = ?";

if (!empty($search)) {
    $query .= " AND (c.course_name LIKE ? OR s.section LIKE ?)";
}
if (!empty($section_filter)) {
    $query .= " AND s.schedule_id = ?";
}
if (!empty($date_filter)) {
    $query .= " AND a.date = ?";
}
if (!empty($status_filter)) {
    if ($status_filter == 'Absent') {
        $query .= " AND (a.status IS NULL OR a.status = 'Absent')";
    } else {
        $query .= " AND a.status = ?";
    }
}

$query .= " ORDER BY a.date DESC, s.start_time ASC";

// Prepare and execute the query with filters
$types = "i"; // First parameter is always user_id
$params = [$student['user_id']];

if (!empty($search)) {
    $search_param = "%$search%";
    $types .= "ss";
    $params[] = $search_param;
    $params[] = $search_param;
}
if (!empty($section_filter)) {
    $types .= "i";
    $params[] = $section_filter;
}
if (!empty($date_filter)) {
    $types .= "s";
    $params[] = $date_filter;
}
if (!empty($status_filter) && $status_filter != 'Absent') {
    $types .= "s";
    $params[] = $status_filter;
}

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$attendance_records = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Attendance</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/tab_logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .status-present {
            color: #28a745;
            font-weight: bold;
            background-color: rgba(40, 167, 69, 0.1);
            padding: 5px 10px;
            border-radius: 20px;
        }
        .status-absent {
            color: #dc3545;
            font-weight: bold;
            background-color: rgba(220, 53, 69, 0.1);
            padding: 5px 10px;
            border-radius: 20px;
        }
        .status-late {
            color: #ffc107;
            font-weight: bold;
            background-color: rgba(255, 193, 7, 0.1);
            padding: 5px 10px;
            border-radius: 20px;
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
                
                <!-- Navigation -->
                <div class="flex flex-col flex-grow px-4 mt-5">
                    <nav class="flex-1 space-y-1">
                        <a href="student_dashboard.php" class="flex items-center px-4 py-3 text-sm font-medium text-indigo-100 rounded-lg hover:bg-indigo-700">
                            <i class="fas fa-home w-6"></i>
                            <span>Dashboard</span>
                        </a>
                        <a href="student_attendance.php" class="flex items-center px-4 py-3 text-sm font-medium text-white bg-indigo-700 rounded-lg">
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
        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Top Navigation -->
            <header class="bg-white shadow">
                <div class="px-4 sm:px-6 lg:px-8 py-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-2xl font-semibold text-gray-900">Attendance Records</h2>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                <!-- Search and Filter Section -->
                <div class="bg-white p-4 rounded-lg shadow mb-6">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- Search Bar -->
                        <div>
                            <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                            <input type="text" name="search" id="search" value="<?= htmlspecialchars($search) ?>"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                   placeholder="Search courses...">
                        </div>

                        <!-- Section Filter -->
                        <div>
                            <label for="section" class="block text-sm font-medium text-gray-700">Course & Section</label>
                            <select name="section" id="section"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">All Sections</option>
                                <?php while ($section = $sections->fetch_assoc()): ?>
                                    <option value="<?= $section['schedule_id'] ?>" <?= $section_filter == $section['schedule_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($section['course_name'] . ' - ' . $section['section']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Date Filter -->
                        <div>
                            <label for="date" class="block text-sm font-medium text-gray-700">Date</label>
                            <input type="date" name="date" id="date" value="<?= htmlspecialchars($date_filter) ?>"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>

                        <!-- Status Filter -->
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" id="status"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">All Statuses</option>
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?= $status ?>" <?= $status_filter == $status ? 'selected' : '' ?>>
                                        <?= $status ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Filter Buttons -->
                        <div class="md:col-span-4 flex justify-end space-x-2">
                            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                                Apply Filters
                            </button>
                            <a href="student_attendance.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Attendance Records Table -->
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-black text-white">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                        Course & Section
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                        Day
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                        Date
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                        Schedule
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                        Room
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                        Check-in
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                        Check-out
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($attendance_records->num_rows > 0): ?>
                                    <?php while ($record = $attendance_records->fetch_assoc()): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?= htmlspecialchars($record['course_name']) ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?= htmlspecialchars($record['section']) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= htmlspecialchars($record['day']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('M d, Y', strtotime($record['date'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('h:i A', strtotime($record['start_time'])) ?> - 
                                                <?= date('h:i A', strtotime($record['end_time'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= htmlspecialchars($record['room']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= !empty($record['check_in_time']) ? date('h:i A', strtotime($record['check_in_time'])) : 'N/A' ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= !empty($record['check_out_time']) ? date('h:i A', strtotime($record['check_out_time'])) : 'N/A' ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="status-<?= strtolower($record['status']) ?>">
                                                    <?= htmlspecialchars($record['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
                                            No attendance records found
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
