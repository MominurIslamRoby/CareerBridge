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
$userId = (int) $user['id'];


/*
|--------------------------------------------------------------------------
| Get Employer Information
|--------------------------------------------------------------------------
*/

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

$employer = $employerStmt->fetch(PDO::FETCH_ASSOC);

if (!$employer) {

    http_response_code(404);

    exit('Employer profile not found.');
}

$employerId = (int) $employer['employer_id'];

$companyName = $employer['company_name'] ?? '';

$displayName = $companyName !== ''
    ? $companyName
    : ($user['full_name'] ?? 'Employer');

$avatarLetter = strtoupper(
    substr(
        $displayName,
        0,
        1
    )
);


/*
|--------------------------------------------------------------------------
| Interview Statistics
|--------------------------------------------------------------------------
*/

$statsStmt = $pdo->prepare(
    '
    SELECT

        COUNT(*) AS total_interviews,

        SUM(
            CASE
                WHEN i.status IN ("scheduled", "rescheduled")
                THEN 1
                ELSE 0
            END
        ) AS scheduled_count,

        SUM(
            CASE
                WHEN i.status = "completed"
                THEN 1
                ELSE 0
            END
        ) AS completed_count,

        SUM(
            CASE
                WHEN i.status = "cancelled"
                THEN 1
                ELSE 0
            END
        ) AS cancelled_count

    FROM interviews i

    INNER JOIN applications a
        ON a.application_id = i.application_id

    INNER JOIN opportunities o
        ON o.opportunity_id = a.opportunity_id

    WHERE o.employer_id = ?
    '
);

$statsStmt->execute([$employerId]);

$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);


$totalInterviews = (int) (
    $stats['total_interviews'] ?? 0
);

$scheduledInterviews = (int) (
    $stats['scheduled_count'] ?? 0
);

$completedInterviews = (int) (
    $stats['completed_count'] ?? 0
);

$cancelledInterviews = (int) (
    $stats['cancelled_count'] ?? 0
);


/*
|--------------------------------------------------------------------------
| Get Employer Interviews
|--------------------------------------------------------------------------
|
| Relationship:
|
| employers
|     ↓
| opportunities
|     ↓
| applications
|     ↓
| interviews
|
| students → users
|
|--------------------------------------------------------------------------
*/

$interviewsStmt = $pdo->prepare(
    '
    SELECT

        i.interview_id,
        i.application_id,
        i.interview_date,
        i.interview_mode,
        i.interview_location,
        i.meeting_link,
        i.notes,
        i.status AS interview_status,

        a.status AS application_status,

        s.student_id,

        u.user_id,
        u.full_name,
        u.email,

        o.opportunity_id,
        o.title AS opportunity_title,
        o.opportunity_type

    FROM interviews i

    INNER JOIN applications a
        ON a.application_id = i.application_id

    INNER JOIN students s
        ON s.student_id = a.student_id

    INNER JOIN users u
        ON u.user_id = s.user_id

    INNER JOIN opportunities o
        ON o.opportunity_id = a.opportunity_id

    WHERE o.employer_id = ?

    ORDER BY
        CASE
            WHEN i.status IN ("scheduled", "rescheduled")
            THEN 0
            ELSE 1
        END ASC,

        i.interview_date ASC
    '
);

$interviewsStmt->execute([$employerId]);

$interviews = $interviewsStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function formatInterviewDate(?string $date): string
{
    if (empty($date)) {
        return 'Not specified';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return 'Not specified';
    }

    return date(
        'M d, Y h:i A',
        $timestamp
    );
}


function getInterviewStatusClass(string $status): string
{
    switch ($status) {

        case 'scheduled':
            return 'status-interview';

        case 'rescheduled':
            return 'status-shortlisted';

        case 'completed':
            return 'status-selected';

        case 'cancelled':
            return 'status-rejected';

        default:
            return 'status-submitted';
    }
}


function getInterviewStatusIcon(string $status): string
{
    switch ($status) {

        case 'scheduled':
            return 'fa-solid fa-calendar-check';

        case 'rescheduled':
            return 'fa-solid fa-calendar-plus';

        case 'completed':
            return 'fa-solid fa-circle-check';

        case 'cancelled':
            return 'fa-solid fa-circle-xmark';

        default:
            return 'fa-solid fa-calendar';
    }
}


function formatInterviewStatus(string $status): string
{
    return ucwords(
        str_replace(
            '_',
            ' ',
            $status
        )
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

    <title>Interviews | CareerBridge</title>


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
                CB
            </div>


            <div>

                <h2>
                    CareerBridge
                </h2>

                <span>
                    Employer Portal
                </span>

            </div>

        </div>


        <div class="sidebar-divider"></div>


        <p class="menu-label">
            MAIN MENU
        </p>


        <nav class="sidebar-nav">


            <a href="dashboard.php">

                <span>
                    <i class="fa-solid fa-house"></i>
                </span>

                Dashboard

            </a>


            <a href="opportunities.php">

                <span>
                    <i class="fa-solid fa-briefcase"></i>
                </span>

                Opportunities

            </a>


            <a href="applications.php">

                <span>
                    <i class="fa-solid fa-file-lines"></i>
                </span>

                Applications

            </a>


            <a
                href="interviews.php"
                class="active"
            >

                <span>
                    <i class="fa-solid fa-calendar-check"></i>
                </span>

                Interviews

            </a>


        </nav>


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


        <!-- PAGE HEADER -->

        <div class="page-header">


            <div>

                <p class="breadcrumb">
                    EMPLOYER PORTAL / INTERVIEWS
                </p>


                <h1>
                    Interview Management
                </h1>


                <p class="page-subtitle">
                    Manage and track candidate interviews for your opportunities.
                </p>

            </div>


            <!-- USER CARD -->

            <div class="user-card">


                <div class="user-avatar">

                    <?= htmlspecialchars(
                        $avatarLetter,
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
                        Employer
                    </span>

                </div>


            </div>


        </div>



        <!-- =====================================
             PAGE ACTION
        ====================================== -->

        <div class="section-heading">


            <div>

                <p class="section-label">
                    RECRUITMENT MANAGEMENT
                </p>


                <h2>
                    Candidate Interviews
                </h2>


                <p>
                    View, manage, and track all scheduled candidate interviews.
                </p>

            </div>


            <div class="section-icon">

                <i class="fa-solid fa-calendar-check"></i>

            </div>


        </div>



        <!-- =====================================
             STATISTICS
        ====================================== -->

        <section class="stats-grid">


            <!-- TOTAL -->

            <div class="stat-card">

                <div class="stat-icon">

                    <i class="fa-solid fa-calendar-days"></i>

                </div>


                <div>

                    <p>
                        Total Interviews
                    </p>

                    <h2>
                        <?= $totalInterviews ?>
                    </h2>

                </div>

            </div>



            <!-- SCHEDULED -->

            <div class="stat-card">

                <div class="stat-icon">

                    <i class="fa-solid fa-clock"></i>

                </div>


                <div>

                    <p>
                        Scheduled
                    </p>

                    <h2>
                        <?= $scheduledInterviews ?>
                    </h2>

                </div>

            </div>



            <!-- COMPLETED -->

            <div class="stat-card">

                <div class="stat-icon">

                    <i class="fa-solid fa-circle-check"></i>

                </div>


                <div>

                    <p>
                        Completed
                    </p>

                    <h2>
                        <?= $completedInterviews ?>
                    </h2>

                </div>

            </div>



            <!-- CANCELLED -->

            <div class="stat-card">

                <div class="stat-icon">

                    <i class="fa-solid fa-circle-xmark"></i>

                </div>


                <div>

                    <p>
                        Cancelled
                    </p>

                    <h2>
                        <?= $cancelledInterviews ?>
                    </h2>

                </div>

            </div>


        </section>



        <!-- =====================================
             INTERVIEW LIST
        ====================================== -->

        <section class="content-card">


            <div class="section-heading">


                <div>

                    <p class="section-label">
                        INTERVIEW SCHEDULE
                    </p>


                    <h2>
                        All Interviews
                    </h2>


                    <p>
                        Upcoming and previous interviews for your applicants.
                    </p>

                </div>


                <div class="section-icon">

                    <i class="fa-solid fa-users"></i>

                </div>


            </div>



            <?php if (empty($interviews)): ?>


                <!-- EMPTY STATE -->

                <div class="empty-state">


                    <div class="empty-icon">

                        <i class="fa-solid fa-calendar-xmark"></i>

                    </div>


                    <h3>
                        No Interviews Scheduled
                    </h3>


                    <p>
                        You haven't scheduled any candidate interviews yet.
                        Shortlist an applicant first and schedule an interview
                        from the Applications page.
                    </p>


                    <a
                        href="applications.php"
                        class="btn btn-primary"
                    >

                        <i class="fa-solid fa-file-lines"></i>

                        View Applications

                    </a>


                </div>



            <?php else: ?>


                <div class="table-responsive">


                    <table class="data-table">


                        <thead>

                            <tr>

                                <th>
                                    Candidate
                                </th>

                                <th>
                                    Opportunity
                                </th>

                                <th>
                                    Date & Time
                                </th>

                                <th>
                                    Mode
                                </th>

                                <th>
                                    Location / Meeting
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php foreach ($interviews as $interview): ?>


                                <?php

                                $status = strtolower(
                                    trim(
                                        $interview['interview_status']
                                        ?? 'scheduled'
                                    )
                                );


                                $mode = strtolower(
                                    trim(
                                        $interview['interview_mode']
                                        ?? 'online'
                                    )
                                );


                                $candidateName =
                                    $interview['full_name']
                                    ?? 'Candidate';


                                $opportunityTitle =
                                    $interview['opportunity_title']
                                    ?? 'Not specified';


                                $interviewId = (int) (
                                    $interview['interview_id']
                                    ?? 0
                                );

                                ?>


                                <tr>


                                    <!-- CANDIDATE -->

                                    <td>


                                        <div class="candidate-cell">


                                            <div class="candidate-avatar">

                                                <?= htmlspecialchars(
                                                    strtoupper(
                                                        substr(
                                                            $candidateName,
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
                                                        $candidateName,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>

                                                </strong>


                                                <?php if (
                                                    !empty(
                                                        $interview['email']
                                                    )
                                                ): ?>

                                                    <small>

                                                        <?= htmlspecialchars(
                                                            $interview['email'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>

                                                    </small>

                                                <?php endif; ?>


                                            </div>


                                        </div>


                                    </td>



                                    <!-- OPPORTUNITY -->

                                    <td>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $opportunityTitle,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>

                                    </td>



                                    <!-- DATE -->

                                    <td>

                                        <i class="fa-solid fa-calendar"></i>

                                        <?= htmlspecialchars(
                                            formatInterviewDate(
                                                $interview['interview_date']
                                                ?? null
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>



                                    <!-- MODE -->

                                    <td>


                                        <span class="interview-type">


                                            <?php if (
                                                $mode === 'online'
                                            ): ?>

                                                <i class="fa-solid fa-video"></i>

                                            <?php else: ?>

                                                <i class="fa-solid fa-building"></i>

                                            <?php endif; ?>


                                            <?= htmlspecialchars(
                                                ucfirst($mode),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>


                                        </span>


                                    </td>



                                    <!-- LOCATION / MEETING -->

                                    <td>


                                        <?php if (
                                            $mode === 'online'
                                        ): ?>


                                            <?php if (
                                                !empty(
                                                    $interview['meeting_link']
                                                )
                                            ): ?>


                                                <a
                                                    href="<?= htmlspecialchars(
                                                        $interview['meeting_link'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="table-action"
                                                >

                                                    <i class="fa-solid fa-video"></i>

                                                    Join Meeting

                                                </a>


                                            <?php else: ?>

                                                <span>
                                                    Online Meeting
                                                </span>

                                            <?php endif; ?>


                                        <?php else: ?>


                                            <i class="fa-solid fa-location-dot"></i>

                                            <?= htmlspecialchars(
                                                $interview['interview_location']
                                                ?? 'Not specified',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>


                                        <?php endif; ?>


                                    </td>



                                    <!-- STATUS -->

                                    <td>


                                        <span
                                            class="status-badge <?= htmlspecialchars(
                                                getInterviewStatusClass(
                                                    $status
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                        >


                                            <i
                                                class="<?= htmlspecialchars(
                                                    getInterviewStatusIcon(
                                                        $status
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                            ></i>


                                            <?= htmlspecialchars(
                                                formatInterviewStatus(
                                                    $status
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>


                                        </span>


                                    </td>



                                    <!-- ACTION -->

                                    <td>


                                        <a
                                            href="manage_interview.php?id=<?= $interviewId ?>"
                                            class="btn btn-secondary"
                                        >

                                            <i class="fa-solid fa-pen"></i>

                                            Manage

                                        </a>


                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        </tbody>


                    </table>


                </div>


            <?php endif; ?>


        </section>



        <!-- FOOTER -->

        <footer class="page-footer">

            &copy; <?= date('Y') ?>

            CareerBridge — The Ultimate Career Management Platform

        </footer>


    </main>


</div>


</body>

</html>