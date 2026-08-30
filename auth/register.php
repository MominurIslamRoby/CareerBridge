<?php

session_start();

require_once '../config/database.php';

$error = '';
$success = '';

$full_name = '';
$email = '';
$role = 'student';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'student';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Allowed roles based on database ENUM
    $allowed_roles = ['student', 'employer', 'administrator'];

    // Validation
    if (
        empty($full_name) ||
        empty($email) ||
        empty($role) ||
        empty($password) ||
        empty($confirm_password)
    ) {

        $error = 'Please fill in all required fields.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';

    } elseif (!in_array($role, $allowed_roles, true)) {

        $error = 'Invalid account type selected.';

    } elseif (strlen($password) < 6) {

        $error = 'Password must be at least 6 characters long.';

    } elseif ($password !== $confirm_password) {

        $error = 'Passwords do not match.';

    } else {

        try {

            // Check if email already exists
            $check_sql = "
                SELECT user_id
                FROM users
                WHERE email = ?
                LIMIT 1
            ";

            $stmt = $pdo->prepare($check_sql);
            $stmt->execute([$email]);

            $existing_user = $stmt->fetch();

            if ($existing_user) {

                $error = 'An account with this email already exists.';

            } else {

                // Hash password securely
                $password_hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // Insert new user
                $insert_sql = "
                    INSERT INTO users (
                        full_name,
                        email,
                        password_hash,
                        role,
                        account_status
                    )
                    VALUES (?, ?, ?, ?, 'active')
                ";

                $insert_stmt = $pdo->prepare($insert_sql);

                $insert_success = $insert_stmt->execute([
                    $full_name,
                    $email,
                    $password_hash,
                    $role
                ]);

                if ($insert_success) {

                    $success = 'Account created successfully! Redirecting to login...';

                    header('Refresh: 2; URL=login.php');

                } else {

                    $error = 'Unable to create your account. Please try again.';
                }
            }

        } catch (PDOException $e) {

            // For development/debugging
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Create Account | CareerBridge</title>

    <!-- Google Font -->
    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <!-- Main CSS -->
    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

</head>


<body class="auth-body">

    <div class="auth-layout">


        <!-- =========================================
             LEFT SIDE - BRAND PANEL
        ========================================== -->

        <section class="auth-brand-panel">

            <div class="brand-decoration decoration-one"></div>
            <div class="brand-decoration decoration-two"></div>
            <div class="brand-decoration decoration-three"></div>


            <div class="auth-brand-wrapper">


                <!-- Logo -->

                <div class="auth-logo-container">

                    <img
                        src="../assets/images/careerbridgelogo.png"
                        alt="CareerBridge"
                        class="auth-main-logo"
                    >

                </div>


                <!-- Brand Content -->

                <div class="auth-brand-text">

                    <span class="auth-eyebrow">
                        THE ULTIMATE CAREER FINDER PLATFORM
                    </span>


                    <h1>
                        Start Building Your Future Today.
                    </h1>


                    <p>
                        Join CareerBridge and discover opportunities,
                        connect with employers, and take meaningful steps
                        toward your professional career.
                    </p>


                    <!-- Features -->

                    <div class="auth-feature-list">


                        <!-- Feature 1 -->

                        <div class="auth-feature">

                            <div class="auth-feature-icon">

                                <i class="fa-solid fa-briefcase"></i>

                            </div>


                            <div>

                                <strong>
                                    Discover Opportunities
                                </strong>

                                <span>
                                    Explore jobs and internships.
                                </span>

                            </div>

                        </div>


                        <!-- Feature 2 -->

                        <div class="auth-feature">

                            <div class="auth-feature-icon">

                                <i class="fa-solid fa-chart-line"></i>

                            </div>


                            <div>

                                <strong>
                                    Track Your Progress
                                </strong>

                                <span>
                                    Manage your career journey.
                                </span>

                            </div>

                        </div>


                        <!-- Feature 3 -->

                        <div class="auth-feature">

                            <div class="auth-feature-icon">

                                <i class="fa-solid fa-users"></i>

                            </div>


                            <div>

                                <strong>
                                    Connect & Grow
                                </strong>

                                <span>
                                    Build meaningful professional connections.
                                </span>

                            </div>

                        </div>


                    </div>

                </div>


                <!-- Footer -->

                <div class="auth-brand-footer">

                    <span>
                        © <?php echo date('Y'); ?> CareerBridge
                    </span>

                    <span class="footer-dot"></span>

                    <span>
                        Your Career Journey Starts Here
                    </span>

                </div>


            </div>

        </section>



        <!-- =========================================
             RIGHT SIDE - REGISTRATION FORM
        ========================================== -->

        <section class="auth-form-panel">

            <div class="auth-form-container">


                <!-- Mobile Logo -->

                <div class="auth-mobile-logo">

                    <img
                        src="../assets/images/careerbridgelogo.png"
                        alt="CareerBridge"
                    >

                </div>


                <!-- Header -->

                <div class="auth-form-header">

                    <span class="form-eyebrow">
                        JOIN CAREERBRIDGE
                    </span>


                    <h2>
                        Create your account
                    </h2>


                    <p>
                        Start your journey and unlock new career opportunities.
                    </p>

                </div>



                <!-- Error Message -->

                <?php if (!empty($error)): ?>

                    <div class="auth-alert">

                        <div class="auth-alert-icon">

                            <i class="fa-solid fa-circle-exclamation"></i>

                        </div>

                        <span>
                            <?php echo htmlspecialchars($error); ?>
                        </span>

                    </div>

                <?php endif; ?>



                <!-- Success Message -->

                <?php if (!empty($success)): ?>

                    <div
                        class="auth-alert"
                        style="
                            background: #f0fdf4;
                            border-color: #bbf7d0;
                            color: #166534;
                        "
                    >

                        <div
                            class="auth-alert-icon"
                            style="
                                background: #dcfce7;
                                color: #16a34a;
                            "
                        >

                            <i class="fa-solid fa-circle-check"></i>

                        </div>

                        <span>
                            <?php echo htmlspecialchars($success); ?>
                        </span>

                    </div>

                <?php endif; ?>



                <!-- Registration Form -->

                <form
                    method="POST"
                    action=""
                    class="auth-form"
                >


                    <!-- Full Name -->

                    <div class="auth-form-group">

                        <label for="full_name">
                            Full Name
                        </label>


                        <div class="auth-input-wrapper">

                            <i class="fa-solid fa-user"></i>

                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                placeholder="Enter your full name"
                                value="<?php echo htmlspecialchars($full_name); ?>"
                                required
                            >

                        </div>

                    </div>



                    <!-- Email -->

                    <div class="auth-form-group">

                        <label for="email">
                            Email Address
                        </label>


                        <div class="auth-input-wrapper">

                            <i class="fa-solid fa-envelope"></i>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                placeholder="Enter your email address"
                                value="<?php echo htmlspecialchars($email); ?>"
                                required
                            >

                        </div>

                    </div>



                    <!-- Account Type -->

                    <div class="auth-form-group">

                        <label for="role">
                            Account Type
                        </label>


                        <div class="auth-input-wrapper">

                            <i class="fa-solid fa-user-tag"></i>

                            <select
                                id="role"
                                name="role"
                                required
                            >

                                <option
                                    value="student"
                                    <?php echo $role === 'student' ? 'selected' : ''; ?>
                                >
                                    Student
                                </option>


                                <option
                                    value="employer"
                                    <?php echo $role === 'employer' ? 'selected' : ''; ?>
                                >
                                    Employer
                                </option>


                                <option
                                    value="administrator"
                                    <?php echo $role === 'administrator' ? 'selected' : ''; ?>
                                >
                                    Administrator
                                </option>

                            </select>

                        </div>

                    </div>



                    <!-- Password -->

                    <div class="auth-form-group">

                        <label for="password">
                            Password
                        </label>


                        <div class="auth-input-wrapper">

                            <i class="fa-solid fa-lock"></i>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Create a strong password"
                                required
                            >

                        </div>

                    </div>



                    <!-- Confirm Password -->

                    <div class="auth-form-group">

                        <label for="confirm_password">
                            Confirm Password
                        </label>


                        <div class="auth-input-wrapper">

                            <i class="fa-solid fa-shield-halved"></i>

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                placeholder="Confirm your password"
                                required
                            >

                        </div>

                    </div>



                    <!-- Submit Button -->

                    <button
                        type="submit"
                        class="auth-submit-btn"
                    >

                        <span>
                            Create Account
                        </span>

                        <i class="fa-solid fa-arrow-right"></i>

                    </button>


                </form>



                <!-- Divider -->

                <div class="auth-divider">

                    <span>
                        ALREADY A MEMBER?
                    </span>

                </div>



                <!-- Login Link -->

                <div class="auth-register-section">

                    <p>
                        Already have a CareerBridge account?
                    </p>


                    <a
                        href="login.php"
                        class="auth-register-link"
                    >

                        Sign in to your account

                        <i class="fa-solid fa-arrow-right"></i>

                    </a>

                </div>



                <!-- Security Note -->

                <div class="auth-security-note">

                    <i class="fa-solid fa-shield-halved"></i>

                    <span>
                        Your information is securely protected.
                    </span>

                </div>


            </div>

        </section>


    </div>


</body>

</html>