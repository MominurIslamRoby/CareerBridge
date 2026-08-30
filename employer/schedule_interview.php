<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('employer');

$user = currentUser();

$userId = (int) ($user['id'] ?? 0);


/*
|--------------------------------------------------------------------------
| Helper Function
|--------------------------------------------------------------------------
*/

function getFirstAvailableColumn(array $columns, array $possibleColumns): ?string
{
    foreach ($possibleColumns as $column) {

        if (in_array($column, $columns, true)) {
            return $column;
        }
    }

    return null;
}


function getTableColumns(PDO $pdo, string $tableName): array
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


/*
|--------------------------------------------------------------------------
| Get Employer Information
|--------------------------------------------------------------------------
*/

try {

    $employerStmt = $pdo->prepare(
        "
        SELECT employer_id
        FROM employers
        WHERE user_id = ?
        LIMIT 1
        "
    );

    $employerStmt->execute([$userId]);

    $employer = $employerStmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    exit('Unable to load employer profile.');
}


if (!$employer) {

    exit('Employer profile not found.');
}


$employerId = (int) $employer['employer_id'];


/*
|--------------------------------------------------------------------------
| Get Application ID
|--------------------------------------------------------------------------
|
| Supports:
|
| schedule_interview.php?id=5
|
| OR
|
| schedule_interview.php?application_id=5
|
*/

$applicationId = 0;


if (isset($_GET['id'])) {

    $applicationId = (int) $_GET['id'];

} elseif (isset($_GET['application_id'])) {

    $applicationId = (int) $_GET['application_id'];
}


/*
|--------------------------------------------------------------------------
| Redirect If No Application Selected
|--------------------------------------------------------------------------
*/

if ($applicationId <= 0) {

    header('Location: applications.php?error=select_application');

    exit;
}


/*
|--------------------------------------------------------------------------
| Get Application
|--------------------------------------------------------------------------
*/

try {

    $applicationStmt = $pdo->prepare(
        "
        SELECT
            a.application_id,
            a.student_id,
            a.opportunity_id,
            a.status,

            s.student_id AS student_profile_id,

            u.user_id,
            u.full_name,
            u.email,

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

        WHERE a.application_id = ?
          AND o.employer_id = ?

        LIMIT 1
        "
    );


    $applicationStmt->execute([
        $applicationId,
        $employerId
    ]);


    $application = $applicationStmt->fetch(PDO::FETCH_ASSOC);


} catch (PDOException $e) {

    exit(
        'Unable to load application: '
        . htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        )
    );
}


/*
|--------------------------------------------------------------------------
| Validate Application
|--------------------------------------------------------------------------
*/

if (!$application) {

    exit('Application not found or access denied.');
}


/*
|--------------------------------------------------------------------------
| Check Existing Active Interview
|--------------------------------------------------------------------------
*/

$existingInterview = null;


try {

    $existingInterviewStmt = $pdo->prepare(
        "
        SELECT interview_id
        FROM interviews
        WHERE application_id = ?
        AND status IN ('scheduled', 'rescheduled')
        LIMIT 1
        "
    );


    $existingInterviewStmt->execute([
        $applicationId
    ]);


    $existingInterview =
        $existingInterviewStmt->fetch(PDO::FETCH_ASSOC);


} catch (PDOException $e) {

    $existingInterview = null;
}


/*
|--------------------------------------------------------------------------
| Initialize Variables
|--------------------------------------------------------------------------
*/

$error = '';

$success = '';

$interviewDate = '';

$interviewMode = 'online';

$interviewLocation = '';

$meetingLink = '';

$notes = '';


/*
|--------------------------------------------------------------------------
| Handle Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    $interviewDate = trim(
        $_POST['interview_date'] ?? ''
    );


    $interviewMode = trim(
        $_POST['interview_mode'] ?? ''
    );


    $interviewLocation = trim(
        $_POST['interview_location'] ?? ''
    );


    $meetingLink = trim(
        $_POST['meeting_link'] ?? ''
    );


    $notes = trim(
        $_POST['notes'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    $allowedModes = [
        'online',
        'offline'
    ];


    if ($existingInterview) {

        $error =
            'An active interview is already scheduled for this application.';


    } elseif ($interviewDate === '') {

        $error =
            'Please select an interview date and time.';


    } elseif (strtotime($interviewDate) === false) {

        $error =
            'Invalid interview date and time.';


    } elseif (strtotime($interviewDate) <= time()) {

        $error =
            'Interview date must be in the future.';


    } elseif (
        !in_array(
            $interviewMode,
            $allowedModes,
            true
        )
    ) {

        $error =
            'Invalid interview mode selected.';


    } elseif (
        $interviewMode === 'online'
        && $meetingLink === ''
    ) {

        $error =
            'Please provide the meeting link for an online interview.';


    } elseif (
        $interviewMode === 'online'
        && !filter_var(
            $meetingLink,
            FILTER_VALIDATE_URL
        )
    ) {

        $error =
            'Please enter a valid meeting URL.';


    } elseif (
        $interviewMode === 'offline'
        && $interviewLocation === ''
    ) {

        $error =
            'Please provide the interview location.';
    }


    /*
    |--------------------------------------------------------------------------
    | Create Interview
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Insert Interview
            |--------------------------------------------------------------------------
            */

            $insertInterviewStmt = $pdo->prepare(
                "
                INSERT INTO interviews
                (
                    application_id,
                    interview_date,
                    interview_mode,
                    interview_location,
                    meeting_link,
                    notes,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
                "
            );


            $insertInterviewStmt->execute([
                $applicationId,
                $interviewDate,
                $interviewMode,

                $interviewMode === 'offline'
                    ? $interviewLocation
                    : null,

                $interviewMode === 'online'
                    ? $meetingLink
                    : null,

                $notes !== ''
                    ? $notes
                    : null,

                'scheduled'
            ]);


            /*
            |--------------------------------------------------------------------------
            | Update Application Status
            |--------------------------------------------------------------------------
            */

            $updateApplicationStmt = $pdo->prepare(
                "
                UPDATE applications
                SET status = ?
                WHERE application_id = ?
                "
            );


            $updateApplicationStmt->execute([
                'interview',
                $applicationId
            ]);


            /*
            |--------------------------------------------------------------------------
            | Create Student Notification
            |--------------------------------------------------------------------------
            */

            try {

                $notificationTitle =
                    'Interview Scheduled';


                $formattedDate =
                    date(
                        'F d, Y h:i A',
                        strtotime($interviewDate)
                    );


                $notificationMessage =
                    'Great news! An interview has been scheduled for your application for "'
                    . $application['opportunity_title']
                    . '" at '
                    . $application['company_name']
                    . '. Interview date: '
                    . $formattedDate
                    . '.';


                $notificationStmt = $pdo->prepare(
                    "
                    INSERT INTO notifications
                    (
                        user_id,
                        title,
                        message,
                        type
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?
                    )
                    "
                );


                $notificationStmt->execute([
                    (int) $application['user_id'],
                    $notificationTitle,
                    $notificationMessage,
                    'interview'
                ]);

            } catch (PDOException $notificationError) {

                /*
                Notification failure should not stop
                interview creation.
                */

            }


            /*
            |--------------------------------------------------------------------------
            | Commit Transaction
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | Redirect to Interviews Page
            |--------------------------------------------------------------------------
            */

            header(
                'Location: interviews.php?success=scheduled'
            );

            exit;


        } catch (PDOException $e) {


            if ($pdo->inTransaction()) {

                $pdo->rollBack();
            }


            $error =
                'Interview could not be scheduled. Please try again.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Display Information
|--------------------------------------------------------------------------
*/

$companyName =
    $application['company_name']
    ?? ($user['full_name'] ?? 'Employer');


$displayName =
    $companyName;


$avatarLetter =
    strtoupper(
        substr(
            $displayName,
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
        Schedule Interview | CareerBridge
    </title>


    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
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


            <div class="brand-icon">
                CB
            </div>


            <div class="brand-content">

                <h1>
                    CareerBridge
                </h1>

                <span>
                    Employer Portal
                </span>

            </div>


        </div>


        <div class="sidebar-divider"></div>


        <nav class="sidebar-nav">


            <p class="nav-title">
                MAIN MENU
            </p>


            <a
                href="dashboard.php"
                class="nav-link"
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
                class="nav-link active"
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
                class="nav-link"
            >

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

    <main class="main-content">


        <!-- HEADER -->

        <header class="top-header">


            <div>


                <p class="breadcrumb">
                    EMPLOYER PORTAL / APPLICATIONS / SCHEDULE INTERVIEW
                </p>


                <h2>
                    Schedule Interview
                </h2>


                <p class="welcome-text">
                    Schedule an interview with the selected applicant.
                </p>


            </div>


            <div class="user-info">


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


        </header>



        <!-- ERROR MESSAGE -->

        <?php if ($error !== ''): ?>


            <div class="profile-alert error">

                <i class="fa-solid fa-circle-exclamation"></i>

                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>


            </div>


        <?php endif; ?>



        <!-- =====================================
             APPLICANT SUMMARY
        ====================================== -->

        <section class="dashboard-card">


            <div class="card-header">


                <div>


                    <p class="section-label">
                        SELECTED APPLICANT
                    </p>


                    <h3>

                        <?= htmlspecialchars(
                            $application['full_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </h3>


                    <p class="welcome-text">

                        <?= htmlspecialchars(
                            $application['opportunity_title'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                        —

                        <?= htmlspecialchars(
                            $application['company_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </p>


                </div>


                <span class="card-label">

                    <i class="fa-solid fa-user"></i>

                </span>


            </div>


        </section>



        <!-- =====================================
             INTERVIEW FORM
        ====================================== -->

        <?php if (!$existingInterview): ?>


            <form
                method="POST"
                class="profile-form"
            >


                <section class="dashboard-card">


                    <div class="card-header">


                        <div>


                            <p class="section-label">
                                INTERVIEW DETAILS
                            </p>


                            <h3>
                                Schedule Details
                            </h3>


                            <p class="welcome-text">
                                Provide the date, time, interview mode and other details.
                            </p>


                        </div>


                        <span class="card-label">

                            <i class="fa-solid fa-calendar-plus"></i>

                        </span>


                    </div>



                    <div class="form-grid">


                        <!-- Interview Date -->

                        <div class="form-group">


                            <label for="interview_date">

                                Interview Date & Time

                            </label>


                            <input
                                type="datetime-local"
                                id="interview_date"
                                name="interview_date"
                                value="<?= htmlspecialchars(
                                    $interviewDate,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                required
                            >


                        </div>



                        <!-- Interview Mode -->

                        <div class="form-group">


                            <label for="interview_mode">

                                Interview Mode

                            </label>


                            <select
                                id="interview_mode"
                                name="interview_mode"
                                required
                            >


                                <option
                                    value="online"
                                    <?= $interviewMode === 'online'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Online
                                </option>


                                <option
                                    value="offline"
                                    <?= $interviewMode === 'offline'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Offline
                                </option>


                            </select>


                        </div>


                    </div>



                    <div class="form-grid">


                        <!-- Meeting Link -->

                        <div class="form-group">


                            <label for="meeting_link">

                                Meeting Link (Online)

                            </label>


                            <input
                                type="url"
                                id="meeting_link"
                                name="meeting_link"
                                value="<?= htmlspecialchars(
                                    $meetingLink,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                placeholder="https://meet.google.com/..."
                            >


                        </div>



                        <!-- Location -->

                        <div class="form-group">


                            <label for="interview_location">

                                Location (Offline)

                            </label>


                            <input
                                type="text"
                                id="interview_location"
                                name="interview_location"
                                value="<?= htmlspecialchars(
                                    $interviewLocation,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                placeholder="Office address or room number"
                            >


                        </div>


                    </div>



                    <!-- Notes -->

                    <div class="form-group form-full">


                        <label for="notes">

                            Additional Notes

                        </label>


                        <textarea
                            id="notes"
                            name="notes"
                            rows="5"
                            placeholder="Add instructions or notes for the candidate..."
                        ><?= htmlspecialchars(
                            $notes,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?></textarea>


                    </div>


                </section>



                <!-- FORM ACTIONS -->

                <div class="form-actions">


                    <a
                        href="applications.php"
                        class="btn btn-secondary"
                    >

                        <i class="fa-solid fa-arrow-left"></i>

                        Back to Applications

                    </a>


                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        <i class="fa-solid fa-calendar-check"></i>

                        Schedule Interview

                    </button>


                </div>


            </form>



        <?php else: ?>


            <!-- ALREADY SCHEDULED -->


            <section class="dashboard-card">


                <div class="empty-state">


                    <div class="empty-icon">

                        <i class="fa-solid fa-calendar-check"></i>

                    </div>


                    <h3>
                        Interview Already Scheduled
                    </h3>


                    <p>
                        There is already an active interview scheduled for this application.
                    </p>


                    <a
                        href="interviews.php"
                        class="primary-button"
                    >

                        <i class="fa-solid fa-calendar-days"></i>

                        View Interviews

                    </a>


                </div>


            </section>


        <?php endif; ?>



        <!-- FOOTER -->

        <footer class="dashboard-footer">


            <span>

                &copy; <?= date('Y') ?>

                CareerBridge - The Ultimate Career Management Platform

            </span>


        </footer>


    </main>


</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const modeSelect =
            document.getElementById('interview_mode');

        const meetingLink =
            document.getElementById('meeting_link');

        const locationInput =
            document.getElementById('interview_location');


        function updateInterviewFields() {

            if (!modeSelect) {
                return;
            }


            if (modeSelect.value === 'online') {

                meetingLink.disabled = false;

                locationInput.disabled = true;

                locationInput.value = '';


            } else {

                meetingLink.disabled = true;

                meetingLink.value = '';

                locationInput.disabled = false;
            }
        }


        if (modeSelect) {

            modeSelect.addEventListener(
                'change',
                updateInterviewFields
            );

            updateInterviewFields();
        }

    }
);

</script>


</body>

</html>