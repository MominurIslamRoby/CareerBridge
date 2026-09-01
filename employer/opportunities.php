<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('employer');

$user = currentUser();

$userId = (int) $user['id'];


/* =========================================
   GET EMPLOYER INFORMATION
========================================= */

$employerStmt = $pdo->prepare(
    '
    SELECT
        employer_id,
        company_name
    FROM employers
    WHERE user_id = ?
    LIMIT 1
    '
);

$employerStmt->execute([$userId]);

$employer = $employerStmt->fetch();

if (!$employer) {

    http_response_code(404);

    exit('Employer profile not found.');
}

$employerId = (int) $employer['employer_id'];

$companyName = $employer['company_name'] ?? '';

$displayName = !empty($companyName)
    ? $companyName
    : ($user['full_name'] ?? 'Employer');


/* =========================================
   GET OPPORTUNITY STATISTICS
========================================= */

$statsStmt = $pdo->prepare(
    '
    SELECT

        COUNT(*) AS total_opportunities,

        SUM(
            CASE
                WHEN status = "open"
                THEN 1
                ELSE 0
            END
        ) AS open_count,

        SUM(
            CASE
                WHEN status = "closed"
                THEN 1
                ELSE 0
            END
        ) AS closed_count

    FROM opportunities

    WHERE employer_id = ?
    '
);

$statsStmt->execute([$employerId]);

$stats = $statsStmt->fetch();


$totalOpportunities =
    (int) ($stats['total_opportunities'] ?? 0);

$openCount =
    (int) ($stats['open_count'] ?? 0);

$closedCount =
    (int) ($stats['closed_count'] ?? 0);


/* =========================================
   GET EMPLOYER OPPORTUNITIES
========================================= */

$opportunitiesStmt = $pdo->prepare(
    '
    SELECT

        o.opportunity_id,
        o.title,
        o.opportunity_type,
        o.description,
        o.location,
        o.duration,
        o.deadline,
        o.status,
        o.created_at,

        COUNT(a.application_id) AS application_count

    FROM opportunities o

    LEFT JOIN applications a
        ON a.opportunity_id = o.opportunity_id

    WHERE o.employer_id = ?

    GROUP BY
        o.opportunity_id,
        o.title,
        o.opportunity_type,
        o.description,
        o.location,
        o.duration,
        o.deadline,
        o.status,
        o.created_at

    ORDER BY
        o.created_at DESC
    '
);

$opportunitiesStmt->execute([$employerId]);

$opportunities = $opportunitiesStmt->fetchAll();


/* =========================================
   HELPER: OPPORTUNITY STATUS CLASS
========================================= */

function getOpportunityStatusClass(string $status): string
{
    switch ($status) {

        case 'open':
            return 'status-selected';

        case 'closed':
            return 'status-rejected';

        default:
            return 'status-submitted';
    }
}


/* =========================================
   HELPER: OPPORTUNITY STATUS ICON
========================================= */

function getOpportunityStatusIcon(string $status): string
{
    switch ($status) {

        case 'open':
            return 'fa-solid fa-check';

        case 'closed':
            return 'fa-solid fa-lock';

        default:
            return 'fa-solid fa-circle';
    }
}


/* =========================================
   HELPER: FORMAT DEADLINE
========================================= */

function formatDeadline(?string $deadline): string
{
    if (empty($deadline)) {

        return 'Not specified';
    }

    $timestamp = strtotime($deadline);

    if ($timestamp === false) {

        return 'Not specified';
    }

    return date('d M Y', $timestamp);
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
        Opportunities | CareerBridge
    </title>


    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
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

    <div class="sidebar-brand">

        <div class="brand-logo">

            <img
                src="../assets/images/CB Logo Transparent.png"
                alt="CareerBridge Logo"
            >

        </div>


        <div class="brand-content">

            <h2>
                CareerBridge
            </h2>

            <span>
                Employer Portal
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


            <!-- Dashboard -->

            <a href="dashboard.php">

                <span>
                    <i class="fa-solid fa-house"></i>
                </span>

                Dashboard

            </a>


            <!-- Company Profile -->

            <a href="profile.php">

                <span>
                    <i class="fa-solid fa-building"></i>
                </span>

                Company Profile

            </a>


            <!-- My Opportunities -->

            <a
                href="opportunities.php"
                class="active"
            >

                <span>
                    <i class="fa-solid fa-briefcase"></i>
                </span>

                My Opportunities

            </a>


            <!-- Post Opportunity -->

            <a href="create_opportunity.php">

                <span>
                    <i class="fa-solid fa-plus"></i>
                </span>

                Post Opportunity

            </a>


            <!-- Applications -->

            <a href="applications.php">

                <span>
                    <i class="fa-solid fa-file-lines"></i>
                </span>

                Applications

            </a>


            <!-- Interviews -->

            <a href="interviews.php">

                <span>
                    <i class="fa-solid fa-calendar-check"></i>
                </span>

                Interviews

            </a>


            <!-- Notifications -->

            <a href="notifications.php">

                <span>
                    <i class="fa-solid fa-bell"></i>
                </span>

                Notifications

            </a>


        </nav>


        <!-- SIDEBAR BOTTOM -->

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

                    EMPLOYER PORTAL / OPPORTUNITIES

                </p>


                <h1>
                    Opportunities
                </h1>


                <p class="page-subtitle">

                    Create and manage career opportunities for students.

                </p>


            </div>



            <!-- USER CARD -->

            <div class="user-card">


                <div class="user-avatar">

                    <?= htmlspecialchars(
                        strtoupper(
                            substr(
                                $displayName,
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
                            $displayName,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </strong>


                    <span>
                        Employer
                    </span>


                </div>


            </div>


        </div>



        <!-- =====================================
             STATISTICS
        ====================================== -->

        <section class="stats-grid">


            <!-- TOTAL OPPORTUNITIES -->

            <div class="stat-card">


                <div class="stat-icon">

                    <i class="fa-solid fa-briefcase"></i>

                </div>


                <div>


                    <p>
                        Total Opportunities
                    </p>


                    <h2>
                        <?= $totalOpportunities ?>
                    </h2>


                </div>


            </div>



            <!-- OPEN OPPORTUNITIES -->

            <div class="stat-card">


                <div class="stat-icon">

                    <i class="fa-solid fa-check"></i>

                </div>


                <div>


                    <p>
                        Open Opportunities
                    </p>


                    <h2>
                        <?= $openCount ?>
                    </h2>


                </div>


            </div>



            <!-- CLOSED OPPORTUNITIES -->

            <div class="stat-card">


                <div class="stat-icon">

                    <i class="fa-solid fa-lock"></i>

                </div>


                <div>


                    <p>
                        Closed Opportunities
                    </p>


                    <h2>
                        <?= $closedCount ?>
                    </h2>


                </div>


            </div>


        </section>



        <!-- =====================================
             OPPORTUNITIES SECTION
        ====================================== -->

        <section class="content-card">


            <!-- SECTION HEADER -->

            <div class="section-heading">


                <div>


                    <p class="section-label">
                        MANAGE OPPORTUNITIES
                    </p>


                    <h2>
                        Your Opportunities
                    </h2>


                    <p>

                        Manage job, internship, and career opportunities
                        posted by your company.

                    </p>


                </div>



                <div>


                    <a
                        href="create_opportunity.php"
                        class="btn btn-primary"
                    >

                        <i class="fa-solid fa-plus"></i>

                        Create Opportunity

                    </a>


                </div>


            </div>



            <!-- =====================================
                 EMPTY STATE
            ====================================== -->

            <?php if (!$opportunities): ?>


                <div class="empty-state">


                    <div class="empty-icon">

                        <i class="fa-solid fa-briefcase"></i>

                    </div>


                    <h3>
                        No Opportunities Yet
                    </h3>


                    <p>

                        You have not created any opportunities yet.
                        Create your first opportunity and start connecting
                        with talented students.

                    </p>


                    <a
                        href="create_opportunity.php"
                        class="btn btn-primary"
                    >

                        <i class="fa-solid fa-plus"></i>

                        Create Your First Opportunity

                    </a>


                </div>



            <!-- =====================================
                 OPPORTUNITIES LIST
            ====================================== -->

            <?php else: ?>


                <div class="applications-list">


                    <?php foreach ($opportunities as $opportunity): ?>


                        <article class="application-item">


                            <!-- OPPORTUNITY ICON -->

                            <div class="application-avatar">

                                <i class="fa-solid fa-briefcase"></i>

                            </div>



                            <!-- OPPORTUNITY CONTENT -->

                            <div class="application-content">


                                <!-- HEADER -->

                                <div class="application-top">


                                    <div>


                                        <h3>

                                            <?= htmlspecialchars(
                                                $opportunity['title'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </h3>


                                        <p class="application-opportunity">


                                            <?= htmlspecialchars(
                                                ucfirst(
                                                    $opportunity['opportunity_type']
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                            Opportunity


                                        </p>


                                    </div>



                                    <!-- STATUS -->

                                    <span
                                        class="status-badge <?= htmlspecialchars(
                                            getOpportunityStatusClass(
                                                $opportunity['status']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >


                                        <i
                                            class="<?= htmlspecialchars(
                                                getOpportunityStatusIcon(
                                                    $opportunity['status']
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                        ></i>


                                        <?= htmlspecialchars(
                                            ucfirst(
                                                $opportunity['status']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>


                                    </span>


                                </div>



                                <!-- DESCRIPTION -->

                                <?php if (
                                    !empty(
                                        $opportunity['description']
                                    )
                                ): ?>


                                    <p>

                                        <?= htmlspecialchars(
                                            $opportunity['description'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </p>


                                <?php endif; ?>



                                <!-- OPPORTUNITY META -->

                                <div class="application-meta opportunity-meta">


                                    <!-- LOCATION -->

                                    <div>


                                        <span class="meta-label">
                                            LOCATION
                                        </span>


                                        <strong>

                                            <i class="fa-solid fa-location-dot"></i>

                                            <?= htmlspecialchars(
                                                $opportunity['location']
                                                    ?? 'Not specified',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>


                                    </div>



                                    <!-- DURATION -->

                                    <div>


                                        <span class="meta-label">
                                            DURATION
                                        </span>


                                        <strong>

                                            <i class="fa-solid fa-clock"></i>

                                            <?= htmlspecialchars(
                                                $opportunity['duration']
                                                    ?? 'Not specified',
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

                                            <i class="fa-solid fa-calendar"></i>

                                            <?= htmlspecialchars(
                                                formatDeadline(
                                                    $opportunity['deadline']
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>


                                    </div>



                                    <!-- APPLICATIONS -->

                                    <div>


                                        <span class="meta-label">
                                            APPLICATIONS
                                        </span>


                                        <strong>

                                            <i class="fa-solid fa-users"></i>

                                            <?= (int) $opportunity['application_count'] ?>

                                        </strong>


                                    </div>


                                </div>



                                <!-- ACTIONS -->

                                <div class="application-actions">


                                    <!-- MANAGE -->

                                    <a
                                        href="edit_opportunity.php?id=<?= (int) $opportunity['opportunity_id'] ?>"
                                        class="btn btn-primary"
                                    >

                                        <i class="fa-solid fa-pen-to-square"></i>

                                        Manage Opportunity

                                    </a>



                                    <!-- APPLICATIONS -->

                                    <a
                                        href="applications.php"
                                        class="btn btn-secondary"
                                    >

                                        <i class="fa-solid fa-file-lines"></i>

                                        View Applications

                                    </a>


                                </div>


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


            &copy; <?= date('Y') ?>

            CareerBridge — University Career Management Platform


        </footer>


    </main>


</div>


</body>

</html>