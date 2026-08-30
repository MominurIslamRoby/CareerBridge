<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('student');

$user = currentUser();

$userId = (int) $user['id'];

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| Handle Notification Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /*
     * Mark one notification as read.
     */
    if ($action === 'mark_read') {

        $notificationId = filter_input(
            INPUT_POST,
            'notification_id',
            FILTER_VALIDATE_INT
        );

        if (!$notificationId) {

            $error = 'Invalid notification.';

        } else {

            $updateStmt = $pdo->prepare(
                'UPDATE notifications
                 SET is_read = 1
                 WHERE notification_id = ?
                   AND user_id = ?
                   AND is_read = 0'
            );

            $updateStmt->execute([
                $notificationId,
                $userId
            ]);

            if ($updateStmt->rowCount() > 0) {
                $success = 'Notification marked as read.';
            } else {
                $error = 'Notification could not be updated.';
            }
        }
    }

    /*
     * Mark all notifications as read.
     */
    elseif ($action === 'mark_all_read') {

        $updateAllStmt = $pdo->prepare(
            'UPDATE notifications
             SET is_read = 1
             WHERE user_id = ?
               AND is_read = 0'
        );

        $updateAllStmt->execute([
            $userId
        ]);

        if ($updateAllStmt->rowCount() > 0) {
            $success = 'All notifications marked as read.';
        } else {
            $success = 'There are no unread notifications.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Get Unread Notification Count
|--------------------------------------------------------------------------
*/

$unreadStmt = $pdo->prepare(
    'SELECT COUNT(*) AS unread_count
     FROM notifications
     WHERE user_id = ?
       AND is_read = 0'
);

$unreadStmt->execute([
    $userId
]);

$unreadResult = $unreadStmt->fetch();

$unreadCount = (int) (
    $unreadResult['unread_count'] ?? 0
);

/*
|--------------------------------------------------------------------------
| Get All Notifications
|--------------------------------------------------------------------------
*/

$notificationStmt = $pdo->prepare(
    'SELECT
        notification_id,
        title,
        message,
        notification_type,
        is_read,
        created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC, notification_id DESC'
);

$notificationStmt->execute([
    $userId
]);

$notifications = $notificationStmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Notifications | CareerBridge</title>

</head>

<body>

<h1>Notifications</h1>

<p>

    <a href="dashboard.php">
        Dashboard
    </a>

    |

    <a href="opportunities.php">
        Opportunities
    </a>

    |

    <a href="applications.php">
        My Applications
    </a>

    |

    <a href="profile.php">
        Career Profile
    </a>

    |

    <a href="skills.php">
        Skills
    </a>

    |

    <a href="resume.php">
        Resume / CV
    </a>

    |

    <a href="../auth/logout.php">
        Logout
    </a>

</p>

<hr>

<h2>Notification Center</h2>

<p>
    <strong>
        Unread Notifications:
    </strong>

    <?= $unreadCount ?>
</p>

<?php if ($error !== ''): ?>

    <p>
        <strong>
            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>
    </p>

<?php endif; ?>

<?php if ($success !== ''): ?>

    <p>
        <strong>
            <?= htmlspecialchars(
                $success,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>
    </p>

<?php endif; ?>

<?php if ($notifications): ?>

    <?php if ($unreadCount > 0): ?>

        <form method="POST" action="">

            <input
                type="hidden"
                name="action"
                value="mark_all_read"
            >

            <button type="submit">
                Mark All as Read
            </button>

        </form>

        <br>

    <?php endif; ?>

    <?php foreach ($notifications as $notification): ?>

        <article>

            <h3>

                <?php if ((int) $notification['is_read'] === 0): ?>

                    🔵

                <?php endif; ?>

                <?= htmlspecialchars(
                    $notification['title'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </h3>

            <p>
                <?= nl2br(
                    htmlspecialchars(
                        $notification['message'],
                        ENT_QUOTES,
                        'UTF-8'
                    )
                ) ?>
            </p>

            <p>
                <strong>Type:</strong>

                <?= htmlspecialchars(
                    ucfirst(
                        $notification['notification_type']
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

            <p>
                <strong>Received:</strong>

                <?= htmlspecialchars(
                    $notification['created_at'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

            <p>
                <strong>Status:</strong>

                <?php if ((int) $notification['is_read'] === 0): ?>

                    Unread

                <?php else: ?>

                    Read

                <?php endif; ?>

            </p>

            <?php if ((int) $notification['is_read'] === 0): ?>

                <form method="POST" action="">

                    <input
                        type="hidden"
                        name="action"
                        value="mark_read"
                    >

                    <input
                        type="hidden"
                        name="notification_id"
                        value="<?= (int) $notification['notification_id'] ?>"
                    >

                    <button type="submit">
                        Mark as Read
                    </button>

                </form>

            <?php endif; ?>

        </article>

        <hr>

    <?php endforeach; ?>

<?php else: ?>

    <p>
        You have no notifications.
    </p>

<?php endif; ?>

</body>

</html>