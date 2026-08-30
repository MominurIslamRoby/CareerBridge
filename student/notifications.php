<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('student');

$user = currentUser();

$userId = (int) $user['id'];


/*
|--------------------------------------------------------------------------
| MARK SINGLE NOTIFICATION AS READ
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| MARK ALL NOTIFICATIONS AS READ
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['action'])
    && $_GET['action'] === 'mark_all_read'
) {

    $markAllReadStmt = $pdo->prepare(
        '
        UPDATE notifications
        SET is_read = 1
        WHERE user_id = ?
        '
    );

    $markAllReadStmt->execute([
        $userId
    ]);

    header('Location: notifications.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| GET NOTIFICATIONS
|--------------------------------------------------------------------------
*/

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

$stmt->execute([$userId]);

$notifications = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| NOTIFICATION STATISTICS
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| HELPER: NOTIFICATION ICON
|--------------------------------------------------------------------------
*/

function getNotificationIcon(string $type): string
{
    switch ($type) {

        case 'application':
            return '📋';

        case 'opportunity':
            return '💼';

        case 'interview':
            return '🎯';

        case 'selected':
            return '🎉';

        case 'rejected':
            return '📌';

        case 'system':
        default:
            return '🔔';
    }
}


/*
|--------------------------------------------------------------------------
| HELPER: NOTIFICATION TYPE CLASS
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| HELPER: FORMAT DATE
|--------------------------------------------------------------------------
*/

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

    return date('d M Y, h:i A', $timestamp);
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
        Notifications | CareerBridge
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

                <span>⌂</span>

                Dashboard

            </a>


            <a href="profile.php">

                <span>♟</span>

                Career Profile

            </a>


            <a href="skills.php">

                <span>⚡</span>

                My Skills

            </a>


            <a href="resume.php">

                <span>▣</span>

                Resume / CV

            </a>


            <a href="opportunities.php">

                <span>💼</span>

                Opportunities

            </a>


            <a href="applications.php">

                <span>▤</span>

                My Applications

            </a>


            <a
                href="notifications.php"
                class="active"
            >

                <span>🔔</span>

                Notifications

            </a>


        </nav>


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

                    STUDENT PORTAL / NOTIFICATIONS

                </p>


                <h1>

                    Notifications

                </h1>


                <p class="page-subtitle">

                    Stay updated with your applications, opportunities, and career activities.

                </p>


            </div>



            <!-- USER CARD -->

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

                    🔔

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

                    ✉️

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

                    ✓

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
             NOTIFICATIONS LIST
        ====================================== -->

        <section class="content-card">


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



                <div class="notification-header-action">


                    <?php if ($unreadNotifications > 0): ?>


                        <a
                            href="notifications.php?action=mark_all_read"
                            class="btn btn-secondary"
                        >

                            ✓ Mark All as Read

                        </a>


                    <?php endif; ?>


                </div>


            </div>



            <!-- =====================================
                 EMPTY STATE
            ====================================== -->

            <?php if (!$notifications): ?>


                <div class="empty-state">


                    <div class="empty-icon">

                        🔔

                    </div>


                    <h3>

                        No Notifications Yet

                    </h3>


                    <p>

                        You're all caught up! New updates about your applications
                        and opportunities will appear here.

                    </p>


                    <a
                        href="opportunities.php"
                        class="btn btn-primary"
                    >

                        Browse Opportunities →

                    </a>


                </div>



            <!-- =====================================
                 NOTIFICATION LIST
            ====================================== -->

            <?php else: ?>


                <div class="notifications-list">


                    <?php foreach ($notifications as $notification): ?>


                        <?php

                        $notificationType =
                            strtolower(
                                $notification['type']
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


                            <!-- NOTIFICATION ICON -->

                            <div
                                class="notification-type-icon <?= htmlspecialchars(
                                    $notificationClass,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                                <?= $notificationIcon ?>

                            </div>



                            <!-- NOTIFICATION CONTENT -->

                            <div class="notification-content">


                                <div class="notification-top">


                                    <h3>

                                        <?= htmlspecialchars(
                                            $notification['title'],
                                            ENT_QUOTES,
                                            'UTF-8'
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
                                        htmlspecialchars(
                                            $notification['message'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        )
                                    ) ?>

                                </p>



                                <small class="notification-date">

                                    <?= htmlspecialchars(
                                        formatNotificationDate(
                                            $notification['created_at']
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </small>


                            </div>



                            <!-- ACTION -->

                            <?php if (!$isRead): ?>


                                <div class="notification-action">


                                    <a
                                        href="notifications.php?read=<?= (int) $notification['notification_id'] ?>"
                                        class="notification-read-btn"
                                        title="Mark as read"
                                    >

                                        ✓

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

            © <?= date('Y') ?>
            CareerBridge — University Career Management Platform

        </footer>


    </main>


</div>


</body>

</html>