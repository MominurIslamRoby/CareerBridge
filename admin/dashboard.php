```php
<?php
session_start();


$conn = new mysqli(
    "localhost",
    "root",
    "",
    "careerbridge_db"
);

if ($conn->connect_error) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");



if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'administrator'
) {
    header("Location: ../auth/login.php");
    exit();
}




function getCount($conn, $table)
{
    $allowedTables = [
        "students",
        "employers",
        "opportunities",
        "applications"
    ];

    if (!in_array($table, $allowedTables)) {
        return 0;
    }

    $result = $conn->query(
        "SELECT COUNT(*) AS total FROM `$table`"
    );

    if ($result) {
        $row = $result->fetch_assoc();
        return (int)$row['total'];
    }

    return 0;
}


$totalStudents =
    getCount($conn, "students");

$totalEmployers =
    getCount($conn, "employers");

$totalOpportunities =
    getCount($conn, "opportunities");

$totalApplications =
    getCount($conn, "applications");




$statusCounts = [];

$statusQuery = $conn->query(
    "SELECT status, COUNT(*) AS total
     FROM applications
     GROUP BY status
     ORDER BY total DESC"
);

if ($statusQuery) {

    while ($row = $statusQuery->fetch_assoc()) {

        $statusCounts[$row['status']] =
            (int)$row['total'];
    }
}




$recentApplications = $conn->query(
    "SELECT
        a.application_id,
        a.status,
        a.applied_at,

        s.student_id_number,

        u.full_name AS student_name,

        o.title AS opportunity_title

     FROM applications a

     INNER JOIN students s
        ON a.student_id = s.student_id

     INNER JOIN users u
        ON s.user_id = u.user_id

     INNER JOIN opportunities o
        ON a.opportunity_id = o.opportunity_id

     ORDER BY a.applied_at DESC

     LIMIT 5"
);


$recentOpportunities = $conn->query(
    "SELECT
        o.opportunity_id,
        o.title,
        o.opportunity_type,
        o.location,
        o.deadline,
        o.status,

        e.company_name

     FROM opportunities o

     INNER JOIN employers e
        ON o.employer_id = e.employer_id

     ORDER BY o.created_at DESC

     LIMIT 5"
);


$adminName = "Administrator";

if (isset($_SESSION['user_id'])) {

    $adminId = (int)$_SESSION['user_id'];

    $adminQuery = $conn->query(
        "SELECT full_name, email
         FROM users
         WHERE user_id = $adminId
         AND role = 'administrator'
         LIMIT 1"
    );

    if (
        $adminQuery &&
        $adminQuery->num_rows > 0
    ) {

        $admin = $adminQuery->fetch_assoc();

        $adminName = $admin['full_name'];
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

<title>Admin Dashboard - CareerBridge</title>


<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f7fb;
    color: #333;
}

.sidebar {

    position: fixed;

    left: 0;
    top: 0;

    width: 240px;
    height: 100vh;

    background: #1f2937;

    color: white;

    padding: 25px 0;
}


.sidebar .logo {

    text-align: center;

    padding: 0 20px 30px;

    font-size: 22px;

    font-weight: bold;
}


.sidebar .logo span {

    display: block;

    font-size: 13px;

    font-weight: normal;

    margin-top: 5px;

    opacity: .7;
}


.sidebar a {

    display: block;

    padding: 14px 25px;

    color: white;

    text-decoration: none;

    transition: .2s;
}


.sidebar a:hover {

    background: #374151;
}


.sidebar a.active {

    background: #4b5563;

    font-weight: bold;
}


.main {

    margin-left: 240px;

    padding: 30px;
}


.header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 30px;
}


.header h1 {

    font-size: 28px;

    margin-bottom: 6px;
}


.header p {

    color: #777;

    font-size: 14px;
}


.admin-info {

    text-align: right;
}


.admin-info strong {

    display: block;

    font-size: 15px;
}


.admin-info small {

    color: #777;
}


.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 20px;

    margin-bottom: 30px;
}


.card {

    background: white;

    border-radius: 10px;

    padding: 22px;

    box-shadow:
        0 2px 10px rgba(0,0,0,.06);
}


.stat-card h3 {

    font-size: 14px;

    color: #777;

    margin-bottom: 10px;
}


.stat-number {

    font-size: 32px;

    font-weight: bold;
}


.content-grid {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 25px;

    margin-bottom: 25px;
}


.card-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 20px;
}


.card-header h2 {

    font-size: 18px;
}


.table-wrapper {

    overflow-x: auto;
}


table {

    width: 100%;

    border-collapse: collapse;
}


th {

    text-align: left;

    background: #f8f9fa;

    padding: 11px;

    font-size: 12px;

    color: #666;
}


td {

    padding: 12px 11px;

    border-bottom: 1px solid #eee;

    font-size: 13px;
}


td strong {

    display: block;

    margin-bottom: 3px;
}


td small {

    color: #888;
}


.badge {

    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: bold;

    background: #eee;
}


.badge-submitted {

    background: #e0f2fe;
}


.badge-under_review {

    background: #fef3c7;
}


.badge-shortlisted {

    background: #ede9fe;
}


.badge-interview {

    background: #dbeafe;
}


.badge-selected {

    background: #dcfce7;
}


.badge-rejected {

    background: #fee2e2;
}


.empty {

    text-align: center;

    padding: 35px 15px;

    color: #888;

    font-size: 14px;
}


@media (max-width: 1000px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .content-grid {

        grid-template-columns: 1fr;
    }
}


@media (max-width: 700px) {

    .sidebar {

        width: 200px;
    }

    .main {

        margin-left: 200px;

        padding: 20px;
    }

    .stats {

        grid-template-columns: 1fr;
    }
}

</style>

</head>


<body>


<div class="sidebar">


    <div class="logo">

        CareerBridge

        <span>
            Administrator Panel
        </span>

    </div>


    <a
        href="dashboard.php"
        class="active"
    >
        Dashboard
    </a>


    <a href="students.php">
        Students
    </a>


    <a href="employers.php">
        Employers
    </a>


    <a href="opportunities.php">
        Opportunities
    </a>


    <a href="applications.php">
        Applications
    </a>


    <a href="../auth/logout.php">
        Logout
    </a>


</div>



<div class="main">


    <div class="header">


        <div>

            <h1>
                Admin Dashboard
            </h1>

            <p>
                Welcome back, <?php
                echo htmlspecialchars($adminName);
                ?>.
            </p>

        </div>


        <div class="admin-info">

            <strong>
                <?php
                echo htmlspecialchars($adminName);
                ?>
            </strong>

            <small>
                Administrator
            </small>

        </div>


    </div>



    <div class="stats">


        <div class="card stat-card">

            <h3>
                Total Students
            </h3>

            <div class="stat-number">
                <?php
                echo $totalStudents;
                ?>
            </div>

        </div>


        <div class="card stat-card">

            <h3>
                Total Employers
            </h3>

            <div class="stat-number">
                <?php
                echo $totalEmployers;
                ?>
            </div>

        </div>


        <div class="card stat-card">

            <h3>
                Total Opportunities
            </h3>

            <div class="stat-number">
                <?php
                echo $totalOpportunities;
                ?>
            </div>

        </div>


        <div class="card stat-card">

            <h3>
                Total Applications
            </h3>

            <div class="stat-number">
                <?php
                echo $totalApplications;
                ?>
            </div>

        </div>


    </div>



    <div class="content-grid">


        <div class="card">


            <div class="card-header">

                <h2>
                    Recent Applications
                </h2>

            </div>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Student
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


                    <?php

                    if (
                        $recentApplications &&
                        $recentApplications->num_rows > 0
                    ) {

                        while (
                            $application =
                            $recentApplications->fetch_assoc()
                        ) {

                            $status =
                                $application['status'];

                            $badgeClass =
                                'badge-' .
                                str_replace(
                                    ' ',
                                    '_',
                                    $status
                                );

                    ?>


                        <tr>


                            <td>

                                <strong>

                                    <?php
                                    echo htmlspecialchars(
                                        $application[
                                            'student_name'
                                        ]
                                    );
                                    ?>

                                </strong>

                                <small>

                                    <?php
                                    echo htmlspecialchars(
                                        $application[
                                            'student_id_number'
                                        ]
                                    );
                                    ?>

                                </small>

                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $application[
                                        'opportunity_title'
                                    ]
                                );
                                ?>

                            </td>


                            <td>

                                <span
                                    class="badge
                                    <?php
                                    echo $badgeClass;
                                    ?>"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        ucfirst(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $status
                                            )
                                        )
                                    );
                                    ?>

                                </span>

                            </td>


                        </tr>


                    <?php

                        }

                    }

                    else {

                    ?>


                        <tr>

                            <td
                                colspan="3"
                                class="empty"
                            >

                                No applications found.

                            </td>

                        </tr>


                    <?php

                    }

                    ?>


                    </tbody>

                </table>

            </div>


        </div>



        <div class="card">


            <div class="card-header">

                <h2>
                    Recent Opportunities
                </h2>

            </div>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Opportunity
                            </th>

                            <th>
                                Company
                            </th>

                            <th>
                                Status
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php

                    if (
                        $recentOpportunities &&
                        $recentOpportunities->num_rows > 0
                    ) {

                        while (
                            $opportunity =
                            $recentOpportunities->fetch_assoc()
                        ) {

                    ?>


                        <tr>


                            <td>

                                <strong>

                                    <?php
                                    echo htmlspecialchars(
                                        $opportunity[
                                            'title'
                                        ]
                                    );
                                    ?>

                                </strong>

                                <small>

                                    <?php
                                    echo htmlspecialchars(
                                        ucfirst(
                                            $opportunity[
                                                'opportunity_type'
                                            ]
                                        )
                                    );
                                    ?>

                                </small>

                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $opportunity[
                                        'company_name'
                                    ]
                                );
                                ?>

                            </td>


                            <td>

                                <span class="badge">

                                    <?php
                                    echo htmlspecialchars(
                                        ucfirst(
                                            $opportunity[
                                                'status'
                                            ]
                                        )
                                    );
                                    ?>

                                </span>

                            </td>


                        </tr>


                    <?php

                        }

                    }

                    else {

                    ?>


                        <tr>

                            <td
                                colspan="3"
                                class="empty"
                            >

                                No opportunities found.

                            </td>

                        </tr>


                    <?php

                    }

                    ?>


                    </tbody>

                </table>

            </div>


        </div>


    </div>



    <div class="card">


        <div class="card-header">

            <h2>
                Application Status Overview
            </h2>

        </div>


        <?php

        if (count($statusCounts) > 0) {

        ?>

            <div class="stats">


            <?php

            foreach (
                $statusCounts
                as $status => $count
            ) {

            ?>


                <div class="card">

                    <h3>

                        <?php
                        echo htmlspecialchars(
                            ucfirst(
                                str_replace(
                                    '_',
                                    ' ',
                                    $status
                                )
                            )
                        );
                        ?>

                    </h3>

                    <div class="stat-number">

                        <?php
                        echo $count;
                        ?>

                    </div>

                </div>


            <?php

            }

            ?>


            </div>

        <?php

        }

        else {

        ?>

            <div class="empty">

                No application statistics available.

            </div>

        <?php

        }

        ?>


    </div>


</div>


</body>

</html>
```
