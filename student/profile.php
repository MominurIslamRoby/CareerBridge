<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';


/* =========================================
   AUTHORIZATION
========================================= */

requireRole('student');


/* =========================================
   GET CURRENT USER
========================================= */

$user = currentUser();


/* =========================================
   GET STUDENT PROFILE
========================================= */

$stmt = $pdo->prepare(
    '
    SELECT
        s.student_id,
        s.student_id_number,
        s.university_name,
        s.department,
        s.academic_level,
        s.phone,
        s.location,
        s.career_summary,
        s.career_interests,

        u.full_name,
        u.email

    FROM students s

    INNER JOIN users u
        ON u.user_id = s.user_id

    WHERE s.user_id = ?

    LIMIT 1
    '
);

$stmt->execute([
    $user['id']
]);

$student = $stmt->fetch();


if (!$student) {

    http_response_code(404);

    exit('Student profile not found.');
}


/* =========================================
   PAGE MESSAGES
========================================= */

$successMessage = '';

$errorMessage = '';


if (
    isset($_GET['success'])
    &&
    $_GET['success'] === '1'
) {

    $successMessage =
        'Profile updated successfully.';
}


if (isset($_GET['error'])) {

    switch ($_GET['error']) {

        case 'student':

            $errorMessage =
                'Student profile could not be found.';

            break;


        case 'length':

            $errorMessage =
                'One or more fields exceed the allowed character limit.';

            break;


        case 'database':

            $errorMessage =
                'Unable to update your profile. Please try again.';

            break;


        default:

            $errorMessage =
                'Something went wrong. Please try again.';

            break;
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

    <title>
        Career Profile | CareerBridge
    </title>


    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

</head>


<body>


<div class="app-layout">


    <!-- =====================================
         SIDEBAR
    ====================================== -->

    <aside class="sidebar">


        <!-- BRAND -->

        <div class="sidebar-brand">


            <div class="brand-logo">
                <i class="fa-solid fa-bridge"></i>
            </div>


            <div>

                <h2>
                    CareerBridge
                </h2>

                <span>
                    Student Portal
                </span>

            </div>


        </div>


        <div class="sidebar-divider"></div>


        <!-- MENU LABEL -->

        <p class="menu-label">
            MAIN MENU
        </p>


        <!-- NAVIGATION -->

        <nav class="sidebar-nav">


            <a href="dashboard.php">

                <span>
                    <i class="fa-solid fa-house"></i>
                </span>

                Dashboard

            </a>


            <a
                href="profile.php"
                class="active"
            >

                <span>
                    <i class="fa-solid fa-user"></i>
                </span>

                Career Profile

            </a>


            <a href="skills.php">

                <span>
                    <i class="fa-solid fa-bolt"></i>
                </span>

                My Skills

            </a>


            <a href="resume.php">

                <span>
                    <i class="fa-solid fa-file-lines"></i>
                </span>

                Resume / CV

            </a>


            <a href="opportunities.php">

                <span>
                    <i class="fa-solid fa-briefcase"></i>
                </span>

                Opportunities

            </a>


            <a href="applications.php">

                <span>
                    <i class="fa-solid fa-clipboard-list"></i>
                </span>

                My Applications

            </a>


            <a href="interviews.php">

                <span>
                    <i class="fa-solid fa-calendar-check"></i>
                </span>

                My Interviews

            </a>


            <a href="notifications.php">

                <span>
                    <i class="fa-solid fa-bell"></i>
                </span>

                Notifications

            </a>


        </nav>


        <!-- LOGOUT -->

        <div class="sidebar-bottom">


            <a
                href="../auth/logout.php"
                class="logout-link"
            >

                <i class="fa-solid fa-right-from-bracket"></i>

                Logout

            </a>


        </div>


    </aside>



    <!-- =====================================
         MAIN CONTENT
    ====================================== -->

    <main class="main-content">


        <!-- =====================================
             SUCCESS MESSAGE
        ====================================== -->

        <?php if ($successMessage !== ''): ?>


            <div class="alert alert-success">


                <span class="alert-icon">

                    <i class="fa-solid fa-circle-check"></i>

                </span>


                <span>

                    <?= htmlspecialchars(
                        $successMessage,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </span>


            </div>


        <?php endif; ?>



        <!-- =====================================
             ERROR MESSAGE
        ====================================== -->

        <?php if ($errorMessage !== ''): ?>


            <div class="alert alert-error">


                <span class="alert-icon">

                    <i class="fa-solid fa-circle-exclamation"></i>

                </span>


                <span>

                    <?= htmlspecialchars(
                        $errorMessage,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </span>


            </div>


        <?php endif; ?>



        <!-- =====================================
             PAGE HEADER
        ====================================== -->

        <div class="page-header">


            <div>


                <p class="breadcrumb">

                    STUDENT PORTAL / CAREER PROFILE

                </p>


                <h1>

                    Career Profile

                </h1>


                <p class="page-subtitle">

                    Manage your academic and professional information.

                </p>


            </div>



            <!-- USER CARD -->

            <div class="user-card">


                <div class="user-avatar">

                    <?= htmlspecialchars(
                        strtoupper(
                            substr(
                                $student['full_name'],
                                0,
                                1
                            )
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>


                <div>


                    <strong>

                        <?= htmlspecialchars(
                            $student['full_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </strong>


                    <span>
                        Student
                    </span>


                </div>


            </div>


        </div>



        <!-- =====================================
             PROFILE FORM
        ====================================== -->

        <form
            method="POST"
            action="save_profile.php"
            class="profile-form"
        >


            <!-- =====================================
                 PERSONAL INFORMATION
            ====================================== -->

            <section class="content-card">


                <div class="section-heading">


                    <div>


                        <p class="section-label">

                            PERSONAL

                        </p>


                        <h2>

                            Personal Information

                        </h2>


                        <p>

                            Your basic account information.

                        </p>


                    </div>


                    <div class="section-icon">

                        <i class="fa-solid fa-user"></i>

                    </div>


                </div>



                <div class="form-grid">


                    <!-- FULL NAME -->

                    <div class="form-group">


                        <label>

                            Full Name

                        </label>


                        <input
                            type="text"
                            value="<?= htmlspecialchars(
                                $student['full_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            disabled
                        >


                    </div>



                    <!-- EMAIL -->

                    <div class="form-group">


                        <label>

                            Email Address

                        </label>


                        <input
                            type="email"
                            value="<?= htmlspecialchars(
                                $student['email'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            disabled
                        >


                    </div>



                    <!-- STUDENT ID -->

                    <div class="form-group">


                        <label>

                            Student ID

                        </label>


                        <input
                            type="text"
                            value="<?= htmlspecialchars(
                                $student['student_id_number']
                                    ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            disabled
                        >


                    </div>



                    <!-- PHONE -->

                    <div class="form-group">


                        <label for="phone">

                            Phone Number

                        </label>


                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            maxlength="50"
                            value="<?= htmlspecialchars(
                                $student['phone']
                                    ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            placeholder="Enter your phone number"
                        >


                    </div>



                    <!-- LOCATION -->

                    <div class="form-group form-full">


                        <label for="location">

                            Location

                        </label>


                        <input
                            type="text"
                            id="location"
                            name="location"
                            maxlength="150"
                            value="<?= htmlspecialchars(
                                $student['location']
                                    ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            placeholder="City, Country"
                        >


                    </div>


                </div>


            </section>



            <!-- =====================================
                 ACADEMIC INFORMATION
            ====================================== -->

            <section class="content-card">


                <div class="section-heading">


                    <div>


                        <p class="section-label">

                            EDUCATION

                        </p>


                        <h2>

                            Academic Information

                        </h2>


                        <p>

                            Keep your educational background updated.

                        </p>


                    </div>


                    <div class="section-icon">

                        <i class="fa-solid fa-graduation-cap"></i>

                    </div>


                </div>



                <div class="form-grid">


                    <!-- UNIVERSITY -->

                    <div class="form-group form-full">


                        <label for="university_name">

                            University

                        </label>


                        <input
                            type="text"
                            id="university_name"
                            name="university_name"
                            maxlength="255"
                            value="<?= htmlspecialchars(
                                $student['university_name']
                                    ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            placeholder="Enter your university name"
                        >


                    </div>



                    <!-- DEPARTMENT -->

                    <div class="form-group">


                        <label for="department">

                            Department

                        </label>


                        <input
                            type="text"
                            id="department"
                            name="department"
                            maxlength="150"
                            value="<?= htmlspecialchars(
                                $student['department']
                                    ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            placeholder="Enter your department"
                        >


                    </div>



                    <!-- ACADEMIC LEVEL -->

                    <div class="form-group">


                        <label for="academic_level">

                            Academic Level

                        </label>


                        <input
                            type="text"
                            id="academic_level"
                            name="academic_level"
                            maxlength="100"
                            value="<?= htmlspecialchars(
                                $student['academic_level']
                                    ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            placeholder="Example: Undergraduate"
                        >


                    </div>


                </div>


            </section>



            <!-- =====================================
                 CAREER INFORMATION
            ====================================== -->

            <section class="content-card">


                <div class="section-heading">


                    <div>


                        <p class="section-label">

                            CAREER

                        </p>


                        <h2>

                            Career Information

                        </h2>


                        <p>

                            Tell employers about your goals and interests.

                        </p>


                    </div>


                    <div class="section-icon">

                        <i class="fa-solid fa-briefcase"></i>

                    </div>


                </div>



                <div class="form-grid">


                    <!-- CAREER SUMMARY -->

                    <div class="form-group form-full">


                        <label for="career_summary">

                            Career Summary

                        </label>


                        <textarea
                            id="career_summary"
                            name="career_summary"
                            rows="6"
                            maxlength="3000"
                            placeholder="Write a short summary about yourself and your career goals..."
                        ><?= htmlspecialchars(
                            $student['career_summary']
                                ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?></textarea>


                    </div>



                    <!-- CAREER INTERESTS -->

                    <div class="form-group form-full">


                        <label for="career_interests">

                            Career Interests

                        </label>


                        <textarea
                            id="career_interests"
                            name="career_interests"
                            rows="5"
                            maxlength="2000"
                            placeholder="Example: Web Development, Software Engineering, AI..."
                        ><?= htmlspecialchars(
                            $student['career_interests']
                                ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?></textarea>


                    </div>


                </div>


            </section>



            <!-- =====================================
                 FORM ACTIONS
            ====================================== -->

            <div class="form-actions">


                <a
                    href="dashboard.php"
                    class="btn btn-secondary"
                >

                    <i class="fa-solid fa-arrow-left"></i>

                    Back to Dashboard

                </a>



                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    <i class="fa-solid fa-floppy-disk"></i>

                    Save Profile

                </button>


            </div>


        </form>



        <!-- =====================================
             FOOTER
        ====================================== -->

        <footer class="page-footer">

            &copy; <?= date('Y') ?>
            CareerBridge - The Ultimate Career Management Platform

        </footer>


    </main>


</div>


</body>

</html>