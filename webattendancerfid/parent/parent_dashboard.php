<?php
session_start();
include '../db_config.php';

// Check if user is logged in and is a parent (role_id = 4)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 4) {
    header("Location: ../index.php");
    exit();
}

// Fetch parent's information
$stmt = $conn->prepare("SELECT name FROM user WHERE id_number = ? AND role_id = 4");
$stmt->bind_param("s", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$parent = $result->fetch_assoc();
$stmt->close();

// Fetch all children connected to this parent
$stmt = $conn->prepare("
    SELECT 
        u.user_id,
        u.id_number,
        u.name,
        COUNT(DISTINCT e.schedule_id) as total_courses,
        AVG(CASE WHEN a.status = 'Present' THEN 1 WHEN a.status = 'Late' THEN 0.5 ELSE 0 END) * 100 as attendance_rate
    FROM parent_student ps
    JOIN user u ON ps.student_id = u.id_number
    LEFT JOIN enrollment e ON u.user_id = e.user_id
    LEFT JOIN attendance a ON u.user_id = a.user_id
    WHERE ps.parent_id = ?
    GROUP BY u.user_id, u.id_number, u.name
");
$stmt->bind_param("s", $_SESSION['user_id']);
$stmt->execute();
$children = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// If no child is selected yet and we have children, default to the first one
$selected_child_id = $_GET['child_id'] ?? ($children[0]['id_number'] ?? null);

// Get selected child's details
$selected_child = null;
if ($selected_child_id && !empty($children)) {
    foreach ($children as $child) {
        if ($child['id_number'] === $selected_child_id) {
            $selected_child = $child;
            break;
        }
    }
}

// Get selected child's attendance history
if ($selected_child) {
    $stmt = $conn->prepare("
        SELECT 
            a.*,
            c.course_name,
            s.section,
            s.start_time,
            s.end_time,
            s.day
        FROM attendance a
        JOIN schedules s ON a.schedule_id = s.schedule_id
        JOIN courses c ON s.course_id = c.course_id
        WHERE a.user_id = ?
        ORDER BY a.date DESC, s.start_time ASC
        LIMIT 5
    ");
    $stmt->bind_param("i", $selected_child['user_id']);
    $stmt->execute();
    $attendance_history = $stmt->get_result();
    $stmt->close();
}

// Process form for adding a new child
$error_message = '';
$success_message = '';

if (isset($_POST['add_child'])) {
    $new_student_id = $_POST['student_id'];
    
    // Check if student exists
    $check_student = $conn->prepare("SELECT id_number FROM user WHERE id_number = ? AND role_id = 3");
    $check_student->bind_param("s", $new_student_id);
    $check_student->execute();
    $check_student->store_result();
    
    if ($check_student->num_rows == 0) {
        $error_message = "Student ID not found! Please enter a valid student ID.";
    } else {
        // Check if student already has a parent assigned
        $check_existing = $conn->prepare("SELECT parent_id FROM parent_student WHERE student_id = ?");
        $check_existing->bind_param("s", $new_student_id);
        $check_existing->execute();
        $check_existing->store_result();
        
        if ($check_existing->num_rows > 0) {
            $error_message = "This student is already linked to a parent account.";
        } else {
            // Add relationship to parent_student table
            $add_child = $conn->prepare("INSERT INTO parent_student (parent_id, student_id) VALUES (?, ?)");
            $add_child->bind_param("ss", $_SESSION['user_id'], $new_student_id);
            
            if ($add_child->execute()) {
                $success_message = "Child added successfully.";
                // Reload the page to show the new child
                header("Location: parent_dashboard.php");
                exit();
            } else {
                $error_message = "Failed to add child: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard</title>
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
                        <a href="parent_dashboard.php" class="flex items-center px-4 py-3 text-sm font-medium text-white bg-indigo-700 rounded-lg hover:bg-indigo-600 transition duration-150">
                            <i class="fas fa-home w-6"></i>
                            <span>Dashboard</span>
                        </a>
                    </nav>
                    
                    <!-- User Profile -->
                    <div class="flex items-center px-4 py-3 mt-auto mb-6 bg-indigo-950 bg-opacity-50 rounded-lg">
                        <img class="w-10 h-10 rounded-full" src="https://ui-avatars.com/api/?name=<?= urlencode($parent['name']) ?>&background=6366F1&color=fff" alt="User">
                        <div class="ml-3">
                            <p class="text-sm font-medium text-white"><?= htmlspecialchars($parent['name']) ?></p>
                            <p class="text-xs text-indigo-200">Parent</p>
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
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <main class="flex-1 p-4 sm:px-6 lg:px-8 bg-gray-50">
                <div class="mb-6">
                    <h1 class="text-2xl font-semibold text-gray-900">Parent Dashboard</h1>
                    <p class="mt-1 text-sm text-gray-600">Welcome back! Here's an overview of your children's academic progress.</p>
                </div>

                <!-- Error/Success Messages -->
                <?php if (!empty($error_message)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                    <p><?= htmlspecialchars($error_message) ?></p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($success_message)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                    <p><?= htmlspecialchars($success_message) ?></p>
                </div>
                <?php endif; ?>

                <!-- Children Selection -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Your Children</h3>
                        <button type="button" onclick="document.getElementById('addChildModal').classList.remove('hidden')" 
                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <i class="fas fa-plus mr-2"></i> Add Child
                        </button>
                    </div>
                    <div class="flex space-x-4 overflow-x-auto pb-2">
                        <?php if (empty($children)): ?>
                            <div class="text-gray-500 italic">No children linked to your account yet. Add a child to get started.</div>
                        <?php else: ?>
                            <?php foreach ($children as $child): ?>
                                <a href="?child_id=<?= htmlspecialchars($child['id_number']) ?>" 
                                   class="flex flex-col items-center p-4 <?= $child['id_number'] === $selected_child_id ? 'bg-indigo-100 border-2 border-indigo-500' : 'bg-gray-100 hover:bg-gray-200' ?> rounded-lg transition">
                                    <img class="w-16 h-16 rounded-full mb-2" 
                                         src="https://ui-avatars.com/api/?name=<?= urlencode($child['name']) ?>&background=6366F1&color=fff" 
                                         alt="<?= htmlspecialchars($child['name']) ?>">
                                    <p class="text-sm font-medium <?= $child['id_number'] === $selected_child_id ? 'text-indigo-800' : 'text-gray-800' ?>">
                                        <?= htmlspecialchars($child['name']) ?>
                                    </p>
                                    <p class="text-xs <?= $child['id_number'] === $selected_child_id ? 'text-indigo-600' : 'text-gray-600' ?>">
                                        ID: <?= htmlspecialchars($child['id_number']) ?>
                                    </p>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($selected_child): ?>
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
                                                <div class="text-2xl font-semibold text-gray-900"><?= number_format($selected_child['attendance_rate'], 1) ?>%</div>
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
                                                <div class="text-2xl font-semibold text-gray-900"><?= $selected_child['total_courses'] ?></div>
                                            </dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance History -->
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Recent Attendance History</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Day</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if ($attendance_history && $attendance_history->num_rows > 0): ?>
                                        <?php while ($record = $attendance_history->fetch_assoc()): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= date('M d, Y', strtotime($record['date'])) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($record['course_name']) ?></div>
                                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($record['section']) ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= htmlspecialchars($record['day']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= date('h:i A', strtotime($record['start_time'])) ?> - 
                                                    <?= date('h:i A', strtotime($record['end_time'])) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php
                                                    $statusClass = [
                                                        'Present' => 'bg-green-100 text-green-800',
                                                        'Late' => 'bg-yellow-100 text-yellow-800',
                                                        'Absent' => 'bg-red-100 text-red-800'
                                                    ][$record['status']] ?? 'bg-gray-100 text-gray-800';
                                                    ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusClass ?>">
                                                        <?= htmlspecialchars($record['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No attendance records found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php elseif (!empty($children)): ?>
                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                        <p class="text-sm text-blue-700">Please select a child from the list above to view their details.</p>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Add Child Modal -->
    <div id="addChildModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Add Child</h3>
                <button type="button" onclick="document.getElementById('addChildModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form action="" method="POST">
                <div class="mb-4">
                    <label for="student_id" class="block text-sm font-medium text-gray-700 mb-2">Student ID</label>
                    <input type="text" id="student_id" name="student_id" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    <p class="mt-1 text-sm text-gray-500">Enter your child's student ID to link to your account</p>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="document.getElementById('addChildModal').classList.add('hidden')"
                            class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Cancel
                    </button>
                    <button type="submit" name="add_child" 
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Add Child
                    </button>
                </div>
            </form>
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
