<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('administrator');

$user = currentUser();

$userId = (int) $user['id'];


/* =========================================
   INITIALIZE DASHBOARD STATISTICS
========================================= */

$totalStudents = 0;

$totalEmployers = 0;

$totalOpportunities = 0;

$openOpportunities = 0;

$closedOpportunities = 0;

$totalApplications = 0;

$submittedApplications = 0;

$shortlistedApplications = 0;

$interviewApplications = 0;

$selectedApplications = 0;

$rejectedApplications = 0;


/* =========================================
   GET STUDENT STATISTICS
========================================= */

$studentStatsStmt = $pdo->query(
    '
    SELECT
        COUNT(*) AS total_students
    FROM students
    '
);

$studentStats = $studentStatsStmt->fetch();

if ($studentStats) {

    $totalStudents =
        (int) (
            $studentStats['total_students']
            ?? 0
        );
}


/* =========================================
   GET EMPLOYER STATISTICS
========================================= */

$employerStatsStmt = $pdo->query(
    '
    SELECT
        COUNT(*) AS total_employers
    FROM employers
    '
);

$employerStats = $employerStatsStmt->fetch();

if ($employerStats) {

    $totalEmployers =
        (int) (
            $employerStats['total_employers']
            ?? 0
        );
}


/* =========================================
   GET OPPORTUNITY STATISTICS
========================================= */

$opportunityStatsStmt = $pdo->query(
    '
    SELECT

        COUNT(*) AS total_opportunities,

        SUM(
            CASE
                WHEN status = "open" THEN 1
                ELSE 0
            END
        ) AS open_opportunities,

        SUM(
            CASE
                WHEN status <> "open" THEN 1
                ELSE 0
            END
        ) AS closed_opportunities

    FROM opportunities
    '
);

$opportunityStats =
    $opportunityStatsStmt->fetch();

if ($opportunityStats) {

    $totalOpportunities =
        (int) (
            $opportunityStats['total_opportunities']
            ?? 0
        );

    $openOpportunities =
        (int) (
            $opportunityStats['open_opportunities']
            ?? 0
        );

    $closedOpportunities =
        (int) (
            $opportunityStats['closed_opportunities']
            ?? 0
        );
}


/* =========================================
   GET APPLICATION STATISTICS
========================================= */

$applicationStatsStmt = $pdo->query(
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
                WHEN status = "shortlisted" THEN 1
                ELSE 0
            END
        ) AS shortlisted_count,

        SUM(
            CASE
                WHEN status = "interview" THEN 1
                ELSE 0
            END
        ) AS interview_count,

        SUM(
            CASE
                WHEN status = "selected" THEN 1
                ELSE 0
            END
        ) AS selected_count,

        SUM(
            CASE
                WHEN status = "rejected" THEN 1
                ELSE 0
            END
        ) AS rejected_count

    FROM applications
    '
);

$applicationStats =
    $applicationStatsStmt->fetch();

if ($applicationStats) {

    $totalApplications =
        (int) (
            $applicationStats['total_applications']
            ?? 0
        );

    $submittedApplications =
        (int) (
            $applicationStats['submitted_count']
            ?? 0
        );

    $shortlistedApplications =
        (int) (
            $applicationStats['shortlisted_count']
            ?? 0
        );

    $interviewApplications =
        (int) (
            $applicationStats['interview_count']
            ?? 0
        );

    $selectedApplications =
        (int) (
            $applicationStats['selected_count']
            ?? 0
        );

    $rejectedApplications =
        (int) (
            $applicationStats['rejected_count']
            ?? 0
        );
}


/* =========================================
   GET RECENT STUDENTS
========================================= */

$recentStudentsStmt = $pdo->query(
    '
    SELECT

        s.student_id,
        s.student_id_number,
        s.university_name,
        s.department,
        s.academic_level,

        u.full_name,
        u.email,
        u.created_at

    FROM students s

    INNER JOIN users u
        ON u.user_id = s.user_id

    ORDER BY u.created_at DESC

    LIMIT 5
    '
);

$recentStudents =
    $recentStudentsStmt->fetchAll();


/* =========================================
   GET RECENT EMPLOYERS
========================================= */

$recentEmployersStmt = $pdo->query(
    '
    SELECT

        e.employer_id,
        e.company_name,
        e.industry,

        u.full_name,
        u.email,
        u.created_at

    FROM employers e

    INNER JOIN users u
        ON u.user_id = e.user_id

    ORDER BY u.created_at DESC

    LIMIT 5
    '
);

$recentEmployers =
    $recentEmployersStmt->fetchAll();


/* =========================================
   GET RECENT OPPORTUNITIES
========================================= */

$recentOpportunitiesStmt = $pdo->query(
    '
    SELECT

        o.opportunity_id,
        o.title,
        o.opportunity_type,
        o.location,
        o.deadline,
        o.status,
        o.created_at,

        e.company_name

    FROM opportunities o

    INNER JOIN employers e
        ON e.employer_id = o.employer_id

    ORDER BY o.created_at DESC

    LIMIT 5
    '
);

$recentOpportunities =
    $recentOpportunitiesStmt->fetchAll();


/* =========================================
   GET RECENT APPLICATIONS
========================================= */

$recentApplicationsStmt = $pdo->query(
    '
    SELECT

        a.application_id,
        a.status,
        a.applied_at,

        u.full_name,

        o.title AS opportunity_title,

        e.company_name

    FROM applications a

    INNER JOIN students s
        ON s.student_id = a.student_id

    INNER JOIN users u
        ON u.user_id = s.user_id

    INNER JOIN opportunities o
        ON o.opportunity_id = a.opportunity_id

    INNER JOIN employers e
        ON e.employer_id = o.employer_id

    ORDER BY a.applied_at DESC

    LIMIT 5
    '
);

$recentApplications =
    $recentApplicationsStmt->fetchAll();


/* =========================================
   HELPER: APPLICATION STATUS CLASS
========================================= */

function getApplicationStatusClass(
    string $status
): string {

    switch ($status) {

        case 'shortlisted':
            return 'status-shortlisted';

        case 'interview':
            return 'status-interview';

        case 'selected':
            return 'status-selected';

        case 'rejected':
            return 'status-rejected';

        case 'submitted':
        default:
            return 'status-submitted';
    }
}


/* =========================================
   HELPER: OPPORTUNITY STATUS CLASS
========================================= */

function getOpportunityStatusClass(
    string $status
): string {

    if ($status === 'open') {

        return 'status-open';
    }

    return 'status-closed';
}


/* =========================================
   HELPER: FORMAT DATE
========================================= */

function formatDashboardDate(
    ?string $date
): string {

    if (empty($date)) {

        return 'Not specified';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {

        return 'Not specified';
    }

    return date(
        'd M Y',
        $timestamp
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
        Administrator Dashboard | CareerBridge
    </title>

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


        <div class="sidebar-brand">


            <div class="brand-logo">
                CB
            </div>


            <div>

                <h2>
                    CareerBridge
                </h2>

                <span>
                    Administrator Portal
                </span>

            </div>


        </div>


        <div class="sidebar-divider"></div>


        <p class="menu-label">
            MAIN MENU
        </p>


        <nav class="sidebar-nav">


            <a
                href="dashboard.php"
                class="active"
            >

                <span>⌂</span>

                Dashboard

            </a>


            <a href="students.php">

                <span>🎓</span>

                Students

            </a>


            <a href="employers.php">

                <span>🏢</span>

                Employers

            </a>


            <a href="opportunities.php">

                <span>💼</span>

                Opportunities

            </a>


            <a href="applications.php">

                <span>▤</span>

                Applications

            </a>


        </nav>


        <div class="sidebar-bottom">


            <a
                href="../auth/logout.php"
                class="logout-link"
            >

                ↪ Logout

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

                    ADMINISTRATOR PORTAL / DASHBOARD

                </p>


                <h1>

                    Administrator Dashboard

                </h1>


                <p class="page-subtitle">

                    Monitor platform activity and manage students, employers,
                    opportunities, and applications.

                </p>


            </div>



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
                        Administrator
                    </span>


                </div>


            </div>


        </div>



        <!-- =====================================
             WELCOME SECTION
        ====================================== -->

        <section class="content-card">


            <div class="section-heading">


                <div>


                    <p class="section-label">
                        PLATFORM OVERVIEW
                    </p>


                    <h2>
                        Welcome back,
                        <?= htmlspecialchars(
                            $user['full_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </h2>


                    <p>

                        Monitor CareerBridge activity and manage
                        the university career management platform.

                    </p>


                </div>


                <div class="section-icon">
                    🛠️
                </div>


            </div>



            <div class="application-meta">


                <div>

                    <span class="meta-label">
                        STUDENTS
                    </span>

                    <strong>
                        <?= $totalStudents ?>
                    </strong>

                </div>



                <div>

                    <span class="meta-label">
                        EMPLOYERS
                    </span>

                    <strong>
                        <?= $totalEmployers ?>
                    </strong>

                </div>



                <div>

                    <span class="meta-label">
                        OPEN OPPORTUNITIES
                    </span>

                    <strong>
                        <?= $openOpportunities ?>
                    </strong>

                </div>



                <div>

                    <span class="meta-label">
                        TOTAL APPLICATIONS
                    </span>

                    <strong>
                        <?= $totalApplications ?>
                    </strong>

                </div>


            </div>


        </section>



        <!-- =====================================
             MAIN STATISTICS
        ====================================== -->

        <section class="stats-grid">


            <div class="stat-card">


                <div class="stat-icon">
                    🎓
                </div>


                <div>

                    <p>
                        Total Students
                    </p>

                    <h2>
                        <?= $totalStudents ?>
                    </h2>

                </div>


            </div>



            <div class="stat-card">


                <div class="stat-icon">
                    🏢
                </div>


                <div>

                    <p>
                        Total Employers
                    </p>

                    <h2>
                        <?= $totalEmployers ?>
                    </h2>

                </div>


            </div>



            <div class="stat-card">


                <div class="stat-icon">
                    💼
                </div>


                <div>

                    <p>
                        Opportunities
                    </p>

                    <h2>
                        <?= $totalOpportunities ?>
                    </h2>

                </div>


            </div>



            <div class="stat-card">


                <div class="stat-icon">
                    📋
                </div>


                <div>

                    <p>
                        Applications
                    </p>

                    <h2>
                        <?= $totalApplications ?>
                    </h2>

                </div>


            </div>


        </section>



        <!-- =====================================
             APPLICATION STATUS OVERVIEW
        ====================================== -->

        <section class="content-card">


            <div class="section-heading">


                <div>


                    <p class="section-label">
                        APPLICATION ANALYTICS
                    </p>


                    <h2>
                        Application Status Summary
                    </h2>


                    <p>

                        Overview of application progress across
                        all opportunities on the platform.

                    </p>


                </div>


                <div class="section-icon">
                    📊
                </div>


            </div>



            <div class="application-meta">


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
                        SHORTLISTED
                    </span>

                    <strong>
                        <?= $shortlistedApplications ?>
                    </strong>

                </div>



                <div>

                    <span class="meta-label">
                        INTERVIEWS
                    </span>

                    <strong>
                        <?= $interviewApplications ?>
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



                <div>

                    <span class="meta-label">
                        REJECTED
                    </span>

                    <strong>
                        <?= $rejectedApplications ?>
                    </strong>

                </div>


            </div>


        </section>



        <!-- =====================================
             QUICK ACTIONS
        ====================================== -->

        <section class="content-card">


            <div class="section-heading">


                <div>


                    <p class="section-label">
                        QUICK ACTIONS
                    </p>


                    <h2>
                        Platform Management
                    </h2>


                    <p>

                        Quickly access the main administrative
                        management areas.

                    </p>


                </div>


                <div class="section-icon">
                    ⚡
                </div>


            </div>



            <div class="form-actions">


                <a
                    href="students.php"
                    class="btn btn-primary"
                >

                    🎓 Manage Students

                </a>


                <a
                    href="employers.php"
                    class="btn btn-secondary"
                >

                    🏢 Manage Employers

                </a>


                <a
                    href="opportunities.php"
                    class="btn btn-secondary"
                >

                    💼 View Opportunities

                </a>


                <a
                    href="applications.php"
                    class="btn btn-secondary"
                >

                    📋 View Applications

                </a>


            </div>


        </section>



        <!-- =====================================
             RECENT APPLICATIONS
        ====================================== -->

        <section class="content-card">


            <div class="section-heading">


                <div>


                    <p class="section-label">
                        RECENT ACTIVITY
                    </p>


                    <h2>
                        Recent Applications
                    </h2>


                    <p>

                        Latest applications submitted by students.

                    </p>


                </div>


                <a
                    href="applications.php"
                    class="btn btn-secondary"
                >

                    View All →

                </a>


            </div>



            <?php if (!$recentApplications): ?>


                <div class="empty-state">


                    <div class="empty-icon">
                        📭
                    </div>


                    <h3>
                        No Applications Yet
                    </h3>


                    <p>

                        Student applications will appear here
                        once opportunities receive applications.

                    </p>


                </div>


            <?php else: ?>


                <div class="table-responsive">


                    <table class="data-table">


                        <thead>


                            <tr>

                                <th>
                                    Student
                                </th>

                                <th>
                                    Opportunity
                                </th>

                                <th>
                                    Company
                                </th>

                                <th>
                                    Applied
                                </th>

                                <th>
                                    Status
                                </th>

                            </tr>


                        </thead>


                        <tbody>


                            <?php foreach (
                                $recentApplications
                                as $application
                            ): ?>


                                <tr>


                                    <td>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $application['full_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>

                                    </td>



                                    <td>

                                        <?= htmlspecialchars(
                                            $application['opportunity_title'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>



                                    <td>

                                        <?= htmlspecialchars(
                                            $application['company_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>



                                    <td>

                                        <?= htmlspecialchars(
                                            formatDashboardDate(
                                                $application['applied_at']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>



                                    <td>


                                        <span
                                            class="status-badge <?= htmlspecialchars(
                                                getApplicationStatusClass(
                                                    $application['status']
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                        >

                                            <?= htmlspecialchars(
                                                ucfirst(
                                                    $application['status']
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </span>


                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        </tbody>


                    </table>


                </div>


            <?php endif; ?>


        </section>



        <!-- =====================================
             RECENT OPPORTUNITIES
        ====================================== -->

        <section class="content-card">


            <div class="section-heading">


                <div>


                    <p class="section-label">
                        OPPORTUNITIES
                    </p>


                    <h2>
                        Recent Opportunities
                    </h2>


                    <p>

                        Latest opportunities posted by employers.

                    </p>


                </div>


                <a
                    href="opportunities.php"
                    class="btn btn-secondary"
                >

                    View All →

                </a>


            </div>



            <?php if (!$recentOpportunities): ?>


                <div class="empty-state">


                    <div class="empty-icon">
                        💼
                    </div>


                    <h3>
                        No Opportunities Yet
                    </h3>


                    <p>

                        Employer opportunities will appear here
                        once they are created.

                    </p>


                </div>


            <?php else: ?>


                <div class="table-responsive">


                    <table class="data-table">


                        <thead>


                            <tr>

                                <th>
                                    Opportunity
                                </th>

                                <th>
                                    Company
                                </th>

                                <th>
                                    Type
                                </th>

                                <th>
                                    Deadline
                                </th>

                                <th>
                                    Status
                                </th>

                            </tr>


                        </thead>


                        <tbody>


                            <?php foreach (
                                $recentOpportunities
                                as $opportunity
                            ): ?>


                                <tr>


                                    <td>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $opportunity['title'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>

                                    </td>



                                    <td>

                                        <?= htmlspecialchars(
                                            $opportunity['company_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>



                                    <td>

                                        <?= htmlspecialchars(
                                            ucfirst(
                                                $opportunity['opportunity_type']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>



                                    <td>

                                        <?= htmlspecialchars(
                                            formatDashboardDate(
                                                $opportunity['deadline']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>



                                    <td>


                                        <span
                                            class="status-badge <?= htmlspecialchars(
                                                getOpportunityStatusClass(
                                                    $opportunity['status']
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                        >

                                            <?= htmlspecialchars(
                                                ucfirst(
                                                    $opportunity['status']
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </span>


                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        </tbody>


                    </table>


                </div>


            <?php endif; ?>


        </section>



        <!-- =====================================
             RECENT STUDENTS
        ====================================== -->

        <section class="content-card">


            <div class="section-heading">


                <div>


                    <p class="section-label">
                        USERS
                    </p>


                    <h2>
                        Recent Students
                    </h2>


                    <p>

                        Latest students registered on CareerBridge.

                    </p>


                </div>


                <a
                    href="students.php"
                    class="btn btn-secondary"
                >

                    View All →

                </a>


            </div>



            <?php if (!$recentStudents): ?>


                <div class="empty-state">


                    <div class="empty-icon">
                        🎓
                    </div>


                    <h3>
                        No Students Found
                    </h3>


                    <p>

                        Registered student accounts will appear here.

                    </p>


                </div>


            <?php else: ?>


                <div class="table-responsive">


                    <table class="data-table">


                        <thead>


                            <tr>

                                <th>
                                    Student
                                </th>

                                <th>
                                    Email
                                </th>

                                <th>
                                    University
                                </th>

                                <th>
                                    Department
                                </th>

                                <th>
                                    Registered
                                </th>

                            </tr>


                        </thead>


                        <tbody>


                            <?php foreach (
                                $recentStudents
                                as $student
                            ): ?>


                                <tr>


                                    <td>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $student['full_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>

                                    </td>



                                    <td>

                                        <?= htmlspecialchars(
                                            $student['email'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>



                                    <td>

                                        <?= htmlspecialchars(
                                            $student['university_name']
                                                ?? 'Not specified',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>



                                    <td>

                                        <?= htmlspecialchars(
                                            $student['department']
                                                ?? 'Not specified',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>



                                    <td>

                                        <?= htmlspecialchars(
                                            formatDashboardDate(
                                                $student['created_at']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        </tbody>


                    </table>


                </div>


            <?php endif; ?>


        </section>



        <!-- =====================================
             RECENT EMPLOYERS
        ====================================== -->

        <section class="content-card">


            <div class="section-heading">


                <div>


                    <p class="section-label">
                        ORGANIZATIONS
                    </p>


                    <h2>
                        Recent Employers
                    </h2>


                    <p>

                        Latest employer accounts registered
                        on CareerBridge.

                    </p>


                </div>


                <a
                    href="employers.php"
                    class="btn btn-secondary"
                >

                    View All →

                </a>


            </div>



            <?php if (!$recentEmployers): ?>


                <div class="empty-state">


                    <div class="empty-icon">
                        🏢
                    </div>


                    <h3>
                        No Employers Found
                    </h3>


                    <p>

                        Registered employer accounts will appear here.

                    </p>


                </div>


            <?php else: ?>


                <div class="table-responsive">


                    <table class="data-table">


                        <thead>


                            <tr>

                                <th>
                                    Company
                                </th>

                                <th>
                                    Contact Person
                                </th>

                                <th>
                                    Email
                                </th>

                                <th>
                                    Industry
                                </th>

                                <th>
                                    Registered
                                </th>

                            </tr>


                        </thead>


                        <tbody>


                            <?php foreach (
                                $recentEmployers
                                as $employerItem
                            ): ?>


                                <tr>


                                    <td>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $employerItem['company_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>

                                    </td>



                                    <td>

                                        <?= htmlspecialchars(
                                            $employerItem['full_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>



                                    <td>

                                        <?= htmlspecialchars(
                                            $employerItem['email'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>



                                    <td>

                                        <?= htmlspecialchars(
                                            $employerItem['industry']
                                                ?? 'Not specified',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>



                                    <td>

                                        <?= htmlspecialchars(
                                            formatDashboardDate(
                                                $employerItem['created_at']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        </tbody>


                    </table>


                </div>


            <?php endif; ?>


        </section>



        <!-- =====================================
             FOOTER
        ====================================== -->

        <footer class="page-footer">

            © <?= date('Y') ?>
            CareerBridge — University Career Management Platform

        </footer>


    </main>


</div>


</body>

</html>