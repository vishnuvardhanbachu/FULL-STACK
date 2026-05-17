CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    job_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS saved_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_save (user_id, job_id)
);

-- ── Pro Features ──────────────────────────────────────────────
ALTER TABLE jobs ADD COLUMN IF NOT EXISTS is_featured TINYINT(1) DEFAULT 0;
ALTER TABLE jobs ADD COLUMN IF NOT EXISTS is_approved TINYINT(1) DEFAULT 1;
ALTER TABLE applications ADD COLUMN IF NOT EXISTS employer_notes TEXT;

-- ── Ultra-Premium Features ─────────────────────────────────────
ALTER TABLE users ADD COLUMN IF NOT EXISTS username VARCHAR(50) UNIQUE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS video_path VARCHAR(255);
ALTER TABLE users ADD COLUMN IF NOT EXISTS subscription_tier ENUM('free', 'pro', 'enterprise') DEFAULT 'free';
ALTER TABLE users ADD COLUMN IF NOT EXISTS credits INT DEFAULT 5;
ALTER TABLE users ADD COLUMN IF NOT EXISTS company_bio TEXT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS company_gallery TEXT; -- JSON array of image paths
ALTER TABLE users ADD COLUMN IF NOT EXISTS social_links TEXT;    -- JSON object

-- Verified Skill Assessments
CREATE TABLE IF NOT EXISTS assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    skill VARCHAR(100) NOT NULL,
    score INT NOT NULL,
    passed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Smart Interview Scheduler
CREATE TABLE IF NOT EXISTS interviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    slots_json TEXT NOT NULL, -- JSON array of suggested times
    confirmed_slot DATETIME,
    status ENUM('suggested', 'confirmed', 'completed', 'cancelled') DEFAULT 'suggested',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
);
