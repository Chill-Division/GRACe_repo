# CLAUDE.md

Claude Code reads this file automatically. The project rules live in
AGENTS.md so every AI tool works from the same copy:

@AGENTS.md

## The changelog, in short

AGENTS.md section 6 has the full standard. The points that matter most:

- `grace_addon/CHANGELOG.md` (not the repository root) is what growers see
  in GRACe's "What's new" pop-up after an update. Write every entry like the
  1.1.0 one: a one-line summary (skipped when it would only repeat a short
  entry), then What's new, Important fixes, Changes, and Minor bug fixes
  and reliability improvements, most important first.
- Keep the `## [x.y.z] - YYYY-MM-DD` heading, and keep the Dockerfile's
  `COPY CHANGELOG.md /www/CHANGELOG.md` line. The add-on image doesn't
  include the changelog otherwise, and What's new would show nothing.
