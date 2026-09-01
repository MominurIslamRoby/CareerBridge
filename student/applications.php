<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';


/* =========================================
   AUTHORIZATION
========================================= */

requireRole('student');

$user = currentUser();

$userId = (int) $user['id'];


/* =========================================
   GET STUDENT INFORMATION
========================================= */

$studentStmt = $pdo->prepare(
    '
    SELECT
        student_id
    FROM students
    WHERE user_id = ?
    LIMIT 1
    '
);

$studentStmt->execute([$userId]);

$student = $studentStmt->fetch();


if (!$student) {

    http_response_code(404);

    exit('Student profile not found.');
}


$studentId = (int) $student['student_id'];


/* =========================================
   GET STUDENT APPLICATIONS
========================================= */

$applicationsStmt = $pdo->prepare(
    '
    SELECT

        a.application_id,
        a.opportunity_id,
        a.status,
        a.applied_at,

        o.title,
        o.opportunity_type,
        o.location,
        o.deadline,

        e.company_name

    FROM applications a

    INNER JOIN opportunities o
        ON o.opportunity_id = a.opportunity_id

    INNER JOIN employers e
        ON e.employer_id = o.employer_id

    WHERE a.student_id = ?

    ORDER BY
        a.applied_at DESC
    '
);

$applicationsStmt->execute([
    $studentId
]);

$applications = $applicationsStmt->fetchAll();


/* =========================================
   APPLICATION STATISTICS
========================================= */

$totalApplications = count($applications);

$submittedCount = 0;

$shortlistedCount = 0;

$interviewCount = 0;

$selectedCount = 0;

$rejectedCount = 0;


foreach ($applications as $application) {

    switch (
        strtolower(
            $application['status']
        )
    ) {

        case 'submitted':

            $submittedCount++;

            break;


        case 'shortlisted':

            $shortlistedCount++;

            break;


        case 'interview':

            $interviewCount++;

            break;


        case 'selected':

            $selectedCount++;

            break;


        case 'rejected':

            $rejectedCount++;

            break;
    }
}


/* =========================================
   HELPER: STATUS CLASS
========================================= */

function getApplicationStatusClass(
    string $status
): string {

    switch (strtolower($status)) {

        case 'submitted':

            return 'status-submitted';


        case 'shortlisted':

            return 'status-shortlisted';


        case 'interview':

            return 'status-interview';


        case 'selected':

            return 'status-selected';


        case 'rejected':

            return 'status-rejected';


        default:

            return 'status-submitted';
    }
}


/* =========================================
   HELPER: FORMAT STATUS
========================================= */

function formatApplicationStatus(
    string $status
): string {

    return ucwords(
        str_replace(
            '_',
            ' ',
            $status
        )
    );
}


/* =========================================
   HELPER: FORMAT OPPORTUNITY TYPE
========================================= */

function formatOpportunityType(
    string $type
): string {

    return ucwords(
        str_replace(
            '-',
            ' ',
            $type
        )
    );
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
        My Applications | CareerBridge
    </title>


    <!-- MAIN STYLESHEET -->

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >


    <!-- FONT AWESOME -->

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

                <img
                    src="../assets/images/CB Logo Transparent.png"
                    alt="CareerBridge Logo"
                >

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



            <a
                href="applications.php"
                class="active"
            >

                <span>
                    <i class="fa-solid fa-clipboard-list"></i>
                </span>

                My Applications

            </a>



            <a href="notifications.php">

                <span>
                    <i class="fa-solid fa-bell"></i>
                </span>

                Notifications

            </a>


        </nav>



        <!-- SIDEBAR FOOTER -->

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

                    STUDENT PORTAL / MY APPLICATIONS

                </p>


                <h1>
                    My Applications
                </h1>


                <p class="page-subtitle">

                    Track the progress and current status of all your job and internship applications.

                </p>


            </div>



            <!-- USER CARD -->

            <div class="user-card">


                <div class="user-avatar">

                    <?= htmlspecialchars(
                        strtoupper(
                            substr(
                                $user['full_name'],
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
             APPLICATION STATISTICS
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
                        <?= $submittedCount ?>
                    </h2>

                </div>


            </div>



            <!-- SHORTLISTED -->

            <div class="stat-card">


                <div class="stat-icon">

                    <i class="fa-solid fa-star"></i>

                </div>


                <div>

                    <p>
                        Shortlisted
                    </p>


                    <h2>
                        <?= $shortlistedCount ?>
                    </h2>

                </div>


            </div>



            <!-- INTERVIEWS -->

            <div class="stat-card">


                <div class="stat-icon">

                    <i class="fa-solid fa-calendar-check"></i>

                </div>


                <div>

                    <p>
                        Interviews
                    </p>


                    <h2>
                        <?= $interviewCount ?>
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
                        <?= $selectedCount ?>
                    </h2>

                </div>


            </div>


        </section>



        <!-- =====================================
             APPLICATION HISTORY
        ====================================== -->

        <section class="content-card">


            <!-- SECTION HEADER -->

            <div class="section-heading">


                <div>


                    <p class="section-label">

                        APPLICATION HISTORY

                    </p>


                    <h2>
                        Your Applications
                    </h2>


                    <p>

                        Review every opportunity you have applied for and monitor its current application status.

                    </p>


                </div>



                <div class="section-icon">

                    <i class="fa-solid fa-clipboard-list"></i>

                </div>


            </div>



            <!-- =====================================
                 EMPTY STATE
            ====================================== -->

            <?php if (!$applications): ?>


                <div class="empty-state">


                    <div class="empty-icon">

                        <i class="fa-solid fa-folder-open"></i>

                    </div>


                    <h3>
                        No Applications Yet
                    </h3>


                    <p>

                        You have not submitted any applications yet.
                        Browse available opportunities and start building your career journey.

                    </p>



                    <a
                        href="opportunities.php"
                        class="btn btn-primary"
                    >

                        Browse Opportunities

                        <i class="fa-solid fa-arrow-right"></i>

                    </a>


                </div>



            <!-- =====================================
                 APPLICATION LIST
            ====================================== -->

            <?php else: ?>


                <div class="applications-list">


                    <?php foreach (
                        $applications as $application
                    ): ?>


                        <?php

                        $status = strtolower(
                            $application['status']
                        );

                        $statusClass =
                            getApplicationStatusClass(
                                $status
                            );

                        ?>


                        <article class="application-card">


                            <!-- CARD HEADER -->

                            <div class="application-card-header">


                                <div>


                                    <h3>

                                        <?= htmlspecialchars(
                                            $application['title'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </h3>



                                    <p class="company-name">


                                        <i class="fa-solid fa-building"></i>


                                        <?= htmlspecialchars(
                                            $application['company_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>


                                    </p>


                                </div>



                                <!-- STATUS -->

                                <span
                                    class="status-badge <?= htmlspecialchars(
                                        $statusClass,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        formatApplicationStatus(
                                            $status
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </span>


                            </div>



                            <!-- APPLICATION DETAILS -->

                            <div class="application-meta">


                                <!-- TYPE -->

                                <div>


                                    <span class="meta-label">

                                        TYPE

                                    </span>


                                    <strong>

                                        <?= htmlspecialchars(
                                            formatOpportunityType(
                                                $application['opportunity_type']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </strong>


                                </div>



                                <!-- LOCATION -->

                                <div>


                                    <span class="meta-label">

                                        LOCATION

                                    </span>


                                    <strong>

                                        <?= htmlspecialchars(
                                            !empty(
                                                $application['location']
                                            )
                                                ? $application['location']
                                                : 'Not specified',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </strong>


                                </div>



                                <!-- APPLIED DATE -->

                                <div>


                                    <span class="meta-label">

                                        APPLIED ON

                                    </span>


                                    <strong>

                                        <?= htmlspecialchars(
                                            formatApplicationDate(
                                                $application['applied_at']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </strong>


                                </div>



                                <!-- DEADLINE -->

                                <div>


                                    <span class="meta-label">

                                        DEADLINE

                                    </span>


                                    <strong>

                                        <?= htmlspecialchars(
                                            formatApplicationDate(
                                                $application['deadline']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </strong>


                                </div>


                            </div>



                            <!-- CARD ACTION -->

                            <div class="application-actions">


                                <a
                                    href="opportunity_details.php?id=<?= (int) $application['opportunity_id'] ?>"
                                    class="btn btn-secondary"
                                >

                                    View Opportunity

                                    <i class="fa-solid fa-arrow-right"></i>

                                </a>


                            </div>


                        </article>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


        </section>



        <!-- =====================================
             FOOTER
        ====================================== -->

        <footer class="page-footer">

            <p>

                &copy; <?= date('Y') ?>

                CareerBridge

                <span>
                    |
                </span>

                The Ultimate Career Management Platform

            </p>

        </footer>


    </main>


</div>


</body>

</html>