<?php
session_start();
include '../db_config.php';

// Check if user is logged in and is an instructor
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
$section_filter = $_GET['section'] ?? '';
$day_filter = $_GET['day'] ?? '';
$date_filter = $_GET['date'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build attendance query with filtering
$query = "SELECT a.*, u.name AS student_name, c.course_name, s.section, s.day
          FROM attendance a
          JOIN user u ON a.user_id = u.user_id
          JOIN schedules s ON a.schedule_id = s.schedule_id
          JOIN courses c ON s.course_id = c.course_id
          WHERE s.instructor_id = ?";

// Add filters if they are set
if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR c.course_name LIKE ? OR s.section LIKE ?)";
}
if (!empty($section_filter)) {
    $query .= " AND s.schedule_id = ?";
}
if (!empty($day_filter)) {
    $query .= " AND s.day = ?";
}
if (!empty($date_filter)) {
    $query .= " AND a.date = ?";
}
if (!empty($status_filter)) {
    $query .= " AND a.status = ?";
}

$query .= " ORDER BY a.date DESC, s.section, u.name";

// Prepare and execute the query
$stmt = $conn->prepare($query);

// Initialize types string and params array
$types = "i"; // First parameter is always instructor_id (integer)
$params = [$instructor['user_id']];

// Add additional parameters if filters are set
if (!empty($search)) {
    $search_param = "%$search%";
    $types .= "sss";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($section_filter)) {
    $types .= "i";
    $params[] = $section_filter;
}

if (!empty($day_filter)) {
    $types .= "s";
    $params[] = $day_filter;
}

if (!empty($date_filter)) {
    $types .= "s";
    $params[] = $date_filter;
}

if (!empty($status_filter)) {
    $types .= "s";
    $params[] = $status_filter;
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get sections for filter dropdown (only sections assigned to this instructor)
$sections_query = "SELECT s.schedule_id, s.section, c.course_name 
                  FROM schedules s 
                  JOIN courses c ON s.course_id = c.course_id 
                  WHERE s.instructor_id = ?
                  ORDER BY s.section";
$stmt = $conn->prepare($sections_query);
$stmt->bind_param("i", $instructor['user_id']);
$stmt->execute();
$sections = $stmt->get_result();

// Define days of the week for filter dropdown
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Get statuses for filter dropdown - now including 'Absent'
$statuses = array(
    array('status' => 'Present'),
    array('status' => 'Late'),
    array('status' => 'Absent')
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Records</title>
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
                        <a href="ins_schedule.php" class="flex items-center px-4 py-3 mt-1 text-sm font-medium text-indigo-100 rounded-lg hover:bg-indigo-700 transition duration-150">
                            <i class="fas fa-calendar w-6"></i>
                            <span>Schedule</span>
                        </a>
                        <a href="ins_attendance.php" class="flex items-center px-4 py-3 mt-1 text-sm font-medium text-white bg-indigo-700 rounded-lg hover:bg-indigo-600 transition duration-150">
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
                        
                        <h1 class="text-lg font-medium text-gray-900">Attendance Records</h1>
                        
                        <button id="exportCSV" class="bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <i class="fas fa-download mr-2"></i> Export to CSV
                        </button>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <main class="flex-1 p-4 sm:px-6 lg:px-8 bg-gray-50">
                <!-- Search and Filters -->
                <div class="mb-6">
                    <div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
                        <form method="GET" action="" class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                                <!-- Search -->
                                <div>
                                    <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                                    <input type="text" name="search" id="search" value="<?= htmlspecialchars($search) ?>" 
                                           placeholder="Search by name, course, section..." 
                                           class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                </div>

                                <!-- Section Filter -->
                                <div>
                                    <label for="section" class="block text-sm font-medium text-gray-700">Section</label>
                                    <select name="section" id="section" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                        <option value="">All Sections</option>
                                        <?php while ($section = $sections->fetch_assoc()): ?>
                                            <option value="<?= $section['schedule_id'] ?>" <?= $section_filter == $section['schedule_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($section['section']) ?> - <?= htmlspecialchars($section['course_name']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <!-- Day Filter -->
                                <div>
                                    <label for="day" class="block text-sm font-medium text-gray-700">Day</label>
                                    <select name="day" id="day" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                        <option value="">All Days</option>
                                        <?php foreach ($days_of_week as $day): ?>
                                            <option value="<?= $day ?>" <?= $day_filter == $day ? 'selected' : '' ?>>
                                                <?= $day ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Date Filter -->
                                <div>
                                    <label for="date" class="block text-sm font-medium text-gray-700">Date</label>
                                    <input type="date" name="date" id="date" value="<?= htmlspecialchars($date_filter) ?>"
                                           class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                </div>

                                <!-- Status Filter -->
                                <div>
                                    <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                                    <select name="status" id="status" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                        <option value="">All Statuses</option>
                                        <?php foreach ($statuses as $status): ?>
                                            <option value="<?= htmlspecialchars($status['status']) ?>" <?= $status_filter == $status['status'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($status['status']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="flex justify-end space-x-3">
                                <a href="ins_attendance.php" class="bg-gray-100 py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Reset
                                </a>
                                <button type="submit" class="bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Apply Filters
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Attendance Records Table -->
                <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="overflow-x-auto">
                            <table id="attendanceTable" class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-black text-white">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Student</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Section</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Day</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Check-in Time</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Check-out Time</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    <?= htmlspecialchars($row['student_name']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= htmlspecialchars($row['section']) ?> - <?= htmlspecialchars($row['course_name']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= htmlspecialchars($row['day']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= htmlspecialchars($row['date']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= htmlspecialchars($row['check_in_time']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= !empty($row['check_out_time']) ? htmlspecialchars($row['check_out_time']) : 'N/A' ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                        <?= $row['status'] == 'Present' ? 'bg-green-100 text-green-800' : 
                                                           ($row['status'] == 'Late' ? 'bg-yellow-100 text-yellow-800' : 
                                                           'bg-red-100 text-red-800') ?>">
                                                        <?= htmlspecialchars($row['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">No attendance records found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Generate Reports Panel -->
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Generate Reports</h3>
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <h4 class="font-medium text-gray-900">Attendance Summary</h4>
                                <p class="text-sm text-gray-600 mt-1 mb-3">Generate a summary report of attendance statistics by section and date range.</p>
                                <a href="ins_attendance_summary.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                    <i class="fas fa-chart-bar mr-2"></i> Generate Summary
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- JavaScript for mobile menu toggle and CSV export -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const menuButton = document.querySelector('button.md\\:hidden');
            const sidebar = document.querySelector('.md\\:flex-shrink-0');
            
            menuButton.addEventListener('click', function() {
                sidebar.classList.toggle('hidden');
            });
            
            // CSV Export functionality
            document.getElementById('exportCSV').addEventListener('click', function() {
                const table = document.getElementById('attendanceTable');
                const rows = table.querySelectorAll('tr');
                let csv = [];
                
                // Get headers
                const headers = [];
                const headerCells = rows[0].querySelectorAll('th');
                headerCells.forEach(cell => {
                    headers.push(cell.textContent.trim());
                });
                csv.push(headers.join(','));
                
                // Get data rows
                for (let i = 1; i < rows.length; i++) {
                    const row = rows[i];
                    const cells = row.querySelectorAll('td');
                    if (cells.length > 0) {
                        const rowData = [];
                        cells.forEach(cell => {
                            let text = cell.textContent.trim().replace(/,/g, ' ');
                            rowData.push('"' + text + '"');
                        });
                        csv.push(rowData.join(','));
                    }
                }
                
                // Create and download CSV file
                const csvContent = "data:text/csv;charset=utf-8," + csv.join('\n');
                const encodedUri = encodeURI(csvContent);
                const link = document.createElement("a");
                link.setAttribute("href", encodedUri);
                link.setAttribute("download", "attendance_report_" + new Date().toISOString().slice(0,10) + ".csv");
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        });
    </script>
</body>
</html>
