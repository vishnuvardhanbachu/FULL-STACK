-- ============================================================
-- Job Portal Management System — Database Schema
-- Run this in phpMyAdmin or MySQL CLI
-- ============================================================

CREATE DATABASE IF NOT EXISTS jobportal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE jobportal;

-- ── Users ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100)  NOT NULL,
    email           VARCHAR(150)  UNIQUE NOT NULL,
    password_hash   VARCHAR(255)  NOT NULL,
    role            ENUM('student','employer','admin') NOT NULL DEFAULT 'student',
    phone           VARCHAR(20),
    location        VARCHAR(100),
    bio             TEXT,
    skills          TEXT,
    experience_level ENUM('fresher','junior','mid','senior') DEFAULT 'fresher',
    resume_path     VARCHAR(255),
    company_name    VARCHAR(150),
    company_website VARCHAR(255),
    company_size    VARCHAR(50),
    industry        VARCHAR(100),
    profile_pic     VARCHAR(255),
    is_active       TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── Jobs ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS jobs (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    employer_id      INT          NOT NULL,
    title            VARCHAR(200) NOT NULL,
    description      TEXT         NOT NULL,
    requirements     TEXT,
    skills_required  TEXT,
    category         VARCHAR(100),
    location         VARCHAR(100),
    salary_min       INT          DEFAULT 0,
    salary_max       INT          DEFAULT 0,
    experience_level ENUM('any','fresher','junior','mid','senior') DEFAULT 'any',
    job_type         ENUM('full-time','part-time','internship','contract','remote') DEFAULT 'full-time',
    status           ENUM('active','closed','draft') DEFAULT 'active',
    views            INT          DEFAULT 0,
    deadline         DATE,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ── Applications ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS applications (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    job_id       INT  NOT NULL,
    student_id   INT  NOT NULL,
    status       ENUM('pending','shortlisted','rejected','hired') DEFAULT 'pending',
    cover_letter TEXT,
    applied_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id)     REFERENCES jobs(id)  ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_application (job_id, student_id)
);

-- ── Notifications ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    title      VARCHAR(200),
    message    TEXT NOT NULL,
    type       ENUM('info','success','warning','danger') DEFAULT 'info',
    link       VARCHAR(255),
    is_read    TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ── Seed: Default Admin ───────────────────────────────────────
-- Password: Admin@123
INSERT INTO users (name, email, password_hash, role) VALUES
('System Admin', 'admin@jobportal.com',
 '$2y$10$TKh8H1.PfUsAam9zCodeRuOiCa1yZ8j/.5GQSH.K.4b7pVQa1VsSi',
 'admin')
ON DUPLICATE KEY UPDATE name = name;

-- ── Seed: Sample Employers ────────────────────────────────────
INSERT INTO users (name, email, password_hash, role, company_name, company_website, location, industry) VALUES
('TechCorp Recruiter', 'hr@techcorp.com',
 '$2y$10$TKh8H1.PfUsAam9zCodeRuOiCa1yZ8j/.5GQSH.K.4b7pVQa1VsSi',
 'employer', 'TechCorp Solutions', 'https://techcorp.com', 'Bangalore', 'Information Technology'),
('InnovateLab HR', 'hr@innovatelab.com',
 '$2y$10$TKh8H1.PfUsAam9zCodeRuOiCa1yZ8j/.5GQSH.K.4b7pVQa1VsSi',
 'employer', 'InnovateLab', 'https://innovatelab.io', 'Mumbai', 'Product & SaaS'),
('FinEdge Talent', 'talent@finedge.com',
 '$2y$10$TKh8H1.PfUsAam9zCodeRuOiCa1yZ8j/.5GQSH.K.4b7pVQa1VsSi',
 'employer', 'FinEdge Analytics', 'https://finedge.in', 'Hyderabad', 'Finance & Banking')
ON DUPLICATE KEY UPDATE name = name;

-- ── Seed: Sample Jobs ─────────────────────────────────────────
INSERT INTO jobs (employer_id, title, description, requirements, skills_required, category, location, salary_min, salary_max, experience_level, job_type, status, deadline) VALUES
(2, 'Full Stack Developer', 
 'We are looking for a talented Full Stack Developer to join our growing engineering team. You will work on exciting products used by millions of users.',
 'B.Tech/BE in Computer Science or equivalent. Strong problem-solving skills required.',
 'React, Node.js, MySQL, REST APIs, Git',
 'Engineering', 'Bangalore', 600000, 1200000, 'junior', 'full-time', 'active', DATE_ADD(NOW(), INTERVAL 30 DAY)),

(2, 'Frontend React Developer',
 'Join our product team as a Frontend Developer. You will build responsive, performant UI components and collaborate closely with designers.',
 'Strong understanding of HTML, CSS, and JavaScript fundamentals.',
 'React.js, TypeScript, CSS3, Figma',
 'Engineering', 'Remote', 500000, 900000, 'junior', 'remote', 'active', DATE_ADD(NOW(), INTERVAL 25 DAY)),

(3, 'Product Manager — Growth',
 'Lead product strategy for our growth initiatives. Drive experimentation, define metrics, and work cross-functionally.',
 '3+ years of product management experience preferred.',
 'Product Strategy, SQL, A/B Testing, Agile, JIRA',
 'Product', 'Mumbai', 1200000, 2000000, 'mid', 'full-time', 'active', DATE_ADD(NOW(), INTERVAL 20 DAY)),

(4, 'Data Analyst Intern',
 'Six-month paid internship to work with our data team on market analysis and financial modelling.',
 'Pursuing B.Tech/BCA/MBA with statistics background.',
 'Python, Excel, Power BI, SQL',
 'Analytics', 'Hyderabad', 15000, 25000, 'fresher', 'internship', 'active', DATE_ADD(NOW(), INTERVAL 15 DAY)),

(3, 'UI/UX Designer',
 'Design delightful user experiences for our web and mobile products. You will own end-to-end design from wireframes to final handoff.',
 'Portfolio of shipped projects required.',
 'Figma, Adobe XD, Prototyping, User Research',
 'Design', 'Mumbai', 700000, 1300000, 'mid', 'full-time', 'active', DATE_ADD(NOW(), INTERVAL 30 DAY)),

(2, 'DevOps Engineer',
 'Manage and scale our cloud infrastructure. Automate CI/CD pipelines and ensure 99.9% uptime.',
 'Experience with AWS or GCP is mandatory.',
 'AWS, Docker, Kubernetes, Terraform, Jenkins',
 'Engineering', 'Bangalore', 900000, 1800000, 'senior', 'full-time', 'active', DATE_ADD(NOW(), INTERVAL 28 DAY));
