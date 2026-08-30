<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('student');

/*
|--------------------------------------------------------------------------
| Get Current Logged-in User
|--------------------------------------------------------------------------
*/

$user = currentUser();

$userId = (int) $user['id'];

/*
|--------------------------------------------------------------------------
| Get Notifications
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    '
    SELECT
        notification_id,
        title,
        message,
        notification_type,
        is_read,
        created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    '
);

$stmt->execute([$userId]);

$notifications = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Count Unread Notifications
|--------------------------------------------------------------------------
*/

$unreadStmt = $pdo->prepare(
    '
    SELECT COUNT(*) AS unread_count
    FROM notifications
    WHERE user_id = ?
    AND is_read = 0
    '
);

$unreadStmt->execute([$userId]);

$unreadData = $unreadStmt->fetch();

$unreadCount = (int) ($unreadData['unread_count'] ?? 0);

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

<?php if (!$notifications): ?>

    <p>
        You have no notifications.
    </p>

<?php else: ?>

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

                <strong>
                    Type:
                </strong>

                <?= htmlspecialchars(
                    $notification['notification_type'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </p>

            <p>

                <strong>
                    Status:
                </strong>

                <?php if ((int) $notification['is_read'] === 0): ?>

                    Unread

                <?php else: ?>

                    Read

                <?php endif; ?>

            </p>

            <p>

                <strong>
                    Received:
                </strong>

                <?= htmlspecialchars(
                    $notification['created_at'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </p>

        </article>

        <hr>

    <?php endforeach; ?>

<?php endif; ?>

</body>

</html>