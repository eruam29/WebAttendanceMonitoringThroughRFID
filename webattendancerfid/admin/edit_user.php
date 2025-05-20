<?php
session_start();
include '../db_config.php';

// Check if user is logged in and is an admin (role_id = 1)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header("Location: ../index.php");
    exit();
}

$error = '';
$success = '';
$showSuccessModal = false;

// Define role options
$roles = [
    1 => 'Admin',
    2 => 'Instructor',
    3 => 'Student',
    4 => 'Parent'
];

// Get user ID from URL parameter
$id_number = $_GET['id'] ?? '';

if (empty($id_number)) {
    header("Location: display_user.php");
    exit();
}

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM user WHERE id_number = ?");
$stmt->bind_param("s", $id_number);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: display_user.php");
    exit();
}

$user = $result->fetch_assoc();
$name = $user['name'];
$email = $user['email'];
$role_id = $user['role_id'];
$rfid_tag = $user['rfid_tag'] ?? '';
$section = $user['section'] ?? '';  // Add this line

// Fetch pending RFID tags
$pending_tags_query = "SELECT rfid_tag FROM pending_tags ORDER BY registration_time DESC";
$pending_tags_result = $conn->query($pending_tags_query);
$pending_tags = [];
if ($pending_tags_result && $pending_tags_result->num_rows > 0) {
    while ($row = $pending_tags_result->fetch_assoc()) {
        $pending_tags[] = $row['rfid_tag'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $new_id_number = $_POST['id_number'] ?? '';
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $section = $_POST['section'] ?? '';  // Add this line
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role_id = $_POST['role_id'] ?? '';
    $rfid_tag = $_POST['rfid_tag'] ?? '';
    
    // Validate form data
    if (empty($new_id_number) || empty($name) || empty($email) || empty($role_id)) {
        $error = "All required fields must be filled out.";
    } elseif (!empty($password) && $password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if new ID number or email already exists (excluding current user)
        $check_stmt = $conn->prepare("SELECT id_number FROM user WHERE (id_number = ? OR email = ?) AND id_number != ?");
        $check_stmt->bind_param("sss", $new_id_number, $email, $id_number);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Another user with this ID number or email already exists.";
        } else {
            // Prepare update query
            if (!empty($password)) {
                // Update with new password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE user SET id_number = ?, name = ?, email = ?, password = ?, role_id = ?, rfid_tag = ?, section = ? WHERE id_number = ?");
                $stmt->bind_param("ssssisss", $new_id_number, $name, $email, $hashed_password, $role_id, $rfid_tag, $section, $id_number);
            } else {
                // Update without changing password
                $stmt = $conn->prepare("UPDATE user SET id_number = ?, name = ?, email = ?, role_id = ?, rfid_tag = ?, section = ? WHERE id_number = ?");
                $stmt->bind_param("sssisss", $new_id_number, $name, $email, $role_id, $rfid_tag, $section, $id_number);
            }
            
            if ($stmt->execute()) {
                $success = "User updated successfully!";
                $showSuccessModal = true;
                
                // If ID number was changed, update the URL
                if ($new_id_number != $id_number) {
                    header("Location: edit_user.php?id=" . $new_id_number . "&success=true");
                    exit();
                }
            } else {
                $error = "Error updating user: " . $conn->error;
            }
        }
    }
}

// Check if we need to show the success modal from a redirect
if (isset($_GET['success']) && $_GET['success'] === 'true') {
    $showSuccessModal = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/tab_logo.png">
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/tab_logo.png">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom styles for input fields and modal -->
    <style>
        input, select, textarea {
            border: 2px solid #cbd5e0 !important;
            background-color: #f9fafb !important;
        }
        input:focus, select:focus, textarea:focus {
            border-color: #6366f1 !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2) !important;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 50;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 90%;
            max-width: 500px;
            position: relative;
            animation: modalFadeIn 0.3s ease-out;
        }
        
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-active {
            display: flex;
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
                        <img class="w-10 h-10 rounded-full" src="https://ui-avatars.com/api/?name=Admin+User&background=6366F1&color=fff" alt="User">
                        <div class="ml-3">
                            <p class="text-sm font-medium text-white">Admin User</p>
                            <p class="text-xs text-indigo-200">admin@example.com</p>
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
                        
                        <h1 class="text-lg font-medium text-gray-900">Edit User</h1>
                        
                        <!-- Right Elements -->
                        <div class="flex items-center space-x-4">
                            <div class="md:hidden">
                                <img class="w-8 h-8 rounded-full" src="https://ui-avatars.com/api/?name=Admin+User&background=6366F1&color=fff" alt="User">
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Form Content -->
            <main class="flex-1 p-4 sm:px-6 lg:px-8 bg-gray-50">
                <div class="mb-6">
                    <h1 class="text-2xl font-semibold text-gray-900">Edit User</h1>
                    <p class="mt-1 text-sm text-gray-600">Update user information</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="mb-4 bg-red-50 border-l-4 border-red-500 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-500"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700"><?php echo $error; ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success) && !$showSuccessModal): ?>
                    <div class="mb-4 bg-green-50 border-l-4 border-green-500 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-500"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-green-700"><?php echo $success; ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- User Form -->
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $id_number); ?>" class="p-6">
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <!-- ID Number -->
                            <div>
                                <label for="id_number" class="block text-sm font-medium text-gray-700">ID Number*</label>
                                <input type="text" name="id_number" id="id_number" value="<?php echo htmlspecialchars($id_number); ?>" required
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-2 border-gray-300 rounded-md">
                            </div>

                            <!-- Name -->
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Full Name*</label>
                                <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($name); ?>" required
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-2 border-gray-300 rounded-md">
                            </div>

                            <!-- Section -->
                            <div>
                                <label for="section" class="block text-sm font-medium text-gray-700">Section (Optional)</label>
                                <input type="text" name="section" id="section" value="<?php echo htmlspecialchars($section); ?>"
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-2 border-gray-300 rounded-md">
                                <p class="mt-1 text-xs text-gray-500">For students, e.g. "401I"</p>
                            </div>

                            <!-- Email -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">Email Address*</label>
                                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>" required
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-2 border-gray-300 rounded-md">
                            </div>

                            <!-- Role -->
                            <div>
                                <label for="role_id" class="block text-sm font-medium text-gray-700">Role*</label>
                                <select name="role_id" id="role_id" required
                                        class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-2 border-gray-300 rounded-md">
                                    <option value="">Select Role</option>
                                    <?php foreach ($roles as $id => $role_name): ?>
                                        <option value="<?php echo $id; ?>" <?php echo ($role_id == $id) ? 'selected' : ''; ?>>
                                            <?php echo $role_name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Password (Optional for edit) -->
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700">Password (Leave blank to keep current)</label>
                                <input type="password" name="password" id="password"
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-2 border-gray-300 rounded-md">
                                <p class="mt-1 text-xs text-gray-500">Leave blank to keep the current password</p>
                            </div>

                            <!-- Confirm Password -->
                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                                <input type="password" name="confirm_password" id="confirm_password"
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-2 border-gray-300 rounded-md">
                            </div>

                            <!-- RFID Tag (Optional) -->
                            <div>
                                <label for="rfid_tag" class="block text-sm font-medium text-gray-700">RFID Tag (Optional)</label>
                                <select name="rfid_tag" id="rfid_tag" 
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-2 border-gray-300 rounded-md">
                                    <option value="">None</option>
                                    
                                    <?php if (!empty($rfid_tag) && !in_array($rfid_tag, $pending_tags)): ?>
                                        <option value="<?php echo htmlspecialchars($rfid_tag); ?>" selected>
                                            <?php echo htmlspecialchars($rfid_tag); ?> (Current)
                                        </option>
                                    <?php endif; ?>
                                    
                                    <?php foreach ($pending_tags as $tag): ?>
                                        <option value="<?php echo htmlspecialchars($tag); ?>" <?php echo ($rfid_tag == $tag) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($tag); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="mt-1 text-xs text-gray-500">Select an RFID tag or choose "None" to remove</p>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-end space-x-3">
                            <a href="display_user.php" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Cancel
                            </a>
                            <button type="submit" class="bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Update User
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="modal <?php echo $showSuccessModal ? 'modal-active' : ''; ?>">
        <div class="modal-content">
            <div class="bg-gradient-to-r from-indigo-800 to-indigo-900 p-4 rounded-t-lg">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-semibold text-white">Success</h3>
                    <button id="closeModal" class="text-white hover:text-gray-200 focus:outline-none">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="bg-green-100 rounded-full p-2 mr-4">
                        <i class="fas fa-check text-green-500 text-xl"></i>
                    </div>
                    <p class="text-lg font-medium text-gray-900">Successfully Updated User</p>
                </div>
                <p class="text-gray-600 mb-4">The user information has been updated in the system.</p>
                <p class="text-gray-500 text-sm italic">Click the X button to close this message.</p>
            </div>
        </div>
    </div>

    <!-- JavaScript for mobile menu toggle and modal -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const menuButton = document.querySelector('button.md\\:hidden');
            const sidebar = document.querySelector('.md\\:flex-shrink-0');
            
            menuButton.addEventListener('click', function() {
                sidebar.classList.toggle('hidden');
            });
            
            // Modal functionality
            const modal = document.getElementById('successModal');
            const closeModalBtn = document.getElementById('closeModal');
            
            if (closeModalBtn) {
                closeModalBtn.addEventListener('click', function() {
                    modal.classList.remove('modal-active');
                });
            }
            
            // Prevent closing modal when clicking outside
            modal.addEventListener('click', function(event) {
                if (event.target === modal) {
                    // Do nothing, prevent closing when clicking outside
                    event.stopPropagation();
                }
            });
        });
    </script>
</body>
</html>
