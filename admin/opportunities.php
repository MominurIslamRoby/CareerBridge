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

if (
    isset($_POST['close_opportunity']) &&
    isset($_POST['opportunity_id'])
) {

    $opportunityId = (int)$_POST['opportunity_id'];

    if ($opportunityId > 0) {

        $stmt = $conn->prepare(
            "UPDATE opportunities
             SET status = 'closed'
             WHERE opportunity_id = ?
             AND status = 'open'"
        );

        $stmt->bind_param(
            "i",
            $opportunityId
        );

        if ($stmt->execute()) {

            if ($stmt->affected_rows > 0) {

                $message =
                    "Opportunity closed successfully.";

                $messageType = "success";

            } else {

                $message =
                    "Opportunity could not be closed. It may already be closed or unavailable.";

                $messageType = "error";
            }

        } else {

            $message =
                "Failed to update the opportunity.";

            $messageType = "error";
        }

        $stmt->close();
    }
}

$search = "";

if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

if ($search !== "") {

    $searchValue = "%" . $search . "%";

    $stmt = $conn->prepare(
        "SELECT
            o.opportunity_id,
            o.employer_id,
            o.title,
            o.opportunity_type,
            o.description,
            o.responsibilities,
            o.qualifications,
            o.location,
            o.duration,
            o.deadline,
            o.status,
            o.created_at,

            e.company_name

         FROM opportunities o

         INNER JOIN employers e
             ON o.employer_id = e.employer_id

         WHERE
             o.title LIKE ?
             OR o.opportunity_type LIKE ?
             OR o.location LIKE ?
             OR o.duration LIKE ?
             OR o.status LIKE ?
             OR e.company_name LIKE ?

         ORDER BY o.created_at DESC"
    );

    $stmt->bind_param(
        "ssssss",
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue
    );

    $stmt->execute();

    $opportunities = $stmt->get_result();

} else {

    $opportunities = $conn->query(
        "SELECT
            o.opportunity_id,
            o.employer_id,
            o.title,
            o.opportunity_type,
            o.description,
            o.responsibilities,
            o.qualifications,
            o.location,
            o.duration,
            o.deadline,
            o.status,
            o.created_at,

            e.company_name

         FROM opportunities o

         INNER JOIN employers e
             ON o.employer_id = e.employer_id

         ORDER BY o.created_at DESC"
    );
}

$totalOpportunities = 0;
$openOpportunities = 0;
$closedOpportunities = 0;
$draftOpportunities = 0;
$filledOpportunities = 0;

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM opportunities"
);

if ($result) {

    $totalOpportunities =
        (int)$result->fetch_assoc()['total'];
}

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM opportunities
     WHERE status = 'open'"
);

if ($result) {

    $openOpportunities =
        (int)$result->fetch_assoc()['total'];
}

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM opportunities
     WHERE status = 'closed'"
);

if ($result) {

    $closedOpportunities =
        (int)$result->fetch_assoc()['total'];
}

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM opportunities
     WHERE status = 'draft'"
);

if ($result) {

    $draftOpportunities =
        (int)$result->fetch_assoc()['total'];
}

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM opportunities
     WHERE status = 'filled'"
);

if ($result) {

    $filledOpportunities =
        (int)$result->fetch_assoc()['total'];
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
    Opportunity Monitoring - CareerBridge
</title>

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
    padding-top: 25px;
}

.logo {
    text-align: center;
    padding: 0 20px 30px;
    font-size: 22px;
    font-weight: bold;
}

.logo span {
    display: block;
    margin-top: 5px;
    font-size: 13px;
    font-weight: normal;
    opacity: .7;
}

.sidebar a {
    display: block;
    padding: 14px 25px;
    color: white;
    text-decoration: none;
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
    margin-bottom: 7px;
}

.header p {
    color: #777;
    font-size: 14px;
}

.admin-name {
    text-align: right;
    font-size: 14px;
}

.message {
    padding: 14px 18px;
    border-radius: 7px;
    margin-bottom: 20px;
    font-size: 14px;
}

.message-success {
    background: #dcfce7;
    color: #166534;
}

.message-error {
    background: #fee2e2;
    color: #991b1b;
}

.stats {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
}

.stat-card h3 {
    color: #777;
    font-size: 13px;
    margin-bottom: 10px;
}

.stat-number {
    font-size: 28px;
    font-weight: bold;
}

.search-card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
}

.search-form {
    display: flex;
    gap: 10px;
}

.search-form input {
    flex: 1;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}

.search-form button {
    padding: 12px 22px;
    border: none;
    border-radius: 6px;
    background: #1f2937;
    color: white;
    cursor: pointer;
}

.search-form button:hover {
    background: #374151;
}

.clear-button {
    display: inline-block;
    padding: 12px 18px;
    border-radius: 6px;
    background: #e5e7eb;
    color: #333;
    text-decoration: none;
}

.table-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
    overflow-x: auto;
}

.table-header {
    padding: 20px;
    border-bottom: 1px solid #eee;
}

.table-header h2 {
    font-size: 18px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #f8f9fa;
    text-align: left;
    padding: 13px;
    font-size: 12px;
    color: #666;
    white-space: nowrap;
}

td {
    padding: 14px 13px;
    border-bottom: 1px solid #eee;
    font-size: 13px;
    vertical-align: top;
}

.title {
    font-weight: bold;
    margin-bottom: 5px;
}

.company {
    color: #666;
    font-size: 12px;
}

.description {
    max-width: 300px;
    color: #666;
    line-height: 1.4;
}

.type {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 15px;
    font-size: 11px;
    text-transform: capitalize;
    font-weight: bold;
}

.type-internship {
    background: #e0f2fe;
    color: #075985;
}

.type-job {
    background: #ede9fe;
    color: #5b21b6;
}

.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
    text-transform: capitalize;
}

.status-open {
    background: #dcfce7;
    color: #166534;
}

.status-closed {
    background: #fee2e2;
    color: #991b1b;
}

.status-draft {
    background: #f3f4f6;
    color: #4b5563;
}

.status-filled {
    background: #dbeafe;
    color: #1e40af;
}

.deadline {
    white-space: nowrap;
}

.deadline-expired {
    color: #dc2626;
    font-weight: bold;
}

.deadline-normal {
    color: #555;
}

.action-form {
    display: inline;
}

.close-button {
    border: none;
    background: #dc2626;
    color: white;
    padding: 7px 12px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 11px;
}

.close-button:hover {
    background: #b91c1c;
}

.closed-label {
    color: #888;
    font-size: 12px;
}

.empty {
    text-align: center;
    padding: 50px 20px;
    color: #888;
}

.empty h3 {
    color: #555;
    margin-bottom: 8px;
}

@media (max-width: 1200px) {

    .stats {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 900px) {

    .sidebar {
        width: 200px;
    }

    .main {
        margin-left: 200px;
        padding: 20px;
    }

    .stats {
        grid-template-columns: repeat(2, 1fr);
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

    .search-form {
        flex-direction: column;
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

    <a
        href="opportunities.php"
        class="active"
    >
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
                Opportunity Monitoring
            </h1>

            <p>
                Monitor internship and job opportunities posted by employers.
            </p>

        </div>

        <div class="admin-name">

            Administrator

        </div>

    </div>

    <?php

    if ($message !== "") {

    ?>

        <div
            class="message message-<?php
                echo htmlspecialchars(
                    $messageType
                );
            ?>"
        >

            <?php
            echo htmlspecialchars($message);
            ?>

        </div>

    <?php

    }

    ?>

    <div class="stats">

        <div class="stat-card">

            <h3>
                Total
            </h3>

            <div class="stat-number">

                <?php
                echo $totalOpportunities;
                ?>

            </div>

        </div>

        <div class="stat-card">

            <h3>
                Open
            </h3>

            <div class="stat-number">

                <?php
                echo $openOpportunities;
                ?>

            </div>

        </div>

        <div class="stat-card">

            <h3>
                Closed
            </h3>

            <div class="stat-number">

                <?php
                echo $closedOpportunities;
                ?>

            </div>

        </div>

        <div class="stat-card">

            <h3>
                Draft
            </h3>

            <div class="stat-number">

                <?php
                echo $draftOpportunities;
                ?>

            </div>

        </div>

        <div class="stat-card">

            <h3>
                Filled
            </h3>

            <div class="stat-number">

                <?php
                echo $filledOpportunities;
                ?>

            </div>

        </div>

    </div>

    <div class="search-card">

        <form
            method="GET"
            class="search-form"
        >

            <input
                type="text"
                name="search"
                placeholder="Search by title, company, type, location, duration or status..."
                value="<?php
                    echo htmlspecialchars($search);
                ?>"
            >

            <button type="submit">
                Search
            </button>

            <?php

            if ($search !== "") {

            ?>

                <a
                    href="opportunities.php"
                    class="clear-button"
                >
                    Clear
                </a>

            <?php

            }

            ?>

        </form>

    </div>

    <div class="table-card">

        <div class="table-header">

            <h2>

                <?php

                if ($search !== "") {

                    echo "Search Results";

                } else {

                    echo "All Opportunities";

                }

                ?>

            </h2>

        </div>

        <table>

            <thead>

                <tr>

                    <th>
                        Opportunity
                    </th>

                    <th>
                        Employer
                    </th>

                    <th>
                        Type
                    </th>

                    <th>
                        Location
                    </th>

                    <th>
                        Duration
                    </th>

                    <th>
                        Deadline
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Created
                    </th>

                    <th>
                        Action
                    </th>

                </tr>

            </thead>

            <tbody>

            <?php

            if (
                $opportunities &&
                $opportunities->num_rows > 0
            ) {

                while (
                    $opportunity =
                    $opportunities->fetch_assoc()
                ) {

                    $type =
                        strtolower(
                            $opportunity[
                                'opportunity_type'
                            ]
                        );

                    $status =
                        strtolower(
                            $opportunity[
                                'status'
                            ]
                        );

                    $deadlineClass =
                        "deadline-normal";

                    $deadlineDate =
                        strtotime(
                            $opportunity['deadline']
                        );

                    if (
                        $deadlineDate !== false &&
                        $deadlineDate < time() &&
                        $status === "open"
                    ) {

                        $deadlineClass =
                            "deadline-expired";
                    }

            ?>

                <tr>

                    <td>

                        <div class="title">

                            <?php
                            echo htmlspecialchars(
                                $opportunity['title']
                            );
                            ?>

                        </div>

                        <?php

                        if (
                            !empty(
                                $opportunity[
                                    'description'
                                ]
                            )
                        ) {

                        ?>

                            <div class="description">

                                <?php

                                $description =
                                    $opportunity[
                                        'description'
                                    ];

                                if (
                                    strlen(
                                        $description
                                    ) > 120
                                ) {

                                    echo htmlspecialchars(
                                        substr(
                                            $description,
                                            0,
                                            120
                                        )
                                    );

                                    echo "...";

                                } else {

                                    echo htmlspecialchars(
                                        $description
                                    );

                                }

                                ?>

                            </div>

                        <?php

                        }

                        ?>

                    </td>

                    <td>

                        <div class="company">

                            <?php
                            echo htmlspecialchars(
                                $opportunity[
                                    'company_name'
                                ]
                            );
                            ?>

                        </div>

                    </td>

                    <td>

                        <span
                            class="type type-<?php
                                echo htmlspecialchars($type);
                            ?>"
                        >

                            <?php
                            echo htmlspecialchars(
                                $opportunity[
                                    'opportunity_type'
                                ]
                            );
                            ?>

                        </span>

                    </td>

                    <td>

                        <?php

                        if (
                            !empty(
                                $opportunity['location']
                            )
                        ) {

                            echo htmlspecialchars(
                                $opportunity['location']
                            );

                        } else {

                            echo "-";

                        }

                        ?>

                    </td>

                    <td>

                        <?php

                        if (
                            !empty(
                                $opportunity['duration']
                            )
                        ) {

                            echo htmlspecialchars(
                                $opportunity['duration']
                            );

                        } else {

                            echo "-";

                        }

                        ?>

                    </td>

                    <td>

                        <span
                            class="<?php
                                echo $deadlineClass;
                            ?>"
                        >

                            <?php

                            echo date(
                                "d M Y",
                                strtotime(
                                    $opportunity[
                                        'deadline'
                                    ]
                                )
                            );

                            ?>

                        </span>

                    </td>

                    <td>

                        <span
                            class="status status-<?php
                                echo htmlspecialchars($status);
                            ?>"
                        >

                            <?php
                            echo htmlspecialchars(
                                $opportunity['status']
                            );
                            ?>

                        </span>

                    </td>

                    <td>

                        <?php

                        echo date(
                            "d M Y",
                            strtotime(
                                $opportunity[
                                    'created_at'
                                ]
                            )
                        );

                        ?>

                    </td>

                    <td>

                        <?php

                        if ($status === "open") {

                        ?>

                            <form
                                method="POST"
                                class="action-form"
                                onsubmit="return confirm('Are you sure you want to close this opportunity?');"
                            >

                                <input
                                    type="hidden"
                                    name="opportunity_id"
                                    value="<?php
                                        echo (int)
                                            $opportunity[
                                                'opportunity_id'
                                            ];
                                    ?>"
                                >

                                <button
                                    type="submit"
                                    name="close_opportunity"
                                    class="close-button"
                                >

                                    Close

                                </button>

                            </form>

                        <?php

                        } else {

                        ?>

                            <span class="closed-label">

                                No action

                            </span>

                        <?php

                        }

                        ?>

                    </td>

                </tr>

            <?php

                }

            } else {

            ?>

                <tr>

                    <td
                        colspan="9"
                        class="empty"
                    >

                        <h3>
                            No opportunities found
                        </h3>

                        <p>

                            <?php

                            if ($search !== "") {

                                echo "No opportunities match your search.";

                            } else {

                                echo "There are currently no opportunities.";

                            }

                            ?>

                        </p>

                    </td>

                </tr>

            <?php

            }

            ?>

            </tbody>

        </table>

    </div>

</div>

</body>

</html>
