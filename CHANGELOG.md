# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [0.2] - 2016-08-30
### Added
- This CHANGELOG file.
- A way to generate reports from Redmine.
- MainModel, a Model with some methods inheritable by all models.
- Report and TimeEntry tables/models, that both Redmine and Toggl can use.

### Changed
- TogglReports and other controllers, to use Report and TimeEntry tables/models.
- Replace external javascript and font files calls by calls to internal files.
- Adjust code layout to match PSR (http://www.php-fig.org/psr/)

## [0.1] - 2016-08-09
### Added
- Initial release, support Toggl import, Toggl Report generation, send time
entries to Redmine and Jira
