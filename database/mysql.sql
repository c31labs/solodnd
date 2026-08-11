CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(254) NOT NULL UNIQUE,
    display_name VARCHAR(60) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    session_version INT UNSIGNED NOT NULL DEFAULT 0,
    email_verified_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    last_login_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    purpose ENUM('verify_email', 'password_reset') NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    INDEX auth_tokens_lookup_idx (token_hash, purpose, used_at, expires_at),
    INDEX auth_tokens_user_idx (user_id, purpose),
    CONSTRAINT auth_tokens_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE adventures (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    title VARCHAR(140) NOT NULL,
    current_scene INT UNSIGNED NOT NULL DEFAULT 1,
    chaos_factor TINYINT UNSIGNED NOT NULL DEFAULT 5,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT adventures_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE characters (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    adventure_id BIGINT UNSIGNED NOT NULL UNIQUE,
    name VARCHAR(80) NOT NULL,
    class_name VARCHAR(30) NOT NULL,
    species VARCHAR(30) NOT NULL,
    background VARCHAR(30) NOT NULL,
    alignment VARCHAR(30) NOT NULL,
    level TINYINT UNSIGNED NOT NULL DEFAULT 1,
    xp INT UNSIGNED NOT NULL DEFAULT 0,
    hp_current SMALLINT NOT NULL,
    hp_max SMALLINT NOT NULL,
    armor_class TINYINT UNSIGNED NOT NULL,
    speed SMALLINT UNSIGNED NOT NULL,
    strength TINYINT UNSIGNED NOT NULL,
    dexterity TINYINT UNSIGNED NOT NULL,
    constitution TINYINT UNSIGNED NOT NULL,
    intelligence TINYINT UNSIGNED NOT NULL,
    wisdom TINYINT UNSIGNED NOT NULL,
    charisma TINYINT UNSIGNED NOT NULL,
    notes TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT characters_adventure_fk FOREIGN KEY (adventure_id) REFERENCES adventures(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE journal_entries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    adventure_id BIGINT UNSIGNED NOT NULL,
    entry_type VARCHAR(24) NOT NULL,
    title VARCHAR(140) NOT NULL,
    body TEXT NOT NULL,
    metadata JSON NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX journal_adventure_idx (adventure_id, id),
    CONSTRAINT journal_adventure_fk FOREIGN KEY (adventure_id) REFERENCES adventures(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE oracle_rolls (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    adventure_id BIGINT UNSIGNED NOT NULL,
    question VARCHAR(300) NOT NULL,
    odds VARCHAR(20) NOT NULL,
    roll TINYINT UNSIGNED NOT NULL,
    threshold_value TINYINT UNSIGNED NOT NULL,
    answer VARCHAR(24) NOT NULL,
    exceptional TINYINT(1) NOT NULL DEFAULT 0,
    event_triggered TINYINT(1) NOT NULL DEFAULT 0,
    event_focus VARCHAR(100) NOT NULL DEFAULT '',
    event_meaning VARCHAR(100) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    INDEX oracle_adventure_idx (adventure_id, id),
    CONSTRAINT oracle_adventure_fk FOREIGN KEY (adventure_id) REFERENCES adventures(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE threads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    adventure_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(180) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    CONSTRAINT threads_adventure_fk FOREIGN KEY (adventure_id) REFERENCES adventures(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE npcs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    adventure_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    role VARCHAR(300) NOT NULL DEFAULT '',
    status VARCHAR(30) NOT NULL DEFAULT 'unknown',
    created_at DATETIME NOT NULL,
    CONSTRAINT npcs_adventure_fk FOREIGN KEY (adventure_id) REFERENCES adventures(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    key_hash CHAR(64) NOT NULL PRIMARY KEY,
    attempt_count SMALLINT UNSIGNED NOT NULL,
    window_started DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
