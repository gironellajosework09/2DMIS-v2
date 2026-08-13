# 2DMIS v2 — Efficient Development

## Purpose

Use this skill for normal implementation and maintenance tasks in the 2DMIS v2 project.

The primary goal is to complete the requested task accurately while minimizing unnecessary token consumption, repository inspection, tool calls, repeated analysis, and unrelated changes.

This skill is intended for active development. It does not replace the `2dmis-finalize` skill, which is used when an implementation is ready for final verification and documentation.

---

## 1. Core Principle — Minimal Necessary Work

For every task:

* Understand the requested change first.
* Identify the smallest scope required to complete it.
* Inspect only the files directly relevant to the task.
* Make the smallest implementation that satisfies the requirement.
* Validate only what is necessary during development.
* Stop when the requested task is complete.

Do not perform additional work simply because it could be improved.

---

## 2. Context Efficiency

### Do Not Scan the Entire Repository

Never begin a task by reading or analyzing the entire repository.

Do not recursively inspect:

* all controllers
* all models
* all views
* all migrations
* all routes
* all documentation
* all JavaScript
* all CSS
* all tests

unless the task explicitly requires repository-wide analysis.

### Start Narrow

Begin with the most likely relevant entry point:

* route
* controller
* service
* model
* Blade view
* component
* JavaScript file
* test
* configuration file

Then follow references only when necessary.

Example:

For a scholar filtering task:

1. Find the scholar route.
2. Inspect the relevant controller/service.
3. Inspect the scholar query/filter implementation.
4. Inspect the relevant Blade view/component.
5. Inspect related JavaScript only if required.
6. Implement the change.
7. Perform targeted validation.

Do not inspect unrelated modules unless the implementation proves they are required.

---

## 3. Avoid Repeated File Reading

Once a file has been inspected:

* Do not reread the entire file unnecessarily.
* Reinspect only the relevant section if possible.
* Do not repeatedly search for the same information.
* Remember decisions and findings from the current task.

If a file was modified, inspect the changed section when validation requires it instead of rereading the entire repository.

---

## 4. Search Efficiently

Prefer targeted searches.

Use specific searches for:

* class names
* method names
* route names
* table names
* component names
* configuration keys
* specific UI labels

Avoid broad searches such as:

> Search the entire repository for anything related to scholars.

when a narrower search can identify the relevant implementation.

Do not repeat an identical search unless the repository has changed and the new result is necessary.

---

## 5. Implementation Rules

### Make Small Changes

Implement only what the user requested.

Do not:

* refactor unrelated code
* redesign unrelated modules
* rename existing tables
* rename existing columns
* change database structure unnecessarily
* replace working architecture
* introduce new design patterns without justification
* install packages without explicit approval
* rewrite existing modules simply because another implementation is preferred

Prefer extending existing code over creating duplicate implementations.

---

## 6. Preserve 2DMIS v2 Architecture

Follow the established 2DMIS v2 architecture and project rules.

The existing production database is a critical constraint.

Unless the task explicitly requires a schema change:

* Do not modify migrations.
* Do not rename database tables.
* Do not rename database columns.
* Do not remove existing fields.
* Do not drop tables.
* Do not alter production data.
* Do not run destructive database commands.

Preserve existing application behavior unless the requested task explicitly changes that behavior.

Follow the project's established:

* Laravel conventions
* authentication
* RBAC/authorization
* validation
* services
* layouts
* components
* database access patterns
* UI conventions

Reuse existing implementations whenever appropriate.

---

## 7. Scope Control

Stay within the user's requested module or feature.

For example:

If the task is:

> Add multi-select filtering to Scholars.

Do not automatically modify:

* Clients
* Users
* Transactions
* Programs
* Reports
* Authentication

unless the task explicitly requests them or the implementation requires a shared change.

If a shared component must be changed, change only the necessary portion.

---

## 8. Do Not Proactively Fix Unrelated Problems

If an unrelated issue is discovered:

1. Do not fix it automatically.
2. Do not refactor it.
3. Do not investigate it deeply.
4. Mention it briefly in the completion report.

Example:

> Found an unrelated issue in the transaction export module. It was not modified because it is outside the current task.

This prevents scope expansion and unnecessary token usage.

---

## 9. Testing Strategy

During normal development, use targeted validation first.

### Preferred order

1. Syntax/static check if appropriate.
2. Relevant feature test.
3. Relevant browser/UI check if applicable.
4. Targeted manual verification.
5. Full test suite only when necessary.

Do not run the complete test suite after every small change.

Run the full suite when:

* the user explicitly requests it
* the task affects shared/core functionality
* the task affects authentication or authorization
* the task affects database behavior
* the implementation is being finalized
* there is a strong reason to verify system-wide behavior

The `2dmis-finalize` skill is responsible for the complete final verification process.

---

## 10. Avoid Repeated Testing

Do not repeatedly run the same test without making a relevant change.

Use this pattern:

```text
Implement
→ Test
→ Identify failure
→ Fix
→ Test again
```

Not:

```text
Test
→ Test again
→ Test again
→ Test again
```

If a failure is clearly unrelated to the current change, stop investigating it and report it.

---

## 11. Tool and Command Efficiency

Before running a command, determine whether its result is actually needed.

Avoid unnecessary:

* repository-wide scans
* dependency inspections
* database dumps
* full test suites
* build processes
* cache clearing
* package installations
* server restarts
* repeated commands
* large log outputs

Prefer commands that produce focused output.

If a command has already established the required information, do not run it again unless the project changed.

---

## 12. Database Safety

Never use destructive commands during normal implementation.

Do not run:

```text
migrate:fresh
db:wipe
```

Do not drop existing production tables.

Do not modify production data simply to make a test pass.

If database schema modification is explicitly required:

1. Identify the exact schema requirement.
2. Confirm that the change is additive and compatible with the 2DMIS architecture.
3. Follow the project's database procedures.
4. Defer full database verification to the finalization process when appropriate.

Never assume a database change is safe merely because Laravel allows it.

---

## 13. Documentation Efficiency

Do not update every project document after every small implementation.

During normal development:

* Update documentation only when the task explicitly requires it.
* Update documentation when the project's established rules require an immediate update.
* Avoid rewriting large documentation files for minor implementation changes.

The complete documentation synchronization process belongs to `2dmis-finalize`.

Do not repeatedly reread `AGENTS.md` unless:

* the task requires its rules,
* the relevant instructions are unknown,
* or the project rules have changed.

---

## 14. Session Efficiency

Treat each task as an independent unit.

Do not rely on a long conversation to preserve project knowledge.

Stable project information should come from:

* `AGENTS.md`
* project documentation
* architecture documentation
* skills

not from an unnecessarily long OpenCode conversation.

When a task is complete, provide a concise summary and stop.

For a new unrelated feature, prefer starting a fresh session rather than carrying an unnecessarily large previous context.

---

## 15. Handling Ambiguous Requirements

If the requirement is genuinely ambiguous:

* Do not scan the entire project trying to guess.
* Inspect only the files necessary to understand the current behavior.
* If the ambiguity materially affects implementation, ask for clarification.
* Do not implement multiple speculative solutions.

Do not make large architectural decisions based on assumptions.

---

## 16. Avoid Large Explanations

Keep responses concise during implementation.

After completing a task, report only:

### Changed

* Files modified.
* Important implementation changes.

### Validation

* Commands/checks performed.
* Relevant results.

### Notes

* Remaining issues, if any.

Do not provide lengthy explanations of code that was not requested.

---

## 17. Stop Condition

Once all of the following are true:

* The requested implementation is complete.
* The relevant files have been updated.
* Targeted validation has passed.
* No task-related errors remain.

STOP.

Do not:

* search for additional improvements
* refactor unrelated code
* review the entire architecture
* run unrelated tests
* update unrelated documentation
* inspect unrelated modules
* make additional "quality improvements"

If additional improvements are discovered, report them separately instead of implementing them.

---

## 18. Finalization Boundary

This skill does not replace the project's finalization process.

When the user says:

* finalize
* finish
* wrap up
* ship
* prepare for commit
* verify the completed implementation

use the `2dmis-finalize` skill.

The finalization process is responsible for:

* full code-quality checks
* complete test-suite execution
* database verification
* documentation updates
* documentation consistency
* final implementation verification

Do not perform the entire finalization process during every normal development task.

---

## 19. Commit Safety

Never create a Git commit automatically.

Only commit when the user explicitly requests it.

Never stage or commit:

* `.env`
* credentials
* API keys
* passwords
* private keys
* secrets
* generated sensitive files

Before a requested commit, verify that sensitive files are not included.

---

## 20. Default Development Workflow

Use this workflow unless the task requires otherwise:

```text
Understand request
       ↓
Identify smallest scope
       ↓
Inspect relevant files only
       ↓
Implement minimal change
       ↓
Run targeted validation
       ↓
Fix task-related failures
       ↓
Verify requested behavior
       ↓
Summarize changes
       ↓
STOP
```

Do not expand the workflow unless the task requires it.

---

## 21. Priority Rule

When deciding whether to perform additional work, use this priority:

1. User's explicit request
2. Project/AGENTS.md requirements
3. Established 2DMIS v2 architecture
4. Task-specific implementation requirements
5. Necessary validation
6. Everything else

If an action is not required by one of the above, avoid doing it unless there is a clear technical reason.

The objective is:

> **Correct implementation with the smallest necessary context and smallest necessary change.**
