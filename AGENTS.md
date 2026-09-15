# Notes for AI coding agents

Guidance for agents working in a project that **uses** this SDK lives in:

    resources/boost/guidelines/core.md

If the project has [Laravel Boost](https://github.com/laravel/boost) installed,
that file is picked up automatically — `php artisan boost:install` merges it into
the project's `CLAUDE.md`, `.github/copilot-instructions.md` and
`.junie/guidelines.md`, so nothing needs copying.

Without Boost, point your agent at that file directly, or paste its contents into
whatever instructions file your tool reads. It is deliberately the only copy:
duplicating it is how guidance drifts out of step with the code.

---

Working on the SDK **itself**? Two things are load-bearing:

- `Support/MessageSegments` must agree with the gateway's
  `BalanceService::calculateSegments()` character for character. When they
  disagree, `estimateCost()` quotes a figure the invoice will not match. Both
  sides carry the same test cases; change neither alone.
- `Http::fake()` in every test. A test that reaches the real gateway sends a real
  SMS and bills the account.

Run the suite with `vendor/bin/phpunit`.
