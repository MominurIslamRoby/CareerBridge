<?php
session_start();

$conn = new mysqli("localhost", "root", "", "careerbridge_db");

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
            s.student_id,
            s.user_id,
            s.student_id_number,
            s.university_name,
            s.department,
            s.academic_level,
            s.phone,
            s.location,
            s.career_summary,
            s.career_interests,
            s.created_at,
            u.full_name,
            u.email,
            u.account_status
        FROM students s
        INNER JOIN users u ON s.user_id = u.user_id
        WHERE
            u.full_name LIKE ?
            OR u.email LIKE ?
            OR s.student_id_number LIKE ?
            OR s.university_name LIKE ?
            OR s.department LIKE ?
            OR s.academic_level LIKE ?
        ORDER BY u.full_name ASC"
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
    $students = $stmt->get_result();
} else {
    $students = $conn->query(
        "SELECT
            s.student_id,
            s.user_id,
            s.student_id_number,
            s.university_name,
            s.department,
            s.academic_level,
            s.phone,
            s.location,
            s.career_summary,
            s.career_interests,
            s.created_at,
            u.full_name,
            u.email,
            u.account_status
        FROM students s
        INNER JOIN users u ON s.user_id = u.user_id
        ORDER BY u.full_name ASC"
    );
}

$totalStudents = 0;

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM students"
);

if ($result) {
    $totalStudents = (int)$result->fetch_assoc()['total'];
}

$activeStudents = 0;

$result = $conn->query(
    "SELECT COUNT(*) AS total
    FROM students s
    INNER JOIN users u ON s.user_id = u.user_id
    WHERE u.account_status = 'active'"
);

if ($result) {
    $activeStudents = (int)$result->fetch_assoc()['total'];
}

$inactiveStudents = 0;

$result = $conn->query(
    "SELECT COUNT(*) AS total
    FROM students s
    INNER JOIN users u ON s.user_id = u.user_id
    WHERE u.account_status = 'inactive'"
);

if ($result) {
    $inactiveStudents = (int)$result->fetch_assoc()['total'];
}

$suspendedStudents = 0;

$result = $conn->query(
    "SELECT COUNT(*) AS total
    FROM students s
    INNER JOIN users u ON s.user_id = u.user_id
    WHERE u.account_status = 'suspended'"
);

if ($result) {
    $suspendedStudents = (int)$result->fetch_assoc()['total'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Students Management - CareerBridge</title>
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

.student-name {
    font-weight: bold;
    margin-bottom: 4px;
}

.student-email {
    color: #888;
    font-size: 12px;
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

@media (max-width: 1100px) {
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
        <span>Administrator Panel</span>
    </div>
    <a href="dashboard.php">Dashboard</a>
    <a href="students.php" class="active">Students</a>
    <a href="employers.php">Employers</a>
    <a href="opportunities.php">Opportunities</a>
    <a href="applications.php">Applications</a>
    <a href="../auth/logout.php">Logout</a>
</div>

<div class="main">
    <div class="header">
        <div>
            <h1>Students Management</h1>
            <p>View registered students and their account information.</p>
        </div>
        <div class="admin-name">Administrator</div>
    </div>

    <div class="stats">
        <div class="stat-card">
            <h3>Total Students</h3>
            <div class="stat-number"><?php echo $totalStudents; ?></div>
        </div>
        <div class="stat-card">
            <h3>Active Students</h3>
            <div class="stat-number"><?php echo $activeStudents; ?></div>
        </div>
        <div class="stat-card">
            <h3>Inactive Students</h3>
            <div class="stat-number"><?php echo $inactiveStudents; ?></div>
        </div>
        <div class="stat-card">
            <h3>Suspended Students</h3>
            <div class="stat-number"><?php echo $suspendedStudents; ?></div>
        </div>
    </div>

    <div class="search-card">
        <form method="GET" class="search-form">
            <input
                type="text"
                name="search"
                placeholder="Search by name, email, student ID, university, department or academic level..."
                value="<?php echo htmlspecialchars($search); ?>"
            >
            <button type="submit">Search</button>
            <?php if ($search !== "") { ?>
                <a href="students.php" class="clear-button">Clear</a>
            <?php } ?>
        </form>
    </div>

    <div class="table-card">
        <div class="table-header">
            <h2>
                <?php
                if ($search !== "") {
                    echo "Search Results";
                } else {
                    echo "Registered Students";
                }
                ?>
            </h2>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Student ID</th>
                    <th>University</th>
                    <th>Department</th>
                    <th>Academic Level</th>
                    <th>Phone</th>
                    <th>Location</th>
                    <th>Account Status</th>
                    <th>Registered</th>
                </tr>
            </thead>
            <tbody>
            <?php
            if ($students && $students->num_rows > 0) {
                while ($student = $students->fetch_assoc()) {
                    $status = strtolower($student['account_status']);
            ?>
                <tr>
                    <td>
                        <div class="student-name">
                            <?php echo htmlspecialchars($student['full_name']); ?>
                        </div>
                        <div class="student-email">
                            <?php echo htmlspecialchars($student['email']); ?>
                        </div>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($student['student_id_number']); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($student['university_name'] ?? '-'); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($student['department'] ?? '-'); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($student['academic_level'] ?? '-'); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($student['phone'] ?? '-'); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($student['location'] ?? '-'); ?>
                    </td>
                    <td>
                        <span class="status status-<?php echo htmlspecialchars($status); ?>">
                            <?php echo htmlspecialchars($student['account_status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        if (!empty($student['created_at'])) {
                            echo date("d M Y", strtotime($student['created_at']));
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
                    <td colspan="9" class="empty">
                        <h3>No students found</h3>
                        <p>
                            <?php
                            if ($search !== "") {
                                echo "No students match your search.";
                            } else {
                                echo "There are currently no registered students.";
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
