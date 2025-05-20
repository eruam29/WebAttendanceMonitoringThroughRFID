<?php
session_start();
require_once 'db_config.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on role
    if ($_SESSION['role'] == 1) {
        header("Location: admin/admin_dashboard.php");
    } elseif ($_SESSION['role'] == 2) {
        header("Location: instructor/instructor_dashboard.php");
    } elseif ($_SESSION['role'] == 3) {
        header("Location: student/student_dashboard.php");
    } elseif ($_SESSION['role'] == 4) {
        header("Location: parent/parent_dashboard.php");
    }
    exit;
}

$error_message = '';
$success_message = '';
$registration_step = isset($_POST['registration_step']) ? $_POST['registration_step'] : 0;

// Process login
if (isset($_POST['login'])) {
    $id_number = $_POST['id_number'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    // Fetch user by ID number and email
    $stmt = $conn->prepare("SELECT id_number, email, password, role_id, name FROM user WHERE id_number = ? AND email = ?");
    $stmt->bind_param("ss", $id_number, $email);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($db_id_number, $db_email, $db_password, $role_id, $name);
        $stmt->fetch();
        
        // Verify password (in production, use password_verify())
        if ($password === $db_password) {
            $_SESSION['user_id'] = $db_id_number;
            $_SESSION['role'] = $role_id;
            $_SESSION['name'] = $name;
            
            // Redirect based on role
            if ($role_id == 1) {
                header("Location: admin/admin_dashboard.php");
            } elseif ($role_id == 2) {
                header("Location: instructor/instructor_dashboard.php");
            } elseif ($role_id == 3) {
                header("Location: student/student_dashboard.php");
            } elseif ($role_id == 4) {
                header("Location: parent/parent_dashboard.php");
            }
            exit;
        } else {
            $error_message = "Invalid password!";
        }
    } else {
        $error_message = "User not found with the provided ID number and email!";
    }
}

// Process registration
if (isset($_POST['register']) && $registration_step > 0) {
    // Step 1: Role selection - just store the role and move to step 2
    if ($registration_step == 1 && isset($_POST['role'])) {
        $role = $_POST['role'];
        $registration_step = 2;
    }
    // Step 2: Basic information
    elseif ($registration_step == 2) {
        $id_number = $_POST['id_number'];
        $name = $_POST['name'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $role = $_POST['role'];
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Invalid email format!";
        }
        // Check if passwords match
        elseif ($password !== $confirm_password) {
            $error_message = "Passwords do not match!";
        }
        // Check if ID number or email already exists
        else {
            // Different validation for parent role
            if ($role == 4) {
                // Check if student exists
                $check_student = $conn->prepare("SELECT id_number FROM user WHERE id_number = ? AND role_id = 3");
                $check_student->bind_param("s", $id_number);
                $check_student->execute();
                $check_student->store_result();
                
                if ($check_student->num_rows == 0) {
                    $error_message = "Student ID not found! Please enter a valid student ID.";
                } else {
                    // Check if parent account already exists for this student
                    $parent_id = 'P' . $id_number;
                    $check_parent = $conn->prepare("SELECT parent_id FROM parent_student WHERE student_id = ?");
                    $check_parent->bind_param("s", $id_number);
                    $check_parent->execute();
                    $check_parent->store_result();
                    
                    if ($check_parent->num_rows > 0) {
                        $error_message = "A parent account is already registered for this student!";
                    } else {
                        // For parent role, move to step 3 to confirm
                        $_SESSION['temp_registration'] = [
                            'id_number' => $parent_id,
                            'name' => $name,
                            'email' => $email,
                            'password' => $password,
                            'role' => $role,
                            'student_id' => $id_number
                        ];
                        $registration_step = 3;
                    }
                    $check_parent->close();
                }
                $check_student->close();
            } else {
                // For non-parent roles, check both ID and email
                $check_stmt = $conn->prepare("SELECT id_number FROM user WHERE id_number = ? OR email = ?");
                $check_stmt->bind_param("ss", $id_number, $email);
                $check_stmt->execute();
                $check_stmt->store_result();
                
                if ($check_stmt->num_rows > 0) {
                    $error_message = "ID number or email already exists!";
                } else {
                    // For student and instructor, complete registration
                    $insert_stmt = $conn->prepare("INSERT INTO user (id_number, email, password, name, role_id) VALUES (?, ?, ?, ?, ?)");
                    $insert_stmt->bind_param("ssssi", $id_number, $email, $password, $name, $role);
                    
                    if ($insert_stmt->execute()) {
                        $success_message = "Registration successful! You can now login.";
                        $registration_step = 0; // Reset to login form
                    } else {
                        $error_message = "Registration failed: " . $conn->error;
                    }
                }
                $check_stmt->close();
            }
        }
    }
    // Step 3: Parent Registration Confirmation
    elseif ($registration_step == 3 && isset($_SESSION['temp_registration'])) {
        $temp_data = $_SESSION['temp_registration'];
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Insert parent user
            $insert_parent = $conn->prepare("INSERT INTO user (id_number, email, password, name, role_id) VALUES (?, ?, ?, ?, ?)");
            $insert_parent->bind_param("ssssi", 
                $temp_data['id_number'],  // This will be the P{student_id} format
                $temp_data['email'],
                $temp_data['password'],
                $temp_data['name'],
                $temp_data['role']
            );
            
            if (!$insert_parent->execute()) {
                throw new Exception("Failed to create parent account: " . $conn->error);
            }
            
            // Link parent with student in parent_student table
            $insert_link = $conn->prepare("INSERT INTO parent_student (parent_id, student_id) VALUES (?, ?)");
            $insert_link->bind_param("ss", 
                $temp_data['id_number'],  // Parent ID (P{student_id})
                $temp_data['student_id']  // Student ID
            );
            
            if (!$insert_link->execute()) {
                throw new Exception("Failed to link parent and student: " . $conn->error);
            }
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Parent registration successful! You can now login with ID: " . $temp_data['id_number'];
            $registration_step = 0; // Reset to login form
            unset($_SESSION['temp_registration']); // Clear temporary data
            
            // Add this after successful registration for debugging
            if ($success_message && strpos($success_message, "Parent registration successful") !== false) {
                // Check user table
                $check_user = $conn->prepare("SELECT * FROM user WHERE id_number = ?");
                $check_user->bind_param("s", $temp_data['id_number']);
                $check_user->execute();
                $user_result = $check_user->get_result();
                
                // Check parent_student table
                $check_link = $conn->prepare("SELECT * FROM parent_student WHERE parent_id = ? AND student_id = ?");
                $check_link->bind_param("ss", $temp_data['id_number'], $temp_data['student_id']);
                $check_link->execute();
                $link_result = $check_link->get_result();
                
                if ($user_result->num_rows > 0 && $link_result->num_rows > 0) {
                    $success_message .= "\nVerified: Account and link created successfully.";
                } else {
                    $success_message .= "\nWarning: Data verification failed.";
                }
            }
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Registration failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechTrack - Login</title>
     <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/tab_logo.png">
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .bg-gradient {
            background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="container mx-auto px-4 py-8 max-w-md">
        <div class="bg-white rounded-xl shadow-xl overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient p-6 text-center">
                <h1 class="text-2xl font-bold text-white">TechTrack</h1>
                <p class="text-indigo-100 mt-2">Sign in to access your dashboard</p>
            </div>
            
            <!-- Alert Messages -->
            <?php if (!empty($error_message)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 mx-6 mt-6">
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 mx-6 mt-6">
                    <p><?php echo $success_message; ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <?php if ($registration_step == 0): ?>
                <div class="p-6">
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label for="id_number" class="block text-gray-700 text-sm font-medium mb-2">ID Number</label>
                            <input type="text" id="id_number" name="id_number" required 
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        
                        <div class="mb-4">
                            <label for="email" class="block text-gray-700 text-sm font-medium mb-2">Email</label>
                            <input type="email" id="email" name="email" required 
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        
                        <div class="mb-6">
                            <label for="password" class="block text-gray-700 text-sm font-medium mb-2">Password</label>
                            <input type="password" id="password" name="password" required 
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        
                        <div class="flex items-center justify-between mb-4">
                            <button type="submit" name="login" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                Sign In
                            </button>
                            
                            <button type="button" onclick="document.getElementById('registration_step').value='1'; document.getElementById('registration_form').submit();" 
                                class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                Create Account
                            </button>
                        </div>
                    </form>
                    
                    <!-- Hidden form to start registration -->
                    <form id="registration_form" method="POST" action="" class="hidden">
                        <input type="hidden" name="registration_step" id="registration_step" value="1">
                        <input type="hidden" name="register" value="1">
                    </form>
                </div>
            
            <!-- Registration Step 1: Role Selection -->
            <?php elseif ($registration_step == 1): ?>
                <div class="p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-6">Step 1: Select Your Role</h2>
                    <p class="text-gray-600 mb-6">What role do you want to register your account as?</p>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="registration_step" value="1">
                        <input type="hidden" name="register" value="1">
                        
                        <div class="space-y-4 mb-6">
                            <label class="flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="radio" name="role" value="3" class="h-5 w-5 text-indigo-600" required>
                                <div class="ml-4">
                                    <div class="text-gray-900 font-medium">Student</div>
                                    <div class="text-gray-500 text-sm">Register as a student to track your attendance</div>
                                </div>
                            </label>
                            
                            <label class="flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="radio" name="role" value="2" class="h-5 w-5 text-indigo-600">
                                <div class="ml-4">
                                    <div class="text-gray-900 font-medium">Instructor</div>
                                    <div class="text-gray-500 text-sm">Register as an instructor to manage classes and attendance</div>
                                </div>
                            </label>
                            
                            <label class="flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="radio" name="role" value="4" class="h-5 w-5 text-indigo-600">
                                <div class="ml-4">
                                    <div class="text-gray-900 font-medium">Parent</div>
                                    <div class="text-gray-500 text-sm">Register as a parent to monitor your child's attendance</div>
                                </div>
                            </label>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <button type="button" onclick="window.location.href='index.php'" 
                                class="text-gray-600 hover:text-gray-800">
                                Back to Login
                            </button>
                            
                            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                Continue
                            </button>
                        </div>
                    </form>
                </div>
            
            <!-- Registration Step 2: Basic Information -->
            <?php elseif ($registration_step == 2): ?>
                <div class="p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-6">Step 2: Account Information</h2>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="registration_step" value="2">
                        <input type="hidden" name="register" value="1">
                        <input type="hidden" name="role" value="<?php echo $_POST['role']; ?>">
                        
                        <?php if ($_POST['role'] == 4): ?>
                            <div class="mb-4">
                                <label for="id_number" class="block text-gray-700 text-sm font-medium mb-2">Student's ID Number</label>
                                <input type="text" id="id_number" name="id_number" required 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    placeholder="Enter your child's student ID">
                                <p class="mt-1 text-sm text-gray-500">Enter your child's student ID number to link accounts</p>
                            </div>
                        <?php else: ?>
                            <div class="mb-4">
                                <label for="id_number" class="block text-gray-700 text-sm font-medium mb-2">ID Number</label>
                                <input type="text" id="id_number" name="id_number" required 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-4">
                            <label for="name" class="block text-gray-700 text-sm font-medium mb-2">Full Name</label>
                            <input type="text" id="name" name="name" required 
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        
                        <div class="mb-4">
                            <label for="email" class="block text-gray-700 text-sm font-medium mb-2">Email</label>
                            <input type="email" id="email" name="email" required 
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="block text-gray-700 text-sm font-medium mb-2">Password</label>
                            <input type="password" id="password" name="password" required 
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        
                        <div class="mb-6">
                            <label for="confirm_password" class="block text-gray-700 text-sm font-medium mb-2">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required 
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <button type="button" onclick="window.location.href='index.php?step=1'" 
                                class="text-gray-600 hover:text-gray-800">
                                Back
                            </button>
                            
                            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                <?php echo ($_POST['role'] == 4) ? 'Continue' : 'Register'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            
            <!-- Registration Step 3: Parent Confirmation -->
            <?php elseif ($registration_step == 3 && isset($_SESSION['temp_registration'])): ?>
                <div class="p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-6">Step 3: Confirm Parent Registration</h2>
                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-blue-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-blue-700">
                                    Your parent account ID will be: <strong><?= htmlspecialchars($_SESSION['temp_registration']['id_number']) ?></strong>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="registration_step" value="3">
                        <input type="hidden" name="register" value="1">
                        
                        <div class="space-y-4 mb-6">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="text-sm font-medium text-gray-700 mb-2">Registration Details</h3>
                                <dl class="grid grid-cols-1 gap-2">
                                    <div class="flex justify-between">
                                        <dt class="text-sm text-gray-600">Parent Name:</dt>
                                        <dd class="text-sm text-gray-900"><?= htmlspecialchars($_SESSION['temp_registration']['name']) ?></dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-sm text-gray-600">Email:</dt>
                                        <dd class="text-sm text-gray-900"><?= htmlspecialchars($_SESSION['temp_registration']['email']) ?></dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-sm text-gray-600">Student ID:</dt>
                                        <dd class="text-sm text-gray-900"><?= htmlspecialchars($_SESSION['temp_registration']['student_id']) ?></dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <button type="button" onclick="window.location.href='index.php?step=2'" 
                                class="text-gray-600 hover:text-gray-800">
                                Back
                            </button>
                            
                            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                Complete Registration
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
            
            <!-- Footer -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 text-center">
                <p class="text-sm text-gray-600">© 2025 TechTrack. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>