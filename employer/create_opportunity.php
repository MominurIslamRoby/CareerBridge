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
   INITIALIZE FORM VARIABLES
========================================= */

$error = '';

$title = '';

$opportunityType = '';

$description = '';

$location = '';

$duration = '';

$deadline = '';

$status = 'open';


/* =========================================
   ALLOWED VALUES
========================================= */

$allowedTypes = [
    'job',
    'internship',
    'training',
    'part-time'
];

$allowedStatuses = [
    'open',
    'closed'
];


/* =========================================
   HANDLE FORM SUBMISSION
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /* -----------------------------------------
       GET FORM DATA
    ----------------------------------------- */

    $title = trim(
        $_POST['title'] ?? ''
    );

    $opportunityType = strtolower(
        trim(
            $_POST['opportunity_type'] ?? ''
        )
    );

    $description = trim(
        $_POST['description'] ?? ''
    );

    $location = trim(
        $_POST['location'] ?? ''
    );

    $duration = trim(
        $_POST['duration'] ?? ''
    );

    $deadline = trim(
        $_POST['deadline'] ?? ''
    );

    $status = strtolower(
        trim(
            $_POST['status'] ?? 'open'
        )
    );


    /* -----------------------------------------
       VALIDATE REQUIRED FIELDS
    ----------------------------------------- */

    if ($title === '') {

        $error =
            'Please enter an opportunity title.';

    } elseif ($opportunityType === '') {

        $error =
            'Please select an opportunity type.';

    } elseif (!in_array(
        $opportunityType,
        $allowedTypes,
        true
    )) {

        $error =
            'Invalid opportunity type selected.';

    } elseif ($description === '') {

        $error =
            'Please enter an opportunity description.';

    } elseif (!in_array(
        $status,
        $allowedStatuses,
        true
    )) {

        $error =
            'Invalid opportunity status selected.';
    }


    /* -----------------------------------------
       VALIDATE DEADLINE
    ----------------------------------------- */

    if ($error === '' && $deadline !== '') {

        $deadlineTimestamp = strtotime($deadline);

        if ($deadlineTimestamp === false) {

            $error =
                'Please enter a valid application deadline.';

        } elseif (
            $deadlineTimestamp < strtotime('today')
        ) {

            $error =
                'The application deadline cannot be in the past.';
        }
    }


    /* -----------------------------------------
       CREATE OPPORTUNITY
    ----------------------------------------- */

    if ($error === '') {

        try {

            $insertStmt = $pdo->prepare(
                '
                INSERT INTO opportunities
                    (
                        employer_id,
                        title,
                        opportunity_type,
                        description,
                        location,
                        duration,
                        deadline,
                        status
                    )
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?)
                '
            );

            $insertStmt->execute([
                $employerId,
                $title,
                $opportunityType,
                $description,
                $location !== ''
                    ? $location
                    : null,
                $duration !== ''
                    ? $duration
                    : null,
                $deadline !== ''
                    ? $deadline
                    : null,
                $status
            ]);


            header(
                'Location: opportunities.php?created=1'
            );

            exit;


        } catch (PDOException $e) {

            $error =
                'The opportunity could not be created. Please try again.';
        }
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
        Create Opportunity | CareerBridge
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


            <a
                href="opportunities.php"
                class="active"
            >

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


        <!-- =====================================
             PAGE HEADER
        ====================================== -->

        <div class="page-header">


            <div>

                <p class="breadcrumb">

                    EMPLOYER PORTAL / OPPORTUNITIES / CREATE

                </p>


                <h1>
                    Create Opportunity
                </h1>


                <p class="page-subtitle">

                    Post a new career opportunity and connect with talented students.

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
                        Employer
                    </span>

                </div>


            </div>


        </div>



        <!-- =====================================
             ERROR MESSAGE
        ====================================== -->

        <?php if ($error !== ''): ?>


            <div class="alert alert-error">


                <i class="fa-solid fa-triangle-exclamation"></i>


                <div>

                    <strong>
                        Error
                    </strong>


                    <span>

                        <?= htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </span>

                </div>


            </div>


        <?php endif; ?>



        <!-- =====================================
             CREATE OPPORTUNITY FORM
        ====================================== -->

        <form
            method="POST"
            class="profile-form"
        >


            <!-- =====================================
                 BASIC INFORMATION
            ====================================== -->

            <section class="content-card">


                <div class="section-heading">


                    <div>

                        <p class="section-label">
                            OPPORTUNITY INFORMATION
                        </p>


                        <h2>
                            Basic Details
                        </h2>


                        <p>

                            Provide the essential information about this opportunity.

                        </p>


                    </div>


                    <div class="section-icon">

                        <i class="fa-solid fa-briefcase"></i>

                    </div>


                </div>



                <div class="form-grid">


                    <!-- TITLE -->

                    <div class="form-group form-full">


                        <label for="title">

                            Opportunity Title

                        </label>


                        <input
                            type="text"
                            id="title"
                            name="title"
                            required
                            maxlength="255"
                            placeholder="e.g. Software Engineering Intern"
                            value="<?= htmlspecialchars(
                                $title,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >


                    </div>



                    <!-- TYPE -->

                    <div class="form-group">


                        <label for="opportunity_type">

                            Opportunity Type

                        </label>


                        <select
                            id="opportunity_type"
                            name="opportunity_type"
                            required
                        >


                            <option value="">

                                Select Type

                            </option>


                            <?php foreach (
                                $allowedTypes as $type
                            ): ?>


                                <option
                                    value="<?= htmlspecialchars(
                                        $type,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"

                                    <?= $opportunityType === $type
                                        ? 'selected'
                                        : ''
                                    ?>

                                >

                                    <?= htmlspecialchars(
                                        ucwords(
                                            str_replace(
                                                '-',
                                                ' ',
                                                $type
                                            )
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </option>


                            <?php endforeach; ?>


                        </select>


                    </div>



                    <!-- STATUS -->

                    <div class="form-group">


                        <label for="status">

                            Opportunity Status

                        </label>


                        <select
                            id="status"
                            name="status"
                            required
                        >


                            <option
                                value="open"

                                <?= $status === 'open'
                                    ? 'selected'
                                    : ''
                                ?>

                            >

                                Open

                            </option>


                            <option
                                value="closed"

                                <?= $status === 'closed'
                                    ? 'selected'
                                    : ''
                                ?>

                            >

                                Closed

                            </option>


                        </select>


                    </div>


                </div>


            </section>



            <!-- =====================================
                 DESCRIPTION
            ====================================== -->

            <section class="content-card">


                <div class="section-heading">


                    <div>

                        <p class="section-label">
                            DESCRIPTION
                        </p>


                        <h2>
                            Opportunity Description
                        </h2>


                        <p>

                            Describe the role, responsibilities, requirements,
                            and what students can expect.

                        </p>


                    </div>


                    <div class="section-icon">

                        <i class="fa-solid fa-file-lines"></i>

                    </div>


                </div>



                <div class="form-group form-full">


                    <label for="description">

                        Full Description

                    </label>


                    <textarea
                        id="description"
                        name="description"
                        rows="10"
                        required
                        placeholder="Describe the opportunity, responsibilities, qualifications, required skills, and other important details..."
                    ><?= htmlspecialchars(
                        $description,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?></textarea>


                    <p class="form-help">

                        Include enough information to help students understand
                        the opportunity before applying.

                    </p>


                </div>


            </section>



            <!-- =====================================
                 ADDITIONAL DETAILS
            ====================================== -->

            <section class="content-card">


                <div class="section-heading">


                    <div>

                        <p class="section-label">
                            ADDITIONAL DETAILS
                        </p>


                        <h2>
                            Location & Duration
                        </h2>


                        <p>

                            Add optional details to provide more context
                            about the opportunity.

                        </p>


                    </div>


                    <div class="section-icon">

                        <i class="fa-solid fa-location-dot"></i>

                    </div>


                </div>



                <div class="form-grid">


                    <!-- LOCATION -->

                    <div class="form-group">


                        <label for="location">

                            Location

                        </label>


                        <input
                            type="text"
                            id="location"
                            name="location"
                            maxlength="255"
                            placeholder="e.g. Dhaka / Remote"
                            value="<?= htmlspecialchars(
                                $location,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >


                    </div>



                    <!-- DURATION -->

                    <div class="form-group">


                        <label for="duration">

                            Duration

                        </label>


                        <input
                            type="text"
                            id="duration"
                            name="duration"
                            maxlength="100"
                            placeholder="e.g. 3 Months"
                            value="<?= htmlspecialchars(
                                $duration,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >


                    </div>



                    <!-- DEADLINE -->

                    <div class="form-group form-full">


                        <label for="deadline">

                            Application Deadline

                        </label>


                        <input
                            type="date"
                            id="deadline"
                            name="deadline"
                            min="<?= date('Y-m-d') ?>"
                            value="<?= htmlspecialchars(
                                $deadline,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >


                        <p class="form-help">

                            Leave empty if there is no specific application deadline.

                        </p>


                    </div>


                </div>


            </section>



            <!-- =====================================
                 FORM ACTIONS
            ====================================== -->

            <div class="form-actions">


                <a
                    href="opportunities.php"
                    class="btn btn-secondary"
                >

                    <i class="fa-solid fa-arrow-left"></i>

                    Cancel

                </a>


                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    <i class="fa-solid fa-plus"></i>

                    Create Opportunity

                </button>


            </div>


        </form>



        <!-- =====================================
             FOOTER
        ====================================== -->

        <footer class="page-footer">

            &copy; <?= date('Y') ?>
            CareerBridge - The Ultimate Career Management Platform

        </footer>


    </main>


</div>


</body>

</html>