<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('administrator');

$user = currentUser();


/* =========================================
   INITIALIZE STATISTICS
========================================= */

$totalStudents = 0;

$totalUniversities = 0;

$totalDepartments = 0;

$recentStudentsCount = 0;


/* =========================================
   GET STUDENT STATISTICS
========================================= */

$statsStmt = $pdo->query(
    '
    SELECT

        COUNT(*) AS total_students,

        COUNT(
            DISTINCT NULLIF(
                university_name,
                ""
            )
        ) AS total_universities,

        COUNT(
            DISTINCT NULLIF(
                department,
                ""
            )
        ) AS total_departments,

        SUM(
            CASE
                WHEN created_at >= DATE_SUB(
                    NOW(),
                    INTERVAL 30 DAY
                )
                THEN 1
                ELSE 0
            END
        ) AS recent_students

    FROM students
    '
);

$stats = $statsStmt->fetch();


if ($stats) {

    $totalStudents =
        (int) (
            $stats['total_students']
            ?? 0
        );

    $totalUniversities =
        (int) (
            $stats['total_universities']
            ?? 0
        );

    $totalDepartments =
        (int) (
            $stats['total_departments']
            ?? 0
        );

    $recentStudentsCount =
        (int) (
            $stats['recent_students']
            ?? 0
        );
}


/* =========================================
   GET ALL STUDENTS
========================================= */

$studentsStmt = $pdo->query(
    '
    SELECT

        s.student_id,

        s.student_id_number,

        s.university_name,

        s.department,

        s.academic_level,

        s.created_at,

        u.user_id,

        u.full_name,

        u.email,

        u.role

    FROM students s

    INNER JOIN users u
        ON u.user_id = s.user_id

    ORDER BY
        s.created_at DESC
    '
);

$students = $studentsStmt->fetchAll();


/* =========================================
   HELPER: FORMAT DATE
========================================= */

function formatStudentDate(
    ?string $date
): string {

    if (empty($date)) {

        return 'Not specified';
    }


    $timestamp = strtotime($date);


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
        Students | CareerBridge
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


        <!-- BRAND -->

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

            MAIN MENU

        </p>



        <nav class="sidebar-nav">


            <!-- DASHBOARD -->

            <a href="dashboard.php">


                <span>
                    ⌂
                </span>


                Dashboard


            </a>



            <!-- STUDENTS -->

            <a
                href="students.php"
                class="active"
            >


                <span>
                    🎓
                </span>


                Students


            </a>



            <!-- EMPLOYERS -->

            <a href="employers.php">


                <span>
                    🏢
                </span>


                Employers


            </a>



            <!-- OPPORTUNITIES -->

            <a href="opportunities.php">


                <span>
                    💼
                </span>


                Opportunities


            </a>



            <!-- APPLICATIONS -->

            <a href="applications.php">


                <span>
                    📋
                </span>


                Applications


            </a>


        </nav>



        <!-- LOGOUT -->

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

                    ADMINISTRATOR PORTAL / STUDENTS

                </p>


                <h1>

                    Students

                </h1>


                <p class="page-subtitle">

                    Manage and monitor registered students
                    within the CareerBridge platform.

                </p>


            </div>



            <!-- ADMIN USER CARD -->

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
             STATISTICS
        ====================================== -->

        <section class="stats-grid">


            <!-- TOTAL STUDENTS -->

            <div class="stat-card">


                <div class="stat-icon">

                    🎓

                </div>


                <div>


                    <p>
                        Total Students
                    </p>


                    <h2>

                        <?= $totalStudents ?>

                    </h2>


                </div>


            </div>



            <!-- UNIVERSITIES -->

            <div class="stat-card">


                <div class="stat-icon">

                    🏫

                </div>


                <div>


                    <p>
                        Universities
                    </p>


                    <h2>

                        <?= $totalUniversities ?>

                    </h2>


                </div>


            </div>



            <!-- DEPARTMENTS -->

            <div class="stat-card">


                <div class="stat-icon">

                    📚

                </div>


                <div>


                    <p>
                        Departments
                    </p>


                    <h2>

                        <?= $totalDepartments ?>

                    </h2>


                </div>


            </div>



            <!-- RECENT STUDENTS -->

            <div class="stat-card">


                <div class="stat-icon">

                    🆕

                </div>


                <div>


                    <p>
                        Joined Last 30 Days
                    </p>


                    <h2>

                        <?= $recentStudentsCount ?>

                    </h2>


                </div>


            </div>


        </section>



        <!-- =====================================
             STUDENT LIST
        ====================================== -->

        <section class="content-card">


            <!-- SECTION HEADER -->

            <div class="section-heading">


                <div>


                    <p class="section-label">

                        REGISTERED STUDENTS

                    </p>


                    <h2>

                        All Students

                    </h2>


                    <p>

                        View information about students
                        registered on CareerBridge.

                    </p>


                </div>



                <div class="section-icon">

                    👥

                </div>


            </div>



            <!-- =====================================
                 SEARCH
            ====================================== -->

            <?php if ($students): ?>


                <div
                    class="form-group form-full"
                    style="margin-bottom: 25px;"
                >


                    <label for="studentSearch">

                        Search Students

                    </label>


                    <input
                        type="text"
                        id="studentSearch"
                        placeholder="Search by name, email, university, department or student ID..."
                    >


                </div>


            <?php endif; ?>



            <!-- =====================================
                 EMPTY STATE
            ====================================== -->

            <?php if (!$students): ?>


                <div class="empty-state">


                    <div class="empty-icon">

                        🎓

                    </div>


                    <h3>

                        No Students Found

                    </h3>


                    <p>

                        No student accounts have been
                        registered on CareerBridge yet.

                    </p>


                </div>



            <!-- =====================================
                 STUDENTS TABLE
            ====================================== -->

            <?php else: ?>


                <div class="table-responsive">


                    <table
                        class="data-table"
                        id="studentsTable"
                    >


                        <!-- TABLE HEAD -->

                        <thead>


                            <tr>


                                <th>
                                    Student
                                </th>


                                <th>
                                    Student ID
                                </th>


                                <th>
                                    University
                                </th>


                                <th>
                                    Department
                                </th>


                                <th>
                                    Academic Level
                                </th>


                                <th>
                                    Joined
                                </th>


                            </tr>


                        </thead>



                        <!-- TABLE BODY -->

                        <tbody>


                            <?php foreach (
                                $students
                                as $student
                            ): ?>


                                <tr>


                                    <!-- STUDENT -->

                                    <td>


                                        <div
                                            style="
                                                display: flex;
                                                align-items: center;
                                                gap: 12px;
                                            "
                                        >


                                            <!-- AVATAR -->

                                            <div
                                                class="user-avatar"
                                                style="
                                                    width: 38px;
                                                    height: 38px;
                                                    font-size: 14px;
                                                "
                                            >


                                                <?= strtoupper(
                                                    substr(
                                                        $student['full_name'],
                                                        0,
                                                        1
                                                    )
                                                ) ?>


                                            </div>



                                            <!-- NAME + EMAIL -->

                                            <div>


                                                <strong>


                                                    <?= htmlspecialchars(
                                                        $student['full_name'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>


                                                </strong>


                                                <br>


                                                <span
                                                    style="
                                                        font-size: 13px;
                                                        color: #6b7280;
                                                    "
                                                >


                                                    <?= htmlspecialchars(
                                                        $student['email'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>


                                                </span>


                                            </div>


                                        </div>


                                    </td>



                                    <!-- STUDENT ID -->

                                    <td>


                                        <?= htmlspecialchars(
                                            $student['student_id_number']
                                                ?? 'Not provided',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>


                                    </td>



                                    <!-- UNIVERSITY -->

                                    <td>


                                        <?= htmlspecialchars(
                                            $student['university_name']
                                                ?? 'Not specified',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>


                                    </td>



                                    <!-- DEPARTMENT -->

                                    <td>


                                        <?= htmlspecialchars(
                                            $student['department']
                                                ?? 'Not specified',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>


                                    </td>



                                    <!-- ACADEMIC LEVEL -->

                                    <td>


                                        <?= htmlspecialchars(
                                            $student['academic_level']
                                                ?? 'Not specified',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>


                                    </td>



                                    <!-- JOIN DATE -->

                                    <td>


                                        <?= htmlspecialchars(
                                            formatStudentDate(
                                                $student['created_at']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>


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


            © <?= date('Y') ?>

            CareerBridge — University Career Management Platform


        </footer>


    </main>


</div>



<!-- =====================================
     SEARCH SCRIPT
====================================== -->

<script>


const studentSearch =
    document.getElementById(
        'studentSearch'
    );


if (studentSearch) {


    studentSearch.addEventListener(
        'keyup',
        function () {


            const searchValue =
                this.value.toLowerCase();


            const table =
                document.getElementById(
                    'studentsTable'
                );


            const rows =
                table.querySelectorAll(
                    'tbody tr'
                );


            rows.forEach(
                function (row) {


                    const rowText =
                        row.textContent
                            .toLowerCase();


                    if (
                        rowText.includes(
                            searchValue
                        )
                    ) {

                        row.style.display = '';

                    } else {

                        row.style.display = 'none';

                    }


                }
            );


        }
    );


}


</script>


</body>


</html>