# Motivation

The initial design idea behind the plugin borrows from:

1. LTI 1.3 Assignment and Grade Service (AGS) specification for the `/scores` LTI REST API and recommendation for extensions on the service: https://www.imsglobal.org/spec/lti-ags/v2p0
2. Canvas LMS implementation of such an extension: https://canvas.instructure.com/doc/api/score.html#method.lti/ims/scores.create

In the LTI 1.3 flow, Cadmus receives an LMS Grade Book line-item URL for each student and read/write scopes associated with it. Using an OAUth2 callback flow, Cadmus makes signed requests to this line-item URL. We can `POST` grades and submission events to the LMS Grade Book. We can also `GET` grade book result information that was written previously by anyone.

## What is Cadmus?

Cadmus is an external LTI 1.3 tool used as a submission method in an LMS assignment. When teachers and students access the LMS assignment link using the LTI 1.3 OAuth2 (OIDC) flow, the LMS assignment can use Cadmus as the configured submission method, redirecting them to the Cadmus Platform.

Within the Cadmus Platform, a teacher creates Instruction Sheets, sets up an assignment or exam, and manages the Cadmus class list. Similarly, students see a writing area where they can write their assignment or exam and submit it within Cadmus.

Post submission, and in the background, Cadmus will generate a PDF from the submission and send it to its own integration. The most popular integration is Turnitin for plagiarism checks and marking via Turnitin Feedback Studio.

When Universities wanted to use SpeedGrader for marking instead, we found an extended use of the LTI APIs to send PDFs back to the Canvas LMS, making them available for SpeedGrader within the same LMS environment.

Moodle Grader is also in demand and a requirement for most Moodle Universities. Therefore, we are looking for a similar API in the Moodle world.

## AGS Result and Scores API

The Assignment and Grade service (AGS) lets a learning tool like Cadmus interact with the LMS Grade Book line-items associated with the LMS assignment launched into the learning tool.

Grade Book lineitem holds information like the grade and submission files in most cases. A grading plugin inside the LMS or a native grading tool like Moodle Grader, Canvas Speed Grader, can pick up the submission from there. The submission file is traditionally uploaded directly by the student.

The default [**Score publish service**](https://www.imsglobal.org/spec/lti-ags/v2p0#score-publish-service) allows Cadmus to `POST` submission events and scores only. The specification mentions possible extensions that can allow an external tool to also modify the submission file associated with the line item here: https://www.imsglobal.org/spec/lti-ags/v2p0#extensions using a request shape:

```
POST lineitems URL
Authorization: Bearer 78aerc7829z.890.duepz
Content-Type: application/vnd.ims.lis.v2.lineitem+json
{
  "timestamp": "2017-04-16T18:54:36.736+00:00",
  "activityProgress" : "Completed",
  "gradingProgress" : "PendingManual",
  "userId" : "5323497",
  "https://www.toolexample.com/lti/score": {
    "originality": 94,
    "submissionUrl": "https://www.toolexample.com/lti/score/54/5893/essay.pdf"
  }
}
```

Canvas LMS implements this extension, shown in Scenario 2 below, and Cadmus uses it to send submission PDFs to Canvas SpeedGrader. We hoped to create a similar extension in the Moodle LMS AGS endpoint.

## Current Scenarios 

### Scenario 1 - Cadmus for writing and Turnitin for marking

In setups where Universities use Cadmus as the writing tool and Turnitin as the feedback tool, the information flows like this:

Cadmus uses the standard AGS `POST` 

```mermaid
sequenceDiagram
    LMS->>+Cadmus: Student LTI 1.3 Launch with AGS details
    Cadmus-->>+Cadmus: write and submit
    Cadmus->>-LMS: POST submission event over AGS
    Cadmus->>+Turnitin: Send Submission PDF
    Turnitin->>+Cadmus: Return Grades
    Cadmus->>-LMS: POST grades over AGS
```

The `POST /scores` AGS request sent by Cadmus will be a simple submission event:

```
POST /api/lti/courses/:course_id/line_items/:line_item_id/scores
Authorization: Bearer 78aerc7829z.890.duepz
Content-Type: application/vnd.ims.lis.v2.lineitem+json

{
  "userId": "...",
  "comment": "Final submitted in Cadmus",
  "timestamp": "2024-05-30T00:00:00Z",
  "activityProgress": "Submitted",
  "gradingProgress": "Pending"
}
```

### Scenario 2 - Cadmus for writing and Canvas SpeedGrader for marking

In setups where a Canvas LMS based University wants to use Canvas SpeedGrader as the feedback tool, the information flows like this:

```mermaid
sequenceDiagram
    CanvasLMS->>+Cadmus: Student LTI 1.3 Launch with AGS details
    Cadmus-->>+Cadmus: write and submit
    Cadmus->>-CanvasLMS: POST submission event + PDF over AGS
    CanvasLMS->>CanvasLMS: Make PDF available for SpeedGrader
    Cadmus-->>+Turnitin: Send Submission PDF only for similarity
    Cadmus->>CanvasLMS: GET grades over AGS
```

There is no unique integration beyond the standard LTI 1.3 as Canvas accepts extension fields on the JSON payload for the `POST /scores` LTI AGS endpoint. Read more [here](https://canvas.instructure.com/doc/api/score.html#method.lti/ims/scores.create).

Therefore, a Cadmus AGS request will look like this:

```
POST /api/lti/courses/:course_id/line_items/:line_item_id/scores
Authorization: Bearer 78aerc7829z.890.duepz
Content-Type: application/vnd.ims.lis.v2.lineitem+json
{
  "userId": "...",
  "comment": "Final submitted in Cadmus",
  "timestamp": "2024-05-30T00:00:00Z",
  "activityProgress": "Submitted",
  "gradingProgress": "Pending",
  "https://canvas.instructure.com/lti/submission": {
    "new_submission": "true",
    "submission_type"": "online_upload",
    "submitted_at": "2024-05-30T00:00:00Z",
    "content_items": [
      {
        "type": "file",
        "title": "Final Submission",
        "url": "https://api.cadmus.io/cadmus/api/submissions/submission-123.pdf"
      }
    ]
  }
}
```

