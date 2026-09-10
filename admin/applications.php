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

$message = "";
$messageType = "";

$allowedStatuses = [
    "submitted",
    "under_review",
    "shortlisted",
    "interview",
    "selected",
    "rejected"
];

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_status'])
) {

    $applicationId = isset($_POST['application_id'])
        ? (int)$_POST['application_id']
        : 0;

    $newStatus = isset($_POST['status'])
        ? trim($_POST['status'])
        : "";

    if (
        $applicationId > 0 &&
        in_array($newStatus, $allowedStatuses)
    ) {

        $stmt = $conn->prepare(
            "UPDATE applications
             SET status = ?, updated_at = CURRENT_TIMESTAMP
             WHERE application_id = ?"
        );

        if ($stmt) {

            $stmt->bind_param(
                "si",
                $newStatus,
                $applicationId
            );

            if ($stmt->execute()) {

                $message =
                    "Application status updated successfully.";

                $messageType = "success";

            } else {

                $message =
                    "Failed to update application status.";

                $messageType = "error";
            }

            $stmt->close();

        } else {

            $message =
                "Unable to process status update.";

            $messageType = "error";
        }

    } else {

        $message =
            "Invalid application or status.";

        $messageType = "error";
    }
}

$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : "";

$statusFilter = isset($_GET['status'])
    ? trim($_GET['status'])
    : "";

$sql = "
    SELECT
        a.application_id,
        a.opportunity_id,
        a.student_id,
        a.resume_id,
        a.cover_letter,
        a.status,
        a.applied_at,
        a.updated_at,

        s.student_id_number,

        u.full_name AS student_name,
        u.email AS student_email,

        o.title AS opportunity_title,

        e.company_name

    FROM applications a

    INNER JOIN students s
        ON a.student_id = s.student_id

    INNER JOIN users u
        ON s.user_id = u.user_id

    INNER JOIN opportunities o
        ON a.opportunity_id = o.opportunity_id

    INNER JOIN employers e
        ON o.employer_id = e.employer_id

    WHERE 1=1
";

$params = [];
$types = "";

if ($search !== "") {

    $sql .= "
        AND (
            u.full_name LIKE ?
            OR u.email LIKE ?
            OR s.student_id_number LIKE ?
            OR o.title LIKE ?
            OR e.company_name LIKE ?
        )
    ";

    $searchValue = "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= "sssss";
}

if (
    $statusFilter !== "" &&
    in_array($statusFilter, $allowedStatuses)
) {

    $sql .= " AND a.status = ? ";

    $params[] = $statusFilter;

    $types .= "s";
}

$sql .= "
    ORDER BY a.applied_at DESC
";

$stmt = $conn->prepare($sql);

if ($stmt) {

    if (!empty($params)) {

        $stmt->bind_param(
            $types,
            ...$params
        );
    }

    $stmt->execute();

    $applications = $stmt->get_result();

} else {

    $applications = false;
}

$adminName = "Administrator";

$adminId = (int)$_SESSION['user_id'];

$adminQuery = $conn->query(
    "SELECT full_name
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

$totalApplications = 0;
$submittedCount = 0;
$underReviewCount = 0;
$shortlistedCount = 0;
$interviewCount = 0;
$selectedCount = 0;
$rejectedCount = 0;

$countQuery = $conn->query(
    "SELECT status, COUNT(*) AS total
     FROM applications
     GROUP BY status"
);

if ($countQuery) {

    while ($row = $countQuery->fetch_assoc()) {

        $count = (int)$row['total'];

        $totalApplications += $count;

        switch ($row['status']) {

            case "submitted":
                $submittedCount = $count;
                break;

            case "under_review":
                $underReviewCount = $count;
                break;

            case "shortlisted":
                $shortlistedCount = $count;
                break;

            case "interview":
                $interviewCount = $count;
                break;

            case "selected":
                $selectedCount = $count;
                break;

            case "rejected":
                $rejectedCount = $count;
                break;
        }
    }
}

function formatStatus($status)
{
    return ucfirst(
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

<title>Applications - CareerBridge</title>


<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

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

    margin-bottom: 25px;
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


.message {

    padding: 13px 16px;

    border-radius: 8px;

    margin-bottom: 20px;

    font-size: 14px;
}


.message.success {

    background: #dcfce7;

    color: #166534;

    border: 1px solid #bbf7d0;
}


.message.error {

    background: #fee2e2;

    color: #991b1b;

    border: 1px solid #fecaca;
}


.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 25px;
}


.card {

    background: white;

    border-radius: 10px;

    padding: 20px;

    box-shadow:
        0 2px 10px rgba(0,0,0,.06);
}


.stat-card h3 {

    font-size: 13px;

    color: #777;

    margin-bottom: 8px;
}


.stat-number {

    font-size: 28px;

    font-weight: bold;
}


.filter-card {

    background: white;

    border-radius: 10px;

    padding: 20px;

    margin-bottom: 25px;

    box-shadow:
        0 2px 10px rgba(0,0,0,.06);
}


.filter-form {

    display: flex;

    gap: 12px;

    align-items: center;
}


.search-input {

    flex: 1;

    padding: 11px 13px;

    border: 1px solid #ddd;

    border-radius: 7px;

    font-size: 14px;

    outline: none;
}


.search-input:focus {

    border-color: #6b7280;
}


.status-select {

    padding: 11px 13px;

    border: 1px solid #ddd;

    border-radius: 7px;

    font-size: 14px;

    background: white;

    min-width: 170px;
}


.btn {

    padding: 11px 18px;

    border: none;

    border-radius: 7px;

    cursor: pointer;

    font-size: 14px;

    text-decoration: none;

    display: inline-block;
}


.btn-primary {

    background: #1f2937;

    color: white;
}


.btn-primary:hover {

    background: #374151;
}


.btn-reset {

    background: #e5e7eb;

    color: #374151;
}


.btn-reset:hover {

    background: #d1d5db;
}


.table-card {

    background: white;

    border-radius: 10px;

    box-shadow:
        0 2px 10px rgba(0,0,0,.06);

    overflow: hidden;
}


.table-header {

    padding: 20px;

    border-bottom: 1px solid #eee;
}


.table-header h2 {

    font-size: 19px;

    margin-bottom: 5px;
}


.table-header p {

    color: #888;

    font-size: 13px;
}


.table-wrapper {

    overflow-x: auto;
}


table {

    width: 100%;

    border-collapse: collapse;

    min-width: 1000px;
}


th {

    text-align: left;

    background: #f8f9fa;

    padding: 13px 12px;

    font-size: 12px;

    color: #666;

    white-space: nowrap;
}


td {

    padding: 14px 12px;

    border-bottom: 1px solid #eee;

    font-size: 13px;

    vertical-align: middle;
}


td strong {

    display: block;

    margin-bottom: 4px;
}


td small {

    display: block;

    color: #888;
}


.badge {

    display: inline-block;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: bold;

    white-space: nowrap;
}


.badge-submitted {

    background: #e0f2fe;

    color: #0369a1;
}


.badge-under_review {

    background: #fef3c7;

    color: #92400e;
}


.badge-shortlisted {

    background: #ede9fe;

    color: #6d28d9;
}


.badge-interview {

    background: #dbeafe;

    color: #1d4ed8;
}


.badge-selected {

    background: #dcfce7;

    color: #166534;
}


.badge-rejected {

    background: #fee2e2;

    color: #991b1b;
}


.status-form {

    display: flex;

    align-items: center;

    gap: 7px;
}


.status-update-select {

    padding: 7px 8px;

    border: 1px solid #ddd;

    border-radius: 6px;

    font-size: 12px;

    background: white;
}


.update-btn {

    padding: 7px 10px;

    border: none;

    border-radius: 6px;

    background: #1f2937;

    color: white;

    cursor: pointer;

    font-size: 11px;
}


.update-btn:hover {

    background: #374151;
}


.empty {

    text-align: center;

    padding: 50px 20px;

    color: #888;

    font-size: 14px;
}


@media (max-width: 1100px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }

}


@media (max-width: 800px) {

    .sidebar {

        width: 200px;
    }

    .main {

        margin-left: 200px;

        padding: 20px;
    }

    .filter-form {

        flex-direction: column;

        align-items: stretch;
    }

    .status-select {

        width: 100%;
    }

}


@media (max-width: 600px) {

    .sidebar {

        position: relative;

        width: 100%;

        height: auto;
    }

    .main {

        margin-left: 0;
    }

    .stats {

        grid-template-columns: 1fr;
    }

    .header {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;
    }

    .admin-info {

        text-align: left;
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


    <a href="dashboard.php">
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


    <a
        href="applications.php"
        class="active"
    >
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
                Applications
            </h1>

            <p>
                Manage and review student internship applications.
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



    <?php if ($message !== "") { ?>

        <div class="message <?php echo $messageType; ?>">

            <?php
            echo htmlspecialchars($message);
            ?>

        </div>

    <?php } ?>



    <div class="stats">


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


        <div class="card stat-card">

            <h3>
                Under Review
            </h3>

            <div class="stat-number">
                <?php
                echo $underReviewCount;
                ?>
            </div>

        </div>


        <div class="card stat-card">

            <h3>
                Shortlisted
            </h3>

            <div class="stat-number">
                <?php
                echo $shortlistedCount;
                ?>
            </div>

        </div>


        <div class="card stat-card">

            <h3>
                Selected
            </h3>

            <div class="stat-number">
                <?php
                echo $selectedCount;
                ?>
            </div>

        </div>


    </div>



    <div class="filter-card">

        <form
            method="GET"
            action="applications.php"
            class="filter-form"
        >


            <input
                type="text"
                name="search"
                class="search-input"
                placeholder="Search student, email, ID, opportunity or company..."
                value="<?php
                    echo htmlspecialchars($search);
                ?>"
            >


            <select
                name="status"
                class="status-select"
            >

                <option value="">
                    All Statuses
                </option>


                <?php foreach (
                    $allowedStatuses
                    as $status
                ) { ?>

                    <option
                        value="<?php
                            echo htmlspecialchars($status);
                        ?>"
                        <?php
                        echo (
                            $statusFilter === $status
                            ? "selected"
                            : ""
                        );
                        ?>
                    >

                        <?php
                        echo htmlspecialchars(
                            formatStatus($status)
                        );
                        ?>

                    </option>

                <?php } ?>

            </select>


            <button
                type="submit"
                class="btn btn-primary"
            >
                Search
            </button>


            <a
                href="applications.php"
                class="btn btn-reset"
            >
                Reset
            </a>


        </form>

    </div>



    <div class="table-card">


        <div class="table-header">

            <h2>
                Application List
            </h2>

            <p>

                <?php

                if ($applications) {

                    echo $applications->num_rows;

                } else {

                    echo "0";
                }

                ?>

                application(s) found.

            </p>

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
                            Company
                        </th>

                        <th>
                            Applied At
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Update Status
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php

                if (
                    $applications &&
                    $applications->num_rows > 0
                ) {

                    while (
                        $application =
                        $applications->fetch_assoc()
                    ) {

                        $status =
                            $application['status'];

                        $badgeClass =
                            "badge-" .
                            str_replace(
                                " ",
                                "_",
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

                                ID:
                                <?php
                                echo htmlspecialchars(
                                    $application[
                                        'student_id_number'
                                    ]
                                );
                                ?>

                            </small>


                            <small>

                                <?php
                                echo htmlspecialchars(
                                    $application[
                                        'student_email'
                                    ]
                                );
                                ?>

                            </small>

                        </td>



                        <td>

                            <strong>

                                <?php
                                echo htmlspecialchars(
                                    $application[
                                        'opportunity_title'
                                    ]
                                );
                                ?>

                            </strong>


                            <small>

                                Opportunity ID:
                                <?php
                                echo (int)
                                    $application[
                                        'opportunity_id'
                                    ];
                                ?>

                            </small>

                        </td>



                        <td>

                            <?php
                            echo htmlspecialchars(
                                $application[
                                    'company_name'
                                ]
                            );
                            ?>

                        </td>



                        <td>

                            <?php

                            echo date(
                                "d M Y",
                                strtotime(
                                    $application[
                                        'applied_at'
                                    ]
                                )
                            );

                            ?>


                            <small>

                                <?php

                                echo date(
                                    "h:i A",
                                    strtotime(
                                        $application[
                                            'applied_at'
                                        ]
                                    )
                                );

                                ?>

                            </small>

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
                                    formatStatus($status)
                                );
                                ?>

                            </span>

                        </td>



                        <td>

                            <form
                                method="POST"
                                action="applications.php"
                                class="status-form"
                            >


                                <input
                                    type="hidden"
                                    name="application_id"
                                    value="<?php
                                        echo (int)
                                            $application[
                                                'application_id'
                                            ];
                                    ?>"
                                >


                                <select
                                    name="status"
                                    class="status-update-select"
                                >

                                    <?php foreach (
                                        $allowedStatuses
                                        as $availableStatus
                                    ) { ?>

                                        <option
                                            value="<?php
                                                echo htmlspecialchars(
                                                    $availableStatus
                                                );
                                            ?>"
                                            <?php
                                            echo (
                                                $status ===
                                                $availableStatus
                                                ? "selected"
                                                : ""
                                            );
                                            ?>
                                        >

                                            <?php
                                            echo htmlspecialchars(
                                                formatStatus(
                                                    $availableStatus
                                                )
                                            );
                                            ?>

                                        </option>

                                    <?php } ?>

                                </select>


                                <button
                                    type="submit"
                                    name="update_status"
                                    class="update-btn"
                                >
                                    Update
                                </button>


                            </form>

                        </td>


                    </tr>


                <?php

                    }

                }

                else {

                ?>


                    <tr>

                        <td
                            colspan="6"
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


</div>


</body>

</html>
```
