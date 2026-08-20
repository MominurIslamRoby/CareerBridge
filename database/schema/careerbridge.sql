CREATE DATABASE IF NOT EXISTS careerbridge_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE careerbridge_db;

-- ==========================================
-- USERS
-- Common authentication and account data
-- ==========================================
CREATE TABLE users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'employer', 'administrator') NOT NULL,
    account_status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);

-- ==========================================
-- STUDENTS
-- ==========================================
CREATE TABLE students (
    student_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    student_id_number VARCHAR(30) NOT NULL UNIQUE,
    university_name VARCHAR(200),
    department VARCHAR(150),
    academic_level VARCHAR(100),
    phone VARCHAR(30),
    location VARCHAR(150),
    career_summary TEXT,
    career_interests TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_students_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- ==========================================
-- EMPLOYERS
-- ==========================================
CREATE TABLE employers (
    employer_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    company_name VARCHAR(200) NOT NULL,
    company_description TEXT,
    industry VARCHAR(150),
    website VARCHAR(255),
    company_email VARCHAR(150),
    phone VARCHAR(30),
    address VARCHAR(255),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_employers_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- ==========================================
-- SKILLS
-- ==========================================
CREATE TABLE skills (
    skill_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    skill_name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- STUDENT SKILLS
-- Many-to-many relationship
-- ==========================================
CREATE TABLE student_skills (
    student_id INT UNSIGNED NOT NULL,
    skill_id INT UNSIGNED NOT NULL,
    proficiency_level ENUM('beginner', 'intermediate', 'advanced', 'expert')
        DEFAULT 'intermediate',

    PRIMARY KEY (student_id, skill_id),

    CONSTRAINT fk_student_skills_student
        FOREIGN KEY (student_id)
        REFERENCES students(student_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_student_skills_skill
        FOREIGN KEY (skill_id)
        REFERENCES skills(skill_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- ==========================================
-- RESUMES / CVs
-- ==========================================
CREATE TABLE resumes (
    resume_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,

    CONSTRAINT fk_resumes_student
        FOREIGN KEY (student_id)
        REFERENCES students(student_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- ==========================================
-- OPPORTUNITIES
-- Internship and job opportunities
-- ==========================================
CREATE TABLE opportunities (
    opportunity_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employer_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    opportunity_type ENUM('internship', 'job') NOT NULL,
    description TEXT NOT NULL,
    responsibilities TEXT,
    qualifications TEXT,
    location VARCHAR(150),
    duration VARCHAR(100),
    deadline DATE NOT NULL,
    status ENUM('draft', 'open', 'closed', 'filled') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_opportunities_employer
        FOREIGN KEY (employer_id)
        REFERENCES employers(employer_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- ==========================================
-- OPPORTUNITY SKILLS
-- Required skills for an opportunity
-- ==========================================
CREATE TABLE opportunity_skills (
    opportunity_id INT UNSIGNED NOT NULL,
    skill_id INT UNSIGNED NOT NULL,

    PRIMARY KEY (opportunity_id, skill_id),

    CONSTRAINT fk_opportunity_skills_opportunity
        FOREIGN KEY (opportunity_id)
        REFERENCES opportunities(opportunity_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_opportunity_skills_skill
        FOREIGN KEY (skill_id)
        REFERENCES skills(skill_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- ==========================================
-- APPLICATIONS
-- ==========================================
CREATE TABLE applications (
    application_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    opportunity_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    resume_id INT UNSIGNED,
    cover_letter TEXT,
    status ENUM(
        'submitted',
        'under_review',
        'shortlisted',
        'interview',
        'rejected',
        'selected'
    ) NOT NULL DEFAULT 'submitted',
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE (opportunity_id, student_id),

    CONSTRAINT fk_applications_opportunity
        FOREIGN KEY (opportunity_id)
        REFERENCES opportunities(opportunity_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_applications_student
        FOREIGN KEY (student_id)
        REFERENCES students(student_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_applications_resume
        FOREIGN KEY (resume_id)
        REFERENCES resumes(resume_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

-- ==========================================
-- INTERVIEWS
-- ==========================================
CREATE TABLE interviews (
    interview_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    interview_date DATETIME NOT NULL,
    interview_mode ENUM('online', 'offline') NOT NULL,
    interview_location VARCHAR(255),
    meeting_link VARCHAR(500),
    notes TEXT,
    status ENUM('scheduled', 'completed', 'cancelled', 'rescheduled')
        NOT NULL DEFAULT 'scheduled',
    outcome TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_interviews_application
        FOREIGN KEY (application_id)
        REFERENCES applications(application_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- ==========================================
-- SELECTIONS
-- ==========================================
CREATE TABLE selections (
    selection_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL UNIQUE,
    selected_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decision ENUM('selected', 'rejected') NOT NULL,
    remarks TEXT,

    CONSTRAINT fk_selections_application
        FOREIGN KEY (application_id)
        REFERENCES applications(application_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- ==========================================
-- INTERNSHIPS
-- ==========================================
CREATE TABLE internships (
    internship_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    selection_id INT UNSIGNED NOT NULL UNIQUE,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    internship_status ENUM(
        'upcoming',
        'ongoing',
        'completed',
        'cancelled'
    ) NOT NULL DEFAULT 'upcoming',
    responsibilities TEXT,
    progress_notes TEXT,
    completion_date DATE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_internships_selection
        FOREIGN KEY (selection_id)
        REFERENCES selections(selection_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- ==========================================
-- STUDENT EVALUATIONS
-- ==========================================
CREATE TABLE student_evaluations (
    student_evaluation_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    internship_id INT UNSIGNED NOT NULL UNIQUE,
    student_id INT UNSIGNED NOT NULL,
    rating DECIMAL(3,2),
    comments TEXT,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_student_rating
        CHECK (rating IS NULL OR (rating >= 0 AND rating <= 5)),

    CONSTRAINT fk_student_evaluations_internship
        FOREIGN KEY (internship_id)
        REFERENCES internships(internship_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_student_evaluations_student
        FOREIGN KEY (student_id)
        REFERENCES students(student_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- ==========================================
-- EMPLOYER EVALUATIONS
-- ==========================================
CREATE TABLE employer_evaluations (
    employer_evaluation_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    internship_id INT UNSIGNED NOT NULL UNIQUE,
    employer_id INT UNSIGNED NOT NULL,
    rating DECIMAL(3,2),
    comments TEXT,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_employer_rating
        CHECK (rating IS NULL OR (rating >= 0 AND rating <= 5)),

    CONSTRAINT fk_employer_evaluations_internship
        FOREIGN KEY (internship_id)
        REFERENCES internships(internship_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_employer_evaluations_employer
        FOREIGN KEY (employer_id)
        REFERENCES employers(employer_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- ==========================================
-- NOTIFICATIONS
-- ==========================================
CREATE TABLE notifications (
    notification_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    notification_type VARCHAR(100),
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- ==========================================
-- ANNOUNCEMENTS
-- ==========================================
CREATE TABLE announcements (
    announcement_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    administrator_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    published_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',

    CONSTRAINT fk_announcements_admin
        FOREIGN KEY (administrator_id)
        REFERENCES users(user_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);