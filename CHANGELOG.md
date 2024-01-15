# LTI Submissions Plugin

## 2024-01-15


- Add LTI tool whitelist to global configuration. 

- Show only whitelisted LTI tools when Activity's submission type is "LTI Submissions"

- Add validation error messages in Activity settings if Submission Settings are not compatible with LTI Submissions' workflows. Incompatible Submission Settings produced duplicate "Go To Cadmus" buttons and required manual intervention to continue automatic pushing of Cadmus LTI submissions.

- Fix cancelling out not working if fresh Activity settings had "LTI Submissions" selected


#### Patch Changes

- Remove display of LTI configuration details. These were showing up on all LTI tools. They are well-known and don't need this display.

