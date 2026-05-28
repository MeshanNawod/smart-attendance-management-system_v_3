CREATE TABLE IF NOT EXISTS voice_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rate DECIMAL(3,1) NOT NULL DEFAULT 1.0,
    pitch DECIMAL(3,1) NOT NULL DEFAULT 1.0,
    voice_name VARCHAR(100) DEFAULT NULL,
    announce_student_name TINYINT(1) DEFAULT 1,
    announce_student_no TINYINT(1) DEFAULT 0,
    announce_modality TINYINT(1) DEFAULT 1,
    announce_course TINYINT(1) DEFAULT 0,
    announce_timestamp TINYINT(1) DEFAULT 0,
    announce_unknown_card TINYINT(1) DEFAULT 1,
    logs_columns VARCHAR(255) DEFAULT 'student_no,student_name,modality,course_code,timestamp',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO voice_settings (id) VALUES (1);
