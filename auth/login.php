<?php

session_start();

require_once '../config/database.php';

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    if (empty($email) || empty($password)) {

        $error = 'Please enter your email and password.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';

    } else {

        try {

            // Get user using ACTUAL database column names
            $sql = "SELECT 
                        user_id,
                        full_name,
                        email,
                        password_hash,
                        role,
                        account_status
                    FROM users
                    WHERE email = ?
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([$email]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);


            // Check if user exists
            if (!$user) {

                $error = 'Invalid email or password.';

            // Check account status
            } elseif ($user['account_status'] !== 'active') {

                $error = 'Your account is currently not active. Please contact the administrator.';

            // Verify password
            } elseif (!password_verify($password, $user['password_hash'])) {

                $error = 'Invalid email or password.';

            } else {

                // Regenerate session ID for security
                session_regenerate_id(true);

                // Store user information in session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];


                // Redirect based on role
                switch ($user['role']) {

                    case 'student':

                        header('Location: ../student/dashboard.php');
                        exit;


                    case 'employer':

                        header('Location: ../employer/dashboard.php');
                        exit;


                    case 'administrator':

                        header('Location: ../admin/dashboard.php');
                        exit;


                    default:

                        session_destroy();

                        $error = 'Invalid user role.';
                        break;
                }
            }

        } catch (PDOException $e) {

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

    <title>Login | CareerBridge</title>


    <!-- Google Fonts -->

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
             LEFT BRAND PANEL
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
                        alt="CareerBridge Logo"
                        class="auth-main-logo"
                    >

                </div>


                <!-- Brand Content -->

                <div class="auth-brand-text">

                    <span class="auth-eyebrow">
                        UNIVERSITY CAREER PLATFORM
                    </span>


                    <h1>
                        Your Career Journey Starts Here.
                    </h1>


                    <p>
                        Discover opportunities, connect with employers,
                        manage applications, and take the next step
                        toward your professional future.
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
                                    Jobs and internships tailored for you.
                                </span>

                            </div>

                        </div>


                        <!-- Feature 2 -->

                        <div class="auth-feature">

                            <div class="auth-feature-icon">

                                <i class="fa-solid fa-file-lines"></i>

                            </div>


                            <div>

                                <strong>
                                    Manage Applications
                                </strong>

                                <span>
                                    Track your career progress easily.
                                </span>

                            </div>

                        </div>


                        <!-- Feature 3 -->

                        <div class="auth-feature">

                            <div class="auth-feature-icon">

                                <i class="fa-solid fa-building"></i>

                            </div>


                            <div>

                                <strong>
                                    Connect With Employers
                                </strong>

                                <span>
                                    Build meaningful career connections.
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
             RIGHT LOGIN PANEL
        ========================================== -->

        <section class="auth-form-panel">

            <div class="auth-form-container">


                <!-- Mobile Logo -->

                <div class="auth-mobile-logo">

                    <img
                        src="../assets/images/careerbridgelogo.png"
                        alt="CareerBridge Logo"
                    >

                </div>


                <!-- Header -->

                <div class="auth-form-header">

                    <span class="form-eyebrow">
                        WELCOME BACK
                    </span>


                    <h2>
                        Sign in to CareerBridge
                    </h2>


                    <p>
                        Enter your credentials to access your account.
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


                <!-- Login Form -->

                <form
                    method="POST"
                    action=""
                    class="auth-form"
                >


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
                                autocomplete="email"
                            >

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
                                placeholder="Enter your password"
                                required
                                autocomplete="current-password"
                            >

                        </div>

                    </div>


                    <!-- Submit -->

                    <button
                        type="submit"
                        class="auth-submit-btn"
                    >

                        <span>
                            Sign In
                        </span>

                        <i class="fa-solid fa-arrow-right"></i>

                    </button>


                </form>


                <!-- Divider -->

                <div class="auth-divider">

                    <span>
                        NEW TO CAREERBRIDGE?
                    </span>

                </div>


                <!-- Register Link -->

                <div class="auth-register-section">

                    <p>
                        Don't have an account yet?
                    </p>


                    <a
                        href="register.php"
                        class="auth-register-link"
                    >

                        Create your account

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