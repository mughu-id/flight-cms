CREATE TABLE post_types (
    slug TEXT PRIMARY KEY,
    label TEXT NOT NULL,
    label_singular TEXT NOT NULL,
    rewrite TEXT NOT NULL,
    public INTEGER NOT NULL DEFAULT 1,
    has_archive INTEGER NOT NULL DEFAULT 1,
    hierarchical INTEGER NOT NULL DEFAULT 0,
    supports TEXT NOT NULL DEFAULT '[]',
    taxonomies TEXT NOT NULL DEFAULT '[]',
    icon TEXT NOT NULL DEFAULT 'file-earmark',
    is_builtin INTEGER NOT NULL DEFAULT 0,
    menu_position INTEGER NOT NULL DEFAULT 20
);

CREATE TABLE posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    title TEXT NOT NULL DEFAULT '',
    slug TEXT NOT NULL DEFAULT '',
    path TEXT NOT NULL DEFAULT '',
    content TEXT NOT NULL DEFAULT '',
    excerpt TEXT NOT NULL DEFAULT '',
    author_id INTEGER NOT NULL REFERENCES users(id),
    parent_id INTEGER NOT NULL DEFAULT 0,
    menu_order INTEGER NOT NULL DEFAULT 0,
    template TEXT NOT NULL DEFAULT '',
    featured_media_id INTEGER,
    comment_status TEXT NOT NULL DEFAULT 'open',
    comment_count INTEGER NOT NULL DEFAULT 0,
    published_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX posts_type_status_date ON posts (type, status, published_at);
CREATE INDEX posts_type_path ON posts (type, path);
CREATE INDEX posts_type_slug ON posts (type, slug);
CREATE INDEX posts_author ON posts (author_id);

CREATE TABLE post_meta (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
    meta_key TEXT NOT NULL,
    meta_value TEXT,
    UNIQUE (post_id, meta_key)
);

CREATE TABLE post_revisions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
    author_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    excerpt TEXT NOT NULL,
    meta TEXT NOT NULL DEFAULT '{}',
    is_autosave INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL
);

CREATE INDEX revisions_post ON post_revisions (post_id, created_at);

CREATE TABLE terms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    taxonomy TEXT NOT NULL,
    name TEXT NOT NULL,
    slug TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    parent_id INTEGER NOT NULL DEFAULT 0,
    count INTEGER NOT NULL DEFAULT 0,
    UNIQUE (taxonomy, slug)
);

CREATE TABLE term_relationships (
    post_id INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
    term_id INTEGER NOT NULL REFERENCES terms(id) ON DELETE CASCADE,
    PRIMARY KEY (post_id, term_id)
);

CREATE TABLE field_groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    post_types TEXT NOT NULL DEFAULT '[]',
    fields TEXT NOT NULL DEFAULT '[]',
    position TEXT NOT NULL DEFAULT 'normal',
    sort_order INTEGER NOT NULL DEFAULT 0,
    active INTEGER NOT NULL DEFAULT 1
);
