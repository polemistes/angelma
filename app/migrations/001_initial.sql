-- Angelma initial schema. See PLAN.md section 4.
-- Timestamps are UTC ISO 8601 text, booleans are 0 and 1.

CREATE TABLE IF NOT EXISTS schema_version (
    version INTEGER NOT NULL
);

CREATE TABLE settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL DEFAULT ''
);

CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    username TEXT NOT NULL UNIQUE,
    display_name TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'user',
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL,
    last_login_at TEXT NULL
);

CREATE TABLE pages (
    id INTEGER PRIMARY KEY,
    slug TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    title TEXT NOT NULL,
    meta_description TEXT NOT NULL DEFAULT '',
    menu_order INTEGER NOT NULL DEFAULT 0,
    show_in_menu INTEGER NOT NULL DEFAULT 1,
    owner_id INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE INDEX pages_owner ON pages (owner_id);
CREATE INDEX pages_menu ON pages (show_in_menu, menu_order);

CREATE TABLE rows (
    id INTEGER PRIMARY KEY,
    page_id INTEGER NOT NULL,
    position INTEGER NOT NULL DEFAULT 0,
    alignment TEXT NOT NULL DEFAULT 'left'
);
CREATE INDEX rows_page ON rows (page_id, position);

CREATE TABLE boxes (
    id INTEGER PRIMARY KEY,
    row_id INTEGER NOT NULL,
    position INTEGER NOT NULL DEFAULT 0,
    type TEXT NOT NULL,
    width INTEGER NOT NULL DEFAULT 12,
    height INTEGER NULL,
    content TEXT NOT NULL DEFAULT '{}'
);
CREATE INDEX boxes_row ON boxes (row_id, position);
CREATE INDEX boxes_type ON boxes (type);

CREATE TABLE images (
    id INTEGER PRIMARY KEY,
    filename TEXT NOT NULL UNIQUE,
    original_name TEXT NOT NULL,
    mime TEXT NOT NULL,
    width INTEGER NOT NULL,
    height INTEGER NOT NULL,
    size INTEGER NOT NULL,
    alt TEXT NOT NULL DEFAULT '',
    uploader_id INTEGER NOT NULL,
    created_at TEXT NOT NULL
);
CREATE INDEX images_uploader ON images (uploader_id);

CREATE TABLE blogs (
    id INTEGER PRIMARY KEY,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    owner_id INTEGER NOT NULL,
    created_at TEXT NOT NULL
);
CREATE INDEX blogs_owner ON blogs (owner_id);

CREATE TABLE posts (
    id INTEGER PRIMARY KEY,
    blog_id INTEGER NOT NULL,
    slug TEXT NOT NULL,
    title TEXT NOT NULL,
    body_markdown TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'draft',
    comments_mode TEXT NOT NULL DEFAULT 'closed',
    author_id INTEGER NOT NULL,
    published_at TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE (blog_id, slug)
);
CREATE INDEX posts_blog_published ON posts (blog_id, status, published_at);
CREATE INDEX posts_author ON posts (author_id);

CREATE TABLE comments (
    id INTEGER PRIMARY KEY,
    post_id INTEGER NOT NULL,
    user_id INTEGER NULL,
    author_name TEXT NOT NULL,
    body TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    ip_hash TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL
);
CREATE INDEX comments_post ON comments (post_id, status, created_at);
CREATE INDEX comments_user ON comments (user_id);
CREATE INDEX comments_ip ON comments (ip_hash, created_at);

CREATE TABLE login_attempts (
    id INTEGER PRIMARY KEY,
    key TEXT NOT NULL,
    attempted_at TEXT NOT NULL
);
CREATE INDEX login_attempts_key ON login_attempts (key, attempted_at);
