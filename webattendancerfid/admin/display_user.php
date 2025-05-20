<?php
session_start();
include '../db_config.php';

// Check if user is logged in and is an admin (role_id = 1)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header("Location: ../index.php");
    exit();
}

// Initialize filter variables
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';

// Build the SQL query with filters
$sql = "SELECT user_id, id_number, email, name, section, role_id, rfid_tag FROM user WHERE 1=1";
$params = [];
$types = "";

// Add search filter if provided
if (!empty($search)) {
    $sql .= " AND (id_number LIKE ? OR email LIKE ? OR name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sss";
}

// Add role filter if provided
if (!empty($role_filter)) {
    $sql .= " AND role_id = ?";
    $params[] = $role_filter;
    $types .= "i";
}

// Order by role_id and name
$sql .= " ORDER BY role_id, name";

// Prepare and execute the query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Define role names for display
$role_names = [
    1 => 'Admin',
    2 => 'Instructor',
    3 => 'Student',
    4 => 'Parent'
];

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
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/tab_logo.png">
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom styles for input fields -->
    <style>
        input, select, textarea {
            border: 2px solid #cbd5e0 !important;
            background-color: #f9fafb !important;
        }
        input:focus, select:focus, textarea:focus {
            border-color: #6366f1 !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2) !important;
        }
        
        /* Filter dropdown styles */
        .filter-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            width: 300px;
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 20;
            padding: 1rem;
            border: 2px solid #e5e7eb;
        }
        
        .filter-dropdown.active {
            display: block;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 50;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
        }

        .modal.modal-active {
            display: flex;
        }

        .modal-content {
            background-color: white;
            border-radius: 0.5rem;
            width: 90%;
            max-width: 500px;
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
                        
                        <h1 class="text-lg font-medium text-gray-900">User Management</h1>
                        
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
                <div class="mb-6 flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900">User Management</h1>
                        <p class="mt-1 text-sm text-gray-600">View and manage all users</p>
                    </div>
                    <div class="flex space-x-3">
                        <a href="import_export_user.php" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                            <i class="fas fa-file-import mr-2"></i>Import/Export
                        </a>
                        <a href="create_user.php" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            <i class="fas fa-user-plus mr-2"></i>Add New User
                        </a>
                    </div>
                </div>

                <!-- Search and Filter Bar -->
                <div class="bg-white p-4 rounded-lg shadow mb-6">
                    <form action="" method="GET" class="flex flex-col md:flex-row gap-4">
                        <div class="flex-grow relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by ID, email or name..." 
                                   class="pl-10 pr-4 py-2 w-full rounded-md border-2 border-gray-300 focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        </div>
                        
                        <div class="relative">
                            <button type="button" id="filterButton" class="flex items-center justify-center px-4 py-2 bg-gray-100 border-2 border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                <i class="fas fa-filter mr-2"></i>
                                <span>Filter</span>
                                <?php if (!empty($role_filter)): ?>
                                    <span class="ml-2 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-indigo-600 rounded-full">!</span>
                                <?php endif; ?>
                            </button>
                            
                            <!-- Filter Dropdown -->
                            <div id="filterDropdown" class="filter-dropdown">
                                <h3 class="font-medium text-gray-700 mb-3">Filter by Role</h3>
                                <div class="space-y-3">
                                    <div>
                                        <label for="role" class="block text-sm font-medium text-gray-700 mb-1">User Role</label>
                                        <select id="role" name="role" class="w-full rounded-md border-2 border-gray-300 focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                            <option value="">All Roles</option>
                                            <?php foreach ($role_names as $id => $name): ?>
                                                <option value="<?php echo $id; ?>" <?php echo ($role_filter == $id) ? 'selected' : ''; ?>>
                                                    <?php echo $name; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="flex justify-between pt-2">
                                        <button type="button" id="clearFilters" class="text-sm text-gray-600 hover:text-gray-900">
                                            Clear Filters
                                        </button>
                                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                            Apply
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            Search
                        </button>
                    </form>
                </div>

                <!-- User Table -->
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-black text-white">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                        ID Number
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                        Name
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                        Section
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                        Email
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                        Role
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                        RFID Tag
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($row['id_number']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($row['name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($row['section'] ?? 'N/A'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($row['email']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php 
                                                    switch($row['role_id']) {
                                                        case 1: echo 'bg-purple-100 text-purple-800'; break;
                                                        case 2: echo 'bg-blue-100 text-blue-800'; break;
                                                        case 3: echo 'bg-green-100 text-green-800'; break;
                                                        case 4: echo 'bg-yellow-100 text-yellow-800'; break;
                                                        default: echo 'bg-gray-100 text-gray-800';
                                                    }
                                                ?>">
                                                <?php echo htmlspecialchars($role_names[$row['role_id']] ?? 'Unknown'); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($row['rfid_tag'] ?? 'Not Set'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="edit_user.php?id=<?php echo urlencode($row['id_number']); ?>" 
                                               class="text-indigo-600 hover:text-indigo-900 mr-3">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="#" 
                                               class="text-red-600 hover:text-red-900"
                                               onclick="confirmDelete('<?php echo urlencode($row['id_number']); ?>', '<?php echo htmlspecialchars($row['name']); ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <p class="text-gray-500">No users found</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Delete Confirmation Modal -->
                <div id="deleteModal" class="modal">
                    <div class="modal-content">
                        <div class="bg-gradient-to-r from-red-800 to-red-900 p-4 rounded-t-lg">
                            <div class="flex justify-between items-center">
                                <h3 class="text-xl font-semibold text-white">Confirm Delete</h3>
                            </div>
                        </div>
                        <div class="p-6">
                            <p class="text-gray-700 mb-4">Are you sure you want to delete <span id="userName" class="font-semibold"></span>? This action cannot be undone.</p>
                            <div class="flex justify-end space-x-3">
                                <button onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">
                                    Cancel
                                </button>
                                <a id="deleteUserLink" href="#" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 inline-block text-center">
                                    Delete
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- JavaScript for delete confirmation -->
                <script>
                function confirmDelete(id, name) {
                    document.getElementById('userName').textContent = name;
                    document.getElementById('deleteUserLink').href = 'delete_user.php?id=' + id;
                    document.getElementById('deleteModal').classList.add('modal-active');
                    return false;
                }

                function closeDeleteModal() {
                    document.getElementById('deleteModal').classList.remove('modal-active');
                }

                // Prevent closing modal with ESC key
                window.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && document.getElementById('deleteModal').classList.contains('modal-active')) {
                        e.preventDefault();
                    }
                });

                // Close modal when clicking outside
                window.onclick = function(event) {
                    const modal = document.getElementById('deleteModal');
                    if (event.target == modal) {
                        // Don't allow closing by clicking outside
                        // closeDeleteModal();
                    }
                }

                // Filter dropdown toggle
                document.getElementById('filterButton').addEventListener('click', function() {
                    document.getElementById('filterDropdown').classList.toggle('active');
                });

                // Clear filters
                document.getElementById('clearFilters').addEventListener('click', function() {
                    window.location.href = 'display_user.php';
                });
                </script>
            </main>
        </div>
    </div>

    <!-- JavaScript for mobile menu toggle and filter dropdown -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const menuButton = document.querySelector('button.md\\:hidden');
            const sidebar = document.querySelector('.md\\:flex-shrink-0');
            
            menuButton.addEventListener('click', function() {
                sidebar.classList.toggle('hidden');
            });
            
            // Filter dropdown toggle
            const filterButton = document.getElementById('filterButton');
            const filterDropdown = document.getElementById('filterDropdown');
            
            filterButton.addEventListener('click', function() {
                filterDropdown.classList.toggle('active');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!filterButton.contains(event.target) && !filterDropdown.contains(event.target)) {
                    filterDropdown.classList.remove('active');
                }
            });
            
            // Clear filters button
            const clearFiltersButton = document.getElementById('clearFilters');
            clearFiltersButton.addEventListener('click', function() {
                document.getElementById('role').value = '';
            });
        });
    </script>
</body>
</html>