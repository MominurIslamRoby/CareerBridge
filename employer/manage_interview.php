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
   VALIDATE INTERVIEW ID
========================================= */

$interviewId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$interviewId) {

    http_response_code(400);

    exit('Invalid interview ID.');
}


/* =========================================
   GET INTERVIEW
   SECURITY:
   Employer can only manage interviews
   belonging to their own opportunities.
========================================= */

$interviewStmt = $pdo->prepare(
    '
    SELECT

        i.interview_id,
        i.interview_date,
        i.interview_mode,
        i.interview_location,
        i.meeting_link,
        i.notes,
        i.status,
        i.outcome,

        a.application_id,
        a.student_id,

        s.user_id AS student_user_id,

        u.full_name,
        u.email,

        o.title AS opportunity_title,

        e.company_name

    FROM interviews i

    INNER JOIN applications a
        ON a.application_id = i.application_id

    INNER JOIN students s
        ON s.student_id = a.student_id

    INNER JOIN users u
        ON u.user_id = s.user_id

    INNER JOIN opportunities o
        ON o.opportunity_id = a.opportunity_id

    INNER JOIN employers e
        ON e.employer_id = o.employer_id

    WHERE i.interview_id = ?
      AND o.employer_id = ?

    LIMIT 1
    '
);

$interviewStmt->execute([
    $interviewId,
    $employerId
]);

$interview = $interviewStmt->fetch();

if (!$interview) {

    http_response_code(404);

    exit('Interview not found or access denied.');
}


/* =========================================
   INITIALIZE VARIABLES
========================================= */

$error = '';

$success = '';


/* =========================================
   HANDLE INTERVIEW ACTION
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    $action = trim(
        $_POST['action'] ?? ''
    );


    /* =====================================
       RESCHEDULE INTERVIEW
    ====================================== */

    if ($action === 'reschedule') {


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


        $allowedModes = [
            'online',
            'offline'
        ];


        if (
            !in_array(
                $interview['status'],
                [
                    'scheduled',
                    'rescheduled'
                ],
                true
            )
        ) {

            $error =
                'This interview can no longer be rescheduled.';


        } elseif ($interviewDate === '') {

            $error =
                'Please select a new interview date and time.';


        } elseif (
            strtotime($interviewDate) <= time()
        ) {

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
                'Please provide a meeting link for the online interview.';


        } elseif (
            $interviewMode === 'online'
            && !filter_var(
                $meetingLink,
                FILTER_VALIDATE_URL
            )
        ) {

            $error =
                'Please provide a valid meeting URL.';


        } elseif (
            $interviewMode === 'offline'
            && $interviewLocation === ''
        ) {

            $error =
                'Please provide an interview location.';
        }


        if ($error === '') {

            try {

                $pdo->beginTransaction();


                /* UPDATE INTERVIEW */

                $updateInterviewStmt = $pdo->prepare(
                    '
                    UPDATE interviews

                    SET
                        interview_date = ?,
                        interview_mode = ?,
                        interview_location = ?,
                        meeting_link = ?,
                        notes = ?,
                        status = ?

                    WHERE interview_id = ?
                    '
                );

                $updateInterviewStmt->execute([
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
                    'rescheduled',
                    $interviewId
                ]);


                /* CREATE NOTIFICATION */

                $notificationTitle =
                    'Interview Rescheduled';


                $notificationMessage =
                    'Your interview for "' .
                    $interview['opportunity_title'] .
                    '" at ' .
                    $interview['company_name'] .
                    ' has been rescheduled. Please check your interview details.';


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
                    (int) $interview['student_user_id'],
                    $notificationTitle,
                    $notificationMessage,
                    'interview'
                ]);


                $pdo->commit();


                $success =
                    'Interview rescheduled successfully.';


                /* REFRESH DATA */

                $interviewStmt->execute([
                    $interviewId,
                    $employerId
                ]);

                $interview =
                    $interviewStmt->fetch();


            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {

                    $pdo->rollBack();
                }

                $error =
                    'Interview could not be rescheduled. Please try again.';
            }
        }
    }


    /* =====================================
       COMPLETE INTERVIEW
    ====================================== */

    elseif ($action === 'complete') {


        $outcome = trim(
            $_POST['outcome'] ?? ''
        );


        if (
            !in_array(
                $interview['status'],
                [
                    'scheduled',
                    'rescheduled'
                ],
                true
            )
        ) {

            $error =
                'This interview cannot be marked as completed.';


        } elseif ($outcome === '') {

            $error =
                'Please provide the interview outcome.';
        }


        if ($error === '') {

            try {

                $pdo->beginTransaction();


                /* UPDATE INTERVIEW */

                $completeStmt = $pdo->prepare(
                    '
                    UPDATE interviews

                    SET
                        status = ?,
                        outcome = ?

                    WHERE interview_id = ?
                    '
                );

                $completeStmt->execute([
                    'completed',
                    $outcome,
                    $interviewId
                ]);


                /* NOTIFICATION */

                $notificationTitle =
                    'Interview Completed';


                $notificationMessage =
                    'Your interview for "' .
                    $interview['opportunity_title'] .
                    '" at ' .
                    $interview['company_name'] .
                    ' has been completed. Please check the interview outcome.';


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
                    (int) $interview['student_user_id'],
                    $notificationTitle,
                    $notificationMessage,
                    'interview'
                ]);


                $pdo->commit();


                $success =
                    'Interview marked as completed successfully.';


                $interviewStmt->execute([
                    $interviewId,
                    $employerId
                ]);

                $interview =
                    $interviewStmt->fetch();


            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {

                    $pdo->rollBack();
                }

                $error =
                    'Interview could not be completed. Please try again.';
            }
        }
    }


    /* =====================================
       CANCEL INTERVIEW
    ====================================== */

    elseif ($action === 'cancel') {


        if (
            !in_array(
                $interview['status'],
                [
                    'scheduled',
                    'rescheduled'
                ],
                true
            )
        ) {

            $error =
                'This interview cannot be cancelled.';
        }


        if ($error === '') {

            try {

                $pdo->beginTransaction();


                /* UPDATE INTERVIEW */

                $cancelStmt = $pdo->prepare(
                    '
                    UPDATE interviews

                    SET
                        status = ?

                    WHERE interview_id = ?
                    '
                );

                $cancelStmt->execute([
                    'cancelled',
                    $interviewId
                ]);


                /* UPDATE APPLICATION STATUS */

                $applicationStmt = $pdo->prepare(
                    '
                    UPDATE applications

                    SET status = ?

                    WHERE application_id = ?
                    '
                );

                $applicationStmt->execute([
                    'shortlisted',
                    (int) $interview['application_id']
                ]);


                /* NOTIFICATION */

                $notificationTitle =
                    'Interview Cancelled';


                $notificationMessage =
                    'The interview for "' .
                    $interview['opportunity_title'] .
                    '" at ' .
                    $interview['company_name'] .
                    ' has been cancelled.';


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
                    (int) $interview['student_user_id'],
                    $notificationTitle,
                    $notificationMessage,
                    'interview'
                ]);


                $pdo->commit();


                $success =
                    'Interview cancelled successfully.';


                $interviewStmt->execute([
                    $interviewId,
                    $employerId
                ]);

                $interview =
                    $interviewStmt->fetch();


            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {

                    $pdo->rollBack();
                }

                $error =
                    'Interview could not be cancelled. Please try again.';
            }
        }
    }
}


/* =========================================
   STATUS CLASS HELPER
========================================= */

function getInterviewStatusClass(
    string $status
): string {

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


/* =========================================
   FORMAT STATUS
========================================= */

function formatInterviewStatus(
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
   FORMAT DATE
========================================= */

function formatInterviewDate(
    ?string $date
): string {

    if (empty($date)) {

        return 'Not scheduled';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {

        return 'Not scheduled';
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
        Manage Interview | CareerBridge
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

                    EMPLOYER PORTAL / APPLICATIONS / INTERVIEW

                </p>

                <h1>
                    Manage Interview
                </h1>

                <p class="page-subtitle">

                    Manage the interview schedule and outcome.

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



        <!-- INTERVIEW INFORMATION -->

        <section class="content-card">


            <div class="section-heading">

                <div>

                    <p class="section-label">
                        APPLICANT
                    </p>

                    <h2>

                        <?= htmlspecialchars(
                            $interview['full_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </h2>

                    <p>

                        <?= htmlspecialchars(
                            $interview['opportunity_title'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                        —

                        <?= htmlspecialchars(
                            $interview['company_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </p>

                </div>


                <div class="section-icon">
                    🎯
                </div>

            </div>



            <div class="application-meta">


                <div>

                    <span class="meta-label">
                        DATE & TIME
                    </span>

                    <strong>

                        <?= htmlspecialchars(
                            formatInterviewDate(
                                $interview['interview_date']
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </strong>

                </div>



                <div>

                    <span class="meta-label">
                        MODE
                    </span>

                    <strong>

                        <?= htmlspecialchars(
                            ucfirst(
                                $interview['interview_mode']
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </strong>

                </div>



                <div>

                    <span class="meta-label">
                        STATUS
                    </span>

                    <strong>

                        <?= htmlspecialchars(
                            formatInterviewStatus(
                                $interview['status']
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </strong>

                </div>


            </div>


        </section>



        <!-- ONLY ACTIVE INTERVIEWS CAN BE MANAGED -->

        <?php if (
            in_array(
                $interview['status'],
                [
                    'scheduled',
                    'rescheduled'
                ],
                true
            )
        ): ?>


            <!-- RESCHEDULE -->

            <form
                method="POST"
                class="profile-form"
            >

                <input
                    type="hidden"
                    name="action"
                    value="reschedule"
                >


                <section class="content-card">


                    <div class="section-heading">

                        <div>

                            <p class="section-label">
                                RESCHEDULE
                            </p>

                            <h2>
                                Update Interview Schedule
                            </h2>

                            <p>
                                Change the date, interview mode, or meeting details.
                            </p>

                        </div>

                        <div class="section-icon">
                            🔄
                        </div>

                    </div>



                    <div class="form-grid">


                        <div class="form-group">

                            <label for="interview_date">
                                Interview Date & Time
                            </label>

                            <input
                                type="datetime-local"
                                id="interview_date"
                                name="interview_date"
                                value="<?= htmlspecialchars(
                                    date(
                                        'Y-m-d\TH:i',
                                        strtotime(
                                            $interview['interview_date']
                                        )
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                required
                            >

                        </div>



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
                                    <?= $interview['interview_mode'] === 'online'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >

                                    Online

                                </option>


                                <option
                                    value="offline"
                                    <?= $interview['interview_mode'] === 'offline'
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


                        <div class="form-group">

                            <label for="meeting_link">
                                Meeting Link
                            </label>

                            <input
                                type="url"
                                id="meeting_link"
                                name="meeting_link"
                                value="<?= htmlspecialchars(
                                    $interview['meeting_link'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                        </div>



                        <div class="form-group">

                            <label for="interview_location">
                                Interview Location
                            </label>

                            <input
                                type="text"
                                id="interview_location"
                                name="interview_location"
                                value="<?= htmlspecialchars(
                                    $interview['interview_location'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                        </div>


                    </div>



                    <div class="form-group form-full">

                        <label for="notes">
                            Additional Notes
                        </label>

                        <textarea
                            id="notes"
                            name="notes"
                            rows="4"
                        ><?= htmlspecialchars(
                            $interview['notes'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?></textarea>

                    </div>


                </section>



                <div class="form-actions">

                    <button
                        type="submit"
                        class="btn btn-secondary"
                    >

                        🔄 Reschedule Interview

                    </button>

                </div>


            </form>



            <!-- COMPLETE INTERVIEW -->

            <form
                method="POST"
                class="profile-form"
            >

                <input
                    type="hidden"
                    name="action"
                    value="complete"
                >


                <section class="content-card">


                    <div class="section-heading">

                        <div>

                            <p class="section-label">
                                COMPLETE INTERVIEW
                            </p>

                            <h2>
                                Record Interview Outcome
                            </h2>

                            <p>
                                Add the result or feedback from the interview.
                            </p>

                        </div>

                        <div class="section-icon">
                            ✓
                        </div>

                    </div>


                    <div class="form-group form-full">

                        <label for="outcome">
                            Interview Outcome
                        </label>

                        <textarea
                            id="outcome"
                            name="outcome"
                            rows="5"
                            placeholder="Example: Candidate performed well and will proceed to the next stage."
                            required
                        ></textarea>

                    </div>


                </section>



                <div class="form-actions">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        ✓ Mark as Completed

                    </button>

                </div>


            </form>



            <!-- CANCEL INTERVIEW -->

            <form
                method="POST"
                onsubmit="return confirm('Are you sure you want to cancel this interview?');"
            >

                <input
                    type="hidden"
                    name="action"
                    value="cancel"
                >


                <div class="form-actions">

                    <a
                        href="applications.php"
                        class="btn btn-secondary"
                    >

                        ← Back to Applications

                    </a>


                    <button
                        type="submit"
                        class="btn btn-secondary"
                    >

                        ✕ Cancel Interview

                    </button>

                </div>


            </form>


        <?php else: ?>


            <!-- COMPLETED / CANCELLED -->

            <section class="content-card">


                <div class="empty-state">


                    <div class="empty-icon">

                        <?= $interview['status'] === 'completed'
                            ? '✓'
                            : '✕'
                        ?>

                    </div>


                    <h2>

                        Interview <?= htmlspecialchars(
                            formatInterviewStatus(
                                $interview['status']
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </h2>


                    <?php if (
                        !empty($interview['outcome'])
                    ): ?>


                        <p>

                            <?= nl2br(
                                htmlspecialchars(
                                    $interview['outcome'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                            ) ?>

                        </p>


                    <?php endif; ?>


                    <a
                        href="applications.php"
                        class="btn btn-primary"
                    >

                        Back to Applications

                    </a>


                </div>


            </section>


        <?php endif; ?>



        <footer class="page-footer">

            © <?= date('Y') ?>
            CareerBridge — University Career Management Platform

        </footer>


    </main>


</div>


</body>

</html>