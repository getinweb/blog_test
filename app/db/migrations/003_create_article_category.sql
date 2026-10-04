CREATE TABLE article_category (
    article_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (article_id, category_id),
    INDEX idx_article_category_category (category_id, article_id),
    CONSTRAINT fk_article_category_article FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE,
    CONSTRAINT fk_article_category_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
