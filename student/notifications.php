<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';


/* =========================================
   AUTHORIZATION
========================================= */

requireRole('student');

$user = currentUser();

$userId = (int) ($user['id'] ?? 0);


/* =========================================
   HELPER FUNCTION
========================================= */

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================
   MARK SINGLE NOTIFICATION AS READ
========================================= */

$notificationId = filter_input(
    INPUT_GET,
    'read',
    FILTER_VALIDATE_INT
);

if ($notificationId) {

    $markReadStmt = $pdo->prepare(
        '
        UPDATE notifications
        SET is_read = 1
        WHERE notification_id = ?
          AND user_id = ?
        '
    );

    $markReadStmt->execute([
        $notificationId,
        $userId
    ]);

    header('Location: notifications.php');
    exit;
}


/* =========================================
   MARK ALL NOTIFICATIONS AS READ
========================================= */

if (
    isset($_GET['action'])
    && $_GET['action'] === 'mark_all_read'
) {

    $markAllReadStmt = $pdo->prepare(
        '
        UPDATE notifications
        SET is_read = 1
        WHERE user_id = ?
          AND is_read = 0
        '
    );

    $markAllReadStmt->execute([
        $userId
    ]);

    header('Location: notifications.php');
    exit;
}


/* =========================================
   GET NOTIFICATIONS
========================================= */

$stmt = $pdo->prepare(
    '
    SELECT
        notification_id,
        title,
        message,
        type,
        is_read,
        created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY
        is_read ASC,
        created_at DESC
    '
);

$stmt->execute([
    $userId
]);

$notifications = $stmt->fetchAll();


/* =========================================
   NOTIFICATION STATISTICS
========================================= */

$totalNotifications = count($notifications);

$unreadNotifications = 0;
$readNotifications = 0;

foreach ($notifications as $notification) {

    if ((int) $notification['is_read'] === 1) {
        $readNotifications++;
    } else {
        $unreadNotifications++;
    }
}


/* =========================================
   NOTIFICATION ICON HELPER
========================================= */

function getNotificationIcon(string $type): string
{
    switch ($type) {

        case 'application':
            return 'fa-solid fa-clipboard-list';

        case 'opportunity':
            return 'fa-solid fa-briefcase';

        case 'interview':
            return 'fa-solid fa-calendar-check';

        case 'selected':
            return 'fa-solid fa-circle-check';

        case 'rejected':
            return 'fa-solid fa-circle-xmark';

        case 'system':
        default:
            return 'fa-solid fa-bell';
    }
}


/* =========================================
   NOTIFICATION TYPE CLASS HELPER
========================================= */

function getNotificationClass(string $type): string
{
    switch ($type) {

        case 'application':
            return 'notification-application';

        case 'opportunity':
            return 'notification-opportunity';

        case 'interview':
            return 'notification-interview';

        case 'selected':
            return 'notification-selected';

        case 'rejected':
            return 'notification-rejected';

        case 'system':
        default:
            return 'notification-system';
    }
}


/* =========================================
   FORMAT NOTIFICATION DATE
========================================= */

function formatNotificationDate(string $date): string
{
    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return 'Unknown date';
    }

    $today = strtotime('today');
    $yesterday = strtotime('yesterday');

    if ($timestamp >= $today) {
        return 'Today, ' . date('h:i A', $timestamp);
    }

    if ($timestamp >= $yesterday) {
        return 'Yesterday, ' . date('h:i A', $timestamp);
    }

    return date(
        'd M Y, h:i A',
        $timestamp
    );
}


/* =========================================
   USER DISPLAY DATA
========================================= */

$userName = $user['full_name'] ?? 'Student';

$userInitial = strtoupper(
    substr(
        $userName,
        0,
        1
    )
);

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
        Notifications | CareerBridge
    </title>


    <!-- FONT AWESOME -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <!-- MAIN STYLESHEET -->

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >


    <!-- PAGE SPECIFIC STYLES -->

    <style>

        /* =====================================
           PAGE WRAPPER
        ====================================== */

        .notifications-page {
            width: 100%;
        }


        /* =====================================
           STATISTICS
        ====================================== */

        .notifications-page .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 30px;
        }


        .notifications-page .stat-card {
            min-height: 125px;
            display: flex;
            align-items: center;
            gap: 18px;
        }


        .notifications-page .stat-card h2 {
            margin: 7px 0 0;
        }


        .notifications-page .stat-card p {
            margin: 0;
        }


        /* =====================================
           NOTIFICATIONS CARD
        ====================================== */

        .notifications-card {
            margin-bottom: 35px;
        }


        /* =====================================
           SECTION HEADING
        ====================================== */

        .notifications-page .section-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            padding-bottom: 22px;
            border-bottom: 1px solid #e2e8f0;
        }


        .notifications-page .section-heading h2 {
            margin: 6px 0 8px;
        }


        .notifications-page .section-heading p {
            margin: 0;
        }


        .notification-header-action {
            flex-shrink: 0;
        }


        /* =====================================
           NOTIFICATIONS LIST
        ====================================== */

        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-top: 25px;
        }


        /* =====================================
           NOTIFICATION ITEM
        ====================================== */

        .notification-item {
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: 18px;
            padding: 22px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: #ffffff;
            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease,
                border-color 0.2s ease;
        }


        .notification-item:hover {
            transform: translateY(-2px);
            border-color: #c7d2fe;
            box-shadow:
                0 8px 20px rgba(15, 23, 42, 0.07);
        }


        /* =====================================
           UNREAD / READ STATES
        ====================================== */

        .notification-unread {
            border-left: 4px solid #4f46e5;
            background: #fafbff;
        }


        .notification-read {
            opacity: 0.82;
        }


        /* =====================================
           NOTIFICATION ICON
        ====================================== */

        .notification-type-icon {
            width: 48px;
            height: 48px;
            flex-shrink: 0;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 12px;

            font-size: 18px;
        }


        .notification-application {
            background: #eef2ff;
            color: #4f46e5;
        }


        .notification-opportunity {
            background: #eff6ff;
            color: #2563eb;
        }


        .notification-interview {
            background: #fdf4ff;
            color: #a21caf;
        }


        .notification-selected {
            background: #ecfdf5;
            color: #059669;
        }


        .notification-rejected {
            background: #fef2f2;
            color: #dc2626;
        }


        .notification-system {
            background: #f8fafc;
            color: #64748b;
        }


        /* =====================================
           NOTIFICATION CONTENT
        ====================================== */

        .notification-content {
            flex: 1;
            min-width: 0;
        }


        .notification-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 8px;
        }


        .notification-top h3 {
            margin: 0;
            font-size: 17px;
            color: #1e293b;
        }


        .notification-content p {
            margin: 0 0 12px;
            color: #64748b;
            line-height: 1.7;
        }


        .notification-date {
            display: inline-flex;
            align-items: center;
            gap: 7px;

            color: #94a3b8;
            font-size: 13px;
        }


        .notification-date i {
            color: #6366f1;
        }


        /* =====================================
           NEW BADGE
        ====================================== */

        .unread-indicator {
            flex-shrink: 0;

            display: inline-flex;
            align-items: center;

            padding: 5px 10px;

            border-radius: 999px;

            background: #eef2ff;
            color: #4f46e5;

            font-size: 11px;
            font-weight: 700;

            text-transform: uppercase;
            letter-spacing: 0.5px;
        }


        /* =====================================
           MARK AS READ BUTTON
        ====================================== */

        .notification-action {
            flex-shrink: 0;
        }


        .notification-read-btn {
            width: 38px;
            height: 38px;

            display: flex;
            align-items: center;
            justify-content: center;

            border: 1px solid #cbd5e1;
            border-radius: 10px;

            background: #ffffff;
            color: #4f46e5;

            text-decoration: none;

            transition:
                background 0.2s ease,
                color 0.2s ease,
                border-color 0.2s ease;
        }


        .notification-read-btn:hover {
            background: #4f46e5;
            border-color: #4f46e5;
            color: #ffffff;
        }


        /* =====================================
           EMPTY STATE
        ====================================== */

        .notification-empty-state {
            text-align: center;
            padding: 65px 25px;
        }


        .notification-empty-state .empty-icon {
            width: 72px;
            height: 72px;

            margin: 0 auto 20px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 50%;

            background: #eef2ff;
            color: #4f46e5;

            font-size: 28px;
        }


        .notification-empty-state h3 {
            margin-bottom: 10px;
            color: #1e293b;
        }


        .notification-empty-state p {
            max-width: 500px;
            margin: 0 auto 25px;

            color: #64748b;
            line-height: 1.7;
        }


        /* =====================================
           RESPONSIVE
        ====================================== */

        @media (max-width: 900px) {

            .notifications-page .stats-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }


            .notifications-page .section-heading {
                flex-direction: column;
            }

        }


        @media (max-width: 650px) {

            .notifications-page .stats-grid {
                grid-template-columns: 1fr;
            }


            .notification-item {
                padding: 18px;
                gap: 14px;
            }


            .notification-top {
                align-items: flex-start;
                flex-direction: column;
                gap: 8px;
            }


            .notification-action {
                position: absolute;
                top: 18px;
                right: 18px;
            }


            .notification-unread .notification-content {
                padding-right: 42px;
            }


            .notification-header-action {
                width: 100%;
            }


            .notification-header-action .btn {
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


        <!-- BRAND -->

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


        <!-- NAVIGATION -->

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


            <a href="interviews.php">

                <span>
                    <i class="fa-solid fa-calendar-check"></i>
                </span>

                My Interviews

            </a>


            <a
                href="notifications.php"
                class="active"
            >

                <span>
                    <i class="fa-solid fa-bell"></i>
                </span>

                Notifications

            </a>


        </nav>


        <!-- LOGOUT -->

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

    <main class="main-content notifications-page">


        <!-- =====================================
             PAGE HEADER
        ====================================== -->

        <div class="page-header">


            <div>


                <p class="breadcrumb">
                    STUDENT PORTAL / NOTIFICATIONS
                </p>


                <h1>
                    Notifications
                </h1>


                <p class="page-subtitle">
                    Stay updated with your applications, opportunities,
                    interviews, and career activities.
                </p>


            </div>


            <!-- USER CARD -->

            <div class="user-card">


                <div class="user-avatar">

                    <?= e($userInitial) ?>

                </div>


                <div>


                    <strong>

                        <?= e($userName) ?>

                    </strong>


                    <span>
                        Student
                    </span>


                </div>


            </div>


        </div>



        <!-- =====================================
             NOTIFICATION STATISTICS
        ====================================== -->

        <section class="stats-grid">


            <!-- TOTAL -->

            <div class="stat-card">


                <div class="stat-icon">

                    <i class="fa-solid fa-bell"></i>

                </div>


                <div>

                    <p>
                        Total Notifications
                    </p>

                    <h2>
                        <?= $totalNotifications ?>
                    </h2>

                </div>


            </div>



            <!-- UNREAD -->

            <div class="stat-card">


                <div class="stat-icon">

                    <i class="fa-solid fa-envelope"></i>

                </div>


                <div>

                    <p>
                        Unread
                    </p>

                    <h2>
                        <?= $unreadNotifications ?>
                    </h2>

                </div>


            </div>



            <!-- READ -->

            <div class="stat-card">


                <div class="stat-icon">

                    <i class="fa-solid fa-circle-check"></i>

                </div>


                <div>

                    <p>
                        Read
                    </p>

                    <h2>
                        <?= $readNotifications ?>
                    </h2>

                </div>


            </div>


        </section>



        <!-- =====================================
             NOTIFICATIONS CARD
        ====================================== -->

        <section class="content-card notifications-card">


            <!-- SECTION HEADER -->

            <div class="section-heading">


                <div>


                    <p class="section-label">
                        UPDATES
                    </p>


                    <h2>
                        Your Notifications
                    </h2>


                    <p>
                        Keep track of important updates from CareerBridge.
                    </p>


                </div>


                <!-- MARK ALL AS READ -->

                <div class="notification-header-action">


                    <?php if ($unreadNotifications > 0): ?>


                        <a
                            href="notifications.php?action=mark_all_read"
                            class="btn btn-secondary"
                        >

                            <i class="fa-solid fa-check-double"></i>

                            Mark All as Read

                        </a>


                    <?php endif; ?>


                </div>


            </div>



            <!-- =====================================
                 EMPTY STATE
            ====================================== -->

            <?php if (!$notifications): ?>


                <div class="notification-empty-state">


                    <div class="empty-icon">

                        <i class="fa-solid fa-bell"></i>

                    </div>


                    <h3>
                        No Notifications Yet
                    </h3>


                    <p>
                        You're all caught up. New updates about your
                        applications and opportunities will appear here.
                    </p>


                    <a
                        href="opportunities.php"
                        class="btn btn-primary"
                    >

                        <i class="fa-solid fa-briefcase"></i>

                        Browse Opportunities

                    </a>


                </div>



            <!-- =====================================
                 NOTIFICATIONS LIST
            ====================================== -->

            <?php else: ?>


                <div class="notifications-list">


                    <?php foreach ($notifications as $notification): ?>


                        <?php

                        $notificationType = strtolower(
                            (string) (
                                $notification['type']
                                ?? 'system'
                            )
                        );

                        $notificationClass =
                            getNotificationClass(
                                $notificationType
                            );

                        $notificationIcon =
                            getNotificationIcon(
                                $notificationType
                            );

                        $isRead =
                            (int) $notification['is_read'] === 1;

                        ?>


                        <article
                            class="notification-item <?= $isRead
                                ? 'notification-read'
                                : 'notification-unread'
                            ?>"
                        >


                            <!-- TYPE ICON -->

                            <div
                                class="notification-type-icon <?= e(
                                    $notificationClass
                                ) ?>"
                            >

                                <i
                                    class="<?= e(
                                        $notificationIcon
                                    ) ?>"
                                ></i>

                            </div>



                            <!-- CONTENT -->

                            <div class="notification-content">


                                <div class="notification-top">


                                    <h3>

                                        <?= e(
                                            $notification['title']
                                        ) ?>

                                    </h3>


                                    <?php if (!$isRead): ?>


                                        <span class="unread-indicator">

                                            New

                                        </span>


                                    <?php endif; ?>


                                </div>


                                <p>

                                    <?= nl2br(
                                        e(
                                            $notification['message']
                                        )
                                    ) ?>

                                </p>


                                <small class="notification-date">

                                    <i class="fa-regular fa-clock"></i>

                                    <?= e(
                                        formatNotificationDate(
                                            $notification['created_at']
                                        )
                                    ) ?>

                                </small>


                            </div>



                            <!-- MARK AS READ -->

                            <?php if (!$isRead): ?>


                                <div class="notification-action">


                                    <a
                                        href="notifications.php?read=<?= (int) $notification['notification_id'] ?>"
                                        class="notification-read-btn"
                                        title="Mark as read"
                                    >

                                        <i class="fa-solid fa-check"></i>

                                    </a>


                                </div>


                            <?php endif; ?>


                        </article>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


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


</body>

</html>