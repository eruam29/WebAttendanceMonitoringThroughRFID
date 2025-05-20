<?php
session_start();
include '../db_config.php';

// Check if user is logged in and is an instructor
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

// Initialize filter variables
$section_filter = $_GET['section'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$status_filter = $_GET['status'] ?? ''; // Add status filter

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

// Function to get attendance summary data (filtered by instructor)
function getAttendanceSummary($conn, $instructor_id, $section_filter, $date_from, $date_to, $status_filter) {
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
              WHERE s.instructor_id = ?";
    
    $types = "i";
    $params = [$instructor_id];
    
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
    
    if (!empty($status_filter)) {
        $query .= " AND a.status = ?";
        $types .= "s";
        $params[] = $status_filter;
    }
    
    $query .= " GROUP BY s.section, c.course_name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    
    $stmt->execute();
    $result = $stmt->get_result();
    $summary_data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $summary_data;
}

// Get summary data if filters are set
$summary_data = [];
if (!empty($section_filter) || (!empty($date_from) && !empty($date_to)) || !empty($status_filter)) {
    $summary_data = getAttendanceSummary($conn, $instructor['user_id'], $section_filter, $date_from, $date_to, $status_filter);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Summary</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/tab_logo.png">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Main Content -->
        <div class="p-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-6 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Attendance Summary Report</h1>
                    <p class="mt-1 text-sm text-gray-600">Generate attendance summary by section and date range</p>
                </div>

                <a href="ins_attendance.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </div>

            <!-- Filters -->
            <div class="bg-white shadow rounded-lg mb-6">
                <div class="p-6">
                    <form action="" method="GET" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                            <div>
                                <label for="section" class="block text-sm font-medium text-gray-700">Section</label>
                                <select name="section" id="section" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">All Sections</option>
                                    <?php while ($section = $sections->fetch_assoc()): ?>
                                        <option value="<?= $section['schedule_id'] ?>" <?= $section_filter == $section['schedule_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($section['section']) ?> - <?= htmlspecialchars($section['course_name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                                <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">All Status</option>
                                    <option value="Present" <?= $status_filter == 'Present' ? 'selected' : '' ?>>Present</option>
                                    <option value="Late" <?= $status_filter == 'Late' ? 'selected' : '' ?>>Late</option>
                                    <option value="Absent" <?= $status_filter == 'Absent' ? 'selected' : '' ?>>Absent</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="date_from" class="block text-sm font-medium text-gray-700">Date From</label>
                                <input type="date" name="date_from" id="date_from" value="<?= htmlspecialchars($date_from) ?>" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            
                            <div>
                                <label for="date_to" class="block text-sm font-medium text-gray-700">Date To</label>
                                <input type="date" name="date_to" id="date_to" value="<?= htmlspecialchars($date_to) ?>" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="reset" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Reset
                            </button>
                            <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                                Generate Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!empty($summary_data)): ?>
                <!-- Export Button -->
                <div class="flex justify-end mb-6">
                    <form action="ins_export_summary.php" method="POST">
                        <input type="hidden" name="section" value="<?= htmlspecialchars($section_filter) ?>">
                        <input type="hidden" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                        <input type="hidden" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            <i class="fas fa-file-csv mr-2"></i> Export to CSV
                        </button>
                    </form>
                </div>

                <!-- Detailed Summary Table (Moved above charts) -->
                <div class="bg-white shadow rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Detailed Summary</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Section</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Students</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Present</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Late</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Absent</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">% Present</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">% Late</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">% Absent</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($summary_data as $row): 
                                        $total = $row['present_count'] + $row['late_count'] + $row['absent_count'];
                                        $present_percent = $total > 0 ? round(($row['present_count'] / $total) * 100, 1) : 0;
                                        $late_percent = $total > 0 ? round(($row['late_count'] / $total) * 100, 1) : 0;
                                        $absent_percent = $total > 0 ? round(($row['absent_count'] / $total) * 100, 1) : 0;
                                    ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($row['section']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= htmlspecialchars($row['course_name']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= $row['total_students'] ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600">
                                                <?= $row['present_count'] ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-yellow-600">
                                                <?= $row['late_count'] ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600">
                                                <?= $row['absent_count'] ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600">
                                                <?= $present_percent ?>%
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-yellow-600">
                                                <?= $late_percent ?>%
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600">
                                                <?= $absent_percent ?>%
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Summary Charts (Moved below table and made smaller) -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <!-- Overall Pie Chart -->
                    <div class="bg-white shadow rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Overall Attendance Distribution</h3>
                        <div class="h-64 mx-auto max-w-md">
                            <canvas id="pieChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Section Breakdown Pie Chart (Replaced Bar Chart) -->
                    <div class="bg-white shadow rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Attendance by Section</h3>
                        <div class="h-64 mx-auto max-w-md">
                            <canvas id="sectionPieChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Charts Initialization -->
                <script>
                    // Prepare data for charts
                    const summaryData = <?= json_encode($summary_data) ?>;
                    
                    // Calculate totals for pie chart
                    const totalPresent = summaryData.reduce((sum, row) => sum + parseInt(row.present_count), 0);
                    const totalLate = summaryData.reduce((sum, row) => sum + parseInt(row.late_count), 0);
                    const totalAbsent = summaryData.reduce((sum, row) => sum + parseInt(row.absent_count), 0);
                    const grandTotal = totalPresent + totalLate + totalAbsent;
                    
                    // Calculate percentages
                    const presentPercent = grandTotal > 0 ? Math.round((totalPresent / grandTotal) * 100) : 0;
                    const latePercent = grandTotal > 0 ? Math.round((totalLate / grandTotal) * 100) : 0;
                    const absentPercent = grandTotal > 0 ? Math.round((totalAbsent / grandTotal) * 100) : 0;
                    
                    // Pie Chart with percentages
                    new Chart(document.getElementById('pieChart'), {
                        type: 'pie',
                        data: {
                            labels: [`Present (${presentPercent}%)`, `Late (${latePercent}%)`, `Absent (${absentPercent}%)`],
                            datasets: [{
                                data: [totalPresent, totalLate, totalAbsent],
                                backgroundColor: ['#059669', '#D97706', '#DC2626']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        boxWidth: 12,
                                        font: {
                                            size: 11
                                        }
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.raw || 0;
                                            const percentage = Math.round((value / grandTotal) * 100);
                                            return `${label}: ${value} (${percentage}%)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                    
                    // Section Pie Chart (Replaced Bar Chart)
                    new Chart(document.getElementById('sectionPieChart'), {
                        type: 'pie',
                        data: {
                            labels: summaryData.map(row => row.section),
                            datasets: [{
                                data: summaryData.map(row => {
                                    const total = parseInt(row.present_count) + parseInt(row.late_count) + parseInt(row.absent_count);
                                    return total;
                                }),
                                backgroundColor: [
                                    '#3B82F6', '#8B5CF6', '#EC4899', '#F97316', '#10B981', 
                                    '#6366F1', '#14B8A6', '#84CC16', '#EF4444', '#F59E0B'
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        boxWidth: 12,
                                        font: {
                                            size: 11
                                        }
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.raw || 0;
                                            const section = summaryData[context.dataIndex];
                                            const total = parseInt(section.present_count) + parseInt(section.late_count) + parseInt(section.absent_count);
                                            const presentPct = Math.round((section.present_count / total) * 100);
                                            const latePct = Math.round((section.late_count / total) * 100);
                                            const absentPct = Math.round((section.absent_count / total) * 100);
                                            return [
                                                `${label}: ${value} students`,
                                                `Present: ${section.present_count} (${presentPct}%)`,
                                                `Late: ${section.late_count} (${latePct}%)`,
                                                `Absent: ${section.absent_count} (${absentPct}%)`
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                    });
                </script>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
