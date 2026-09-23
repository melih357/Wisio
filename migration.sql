USE wisio;

-- Wisio Revize 4.1 - MySQL 5.7 / 8.0 uyumlu güvenli geçiş
SET FOREIGN_KEY_CHECKS=0;

-- users.username
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='username');
SET @sql := IF(@exists=0,'ALTER TABLE users ADD COLUMN username VARCHAR(30) NULL AFTER name','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
UPDATE users SET username=CONCAT('kullanici.',id) WHERE username IS NULL OR TRIM(username)='';
UPDATE users u JOIN (SELECT username,MIN(id) keep_id FROM users GROUP BY username HAVING COUNT(*)>1) d ON d.username=u.username AND u.id<>d.keep_id SET u.username=CONCAT(LEFT(u.username,20),'.',u.id);
ALTER TABLE users MODIFY username VARCHAR(30) NOT NULL;
SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND INDEX_NAME='uq_users_username');
SET @sql := IF(@exists=0,'ALTER TABLE users ADD UNIQUE KEY uq_users_username(username)','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Benzersiz topluluk adı
UPDATE communities c JOIN (SELECT name,MIN(id) keep_id FROM communities GROUP BY name HAVING COUNT(*)>1) d ON d.name=c.name AND c.id<>d.keep_id SET c.name=CONCAT(LEFT(c.name,130),' #',c.id);
SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='communities' AND INDEX_NAME='uq_communities_name');
SET @sql := IF(@exists=0,'ALTER TABLE communities ADD UNIQUE KEY uq_communities_name(name)','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Topluluk şifresi
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='communities' AND COLUMN_NAME='join_password_hash');
SET @sql := IF(@exists=0,'ALTER TABLE communities ADD COLUMN join_password_hash VARCHAR(255) NULL AFTER join_method','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Gelişmiş soru alanları (ADD COLUMN IF NOT EXISTS kullanılmaz; WAMP MySQL 5.7 uyumludur)
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='questions' AND COLUMN_NAME='urgency');
SET @sql := IF(@exists=0,"ALTER TABLE questions ADD COLUMN urgency ENUM('low','normal','high') DEFAULT 'normal' AFTER status",'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='questions' AND COLUMN_NAME='expected_answer_type');
SET @sql := IF(@exists=0,"ALTER TABLE questions ADD COLUMN expected_answer_type VARCHAR(30) DEFAULT 'any' AFTER urgency",'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='questions' AND COLUMN_NAME='target_count');
SET @sql := IF(@exists=0,"ALTER TABLE questions ADD COLUMN target_count TINYINT UNSIGNED DEFAULT 3 AFTER expected_answer_type",'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='questions' AND COLUMN_NAME='allow_public_fallback');
SET @sql := IF(@exists=0,"ALTER TABLE questions ADD COLUMN allow_public_fallback TINYINT(1) DEFAULT 1 AFTER target_count",'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Duyurular
CREATE TABLE IF NOT EXISTS announcements(
 id INT AUTO_INCREMENT PRIMARY KEY,
 community_id INT NOT NULL,
 author_id INT NOT NULL,
 title VARCHAR(180) NOT NULL,
 content TEXT NOT NULL,
 is_pinned TINYINT(1) DEFAULT 0,
 status ENUM('published','archived') DEFAULT 'published',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_announcements_community(community_id,status,created_at),
 CONSTRAINT fk_announcements_community FOREIGN KEY(community_id) REFERENCES communities(id) ON DELETE CASCADE,
 CONSTRAINT fk_announcements_author FOREIGN KEY(author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS=1;
