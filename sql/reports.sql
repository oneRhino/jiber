INSERT INTO reports SELECT id, user_id, start_date, end_date, created_at, updated_at FROM toggl_reports;
ALTER TABLE toggl_reports DROP FOREIGN KEY toggl_reports_user_id_foreign;
ALTER TABLE toggl_reports DROP INDEX toggl_reports_user_id_foreign;
ALTER TABLE `toggl_reports`
  DROP `user_id`,
  DROP `start_date`,
  DROP `end_date`,
  DROP `created_at`,
  DROP `updated_at`;
ALTER TABLE `jira_sent` DROP FOREIGN KEY `jira_sent_report_id_foreign`;
ALTER TABLE `jira_sent` ADD CONSTRAINT `jira_sent_report_id_foreign` FOREIGN KEY (`report_id`) REFERENCES `reports`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `redmine_sent` DROP FOREIGN KEY `redmine_sent_report_id_foreign`;
ALTER TABLE `redmine_sent` ADD CONSTRAINT `redmine_sent_report_id_foreign` FOREIGN KEY (`report_id`) REFERENCES `reports`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `toggl_time_entries` DROP FOREIGN KEY `toggl_time_entries_report_id_foreign`;
ALTER TABLE `toggl_time_entries` ADD CONSTRAINT `toggl_time_entries_report_id_foreign` FOREIGN KEY (`report_id`) REFERENCES `reports`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `toggl_reports` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL;
ALTER TABLE `toggl_reports` ADD FOREIGN KEY (`id`) REFERENCES `reports`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
DELETE FROM `reports`;
DELETE FROM `redmine_reports`;
ALTER TABLE toggl_time_entries DROP FOREIGN KEY toggl_time_entries_report_id_foreign;
ALTER TABLE toggl_time_entries DROP FOREIGN KEY toggl_time_entries_user_id_foreign;
ALTER TABLE toggl_time_entries DROP INDEX toggl_time_entries_report_id_foreign;
ALTER TABLE toggl_time_entries DROP INDEX toggl_time_entries_user_id_foreign;
ALTER TABLE `toggl_time_entries`
  DROP `user_id`,
  DROP `report_id`,
  DROP `date`,
  DROP `time`,
  DROP `description`,
  DROP `duration`;
ALTER TABLE `toggl_time_entries`
  DROP `redmine_issue_id`,
  DROP `jira`;
ALTER TABLE redmine_time_entries DROP FOREIGN KEY redmine_time_entries_report_id_foreign;
ALTER TABLE redmine_time_entries DROP FOREIGN KEY redmine_time_entries_user_id_foreign;
ALTER TABLE `redmine_time_entries`
  DROP `user_id`,
  DROP `report_id`,
  DROP `jira`,
  DROP `date`,
  DROP `time`,
  DROP `description`,
  DROP `duration`,
  DROP `created_at`,
  DROP `updated_at`,
  DROP `redmine_issue_id`;
ALTER TABLE `redmine_time_entries` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL;
ALTER TABLE `redmine_time_entries` ADD FOREIGN KEY (`id`) REFERENCES `time_entries`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `toggl_time_entries`
  DROP `created_at`,
  DROP `updated_at`;
ALTER TABLE `toggl_time_entries` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL;
ALTER TABLE `toggl_time_entries` ADD FOREIGN KEY (`id`) REFERENCES `time_entries`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE redmine_reports DROP FOREIGN KEY redmine_reports_user_id_foreign;
ALTER TABLE `redmine_reports`
  DROP `user_id`,
  DROP `start_date`,
  DROP `end_date`,
  DROP `created_at`,
  DROP `updated_at`;
ALTER TABLE `redmine_reports` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL;
ALTER TABLE `redmine_reports` ADD FOREIGN KEY (`id`) REFERENCES `reports`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
