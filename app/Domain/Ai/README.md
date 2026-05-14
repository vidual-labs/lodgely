# Domain · Ai (reserved, not in MVP)

This folder is a deliberate placeholder for AI-assisted features on top of
the Reporting domain (which is itself a reserved seam — see
`app/Domain/Reporting/README.md`).

Planned scope (post-MVP):

- Short, human-readable summaries of campaign performance.
- Anomaly / quality-of-leads hints.
- Optional, opt-in light optimization suggestions.

Design constraints baked in from day one:

- AI features operate **only** on the Reporting layer or on **explicitly
  selected** lead snippets, never on the full lead corpus.
- Data passed to a model should be pseudonymized or aggregated where
  feasible.
- A single config switch turns AI features off without breaking anything
  else.
- Self-hosted-friendly: it must be possible to plug a local model in
  place of any hosted endpoint.

Nothing in this folder is implemented in v1.
