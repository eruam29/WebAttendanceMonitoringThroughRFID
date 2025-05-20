<?php
include '../db_config.php';
session_start();

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

// Message variables for modal alerts
$show_modal = false;
$modal_message = "";

if (isset($_GET['delete_id'])) {
    $schedule_id = $_GET['delete_id'];
    $conn->query("DELETE FROM schedules WHERE schedule_id = $schedule_id");
    $show_modal = true;
    $modal_message = "Schedule deleted successfully!";
}

// Handle student enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_students'])) {
    $schedule_id = $_POST['schedule_id'];
    $enrollment_type = $_POST['enrollment_type'];
    $success_count = 0;
    $error_count = 0;
    
    if ($enrollment_type === 'individual' && isset($_POST['student_ids'])) {
        // Individual enrollment (existing functionality)
        $student_ids = $_POST['student_ids'];
        foreach ($student_ids as $student_id) {
            $check_stmt = $conn->prepare("SELECT user_id FROM enrollment WHERE schedule_id = ? AND user_id = ?");
            $check_stmt->bind_param("ii", $schedule_id, $student_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error_count++;
            } else {
                $stmt = $conn->prepare("INSERT INTO enrollment (user_id, schedule_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $student_id, $schedule_id);
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
        }
    } elseif ($enrollment_type === 'section' && !empty($_POST['section'])) {
        // Section-based enrollment
        $section = $_POST['section'];
        
        // Get the schedule section to compare
        $sched_stmt = $conn->prepare("SELECT section FROM schedules WHERE schedule_id = ?");
        $sched_stmt->bind_param("i", $schedule_id);
        $sched_stmt->execute();
        $schedule_result = $sched_stmt->get_result();
        $schedule_data = $schedule_result->fetch_assoc();
        $schedule_section = $schedule_data['section'];
        
        // Verify if the entered section matches the schedule section
        if ($section !== $schedule_section) {
            $show_modal = true;
            $modal_message = "The entered section does not match the schedule section. Aborting enrollment.";
        } else {
            // Debug message to console
            echo "<script>console.log('Processing section-based enrollment for section: $section');</script>";
            
            // Get all students who are not already enrolled in this schedule
            // Use the section field instead of ID pattern matching
            $students_query = "SELECT u.user_id 
                              FROM user u 
                              LEFT JOIN enrollment e ON u.user_id = e.user_id AND e.schedule_id = ? 
                              WHERE u.role_id = 3 AND u.section = ? AND e.enrollment_id IS NULL";
            
            $students_stmt = $conn->prepare($students_query);
            $students_stmt->bind_param("is", $schedule_id, $section);
            $students_stmt->execute();
            $students_result = $students_stmt->get_result();
            
            // Enroll all matching students
            if ($students_result->num_rows > 0) {
                $insert_stmt = $conn->prepare("INSERT INTO enrollment (user_id, schedule_id) VALUES (?, ?)");
                
                while ($student = $students_result->fetch_assoc()) {
                    $insert_stmt->bind_param("ii", $student['user_id'], $schedule_id);
                    if ($insert_stmt->execute()) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                }
            }
        }
    }
    
    if ($success_count > 0 || $error_count > 0 || ($success_count == 0 && $error_count == 0)) {
        $show_modal = true;
        $modal_message = '';
        
        if ($success_count > 0) {
            $modal_message .= "Successfully enrolled " . $success_count . " students!\n";
        }
        if ($error_count > 0) {
            $modal_message .= $error_count . " students could not be enrolled. They might already be enrolled or there was an error.\n";
        }
        if ($success_count == 0 && $error_count == 0) {
            $modal_message .= "No students were found to enroll with the provided criteria.";
        }
    }
}

// Fetch schedules
$result = $conn->query("SELECT s.*, c.course_name, u.name
                        FROM schedules s 
                        JOIN courses c ON s.course_id = c.course_id
                        JOIN user u ON s.instructor_id = u.user_id");
                        
// Fetch all students
$students = $conn->query("SELECT user_id, name FROM user WHERE role_id = 3"); // Assuming role_id = 3 is for students

// Fetch all enrollments
$enrollments = $conn->query("
    SELECT e.enrollment_id, u.name AS student_name, s.section, c.course_name 
    FROM enrollment e
    JOIN user u ON e.user_id = u.user_id
    JOIN schedules s ON e.schedule_id = s.schedule_id
    JOIN courses c ON s.course_id = c.course_id
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Manage Schedules</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/tab_logo.png">
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    /* Modal styles */
    .modal {
        display: none;
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
        margin: 10% auto;
        border-radius: 0.5rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        max-width: 500px;
        width: 90%;
        position: relative;
    }
    
    .close-modal {
        cursor: pointer;
    }
    
    /* Multi-select styling */
    select[multiple] {
        height: auto;
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
                        
                        <!-- Right Elements -->
                        <div class="flex items-center space-x-4">
                            <div class="md:hidden">
                                <img class="w-8 h-8 rounded-full" src="https://ui-avatars.com/api/?name=<?= urlencode($admin['name']) ?>&background=6366F1&color=fff" alt="User">
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <main class="flex-1 p-4 sm:px-6 lg:px-8 bg-gray-50">
                <div class="mb-6">
                    <h1 class="text-2xl font-semibold text-gray-900">Manage Schedules</h1>
                    <p class="mt-1 text-sm text-gray-600">View, create, and manage class schedules.</p>
                </div>

                <!-- Schedule Table -->
                <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-medium text-gray-900">Class Schedules</h2>
                            <a href="create_schedule.php" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <i class="fas fa-plus mr-2"></i>Add New Schedule
                            </a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-black text-white">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Section</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Instructor</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Room</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Day</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Time</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Actions</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php while ($row = $result->fetch_assoc()) { ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($row['section']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($row['course_name']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['name']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['room']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['day']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= htmlspecialchars($row['start_time']) ?> - <?= htmlspecialchars($row['end_time']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="edit_schedule.php?id=<?= $row['schedule_id'] ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="delete_schedule.php?id=<?= $row['schedule_id'] ?>" class="text-red-500 hover:text-red-700" 
                                                   onclick="return confirmDeleteSchedule(<?= $row['schedule_id'] ?>, '<?= htmlspecialchars($row['course_name']) ?>', '<?= htmlspecialchars($row['section']) ?>', '<?= htmlspecialchars($row['name']) ?>');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                            <td class="px-4 py-2 whitespace-nowrap text-right text-sm font-medium">
                                                <button onclick="openEnrollmentModal(<?= $row['schedule_id'] ?>, '<?= htmlspecialchars($row['course_name']) ?>', '<?= htmlspecialchars($row['section']) ?>', '<?= htmlspecialchars($row['name']) ?>')" class="text-indigo-600 hover:text-indigo-900">
                                                    <i class="fas fa-user-plus mr-1"></i> Enroll
                                                </button>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                    
                    <!-- Enrollments List -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Current Enrollments</h3>
                            <div class="overflow-x-auto max-h-96">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-black text-white">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Student</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Section</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Course</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php while ($enrollment = $enrollments->fetch_assoc()) { ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($enrollment['student_name']) ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($enrollment['section']) ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($enrollment['course_name']) ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <a href="delete_enrollment.php?id=<?= $enrollment['enrollment_id'] ?>" class="text-red-500 hover:text-red-700"
                                                       onclick="return confirmDeleteEnrollment(<?= $enrollment['enrollment_id'] ?>, '<?= htmlspecialchars($enrollment['student_name']) ?>', '<?= htmlspecialchars($enrollment['course_name']) ?>');">
                                                        <i class="fas fa-user-minus"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Enrollment Modal HTML -->
    <div id="enrollmentModal" class="modal">
        <div class="modal-content max-w-2xl">
            <div class="bg-gradient-to-r from-indigo-800 to-indigo-900 p-4 rounded-t-lg">
                <h3 class="text-white font-bold">Enroll Students</h3>
                <button type="button" class="close-modal absolute top-3 right-4 text-white">&times;</button>
            </div>
            <div class="p-6">
                <form id="enrollmentForm" method="POST">
                    <input type="hidden" id="modal_schedule_id" name="schedule_id">
                    
                    <!-- Schedule Information -->
                    <div class="mb-4">
                        <p class="font-bold">Course: <span id="modal_course" class="font-normal"></span></p>
                        <p class="font-bold">Section: <span id="modal_section" class="font-normal"></span></p>
                        <p class="font-bold">Instructor: <span id="modal_instructor" class="font-normal"></span></p>
                    </div>

                    <!-- Enrollment Type Selector -->
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Enrollment Method:</label>
                        <div class="flex space-x-4">
                            <label class="inline-flex items-center">
                                <input type="radio" name="enrollment_type" value="individual" class="form-radio" checked>
                                <span class="ml-2">Individual Students</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="enrollment_type" value="section" class="form-radio">
                                <span class="ml-2">By Section</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Individual Student Selection -->
                    <div id="individual_enrollment" class="mb-4">
                        <label for="student_ids" class="block text-gray-700 text-sm font-bold mb-2">Select Students:</label>
                        <select name="student_ids[]" multiple class="w-full p-2 border rounded-md" size="8">
                            <?php while ($student = $students->fetch_assoc()): ?>
                                <option value="<?= $student['user_id'] ?>"><?= htmlspecialchars($student['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <p class="text-sm text-gray-500 mt-1">Hold Ctrl/Cmd key to select multiple students</p>
                    </div>

                    <!-- Section-based Enrollment -->
                    <div id="section_enrollment" class="mb-4" style="display:none;">
                        <label for="section" class="block text-gray-700 text-sm font-bold mb-2">Enter Section Code:</label>
                        <input type="text" name="section" class="w-full p-2 border rounded-md" placeholder="e.g., 401I">
                        <p class="text-sm text-gray-500 mt-1">All students with this section in their ID will be enrolled</p>
                    </div>
                    
                    <div class="mt-6 flex justify-end">
                        <button type="button" class="close-modal bg-gray-300 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-400 mr-2">Cancel</button>
                        <button type="submit" name="enroll_students" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">Enroll Students</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Alert Modal -->
    <div id="alertModal" class="modal" style="display: <?= $show_modal ? 'block' : 'none' ?>;">
        <div class="modal-content">
            <div class="bg-indigo-600 p-4 rounded-t-lg">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-semibold text-white">Notification</h3>
                </div>
            </div>
            <div class="p-6">
                <p class="text-gray-700 mb-4" id="modalMessageText"><?= nl2br(htmlspecialchars($modal_message)) ?></p>
                <div class="flex justify-end">
                    <button onclick="closeAlertModal()" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Schedule Confirmation Modal -->
    <div id="deleteScheduleModal" class="modal">
        <div class="modal-content">
            <div class="bg-gradient-to-r from-red-800 to-red-900 p-4 rounded-t-lg">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-semibold text-white">Confirm Delete</h3>
                </div>
            </div>
            <div class="p-6">
                <p class="text-gray-700 mb-4">Are you sure you want to delete this schedule? This action cannot be undone.</p>
                <div id="scheduleDetails" class="bg-gray-100 p-3 rounded-md mb-4">
                    <p><strong>Course:</strong> <span id="deleteCourse"></span></p>
                    <p><strong>Section:</strong> <span id="deleteSection"></span></p>
                    <p><strong>Instructor:</strong> <span id="deleteInstructor"></span></p>
                </div>
                <div class="flex justify-end space-x-3">
                    <button onclick="closeDeleteScheduleModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <a id="deleteScheduleLink" href="#" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 inline-block text-center">
                        Delete
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Enrollment Confirmation Modal -->
    <div id="deleteEnrollmentModal" class="modal">
        <div class="modal-content">
            <div class="bg-gradient-to-r from-red-800 to-red-900 p-4 rounded-t-lg">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-semibold text-white">Confirm Remove Student</h3>
                </div>
            </div>
            <div class="p-6">
                <p class="text-gray-700 mb-4">Are you sure you want to remove this student from the schedule?</p>
                <div id="enrollmentDetails" class="bg-gray-100 p-3 rounded-md mb-4">
                    <p><strong>Student:</strong> <span id="deleteStudent"></span></p>
                    <p><strong>Course:</strong> <span id="deleteEnrollmentCourse"></span></p>
                </div>
                <div class="flex justify-end space-x-3">
                    <button onclick="closeDeleteEnrollmentModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <a id="deleteEnrollmentLink" href="#" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 inline-block text-center">
                        Remove
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript for mobile menu toggle and enrollment validation -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const menuButton = document.querySelector('button.md\\:hidden');
            const sidebar = document.querySelector('.md\\:flex-shrink-0');
            
            if (menuButton && sidebar) {
                menuButton.addEventListener('click', function() {
                    sidebar.classList.toggle('hidden');
                });
            }
            
            // Enrollment form validation
            const enrollmentForm = document.getElementById('enrollmentForm');
            const studentCheckboxes = document.querySelectorAll('.student-checkbox');
            const studentError = document.getElementById('student-error');
            
            if (enrollmentForm) {
                enrollmentForm.addEventListener('submit', function(event) {
                    const enrollmentType = document.querySelector('input[name="enrollment_type"]:checked').value;
                    
                    if (enrollmentType === 'individual') {
                        // Check if at least one student is selected for individual enrollment
                        const selectedStudents = document.querySelectorAll('select[name="student_ids[]"] option:checked');
                        if (selectedStudents.length === 0) {
                            event.preventDefault();
                            showAlertModal('Please select at least one student to enroll');
                        }
                    } else if (enrollmentType === 'section') {
                        // Check if section is entered for section-based enrollment
                        const sectionInput = document.querySelector('input[name="section"]');
                        if (!sectionInput.value.trim()) {
                            event.preventDefault();
                            showAlertModal('Please enter a section code');
                        }
                    }
                });
            }
            
            // Toggle enrollment methods
            const enrollmentTypeRadios = document.querySelectorAll('input[name="enrollment_type"]');
            const individualEnrollment = document.getElementById('individual_enrollment');
            const sectionEnrollment = document.getElementById('section_enrollment');
            
            if (enrollmentTypeRadios.length > 0) {
                enrollmentTypeRadios.forEach(radio => {
                    radio.addEventListener('change', function() {
                        if (this.value === 'individual') {
                            individualEnrollment.style.display = 'block';
                            sectionEnrollment.style.display = 'none';
                        } else {
                            individualEnrollment.style.display = 'none';
                            sectionEnrollment.style.display = 'block';
                        }
                    });
                });
            }
            
            // Modal handlers
            const modal = document.getElementById('enrollmentModal');
            const closeButtons = document.querySelectorAll('.close-modal');
            
            if (closeButtons.length > 0) {
                closeButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        if (modal) modal.style.display = 'none';
                    });
                });
            }
            
            // Close modal when clicking outside
            if (modal) {
                window.addEventListener('click', function(event) {
                    if (event.target === modal) {
                        modal.style.display = 'none';
                    }
                });
            }
            
            // Setup alert modal close button
            const alertCloseBtn = document.getElementById('modalButton');
            if (alertCloseBtn) {
                alertCloseBtn.addEventListener('click', closeAlertModal);
            }
        });
        
        // Define openEnrollmentModal globally, outside of DOMContentLoaded
        function openEnrollmentModal(scheduleId, courseName, section, instructor) {
            const modal = document.getElementById('enrollmentModal');
            if (!modal) {
                console.error('Enrollment modal element not found');
                return;
            }
            
            document.getElementById('modal_schedule_id').value = scheduleId;
            document.getElementById('modal_course').textContent = courseName;
            document.getElementById('modal_section').textContent = section;
            document.getElementById('modal_instructor').textContent = instructor;
            
            // Pre-fill the section field with the schedule's section
            const sectionInput = document.querySelector('input[name="section"]');
            if (sectionInput) sectionInput.value = section;
            
            modal.style.display = 'block';
        }

        // Alert modal functions
        function showAlertModal(message) {
            document.getElementById('modalMessageText').innerHTML = message.replace(/\n/g, '<br>');
            document.getElementById('alertModal').style.display = 'block';
        }
        
        function closeAlertModal() {
            document.getElementById('alertModal').style.display = 'none';
        }
        
        // Delete schedule modal functions
        function confirmDeleteSchedule(id, course, section, instructor) {
            document.getElementById('deleteCourse').textContent = course;
            document.getElementById('deleteSection').textContent = section;
            document.getElementById('deleteInstructor').textContent = instructor;
            document.getElementById('deleteScheduleLink').href = 'delete_schedule.php?id=' + id;
            document.getElementById('deleteScheduleModal').style.display = 'block';
            return false;
        }
        
        function closeDeleteScheduleModal() {
            document.getElementById('deleteScheduleModal').style.display = 'none';
        }
        
        // Delete enrollment modal functions
        function confirmDeleteEnrollment(id, student, course) {
            document.getElementById('deleteStudent').textContent = student;
            document.getElementById('deleteEnrollmentCourse').textContent = course;
            document.getElementById('deleteEnrollmentLink').href = 'delete_enrollment.php?id=' + id;
            document.getElementById('deleteEnrollmentModal').style.display = 'block';
            return false;
        }
        
        function closeDeleteEnrollmentModal() {
            document.getElementById('deleteEnrollmentModal').style.display = 'none';
        }
        
        // Prevent closing modals with ESC key
        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (document.getElementById('alertModal').style.display === 'block' ||
                    document.getElementById('deleteScheduleModal').style.display === 'block' ||
                    document.getElementById('deleteEnrollmentModal').style.display === 'block' ||
                    document.getElementById('enrollmentModal').style.display === 'block') {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>
