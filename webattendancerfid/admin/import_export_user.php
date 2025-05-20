<?php
session_start();
include '../db_config.php';

// Check if user is logged in and is an admin (role_id = 1)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header("Location: ../index.php");
    exit();
}

// Process CSV import
$import_status = '';
$import_message = '';

if (isset($_POST['import'])) {
    if ($_FILES['csv_file']['error'] == 0 && pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION) == 'csv') {
        $handle = fopen($_FILES['csv_file']['tmp_name'], "r");
        
        // Skip the header row
        $header = fgetcsv($handle);
        
        // Check if CSV format is correct
        $required_headers = ['id_number', 'email', 'password', 'name', 'role_id', 'rfid_tag'];
        $valid_format = true;
        
        foreach ($required_headers as $header_field) {
            if (!in_array($header_field, $header)) {
                $valid_format = false;
                break;
            }
        }
        
        // Check if section exists in headers (not required)
        $section_idx = array_search('section', $header);
        
        if (!$valid_format) {
            $import_status = 'error';
            $import_message = 'CSV file format is invalid. Please use the correct format.';
        } else {
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Include section field if available in CSV
                if ($section_idx !== false) {
                    $stmt = $conn->prepare("INSERT INTO user (id_number, email, password, name, role_id, rfid_tag, section) 
                                           VALUES (?, ?, ?, ?, ?, ?, ?) 
                                           ON DUPLICATE KEY UPDATE email = VALUES(email), 
                                                                  password = VALUES(password), 
                                                                  name = VALUES(name), 
                                                                  role_id = VALUES(role_id), 
                                                                  rfid_tag = VALUES(rfid_tag), 
                                                                  section = VALUES(section)");
                } else {
                    $stmt = $conn->prepare("INSERT INTO user (id_number, email, password, name, role_id, rfid_tag) 
                                           VALUES (?, ?, ?, ?, ?, ?) 
                                           ON DUPLICATE KEY UPDATE email = VALUES(email), 
                                                                  password = VALUES(password), 
                                                                  name = VALUES(name), 
                                                                  role_id = VALUES(role_id), 
                                                                  rfid_tag = VALUES(rfid_tag)");
                }
                
                $rows_processed = 0;
                $rows_imported = 0;
                $rows_updated = 0;
                $rows_new = 0;
                
                while (($data = fgetcsv($handle)) !== FALSE) {
                    $rows_processed++;
                    
                    // Map data to columns based on header positions
                    $id_number_idx = array_search('id_number', $header);
                    $email_idx = array_search('email', $header);
                    $password_idx = array_search('password', $header);
                    $name_idx = array_search('name', $header);
                    $role_id_idx = array_search('role_id', $header);
                    $rfid_tag_idx = array_search('rfid_tag', $header);
                    
                    $id_number = trim($data[$id_number_idx]);
                    $email = trim($data[$email_idx]);
                    $password = trim($data[$password_idx]);
                    $name = trim($data[$name_idx]);
                    $role_id = intval(trim($data[$role_id_idx]));
                    $rfid_tag = !empty($data[$rfid_tag_idx]) ? trim($data[$rfid_tag_idx]) : null;
                    
                    // Get section if available
                    $section = ($section_idx !== false && isset($data[$section_idx])) ? trim($data[$section_idx]) : null;
                    
                    // Validate data
                    if (empty($id_number) || empty($email) || empty($name) || !in_array($role_id, [1, 2, 3, 4])) {
                        continue;
                    }
                    
                    // Hash password if it's not already hashed (simple length check)
                    if (strlen($password) < 40) {
                        $password = password_hash($password, PASSWORD_DEFAULT);
                    }
                    
                    if ($section_idx !== false) {
                        $stmt->bind_param("ssssiss", $id_number, $email, $password, $name, $role_id, $rfid_tag, $section);
                    } else {
                        $stmt->bind_param("ssssis", $id_number, $email, $password, $name, $role_id, $rfid_tag);
                    }
                    
                    $stmt->execute();
                    $rows_imported++;
                    
                    // Check if record was inserted or updated
                    // affected_rows = 1 means insert, affected_rows = 2 means update
                    if ($stmt->affected_rows == 1) {
                        $rows_new++;
                    } elseif ($stmt->affected_rows == 2) {
                        $rows_updated++;
                    }
                }
                
                $conn->commit();
                $import_status = 'success';
                $import_message = "$rows_imported out of $rows_processed records were successfully imported ($rows_new new records, $rows_updated existing records updated).";
                
            } catch (Exception $e) {
                $conn->rollback();
                $import_status = 'error';
                $import_message = "An error occurred during import: " . $e->getMessage();
            }
        }
        
        fclose($handle);
    } else {
        $import_status = 'error';
        $import_message = 'Please upload a valid CSV file.';
    }
}

// Fetch admin's name from database
$stmt = $conn->prepare("SELECT name FROM user WHERE id_number = ? AND role_id = 1");
$stmt->bind_param("s", $_SESSION['user_id']);
$stmt->execute();
$result_admin = $stmt->get_result();
$admin = $result_admin->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import/Export Users</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/tab_logo.png">
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        input, select, textarea {
            border: 2px solid #cbd5e0 !important;
            background-color: #f9fafb !important;
        }
        input:focus, select:focus, textarea:focus {
            border-color: #6366f1 !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2) !important;
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
                        <a href="display_user.php" class="flex items-center px-4 py-3 mt-1 text-sm font-medium text-white bg-indigo-700 rounded-lg hover:bg-indigo-600 transition duration-150">
                            <i class="fas fa-users w-6"></i>
                            <span>Users</span>
                        </a>
                        <a href="display_course.php" class="flex items-center px-4 py-3 mt-1 text-sm font-medium text-indigo-100 rounded-lg hover:bg-indigo-700 transition duration-150">
                            <i class="fas fa-chart-bar w-6"></i>
                            <span>Course</span>
                        </a>
                        <a href="display_schedule.php" class="flex items-center px-4 py-3 mt-1 text-sm font-medium text-indigo-100 rounded-lg hover:bg-indigo-700 transition duration-150">
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
                        
                        <h1 class="text-lg font-medium text-gray-900">Import/Export Users</h1>
                        
                        <!-- Right Elements -->
                        <div class="flex items-center space-x-4">
                            <div class="md:hidden">
                                <img class="w-8 h-8 rounded-full" src="https://ui-avatars.com/api/?name=Admin+User&background=6366F1&color=fff" alt="User">
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <main class="flex-1 p-4 sm:px-6 lg:px-8 bg-gray-50">
                <!-- Page Header -->
                <div class="mb-6 flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900">Import/Export User Data</h1>
                        <p class="mt-1 text-sm text-gray-600">Manage bulk user operations</p>
                    </div>
                    <a href="display_user.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Users
                    </a>
                </div>

                <?php if (!empty($import_status)): ?>
                    <div class="mb-6 p-4 rounded-md <?php echo $import_status == 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'; ?>">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <?php if ($import_status == 'success'): ?>
                                    <i class="fas fa-check-circle text-green-400"></i>
                                <?php else: ?>
                                    <i class="fas fa-exclamation-circle text-red-400"></i>
                                <?php endif; ?>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium <?php echo $import_status == 'success' ? 'text-green-800' : 'text-red-800'; ?>">
                                    <?php echo $import_status == 'success' ? 'Import Successful' : 'Import Failed'; ?>
                                </h3>
                                <div class="mt-2 text-sm <?php echo $import_status == 'success' ? 'text-green-700' : 'text-red-700'; ?>">
                                    <p><?php echo $import_message; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="grid md:grid-cols-2 gap-6">
                    <!-- Import Section -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Import Users</h2>
                        <form method="POST" enctype="multipart/form-data" action="">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Upload CSV File
                                </label>
                                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                                    <div class="space-y-1 text-center">
                                        <i class="fas fa-file-csv text-gray-400 text-3xl"></i>
                                        <div class="flex text-sm text-gray-600">
                                            <label for="csv_file" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500">
                                                <span>Upload a CSV file</span>
                                                <input id="csv_file" name="csv_file" type="file" accept=".csv" class="sr-only" required>
                                            </label>
                                            <p class="pl-1">or drag and drop</p>
                                        </div>
                                        <p class="text-xs text-gray-500" id="file-name">CSV format only</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6">
                                <h3 class="text-sm font-medium text-gray-700 mb-2">CSV Format Requirements:</h3>
                                <ul class="list-disc pl-5 text-xs text-gray-600 space-y-1">
                                    <li>First row should contain column headers</li>
                                    <li>Required columns: id_number, email, password, name, role_id, rfid_tag</li>
                                    <li>Optional columns: section (for students)</li>
                                    <li>role_id must be 1 (Admin), 2 (Instructor), 3 (Student), or 4 (Parent)</li>
                                    <li>Password will be hashed if not already hashed</li>
                                    <li>rfid_tag field can be left empty</li>
                                </ul>
                            </div>
                            
                            <div class="mt-2">
                                <a href="export_user_template.php" class="text-sm text-indigo-600 hover:text-indigo-800">
                                    <i class="fas fa-download mr-1"></i> Download Template
                                </a>
                            </div>

                            <div class="mt-6">
                                <button type="submit" name="import" class="w-full bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                    <i class="fas fa-file-import mr-2"></i>Import Users
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Export Section -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Export Users</h2>
                        <p class="text-sm text-gray-600 mb-6">Download current user data in CSV format.</p>
                        
                        <form method="GET" action="export_users.php">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Export Options
                                </label>
                                <div class="mt-2">
                                    <div class="relative flex items-start">
                                        <div class="flex items-center h-5">
                                            <input id="all_users" name="all_users" type="checkbox" class="h-4 w-4 text-indigo-600 border-gray-300 rounded" checked>
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="all_users" class="font-medium text-gray-700">Export all users</label>
                                            <p class="text-gray-500">Include all users in the database</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="role_filter" class="block text-sm font-medium text-gray-700">Filter by Role</label>
                                <select id="role_filter" name="role" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="">All Roles</option>
                                    <option value="1">Admin</option>
                                    <option value="2">Instructor</option>
                                    <option value="3">Student</option>
                                    <option value="4">Parent</option>
                                </select>
                            </div>
                                
                            <div class="mb-4">
                                <label for="include_passwords" class="block text-sm font-medium text-gray-700">Include Passwords</label>
                                <div class="mt-2">
                                    <div class="relative flex items-start">
                                        <div class="flex items-center h-5">
                                            <input id="include_passwords" name="include_passwords" type="checkbox" class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="include_passwords" class="font-medium text-gray-700">Include hashed passwords</label>
                                            <p class="text-gray-500">Not recommended for security reasons</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-6">
                                <button type="submit" class="w-full bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                                    <i class="fas fa-file-export mr-2"></i>Export Users
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Display selected filename
        document.getElementById('csv_file').addEventListener('change', function(e) {
            var fileName = e.target.files[0].name;
            document.getElementById('file-name').textContent = fileName;
        });

        // Mobile menu toggle
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