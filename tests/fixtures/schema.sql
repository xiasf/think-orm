-- think-orm 测试 schema
-- 7 张表：users、posts、tags、posts_tags、comments、images、logs

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS posts_tags;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS images;
DROP TABLE IF EXISTS posts;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS logs;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    age INT,
    is_active TINYINT(1) DEFAULT 1,
    meta LONGTEXT,
    delete_time DATETIME NULL,
    create_time DATETIME NULL,
    update_time DATETIME NULL,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    body TEXT,
    status VARCHAR(20) DEFAULT 'draft',
    create_time DATETIME NULL,
    update_time DATETIME NULL,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE posts_tags (
    post_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (post_id, tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id INT UNSIGNED NOT NULL,
    commentable_type VARCHAR(50),
    commentable_id INT UNSIGNED,
    author VARCHAR(100),
    body TEXT,
    create_time DATETIME NULL,
    INDEX idx_post (post_id),
    INDEX idx_morph (commentable_type, commentable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    imageable_type VARCHAR(50) NOT NULL,
    imageable_id INT UNSIGNED NOT NULL,
    path VARCHAR(255) NOT NULL,
    INDEX idx_morph (imageable_type, imageable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE logs (
    log_date DATE NOT NULL,
    seq INT UNSIGNED NOT NULL,
    level VARCHAR(10),
    message TEXT,
    create_time DATETIME NULL,
    PRIMARY KEY (log_date, seq)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
