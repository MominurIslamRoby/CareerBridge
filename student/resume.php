<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';


/* =========================================
   AUTHORIZATION
========================================= */

requireRole('student');


/* =========================================
   GET CURRENT USER
========================================= */

$user = currentUser();

$userId = (int) $user['id'];


/* =========================================
   GET STUDENT
========================================= */

$studentStmt = $pdo->prepare(
    '
    SELECT
        student_id
    FROM students
    WHERE user_id = ?
    LIMIT 1
    '
);

$studentStmt->execute([
    $userId
]);

$student = $studentStmt->fetch();


if (!$student) {

    http_response_code(404);

    exit('Student profile not found.');
}


$studentId = (int) $student['student_id'];


/* =========================================
   GET RESUMES
========================================= */

$resumeStmt = $pdo->prepare(
    '
    SELECT
        resume_id,
        file_name,
        file_path,
        uploaded_at,
        is_primary

    FROM resumes

    WHERE student_id = ?

    ORDER BY
        is_primary DESC,
        uploaded_at DESC
    '
);

$resumeStmt->execute([
    $studentId
]);

$resumes = $resumeStmt->fetchAll();


/* =========================================
   RESUME STATISTICS
========================================= */

$totalResumes = count($resumes);

$primaryResume = null;


foreach ($resumes as $resume) {

    if ((int) $resume['is_primary'] === 1) {

        $primaryResume = $resume;

        break;
    }
}


/* =========================================
   PAGE MESSAGES
========================================= */

$successMessage = '';

$errorMessage = '';


if (isset($_GET['success'])) {

    switch ($_GET['success']) {

        case 'uploaded':

            $successMessage =
                'Resume uploaded successfully.';

            break;


        case 'deleted':

            $successMessage =
                'Resume deleted successfully.';

            break;


        case 'primary':

            $successMessage =
                'Primary resume updated successfully.';

            break;


        default:

            $successMessage =
                'Operation completed successfully.';

            break;
    }
}


if (isset($_GET['error'])) {

    switch ($_GET['error']) {

        case 'upload':

            $errorMessage =
                'Resume upload failed. Please try again.';

            break;


        case 'file':

        case 'format':

            $errorMessage =
                'Please upload a valid PDF file.';

            break;


        case 'size':

            $errorMessage =
                'The resume file is too large. Maximum size is 5 MB.';

            break;


        case 'delete':

            $errorMessage =
                'Unable to delete the resume. Please try again.';

            break;


        case 'invalid':

            $errorMessage =
                'Invalid resume request.';

            break;


        case 'notfound':

            $errorMessage =
                'Resume not found or access denied.';

            break;


        case 'primary':

            $errorMessage =
                'Unable to update the primary resume.';

            break;


        case 'student':

            $errorMessage =
                'Student profile not found.';

            break;


        case 'database':

            $errorMessage =
                'Database operation failed. Please try again.';

            break;


        case 'directory':

            $errorMessage =
                'Upload directory could not be created.';

            break;


        default:

            $errorMessage =
                'Something went wrong. Please try again.';

            break;
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
        Resume / CV | CareerBridge
    </title>


    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >


    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
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

                <i class="fa-solid fa-bridge"></i>

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

                <span>
                    <i class="fa-solid fa-house"></i>
                </span>

                Dashboard

            </a>


            <a href="profile.php">

                <span>
                    <i class="fa-solid fa-user"></i>
                </span>

                Career Profile

            </a>


            <a href="skills.php">

                <span>
                    <i class="fa-solid fa-bolt"></i>
                </span>

                My Skills

            </a>


            <a
                href="resume.php"
                class="active"
            >

                <span>
                    <i class="fa-solid fa-file-lines"></i>
                </span>

                Resume / CV

            </a>


            <a href="opportunities.php">

                <span>
                    <i class="fa-solid fa-briefcase"></i>
                </span>

                Opportunities

            </a>


            <a href="applications.php">

                <span>
                    <i class="fa-solid fa-clipboard-list"></i>
                </span>

                My Applications

            </a>


            <a href="interviews.php">

                <span>
                    <i class="fa-solid fa-calendar-check"></i>
                </span>

                My Interviews

            </a>


            <a href="notifications.php">

                <span>
                    <i class="fa-solid fa-bell"></i>
                </span>

                Notifications

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
                    STUDENT PORTAL / RESUME & CV
                </p>


                <h1>
                    Resume / CV
                </h1>


                <p class="page-subtitle">
                    Upload and manage your professional resumes
                    and CV documents.
                </p>

            </div>



            <div class="user-card">


                <div class="user-avatar">

                    <?= htmlspecialchars(
                        strtoupper(
                            substr(
                                $user['full_name'],
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
                            $user['full_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </strong>


                    <span>
                        Student
                    </span>


                </div>


            </div>


        </div>



        <!-- =====================================
             SUCCESS MESSAGE
        ====================================== -->

        <?php if ($successMessage !== ''): ?>


            <div class="alert alert-success">


                <span class="alert-icon">

                    <i class="fa-solid fa-circle-check"></i>

                </span>


                <span>

                    <?= htmlspecialchars(
                        $successMessage,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </span>


            </div>


        <?php endif; ?>



        <!-- =====================================
             ERROR MESSAGE
        ====================================== -->

        <?php if ($errorMessage !== ''): ?>


            <div class="alert alert-error">


                <span class="alert-icon">

                    <i class="fa-solid fa-circle-exclamation"></i>

                </span>


                <span>

                    <?= htmlspecialchars(
                        $errorMessage,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </span>


            </div>


        <?php endif; ?>



        <!-- =====================================
             RESUME STATISTICS
        ====================================== -->

        <section class="stats-grid">


            <div class="stat-card">


                <div class="stat-icon">

                    <i class="fa-solid fa-file-lines"></i>

                </div>


                <div>

                    <p>
                        Total Resumes
                    </p>


                    <h2>
                        <?= $totalResumes ?>
                    </h2>

                </div>


            </div>



            <div class="stat-card">


                <div class="stat-icon">

                    <i class="fa-solid fa-star"></i>

                </div>


                <div>

                    <p>
                        Primary Resume
                    </p>


                    <h2>

                        <?= $primaryResume
                            ? 'Yes'
                            : 'No' ?>

                    </h2>

                </div>


            </div>



            <div class="stat-card">


                <div class="stat-icon">

                    <i class="fa-solid fa-file-pdf"></i>

                </div>


                <div>

                    <p>
                        Accepted Format
                    </p>


                    <h2>
                        PDF
                    </h2>

                </div>


            </div>



            <div class="stat-card">


                <div class="stat-icon">

                    <?php if ($totalResumes > 0): ?>

                        <i class="fa-solid fa-circle-check"></i>

                    <?php else: ?>

                        <i class="fa-solid fa-circle-exclamation"></i>

                    <?php endif; ?>

                </div>


                <div>

                    <p>
                        Resume Status
                    </p>


                    <h2>

                        <?= $totalResumes > 0
                            ? 'Ready'
                            : 'Incomplete' ?>

                    </h2>

                </div>


            </div>


        </section>



        <!-- =====================================
             RESUME CONTENT
        ====================================== -->

        <div class="resume-layout">


            <!-- =====================================
                 UPLOAD RESUME
            ====================================== -->

            <section class="content-card upload-card">


                <div class="section-heading">


                    <div>

                        <p class="section-label">
                            UPLOAD DOCUMENT
                        </p>


                        <h2>
                            Upload Resume / CV
                        </h2>


                        <p>
                            Upload your professional resume in PDF format.
                            Maximum file size: 5 MB.
                        </p>


                    </div>


                    <div class="section-icon">

                        <i class="fa-solid fa-cloud-arrow-up"></i>

                    </div>


                </div>



                <form
                    method="POST"
                    action="upload_resume.php"
                    enctype="multipart/form-data"
                    class="resume-form"
                >


                    <div class="form-group">


                        <label for="resume">

                            <i class="fa-solid fa-file-pdf"></i>

                            Select PDF Resume / CV

                        </label>


                        <input
                            type="file"
                            id="resume"
                            name="resume"
                            accept=".pdf,application/pdf"
                            required
                        >


                        <small class="form-hint">

                            Only PDF files are accepted.
                            Maximum size is 5 MB.

                        </small>


                    </div>



                    <div class="checkbox-group">


                        <input
                            type="checkbox"
                            id="is_primary"
                            name="is_primary"
                            value="1"
                        >


                        <label for="is_primary">

                            Set this as my primary resume

                        </label>


                    </div>



                    <div class="form-actions">


                        <a
                            href="dashboard.php"
                            class="btn btn-secondary"
                        >

                            <i class="fa-solid fa-arrow-left"></i>

                            Dashboard

                        </a>


                        <button
                            type="submit"
                            class="btn btn-primary"
                        >

                            <i class="fa-solid fa-cloud-arrow-up"></i>

                            Upload Resume

                        </button>


                    </div>


                </form>


            </section>



            <!-- =====================================
                 PRIMARY RESUME
            ====================================== -->

            <section class="content-card primary-resume-card">


                <div class="section-heading">


                    <div>

                        <p class="section-label">
                            PRIMARY DOCUMENT
                        </p>


                        <h2>
                            Primary Resume
                        </h2>


                        <p>
                            Your currently selected main resume
                            for job applications.
                        </p>


                    </div>


                    <div class="section-icon">

                        <i class="fa-solid fa-star"></i>

                    </div>


                </div>



                <?php if ($primaryResume): ?>


                    <div class="primary-resume-info">


                        <div class="document-icon">

                            <i class="fa-solid fa-file-pdf"></i>

                        </div>


                        <div>


                            <h3>

                                <?= htmlspecialchars(
                                    $primaryResume['file_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </h3>


                            <p>

                                Uploaded:

                                <?= htmlspecialchars(
                                    $primaryResume['uploaded_at'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </p>


                            <span class="primary-badge">

                                <i class="fa-solid fa-star"></i>

                                Primary Resume

                            </span>


                        </div>


                    </div>



                    <div class="form-actions">


                        <a
                            href="../<?= htmlspecialchars(
                                $primaryResume['file_path'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn btn-secondary"
                        >

                            <i class="fa-solid fa-eye"></i>

                            View Resume

                        </a>


                    </div>


                <?php else: ?>


                    <div class="empty-state">


                        <div class="empty-icon">

                            <i class="fa-solid fa-file-circle-xmark"></i>

                        </div>


                        <h3>
                            No Primary Resume
                        </h3>


                        <p>
                            Upload a resume and mark it as primary
                            to use it for applications.
                        </p>


                    </div>


                <?php endif; ?>


            </section>


        </div>



        <!-- =====================================
             MY RESUMES
        ====================================== -->

        <section class="content-card resumes-card">


            <div class="section-heading">


                <div>

                    <p class="section-label">
                        DOCUMENT LIBRARY
                    </p>


                    <h2>
                        My Resumes
                    </h2>


                    <p>
                        Manage all resumes uploaded to your account.
                    </p>


                </div>


                <div class="section-icon">

                    <i class="fa-solid fa-folder-open"></i>

                </div>


            </div>



            <?php if (!$resumes): ?>


                <div class="empty-state">


                    <div class="empty-icon">

                        <i class="fa-solid fa-folder-open"></i>

                    </div>


                    <h3>
                        No Resumes Uploaded Yet
                    </h3>


                    <p>
                        Upload your first resume to start applying
                        for career opportunities.
                    </p>


                </div>


            <?php else: ?>


                <div class="resume-table-wrapper">


                    <table class="resume-table">


                        <thead>

                            <tr>

                                <th>
                                    Document
                                </th>


                                <th>
                                    Uploaded
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


                            <?php foreach ($resumes as $resume): ?>


                                <tr>


                                    <!-- DOCUMENT -->

                                    <td>


                                        <div class="document-name">


                                            <span class="file-icon">

                                                <i class="fa-solid fa-file-pdf"></i>

                                            </span>


                                            <strong>

                                                <?= htmlspecialchars(
                                                    $resume['file_name'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </strong>


                                        </div>


                                    </td>



                                    <!-- UPLOAD DATE -->

                                    <td>

                                        <i class="fa-regular fa-calendar"></i>

                                        <?= htmlspecialchars(
                                            $resume['uploaded_at'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>



                                    <!-- STATUS -->

                                    <td>


                                        <?php if (
                                            (int) $resume['is_primary'] === 1
                                        ): ?>


                                            <span class="primary-badge">

                                                <i class="fa-solid fa-star"></i>

                                                Primary

                                            </span>


                                        <?php else: ?>


                                            <span class="status-badge">

                                                <i class="fa-solid fa-file"></i>

                                                Secondary

                                            </span>


                                        <?php endif; ?>


                                    </td>



                                    <!-- ACTIONS -->

                                    <td>


                                        <div class="resume-actions">


                                            <!-- VIEW -->

                                            <a
                                                href="../<?= htmlspecialchars(
                                                    $resume['file_path'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="btn btn-secondary btn-small"
                                            >

                                                <i class="fa-solid fa-eye"></i>

                                                View

                                            </a>



                                            <!-- SET PRIMARY -->

                                            <?php if (
                                                (int) $resume['is_primary'] !== 1
                                            ): ?>


                                                <form
                                                    method="POST"
                                                    action="set_primary_resume.php"
                                                    class="set-primary-form"
                                                >


                                                    <input
                                                        type="hidden"
                                                        name="resume_id"
                                                        value="<?= (int) $resume['resume_id'] ?>"
                                                    >


                                                    <button
                                                        type="submit"
                                                        class="btn btn-primary btn-small"
                                                    >

                                                        <i class="fa-solid fa-star"></i>

                                                        Set Primary

                                                    </button>


                                                </form>


                                            <?php endif; ?>



                                            <!-- DELETE -->

                                            <form
                                                method="POST"
                                                action="delete_resume.php"
                                                class="delete-resume-form"
                                                onsubmit="return confirm('Are you sure you want to delete this resume?');"
                                            >


                                                <input
                                                    type="hidden"
                                                    name="resume_id"
                                                    value="<?= (int) $resume['resume_id'] ?>"
                                                >


                                                <button
                                                    type="submit"
                                                    class="btn-delete"
                                                >

                                                    <i class="fa-solid fa-trash"></i>

                                                    Delete

                                                </button>


                                            </form>


                                        </div>


                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        </tbody>


                    </table>


                </div>


            <?php endif; ?>


        </section>



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