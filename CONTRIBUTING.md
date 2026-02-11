# Contributing

Thanks for contributing to `eznix86/pest-plugin-testcontainers`.

## What to work on

- Bug fixes and stability improvements.
- Small, additive API improvements to the container DSL.
- Test coverage for every behavior change.
- Documentation updates when API behavior changes.

## Development setup

From this package directory:

```bash
composer install
```

Docker must be running for integration tests.

## Quality gates

Run these before opening a pull request:

```bash
composer lint
composer test:types
composer test:unit
```

Or run everything:

```bash
composer test
```

## Contribution guidelines

- Keep changes focused and backward-compatible when possible.
- Prefer fluent, typed APIs over array-heavy payloads.
- Do not add dummy tests; assertions should verify real behavior.
- Keep commit history coherent and meaningful.
- Rebase as needed to keep pull requests easy to review.

## Pull request checklist

- Include a clear description of the problem and the chosen approach.
- Link related issue(s) when available.
- Add or update tests for the behavior you changed.
- Update `README.md` if user-facing behavior changed.

## Release notes guidance

When introducing user-visible changes, include a short note in the PR body covering:

- Added behavior.
- Breaking changes (if any).
- Migration notes (if needed).
