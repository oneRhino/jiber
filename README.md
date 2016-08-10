# Jiber

[http://jiber.tmisoft.com/](http://jiber.tmisoft.com/)

Imports time entries (and everything else needed) from Toggl, and send them to Redmine, Jira and Basecamp.

## First utilization

1. [Create an user](http://jiber.tmisoft.com/register)
2. Add your Toggl API Token, Redmine API Token and Jira username at [settings page](http://jiber.tmisoft.com/settings)
	1. Jira only connects using username/password (doesn't have a API Token). Password is asked once per session.
	2. When system can successfully connect into the services, they'll be green.
3. Beside Toggl API Token input field, when connection is successful, an "Import" button appear. This will import all Toggl data into the system.
	1. Import can also be done per session, visiting each session under Toggl on top menu, and clicking "Import". Please make sure to import Projects before Tasks, Clients before Projects, and Workspaces before Clients.
4. After importing, you'll be able to run [Reports](http://jiber.tmisoft.com/toggl/reports)
	1. Depending on the date range (and the number of time entries within that range), creating a report might take a while.
