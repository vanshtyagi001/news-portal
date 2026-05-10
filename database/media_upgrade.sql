-- ============================================================
-- Express News — Media System Upgrade Migration
-- Run this once against your express_news_db database.
-- ============================================================

USE express_news_db;

-- Drop old media table and replace with the upgraded schema
-- (backs up old data first via rename if you want safety)
-- ALTER TABLE media RENAME TO media_backup_old;

DROP TABLE IF EXISTS media;

CREATE TABLE media (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,

    -- Core identity
    base_name       VARCHAR(100)    NOT NULL COMMENT 'Stem without extension: YYYYMMDDHHMMSS_hash',
    storage_path    VARCHAR(20)     NOT NULL COMMENT 'Relative date folder: YYYY/MM',
    original_name   VARCHAR(255)    NOT NULL DEFAULT '' COMMENT 'Original uploaded filename',
    mime_type       VARCHAR(100)    NOT NULL COMMENT 'Detected MIME type',
    file_size       BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Size of the primary file in bytes',

    -- Image variants (NULL = not generated / not applicable)
    has_thumbnail   TINYINT(1)      NOT NULL DEFAULT 0,
    has_medium      TINYINT(1)      NOT NULL DEFAULT 0,
    has_large       TINYINT(1)      NOT NULL DEFAULT 0,
    has_webp        TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'WebP fallback generated',
    image_width     SMALLINT UNSIGNED DEFAULT NULL,
    image_height    SMALLINT UNSIGNED DEFAULT NULL,

    -- Video
    video_thumb     VARCHAR(255)    DEFAULT NULL COMMENT 'Relative path to video poster frame',
    video_duration  SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Duration in seconds',

    -- Metadata (editable by admin)
    title           VARCHAR(255)    DEFAULT NULL,
    alt_text        VARCHAR(255)    DEFAULT NULL,
    caption         TEXT            DEFAULT NULL,
    description     TEXT            DEFAULT NULL,

    -- Housekeeping
    uploader_id     INT UNSIGNED    DEFAULT NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_media_v2_uploader
        FOREIGN KEY (uploader_id) REFERENCES admins(id)
        ON DELETE SET NULL ON UPDATE CASCADE,

    INDEX idx_media_created  (created_at),
    INDEX idx_media_mime     (mime_type(20)),
    INDEX idx_media_basename (base_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Centralised media library — v2 with multi-size image support';
