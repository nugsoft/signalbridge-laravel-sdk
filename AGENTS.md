# Notes for AI coding agents

Guidance for agents working in a project that **uses** this SDK lives in:

    resources/boost/guidelines/core.md

It is deliberately the only copy: duplicating it is how guidance drifts out of
step with the code.

## With Laravel Boost

Boost scans every installed Composer package for `resources/boost/guidelines/`,
so this file is found automatically. Running

    php artisan boost:install

offers `nugsoft/signalbridge-laravel-sdk` among the third-party guidelines and
merges the ones you pick into the project's `CLAUDE.md`,
`.github/copilot-instructions.md` and `.junie/guidelines.md`.

One catch worth knowing: the selection is recorded in the project's `boost.json`
under `guidelines`, and once that key exists it acts as an allow-list. If
SignalBridge guidance is missing after an install, check that
`nugsoft/signalbridge-laravel-sdk` is listed there — a `"guidelines": []` from an
earlier install silently excludes every third-party package. Re-run
`boost:install` and select it, or add the package name by hand and run
`php artisan boost:update`.

## Without Boost

Point your agent at the file. For Claude Code, one line in the project's
`CLAUDE.md` is enough, and because the path stays inside the working directory it
needs no approval:

```md
@vendor/nugsoft/signalbridge-laravel-sdk/resources/boost/guidelines/core.md
```

Claude Code also reads a project's own `AGENTS.md` when there is no `CLAUDE.md`,
so the same line works there. For other tools, copy the file's contents into
whatever instructions file they read — and re-copy it when you upgrade the
package.

---

Working on the SDK **itself**? Three things are load-bearing:

- `Support/MessageSegments` must agree with the gateway's
  `BalanceService::calculateSegments()` character for character. When they
  disagree, `estimateCost()` quotes a figure the invoice will not match. Both
  sides carry the same test cases; change neither alone.
- `Http::fake()` in every test. A test that reaches the real gateway sends a real
  SMS and bills the account.
- Nothing in this package may retry a send. The gateway charges a message when it
  accepts one and there is no idempotency key, so a retried request bills and
  delivers twice.

Run the suite with `vendor/bin/phpunit`.
