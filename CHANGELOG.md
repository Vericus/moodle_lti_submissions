# LTI Submissions Plugin

## 2025-01-15

- Remove unnecessary mod/assign:submit overrides which cause teacher listed as submissions participants.
- Allow automatic anonymous submissions.
- Update error message for grant attempts.
- Remove deprecated typeid.
- Tests has been updated as per the issue [[MOODLE-81520](https://tracker.moodle.org/browse/MDL-81520)](https://tracker.moodle.org/browse/MDL-81520)
- Minor code-checker fixes for errors/warnings raised.

## 2024-09-04

Updated with upstream changes contributed by [Catalyst](https://www.catalyst-au.net/solutions/moodle) following a code review.

## 2024-05-17

- Fix `LIMIT 1` not used when querying and expecting a single course module
- Update plugin incompatibility version to Moodle 405
- Create and release patches for Moodle 401, 402, 403, 404

## 2024-01-30

- Fix sanitisation of tool selection when pre-existing automated tool option was selected.

## 2024-01-15

- Add LTI tool whitelist to global configuration.

- Show only whitelisted LTI tools when Activity's submission type is "LTI Submissions"

- Add validation error messages in Activity settings if Submission Settings are not compatible with LTI Submissions' workflows. Incompatible Submission Settings produced duplicate "Go To Cadmus" buttons and required manual intervention to continue automatic pushing of Cadmus LTI submissions.

- Fix cancelling out not working if fresh Activity settings had "LTI Submissions" selected

#### Patch Changes

- Remove display of LTI configuration details. These were showing up on all LTI tools. They are well-known and don't need this display.
