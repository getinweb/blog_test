CREATE TABLE articles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    body LONGTEXT NOT NULL,
    published_at DATETIME NOT NULL COMMENT 'UTC',
    views BIGINT NOT NULL DEFAULT 0,
    CONSTRAINT chk_articles_views_nonnegative CHECK (views >= 0),
    INDEX idx_articles_published (published_at DESC, id DESC),
    INDEX idx_articles_views (views DESC, published_at DESC, id DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
