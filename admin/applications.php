<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('administrator');

$user = currentUser();


/* =========================================
   GET FILTER
========================================= */

$statusFilter = isset($_GET['status'])
    ? trim((string) $_GET['status'])
    : 'all';


$allowedStatuses = [
    'all',
    'submitted',
    'shortlisted',
    'interview',
    'selected',
    'rejected'
];


if (!in_array(
    $statusFilter,
    $allowedStatuses,
    true
)) {

    $statusFilter = 'all';
}


/* =========================================
   INITIALIZE STATISTICS
========================================= */

$totalApplications = 0;

$submittedApplications = 0;

$shortlistedApplications = 0;

$interviewApplications = 0;

$selectedApplications = 0;

$rejectedApplications = 0;


/* =========================================
   GET APPLICATION STATISTICS
========================================= */

$statsStmt = $pdo->query(
    '
    SELECT

        COUNT(*) AS total_applications,

        SUM(
            CASE
                WHEN status = "submitted"
                THEN 1
                ELSE 0
            END
        ) AS submitted_count,

        SUM(
            CASE
                WHEN status = "shortlisted"
                THEN 1
                ELSE 0
            END
        ) AS shortlisted_count,

        SUM(
            CASE
                WHEN status = "interview"
                THEN 1
                ELSE 0
            END
        ) AS interview_count,

        SUM(
            CASE
                WHEN status = "selected"
                THEN 1
                ELSE 0
            END
        ) AS selected_count,

        SUM(
            CASE
                WHEN status = "rejected"
                THEN 1
                ELSE 0
            END
        ) AS rejected_count

    FROM applications
    '
);

$stats = $statsStmt->fetch();


if ($stats) {

    $totalApplications =
        (int) (
            $stats['total_applications']
            ?? 0
        );

    $submittedApplications =
        (int) (
            $stats['submitted_count']
            ?? 0
        );

    $shortlistedApplications =
        (int) (
            $stats['shortlisted_count']
            ?? 0
        );

    $interviewApplications =
        (int) (
            $stats['interview_count']
            ?? 0
        );

    $selectedApplications =
        (int) (
            $stats['selected_count']
            ?? 0
        );

    $rejectedApplications =
        (int) (
            $stats['rejected_count']
            ?? 0
        );
}


/* =========================================
   BUILD APPLICATION QUERY
========================================= */

$sql = '
    SELECT

        a.application_id,
        a.status,
        a.applied_at,

        s.student_id,
        s.student_id_number,
        s.university_name,
        s.department,
        s.academic_level,

        u.full_name,
        u.email,

        o.opportunity_id,
        o.title AS opportunity_title,
        o.opportunity_type,
        o.location,
        o.deadline,

        e.employer_id,
        e.company_name,
        e.industry,

        r.file_name AS resume_file_name,
        r.file_path AS resume_file_path

    FROM applications a

    INNER JOIN students s
        ON s.student_id = a.student_id

    INNER JOIN users u
        ON u.user_id = s.user_id

    INNER JOIN opportunities o
        ON o.opportunity_id = a.opportunity_id

    INNER JOIN employers e
        ON e.employer_id = o.employer_id

    LEFT JOIN resumes r
        ON r.resume_id = a.resume_id
';


$params = [];


/* =========================================
   APPLY STATUS FILTER
========================================= */

if ($statusFilter !== 'all') {

    $sql .= '
        WHERE a.status = ?
    ';

    $params[] = $statusFilter;
}


/* =========================================
   ORDER APPLICATIONS
========================================= */

$sql .= '
    ORDER BY
        a.applied_at DESC
';


$applicationsStmt = $pdo->prepare(
    $sql
);

$applicationsStmt->execute(
    $params
);

$applications =
    $applicationsStmt->fetchAll();


/* =========================================
   HELPER: STATUS CLASS
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
   HELPER: STATUS ICON
========================================= */

function getApplicationStatusIcon(
    string $status
): string {

    switch ($status) {

        case 'shortlisted':
            return '⭐';

        case 'interview':
            return '🎯';

        case 'selected':
            return '🎉';

        case 'rejected':
            return '✕';

        case 'submitted':
        default:
            return '📩';
    }
}


/* =========================================
   HELPER: FORMAT DATE
========================================= */

function formatApplicationDate(
    ?string $date
): string {

    if (empty($date)) {

        return 'Not specified';
    }

    $timestamp = strtotime(
        $date
    );

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
        Applications | CareerBridge
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


            <a href="dashboard.php">

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


            <a
                href="applications.php"
                class="active"
            >

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

                    ADMINISTRATOR PORTAL / APPLICATIONS

                </p>


                <h1>
                    Applications
                </h1>


                <p class="page-subtitle">

                    Monitor and review all student applications
                    submitted across the CareerBridge platform.

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
                        Administrator
                    </span>


                </div>


            </div>


        </div>



        <!-- =====================================
             STATISTICS
        ====================================== -->

        <section class="stats-grid">


            <!-- TOTAL -->

            <div class="stat-card">


                <div class="stat-icon">
                    📋
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
                    📩
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
                    ⭐
                </div>


                <div>


                    <p>
                        Shortlisted
                    </p>


                    <h2>
                        <?= $shortlistedApplications ?>
                    </h2>


                </div>


            </div>



            <!-- SELECTED -->

            <div class="stat-card">


                <div class="stat-icon">
                    🎉
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
             APPLICATION STATUS SUMMARY
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

                        Overview of the current application
                        progress across the entire platform.

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
             FILTER
        ====================================== -->

        <section class="content-card">


            <div class="section-heading">


                <div>


                    <p class="section-label">
                        FILTER
                    </p>


                    <h2>
                        Filter Applications
                    </h2>


                    <p>

                        View applications based on their
                        current recruitment status.

                    </p>


                </div>


                <div class="section-icon">
                    🔎
                </div>


            </div>



            <div class="form-actions">


                <a
                    href="applications.php"
                    class="btn <?= $statusFilter === 'all'
                        ? 'btn-primary'
                        : 'btn-secondary'
                    ?>"
                >

                    All

                </a>


                <a
                    href="applications.php?status=submitted"
                    class="btn <?= $statusFilter === 'submitted'
                        ? 'btn-primary'
                        : 'btn-secondary'
                    ?>"
                >

                    Submitted

                </a>


                <a
                    href="applications.php?status=shortlisted"
                    class="btn <?= $statusFilter === 'shortlisted'
                        ? 'btn-primary'
                        : 'btn-secondary'
                    ?>"
                >

                    Shortlisted

                </a>


                <a
                    href="applications.php?status=interview"
                    class="btn <?= $statusFilter === 'interview'
                        ? 'btn-primary'
                        : 'btn-secondary'
                    ?>"
                >

                    Interview

                </a>


                <a
                    href="applications.php?status=selected"
                    class="btn <?= $statusFilter === 'selected'
                        ? 'btn-primary'
                        : 'btn-secondary'
                    ?>"
                >

                    Selected

                </a>


                <a
                    href="applications.php?status=rejected"
                    class="btn <?= $statusFilter === 'rejected'
                        ? 'btn-primary'
                        : 'btn-secondary'
                    ?>"
                >

                    Rejected

                </a>


            </div>


        </section>



        <!-- =====================================
             APPLICATIONS LIST
        ====================================== -->

        <section class="content-card">


            <div class="section-heading">


                <div>


                    <p class="section-label">
                        ALL APPLICATIONS
                    </p>


                    <h2>
                        Student Applications
                    </h2>


                    <p>

                        Review applications submitted by students
                        to opportunities posted by employers.

                    </p>


                </div>


                <div class="section-icon">
                    👥
                </div>


            </div>



            <!-- =====================================
                 EMPTY STATE
            ====================================== -->

            <?php if (!$applications): ?>


                <div class="empty-state">


                    <div class="empty-icon">
                        📭
                    </div>


                    <h3>
                        No Applications Found
                    </h3>


                    <p>

                        No applications match the selected filter.

                    </p>


                    <?php if (
                        $statusFilter !== 'all'
                    ): ?>


                        <a
                            href="applications.php"
                            class="btn btn-primary"
                        >

                            View All Applications

                        </a>


                    <?php endif; ?>


                </div>



            <!-- =====================================
                 APPLICATION TABLE
            ====================================== -->

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
                                    Department
                                </th>

                                <th>
                                    Applied
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Resume
                                </th>

                                <th>
                                    Action
                                </th>

                            </tr>


                        </thead>


                        <tbody>


                            <?php foreach (
                                $applications
                                as $application
                            ): ?>


                                <tr>


                                    <!-- STUDENT -->

                                    <td>


                                        <strong>

                                            <?= htmlspecialchars(
                                                $application['full_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>


                                        <br>


                                        <small>

                                            <?= htmlspecialchars(
                                                $application['email'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </small>


                                    </td>



                                    <!-- OPPORTUNITY -->

                                    <td>


                                        <strong>

                                            <?= htmlspecialchars(
                                                $application['opportunity_title'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>


                                        <br>


                                        <small>

                                            <?= htmlspecialchars(
                                                ucfirst(
                                                    $application['opportunity_type']
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </small>


                                    </td>



                                    <!-- COMPANY -->

                                    <td>


                                        <?= htmlspecialchars(
                                            $application['company_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>


                                    </td>



                                    <!-- DEPARTMENT -->

                                    <td>


                                        <?= htmlspecialchars(
                                            $application['department']
                                                ?? 'Not specified',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>


                                    </td>



                                    <!-- APPLIED DATE -->

                                    <td>


                                        <?= htmlspecialchars(
                                            formatApplicationDate(
                                                $application['applied_at']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>


                                    </td>



                                    <!-- STATUS -->

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


                                            <?= getApplicationStatusIcon(
                                                $application['status']
                                            ) ?>


                                            <?= htmlspecialchars(
                                                ucfirst(
                                                    $application['status']
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>


                                        </span>


                                    </td>



                                    <!-- RESUME -->

                                    <td>


                                        <?php if (
                                            !empty(
                                                $application['resume_file_path']
                                            )
                                        ): ?>


                                            <a
                                                href="../<?= htmlspecialchars(
                                                    $application['resume_file_path'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                                target="_blank"
                                                class="table-action"
                                            >

                                                View Resume

                                            </a>


                                        <?php else: ?>


                                            <span>

                                                Not Available

                                            </span>


                                        <?php endif; ?>


                                    </td>



                                    <!-- ACTION -->

                                    <td>


                                        <a
                                            href="view_application.php?id=<?= (int) $application['application_id'] ?>"
                                            class="table-action"
                                        >

                                            View →

                                        </a>


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