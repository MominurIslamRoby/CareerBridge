<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';


/* =========================================
   AUTHORIZATION
========================================= */

requireRole('student');

$user = currentUser();


/* =========================================
   HELPER FUNCTION
========================================= */

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================
   GET FILTER VALUES
========================================= */

$keyword = trim($_GET['keyword'] ?? '');
$type = trim($_GET['type'] ?? '');
$location = trim($_GET['location'] ?? '');


/* =========================================
   VALIDATE TYPE
========================================= */

$allowedTypes = [
    'internship',
    'job'
];

if (!in_array($type, $allowedTypes, true)) {
    $type = '';
}


/* =========================================
   GET OPPORTUNITIES
========================================= */

$sql = '
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

        e.company_name

    FROM opportunities o

    INNER JOIN employers e
        ON e.employer_id = o.employer_id

    WHERE o.status = "open"
';


$params = [];


/* =========================================
   KEYWORD FILTER
========================================= */

if ($keyword !== '') {

    $sql .= '
        AND (
            o.title LIKE ?
            OR o.description LIKE ?
            OR e.company_name LIKE ?
        )
    ';

    $search = '%' . $keyword . '%';

    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}


/* =========================================
   OPPORTUNITY TYPE FILTER
========================================= */

if ($type !== '') {

    $sql .= '
        AND o.opportunity_type = ?
    ';

    $params[] = $type;
}


/* =========================================
   LOCATION FILTER
========================================= */

if ($location !== '') {

    $sql .= '
        AND o.location LIKE ?
    ';

    $params[] = '%' . $location . '%';
}


/* =========================================
   ORDER RESULTS
========================================= */

$sql .= '
    ORDER BY
        CASE
            WHEN o.deadline IS NULL THEN 1
            ELSE 0
        END,
        o.deadline ASC,
        o.created_at DESC
';


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$opportunities = $stmt->fetchAll();


/* =========================================
   OPPORTUNITY STATISTICS
========================================= */

$totalOpportunities = count($opportunities);

$internshipCount = 0;
$jobCount = 0;


foreach ($opportunities as $opportunity) {

    if (
        strtolower($opportunity['opportunity_type'])
        === 'internship'
    ) {
        $internshipCount++;
    }

    if (
        strtolower($opportunity['opportunity_type'])
        === 'job'
    ) {
        $jobCount++;
    }
}


/* =========================================
   FILTER STATUS
========================================= */

$filtersActive = (
    $keyword !== ''
    || $type !== ''
    || $location !== ''
);


/* =========================================
   RESULT TEXT
========================================= */

$opportunityText = $totalOpportunities === 1
    ? 'opportunity'
    : 'opportunities';

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


    <!-- FONT AWESOME -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <!-- MAIN STYLESHEET -->

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >


    <!-- =====================================
         OPPORTUNITIES PAGE SPECIFIC STYLES
    ====================================== -->

    <style>

        /* -------------------------------------
           PAGE WRAPPER
        ------------------------------------- */

        .opportunities-page {
            width: 100%;
        }


        /* -------------------------------------
           STATISTICS
        ------------------------------------- */

        .opportunities-page .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 30px;
        }


        .opportunities-page .stat-card {
            min-height: 130px;
            display: flex;
            align-items: center;
            gap: 18px;
        }


        .opportunities-page .stat-card h3 {
            margin: 8px 0 0;
            font-size: 25px;
        }


        .opportunities-page .stat-card p {
            margin: 0;
        }


        /* -------------------------------------
           SEARCH CARD
        ------------------------------------- */

        .opportunities-page .search-card {
            margin-bottom: 35px;
        }


        /* -------------------------------------
           FILTER FORM
        ------------------------------------- */

        .opportunities-page .filter-form {
            width: 100%;
            margin-top: 25px;
        }


        .opportunities-page .filter-grid {
            display: grid !important;
            grid-template-columns:
                repeat(3, minmax(0, 1fr)) !important;
            gap: 22px;
            width: 100%;
            align-items: end;
        }


        .opportunities-page .filter-grid .form-group {
            width: 100%;
            margin: 0 !important;
        }


        .opportunities-page .filter-grid label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
        }


        .opportunities-page .filter-grid input,
        .opportunities-page .filter-grid select {
            width: 100%;
            height: 48px;
            box-sizing: border-box;
        }


        /* -------------------------------------
           FILTER ACTIONS
        ------------------------------------- */

        .opportunities-page .filter-actions {
            display: flex !important;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }


        /* -------------------------------------
           SECTION HEADER
        ------------------------------------- */

        .opportunities-page .content-section-header {
            margin-bottom: 22px;
        }


        .opportunities-page .content-section-header h2 {
            margin: 6px 0;
        }


        .opportunities-page .section-description {
            margin: 0;
            color: #64748b;
        }


        /* -------------------------------------
           OPPORTUNITY GRID
        ------------------------------------- */

        .opportunities-page .opportunities-grid {
            display: grid !important;
            grid-template-columns:
                repeat(2, minmax(0, 1fr)) !important;
            gap: 22px;
            width: 100%;
            margin-bottom: 35px;
        }


        /* -------------------------------------
           OPPORTUNITY CARD
        ------------------------------------- */

        .opportunities-page .opportunity-card {
            display: flex;
            flex-direction: column;
            min-height: 100%;
            padding: 25px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow:
                0 4px 15px rgba(15, 23, 42, 0.05);
            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }


        .opportunities-page .opportunity-card:hover {
            transform: translateY(-3px);
            box-shadow:
                0 10px 25px rgba(15, 23, 42, 0.1);
        }


        .opportunities-page .opportunity-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }


        .opportunities-page .opportunity-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #eef2ff;
            color: #4f46e5;
            font-size: 20px;
        }


        .opportunities-page .opportunity-card h3 {
            margin: 0 0 12px;
            font-size: 21px;
            color: #1e293b;
        }


        /* -------------------------------------
           TYPE BADGE
        ------------------------------------- */

        .opportunities-page .type-badge {
            display: inline-flex;
            align-items: center;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }


        .opportunities-page .type-internship {
            background: #ecfdf5;
            color: #047857;
        }


        .opportunities-page .type-job {
            background: #fff7ed;
            color: #c2410c;
        }


        /* -------------------------------------
           COMPANY
        ------------------------------------- */

        .opportunities-page .opportunity-company {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 15px;
            color: #475569;
            font-weight: 600;
        }


        /* -------------------------------------
           DESCRIPTION
        ------------------------------------- */

        .opportunities-page .opportunity-description {
            margin: 0 0 20px;
            color: #64748b;
            line-height: 1.7;
        }


        /* -------------------------------------
           META INFORMATION
        ------------------------------------- */

        .opportunities-page .opportunity-meta {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 18px 0;
            margin-top: auto;
            border-top: 1px solid #e2e8f0;
        }


        .opportunities-page .opportunity-meta div {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #64748b;
            font-size: 14px;
        }


        .opportunities-page .opportunity-meta i {
            width: 18px;
            color: #6366f1;
        }


        /* -------------------------------------
           CARD FOOTER
        ------------------------------------- */

        .opportunities-page .opportunity-footer {
            margin-top: 18px;
        }


        .opportunities-page .opportunity-footer .btn {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }


        /* -------------------------------------
           EMPTY STATE
        ------------------------------------- */

        .opportunities-page .opportunity-empty-state {
            text-align: center;
            padding: 60px 25px;
        }


        .opportunities-page .opportunity-empty-state .empty-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #eef2ff;
            color: #4f46e5;
            font-size: 28px;
        }


        /* -------------------------------------
           RESPONSIVE
        ------------------------------------- */

        @media (max-width: 1100px) {

            .opportunities-page .stats-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }


            .opportunities-page .filter-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr)) !important;
            }

        }


        @media (max-width: 800px) {

            .opportunities-page .filter-grid {
                grid-template-columns:
                    1fr !important;
            }


            .opportunities-page .opportunities-grid {
                grid-template-columns:
                    1fr !important;
            }


            .opportunities-page .filter-actions {
                justify-content: stretch;
                flex-direction: column-reverse;
            }


            .opportunities-page .filter-actions .btn {
                width: 100%;
                justify-content: center;
            }

        }


        @media (max-width: 600px) {

            .opportunities-page .stats-grid {
                grid-template-columns: 1fr;
            }

        }

    </style>

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
                    Student Portal
                </span>

            </div>

        </div>


        <div class="sidebar-divider"></div>


        <p class="menu-label">
            MAIN MENU
        </p>


        <nav class="sidebar-nav">


            <a href="dashboard.php">

                <i class="fa-solid fa-table-columns"></i>

                <span>
                    Dashboard
                </span>

            </a>


            <a href="profile.php">

                <i class="fa-solid fa-user"></i>

                <span>
                    Career Profile
                </span>

            </a>


            <a href="skills.php">

                <i class="fa-solid fa-lightbulb"></i>

                <span>
                    My Skills
                </span>

            </a>


            <a href="resume.php">

                <i class="fa-solid fa-file-lines"></i>

                <span>
                    Resume / CV
                </span>

            </a>


            <a
                href="opportunities.php"
                class="active"
            >

                <i class="fa-solid fa-briefcase"></i>

                <span>
                    Opportunities
                </span>

            </a>


            <a href="applications.php">

                <i class="fa-solid fa-folder-open"></i>

                <span>
                    My Applications
                </span>

            </a>


            <a href="interviews.php">

                <i class="fa-solid fa-calendar-check"></i>

                <span>
                    My Interviews
                </span>

            </a>


            <a href="notifications.php">

                <i class="fa-solid fa-bell"></i>

                <span>
                    Notifications
                </span>

            </a>


        </nav>


        <div class="sidebar-bottom">

            <a
                href="../auth/logout.php"
                class="logout-link"
            >

                <i class="fa-solid fa-right-from-bracket"></i>

                <span>
                    Logout
                </span>

            </a>

        </div>


    </aside>



    <!-- =====================================
         MAIN CONTENT
    ====================================== -->

    <main class="main-content opportunities-page">


        <!-- PAGE HEADER -->

        <div class="page-header">


            <div>

                <p class="breadcrumb">
                    STUDENT PORTAL / OPPORTUNITIES
                </p>


                <h1>
                    Explore Opportunities
                </h1>


                <p class="page-subtitle">
                    Discover internships and job opportunities
                    that match your career goals.
                </p>

            </div>


            <div class="user-card">


                <div class="user-avatar">

                    <?= e(
                        strtoupper(
                            substr(
                                $user['full_name'] ?? 'S',
                                0,
                                1
                            )
                        )
                    ) ?>

                </div>


                <div>

                    <strong>

                        <?= e(
                            $user['full_name']
                            ?? 'Student'
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


            <!-- AVAILABLE -->

            <div class="stat-card">

                <div class="stat-icon">

                    <i class="fa-solid fa-briefcase"></i>

                </div>


                <div>

                    <p>
                        Available
                    </p>

                    <h3>
                        <?= $totalOpportunities ?>
                    </h3>

                </div>

            </div>



            <!-- INTERNSHIPS -->

            <div class="stat-card">

                <div class="stat-icon">

                    <i class="fa-solid fa-graduation-cap"></i>

                </div>


                <div>

                    <p>
                        Internships
                    </p>

                    <h3>
                        <?= $internshipCount ?>
                    </h3>

                </div>

            </div>



            <!-- JOBS -->

            <div class="stat-card">

                <div class="stat-icon">

                    <i class="fa-solid fa-building"></i>

                </div>


                <div>

                    <p>
                        Jobs
                    </p>

                    <h3>
                        <?= $jobCount ?>
                    </h3>

                </div>

            </div>



            <!-- FILTERS -->

            <div class="stat-card">

                <div class="stat-icon">

                    <i class="fa-solid fa-filter"></i>

                </div>


                <div>

                    <p>
                        Filters
                    </p>

                    <h3>
                        <?= $filtersActive ? 'Active' : 'All' ?>
                    </h3>

                </div>

            </div>


        </section>



        <!-- =====================================
             SEARCH & FILTER
        ====================================== -->

        <section class="content-card search-card">


            <div class="section-heading">


                <div>

                    <p class="section-label">
                        SEARCH
                    </p>


                    <h2>
                        Find Opportunities
                    </h2>


                    <p>
                        Search by keyword, opportunity type,
                        or location.
                    </p>

                </div>


                <div class="section-icon">

                    <i class="fa-solid fa-magnifying-glass"></i>

                </div>


            </div>



            <form
                method="GET"
                action="opportunities.php"
                class="filter-form"
            >


                <div class="filter-grid">


                    <!-- KEYWORD -->

                    <div class="form-group">

                        <label for="keyword">

                            <i class="fa-solid fa-magnifying-glass"></i>

                            Keyword

                        </label>


                        <input
                            type="text"
                            id="keyword"
                            name="keyword"
                            value="<?= e($keyword) ?>"
                            placeholder="Job title or company..."
                        >

                    </div>



                    <!-- TYPE -->

                    <div class="form-group">

                        <label for="type">

                            <i class="fa-solid fa-briefcase"></i>

                            Opportunity Type

                        </label>


                        <select
                            id="type"
                            name="type"
                        >

                            <option value="">
                                All Opportunities
                            </option>


                            <option
                                value="internship"
                                <?= $type === 'internship'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                Internship
                            </option>


                            <option
                                value="job"
                                <?= $type === 'job'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                Job
                            </option>

                        </select>

                    </div>



                    <!-- LOCATION -->

                    <div class="form-group">

                        <label for="location">

                            <i class="fa-solid fa-location-dot"></i>

                            Location

                        </label>


                        <input
                            type="text"
                            id="location"
                            name="location"
                            value="<?= e($location) ?>"
                            placeholder="Dhaka, Remote..."
                        >

                    </div>


                </div>



                <!-- ACTION BUTTONS -->

                <div class="form-actions filter-actions">


                    <a
                        href="opportunities.php"
                        class="btn btn-secondary"
                    >

                        <i class="fa-solid fa-rotate-left"></i>

                        Clear Filters

                    </a>


                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        <i class="fa-solid fa-magnifying-glass"></i>

                        Search Opportunities

                    </button>


                </div>


            </form>


        </section>



        <!-- =====================================
             OPPORTUNITIES HEADER
        ====================================== -->

        <div class="content-section-header">


            <div>

                <p class="section-label">
                    AVAILABLE POSITIONS
                </p>


                <h2>
                    Available Opportunities
                </h2>


                <p class="section-description">

                    <?= $totalOpportunities ?>

                    <?= $opportunityText ?>

                    found.

                </p>

            </div>


        </div>



        <!-- =====================================
             OPPORTUNITIES LIST
        ====================================== -->

        <?php if (!$opportunities): ?>


            <section class="content-card opportunity-empty-state">


                <div class="empty-icon">

                    <i class="fa-solid fa-briefcase"></i>

                </div>


                <h2>
                    No Opportunities Found
                </h2>


                <p>

                    We couldn't find any opportunities matching
                    your search criteria.

                </p>


                <br>


                <a
                    href="opportunities.php"
                    class="btn btn-primary"
                >

                    <i class="fa-solid fa-list"></i>

                    View All Opportunities

                </a>


            </section>



        <?php else: ?>


            <div class="opportunities-grid">


                <?php foreach ($opportunities as $opportunity): ?>


                    <?php

                    $opportunityType =
                        strtolower(
                            $opportunity['opportunity_type']
                        );

                    $isInternship =
                        $opportunityType === 'internship';

                    ?>


                    <article class="opportunity-card">


                        <!-- CARD HEADER -->

                        <div class="opportunity-card-header">


                            <div class="opportunity-icon">

                                <i
                                    class="<?= $isInternship
                                        ? 'fa-solid fa-graduation-cap'
                                        : 'fa-solid fa-briefcase'
                                    ?>"
                                ></i>

                            </div>


                            <span
                                class="type-badge <?= $isInternship
                                    ? 'type-internship'
                                    : 'type-job'
                                ?>"
                            >

                                <?= e(
                                    ucfirst(
                                        $opportunity['opportunity_type']
                                    )
                                ) ?>

                            </span>


                        </div>



                        <!-- TITLE -->

                        <h3>

                            <?= e(
                                $opportunity['title']
                            ) ?>

                        </h3>



                        <!-- COMPANY -->

                        <p class="opportunity-company">

                            <i class="fa-solid fa-building"></i>

                            <?= e(
                                $opportunity['company_name']
                            ) ?>

                        </p>



                        <!-- DESCRIPTION -->

                        <?php if (
                            !empty(
                                $opportunity['description']
                            )
                        ): ?>


                            <p class="opportunity-description">

                                <?php

                                $description =
                                    $opportunity['description'];

                                if (
                                    mb_strlen($description)
                                    > 150
                                ) {
                                    $description =
                                        mb_substr(
                                            $description,
                                            0,
                                            150
                                        ) . '...';
                                }

                                ?>

                                <?= e($description) ?>

                            </p>


                        <?php else: ?>


                            <p class="opportunity-description">

                                No description provided for this
                                opportunity.

                            </p>


                        <?php endif; ?>



                        <!-- META INFORMATION -->

                        <div class="opportunity-meta">


                            <!-- LOCATION -->

                            <div>

                                <i class="fa-solid fa-location-dot"></i>

                                <span>

                                    <?= e(
                                        $opportunity['location']
                                        ?? 'Not specified'
                                    ) ?>

                                </span>

                            </div>



                            <!-- DURATION -->

                            <div>

                                <i class="fa-solid fa-clock"></i>

                                <span>

                                    <?= e(
                                        $opportunity['duration']
                                        ?? 'Not specified'
                                    ) ?>

                                </span>

                            </div>



                            <!-- DEADLINE -->

                            <div>

                                <i class="fa-solid fa-calendar-days"></i>

                                <span>

                                    Deadline:

                                    <?php if (
                                        !empty(
                                            $opportunity['deadline']
                                        )
                                    ): ?>

                                        <?= e(
                                            date(
                                                'd M Y',
                                                strtotime(
                                                    $opportunity['deadline']
                                                )
                                            )
                                        ) ?>

                                    <?php else: ?>

                                        Not specified

                                    <?php endif; ?>

                                </span>

                            </div>


                        </div>



                        <!-- CARD FOOTER -->

                        <div class="opportunity-footer">


                            <a
                                href="opportunity_details.php?id=<?= (int) $opportunity['opportunity_id'] ?>"
                                class="btn btn-primary btn-small"
                            >

                                View Details

                                <i class="fa-solid fa-arrow-right"></i>

                            </a>


                        </div>


                    </article>


                <?php endforeach; ?>


            </div>


        <?php endif; ?>



        <!-- FOOTER -->

        <footer class="page-footer">

            <span>

                &copy; <?= date('Y') ?>

                CareerBridge — University Career Management Platform

            </span>

        </footer>


    </main>


</div>


</body>

</html>