<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| Employer Authentication
|--------------------------------------------------------------------------
*/

requireRole('employer');

$user = currentUser();


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function tableExists(PDO $pdo, string $tableName): bool
{
    try {

        $stmt = $pdo->prepare(
            "SHOW TABLES LIKE ?"
        );

        $stmt->execute([
            $tableName
        ]);

        return (bool) $stmt->fetchColumn();

    } catch (PDOException $e) {

        return false;
    }
}


function getTableColumns(PDO $pdo, string $tableName): array
{
    try {

        $stmt = $pdo->query(
            "SHOW COLUMNS FROM `$tableName`"
        );

        $columns = [];

        while ($column = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $columns[] = $column['Field'];
        }

        return $columns;

    } catch (PDOException $e) {

        return [];
    }
}


/*
|--------------------------------------------------------------------------
| Default Variables
|--------------------------------------------------------------------------
*/

$notifications = [];

$totalNotifications = 0;
$unreadNotifications = 0;
$readNotifications = 0;

$notificationColumns = [];

$successMessage = '';
$errorMessage = '';


/*
|--------------------------------------------------------------------------
| Detect Notification Table
|--------------------------------------------------------------------------
*/

if (tableExists($pdo, 'notifications')) {

    $notificationColumns = getTableColumns(
        $pdo,
        'notifications'
    );
}


/*
|--------------------------------------------------------------------------
| Determine Notification ID Column
|--------------------------------------------------------------------------
*/

$notificationIdColumn = null;

if (
    in_array(
        'notification_id',
        $notificationColumns,
        true
    )
) {

    $notificationIdColumn = 'notification_id';

} elseif (
    in_array(
        'id',
        $notificationColumns,
        true
    )
) {

    $notificationIdColumn = 'id';
}


/*
|--------------------------------------------------------------------------
| Determine User Column
|--------------------------------------------------------------------------
*/

$userColumn = null;

if (
    in_array(
        'user_id',
        $notificationColumns,
        true
    )
) {

    $userColumn = 'user_id';

} elseif (
    in_array(
        'recipient_id',
        $notificationColumns,
        true
    )
) {

    $userColumn = 'recipient_id';
}


/*
|--------------------------------------------------------------------------
| Handle Actions
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    tableExists($pdo, 'notifications') &&
    $notificationIdColumn !== null &&
    $userColumn !== null
) {

    $action = $_POST['action'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Mark Single Notification As Read
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'mark_read' &&
        isset($_POST['notification_id']) &&
        in_array(
            'is_read',
            $notificationColumns,
            true
        )
    ) {

        try {

            $stmt = $pdo->prepare(
                "UPDATE notifications
                 SET is_read = 1
                 WHERE `$notificationIdColumn` = ?
                 AND `$userColumn` = ?"
            );

            $stmt->execute([
                $_POST['notification_id'],
                $user['id']
            ]);

            $successMessage =
                'Notification marked as read.';

        } catch (PDOException $e) {

            $errorMessage =
                'Unable to update notification.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Mark All As Read
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'mark_all_read' &&
        in_array(
            'is_read',
            $notificationColumns,
            true
        )
    ) {

        try {

            $stmt = $pdo->prepare(
                "UPDATE notifications
                 SET is_read = 1
                 WHERE `$userColumn` = ?"
            );

            $stmt->execute([
                $user['id']
            ]);

            $successMessage =
                'All notifications marked as read.';

        } catch (PDOException $e) {

            $errorMessage =
                'Unable to update notifications.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Delete Notification
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'delete' &&
        isset($_POST['notification_id'])
    ) {

        try {

            $stmt = $pdo->prepare(
                "DELETE FROM notifications
                 WHERE `$notificationIdColumn` = ?
                 AND `$userColumn` = ?"
            );

            $stmt->execute([
                $_POST['notification_id'],
                $user['id']
            ]);

            $successMessage =
                'Notification deleted successfully.';

        } catch (PDOException $e) {

            $errorMessage =
                'Unable to delete notification.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Fetch Notifications
|--------------------------------------------------------------------------
*/

if (
    tableExists($pdo, 'notifications') &&
    $userColumn !== null
) {

    try {

        /*
        |--------------------------------------------------------------------------
        | Order Column
        |--------------------------------------------------------------------------
        */

        $orderColumn = null;

        if (
            in_array(
                'created_at',
                $notificationColumns,
                true
            )
        ) {

            $orderColumn = 'created_at';

        } elseif (
            in_array(
                'created_on',
                $notificationColumns,
                true
            )
        ) {

            $orderColumn = 'created_on';
        }


        $query =
            "SELECT *
             FROM notifications
             WHERE `$userColumn` = ?";


        if ($orderColumn !== null) {

            $query .=
                " ORDER BY `$orderColumn` DESC";
        }


        $stmt = $pdo->prepare($query);

        $stmt->execute([
            $user['id']
        ]);

        $notifications =
            $stmt->fetchAll(PDO::FETCH_ASSOC);


        /*
        |--------------------------------------------------------------------------
        | Calculate Statistics
        |--------------------------------------------------------------------------
        */

        foreach ($notifications as $notification) {

            $totalNotifications++;


            if (
                isset(
                    $notification['is_read']
                )
            ) {

                if (
                    (int) $notification['is_read'] === 0
                ) {

                    $unreadNotifications++;

                } else {

                    $readNotifications++;
                }

            } else {

                $unreadNotifications++;
            }
        }

    } catch (PDOException $e) {

        $notifications = [];
    }
}


/*
|--------------------------------------------------------------------------
| Notification Content Helpers
|--------------------------------------------------------------------------
*/

function getNotificationTitle(array $notification): string
{
    if (!empty($notification['title'])) {

        return $notification['title'];
    }

    if (!empty($notification['subject'])) {

        return $notification['subject'];
    }

    if (!empty($notification['notification_title'])) {

        return $notification['notification_title'];
    }

    return 'CareerBridge Notification';
}


function getNotificationMessage(array $notification): string
{
    if (!empty($notification['message'])) {

        return $notification['message'];
    }

    if (!empty($notification['content'])) {

        return $notification['content'];
    }

    if (!empty($notification['description'])) {

        return $notification['description'];
    }

    return 'You have a new update from CareerBridge.';
}


function getNotificationDate(array $notification): string
{
    $date = null;


    if (!empty($notification['created_at'])) {

        $date = $notification['created_at'];

    } elseif (!empty($notification['created_on'])) {

        $date = $notification['created_on'];

    } elseif (!empty($notification['date'])) {

        $date = $notification['date'];
    }


    if ($date) {

        $timestamp = strtotime($date);

        if ($timestamp !== false) {

            return date(
                'M d, Y • h:i A',
                $timestamp
            );
        }
    }

    return 'Recently';
}


function getNotificationIcon(array $notification): string
{
    $type =
        strtolower(
            $notification['type']
            ?? $notification['notification_type']
            ?? ''
        );


    switch ($type) {

        case 'application':

            return 'fa-file-lines';

        case 'interview':

            return 'fa-calendar-check';

        case 'opportunity':

        case 'job':

            return 'fa-briefcase';

        case 'success':

            return 'fa-circle-check';

        case 'warning':

            return 'fa-triangle-exclamation';

        case 'message':

            return 'fa-envelope';

        default:

            return 'fa-bell';
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
        Notifications | CareerBridge
    </title>


    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <!-- Main CSS -->

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

            <img
                src="../assets/images/CB Logo Transparent.png"
                alt="CareerBridge Logo"
            >

        </div>


        <div class="brand-content">

            <h2>
                CareerBridge
            </h2>

            <span>
                Employer Portal
            </span>

        </div>

    </div>


        <div class="sidebar-divider"></div>


        <nav class="sidebar-nav">


            <p class="nav-title">

                MAIN MENU

            </p>


            <a
                href="dashboard.php"
                class="nav-link"
            >

                <i class="fa-solid fa-house"></i>

                <span>
                    Dashboard
                </span>

            </a>


            <a
                href="profile.php"
                class="nav-link"
            >

                <i class="fa-solid fa-building"></i>

                <span>
                    Company Profile
                </span>

            </a>


            <a
                href="opportunities.php"
                class="nav-link"
            >

                <i class="fa-solid fa-briefcase"></i>

                <span>
                    My Opportunities
                </span>

            </a>


            <a
                href="create_opportunity.php"
                class="nav-link"
            >

                <i class="fa-solid fa-plus"></i>

                <span>
                    Post Opportunity
                </span>

            </a>


            <a
                href="applications.php"
                class="nav-link"
            >

                <i class="fa-solid fa-file-lines"></i>

                <span>
                    Applications
                </span>

            </a>


            <a
                href="interviews.php"
                class="nav-link"
            >

                <i class="fa-solid fa-calendar-check"></i>

                <span>
                    Interviews
                </span>

            </a>


            <a
                href="notifications.php"
                class="nav-link active"
            >

                <i class="fa-solid fa-bell"></i>

                <span>
                    Notifications
                </span>

            </a>


        </nav>


        <!-- LOGOUT -->

        <div class="sidebar-bottom">


            <a
                href="../auth/logout.php"
                class="logout-link"
            >

                <i class="fa-solid fa-right-from-bracket"></i>

                <span>
                    Logout
                </span>

            </a>


        </div>


    </aside>



    <!-- =====================================
         MAIN CONTENT
    ====================================== -->

    <main class="main-content">


        <!-- HEADER -->

        <header class="top-header">


            <div>


                <p class="breadcrumb">

                    EMPLOYER PORTAL / NOTIFICATIONS

                </p>


                <h2>

                    Notifications

                </h2>


                <p class="welcome-text">

                    Stay updated with your recruitment activities.

                </p>


            </div>


            <div class="user-info">


                <div class="user-avatar">

                    <?= htmlspecialchars(
                        strtoupper(
                            substr(
                                $user['full_name']
                                ?? 'E',
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
                            $user['full_name']
                            ?? 'Employer',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </strong>


                    <span>

                        Employer

                    </span>


                </div>


            </div>


        </header>



        <!-- =====================================
             STATISTICS
        ====================================== -->

        <section class="stats-grid">


            <!-- TOTAL -->

            <div class="stat-card">


                <div class="stat-icon blue">

                    <i class="fa-solid fa-bell"></i>

                </div>


                <div>

                    <p>
                        Total Notifications
                    </p>

                    <h3>
                        <?= $totalNotifications ?>
                    </h3>

                </div>


            </div>



            <!-- UNREAD -->

            <div class="stat-card">


                <div class="stat-icon orange">

                    <i class="fa-solid fa-envelope"></i>

                </div>


                <div>

                    <p>
                        Unread
                    </p>

                    <h3>
                        <?= $unreadNotifications ?>
                    </h3>

                </div>


            </div>



            <!-- READ -->

            <div class="stat-card">


                <div class="stat-icon green">

                    <i class="fa-solid fa-envelope-open"></i>

                </div>


                <div>

                    <p>
                        Read
                    </p>

                    <h3>
                        <?= $readNotifications ?>
                    </h3>

                </div>


            </div>



            <!-- STATUS -->

            <div class="stat-card">


                <div class="stat-icon purple">

                    <i class="fa-solid fa-circle-info"></i>

                </div>


                <div>

                    <p>
                        Notification Status
                    </p>

                    <h3>

                        <?= $unreadNotifications > 0
                            ? 'New'
                            : 'Clear'
                        ?>

                    </h3>

                </div>


            </div>


        </section>



        <!-- =====================================
             PAGE ACTIONS
        ====================================== -->

        <div class="page-action-bar">


            <div>


                <p class="section-label">

                    ACTIVITY CENTER

                </p>


                <h3>

                    Recent Updates

                </h3>


            </div>


            <?php if ($unreadNotifications > 0): ?>


                <form
                    method="POST"
                >


                    <input
                        type="hidden"
                        name="action"
                        value="mark_all_read"
                    >


                    <button
                        type="submit"
                        class="secondary-button"
                    >

                        <i class="fa-solid fa-check-double"></i>

                        Mark All as Read

                    </button>


                </form>


            <?php endif; ?>


        </div>



        <!-- =====================================
             SUCCESS MESSAGE
        ====================================== -->

        <?php if (!empty($successMessage)): ?>


            <div class="alert-message success-alert">


                <i class="fa-solid fa-circle-check"></i>

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

        <?php if (!empty($errorMessage)): ?>


            <div class="alert-message error-alert">


                <i class="fa-solid fa-circle-exclamation"></i>

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
             NOTIFICATION LIST
        ====================================== -->

        <section class="dashboard-card notifications-card">


            <div class="card-header">


                <div>


                    <p class="section-label">

                        NOTIFICATION CENTER

                    </p>


                    <h3>

                        All Notifications

                    </h3>


                </div>


                <span class="card-label">

                    <i class="fa-solid fa-bell"></i>

                </span>


            </div>



            <?php if (empty($notifications)): ?>


                <!-- EMPTY STATE -->

                <div class="empty-state">


                    <div class="empty-icon">

                        <i class="fa-solid fa-bell-slash"></i>

                    </div>


                    <h3>

                        You're all caught up!

                    </h3>


                    <p>

                        There are no notifications to display at the moment.

                    </p>


                    <a
                        href="dashboard.php"
                        class="primary-button"
                    >

                        <i class="fa-solid fa-house"></i>

                        Go to Dashboard

                    </a>


                </div>



            <?php else: ?>


                <div class="notification-list">


                    <?php foreach (
                        $notifications
                        as $notification
                    ): ?>


                        <?php

                        $isRead =
                            isset(
                                $notification['is_read']
                            )
                            ? (int) $notification['is_read']
                            : 0;


                        $notificationId =
                            $notification[
                                $notificationIdColumn
                            ]
                            ?? '';

                        ?>


                        <div
                            class="notification-item <?= $isRead === 0
                                ? 'unread'
                                : ''
                            ?>"
                        >


                            <!-- ICON -->

                            <div class="notification-icon">


                                <i
                                    class="fa-solid <?= htmlspecialchars(
                                        getNotificationIcon(
                                            $notification
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                ></i>


                            </div>



                            <!-- CONTENT -->

                            <div
                                class="notification-content"
                            >


                                <div
                                    class="notification-top"
                                >


                                    <div>


                                        <h4>

                                            <?= htmlspecialchars(
                                                getNotificationTitle(
                                                    $notification
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </h4>


                                        <?php if ($isRead === 0): ?>


                                            <span
                                                class="new-badge"
                                            >

                                                NEW

                                            </span>


                                        <?php endif; ?>


                                    </div>


                                    <span
                                        class="notification-time"
                                    >

                                        <?= htmlspecialchars(
                                            getNotificationDate(
                                                $notification
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </span>


                                </div>



                                <p>

                                    <?= htmlspecialchars(
                                        getNotificationMessage(
                                            $notification
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </p>


                            </div>



                            <!-- ACTIONS -->

                            <div
                                class="notification-actions"
                            >


                                <?php if (
                                    $isRead === 0 &&
                                    $notificationId !== ''
                                ): ?>


                                    <form
                                        method="POST"
                                    >


                                        <input
                                            type="hidden"
                                            name="action"
                                            value="mark_read"
                                        >


                                        <input
                                            type="hidden"
                                            name="notification_id"
                                            value="<?= htmlspecialchars(
                                                (string) $notificationId,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                        >


                                        <button
                                            type="submit"
                                            class="notification-action-btn"
                                            title="Mark as read"
                                        >

                                            <i class="fa-solid fa-check"></i>

                                        </button>


                                    </form>


                                <?php endif; ?>



                                <?php if (
                                    $notificationId !== ''
                                ): ?>


                                    <form
                                        method="POST"
                                        onsubmit="
                                            return confirm(
                                                'Delete this notification?'
                                            );
                                        "
                                    >


                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >


                                        <input
                                            type="hidden"
                                            name="notification_id"
                                            value="<?= htmlspecialchars(
                                                (string) $notificationId,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                        >


                                        <button
                                            type="submit"
                                            class="notification-action-btn delete"
                                            title="Delete notification"
                                        >

                                            <i class="fa-solid fa-trash"></i>

                                        </button>


                                    </form>


                                <?php endif; ?>


                            </div>


                        </div>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


        </section>



        <!-- FOOTER -->

        <footer class="dashboard-footer">


            <span>

                &copy; <?= date('Y') ?>

                CareerBridge - The Ultimate Career Management Platform

            </span>


        </footer>


    </main>


</div>


</body>

</html>