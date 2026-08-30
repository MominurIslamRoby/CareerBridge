<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('administrator');

$user = currentUser();


/* =========================================
   VALIDATE APPLICATION ID
========================================= */

$applicationId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;


if ($applicationId <= 0) {

    header(
        'Location: applications.php'
    );

    exit;
}


/* =========================================
   GET APPLICATION DETAILS
========================================= */

$applicationStmt = $pdo->prepare(
    '
    SELECT

        /* APPLICATION */

        a.application_id,
        a.status,
        a.applied_at,


        /* STUDENT */

        s.student_id,
        s.student_id_number,
        s.university_name,
        s.department,
        s.academic_level,


        /* USER */

        u.full_name,
        u.email,


        /* OPPORTUNITY */

        o.opportunity_id,
        o.title AS opportunity_title,
        o.description AS opportunity_description,
        o.opportunity_type,
        o.location,
        o.deadline,
        o.status AS opportunity_status,
        o.created_at AS opportunity_created_at,


        /* EMPLOYER */

        e.employer_id,
        e.company_name,
        e.company_description,
        e.industry,


        /* RESUME */

        r.resume_id,
        r.file_name AS resume_file_name,
        r.file_path AS resume_file_path,
        r.uploaded_at AS resume_uploaded_at


    FROM applications a


    /* STUDENT */

    INNER JOIN students s
        ON s.student_id = a.student_id


    /* USER */

    INNER JOIN users u
        ON u.user_id = s.user_id


    /* OPPORTUNITY */

    INNER JOIN opportunities o
        ON o.opportunity_id = a.opportunity_id


    /* EMPLOYER */

    INNER JOIN employers e
        ON e.employer_id = o.employer_id


    /* RESUME */

    LEFT JOIN resumes r
        ON r.resume_id = a.resume_id


    WHERE a.application_id = ?


    LIMIT 1
    '
);


$applicationStmt->execute([
    $applicationId
]);


$application =
    $applicationStmt->fetch();


/* =========================================
   APPLICATION NOT FOUND
========================================= */

if (!$application) {

    http_response_code(404);

    exit(
        'Application not found.'
    );
}


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
   HELPER: APPLICATION STATUS ICON
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


/* =========================================
   HELPER: FORMAT DATETIME
========================================= */

function formatApplicationDateTime(
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
        'd M Y, h:i A',
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

        Application Details | CareerBridge

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

                    ADMINISTRATOR PORTAL / APPLICATIONS / DETAILS

                </p>


                <h1>

                    Application Details

                </h1>


                <p class="page-subtitle">

                    View complete information about this
                    student application.

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
             BACK BUTTON
        ====================================== -->

        <div class="form-actions">


            <a
                href="applications.php"
                class="btn btn-secondary"
            >

                ← Back to Applications

            </a>


        </div>



        <!-- =====================================
             APPLICATION OVERVIEW
        ====================================== -->

        <section class="content-card">


            <div class="section-heading">


                <div>


                    <p class="section-label">

                        APPLICATION OVERVIEW

                    </p>


                    <h2>

                        <?= htmlspecialchars(
                            $application['full_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </h2>


                    <p>

                        Application for

                        <strong>

                            <?= htmlspecialchars(
                                $application['opportunity_title'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </strong>

                    </p>


                </div>


                <div class="section-icon">

                    📋

                </div>


            </div>



            <div class="application-meta">


                <!-- APPLICATION ID -->

                <div>


                    <span class="meta-label">

                        APPLICATION ID

                    </span>


                    <strong>

                        #<?= (int) $application['application_id'] ?>

                    </strong>


                </div>



                <!-- APPLIED DATE -->

                <div>


                    <span class="meta-label">

                        APPLIED ON

                    </span>


                    <strong>

                        <?= htmlspecialchars(
                            formatApplicationDateTime(
                                $application['applied_at']
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </strong>


                </div>



                <!-- STATUS -->

                <div>


                    <span class="meta-label">

                        APPLICATION STATUS

                    </span>


                    <strong>


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


                    </strong>


                </div>


            </div>


        </section>



        <!-- =====================================
             STUDENT INFORMATION
        ====================================== -->

        <section class="content-card">


            <div class="section-heading">


                <div>


                    <p class="section-label">

                        APPLICANT

                    </p>


                    <h2>

                        Student Information

                    </h2>


                    <p>

                        Academic and contact details of
                        the student who submitted this application.

                    </p>


                </div>


                <div class="section-icon">

                    🎓

                </div>


            </div>



            <div class="application-meta">


                <!-- FULL NAME -->

                <div>


                    <span class="meta-label">

                        FULL NAME

                    </span>


                    <strong>


                        <?= htmlspecialchars(
                            $application['full_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>


                    </strong>


                </div>



                <!-- EMAIL -->

                <div>


                    <span class="meta-label">

                        EMAIL ADDRESS

                    </span>


                    <strong>


                        <?= htmlspecialchars(
                            $application['email'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>


                    </strong>


                </div>



                <!-- STUDENT ID -->

                <div>


                    <span class="meta-label">

                        STUDENT ID

                    </span>


                    <strong>


                        <?= htmlspecialchars(
                            $application['student_id_number']
                                ?? 'Not specified',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>


                    </strong>


                </div>



                <!-- UNIVERSITY -->

                <div>


                    <span class="meta-label">

                        UNIVERSITY

                    </span>


                    <strong>


                        <?= htmlspecialchars(
                            $application['university_name']
                                ?? 'Not specified',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>


                    </strong>


                </div>



                <!-- DEPARTMENT -->

                <div>


                    <span class="meta-label">

                        DEPARTMENT

                    </span>


                    <strong>


                        <?= htmlspecialchars(
                            $application['department']
                                ?? 'Not specified',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>


                    </strong>


                </div>



                <!-- ACADEMIC LEVEL -->

                <div>


                    <span class="meta-label">

                        ACADEMIC LEVEL

                    </span>


                    <strong>


                        <?= htmlspecialchars(
                            $application['academic_level']
                                ?? 'Not specified',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>


                    </strong>


                </div>


            </div>


        </section>



        <!-- =====================================
             OPPORTUNITY INFORMATION
        ====================================== -->

        <section class="content-card">


            <div class="section-heading">


                <div>


                    <p class="section-label">

                        OPPORTUNITY

                    </p>


                    <h2>

                        Opportunity Information

                    </h2>


                    <p>

                        Details of the opportunity
                        the student applied for.

                    </p>


                </div>


                <div class="section-icon">

                    💼

                </div>


            </div>



            <div class="application-meta">


                <!-- TITLE -->

                <div>


                    <span class="meta-label">

                        OPPORTUNITY TITLE

                    </span>


                    <strong>


                        <?= htmlspecialchars(
                            $application['opportunity_title'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>


                    </strong>


                </div>



                <!-- TYPE -->

                <div>


                    <span class="meta-label">

                        TYPE

                    </span>


                    <strong>


                        <?= htmlspecialchars(
                            ucfirst(
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
                            $application['location']
                                ?? 'Not specified',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>


                    </strong>


                </div>



                <!-- DEADLINE -->

                <div>


                    <span class="meta-label">

                        APPLICATION DEADLINE

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



                <!-- STATUS -->

                <div>


                    <span class="meta-label">

                        OPPORTUNITY STATUS

                    </span>


                    <strong>


                        <span
                            class="status-badge <?= htmlspecialchars(
                                getOpportunityStatusClass(
                                    $application['opportunity_status']
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >


                            <?= htmlspecialchars(
                                ucfirst(
                                    $application['opportunity_status']
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>


                        </span>


                    </strong>


                </div>


            </div>



            <!-- OPPORTUNITY DESCRIPTION -->

            <div class="form-group form-full">


                <label>

                    Opportunity Description

                </label>


                <p>


                    <?= nl2br(
                        htmlspecialchars(
                            $application['opportunity_description']
                                ?? 'No description provided.',
                            ENT_QUOTES,
                            'UTF-8'
                        )
                    ) ?>


                </p>


            </div>


        </section>



        <!-- =====================================
             EMPLOYER INFORMATION
        ====================================== -->

        <section class="content-card">


            <div class="section-heading">


                <div>


                    <p class="section-label">

                        EMPLOYER

                    </p>


                    <h2>

                        Company Information

                    </h2>


                    <p>

                        Information about the company
                        offering this opportunity.

                    </p>


                </div>


                <div class="section-icon">

                    🏢

                </div>


            </div>



            <div class="application-meta">


                <!-- COMPANY -->

                <div>


                    <span class="meta-label">

                        COMPANY NAME

                    </span>


                    <strong>


                        <?= htmlspecialchars(
                            $application['company_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>


                    </strong>


                </div>



                <!-- INDUSTRY -->

                <div>


                    <span class="meta-label">

                        INDUSTRY

                    </span>


                    <strong>


                        <?= htmlspecialchars(
                            $application['industry']
                                ?? 'Not specified',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>


                    </strong>


                </div>


            </div>



            <!-- COMPANY DESCRIPTION -->

            <div class="form-group form-full">


                <label>

                    Company Description

                </label>


                <p>


                    <?= nl2br(
                        htmlspecialchars(
                            $application['company_description']
                                ?? 'No company description provided.',
                            ENT_QUOTES,
                            'UTF-8'
                        )
                    ) ?>


                </p>


            </div>


        </section>



        <!-- =====================================
             RESUME
        ====================================== -->

        <section class="content-card">


            <div class="section-heading">


                <div>


                    <p class="section-label">

                        DOCUMENT

                    </p>


                    <h2>

                        Student Resume

                    </h2>


                    <p>

                        Resume submitted with this application.

                    </p>


                </div>


                <div class="section-icon">

                    📄

                </div>


            </div>



            <?php if (
                !empty(
                    $application['resume_file_path']
                )
            ): ?>


                <div class="application-meta">


                    <!-- FILE NAME -->

                    <div>


                        <span class="meta-label">

                            FILE NAME

                        </span>


                        <strong>


                            <?= htmlspecialchars(
                                $application['resume_file_name']
                                    ?? 'Resume',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>


                        </strong>


                    </div>



                    <!-- UPLOAD DATE -->

                    <div>


                        <span class="meta-label">

                            UPLOADED

                        </span>


                        <strong>


                            <?= htmlspecialchars(
                                formatApplicationDate(
                                    $application['resume_uploaded_at']
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>


                        </strong>


                    </div>


                </div>



                <div class="form-actions">


                    <a
                        href="../<?= htmlspecialchars(
                            $application['resume_file_path'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        target="_blank"
                        class="btn btn-primary"
                    >

                        📄 View Resume

                    </a>


                </div>


            <?php else: ?>


                <div class="empty-state">


                    <div class="empty-icon">

                        📭

                    </div>


                    <h3>

                        Resume Not Available

                    </h3>


                    <p>

                        No resume was attached to this application.

                    </p>


                </div>


            <?php endif; ?>


        </section>



        <!-- =====================================
             ADMINISTRATOR NOTICE
        ====================================== -->

        <section class="content-card">


            <div class="section-heading">


                <div>


                    <p class="section-label">

                        ADMINISTRATOR ACCESS

                    </p>


                    <h2>

                        Application Monitoring

                    </h2>


                    <p>

                        Administrators can monitor applications across
                        the platform. Recruitment decisions and application
                        status updates are managed by the respective employer.

                    </p>


                </div>


                <div class="section-icon">

                    🛡️

                </div>


            </div>


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