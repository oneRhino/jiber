# Jiber

[http://jiber.tmisoft.com/](http://jiber.tmisoft.com/)

Imports time entries (and everything else needed) from Toggl, and send them to Redmine and Jira.

## First utilization

1. [Create an user](http://jiber.tmisoft.com/register)
2. Add your Toggl API Token, Redmine API Token and Jira username at [settings page](http://jiber.tmisoft.com/settings)
	1. Jira only connects using username/password (doesn't have a API Token). Password is asked once per session.
	2. When system can successfully connect into the services, they'll be green.
3. Beside Toggl API Token input field, when connection is successful, an "Import" button appear. This will import all Toggl data into the system.
	1. Import can also be done per session, visiting each session under Toggl on top menu, and clicking "Import". Please make sure to import Projects before Tasks, Clients before Projects, and Workspaces before Clients.

## Reports

1. After importing, you'll be able to run [Reports](http://jiber.tmisoft.com/toggl/reports)
	1. Depending on the date range (and the number of time entries within that range), creating a report might take a while.
2. After saving a report, it'll be shown. You can also access it via [Reports list](http://jiber.tmisoft.com/toggl/reports)
	1. IMPORTANT: Time of each time entry is rounded up to nearest 5 minutes, and that's the time that is sent to Redmine and Jira.
3. While viewing a report, clicking "Compare entries to Redmine" will open a page comparing each OneRhino Toggl time entry with time entries found inside Redmine (same task and date).
	1. There, you can view the time difference between Toggl and Redmine;
	2. You'll also be able to "close" each task and date, by clicking its name;
	3. Change "ignore" to "send" (or check the checkbox, if not viewing the toggle field) beside each entry, to make that entry be sent to Redmine;
	4. After selecting all entries you want to be sent to Redmine, click "Send" at the end of the table. This will send all selected time entries to Redmine;
	5. After sending, system will show the Redmine Compare page again, updated.
4. While viewing a report, clicking "Compare entries to Jira" will open a page comparing each OneRhino Toggl time entry with time entries found inside Jira (same task and date, based on Redmine's Jira ID field)
	1. There, you can view the time difference between Toggl and Jira;
	2. You'll also be able to "close" each task and date, by clicking its name;
	3. Change "ignore" to "send" (or check the checkbox, if not viewing the toggle field) beside each entry, to make that entry be sent to Jira;
	4. After selecting all entries you want to be sent to Jira, click "Send" at the end of the table. This will send all selected time entries to Jira;
	5. After sending, system will show the Jira Compare page again, updated.

## Contact

For any questions, suggestions and complainings, please contact Thaissa Mendes at <thaissa.mendes@gmail.com>.
