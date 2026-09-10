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

$search = "";

if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

if ($search !== "") {

    $searchValue = "%" . $search . "%";

    $stmt = $conn->prepare(
        "SELECT
            e.employer_id,
            e.user_id,
            e.company_name,
            e.company_description,
            e.industry,
            e.website,
            e.company_email,
            e.phone,
            e.address,
            e.created_at,

            u.full_name,
            u.email,
            u.account_status

         FROM employers e

         INNER JOIN users u
             ON e.user_id = u.user_id

         WHERE
             e.company_name LIKE ?
             OR e.industry LIKE ?
             OR e.company_email LIKE ?
             OR e.phone LIKE ?
             OR e.address LIKE ?
             OR u.full_name LIKE ?
             OR u.email LIKE ?

         ORDER BY e.company_name ASC"
    );

    $stmt->bind_param(
        "sssssss",
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue
    );

    $stmt->execute();

    $employers = $stmt->get_result();

} else {

    $employers = $conn->query(
        "SELECT
            e.employer_id,
            e.user_id,
            e.company_name,
            e.company_description,
            e.industry,
            e.website,
            e.company_email,
            e.phone,
            e.address,
            e.created_at,

            u.full_name,
            u.email,
            u.account_status

         FROM employers e

         INNER JOIN users u
             ON e.user_id = u.user_id

         ORDER BY e.company_name ASC"
    );
}

$totalEmployers = 0;
$activeEmployers = 0;
$inactiveEmployers = 0;
$suspendedEmployers = 0;

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM employers"
);

if ($result) {
    $totalEmployers =
        (int)$result->fetch_assoc()['total'];
}

$result = $conn->query(
    "SELECT COUNT(*) AS total

     FROM employers e

     INNER JOIN users u
         ON e.user_id = u.user_id

     WHERE u.account_status = 'active'"
);

if ($result) {
    $activeEmployers =
        (int)$result->fetch_assoc()['total'];
}

$result = $conn->query(
    "SELECT COUNT(*) AS total

     FROM employers e

     INNER JOIN users u
         ON e.user_id = u.user_id

     WHERE u.account_status = 'inactive'"
);

if ($result) {
    $inactiveEmployers =
        (int)$result->fetch_assoc()['total'];
}

$result = $conn->query(
    "SELECT COUNT(*) AS total

     FROM employers e

     INNER JOIN users u
         ON e.user_id = u.user_id

     WHERE u.account_status = 'suspended'"
);

if ($result) {
    $suspendedEmployers =
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

<title>Employer Management - CareerBridge</title>


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


.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}


.stat-card {
    background: white;
    padding: 22px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
}


.stat-card h3 {
    color: #777;
    font-size: 14px;
    margin-bottom: 10px;
}


.stat-number {
    font-size: 30px;
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


.company-name {
    font-weight: bold;
    margin-bottom: 4px;
}


.contact-name {
    color: #777;
    font-size: 12px;
}


.contact-email {
    color: #888;
    font-size: 12px;
    margin-top: 3px;
}


.company-description {
    max-width: 280px;
    color: #666;
    line-height: 1.4;
}


.website-link {
    color: #2563eb;
    text-decoration: none;
    word-break: break-all;
}


.website-link:hover {
    text-decoration: underline;
}


.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
    text-transform: capitalize;
}


.status-active {
    background: #dcfce7;
    color: #166534;
}


.status-inactive {
    background: #fef3c7;
    color: #92400e;
}


.status-suspended {
    background: #fee2e2;
    color: #991b1b;
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
        grid-template-columns: repeat(2, 1fr);
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

    .header {
        display: block;
    }

    .admin-name {
        text-align: left;
        margin-top: 10px;
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


    <a
        href="employers.php"
        class="active"
    >
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
                Employer Management
            </h1>

            <p>
                View registered employers and company information.
            </p>

        </div>


        <div class="admin-name">

            Administrator

        </div>


    </div>



    <div class="stats">


        <div class="stat-card">

            <h3>
                Total Employers
            </h3>

            <div class="stat-number">

                <?php
                echo $totalEmployers;
                ?>

            </div>

        </div>


        <div class="stat-card">

            <h3>
                Active Employers
            </h3>

            <div class="stat-number">

                <?php
                echo $activeEmployers;
                ?>

            </div>

        </div>


        <div class="stat-card">

            <h3>
                Inactive Employers
            </h3>

            <div class="stat-number">

                <?php
                echo $inactiveEmployers;
                ?>

            </div>

        </div>


        <div class="stat-card">

            <h3>
                Suspended Employers
            </h3>

            <div class="stat-number">

                <?php
                echo $suspendedEmployers;
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
                placeholder="Search by company, industry, employer, email or phone..."
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
                    href="employers.php"
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

                    echo "Registered Employers";

                }

                ?>

            </h2>

        </div>



        <table>


            <thead>

                <tr>

                    <th>
                        Company
                    </th>

                    <th>
                        Employer
                    </th>

                    <th>
                        Industry
                    </th>

                    <th>
                        Company Email
                    </th>

                    <th>
                        Phone
                    </th>

                    <th>
                        Address
                    </th>

                    <th>
                        Website
                    </th>

                    <th>
                        Account Status
                    </th>

                    <th>
                        Registered
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php

            if (
                $employers &&
                $employers->num_rows > 0
            ) {

                while (
                    $employer =
                    $employers->fetch_assoc()
                ) {

                    $status =
                        strtolower(
                            $employer[
                                'account_status'
                            ]
                        );

            ?>


                <tr>


                    <td>

                        <div class="company-name">

                            <?php
                            echo htmlspecialchars(
                                $employer[
                                    'company_name'
                                ]
                            );
                            ?>

                        </div>


                        <?php

                        if (
                            !empty(
                                $employer[
                                    'company_description'
                                ]
                            )
                        ) {

                        ?>

                            <div class="company-description">

                                <?php

                                $description =
                                    $employer[
                                        'company_description'
                                    ];

                                if (
                                    strlen($description) > 150
                                ) {

                                    echo htmlspecialchars(
                                        substr(
                                            $description,
                                            0,
                                            150
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

                        <div class="company-name">

                            <?php
                            echo htmlspecialchars(
                                $employer[
                                    'full_name'
                                ]
                            );
                            ?>

                        </div>


                        <div class="contact-email">

                            <?php
                            echo htmlspecialchars(
                                $employer[
                                    'email'
                                ]
                            );
                            ?>

                        </div>

                    </td>


                    <td>

                        <?php

                        if (
                            !empty(
                                $employer[
                                    'industry'
                                ]
                            )
                        ) {

                            echo htmlspecialchars(
                                $employer[
                                    'industry'
                                ]
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
                                $employer[
                                    'company_email'
                                ]
                            )
                        ) {

                            echo htmlspecialchars(
                                $employer[
                                    'company_email'
                                ]
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
                                $employer['phone']
                            )
                        ) {

                            echo htmlspecialchars(
                                $employer['phone']
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
                                $employer['address']
                            )
                        ) {

                            echo htmlspecialchars(
                                $employer['address']
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
                                $employer['website']
                            )
                        ) {

                            $website =
                                $employer['website'];

                            if (
                                !preg_match(
                                    '/^https?:\/\//i',
                                    $website
                                )
                            ) {

                                $website =
                                    "https://" .
                                    $website;

                            }

                        ?>

                            <a
                                href="<?php
                                    echo htmlspecialchars(
                                        $website
                                    );
                                ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="website-link"
                            >

                                Visit

                            </a>

                        <?php

                        } else {

                            echo "-";

                        }

                        ?>

                    </td>


                    <td>

                        <span
                            class="status status-<?php
                                echo htmlspecialchars(
                                    $status
                                );
                            ?>"
                        >

                            <?php
                            echo htmlspecialchars(
                                $employer[
                                    'account_status'
                                ]
                            );
                            ?>

                        </span>

                    </td>


                    <td>

                        <?php

                        if (
                            !empty(
                                $employer['created_at']
                            )
                        ) {

                            echo date(
                                "d M Y",
                                strtotime(
                                    $employer[
                                        'created_at'
                                    ]
                                )
                            );

                        } else {

                            echo "-";

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
                            No employers found
                        </h3>


                        <p>

                            <?php

                            if ($search !== "") {

                                echo "No employers match your search.";

                            } else {

                                echo "There are currently no registered employers.";

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
```
