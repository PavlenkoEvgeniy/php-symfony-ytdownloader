# Behavior specs live in OpenSpec

We adopted OpenSpec (https://openspec.dev) to keep behavior contracts in the
repository instead of leaving them in chat history and issue threads. The
`openspec/` folder holds `specs/<capability>/spec.md` (the agreed behavior) and
`changes/<id>/` (proposals, spec deltas, design notes and tasks). OpenSpec does
not replace the other project documents:

- `CONTEXT.md` remains the ubiquitous language: terms only, no behavior.
- `docs/adr/` remains the place for durable architectural decisions.
- GitHub issues remain the intake: why the work exists and who asked for it.
- The task journal (`$TASKS_DIR/ytdownloader/`) remains the session log and
  records decisions; for OpenSpec work it links to the change and does not
  duplicate the plan, which lives in `changes/<id>/tasks.md`.
- `README.md` remains the user-facing documentation.

The CLI is Node-only and runs in a pinned `node:22-alpine` container through
`make openspec`; CI validates all specs and changes with
`openspec validate --all --strict`. Artifacts are written in English; specs use
`CONTEXT.md` terms and testable WHEN/THEN scenarios without file names or
implementation details.

## Considered Options

- **Issues and the journal only** — the previous state: behavior was spread
  across closed issues, PR descriptions and chat, and could not be validated.
- **GitHub Spec Kit** — thorough, but heavier: rigid phases and more Markdown.
- **A wiki or a separate docs site** — drifts from the code and is not reviewed
  with it.
- **OpenSpec** — chosen: lightweight artifacts in the repo, a change lifecycle
  (`propose → apply → archive`) and a validation command that CI can run.

## Consequences

- Any behavior change starts with a proposal and a spec delta; the user reviews
  them before implementation. Refactors, docs, dependency bumps and chore work
  do not need a change.
- Specs are capability-level, not a line-by-line description of the code; they
  are retrofitted lazily when a capability is touched.
- The OpenSpec CLI version is pinned in the Makefile and in the CI job; bumping
  it requires `make openspec ARGS="update"` and committing the regenerated
  `.claude/` files.
- OpenSpec's generated skills and commands are committed with the project; they
  are generated files and must not be hand-edited.
- The code-style rules in `.ai/` and `$TASKS_DIR/_code-style` are unaffected:
  OpenSpec governs behavior, not PHP style.
