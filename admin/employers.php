<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('administrator');

$user = currentUser();


/* =========================================
   INITIALIZE STATISTICS
========================================= */

$totalEmployers = 0;

$totalIndustries = 0;

$recentEmployersCount = 0;

$totalOpportunities = 0;


/* =========================================
   GET EMPLOYER STATISTICS
========================================= */

$statsStmt = $pdo->query(
    '
    SELECT

        COUNT(*) AS total_employers,

        COUNT(
            DISTINCT NULLIF(
                industry,
                ""
            )
        ) AS total_industries,

        SUM(
            CASE
                WHEN created_at >= DATE_SUB(
                    NOW(),
                    INTERVAL 30 DAY
                )
                THEN 1
                ELSE 0
            END
        ) AS recent_employers

    FROM employers
    '
);

$stats = $statsStmt->fetch();


if ($stats) {

    $totalEmployers =
        (int) (
            $stats['total_employers']
            ?? 0
        );

    $totalIndustries =
        (int) (
            $stats['total_industries']
            ?? 0
        );

    $recentEmployersCount =
        (int) (
            $stats['recent_employers']
            ?? 0
        );
}


/* =========================================
   GET TOTAL OPPORTUNITIES
========================================= */

$opportunitiesStmt = $pdo->query(
    '
    SELECT
        COUNT(*) AS total_opportunities
    FROM opportunities
    '
);

$opportunityStats =
    $opportunitiesStmt->fetch();


if ($opportunityStats) {

    $totalOpportunities =
        (int) (
            $opportunityStats['total_opportunities']
            ?? 0
        );
}


/* =========================================
   GET ALL EMPLOYERS
========================================= */

$employersStmt = $pdo->query(
    '
    SELECT

        e.employer_id,

        e.company_name,

        e.company_description,

        e.industry,

        e.created_at,

        u.user_id,

        u.full_name,

        u.email,

        COUNT(
            o.opportunity_id
        ) AS opportunity_count,

        SUM(
            CASE
                WHEN o.status = "open"
                THEN 1
                ELSE 0
            END
        ) AS open_opportunity_count

    FROM employers e

    INNER JOIN users u
        ON u.user_id = e.user_id

    LEFT JOIN opportunities o
        ON o.employer_id = e.employer_id

    GROUP BY

        e.employer_id,

        e.company_name,

        e.company_description,

        e.industry,

        e.created_at,

        u.user_id,

        u.full_name,

        u.email

    ORDER BY
        e.created_at DESC
    '
);

$employers =
    $employersStmt->fetchAll();


/* =========================================
   HELPER: FORMAT DATE
========================================= */

function formatEmployerDate(
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
        Employers | CareerBridge
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


            <a href="dashboard.php">

                <span>⌂</span>

                Dashboard

            </a>



            <a href="students.php">

                <span>🎓</span>

                Students

            </a>



            <a
                href="employers.php"
                class="active"
            >

                <span>🏢</span>

                Employers

            </a>



            <a href="opportunities.php">

                <span>💼</span>

                Opportunities

            </a>



            <a href="applications.php">

                <span>📋</span>

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

                    ADMINISTRATOR PORTAL / EMPLOYERS

                </p>


                <h1>

                    Employers

                </h1>


                <p class="page-subtitle">

                    Manage and monitor companies registered
                    on the CareerBridge platform.

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


            <!-- TOTAL EMPLOYERS -->

            <div class="stat-card">


                <div class="stat-icon">

                    🏢

                </div>


                <div>


                    <p>
                        Total Employers
                    </p>


                    <h2>

                        <?= $totalEmployers ?>

                    </h2>


                </div>


            </div>



            <!-- INDUSTRIES -->

            <div class="stat-card">


                <div class="stat-icon">

                    🏭

                </div>


                <div>


                    <p>
                        Industries
                    </p>


                    <h2>

                        <?= $totalIndustries ?>

                    </h2>


                </div>


            </div>



            <!-- OPPORTUNITIES -->

            <div class="stat-card">


                <div class="stat-icon">

                    💼

                </div>


                <div>


                    <p>
                        Total Opportunities
                    </p>


                    <h2>

                        <?= $totalOpportunities ?>

                    </h2>


                </div>


            </div>



            <!-- RECENT EMPLOYERS -->

            <div class="stat-card">


                <div class="stat-icon">

                    🆕

                </div>


                <div>


                    <p>
                        Joined Last 30 Days
                    </p>


                    <h2>

                        <?= $recentEmployersCount ?>

                    </h2>


                </div>


            </div>


        </section>



        <!-- =====================================
             EMPLOYERS LIST
        ====================================== -->

        <section class="content-card">


            <div class="section-heading">


                <div>


                    <p class="section-label">

                        REGISTERED EMPLOYERS

                    </p>


                    <h2>

                        All Employers

                    </h2>


                    <p>

                        View registered companies and their
                        recruitment activity on CareerBridge.

                    </p>


                </div>



                <div class="section-icon">

                    🏢

                </div>


            </div>



            <!-- SEARCH -->

            <?php if ($employers): ?>


                <div
                    class="form-group form-full"
                    style="margin-bottom: 25px;"
                >


                    <label for="employerSearch">

                        Search Employers

                    </label>


                    <input
                        type="text"
                        id="employerSearch"
                        placeholder="Search by company, employer name, email or industry..."
                    >


                </div>


            <?php endif; ?>



            <!-- =====================================
                 EMPTY STATE
            ====================================== -->

            <?php if (!$employers): ?>


                <div class="empty-state">


                    <div class="empty-icon">

                        🏢

                    </div>


                    <h3>

                        No Employers Found

                    </h3>


                    <p>

                        No companies have registered
                        on CareerBridge yet.

                    </p>


                </div>



            <!-- =====================================
                 EMPLOYERS TABLE
            ====================================== -->

            <?php else: ?>


                <div class="table-responsive">


                    <table
                        class="data-table"
                        id="employersTable"
                    >


                        <thead>


                            <tr>


                                <th>
                                    Company
                                </th>


                                <th>
                                    Contact Person
                                </th>


                                <th>
                                    Industry
                                </th>


                                <th>
                                    Opportunities
                                </th>


                                <th>
                                    Open Positions
                                </th>


                                <th>
                                    Joined
                                </th>


                            </tr>


                        </thead>



                        <tbody>


                            <?php foreach (
                                $employers
                                as $employer
                            ): ?>


                                <tr>


                                    <!-- COMPANY -->

                                    <td>


                                        <div
                                            style="
                                                display: flex;
                                                align-items: center;
                                                gap: 12px;
                                            "
                                        >


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
                                                        $employer['company_name'],
                                                        0,
                                                        1
                                                    )
                                                ) ?>


                                            </div>



                                            <div>


                                                <strong>


                                                    <?= htmlspecialchars(
                                                        $employer['company_name']
                                                            ?? 'Not provided',
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>


                                                </strong>


                                                <?php if (
                                                    !empty(
                                                        $employer[
                                                            'company_description'
                                                        ]
                                                    )
                                                ): ?>


                                                    <br>


                                                    <span
                                                        style="
                                                            font-size: 12px;
                                                            color: #6b7280;
                                                        "
                                                    >


                                                        <?= htmlspecialchars(
                                                            mb_strimwidth(
                                                                $employer[
                                                                    'company_description'
                                                                ],
                                                                0,
                                                                60,
                                                                '...'
                                                            ),
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>


                                                    </span>


                                                <?php endif; ?>


                                            </div>


                                        </div>


                                    </td>



                                    <!-- CONTACT PERSON -->

                                    <td>


                                        <strong>


                                            <?= htmlspecialchars(
                                                $employer['full_name'],
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
                                                $employer['email'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>


                                        </span>


                                    </td>



                                    <!-- INDUSTRY -->

                                    <td>


                                        <?= htmlspecialchars(
                                            $employer['industry']
                                                ?? 'Not specified',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>


                                    </td>



                                    <!-- TOTAL OPPORTUNITIES -->

                                    <td>


                                        <strong>


                                            <?= (int) (
                                                $employer[
                                                    'opportunity_count'
                                                ]
                                                ?? 0
                                            ) ?>


                                        </strong>


                                    </td>



                                    <!-- OPEN OPPORTUNITIES -->

                                    <td>


                                        <span
                                            class="status-badge status-open"
                                        >


                                            <?= (int) (
                                                $employer[
                                                    'open_opportunity_count'
                                                ]
                                                ?? 0
                                            ) ?>

                                            Open


                                        </span>


                                    </td>



                                    <!-- JOIN DATE -->

                                    <td>


                                        <?= htmlspecialchars(
                                            formatEmployerDate(
                                                $employer['created_at']
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


const employerSearch =
    document.getElementById(
        'employerSearch'
    );


if (employerSearch) {


    employerSearch.addEventListener(
        'keyup',
        function () {


            const searchValue =
                this.value.toLowerCase();


            const table =
                document.getElementById(
                    'employersTable'
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