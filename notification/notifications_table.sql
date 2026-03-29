-- Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    actor_id INT NULL,
    related_document_id INT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'info',
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    url VARCHAR(255) NOT NULL DEFAULT '#',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX(user_id),
    INDEX(is_read),
    INDEX(created_at)
);
