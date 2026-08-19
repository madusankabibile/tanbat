-- ============================================================================
--  TV post type — schema changes
--  Migration equivalent of:
--    database/migrations/2026_08_19_100000_add_tv_type_and_create_tv_channels.php
--
--  Run this ONLY if you are applying the change by hand (phpMyAdmin, cPanel,
--  mysql CLI) instead of running `php artisan migrate`. Doing both is not
--  harmful — step 3 records the migration so Artisan skips it — but running
--  `php artisan migrate` alone is the simpler path when you have shell access.
--
--  Target: MySQL / MariaDB, utf8mb4_unicode_ci, InnoDB.
--  Take a backup before running this.
-- ============================================================================


-- ─────────────────────────────────────────────────────────────────────────────
--  1. Widen posts.type so it accepts the new 'tv' member.
--
--  MySQL has no "ADD VALUE" for ENUM, so the whole list is restated. The five
--  existing members are unchanged and no row is rewritten in a way that can
--  lose data — this only extends what is allowed.
--
--  If your posts.type already differs from the list below (a fork, or an older
--  schema without 'book'), edit the list to be YOUR current members plus 'tv'
--  rather than pasting this verbatim. Check first with:
--      SHOW COLUMNS FROM `posts` LIKE 'type';
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE `posts`
  MODIFY COLUMN `type` ENUM('status','image','video','article','book','tv') NOT NULL;


-- ─────────────────────────────────────────────────────────────────────────────
--  2. The channel detail table, one row per TV post.
--
--  post_id is UNIQUE and cascades: deleting the parent post deletes the
--  channel, which is what the admin "Delete" action relies on.
--
--  stream_url holds the origin .m3u8. It is never rendered to a page — the
--  player is handed a signed proxy URL instead — so treat this column as a
--  secret when exporting or sharing dumps.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE `tv_channels` (
  `id`          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id`     BIGINT(20) UNSIGNED NOT NULL,
  `name`        VARCHAR(255)  COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug`        VARCHAR(255)  COLLATE utf8mb4_unicode_ci NOT NULL,
  `logo`        VARCHAR(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` LONGTEXT      COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stream_url`  TEXT          COLLATE utf8mb4_unicode_ci NOT NULL,
  `referer`     VARCHAR(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent`  VARCHAR(512)  COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active`   TINYINT(1)          NOT NULL DEFAULT 1,
  `views`       BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP NULL DEFAULT NULL,
  `updated_at`  TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tv_channels_post_id_unique` (`post_id`),
  UNIQUE KEY `tv_channels_slug_unique` (`slug`),
  KEY `tv_channels_is_active_views_index` (`is_active`, `views`),
  CONSTRAINT `tv_channels_post_id_foreign`
    FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────────────
--  3. Tell Laravel this migration is already applied.
--
--  Without this row, the next `php artisan migrate` re-runs the migration and
--  fails on "table already exists". The batch number is just a grouping label
--  for `migrate:rollback`; taking one past the current max keeps this change
--  rollback-able on its own.
-- ─────────────────────────────────────────────────────────────────────────────
--  Safe to run twice: the WHERE NOT EXISTS makes a second run insert nothing.
--
--  Two MySQL quirks shape the odd-looking form below, so please don't
--  "simplify" it:
--    * MySQL refuses to read the INSERT's target table in a subquery, hence
--      each read of `migrations` is wrapped in a derived table.
--    * MAX() must sit inside its own scalar subquery. Putting it in the outer
--      SELECT list makes the statement an implicit aggregate, which returns one
--      row even when WHERE matches nothing — inserting a duplicate at batch 1.
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_08_19_100000_add_tv_type_and_create_tv_channels',
       (SELECT COALESCE(MAX(b.`batch`), 0) + 1
          FROM (SELECT * FROM `migrations`) AS b)
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM (SELECT * FROM `migrations`) AS m
  WHERE m.`migration` = '2026_08_19_100000_add_tv_type_and_create_tv_channels'
);


-- ============================================================================
--  Verify
-- ============================================================================
-- SHOW COLUMNS FROM `posts` LIKE 'type';     -- expect the enum to include 'tv'
-- SHOW CREATE TABLE `tv_channels`;
-- SELECT * FROM `migrations` WHERE `migration` LIKE '%tv_channels%';


-- ============================================================================
--  Rollback (destructive — drops every TV channel and its posts)
-- ============================================================================
-- DELETE FROM `posts` WHERE `type` = 'tv';   -- cascades to tv_channels
-- DROP TABLE IF EXISTS `tv_channels`;
-- ALTER TABLE `posts`
--   MODIFY COLUMN `type` ENUM('status','image','video','article','book') NOT NULL;
-- DELETE FROM `migrations`
--  WHERE `migration` = '2026_08_19_100000_add_tv_type_and_create_tv_channels';
