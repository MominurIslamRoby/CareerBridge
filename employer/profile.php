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
| Get Current User ID
|--------------------------------------------------------------------------
*/

$currentUserId = null;

if (isset($user['user_id'])) {
    $currentUserId = (int) $user['user_id'];
} elseif (isset($user['id'])) {
    $currentUserId = (int) $user['id'];
}


/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

$successMessage = '';
$errorMessage = '';


/*
|--------------------------------------------------------------------------
| Get Employer Profile
|--------------------------------------------------------------------------
*/

$employerProfile = null;

if ($currentUserId !== null && $currentUserId > 0) {

    try {

        $stmt = $pdo->prepare("
            SELECT *
            FROM employers
            WHERE user_id = ?
            LIMIT 1
        ");

        $stmt->execute([$currentUserId]);

        $employerProfile = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {

        $errorMessage = 'Unable to load employer profile.';
    }
}


/*
|--------------------------------------------------------------------------
| Handle Profile Update
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($currentUserId === null || $currentUserId <= 0) {

        $errorMessage = 'Unable to identify the current user.';

    } elseif (!$employerProfile) {

        $errorMessage = 'Employer profile was not found.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Get Form Data
        |--------------------------------------------------------------------------
        */

        $companyName = trim($_POST['company_name'] ?? '');
        $companyDescription = trim($_POST['company_description'] ?? '');
        $industry = trim($_POST['industry'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $companyEmail = trim($_POST['company_email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if ($companyName === '') {

            $errorMessage = 'Company name is required.';

        } elseif (
            $companyEmail !== '' &&
            !filter_var($companyEmail, FILTER_VALIDATE_EMAIL)
        ) {

            $errorMessage = 'Please enter a valid company email address.';

        } elseif (
            $website !== '' &&
            !filter_var($website, FILTER_VALIDATE_URL)
        ) {

            $errorMessage = 'Please enter a valid website URL.';

        } else {

            try {

                /*
                |--------------------------------------------------------------------------
                | Update Employer Profile
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    UPDATE employers
                    SET
                        company_name = ?,
                        company_description = ?,
                        industry = ?,
                        website = ?,
                        company_email = ?,
                        phone = ?,
                        address = ?
                    WHERE user_id = ?
                ");

                $stmt->execute([
                    $companyName,
                    $companyDescription,
                    $industry,
                    $website,
                    $companyEmail,
                    $phone,
                    $address,
                    $currentUserId
                ]);


                /*
                |--------------------------------------------------------------------------
                | Refresh Employer Profile
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT *
                    FROM employers
                    WHERE user_id = ?
                    LIMIT 1
                ");

                $stmt->execute([$currentUserId]);

                $employerProfile = $stmt->fetch(PDO::FETCH_ASSOC);


                $successMessage =
                    'Company profile updated successfully.';

            } catch (PDOException $e) {

                $errorMessage =
                    'Unable to update company profile. Please try again.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Helper Function
|--------------------------------------------------------------------------
*/

function profileValue(
    ?array $profile,
    string $field,
    string $default = ''
): string {

    if (
        $profile !== null &&
        isset($profile[$field]) &&
        $profile[$field] !== null
    ) {
        return (string) $profile[$field];
    }

    return $default;
}


/*
|--------------------------------------------------------------------------
| Display Information
|--------------------------------------------------------------------------
*/

$companyName = profileValue(
    $employerProfile,
    'company_name'
);

if ($companyName === '') {

    $companyName = $user['full_name'] ?? 'Employer';
}


$displayName = $user['full_name'] ?? 'Employer';


$avatarLetter = strtoupper(
    substr($companyName, 0, 1)
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
        Company Profile | CareerBridge
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


            <div class="brand-icon">
                CB
            </div>


            <div class="brand-content">

                <h1>
                    CareerBridge
                </h1>

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
                class="nav-link active"
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
                class="nav-link"
            >

                <i class="fa-solid fa-bell"></i>

                <span>
                    Notifications
                </span>

            </a>


        </nav>


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


        <!-- PAGE HEADER -->

        <header class="top-header">


            <div>


                <p class="breadcrumb">
                    EMPLOYER PORTAL / COMPANY PROFILE
                </p>


                <h2>
                    Company Profile
                </h2>


                <p class="welcome-text">
                    Manage your company information and employer profile.
                </p>


            </div>


            <div class="user-info">


                <div class="user-avatar">

                    <?= htmlspecialchars(
                        $avatarLetter,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>


                <div>

                    <strong>

                        <?= htmlspecialchars(
                            $displayName,
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
             SUCCESS MESSAGE
        ====================================== -->

        <?php if ($successMessage !== ''): ?>

            <div class="profile-alert success">

                <i class="fa-solid fa-circle-check"></i>

                <?= htmlspecialchars(
                    $successMessage,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>



        <!-- =====================================
             ERROR MESSAGE
        ====================================== -->

        <?php if ($errorMessage !== ''): ?>

            <div class="profile-alert error">

                <i class="fa-solid fa-circle-exclamation"></i>

                <?= htmlspecialchars(
                    $errorMessage,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>



        <!-- =====================================
             COMPANY PROFILE HERO
        ====================================== -->

        <section class="profile-hero-card">


            <div class="company-avatar">

                <?= htmlspecialchars(
                    $avatarLetter,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>


            <div class="company-hero-info">


                <p class="section-label">
                    EMPLOYER ACCOUNT
                </p>


                <h2>

                    <?= htmlspecialchars(
                        $companyName,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </h2>


                <p>

                    <?= htmlspecialchars(
                        profileValue(
                            $employerProfile,
                            'industry',
                            'Company information'
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </p>


                <?php if (
                    profileValue(
                        $employerProfile,
                        'website'
                    ) !== ''
                ): ?>

                    <span class="company-website">

                        <i class="fa-solid fa-globe"></i>

                        <?= htmlspecialchars(
                            profileValue(
                                $employerProfile,
                                'website'
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </span>

                <?php endif; ?>


            </div>


        </section>



        <!-- =====================================
             EDIT COMPANY PROFILE
        ====================================== -->

        <section class="dashboard-card profile-form-card">


            <div class="card-header">


                <div>


                    <p class="section-label">
                        COMPANY INFORMATION
                    </p>


                    <h3>
                        Edit Company Profile
                    </h3>


                </div>


                <span class="card-label">

                    <i class="fa-solid fa-pen-to-square"></i>

                </span>


            </div>



            <?php if (!$employerProfile): ?>


                <div class="notification-empty">


                    <div class="notification-placeholder">

                        <i class="fa-solid fa-building-circle-exclamation"></i>

                    </div>


                    <h4>
                        Employer profile not found
                    </h4>


                    <p>
                        Your employer account exists, but no company profile
                        record was found in the database.
                    </p>


                </div>


            <?php else: ?>


                <form
                    method="POST"
                    class="profile-form"
                >


                    <div class="profile-form-grid">


                        <!-- Company Name -->

                        <div class="form-group">

                            <label for="company_name">
                                Company Name
                            </label>


                            <input
                                type="text"
                                id="company_name"
                                name="company_name"
                                required
                                value="<?= htmlspecialchars(
                                    profileValue(
                                        $employerProfile,
                                        'company_name'
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                        </div>



                        <!-- Industry -->

                        <div class="form-group">

                            <label for="industry">
                                Industry
                            </label>


                            <input
                                type="text"
                                id="industry"
                                name="industry"
                                placeholder="e.g. Information Technology"
                                value="<?= htmlspecialchars(
                                    profileValue(
                                        $employerProfile,
                                        'industry'
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                        </div>



                        <!-- Company Email -->

                        <div class="form-group">

                            <label for="company_email">
                                Company Email
                            </label>


                            <input
                                type="email"
                                id="company_email"
                                name="company_email"
                                placeholder="company@example.com"
                                value="<?= htmlspecialchars(
                                    profileValue(
                                        $employerProfile,
                                        'company_email'
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                        </div>



                        <!-- Phone -->

                        <div class="form-group">

                            <label for="phone">
                                Phone Number
                            </label>


                            <input
                                type="text"
                                id="phone"
                                name="phone"
                                placeholder="+880 1XXXXXXXXX"
                                value="<?= htmlspecialchars(
                                    profileValue(
                                        $employerProfile,
                                        'phone'
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                        </div>



                        <!-- Website -->

                        <div class="form-group">

                            <label for="website">
                                Website
                            </label>


                            <input
                                type="url"
                                id="website"
                                name="website"
                                placeholder="https://example.com"
                                value="<?= htmlspecialchars(
                                    profileValue(
                                        $employerProfile,
                                        'website'
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                        </div>


                    </div>



                    <!-- Address -->

                    <div class="form-group full-width">

                        <label for="address">
                            Company Address
                        </label>


                        <input
                            type="text"
                            id="address"
                            name="address"
                            placeholder="Enter your company address"
                            value="<?= htmlspecialchars(
                                profileValue(
                                    $employerProfile,
                                    'address'
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                    </div>



                    <!-- Company Description -->

                    <div class="form-group full-width">

                        <label for="company_description">
                            Company Description
                        </label>


                        <textarea
                            id="company_description"
                            name="company_description"
                            rows="6"
                            placeholder="Tell students about your company..."
                        ><?= htmlspecialchars(
                            profileValue(
                                $employerProfile,
                                'company_description'
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?></textarea>

                    </div>



                    <!-- FORM ACTIONS -->

                    <div class="profile-form-actions">


                        <a
                            href="dashboard.php"
                            class="secondary-button"
                        >

                            <i class="fa-solid fa-arrow-left"></i>

                            Back to Dashboard

                        </a>


                        <button
                            type="submit"
                            class="primary-button"
                        >

                            <i class="fa-solid fa-floppy-disk"></i>

                            Save Changes

                        </button>


                    </div>


                </form>


            <?php endif; ?>


        </section>



        <!-- =====================================
             ACCOUNT INFORMATION
        ====================================== -->

        <section class="dashboard-card account-info-card">


            <div class="card-header">


                <div>


                    <p class="section-label">
                        ACCOUNT
                    </p>


                    <h3>
                        Account Information
                    </h3>


                </div>


                <span class="card-label">

                    <i class="fa-solid fa-user-shield"></i>

                </span>


            </div>



            <div class="profile-details">


                <!-- Account Name -->

                <div class="profile-detail">


                    <div class="profile-detail-label">

                        <i class="fa-solid fa-user"></i>

                        <span>
                            Account Name
                        </span>

                    </div>


                    <strong>

                        <?= htmlspecialchars(
                            $displayName,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </strong>


                </div>



                <!-- Email -->

                <div class="profile-detail">


                    <div class="profile-detail-label">

                        <i class="fa-solid fa-envelope"></i>

                        <span>
                            Account Email
                        </span>

                    </div>


                    <strong>

                        <?= htmlspecialchars(
                            $user['email']
                            ?? 'Not available',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </strong>


                </div>



                <!-- Role -->

                <div class="profile-detail">


                    <div class="profile-detail-label">

                        <i class="fa-solid fa-user-tag"></i>

                        <span>
                            Account Role
                        </span>

                    </div>


                    <strong>
                        Employer
                    </strong>


                </div>


            </div>


        </section>



        <!-- =====================================
             FOOTER
        ====================================== -->

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