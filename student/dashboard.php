<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('student');

$user = currentUser();

$userId = (int) $user['id'];


/*
|--------------------------------------------------------------------------
| Get Student Profile
|--------------------------------------------------------------------------
*/

$studentStmt = $pdo->prepare(
    '
    SELECT
        student_id,
        university_name,
        department,
        academic_level
    FROM students
    WHERE user_id = ?
    LIMIT 1
    '
);

$studentStmt->execute([
    $userId
]);

$student = $studentStmt->fetch();

$studentId = $student
    ? (int) $student['student_id']
    : 0;


/*
|--------------------------------------------------------------------------
| Default Statistics
|--------------------------------------------------------------------------
*/

$totalApplications = 0;
$submittedApplications = 0;
$shortlistedApplications = 0;
$selectedApplications = 0;

$totalOpportunities = 0;


/*
|--------------------------------------------------------------------------
| Application Statistics
|--------------------------------------------------------------------------
*/

if ($studentId > 0) {

    $applicationStatsStmt = $pdo->prepare(
        '
        SELECT

            COUNT(*) AS total_applications,

            SUM(
                CASE
                    WHEN status = "submitted" THEN 1
                    ELSE 0
                END
            ) AS submitted_count,

            SUM(
                CASE
                    WHEN status IN ("shortlisted", "interview") THEN 1
                    ELSE 0
                END
            ) AS shortlisted_count,

            SUM(
                CASE
                    WHEN status = "selected" THEN 1
                    ELSE 0
                END
            ) AS selected_count

        FROM applications

        WHERE student_id = ?
        '
    );

    $applicationStatsStmt->execute([
        $studentId
    ]);

    $applicationStats = $applicationStatsStmt->fetch();

    if ($applicationStats) {

        $totalApplications = (int) (
            $applicationStats['total_applications']
            ?? 0
        );

        $submittedApplications = (int) (
            $applicationStats['submitted_count']
            ?? 0
        );

        $shortlistedApplications = (int) (
            $applicationStats['shortlisted_count']
            ?? 0
        );

        $selectedApplications = (int) (
            $applicationStats['selected_count']
            ?? 0
        );
    }
}


/*
|--------------------------------------------------------------------------
| Opportunity Statistics
|--------------------------------------------------------------------------
*/

$opportunityStatsStmt = $pdo->query(
    '
    SELECT
        COUNT(*) AS total_opportunities
    FROM opportunities
    WHERE status = "open"
    '
);

$opportunityStats = $opportunityStatsStmt->fetch();

if ($opportunityStats) {

    $totalOpportunities = (int) (
        $opportunityStats['total_opportunities']
        ?? 0
    );
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
        Student Dashboard | CareerBridge
    </title>


    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <!-- Main Stylesheet -->

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
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
                CB
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


            <a
                href="dashboard.php"
                class="active"
            >

                <span>
                    <i class="fa-solid fa-house"></i>
                </span>

                Dashboard

            </a>


            <a href="profile.php">

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
             PAGE HEADER
        ====================================== -->

        <div class="page-header">


            <div>


                <p class="breadcrumb">
                    STUDENT PORTAL / DASHBOARD
                </p>


                <h1>
                    Student Dashboard
                </h1>


                <p class="page-subtitle">

                    Welcome back,
                    <strong>
                        <?= htmlspecialchars(
                            $user['full_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </strong>

                </p>


            </div>


            <!-- USER CARD -->

            <div class="user-card">


                <div class="user-avatar">

                    <?= strtoupper(
                        substr(
                            $user['full_name'],
                            0,
                            1
                        )
                    ) ?>

                </div>


                <div>

                    <strong>

                        <?= htmlspecialchars(
                            $user['full_name'],
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
             STATISTICS
        ====================================== -->

        <section class="stats-grid">


            <!-- TOTAL APPLICATIONS -->

            <div class="stat-card">


                <div class="stat-icon">

                    <i class="fa-solid fa-file-circle-check"></i>

                </div>


                <div>

                    <p>
                        Total Applications
                    </p>

                    <h2>
                        <?= $totalApplications ?>
                    </h2>

                </div>


            </div>



            <!-- SUBMITTED -->

            <div class="stat-card">


                <div class="stat-icon">

                    <i class="fa-solid fa-paper-plane"></i>

                </div>


                <div>

                    <p>
                        Submitted
                    </p>

                    <h2>
                        <?= $submittedApplications ?>
                    </h2>

                </div>


            </div>



            <!-- SHORTLISTED -->

            <div class="stat-card">


                <div class="stat-icon">

                    <i class="fa-solid fa-user-check"></i>

                </div>


                <div>

                    <p>
                        Shortlisted / Interview
                    </p>

                    <h2>
                        <?= $shortlistedApplications ?>
                    </h2>

                </div>


            </div>



            <!-- SELECTED -->

            <div class="stat-card">


                <div class="stat-icon">

                    <i class="fa-solid fa-circle-check"></i>

                </div>


                <div>

                    <p>
                        Selected
                    </p>

                    <h2>
                        <?= $selectedApplications ?>
                    </h2>

                </div>


            </div>


        </section>



        <!-- =====================================
             DASHBOARD GRID
        ====================================== -->

        <section class="dashboard-grid">


            <!-- =====================================
                 LEFT COLUMN
            ====================================== -->

            <div class="dashboard-column">


                <!-- APPLICATION SUMMARY -->

                <section class="content-card">


                    <div class="section-heading">


                        <div>

                            <p class="section-label">
                                OVERVIEW
                            </p>


                            <h2>
                                Application Summary
                            </h2>


                            <p>
                                Track the progress of your applications.
                            </p>

                        </div>


                        <div class="section-icon">

                            <i class="fa-solid fa-chart-pie"></i>

                        </div>


                    </div>


                    <div class="application-meta">


                        <div>

                            <span class="meta-label">
                                TOTAL APPLICATIONS
                            </span>

                            <strong>
                                <?= $totalApplications ?>
                            </strong>

                        </div>



                        <div>

                            <span class="meta-label">
                                SUBMITTED
                            </span>

                            <strong>
                                <?= $submittedApplications ?>
                            </strong>

                        </div>



                        <div>

                            <span class="meta-label">
                                SHORTLISTED / INTERVIEW
                            </span>

                            <strong>
                                <?= $shortlistedApplications ?>
                            </strong>

                        </div>



                        <div>

                            <span class="meta-label">
                                SELECTED
                            </span>

                            <strong>
                                <?= $selectedApplications ?>
                            </strong>

                        </div>


                    </div>


                    <div class="form-actions">

                        <a
                            href="applications.php"
                            class="btn btn-secondary"
                        >

                            View My Applications

                            <i class="fa-solid fa-arrow-right"></i>

                        </a>

                    </div>


                </section>



                <!-- AVAILABLE OPPORTUNITIES -->

                <section class="content-card">


                    <div class="section-heading">


                        <div>

                            <p class="section-label">
                                CAREER
                            </p>


                            <h2>
                                Available Opportunities
                            </h2>


                            <p>
                                Discover internships and career opportunities.
                            </p>

                        </div>


                        <div class="section-icon">

                            <i class="fa-solid fa-briefcase"></i>

                        </div>


                    </div>


                    <div class="application-meta">


                        <div>

                            <span class="meta-label">
                                OPEN OPPORTUNITIES
                            </span>

                            <strong>
                                <?= $totalOpportunities ?>
                            </strong>

                        </div>


                        <div>

                            <span class="meta-label">
                                EXPLORE
                            </span>

                            <strong>
                                Jobs & Internships
                            </strong>

                        </div>


                    </div>


                    <div class="form-actions">

                        <a
                            href="opportunities.php"
                            class="btn btn-primary"
                        >

                            <i class="fa-solid fa-magnifying-glass"></i>

                            Browse Opportunities

                        </a>

                    </div>


                </section>



                <!-- QUICK ACCESS -->

                <section class="content-card">


                    <div class="section-heading">


                        <div>

                            <p class="section-label">
                                QUICK ACCESS
                            </p>


                            <h2>
                                Manage Your Career
                            </h2>


                            <p>
                                Quickly access your most important tools.
                            </p>

                        </div>


                        <div class="section-icon">

                            <i class="fa-solid fa-bolt"></i>

                        </div>


                    </div>


                    <div class="form-actions">


                        <a
                            href="profile.php"
                            class="btn btn-secondary"
                        >

                            <i class="fa-solid fa-user"></i>

                            Career Profile

                        </a>


                        <a
                            href="skills.php"
                            class="btn btn-secondary"
                        >

                            <i class="fa-solid fa-code"></i>

                            My Skills

                        </a>


                        <a
                            href="resume.php"
                            class="btn btn-secondary"
                        >

                            <i class="fa-solid fa-file-lines"></i>

                            Resume / CV

                        </a>


                    </div>


                </section>


            </div>



            <!-- =====================================
                 RIGHT COLUMN
            ====================================== -->

            <div class="dashboard-column">


                <!-- NOTIFICATIONS -->

                <section class="content-card">


                    <div class="section-heading">


                        <div>

                            <p class="section-label">
                                UPDATES
                            </p>


                            <h2>
                                Notifications
                            </h2>


                            <p>
                                Stay updated with important information.
                            </p>

                        </div>


                        <div class="section-icon">

                            <i class="fa-solid fa-bell"></i>

                        </div>


                    </div>


                    <div class="empty-state">


                        <div class="empty-icon">

                            <i class="fa-solid fa-bell-slash"></i>

                        </div>


                        <h3>
                            You're all caught up
                        </h3>


                        <p>
                            No new notifications at the moment.
                        </p>


                        <a
                            href="notifications.php"
                            class="btn btn-secondary"
                        >

                            View Notifications

                            <i class="fa-solid fa-arrow-right"></i>

                        </a>


                    </div>


                </section>



                <!-- ACADEMIC INFORMATION -->

                <section class="content-card">


                    <div class="section-heading">


                        <div>

                            <p class="section-label">
                                PROFILE
                            </p>


                            <h2>
                                Academic Information
                            </h2>


                            <p>
                                Your current academic details.
                            </p>

                        </div>


                        <div class="section-icon">

                            <i class="fa-solid fa-graduation-cap"></i>

                        </div>


                    </div>


                    <?php if ($student): ?>


                        <div class="application-meta">


                            <div>

                                <span class="meta-label">

                                    <i class="fa-solid fa-building-columns"></i>

                                    UNIVERSITY

                                </span>


                                <strong>

                                    <?= htmlspecialchars(
                                        $student['university_name']
                                        ?? 'Not provided',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </strong>

                            </div>



                            <div>

                                <span class="meta-label">

                                    <i class="fa-solid fa-book"></i>

                                    DEPARTMENT

                                </span>


                                <strong>

                                    <?= htmlspecialchars(
                                        $student['department']
                                        ?? 'Not provided',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </strong>

                            </div>



                            <div>

                                <span class="meta-label">

                                    <i class="fa-solid fa-layer-group"></i>

                                    ACADEMIC LEVEL

                                </span>


                                <strong>

                                    <?= htmlspecialchars(
                                        $student['academic_level']
                                        ?? 'Not provided',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </strong>

                            </div>


                        </div>


                        <div class="form-actions">

                            <a
                                href="profile.php"
                                class="btn btn-secondary"
                            >

                                Update Profile

                                <i class="fa-solid fa-arrow-right"></i>

                            </a>

                        </div>


                    <?php else: ?>


                        <div class="empty-state">


                            <div class="empty-icon">

                                <i class="fa-solid fa-user-pen"></i>

                            </div>


                            <h3>
                                Profile Incomplete
                            </h3>


                            <p>
                                Complete your student profile to get
                                the most out of CareerBridge.
                            </p>


                            <a
                                href="profile.php"
                                class="btn btn-primary"
                            >

                                <i class="fa-solid fa-plus"></i>

                                Create Profile

                            </a>


                        </div>


                    <?php endif; ?>


                </section>



                <!-- INTERVIEW QUICK ACCESS -->

                <section class="content-card">


                    <div class="section-heading">


                        <div>

                            <p class="section-label">
                                INTERVIEWS
                            </p>


                            <h2>
                                Interview Management
                            </h2>


                            <p>
                                View and manage your scheduled interviews.
                            </p>

                        </div>


                        <div class="section-icon">

                            <i class="fa-solid fa-calendar-check"></i>

                        </div>


                    </div>


                    <div class="form-actions">

                        <a
                            href="interviews.php"
                            class="btn btn-primary"
                        >

                            <i class="fa-solid fa-calendar-check"></i>

                            View My Interviews

                        </a>

                    </div>


                </section>


            </div>


        </section>



        <!-- =====================================
             FOOTER
        ====================================== -->

        <footer class="page-footer">

            &copy; <?= date('Y') ?>
            CareerBridge — University Career Management Platform

        </footer>


    </main>


</div>


</body>

</html>