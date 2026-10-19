# Notes for AI coding agents

Guidance for agents working in a project that **uses** this SDK lives in:

    resources/boost/guidelines/core.md

It is deliberately the only copy: duplicating it is how guidance drifts out of
step with the code.

## With Laravel Boost

Boost scans every installed Composer package for `resources/boost/guidelines/`,
so this file is found automatically. Running

    php artisan boost:install

offers `nugsoft/signalbridge-laravel-sdk` among the third-party packages and
merges the guidance of the ones you pick into the project's agent files. Boost 2
writes `CLAUDE.md` and `AGENTS.md`; Boost 1 wrote `CLAUDE.md`,
`.github/copilot-instructions.md` and `.junie/guidelines.md`.

One catch worth knowing: `boost:update` includes only the packages your
selection recorded in the project's `boost.json`, so a package left out of it is
silently dropped.

- **Boost 2** keeps the selection under `packages`, and `guidelines` is only
  `true` or `false`:

  ```json
  {
      "agents": ["claude_code", "copilot", "junie"],
      "guidelines": true,
      "packages": ["nugsoft/signalbridge-laravel-sdk"]
  }
  ```

  A `boost.json` written by Boost 1 has no `packages` key. **After upgrading to
  Boost 2, the SignalBridge guidance disappears at the next `boost:update`**
  until the package is listed there.
- **Boost 1** kept the selection under `guidelines`; a `"guidelines": []` from an
  earlier install excluded every third-party package.

If SignalBridge guidance is missing, re-run `boost:install` and select the
package, or add it to `boost.json` by hand and run `php artisan boost:update`.

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
