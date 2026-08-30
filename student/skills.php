<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';


/* =========================================
   AUTHORIZATION
========================================= */

requireRole('student');

$user = currentUser();

$userId = (int) $user['id'];


/* =========================================
   GET STUDENT
========================================= */

$studentStmt = $pdo->prepare(
    '
    SELECT student_id
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
   GET CURRENT SKILLS
========================================= */

$currentSkillsStmt = $pdo->prepare(
    '
    SELECT
        s.skill_id,
        s.skill_name,
        ss.proficiency_level

    FROM student_skills ss

    INNER JOIN skills s
        ON s.skill_id = ss.skill_id

    WHERE ss.student_id = ?

    ORDER BY s.skill_name ASC
    '
);

$currentSkillsStmt->execute([
    $studentId
]);

$skills = $currentSkillsStmt->fetchAll();


/* =========================================
   GET AVAILABLE SKILLS
========================================= */

$availableSkillsStmt = $pdo->prepare(
    '
    SELECT
        s.skill_id,
        s.skill_name

    FROM skills s

    WHERE NOT EXISTS (

        SELECT 1

        FROM student_skills ss

        WHERE ss.skill_id = s.skill_id
        AND ss.student_id = ?

    )

    ORDER BY s.skill_name ASC
    '
);

$availableSkillsStmt->execute([
    $studentId
]);

$allSkills = $availableSkillsStmt->fetchAll();


/* =========================================
   SKILL STATISTICS
========================================= */

$totalSkills = count($skills);

$beginnerCount = 0;
$intermediateCount = 0;
$advancedCount = 0;
$expertCount = 0;


foreach ($skills as $skill) {

    switch ($skill['proficiency_level']) {

        case 'beginner':
            $beginnerCount++;
            break;

        case 'intermediate':
            $intermediateCount++;
            break;

        case 'advanced':
            $advancedCount++;
            break;

        case 'expert':
            $expertCount++;
            break;
    }
}


/* =========================================
   PAGE MESSAGES
========================================= */

$successMessage = '';
$errorMessage = '';


if (
    isset($_GET['success'])
    &&
    $_GET['success'] === '1'
) {

    $successMessage =
        'Skill added successfully.';
}


if (
    isset($_GET['deleted'])
    &&
    $_GET['deleted'] === '1'
) {

    $successMessage =
        'Skill removed successfully.';
}


if (isset($_GET['error'])) {

    switch ($_GET['error']) {

        case 'invalid':
            $errorMessage =
                'Please select a valid skill and proficiency level.';
            break;

        case 'student':
            $errorMessage =
                'Student profile could not be found.';
            break;

        case 'skill':
            $errorMessage =
                'The selected skill could not be found.';
            break;

        case 'exists':
            $errorMessage =
                'This skill is already added to your profile.';
            break;

        case 'delete':
            $errorMessage =
                'Unable to remove the skill. Please try again.';
            break;

        default:
            $errorMessage =
                'Something went wrong. Please try again.';
            break;
    }
}


/* =========================================
   HELPER: PROFICIENCY CLASS
========================================= */

function getSkillLevelClass(
    string $level
): string {

    switch ($level) {

        case 'beginner':
            return 'skill-beginner';

        case 'intermediate':
            return 'skill-intermediate';

        case 'advanced':
            return 'skill-advanced';

        case 'expert':
            return 'skill-expert';

        default:
            return 'skill-beginner';
    }
}


/* =========================================
   HELPER: PROFICIENCY ICON
========================================= */

function getSkillLevelIcon(
    string $level
): string {

    switch ($level) {

        case 'beginner':
            return '<i class="fa-solid fa-seedling"></i>';

        case 'intermediate':
            return '<i class="fa-solid fa-arrow-trend-up"></i>';

        case 'advanced':
            return '<i class="fa-solid fa-star"></i>';

        case 'expert':
            return '<i class="fa-solid fa-crown"></i>';

        default:
            return '<i class="fa-solid fa-seedling"></i>';
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
        My Skills | CareerBridge
    </title>


    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >


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


            <a
                href="skills.php"
                class="active"
            >

                <span>
                    <i class="fa-solid fa-bolt"></i>
                </span>

                My Skills

            </a>


            <a href="resume.php">

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


        <!-- PAGE HEADER -->

        <div class="page-header">


            <div>

                <p class="breadcrumb">
                    STUDENT PORTAL / MY SKILLS
                </p>


                <h1>
                    My Skills
                </h1>


                <p class="page-subtitle">
                    Manage your skills and showcase your strengths
                    to potential employers.
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



        <!-- SUCCESS MESSAGE -->

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



        <!-- ERROR MESSAGE -->

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
             SKILL STATISTICS
        ====================================== -->

        <section class="stats-grid">


            <div class="stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-bolt"></i>
                </div>

                <div>

                    <p>
                        Total Skills
                    </p>

                    <h2>
                        <?= $totalSkills ?>
                    </h2>

                </div>

            </div>



            <div class="stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-seedling"></i>
                </div>

                <div>

                    <p>
                        Beginner
                    </p>

                    <h2>
                        <?= $beginnerCount ?>
                    </h2>

                </div>

            </div>



            <div class="stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-arrow-trend-up"></i>
                </div>

                <div>

                    <p>
                        Intermediate
                    </p>

                    <h2>
                        <?= $intermediateCount ?>
                    </h2>

                </div>

            </div>



            <div class="stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-star"></i>
                </div>

                <div>

                    <p>
                        Advanced / Expert
                    </p>

                    <h2>
                        <?= $advancedCount + $expertCount ?>
                    </h2>

                </div>

            </div>


        </section>



        <!-- =====================================
             SKILLS CONTENT
        ====================================== -->

        <div class="skills-layout">


            <!-- CURRENT SKILLS -->

            <section class="content-card">


                <div class="section-heading">


                    <div>

                        <p class="section-label">
                            YOUR SKILLS
                        </p>


                        <h2>
                            Current Skills
                        </h2>


                        <p>
                            Skills currently listed on your career profile.
                        </p>

                    </div>


                    <div class="section-icon">

                        <i class="fa-solid fa-bolt"></i>

                    </div>


                </div>



                <?php if (!$skills): ?>


                    <div class="empty-state">


                        <div class="empty-icon">

                            <i class="fa-solid fa-bolt"></i>

                        </div>


                        <h3>
                            No Skills Added Yet
                        </h3>


                        <p>
                            Add your first skill to strengthen your
                            CareerBridge profile.
                        </p>


                    </div>


                <?php else: ?>


                    <div class="skills-list">


                        <?php foreach ($skills as $skill): ?>


                            <div class="skill-item">


                                <div class="skill-info">


                                    <div class="skill-icon">

                                        <i class="fa-solid fa-code"></i>

                                    </div>


                                    <div>

                                        <h3>

                                            <?= htmlspecialchars(
                                                $skill['skill_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </h3>


                                        <span>
                                            Proficiency level
                                        </span>


                                    </div>


                                </div>



                                <div class="skill-actions">


                                    <span
                                        class="skill-badge <?= htmlspecialchars(
                                            getSkillLevelClass(
                                                $skill['proficiency_level']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >

                                        <?= getSkillLevelIcon(
                                            $skill['proficiency_level']
                                        ) ?>

                                        <?= htmlspecialchars(
                                            ucfirst(
                                                $skill['proficiency_level']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </span>



                                    <form
                                        method="POST"
                                        action="delete_skill.php"
                                        class="delete-skill-form"
                                        onsubmit="return confirm('Are you sure you want to remove this skill?');"
                                    >


                                        <input
                                            type="hidden"
                                            name="skill_id"
                                            value="<?= (int) $skill['skill_id'] ?>"
                                        >


                                        <button
                                            type="submit"
                                            class="btn-delete-skill"
                                        >

                                            <i class="fa-solid fa-trash-can"></i>

                                            Remove

                                        </button>


                                    </form>


                                </div>


                            </div>


                        <?php endforeach; ?>


                    </div>


                <?php endif; ?>


            </section>



            <!-- ADD NEW SKILL -->

            <section class="content-card add-skill-card">


                <div class="section-heading">


                    <div>

                        <p class="section-label">
                            ADD SKILL
                        </p>


                        <h2>
                            Add a New Skill
                        </h2>


                        <p>
                            Select a skill and specify your proficiency level.
                        </p>

                    </div>


                    <div class="section-icon">

                        <i class="fa-solid fa-plus"></i>

                    </div>


                </div>



                <?php if (!$allSkills): ?>


                    <div class="empty-state">


                        <div class="empty-icon">

                            <i class="fa-solid fa-circle-check"></i>

                        </div>


                        <h3>
                            All Skills Added
                        </h3>


                        <p>
                            You have already added all available skills.
                        </p>


                    </div>


                <?php else: ?>


                    <form
                        method="POST"
                        action="save_skill.php"
                        class="skill-form"
                    >


                        <div class="form-group">


                            <label for="skill_id">

                                <i class="fa-solid fa-code"></i>

                                Select Skill

                            </label>


                            <select
                                id="skill_id"
                                name="skill_id"
                                required
                            >

                                <option value="">
                                    Choose a skill
                                </option>


                                <?php foreach ($allSkills as $skill): ?>


                                    <option
                                        value="<?= (int) $skill['skill_id'] ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $skill['skill_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </option>


                                <?php endforeach; ?>


                            </select>


                            <small class="form-help">
                                Choose a skill you want to add to your profile.
                            </small>


                        </div>



                        <div class="form-group">


                            <label for="proficiency_level">

                                <i class="fa-solid fa-chart-line"></i>

                                Proficiency Level

                            </label>


                            <select
                                id="proficiency_level"
                                name="proficiency_level"
                                required
                            >

                                <option value="beginner">
                                    Beginner
                                </option>

                                <option value="intermediate">
                                    Intermediate
                                </option>

                                <option value="advanced">
                                    Advanced
                                </option>

                                <option value="expert">
                                    Expert
                                </option>

                            </select>


                        </div>



                        <div class="form-actions">


                            <a
                                href="dashboard.php"
                                class="btn btn-secondary"
                            >

                                <i class="fa-solid fa-arrow-left"></i>

                                Back

                            </a>


                            <button
                                type="submit"
                                class="btn btn-primary"
                            >

                                <i class="fa-solid fa-plus"></i>

                                Add Skill

                            </button>


                        </div>


                    </form>


                <?php endif; ?>


            </section>


        </div>



        <!-- FOOTER -->

        <footer class="page-footer">

            &copy; <?= date('Y') ?>

            CareerBridge — University Career Management Platform

        </footer>


    </main>


</div>


</body>

</html>