# AI Project Rules

These rules apply to any AI assistant or coding agent working in this project.

## Hard Restrictions

### 1. No Database Migrations
The AI must **not** create, edit, rename, delete, run, rollback, refresh, reset, or generate database migration files.

Do not run commands such as:

```bash
php artisan make:migration
php artisan migrate
php artisan migrate:fresh
php artisan migrate:refresh
php artisan migrate:reset
php artisan migrate:rollback
```

The AI may inspect existing migrations for context, but must not modify them.

If a requested feature appears to require a database schema change, the AI must:
- explain what schema change would be needed;
- stop before creating or applying that change; and
- ask the user to handle or explicitly approve the database change separately.

### 2. No Git Actions
The AI must **not** perform any Git operation that changes repository state, history, branches, remotes, or the staging area.

Do not run commands such as:

```bash
git add
git commit
git push
git pull
git fetch
git merge
git rebase
git reset
git revert
git checkout
git switch
git branch
git stash
git cherry-pick
git tag
git clean
```

The AI must also not:
- create commits;
- stage files;
- change branches;
- resolve merges by running Git commands;
- modify Git history;
- push or pull from remotes; or
- delete untracked files with Git.

Read-only Git commands are allowed when useful, for example:

```bash
git status
git diff
git log
git show
```

These must remain strictly read-only.

## Allowed Work

The AI may:
- inspect and read project files;
- edit application source code;
- create or update non-migration source files;
- run tests;
- run linters, formatters, and static analysis;
- inspect logs;
- search the codebase;
- run the application locally when needed;
- use the project's existing virtual environment or dependencies; and
- suggest migration or Git steps for the user to perform manually.

## Safety Rule

Before running a command, the AI must check whether it would:
1. alter the database schema or migration history; or
2. alter Git state or repository history.

If either is true, **do not run the command**.

## Default Behavior

Work only within the existing application structure and database schema.

When a task cannot be completed without a migration or Git action, clearly state the limitation and continue with any safe work that can still be completed without violating these rules.
