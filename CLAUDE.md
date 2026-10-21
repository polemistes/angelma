# Angelma

A small PHP content management system for shared web hosting. The full specification and
build order are in `PLAN.md`. Read it before doing anything.

Working rules for this project:

- Follow `PLAN.md` step by step. Read section 12 (Progress) first to find where to
  continue, and tick the step there when it is done.
- Commit after every completed step, so work survives an interrupted session.
- When the plan is ambiguous, pick the simplest option and note it in `DECISIONS.md`.
- Stay inside this folder. Do not read or change other folders under `/home/proteus/koding`.
- Plain PHP 8.2+, SQLite, no framework, no JavaScript build step. Do not add dependencies
  beyond those named in `PLAN.md`.
- Run the local server with `dev/server.sh` and the tests with `vendor/bin/phpunit`.
