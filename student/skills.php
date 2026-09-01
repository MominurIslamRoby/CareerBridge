<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';


/* =========================================
   SESSION / CSRF
========================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (empty($_SESSION['skills_csrf_token'])) {
    $_SESSION['skills_csrf_token'] = bin2hex(
        random_bytes(32)
    );
}


$csrfToken = $_SESSION['skills_csrf_token'];


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
   VALID PROFICIENCY LEVELS
========================================= */

$validLevels = [
    'beginner',
    'intermediate',
    'advanced',
    'expert'
];


/* =========================================
   HELPER: REDIRECT WITH MESSAGE
========================================= */

function redirectSkills(
    string $type,
    string $message
): never {

    $_SESSION['skills_flash'] = [
        'type' => $type,
        'message' => $message
    ];

    header('Location: skills.php');

    exit;
}


/* =========================================
   HELPER: FIND OR CREATE SKILL
========================================= */

function findOrCreateSkill(
    PDO $pdo,
    string $skillName
): int {

    $skillName = trim(
        preg_replace(
            '/\s+/',
            ' ',
            $skillName
        )
    );


    $findStmt = $pdo->prepare(
        '
        SELECT skill_id
        FROM skills
        WHERE LOWER(TRIM(skill_name))
              = LOWER(TRIM(?))
        LIMIT 1
        '
    );

    $findStmt->execute([
        $skillName
    ]);

    $existingSkill = $findStmt->fetch();


    if ($existingSkill) {
        return (int) $existingSkill['skill_id'];
    }


    $insertStmt = $pdo->prepare(
        '
        INSERT INTO skills (
            skill_name
        )
        VALUES (?)
        '
    );

    $insertStmt->execute([
        $skillName
    ]);


    return (int) $pdo->lastInsertId();
}


/* =========================================
   HANDLE POST ACTIONS
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken =
        $_POST['csrf_token'] ?? '';


    if (
        !is_string($submittedToken)
        ||
        !hash_equals(
            $csrfToken,
            $submittedToken
        )
    ) {

        redirectSkills(
            'error',
            'Invalid request. Please try again.'
        );
    }


    $action = $_POST['action'] ?? '';


    /* =====================================
       ADD SKILL
    ====================================== */

    if ($action === 'add_skill') {

        $skillName = trim(
            (string) (
                $_POST['skill_name'] ?? ''
            )
        );

        $proficiencyLevel = strtolower(
            trim(
                (string) (
                    $_POST['proficiency_level'] ?? ''
                )
            )
        );


        if ($skillName === '') {

            redirectSkills(
                'error',
                'Please enter a skill name.'
            );
        }


        if (mb_strlen($skillName) > 100) {

            redirectSkills(
                'error',
                'Skill name cannot exceed 100 characters.'
            );
        }


        if (
            !in_array(
                $proficiencyLevel,
                $validLevels,
                true
            )
        ) {

            redirectSkills(
                'error',
                'Please select a valid proficiency level.'
            );
        }


        try {

            $pdo->beginTransaction();


            $skillId = findOrCreateSkill(
                $pdo,
                $skillName
            );


            $checkStmt = $pdo->prepare(
                '
                SELECT 1
                FROM student_skills
                WHERE student_id = ?
                AND skill_id = ?
                LIMIT 1
                '
            );

            $checkStmt->execute([
                $studentId,
                $skillId
            ]);


            if ($checkStmt->fetch()) {

                $pdo->rollBack();

                redirectSkills(
                    'error',
                    'This skill is already listed on your profile.'
                );
            }


            $insertStmt = $pdo->prepare(
                '
                INSERT INTO student_skills (
                    student_id,
                    skill_id,
                    proficiency_level
                )
                VALUES (?, ?, ?)
                '
            );

            $insertStmt->execute([
                $studentId,
                $skillId,
                $proficiencyLevel
            ]);


            $pdo->commit();


            redirectSkills(
                'success',
                'Skill added successfully.'
            );

        } catch (Throwable $exception) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }


            redirectSkills(
                'error',
                'Unable to add the skill. Please try again.'
            );
        }
    }


    /* =====================================
       EDIT SKILL
    ====================================== */

    if ($action === 'edit_skill') {

        $oldSkillId = (int) (
            $_POST['old_skill_id'] ?? 0
        );

        $skillName = trim(
            (string) (
                $_POST['skill_name'] ?? ''
            )
        );

        $proficiencyLevel = strtolower(
            trim(
                (string) (
                    $_POST['proficiency_level'] ?? ''
                )
            )
        );


        if (
            $oldSkillId <= 0
            ||
            $skillName === ''
        ) {

            redirectSkills(
                'error',
                'Please provide valid skill information.'
            );
        }


        if (mb_strlen($skillName) > 100) {

            redirectSkills(
                'error',
                'Skill name cannot exceed 100 characters.'
            );
        }


        if (
            !in_array(
                $proficiencyLevel,
                $validLevels,
                true
            )
        ) {

            redirectSkills(
                'error',
                'Please select a valid proficiency level.'
            );
        }


        try {

            $pdo->beginTransaction();


            /* Verify ownership */

            $ownershipStmt = $pdo->prepare(
                '
                SELECT skill_id
                FROM student_skills
                WHERE student_id = ?
                AND skill_id = ?
                LIMIT 1
                '
            );

            $ownershipStmt->execute([
                $studentId,
                $oldSkillId
            ]);


            if (!$ownershipStmt->fetch()) {

                $pdo->rollBack();

                redirectSkills(
                    'error',
                    'Skill could not be found.'
                );
            }


            /*
             * Find or create the requested skill.
             *
             * This is important because we do NOT rename
             * the global skills table directly. Renaming it
             * could affect other students using the same skill.
             */

            $newSkillId = findOrCreateSkill(
                $pdo,
                $skillName
            );


            /* Same skill */

            if ($newSkillId === $oldSkillId) {

                $updateStmt = $pdo->prepare(
                    '
                    UPDATE student_skills
                    SET proficiency_level = ?
                    WHERE student_id = ?
                    AND skill_id = ?
                    '
                );

                $updateStmt->execute([
                    $proficiencyLevel,
                    $studentId,
                    $oldSkillId
                ]);

            } else {

                /*
                 * Check whether the student already
                 * has the new skill.
                 */

                $duplicateStmt = $pdo->prepare(
                    '
                    SELECT 1
                    FROM student_skills
                    WHERE student_id = ?
                    AND skill_id = ?
                    LIMIT 1
                    '
                );

                $duplicateStmt->execute([
                    $studentId,
                    $newSkillId
                ]);


                if ($duplicateStmt->fetch()) {

                    $pdo->rollBack();

                    redirectSkills(
                        'error',
                        'You already have this skill on your profile.'
                    );
                }


                /*
                 * Add the new skill association
                 */

                $insertStmt = $pdo->prepare(
                    '
                    INSERT INTO student_skills (
                        student_id,
                        skill_id,
                        proficiency_level
                    )
                    VALUES (?, ?, ?)
                    '
                );

                $insertStmt->execute([
                    $studentId,
                    $newSkillId,
                    $proficiencyLevel
                ]);


                /*
                 * Remove old association
                 */

                $deleteStmt = $pdo->prepare(
                    '
                    DELETE FROM student_skills
                    WHERE student_id = ?
                    AND skill_id = ?
                    '
                );

                $deleteStmt->execute([
                    $studentId,
                    $oldSkillId
                ]);
            }


            $pdo->commit();


            redirectSkills(
                'success',
                'Skill updated successfully.'
            );

        } catch (Throwable $exception) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }


            redirectSkills(
                'error',
                'Unable to update the skill. Please try again.'
            );
        }
    }


    /* =====================================
       DELETE SKILL
    ====================================== */

    if ($action === 'delete_skill') {

        $skillId = (int) (
            $_POST['skill_id'] ?? 0
        );


        if ($skillId <= 0) {

            redirectSkills(
                'error',
                'Invalid skill.'
            );
        }


        try {

            $deleteStmt = $pdo->prepare(
                '
                DELETE FROM student_skills
                WHERE student_id = ?
                AND skill_id = ?
                '
            );

            $deleteStmt->execute([
                $studentId,
                $skillId
            ]);


            if ($deleteStmt->rowCount() === 0) {

                redirectSkills(
                    'error',
                    'Skill could not be found.'
                );
            }


            redirectSkills(
                'success',
                'Skill removed successfully.'
            );

        } catch (Throwable $exception) {

            redirectSkills(
                'error',
                'Unable to remove the skill. Please try again.'
            );
        }
    }


    redirectSkills(
        'error',
        'Invalid action.'
    );
}


/* =========================================
   GET FLASH MESSAGE
========================================= */

$flashMessage =
    $_SESSION['skills_flash'] ?? null;

unset($_SESSION['skills_flash']);


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
   GET ALL SKILLS FOR SUGGESTIONS
========================================= */

$allSkillsStmt = $pdo->query(
    '
    SELECT
        skill_name
    FROM skills
    ORDER BY skill_name ASC
    '
);

$allSkillSuggestions =
    $allSkillsStmt->fetchAll();


/* =========================================
   SKILL STATISTICS
========================================= */

$totalSkills = count($skills);

$beginnerCount = 0;
$intermediateCount = 0;
$advancedCount = 0;
$expertCount = 0;


foreach ($skills as $skill) {

    switch (
        strtolower(
            $skill['proficiency_level']
        )
    ) {

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
   HELPER: PROFICIENCY CLASS
========================================= */

function getSkillLevelClass(
    string $level
): string {

    return match (strtolower($level)) {

        'beginner' =>
            'skill-beginner',

        'intermediate' =>
            'skill-intermediate',

        'advanced' =>
            'skill-advanced',

        'expert' =>
            'skill-expert',

        default =>
            'skill-beginner'
    };
}


/* =========================================
   HELPER: PROFICIENCY ICON
========================================= */

function getSkillLevelIcon(
    string $level
): string {

    return match (strtolower($level)) {

        'beginner' =>
            '<i class="fa-solid fa-seedling"></i>',

        'intermediate' =>
            '<i class="fa-solid fa-arrow-trend-up"></i>',

        'advanced' =>
            '<i class="fa-solid fa-star"></i>',

        'expert' =>
            '<i class="fa-solid fa-crown"></i>',

        default =>
            '<i class="fa-solid fa-seedling"></i>'
    };
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


    <!-- MAIN CSS -->

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >


    <!-- FONT AWESOME -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <style>

        /* =====================================
           STATISTICS
        ====================================== */

        .stats-grid {
            grid-template-columns:
                repeat(5, minmax(0, 1fr));
        }


        /* =====================================
           SKILLS GRID
        ====================================== */

        .skills-list {

            display: grid;

            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(230px, 1fr)
                );

            gap: 18px;

            margin-top: 22px;

        }


        /* =====================================
           SKILL CARD
        ====================================== */

        .skill-item {

            display: flex;

            flex-direction: column;

            justify-content: space-between;

            min-height: 235px;

            padding: 22px;

            border:
                1px solid rgba(
                    148,
                    163,
                    184,
                    0.25
                );

            border-radius: 18px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    0.65
                );

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease,
                border-color 0.2s ease;

        }


        .skill-item:hover {

            transform:
                translateY(-3px);

            border-color:
                rgba(
                    99,
                    102,
                    241,
                    0.4
                );

            box-shadow:
                0 12px 30px
                rgba(
                    15,
                    23,
                    42,
                    0.08
                );

        }


        /* =====================================
           SKILL HEADER
        ====================================== */

        .skill-icon {

            display: flex;

            align-items: center;

            justify-content: center;

            width: 46px;

            height: 46px;

            margin-bottom: 16px;

            border-radius: 13px;

            background:
                rgba(
                    99,
                    102,
                    241,
                    0.09
                );

            color: #4f46e5;

            font-size: 18px;

        }


        .skill-info h3 {

            margin:
                0 0 8px;

            font-size: 19px;

            font-weight: 700;

            color: #1e293b;

        }


        .skill-info p {

            margin: 0;

            font-size: 14px;

            color: #64748b;

        }


        /* =====================================
           SKILL ACTION AREA
        ====================================== */

        .skill-actions {

            display: flex;

            flex-direction: column;

            gap: 13px;

            margin-top: 24px;

        }


        /* =====================================
           PROFICIENCY BADGES
        ====================================== */

        .skill-badge {

            display: inline-flex;

            align-items: center;

            align-self: flex-start;

            gap: 7px;

            padding:
                7px 13px;

            border-radius: 999px;

            font-size: 13px;

            font-weight: 600;

        }


        .skill-beginner {

            background:
                rgba(
                    34,
                    197,
                    94,
                    0.10
                );

            color: #16a34a;

        }


        .skill-intermediate {

            background:
                rgba(
                    59,
                    130,
                    246,
                    0.10
                );

            color: #2563eb;

        }


        .skill-advanced {

            background:
                rgba(
                    139,
                    92,
                    246,
                    0.10
                );

            color: #7c3aed;

        }


        .skill-expert {

            background:
                rgba(
                    245,
                    158,
                    11,
                    0.12
                );

            color: #d97706;

        }


        /* =====================================
           CARD BUTTONS
        ====================================== */

        .skill-card-buttons {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 10px;

        }


        .btn-skill-action {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 7px;

            width: 100%;

            padding:
                10px 12px;

            border-radius: 10px;

            font-family: inherit;

            font-size: 14px;

            font-weight: 600;

            cursor: pointer;

            transition:
                all 0.2s ease;

        }


        /* EDIT BUTTON */

        .btn-edit-skill {

            border:
                1px solid
                rgba(
                    99,
                    102,
                    241,
                    0.25
                );

            background:
                rgba(
                    99,
                    102,
                    241,
                    0.07
                );

            color: #4f46e5;

        }


        .btn-edit-skill:hover {

            background: #4f46e5;

            border-color: #4f46e5;

            color: #ffffff;

        }


        /* DELETE BUTTON */

        .btn-delete-skill {

            border:
                1px solid
                rgba(
                    239,
                    68,
                    68,
                    0.25
                );

            background:
                rgba(
                    239,
                    68,
                    68,
                    0.06
                );

            color: #dc2626;

        }


        .btn-delete-skill:hover {

            background: #dc2626;

            border-color: #dc2626;

            color: #ffffff;

        }


        .btn-skill-action:hover {

            transform:
                translateY(-1px);

        }


        .delete-skill-form {
            width: 100%;
        }


        /* =====================================
           ADD SKILL CARD
        ====================================== */

        .add-skill-intro {

            display: flex;

            align-items: flex-start;

            gap: 12px;

            margin-bottom: 22px;

            padding: 15px;

            border-radius: 12px;

            background:
                rgba(
                    99,
                    102,
                    241,
                    0.06
                );

            color: #475569;

            font-size: 14px;

            line-height: 1.6;

        }


        .add-skill-intro i {

            margin-top: 3px;

            color: #4f46e5;

        }


        /* =====================================
           FORM
        ====================================== */

        .skill-form {

            display: grid;

            grid-template-columns:
                1.4fr 1fr;

            gap: 20px;

            align-items: end;

        }


        .skill-form .form-actions {

            grid-column:
                1 / -1;

            margin-top: 5px;

            padding-top: 20px;

            border-top:
                1px solid
                rgba(
                    148,
                    163,
                    184,
                    0.22
                );

        }


        .form-group {

            display: flex;

            flex-direction: column;

            gap: 8px;

        }


        .form-group label {

            font-size: 14px;

            font-weight: 600;

            color: #334155;

        }


        .form-group label i {

            margin-right: 6px;

            color: #4f46e5;

        }


        .form-group input,
        .form-group select {

            width: 100%;

            min-height: 52px;

            padding:
                0 15px;

            border:
                1px solid #cbd5e1;

            border-radius: 12px;

            outline: none;

            background: #ffffff;

            color: #334155;

            font-family: inherit;

            font-size: 15px;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;

        }


        .form-group input:focus,
        .form-group select:focus {

            border-color: #6366f1;

            box-shadow:
                0 0 0 3px
                rgba(
                    99,
                    102,
                    241,
                    0.12
                );

        }


        .form-help {

            color: #64748b;

            font-size: 12px;

        }


        /* =====================================
           MODAL
        ====================================== */

        .skill-modal {

            display: none;

            position: fixed;

            inset: 0;

            z-index: 9999;

            align-items: center;

            justify-content: center;

            padding: 20px;

            background:
                rgba(
                    15,
                    23,
                    42,
                    0.55
                );

            backdrop-filter:
                blur(5px);

        }


        .skill-modal.active {

            display: flex;

        }


        .modal-content {

            width: 100%;

            max-width: 520px;

            padding: 28px;

            border-radius: 20px;

            background: #ffffff;

            box-shadow:
                0 25px 60px
                rgba(
                    15,
                    23,
                    42,
                    0.25
                );

            animation:
                modalAppear 0.2s ease;

        }


        @keyframes modalAppear {

            from {

                opacity: 0;

                transform:
                    translateY(10px)
                    scale(0.98);

            }

            to {

                opacity: 1;

                transform:
                    translateY(0)
                    scale(1);

            }

        }


        .modal-header {

            display: flex;

            align-items: flex-start;

            justify-content: space-between;

            gap: 20px;

            margin-bottom: 24px;

        }


        .modal-header h2 {

            margin: 0 0 6px;

            color: #1e293b;

        }


        .modal-header p {

            margin: 0;

            color: #64748b;

            font-size: 14px;

        }


        .modal-close {

            display: flex;

            align-items: center;

            justify-content: center;

            width: 38px;

            height: 38px;

            border: none;

            border-radius: 10px;

            background: #f1f5f9;

            color: #64748b;

            cursor: pointer;

            font-size: 17px;

        }


        .modal-close:hover {

            background: #e2e8f0;

            color: #334155;

        }


        .modal-form {

            display: flex;

            flex-direction: column;

            gap: 18px;

        }


        .modal-actions {

            display: flex;

            justify-content: flex-end;

            gap: 12px;

            margin-top: 8px;

            padding-top: 20px;

            border-top:
                1px solid #e2e8f0;

        }


        /* =====================================
           RESPONSIVE
        ====================================== */

        @media (max-width: 1400px) {

            .stats-grid {

                grid-template-columns:
                    repeat(3, 1fr);

            }

        }


        @media (max-width: 900px) {

            .stats-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }


            .skill-form {

                grid-template-columns:
                    1fr;

            }

        }


        @media (max-width: 600px) {

            .stats-grid {

                grid-template-columns:
                    1fr;

            }


            .skills-list {

                grid-template-columns:
                    1fr;

            }


            .skill-card-buttons {

                grid-template-columns:
                    1fr;

            }


            .skill-item {

                min-height: auto;

            }


            .modal-actions {

                flex-direction: column-reverse;

            }


            .modal-actions .btn {

                width: 100%;

                justify-content: center;

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

                <img
                    src="../assets/images/CB Logo Transparent.png"
                    alt="CareerBridge Logo"
                >

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
                    Add, manage, and showcase the skills that best
                    represent your professional interests.
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
             FLASH MESSAGE
        ====================================== -->

        <?php if ($flashMessage): ?>


            <div
                class="alert <?= $flashMessage['type'] === 'success'
                    ? 'alert-success'
                    : 'alert-error' ?>"
            >

                <span class="alert-icon">

                    <i
                        class="fa-solid <?= $flashMessage['type'] === 'success'
                            ? 'fa-circle-check'
                            : 'fa-circle-exclamation' ?>"
                    ></i>

                </span>


                <span>

                    <?= htmlspecialchars(
                        $flashMessage['message'],
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

                    <p>Total Skills</p>

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

                    <p>Beginner</p>

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

                    <p>Intermediate</p>

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

                    <p>Advanced</p>

                    <h2>
                        <?= $advancedCount ?>
                    </h2>

                </div>

            </div>



            <div class="stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-crown"></i>
                </div>

                <div>

                    <p>Expert</p>

                    <h2>
                        <?= $expertCount ?>
                    </h2>

                </div>

            </div>


        </section>



        <!-- =====================================
             CURRENT SKILLS
        ====================================== -->

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
                        Manage and update the skills displayed
                        on your career profile.
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
                        Add skills based on your interests,
                        experience, and professional strengths.
                    </p>


                </div>


            <?php else: ?>


                <div class="skills-list">


                    <?php foreach ($skills as $skill): ?>


                        <article class="skill-item">


                            <div class="skill-info">


                                <div class="skill-icon">

                                    <i class="fa-solid fa-code"></i>

                                </div>


                                <h3>

                                    <?= htmlspecialchars(
                                        $skill['skill_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </h3>


                                <p>
                                    Proficiency Level
                                </p>


                            </div>



                            <div class="skill-actions">


                                <span
                                    class="skill-badge <?= getSkillLevelClass(
                                        $skill['proficiency_level']
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



                                <div class="skill-card-buttons">


                                    <!-- EDIT -->

                                    <button
                                        type="button"
                                        class="btn-skill-action btn-edit-skill"
                                        data-skill-id="<?= (int) $skill['skill_id'] ?>"
                                        data-skill-name="<?= htmlspecialchars(
                                            $skill['skill_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        data-proficiency="<?= htmlspecialchars(
                                            $skill['proficiency_level'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >

                                        <i class="fa-solid fa-pen"></i>

                                        Edit

                                    </button>



                                    <!-- DELETE -->

                                    <form
                                        method="POST"
                                        class="delete-skill-form"
                                        onsubmit="return confirm('Are you sure you want to remove this skill?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= htmlspecialchars(
                                                $csrfToken,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                        >


                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete_skill"
                                        >


                                        <input
                                            type="hidden"
                                            name="skill_id"
                                            value="<?= (int) $skill['skill_id'] ?>"
                                        >


                                        <button
                                            type="submit"
                                            class="btn-skill-action btn-delete-skill"
                                        >

                                            <i class="fa-solid fa-trash-can"></i>

                                            Remove

                                        </button>


                                    </form>


                                </div>


                            </div>


                        </article>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


        </section>



        <!-- =====================================
             ADD NEW SKILL
        ====================================== -->

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
                        Add any skill that represents your interests
                        and professional abilities.
                    </p>

                </div>


                <div class="section-icon">

                    <i class="fa-solid fa-plus"></i>

                </div>


            </div>



            <div class="add-skill-intro">

                <i class="fa-solid fa-circle-info"></i>

                <div>
                    You are free to add any relevant skill.
                    Existing skills may appear as suggestions,
                    but you can also enter a completely new skill.
                </div>

            </div>



            <form
                method="POST"
                class="skill-form"
            >


                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $csrfToken,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >


                <input
                    type="hidden"
                    name="action"
                    value="add_skill"
                >


                <!-- SKILL NAME -->

                <div class="form-group">


                    <label for="skill_name">

                        <i class="fa-solid fa-code"></i>

                        Skill Name

                    </label>


                    <input
                        type="text"
                        id="skill_name"
                        name="skill_name"
                        list="skillSuggestions"
                        placeholder="e.g. React, Graphic Design, Public Speaking"
                        maxlength="100"
                        required
                    >


                    <datalist id="skillSuggestions">

                        <?php foreach (
                            $allSkillSuggestions
                            as $suggestion
                        ): ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $suggestion['skill_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                        <?php endforeach; ?>

                    </datalist>


                    <small class="form-help">
                        Type any skill. Suggestions are optional.
                    </small>


                </div>



                <!-- PROFICIENCY -->

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



                <!-- FORM ACTIONS -->

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


        </section>



        <!-- =====================================
             FOOTER
        ====================================== -->

        <footer class="page-footer">

            &copy; <?= date('Y') ?>

            CareerBridge — University Career Management Platform

        </footer>


    </main>


</div>



<!-- =========================================
     EDIT SKILL MODAL
========================================= -->

<div
    class="skill-modal"
    id="editSkillModal"
    aria-hidden="true"
>


    <div
        class="modal-content"
        role="dialog"
        aria-modal="true"
        aria-labelledby="editSkillTitle"
    >


        <div class="modal-header">


            <div>

                <h2 id="editSkillTitle">
                    Edit Skill
                </h2>

                <p>
                    Update your skill name or proficiency level.
                </p>

            </div>


            <button
                type="button"
                class="modal-close"
                id="closeEditModal"
                aria-label="Close"
            >

                <i class="fa-solid fa-xmark"></i>

            </button>


        </div>



        <form
            method="POST"
            class="modal-form"
        >


            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $csrfToken,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >


            <input
                type="hidden"
                name="action"
                value="edit_skill"
            >


            <input
                type="hidden"
                id="edit_old_skill_id"
                name="old_skill_id"
            >


            <!-- SKILL NAME -->

            <div class="form-group">


                <label for="edit_skill_name">

                    <i class="fa-solid fa-code"></i>

                    Skill Name

                </label>


                <input
                    type="text"
                    id="edit_skill_name"
                    name="skill_name"
                    list="skillSuggestions"
                    maxlength="100"
                    required
                >


            </div>



            <!-- PROFICIENCY -->

            <div class="form-group">


                <label for="edit_proficiency_level">

                    <i class="fa-solid fa-chart-line"></i>

                    Proficiency Level

                </label>


                <select
                    id="edit_proficiency_level"
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



            <div class="modal-actions">


                <button
                    type="button"
                    class="btn btn-secondary"
                    id="cancelEdit"
                >

                    Cancel

                </button>


                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    <i class="fa-solid fa-check"></i>

                    Save Changes

                </button>


            </div>


        </form>


    </div>


</div>



<!-- =========================================
     JAVASCRIPT
========================================= -->

<script>

    const editModal =
        document.getElementById(
            'editSkillModal'
        );


    const closeEditModal =
        document.getElementById(
            'closeEditModal'
        );


    const cancelEdit =
        document.getElementById(
            'cancelEdit'
        );


    const editSkillId =
        document.getElementById(
            'edit_old_skill_id'
        );


    const editSkillName =
        document.getElementById(
            'edit_skill_name'
        );


    const editProficiency =
        document.getElementById(
            'edit_proficiency_level'
        );


    /* =====================================
       OPEN EDIT MODAL
    ====================================== */

    document
        .querySelectorAll('.btn-edit-skill')
        .forEach((button) => {

            button.addEventListener(
                'click',
                () => {

                    editSkillId.value =
                        button.dataset.skillId;


                    editSkillName.value =
                        button.dataset.skillName;


                    editProficiency.value =
                        button.dataset.proficiency;


                    editModal.classList.add(
                        'active'
                    );


                    editModal.setAttribute(
                        'aria-hidden',
                        'false'
                    );


                    editSkillName.focus();

                }
            );

        });


    /* =====================================
       CLOSE MODAL
    ====================================== */

    function closeModal() {

        editModal.classList.remove(
            'active'
        );


        editModal.setAttribute(
            'aria-hidden',
            'true'
        );

    }


    closeEditModal.addEventListener(
        'click',
        closeModal
    );


    cancelEdit.addEventListener(
        'click',
        closeModal
    );


    /* Close when clicking outside */

    editModal.addEventListener(
        'click',
        (event) => {

            if (
                event.target === editModal
            ) {

                closeModal();

            }

        }
    );


    /* Close with Escape */

    document.addEventListener(
        'keydown',
        (event) => {

            if (
                event.key === 'Escape'
            ) {

                closeModal();

            }

        }
    );

</script>


</body>

</html>