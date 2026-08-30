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


/* =========================================
   VALIDATE APPLICATION ID
========================================= */

$applicationId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$applicationId) {

    http_response_code(400);

    exit('Invalid application ID.');
}


/* =========================================
   GET APPLICATION INFORMATION

   Security:
   Employer can only manage applications
   belonging to their own opportunities.
========================================= */

$applicationStmt = $pdo->prepare(
    '
    SELECT
        a.application_id,
        a.status,
        a.applied_at,

        s.student_id,
        s.student_id_number,
        s.university_name,
        s.department,
        s.academic_level,

        u.user_id,
        u.full_name,
        u.email,

        o.opportunity_id,
        o.title,
        o.opportunity_type,

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

    WHERE a.application_id = ?
      AND o.employer_id = ?

    LIMIT 1
    '
);

$applicationStmt->execute([
    $applicationId,
    $employerId
]);

$application = $applicationStmt->fetch();

if (!$application) {

    http_response_code(404);

    exit('Application not found or access denied.');
}


/* =========================================
   INITIALIZE VARIABLES
========================================= */

$error = '';

$success = '';

$currentStatus = $application['status'];


/* =========================================
   ALLOWED APPLICATION STATUSES
========================================= */

$allowedStatuses = [
    'submitted',
    'shortlisted',
    'interview',
    'selected',
    'rejected'
];


/* =========================================
   HANDLE STATUS UPDATE
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    $newStatus = strtolower(
        trim(
            $_POST['status'] ?? ''
        )
    );


    /* -----------------------------------------
       VALIDATE STATUS
    ----------------------------------------- */

    if (!in_array($newStatus, $allowedStatuses, true)) {

        $error =
            'Invalid application status selected.';

    } elseif ($newStatus === $currentStatus) {

        $error =
            'The application already has this status.';

    } else {

        try {


            /* -----------------------------------------
               START TRANSACTION
            ----------------------------------------- */

            $pdo->beginTransaction();


            /* -----------------------------------------
               UPDATE APPLICATION STATUS
            ----------------------------------------- */

            $updateStmt = $pdo->prepare(
                '
                UPDATE applications
                SET status = ?
                WHERE application_id = ?
                '
            );

            $updateStmt->execute([
                $newStatus,
                $applicationId
            ]);


            /* -----------------------------------------
               PREPARE NOTIFICATION CONTENT
            ----------------------------------------- */

            $notificationTitle = '';

            $notificationMessage = '';

            $notificationType = 'application';


            switch ($newStatus) {


                case 'shortlisted':

                    $notificationTitle =
                        'Application Shortlisted';

                    $notificationMessage =
                        'Congratulations! Your application for "' .
                        $application['title'] .
                        '" at ' .
                        $application['company_name'] .
                        ' has been shortlisted.';

                    $notificationType =
                        'application';

                    break;


                case 'interview':

                    $notificationTitle =
                        'Interview Opportunity';

                    $notificationMessage =
                        'Great news! You have been selected for an interview for "' .
                        $application['title'] .
                        '" at ' .
                        $application['company_name'] .
                        '. Please check with the employer for further details.';

                    $notificationType =
                        'interview';

                    break;


                case 'selected':

                    $notificationTitle =
                        'Congratulations! You Have Been Selected';

                    $notificationMessage =
                        'Congratulations! You have been selected for "' .
                        $application['title'] .
                        '" at ' .
                        $application['company_name'] .
                        '.';

                    $notificationType =
                        'selected';

                    break;


                case 'rejected':

                    $notificationTitle =
                        'Application Status Updated';

                    $notificationMessage =
                        'Your application for "' .
                        $application['title'] .
                        '" at ' .
                        $application['company_name'] .
                        ' was not selected at this time.';

                    $notificationType =
                        'rejected';

                    break;


                case 'submitted':

                default:

                    $notificationTitle =
                        'Application Status Updated';

                    $notificationMessage =
                        'The status of your application for "' .
                        $application['title'] .
                        '" at ' .
                        $application['company_name'] .
                        ' has been updated.';

                    $notificationType =
                        'application';

                    break;
            }


            /* -----------------------------------------
               CREATE STUDENT NOTIFICATION
            ----------------------------------------- */

            $notificationStmt = $pdo->prepare(
                '
                INSERT INTO notifications
                    (
                        user_id,
                        title,
                        message,
                        type
                    )
                VALUES
                    (?, ?, ?, ?)
                '
            );

            $notificationStmt->execute([
                (int) $application['user_id'],
                $notificationTitle,
                $notificationMessage,
                $notificationType
            ]);


            /* -----------------------------------------
               COMMIT TRANSACTION
            ----------------------------------------- */

            $pdo->commit();


            $success =
                'Application status updated successfully.';


            /* -----------------------------------------
               REFRESH APPLICATION DATA
            ----------------------------------------- */

            $application['status'] =
                $newStatus;

            $currentStatus =
                $newStatus;


        } catch (PDOException $e) {


            if ($pdo->inTransaction()) {

                $pdo->rollBack();
            }


            $error =
                'Application status could not be updated. Please try again.';
        }
    }
}


/* =========================================
   STATUS CLASS HELPER
========================================= */

function getStatusClass(string $status): string
{
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
        Update Application | CareerBridge
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

</head>


<body>


<div class="app-layout">


    <!-- SIDEBAR -->

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

                <span>⌂</span>

                Dashboard

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



    <!-- MAIN CONTENT -->

    <main class="main-content">


        <!-- PAGE HEADER -->

        <div class="page-header">


            <div>

                <p class="breadcrumb">

                    EMPLOYER PORTAL / APPLICATIONS / UPDATE

                </p>


                <h1>
                    Update Application
                </h1>


                <p class="page-subtitle">

                    Review the applicant and update their application status.

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
                        Employer
                    </span>

                </div>


            </div>


        </div>



        <!-- ERROR -->

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



        <!-- SUCCESS -->

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



        <!-- APPLICATION DETAILS -->

        <section class="content-card">


            <div class="section-heading">


                <div>

                    <p class="section-label">
                        APPLICANT
                    </p>


                    <h2>

                        <?= htmlspecialchars(
                            $application['full_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </h2>


                    <p>

                        <?= htmlspecialchars(
                            $application['email'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </p>

                </div>


                <div class="section-icon">
                    👨‍🎓
                </div>


            </div>



            <div class="application-meta">


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



                <div>

                    <span class="meta-label">
                        OPPORTUNITY
                    </span>

                    <strong>

                        <?= htmlspecialchars(
                            $application['title'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </strong>

                </div>



                <div>

                    <span class="meta-label">
                        APPLIED ON
                    </span>

                    <strong>

                        <?= htmlspecialchars(
                            date(
                                'd M Y',
                                strtotime(
                                    $application['applied_at']
                                )
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </strong>

                </div>


            </div>


        </section>



        <!-- UPDATE STATUS -->

        <form
            method="POST"
            class="profile-form"
        >


            <section class="content-card">


                <div class="section-heading">


                    <div>

                        <p class="section-label">
                            APPLICATION STATUS
                        </p>


                        <h2>
                            Update Status
                        </h2>


                        <p>

                            Changing the status will automatically notify the student.

                        </p>

                    </div>


                    <div class="section-icon">
                        🔄
                    </div>


                </div>



                <div class="form-group form-full">


                    <label for="status">

                        Application Status

                    </label>


                    <select
                        id="status"
                        name="status"
                        required
                    >


                        <?php foreach ($allowedStatuses as $status): ?>


                            <option
                                value="<?= htmlspecialchars(
                                    $status,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"

                                <?= $currentStatus === $status
                                    ? 'selected'
                                    : ''
                                ?>

                            >

                                <?= htmlspecialchars(
                                    ucfirst($status),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </option>


                        <?php endforeach; ?>


                    </select>


                </div>



                <!-- CURRENT STATUS -->

                <div class="form-group form-full">


                    <p class="form-help">

                        Current Status:

                        <span
                            class="status-badge <?= htmlspecialchars(
                                getStatusClass($currentStatus),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                            <?= htmlspecialchars(
                                ucfirst($currentStatus),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </span>

                    </p>


                </div>


            </section>



            <!-- ACTIONS -->

            <div class="form-actions">


                <a
                    href="applications.php"
                    class="btn btn-secondary"
                >

                    ← Back to Applications

                </a>


                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    Update Status →

                </button>


            </div>


        </form>



        <!-- FOOTER -->

        <footer class="page-footer">

            © <?= date('Y') ?>
            CareerBridge — University Career Management Platform

        </footer>


    </main>


</div>


</body>

</html>