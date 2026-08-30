<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('administrator');

$user = currentUser();


/* =========================================
   INITIALIZE VARIABLES
========================================= */

$error = '';

$success = '';


/* =========================================
   HANDLE OPPORTUNITY STATUS UPDATE
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $opportunityId = filter_input(
        INPUT_POST,
        'opportunity_id',
        FILTER_VALIDATE_INT
    );

    $newStatus = strtolower(
        trim(
            $_POST['status'] ?? ''
        )
    );


    $allowedStatuses = [
        'open',
        'closed'
    ];


    if (!$opportunityId) {

        $error =
            'Invalid opportunity ID.';

    } elseif (
        !in_array(
            $newStatus,
            $allowedStatuses,
            true
        )
    ) {

        $error =
            'Invalid opportunity status.';

    } else {

        try {

            $updateStmt = $pdo->prepare(
                '
                UPDATE opportunities
                SET
                    status = ?,
                    updated_at = NOW()
                WHERE opportunity_id = ?
                '
            );

            $updateStmt->execute([
                $newStatus,
                $opportunityId
            ]);


            if ($updateStmt->rowCount() > 0) {

                $success =
                    'Opportunity status updated successfully.';

            } else {

                $error =
                    'Opportunity not found or status was already updated.';
            }

        } catch (PDOException $e) {

            $error =
                'Unable to update opportunity status. Please try again.';
        }
    }
}


/* =========================================
   GET FILTER VALUES
========================================= */

$search = trim(
    $_GET['search'] ?? ''
);

$statusFilter = strtolower(
    trim(
        $_GET['status'] ?? ''
    )
);

$typeFilter = strtolower(
    trim(
        $_GET['type'] ?? ''
    )
);


/* =========================================
   VALIDATE FILTERS
========================================= */

$allowedStatusFilters = [
    '',
    'open',
    'closed'
];

$allowedTypes = [
    '',
    'job',
    'internship',
    'training',
    'other'
];


if (
    !in_array(
        $statusFilter,
        $allowedStatusFilters,
        true
    )
) {

    $statusFilter = '';
}


if (
    !in_array(
        $typeFilter,
        $allowedTypes,
        true
    )
) {

    $typeFilter = '';
}


/* =========================================
   GET OPPORTUNITY STATISTICS
========================================= */

$statsStmt = $pdo->query(
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
        ) AS closed_count,

        SUM(
            CASE
                WHEN opportunity_type = "job"
                THEN 1
                ELSE 0
            END
        ) AS job_count,

        SUM(
            CASE
                WHEN opportunity_type = "internship"
                THEN 1
                ELSE 0
            END
        ) AS internship_count

    FROM opportunities
    '
);

$stats = $statsStmt->fetch();


$totalOpportunities =
    (int) (
        $stats['total_opportunities']
        ?? 0
    );

$openCount =
    (int) (
        $stats['open_count']
        ?? 0
    );

$closedCount =
    (int) (
        $stats['closed_count']
        ?? 0
    );

$jobCount =
    (int) (
        $stats['job_count']
        ?? 0
    );

$internshipCount =
    (int) (
        $stats['internship_count']
        ?? 0
    );


/* =========================================
   BUILD OPPORTUNITY QUERY
========================================= */

$sql =
    '
    SELECT

        o.opportunity_id,
        o.title,
        o.opportunity_type,
        o.description,
        o.responsibilities,
        o.qualifications,
        o.location,
        o.duration,
        o.deadline,
        o.status,
        o.created_at,
        o.updated_at,

        e.employer_id,
        e.company_name,
        e.industry,

        COUNT(
            a.application_id
        ) AS application_count

    FROM opportunities o

    INNER JOIN employers e
        ON e.employer_id = o.employer_id

    LEFT JOIN applications a
        ON a.opportunity_id = o.opportunity_id

    WHERE 1 = 1
    ';


$params = [];


/* =========================================
   SEARCH FILTER
========================================= */

if ($search !== '') {

    $sql .=
        '
        AND
        (
            o.title LIKE ?
            OR e.company_name LIKE ?
            OR o.location LIKE ?
        )
        ';

    $searchTerm =
        '%' . $search . '%';

    $params[] =
        $searchTerm;

    $params[] =
        $searchTerm;

    $params[] =
        $searchTerm;
}


/* =========================================
   STATUS FILTER
========================================= */

if ($statusFilter !== '') {

    $sql .=
        '
        AND o.status = ?
        ';

    $params[] =
        $statusFilter;
}


/* =========================================
   TYPE FILTER
========================================= */

if ($typeFilter !== '') {

    $sql .=
        '
        AND o.opportunity_type = ?
        ';

    $params[] =
        $typeFilter;
}


/* =========================================
   GROUP AND ORDER
========================================= */

$sql .=
    '
    GROUP BY
        o.opportunity_id

    ORDER BY
        o.created_at DESC
    ';


/* =========================================
   EXECUTE QUERY
========================================= */

$opportunitiesStmt =
    $pdo->prepare($sql);

$opportunitiesStmt->execute(
    $params
);

$opportunities =
    $opportunitiesStmt->fetchAll();


/* =========================================
   HELPER: STATUS CLASS
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
   HELPER: TYPE ICON
========================================= */

function getOpportunityTypeIcon(
    string $type
): string {

    switch ($type) {

        case 'internship':
            return '🎓';

        case 'training':
            return '📚';

        case 'job':
            return '💼';

        default:
            return '📌';
    }
}


/* =========================================
   HELPER: FORMAT DATE
========================================= */

function formatOpportunityDate(
    ?string $date
): string {

    if (empty($date)) {

        return 'Not specified';
    }

    $timestamp =
        strtotime($date);

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
        Opportunities | CareerBridge
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
            ADMINISTRATION
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


            <a
                href="opportunities.php"
                class="active"
            >

                <span>💼</span>

                Opportunities

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

                    ADMINISTRATOR / OPPORTUNITIES

                </p>


                <h1>
                    Manage Opportunities
                </h1>


                <p class="page-subtitle">

                    Monitor and manage all job and internship
                    opportunities posted on CareerBridge.

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
             ERROR MESSAGE
        ====================================== -->

        <?php if ($error !== ''): ?>


            <div class="alert alert-error">


                <strong>
                    ⚠ Error
                </strong>


                <span>

                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </span>


            </div>


        <?php endif; ?>



        <!-- =====================================
             SUCCESS MESSAGE
        ====================================== -->

        <?php if ($success !== ''): ?>


            <div class="alert alert-success">


                <strong>
                    ✓ Success
                </strong>


                <span>

                    <?= htmlspecialchars(
                        $success,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </span>


            </div>


        <?php endif; ?>



        <!-- =====================================
             STATISTICS
        ====================================== -->

        <section class="stats-grid">


            <div class="stat-card">


                <div class="stat-icon">
                    💼
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



            <div class="stat-card">


                <div class="stat-icon">
                    🚀
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



            <div class="stat-card">


                <div class="stat-icon">
                    🔒
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



            <div class="stat-card">


                <div class="stat-icon">
                    🎓
                </div>


                <div>

                    <p>
                        Internships
                    </p>

                    <h2>
                        <?= $internshipCount ?>
                    </h2>

                </div>


            </div>


        </section>



        <!-- =====================================
             FILTER SECTION
        ====================================== -->

        <section class="content-card">


            <div class="section-heading">


                <div>


                    <p class="section-label">
                        SEARCH & FILTER
                    </p>


                    <h2>
                        Find Opportunities
                    </h2>


                    <p>

                        Search by opportunity title,
                        company name, or location.

                    </p>


                </div>


                <div class="section-icon">
                    🔎
                </div>


            </div>



            <form
                method="GET"
                class="profile-form"
            >


                <div class="form-group">


                    <label for="search">

                        Search

                    </label>


                    <input
                        type="text"
                        id="search"
                        name="search"
                        value="<?= htmlspecialchars(
                            $search,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        placeholder="Title, company or location"
                    >


                </div>



                <div class="form-group">


                    <label for="status">

                        Status

                    </label>


                    <select
                        id="status"
                        name="status"
                    >


                        <option value="">
                            All Statuses
                        </option>


                        <option
                            value="open"
                            <?= $statusFilter === 'open'
                                ? 'selected'
                                : ''
                            ?>
                        >

                            Open

                        </option>


                        <option
                            value="closed"
                            <?= $statusFilter === 'closed'
                                ? 'selected'
                                : ''
                            ?>
                        >

                            Closed

                        </option>


                    </select>


                </div>



                <div class="form-group">


                    <label for="type">

                        Opportunity Type

                    </label>


                    <select
                        id="type"
                        name="type"
                    >


                        <option value="">
                            All Types
                        </option>


                        <option
                            value="job"
                            <?= $typeFilter === 'job'
                                ? 'selected'
                                : ''
                            ?>
                        >

                            Job

                        </option>


                        <option
                            value="internship"
                            <?= $typeFilter === 'internship'
                                ? 'selected'
                                : ''
                            ?>
                        >

                            Internship

                        </option>


                        <option
                            value="training"
                            <?= $typeFilter === 'training'
                                ? 'selected'
                                : ''
                            ?>
                        >

                            Training

                        </option>


                        <option
                            value="other"
                            <?= $typeFilter === 'other'
                                ? 'selected'
                                : ''
                            ?>
                        >

                            Other

                        </option>


                    </select>


                </div>



                <div class="form-actions">


                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        🔎 Apply Filters

                    </button>


                    <a
                        href="opportunities.php"
                        class="btn btn-secondary"
                    >

                        Reset

                    </a>


                </div>


            </form>


        </section>



        <!-- =====================================
             OPPORTUNITIES LIST
        ====================================== -->

        <section class="content-card">


            <div class="section-heading">


                <div>


                    <p class="section-label">
                        ALL OPPORTUNITIES
                    </p>


                    <h2>
                        Posted Opportunities
                    </h2>


                    <p>

                        <?= count($opportunities) ?>

                        opportunity/opportunities found.

                    </p>


                </div>


                <div class="section-icon">
                    📋
                </div>


            </div>



            <!-- EMPTY STATE -->

            <?php if (!$opportunities): ?>


                <div class="empty-state">


                    <div class="empty-icon">
                        📭
                    </div>


                    <h3>
                        No Opportunities Found
                    </h3>


                    <p>

                        There are currently no opportunities
                        matching your search criteria.

                    </p>


                    <a
                        href="opportunities.php"
                        class="btn btn-primary"
                    >

                        View All Opportunities

                    </a>


                </div>



            <!-- OPPORTUNITY TABLE -->

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
                                    Location
                                </th>


                                <th>
                                    Deadline
                                </th>


                                <th>
                                    Applications
                                </th>


                                <th>
                                    Status
                                </th>


                                <th>
                                    Actions
                                </th>


                            </tr>


                        </thead>


                        <tbody>


                            <?php foreach (
                                $opportunities
                                as $opportunity
                            ): ?>


                                <tr>


                                    <!-- OPPORTUNITY -->

                                    <td>


                                        <strong>

                                            <?= htmlspecialchars(
                                                $opportunity['title'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>


                                        <br>


                                        <small>

                                            <?= htmlspecialchars(
                                                getOpportunityTypeIcon(
                                                    $opportunity[
                                                        'opportunity_type'
                                                    ]
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                            <?= htmlspecialchars(
                                                ucfirst(
                                                    $opportunity[
                                                        'opportunity_type'
                                                    ]
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </small>


                                    </td>



                                    <!-- COMPANY -->

                                    <td>


                                        <strong>

                                            <?= htmlspecialchars(
                                                $opportunity[
                                                    'company_name'
                                                ],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>


                                        <?php if (
                                            !empty(
                                                $opportunity['industry']
                                            )
                                        ): ?>


                                            <br>


                                            <small>

                                                <?= htmlspecialchars(
                                                    $opportunity[
                                                        'industry'
                                                    ],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </small>


                                        <?php endif; ?>


                                    </td>



                                    <!-- TYPE -->

                                    <td>


                                        <?= htmlspecialchars(
                                            ucfirst(
                                                $opportunity[
                                                    'opportunity_type'
                                                ]
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>


                                    </td>



                                    <!-- LOCATION -->

                                    <td>


                                        <?= htmlspecialchars(
                                            $opportunity['location']
                                                ?? 'Not specified',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>


                                    </td>



                                    <!-- DEADLINE -->

                                    <td>


                                        <?= htmlspecialchars(
                                            formatOpportunityDate(
                                                $opportunity['deadline']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>


                                    </td>



                                    <!-- APPLICATION COUNT -->

                                    <td>


                                        <strong>

                                            <?= (int) $opportunity[
                                                'application_count'
                                            ] ?>

                                        </strong>


                                    </td>



                                    <!-- STATUS -->

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



                                    <!-- ACTIONS -->

                                    <td>


                                        <form
                                            method="POST"
                                            style="display:inline;"
                                        >


                                            <input
                                                type="hidden"
                                                name="opportunity_id"
                                                value="<?= (int) $opportunity[
                                                    'opportunity_id'
                                                ] ?>"
                                            >


                                            <?php if (
                                                $opportunity['status']
                                                === 'open'
                                            ): ?>


                                                <input
                                                    type="hidden"
                                                    name="status"
                                                    value="closed"
                                                >


                                                <button
                                                    type="submit"
                                                    class="btn btn-secondary"
                                                    onclick="return confirm('Are you sure you want to close this opportunity?');"
                                                >

                                                    Close

                                                </button>


                                            <?php else: ?>


                                                <input
                                                    type="hidden"
                                                    name="status"
                                                    value="open"
                                                >


                                                <button
                                                    type="submit"
                                                    class="btn btn-primary"
                                                    onclick="return confirm('Are you sure you want to reopen this opportunity?');"
                                                >

                                                    Reopen

                                                </button>


                                            <?php endif; ?>


                                        </form>


                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        </tbody>


                    </table>


                </div>


            <?php endif; ?>


        </section>



        <!-- =====================================
             OPPORTUNITY SUMMARY
        ====================================== -->

        <section class="content-card">


            <div class="section-heading">


                <div>


                    <p class="section-label">
                        PLATFORM SUMMARY
                    </p>


                    <h2>
                        Opportunity Overview
                    </h2>


                    <p>

                        Overall opportunity distribution
                        across the CareerBridge platform.

                    </p>


                </div>


                <div class="section-icon">
                    📊
                </div>


            </div>



            <div class="application-meta">


                <div>


                    <span class="meta-label">
                        TOTAL
                    </span>


                    <strong>
                        <?= $totalOpportunities ?>
                    </strong>


                </div>



                <div>


                    <span class="meta-label">
                        OPEN
                    </span>


                    <strong>
                        <?= $openCount ?>
                    </strong>


                </div>



                <div>


                    <span class="meta-label">
                        CLOSED
                    </span>


                    <strong>
                        <?= $closedCount ?>
                    </strong>


                </div>



                <div>


                    <span class="meta-label">
                        JOBS
                    </span>


                    <strong>
                        <?= $jobCount ?>
                    </strong>


                </div>



                <div>


                    <span class="meta-label">
                        INTERNSHIPS
                    </span>


                    <strong>
                        <?= $internshipCount ?>
                    </strong>


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