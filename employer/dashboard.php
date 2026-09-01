<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';


/*
|--------------------------------------------------------------------------
| Employer Authentication
|--------------------------------------------------------------------------
*/

requireRole('employer');

$user = currentUser();

$userId = (int) ($user['id'] ?? 0);


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function dashboardTableExists(PDO $pdo, string $tableName): bool
{
    try {

        $stmt = $pdo->prepare(
            "SHOW TABLES LIKE ?"
        );

        $stmt->execute([$tableName]);

        return (bool) $stmt->fetchColumn();

    } catch (PDOException $e) {

        return false;
    }
}


function dashboardColumns(PDO $pdo, string $tableName): array
{
    try {

        $stmt = $pdo->query(
            "SHOW COLUMNS FROM `$tableName`"
        );

        $columns = [];

        while ($column = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $columns[] = $column['Field'];
        }

        return $columns;

    } catch (PDOException $e) {

        return [];
    }
}


function dashboardFindColumn(
    array $columns,
    array $possibleColumns
): ?string {

    foreach ($possibleColumns as $column) {

        if (in_array($column, $columns, true)) {

            return $column;
        }
    }

    return null;
}


function dashboardStatusClass(string $status): string
{
    $status = strtolower(trim($status));

    switch ($status) {

        case 'open':
        case 'active':
        case 'published':
        case 'selected':
            return 'status-success';

        case 'shortlisted':
        case 'interview':
        case 'under_review':
            return 'status-warning';

        case 'closed':
        case 'rejected':
        case 'expired':
            return 'status-danger';

        default:
            return 'status-info';
    }
}


function dashboardFormatStatus(string $status): string
{
    return ucwords(
        str_replace(
            '_',
            ' ',
            $status
        )
    );
}


/*
|--------------------------------------------------------------------------
| Default Variables
|--------------------------------------------------------------------------
*/

$employer = null;

$employerId = null;

$companyName = '';

$totalOpportunities = 0;
$openOpportunities = 0;
$closedOpportunities = 0;

$totalApplications = 0;
$submittedApplications = 0;
$underReviewApplications = 0;
$shortlistedApplications = 0;
$interviewApplications = 0;
$selectedApplications = 0;
$rejectedApplications = 0;

$recentApplications = [];
$recentOpportunities = [];

$upcomingInterviews = [];

$recentNotifications = [];
$unreadNotifications = 0;


/*
|--------------------------------------------------------------------------
| Get Employer Information
|--------------------------------------------------------------------------
|
| IMPORTANT:
| opportunities.employer_id references employers.employer_id
|
*/

try {

    $employerStmt = $pdo->prepare(
        "
        SELECT *
        FROM employers
        WHERE user_id = ?
        LIMIT 1
        "
    );

    $employerStmt->execute([
        $userId
    ]);

    $employer = $employerStmt->fetch(
        PDO::FETCH_ASSOC
    );


    if ($employer) {

        $employerId = (int) (
            $employer['employer_id']
            ?? 0
        );

        $companyName = trim(
            (string) (
                $employer['company_name']
                ?? ''
            )
        );
    }

} catch (PDOException $e) {

    $employer = null;
    $employerId = null;
}


/*
|--------------------------------------------------------------------------
| Display Name
|--------------------------------------------------------------------------
*/

$displayName = $companyName !== ''
    ? $companyName
    : (
        $user['full_name']
        ?? 'Employer'
    );


/*
|--------------------------------------------------------------------------
| Opportunity Statistics
|--------------------------------------------------------------------------
*/

if ($employerId !== null && $employerId > 0) {

    try {

        $opportunityStatsStmt = $pdo->prepare(
            "
            SELECT

                COUNT(*) AS total_opportunities,

                SUM(
                    CASE
                        WHEN status = 'open'
                        THEN 1
                        ELSE 0
                    END
                ) AS open_opportunities,

                SUM(
                    CASE
                        WHEN status IN (
                            'closed',
                            'filled'
                        )
                        THEN 1
                        ELSE 0
                    END
                ) AS closed_opportunities

            FROM opportunities

            WHERE employer_id = ?
            "
        );


        $opportunityStatsStmt->execute([
            $employerId
        ]);


        $opportunityStats =
            $opportunityStatsStmt->fetch(
                PDO::FETCH_ASSOC
            );


        if ($opportunityStats) {

            $totalOpportunities =
                (int) (
                    $opportunityStats[
                        'total_opportunities'
                    ]
                    ?? 0
                );


            $openOpportunities =
                (int) (
                    $opportunityStats[
                        'open_opportunities'
                    ]
                    ?? 0
                );


            $closedOpportunities =
                (int) (
                    $opportunityStats[
                        'closed_opportunities'
                    ]
                    ?? 0
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Recent Opportunities
        |--------------------------------------------------------------------------
        */

        $recentOpportunitiesStmt =
            $pdo->prepare(
                "
                SELECT
                    opportunity_id,
                    title,
                    opportunity_type,
                    location,
                    duration,
                    deadline,
                    status,
                    created_at

                FROM opportunities

                WHERE employer_id = ?

                ORDER BY
                    created_at DESC,
                    opportunity_id DESC

                LIMIT 5
                "
            );


        $recentOpportunitiesStmt->execute([
            $employerId
        ]);


        $recentOpportunities =
            $recentOpportunitiesStmt->fetchAll(
                PDO::FETCH_ASSOC
            );

    } catch (PDOException $e) {

        $totalOpportunities = 0;
        $openOpportunities = 0;
        $closedOpportunities = 0;

        $recentOpportunities = [];
    }
}


/*
|--------------------------------------------------------------------------
| Application Statistics
|--------------------------------------------------------------------------
*/

if ($employerId !== null && $employerId > 0) {

    try {

        $applicationColumns =
            dashboardColumns(
                $pdo,
                'applications'
            );


        $applicationStatusColumn =
            dashboardFindColumn(
                $applicationColumns,
                [
                    'status',
                    'application_status'
                ]
            );


        if ($applicationStatusColumn !== null) {

            $applicationStatsStmt =
                $pdo->prepare(
                    "
                    SELECT

                        COUNT(*) AS total_applications,

                        SUM(
                            CASE
                                WHEN a.`$applicationStatusColumn`
                                    = 'submitted'
                                THEN 1
                                ELSE 0
                            END
                        ) AS submitted_count,

                        SUM(
                            CASE
                                WHEN a.`$applicationStatusColumn`
                                    = 'under_review'
                                THEN 1
                                ELSE 0
                            END
                        ) AS under_review_count,

                        SUM(
                            CASE
                                WHEN a.`$applicationStatusColumn`
                                    = 'shortlisted'
                                THEN 1
                                ELSE 0
                            END
                        ) AS shortlisted_count,

                        SUM(
                            CASE
                                WHEN a.`$applicationStatusColumn`
                                    = 'interview'
                                THEN 1
                                ELSE 0
                            END
                        ) AS interview_count,

                        SUM(
                            CASE
                                WHEN a.`$applicationStatusColumn`
                                    = 'selected'
                                THEN 1
                                ELSE 0
                            END
                        ) AS selected_count,

                        SUM(
                            CASE
                                WHEN a.`$applicationStatusColumn`
                                    = 'rejected'
                                THEN 1
                                ELSE 0
                            END
                        ) AS rejected_count

                    FROM applications a

                    INNER JOIN opportunities o
                        ON o.opportunity_id =
                            a.opportunity_id

                    WHERE o.employer_id = ?
                    "
                );


            $applicationStatsStmt->execute([
                $employerId
            ]);


            $applicationStats =
                $applicationStatsStmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if ($applicationStats) {

                $totalApplications =
                    (int) (
                        $applicationStats[
                            'total_applications'
                        ]
                        ?? 0
                    );


                $submittedApplications =
                    (int) (
                        $applicationStats[
                            'submitted_count'
                        ]
                        ?? 0
                    );


                $underReviewApplications =
                    (int) (
                        $applicationStats[
                            'under_review_count'
                        ]
                        ?? 0
                    );


                $shortlistedApplications =
                    (int) (
                        $applicationStats[
                            'shortlisted_count'
                        ]
                        ?? 0
                    );


                $interviewApplications =
                    (int) (
                        $applicationStats[
                            'interview_count'
                        ]
                        ?? 0
                    );


                $selectedApplications =
                    (int) (
                        $applicationStats[
                            'selected_count'
                        ]
                        ?? 0
                    );


                $rejectedApplications =
                    (int) (
                        $applicationStats[
                            'rejected_count'
                        ]
                        ?? 0
                    );
            }
        }

    } catch (PDOException $e) {

        $totalApplications = 0;
    }
}


/*
|--------------------------------------------------------------------------
| Recent Applications
|--------------------------------------------------------------------------
*/

if (
    $employerId !== null
    &&
    $employerId > 0
) {

    try {

        $applicationColumns =
            dashboardColumns(
                $pdo,
                'applications'
            );


        $applicationDateColumn =
            dashboardFindColumn(
                $applicationColumns,
                [
                    'applied_at',
                    'created_at',
                    'application_date'
                ]
            );


        $orderColumn =
            $applicationDateColumn
            ?? 'application_id';


        $recentApplicationsStmt =
            $pdo->prepare(
                "
                SELECT

                    a.*,

                    o.title
                        AS opportunity_title,

                    u.full_name
                        AS candidate_name

                FROM applications a

                INNER JOIN opportunities o
                    ON o.opportunity_id =
                        a.opportunity_id

                INNER JOIN students s
                    ON s.student_id =
                        a.student_id

                INNER JOIN users u
                    ON u.user_id =
                        s.user_id

                WHERE o.employer_id = ?

                ORDER BY
                    a.`$orderColumn` DESC

                LIMIT 5
                "
            );


        $recentApplicationsStmt->execute([
            $employerId
        ]);


        $recentApplications =
            $recentApplicationsStmt->fetchAll(
                PDO::FETCH_ASSOC
            );

    } catch (PDOException $e) {

        $recentApplications = [];
    }
}


/*
|--------------------------------------------------------------------------
| Upcoming Interviews
|--------------------------------------------------------------------------
*/

if (
    $employerId !== null
    &&
    $employerId > 0
    &&
    dashboardTableExists(
        $pdo,
        'interviews'
    )
) {

    try {

        $interviewColumns =
            dashboardColumns(
                $pdo,
                'interviews'
            );


        $interviewApplicationColumn =
            dashboardFindColumn(
                $interviewColumns,
                [
                    'application_id'
                ]
            );


        $interviewDateColumn =
            dashboardFindColumn(
                $interviewColumns,
                [
                    'interview_date',
                    'scheduled_at',
                    'date'
                ]
            );


        if (
            $interviewApplicationColumn !== null
        ) {

            $dateCondition = '';

            if ($interviewDateColumn !== null) {

                $dateCondition =
                    "AND i.`$interviewDateColumn` >= CURDATE()";
            }


            $orderColumn =
                $interviewDateColumn
                ?? 'id';


            $upcomingInterviewsStmt =
                $pdo->prepare(
                    "
                    SELECT

                        i.*,

                        o.title
                            AS opportunity_title,

                        u.full_name
                            AS candidate_name

                    FROM interviews i

                    INNER JOIN applications a
                        ON a.application_id =
                            i.`$interviewApplicationColumn`

                    INNER JOIN opportunities o
                        ON o.opportunity_id =
                            a.opportunity_id

                    INNER JOIN students s
                        ON s.student_id =
                            a.student_id

                    INNER JOIN users u
                        ON u.user_id =
                            s.user_id

                    WHERE o.employer_id = ?

                    $dateCondition

                    ORDER BY
                        i.`$orderColumn` ASC

                    LIMIT 5
                    "
                );


            $upcomingInterviewsStmt->execute([
                $employerId
            ]);


            $upcomingInterviews =
                $upcomingInterviewsStmt->fetchAll(
                    PDO::FETCH_ASSOC
                );
        }

    } catch (PDOException $e) {

        $upcomingInterviews = [];
    }
}


/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

if (
    dashboardTableExists(
        $pdo,
        'notifications'
    )
) {

    try {

        $notificationColumns =
            dashboardColumns(
                $pdo,
                'notifications'
            );


        $notificationUserColumn =
            dashboardFindColumn(
                $notificationColumns,
                [
                    'user_id',
                    'recipient_id'
                ]
            );


        $notificationReadColumn =
            dashboardFindColumn(
                $notificationColumns,
                [
                    'is_read',
                    'read_status'
                ]
            );


        $notificationCreatedColumn =
            dashboardFindColumn(
                $notificationColumns,
                [
                    'created_at',
                    'created_date'
                ]
            );


        if ($notificationUserColumn !== null) {

            if ($notificationReadColumn !== null) {

                $unreadStmt =
                    $pdo->prepare(
                        "
                        SELECT COUNT(*)

                        FROM notifications

                        WHERE `$notificationUserColumn` = ?

                        AND `$notificationReadColumn` = 0
                        "
                    );


                $unreadStmt->execute([
                    $userId
                ]);


                $unreadNotifications =
                    (int) $unreadStmt->fetchColumn();
            }


            $orderColumn =
                $notificationCreatedColumn
                ?? 'id';


            $notificationStmt =
                $pdo->prepare(
                    "
                    SELECT *

                    FROM notifications

                    WHERE `$notificationUserColumn` = ?

                    ORDER BY
                        `$orderColumn` DESC

                    LIMIT 4
                    "
                );


            $notificationStmt->execute([
                $userId
            ]);


            $recentNotifications =
                $notificationStmt->fetchAll(
                    PDO::FETCH_ASSOC
                );
        }

    } catch (PDOException $e) {

        $recentNotifications = [];
        $unreadNotifications = 0;
    }
}


/*
|--------------------------------------------------------------------------
| Dashboard Calculations
|--------------------------------------------------------------------------
*/

$selectionProgress = 0;

if ($totalApplications > 0) {

    $selectionProgress =
        (int) round(
            (
                $selectedApplications
                /
                $totalApplications
            )
            * 100
        );
}


$openOpportunityPercentage = 0;

if ($totalOpportunities > 0) {

    $openOpportunityPercentage =
        (int) round(
            (
                $openOpportunities
                /
                $totalOpportunities
            )
            * 100
        );
}


$initial =
    strtoupper(
        substr(
            $companyName !== ''
                ? $companyName
                : ($user['full_name'] ?? 'E'),
            0,
            1
        )
    );

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
        Employer Dashboard | CareerBridge
    </title>


    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


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


        <nav class="sidebar-nav">


            <p class="menu-label">

                MAIN MENU

            </p>


            <a
                href="dashboard.php"
                class="nav-link active"
            >

                <i class="fa-solid fa-house"></i>

                <span>
                    Dashboard
                </span>

            </a>


            <a
                href="profile.php"
                class="nav-link"
            >

                <i class="fa-solid fa-building"></i>

                <span>
                    Company Profile
                </span>

            </a>


            <a
                href="opportunities.php"
                class="nav-link"
            >

                <i class="fa-solid fa-briefcase"></i>

                <span>
                    My Opportunities
                </span>

            </a>


            <a
                href="create_opportunity.php"
                class="nav-link"
            >

                <i class="fa-solid fa-plus"></i>

                <span>
                    Post Opportunity
                </span>

            </a>


            <a
                href="applications.php"
                class="nav-link"
            >

                <i class="fa-solid fa-file-lines"></i>

                <span>
                    Applications
                </span>

            </a>


            <a
                href="interviews.php"
                class="nav-link"
            >

                <i class="fa-solid fa-calendar-check"></i>

                <span>
                    Interviews
                </span>

            </a>


            <a
                href="notifications.php"
                class="nav-link notification-nav"
            >

                <i class="fa-solid fa-bell"></i>

                <span>
                    Notifications
                </span>


                <?php if ($unreadNotifications > 0): ?>

                    <small class="nav-badge">

                        <?= $unreadNotifications ?>

                    </small>

                <?php endif; ?>


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

    <main class="main-content">


        <!-- HEADER -->

        <header class="top-header">


            <div>


                <p class="breadcrumb">

                    EMPLOYER PORTAL / DASHBOARD

                </p>


                <h2>

                    Employer Dashboard

                </h2>


                <p class="welcome-text">

                    Manage your opportunities, candidates,
                    and recruitment activities.

                </p>


            </div>



            <div class="header-actions">


                <a
                    href="notifications.php"
                    class="header-notification"
                >

                    <i class="fa-solid fa-bell"></i>


                    <?php if ($unreadNotifications > 0): ?>

                        <span>

                            <?= $unreadNotifications ?>

                        </span>

                    <?php endif; ?>


                </a>



                <div class="user-info">


                    <div class="user-avatar">

                        <?= htmlspecialchars(
                            $initial,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>


                    <div class="user-details">


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


        </header>



        <!-- =====================================
             WELCOME BANNER
        ====================================== -->

        <section class="dashboard-welcome-banner">


            <div class="welcome-banner-content">


                <div>


                    <p class="section-label">

                        WELCOME BACK

                    </p>


                    <h3>

                        <?= htmlspecialchars(
                            $displayName,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </h3>


                    <p>

                        Here's what's happening with your
                        recruitment activities today.

                    </p>


                </div>


                <a
                    href="create_opportunity.php"
                    class="welcome-action-button"
                >

                    <i class="fa-solid fa-plus"></i>

                    Post Opportunity

                </a>


            </div>


        </section>



        <!-- =====================================
             STATISTICS
        ====================================== -->

        <section class="stats-grid">


            <!-- TOTAL -->

            <div class="stat-card">


                <div class="stat-icon blue">

                    <i class="fa-solid fa-briefcase"></i>

                </div>


                <div class="stat-content">


                    <p>

                        Total Opportunities

                    </p>


                    <h3>

                        <?= $totalOpportunities ?>

                    </h3>


                    <small>

                        All posted opportunities

                    </small>


                </div>


            </div>



            <!-- ACTIVE -->

            <div class="stat-card">


                <div class="stat-icon green">

                    <i class="fa-solid fa-circle-check"></i>

                </div>


                <div class="stat-content">


                    <p>

                        Active Opportunities

                    </p>


                    <h3>

                        <?= $openOpportunities ?>

                    </h3>


                    <small>

                        Currently accepting applications

                    </small>


                </div>


            </div>



            <!-- APPLICATIONS -->

            <div class="stat-card">


                <div class="stat-icon orange">

                    <i class="fa-solid fa-file-circle-check"></i>

                </div>


                <div class="stat-content">


                    <p>

                        Total Applications

                    </p>


                    <h3>

                        <?= $totalApplications ?>

                    </h3>


                    <small>

                        Candidate submissions received

                    </small>


                </div>


            </div>



            <!-- SELECTED -->

            <div class="stat-card">


                <div class="stat-icon purple">

                    <i class="fa-solid fa-user-check"></i>

                </div>


                <div class="stat-content">


                    <p>

                        Selected Candidates

                    </p>


                    <h3>

                        <?= $selectedApplications ?>

                    </h3>


                    <small>

                        Successfully selected candidates

                    </small>


                </div>


            </div>


        </section>



        <!-- =====================================
             DASHBOARD GRID
        ====================================== -->

        <section class="dashboard-grid dashboard-grid-expanded">


            <!-- LEFT -->

            <div class="dashboard-column dashboard-main-column">


                <!-- RECENT APPLICATIONS -->

                <div class="dashboard-card dashboard-table-card">


                    <div class="card-header">


                        <div>


                            <p class="section-label">

                                CANDIDATE ACTIVITY

                            </p>


                            <h3>

                                Recent Applications

                            </h3>


                        </div>


                        <a
                            href="applications.php"
                            class="view-all-link"
                        >

                            View All

                            <i class="fa-solid fa-arrow-right"></i>

                        </a>


                    </div>



                    <?php if (!empty($recentApplications)): ?>


                        <div class="applications-table-wrapper">


                            <table class="dashboard-table">


                                <thead>

                                    <tr>

                                        <th>
                                            Candidate
                                        </th>

                                        <th>
                                            Opportunity
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


                                            <div class="candidate-cell">


                                                <div class="candidate-avatar">

                                                    <?= htmlspecialchars(
                                                        strtoupper(
                                                            substr(
                                                                $application[
                                                                    'candidate_name'
                                                                ]
                                                                ?? 'C',
                                                                0,
                                                                1
                                                            )
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>

                                                </div>


                                                <strong>

                                                    <?= htmlspecialchars(
                                                        $application[
                                                            'candidate_name'
                                                        ]
                                                        ?? 'Candidate',
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>

                                                </strong>


                                            </div>


                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $application[
                                                    'opportunity_title'
                                                ]
                                                ?? 'Opportunity',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </td>


                                        <td>


                                            <?php
                                            $appStatus =
                                                $application[
                                                    'status'
                                                ]
                                                ?? 'submitted';
                                            ?>


                                            <span
                                                class="status-badge <?= dashboardStatusClass(
                                                    $appStatus
                                                ) ?>"
                                            >

                                                <?= htmlspecialchars(
                                                    dashboardFormatStatus(
                                                        $appStatus
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


                    <?php else: ?>


                        <div class="dashboard-empty-state">


                            <div class="empty-icon">

                                <i class="fa-solid fa-file-circle-plus"></i>

                            </div>


                            <h4>

                                No applications yet

                            </h4>


                            <p>

                                Applications from candidates will
                                appear here once they apply to
                                your opportunities.

                            </p>


                        </div>


                    <?php endif; ?>


                </div>



                <!-- RECENT OPPORTUNITIES -->

                <div class="dashboard-card">


                    <div class="card-header">


                        <div>


                            <p class="section-label">

                                RECRUITMENT

                            </p>


                            <h3>

                                Recent Opportunities

                            </h3>


                        </div>


                        <a
                            href="opportunities.php"
                            class="view-all-link"
                        >

                            View All

                            <i class="fa-solid fa-arrow-right"></i>

                        </a>


                    </div>



                    <?php if (!empty($recentOpportunities)): ?>


                        <div class="opportunity-list">


                            <?php foreach (
                                $recentOpportunities
                                as $opportunity
                            ): ?>


                                <div class="opportunity-item">


                                    <div class="opportunity-icon">

                                        <i class="fa-solid fa-briefcase"></i>

                                    </div>


                                    <div class="opportunity-content">


                                        <div class="opportunity-title-row">


                                            <h4>

                                                <?= htmlspecialchars(
                                                    $opportunity['title']
                                                    ?? 'Untitled Opportunity',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </h4>


                                            <span
                                                class="status-badge <?= dashboardStatusClass(
                                                    $opportunity['status']
                                                    ?? 'open'
                                                ) ?>"
                                            >

                                                <?= htmlspecialchars(
                                                    dashboardFormatStatus(
                                                        $opportunity[
                                                            'status'
                                                        ]
                                                        ?? 'open'
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </span>


                                        </div>



                                        <div class="opportunity-meta">


                                            <span>

                                                <i class="fa-solid fa-tag"></i>

                                                <?= htmlspecialchars(
                                                    dashboardFormatStatus(
                                                        $opportunity[
                                                            'opportunity_type'
                                                        ]
                                                        ?? 'Opportunity'
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </span>



                                            <?php if (
                                                !empty(
                                                    $opportunity['location']
                                                )
                                            ): ?>


                                                <span>

                                                    <i class="fa-solid fa-location-dot"></i>

                                                    <?= htmlspecialchars(
                                                        $opportunity[
                                                            'location'
                                                        ],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>

                                                </span>


                                            <?php endif; ?>



                                            <?php if (
                                                !empty(
                                                    $opportunity['deadline']
                                                )
                                            ): ?>


                                                <span>

                                                    <i class="fa-solid fa-calendar"></i>

                                                    Deadline:

                                                    <?= htmlspecialchars(
                                                        date(
                                                            'M d, Y',
                                                            strtotime(
                                                                $opportunity[
                                                                    'deadline'
                                                                ]
                                                            )
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>

                                                </span>


                                            <?php endif; ?>


                                        </div>


                                    </div>


                                </div>


                            <?php endforeach; ?>


                        </div>


                    <?php else: ?>


                        <div class="dashboard-empty-state compact-empty">


                            <div class="empty-icon">

                                <i class="fa-solid fa-briefcase"></i>

                            </div>


                            <h4>

                                No opportunities posted

                            </h4>


                            <p>

                                Start recruiting by posting your
                                first opportunity.

                            </p>


                            <a
                                href="create_opportunity.php"
                                class="secondary-button"
                            >

                                <i class="fa-solid fa-plus"></i>

                                Post Opportunity

                            </a>


                        </div>


                    <?php endif; ?>


                </div>


            </div>



            <!-- RIGHT -->

            <div class="dashboard-column dashboard-side-column">


                <!-- APPLICATION OVERVIEW -->

                <div class="dashboard-card analytics-card">


                    <div class="card-header">


                        <div>


                            <p class="section-label">

                                ANALYTICS

                            </p>


                            <h3>

                                Application Overview

                            </h3>


                        </div>


                        <span class="card-label">

                            <i class="fa-solid fa-chart-pie"></i>

                        </span>


                    </div>


                    <div class="analytics-content">


                        <div class="analytics-row">

                            <span>
                                Total Applications
                            </span>

                            <strong>
                                <?= $totalApplications ?>
                            </strong>

                        </div>


                        <div class="analytics-row">

                            <span>
                                Submitted
                            </span>

                            <strong>
                                <?= $submittedApplications ?>
                            </strong>

                        </div>


                        <div class="analytics-row">

                            <span>
                                Under Review
                            </span>

                            <strong>
                                <?= $underReviewApplications ?>
                            </strong>

                        </div>


                        <div class="analytics-row">

                            <span>
                                Shortlisted
                            </span>

                            <strong>
                                <?= $shortlistedApplications ?>
                            </strong>

                        </div>


                        <div class="analytics-row">

                            <span>
                                Interviews
                            </span>

                            <strong>
                                <?= $interviewApplications ?>
                            </strong>

                        </div>


                        <div class="analytics-row">

                            <span>
                                Selected
                            </span>

                            <strong>
                                <?= $selectedApplications ?>
                            </strong>

                        </div>


                        <div class="progress-section">


                            <div class="progress-label">

                                <span>
                                    Selection Progress
                                </span>

                                <strong>
                                    <?= $selectionProgress ?>%
                                </strong>

                            </div>


                            <div class="progress-bar">

                                <div
                                    class="progress-fill"
                                    style="width: <?= $selectionProgress ?>%;"
                                ></div>

                            </div>


                        </div>


                    </div>


                    <a
                        href="applications.php"
                        class="card-link"
                    >

                        <span>
                            Review Applications
                        </span>

                        <i class="fa-solid fa-arrow-right"></i>

                    </a>


                </div>



                <!-- OPPORTUNITY OVERVIEW -->

                <div class="dashboard-card">


                    <div class="card-header">


                        <div>


                            <p class="section-label">

                                RECRUITMENT

                            </p>


                            <h3>

                                Opportunity Overview

                            </h3>


                        </div>


                        <span class="card-label">

                            <i class="fa-solid fa-chart-column"></i>

                        </span>


                    </div>


                    <div class="summary-list">


                        <div class="summary-item">

                            <span>
                                Total Opportunities
                            </span>

                            <strong>
                                <?= $totalOpportunities ?>
                            </strong>

                        </div>


                        <div class="summary-item">

                            <span>
                                Currently Open
                            </span>

                            <strong>
                                <?= $openOpportunities ?>
                            </strong>

                        </div>


                        <div class="summary-item">

                            <span>
                                Closed / Filled
                            </span>

                            <strong>
                                <?= $closedOpportunities ?>
                            </strong>

                        </div>


                    </div>


                    <div class="progress-section">


                        <div class="progress-label">

                            <span>
                                Active Opportunities
                            </span>

                            <strong>
                                <?= $openOpportunityPercentage ?>%
                            </strong>

                        </div>


                        <div class="progress-bar">

                            <div
                                class="progress-fill"
                                style="width: <?= $openOpportunityPercentage ?>%;"
                            ></div>

                        </div>


                    </div>


                    <a
                        href="opportunities.php"
                        class="card-link"
                    >

                        <span>
                            Manage Opportunities
                        </span>

                        <i class="fa-solid fa-arrow-right"></i>

                    </a>


                </div>



                <!-- UPCOMING INTERVIEWS -->

                <div class="dashboard-card">


                    <div class="card-header">


                        <div>


                            <p class="section-label">

                                SCHEDULE

                            </p>


                            <h3>

                                Upcoming Interviews

                            </h3>


                        </div>


                        <span class="card-label">

                            <i class="fa-solid fa-calendar-check"></i>

                        </span>


                    </div>



                    <?php if (!empty($upcomingInterviews)): ?>


                        <div class="interview-list">


                            <?php foreach (
                                $upcomingInterviews
                                as $interview
                            ): ?>


                                <div class="interview-item">


                                    <div class="interview-date-icon">

                                        <i class="fa-solid fa-calendar"></i>

                                    </div>


                                    <div>


                                        <strong>

                                            <?= htmlspecialchars(
                                                $interview[
                                                    'candidate_name'
                                                ]
                                                ?? 'Candidate',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>


                                        <p>

                                            <?= htmlspecialchars(
                                                $interview[
                                                    'opportunity_title'
                                                ]
                                                ?? 'Opportunity',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </p>


                                    </div>


                                </div>


                            <?php endforeach; ?>


                        </div>


                    <?php else: ?>


                        <div class="dashboard-empty-state compact-empty">


                            <div class="empty-icon">

                                <i class="fa-solid fa-calendar-xmark"></i>

                            </div>


                            <h4>

                                No upcoming interviews

                            </h4>


                            <p>

                                Scheduled interviews will appear here.

                            </p>


                        </div>


                    <?php endif; ?>


                </div>



                <!-- QUICK ACTIONS -->

                <div class="dashboard-card quick-access-card">


                    <div class="card-header">


                        <div>


                            <p class="section-label">

                                QUICK ACCESS

                            </p>


                            <h3>

                                Recruitment Management

                            </h3>


                        </div>


                        <span class="card-label">

                            <i class="fa-solid fa-bolt"></i>

                        </span>


                    </div>


                    <div class="quick-access-grid">


                        <a
                            href="create_opportunity.php"
                            class="quick-item"
                        >

                            <i class="fa-solid fa-plus"></i>

                            <div>

                                <strong>
                                    Post Opportunity
                                </strong>

                                <small>
                                    Create a new job or internship
                                </small>

                            </div>

                        </a>


                        <a
                            href="opportunities.php"
                            class="quick-item"
                        >

                            <i class="fa-solid fa-briefcase"></i>

                            <div>

                                <strong>
                                    My Opportunities
                                </strong>

                                <small>
                                    Manage posted opportunities
                                </small>

                            </div>

                        </a>


                        <a
                            href="applications.php"
                            class="quick-item"
                        >

                            <i class="fa-solid fa-users"></i>

                            <div>

                                <strong>
                                    Review Applications
                                </strong>

                                <small>
                                    View candidate applications
                                </small>

                            </div>

                        </a>


                        <a
                            href="profile.php"
                            class="quick-item"
                        >

                            <i class="fa-solid fa-building"></i>

                            <div>

                                <strong>
                                    Company Profile
                                </strong>

                                <small>
                                    Update company information
                                </small>

                            </div>

                        </a>


                    </div>


                </div>


            </div>


        </section>



        <!-- FOOTER -->

        <footer class="dashboard-footer">


            <span>

                &copy; <?= date('Y') ?>

                CareerBridge - Career Management Platform

            </span>


        </footer>


    </main>


</div>


</body>

</html>