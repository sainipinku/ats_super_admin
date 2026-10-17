# Git Push Approval Rule

- **Explicit User Confirmation Required**: NEVER execute `git push` or push commits to any remote repository (e.g. `origin`, `cadmax`) without first asking the user for explicit permission.
- **Pre-Push Summary**: When code is ready to be pushed, present a summary to the user:
  - Target remote and branch name (e.g., `origin/abhishek`)
  - Summary of commits / changes included
- **Execution**: Wait for the user's explicit response ("yes", "proceed", or confirmation) before running any push command.
