-- ============================================================
-- Lookup tables
-- ============================================================

CREATE TABLE IF NOT EXISTS priority (
  id    INT AUTO_INCREMENT PRIMARY KEY,
  name  VARCHAR(50) NOT NULL UNIQUE,
  value INT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS status (
  id    INT AUTO_INCREMENT PRIMARY KEY,
  name  VARCHAR(50) NOT NULL UNIQUE,
  value INT NOT NULL UNIQUE
);

-- ============================================================
-- Users
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(255) NOT NULL,
  email         VARCHAR(255) NOT NULL UNIQUE,
  username      VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- Projects
-- ============================================================

CREATE TABLE IF NOT EXISTS projects (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(255) NOT NULL,
  description TEXT,
  owner_id    INT NOT NULL,
  due_date    DATE,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_id) REFERENCES users(id)
);

-- ============================================================
-- Project members  (controls who belongs to a project)
-- ============================================================

CREATE TABLE IF NOT EXISTS project_members (
  project_id INT NOT NULL,
  user_id    INT NOT NULL,
  role       ENUM('owner', 'member', 'viewer') DEFAULT 'member',
  joined_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (project_id, user_id),
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)    REFERENCES users(id)
);

-- ============================================================
-- Tasks  (scoped to a project)
-- ============================================================

CREATE TABLE IF NOT EXISTS tasks (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  project_id  INT NOT NULL,
  title       VARCHAR(255) NOT NULL,
  description TEXT,
  status_id   INT DEFAULT 2,
  priority_id INT DEFAULT 3,
  due_date    DATE,
  created_by  INT,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id)  REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by)  REFERENCES users(id),
  FOREIGN KEY (status_id)   REFERENCES status(id),
  FOREIGN KEY (priority_id) REFERENCES priority(id)
);

-- ============================================================
-- Task assignees
-- Assignee must already be a member of the task's project.
-- The composite FK (project_id, user_id) enforces this at DB level.
-- ============================================================

CREATE TABLE IF NOT EXISTS task_assignees (
  task_id     INT NOT NULL,
  project_id  INT NOT NULL,   -- ← redundant but required for the FK constraint below
  user_id     INT NOT NULL,
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (task_id, user_id),
  FOREIGN KEY (task_id)              REFERENCES tasks(id) ON DELETE CASCADE,
  FOREIGN KEY (project_id, user_id)  REFERENCES project_members(project_id, user_id)  -- ← key constraint
);

-- ============================================================
-- Status history
-- ============================================================

CREATE TABLE IF NOT EXISTS task_status_history (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  task_id       INT NOT NULL,
  old_status_id INT,
  new_status_id INT NOT NULL,
  changed_by    INT NOT NULL,
  changed_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (task_id)       REFERENCES tasks(id) ON DELETE CASCADE,
  FOREIGN KEY (old_status_id) REFERENCES status(id),
  FOREIGN KEY (new_status_id) REFERENCES status(id),
  FOREIGN KEY (changed_by)    REFERENCES users(id)
);

-- ============================================================
-- Seed data
-- ============================================================

INSERT IGNORE INTO priority (id, name, value) VALUES
  (1, 'High',   1),
  (2, 'Medium', 2),
  (3, 'Low',    3);

INSERT IGNORE INTO status (id, name, value) VALUES
  (1, 'Completed', 1),
  (2, 'To Do',     2),
  (3, 'Pending',   3);

-- Default Users for Testing Purpose
INSERT IGNORE INTO users (name, email, username, password_hash) VALUES
  ('Alice Johnson', 'alice@example.com', 'alice', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86.bzQyQXFm'),
  ('Bob Smith', 'bob@example.com', 'bob', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86.bzQyQXFm'),
  ('Carol Davis', 'carol@example.com', 'carol', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86.bzQyQXFm'),
  ('David Wilson', 'david@example.com', 'david', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86.bzQyQXFm'),
  ('Emma Brown', 'emma@example.com', 'emma', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86.bzQyQXFm');