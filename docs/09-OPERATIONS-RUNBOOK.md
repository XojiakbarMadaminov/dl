# Dialog Platform — Operations and Runbook

## 1. System Overview

Dialog runs as a native modular monolith on Linux:
- PHP 8.4 (Laravel 13)
- PostgreSQL 16
- Redis 7
- Nginx + PHP-FPM

---

## 2. Database Backup and Restore

### Backup
Create a full binary/custom dump of the PostgreSQL database:

```bash
# Set credentials if necessary (or rely on ~/.pgpass)
export PGPASSWORD="password"

# Daily dump with timestamp and compression
pg_dump -U postgres -h 127.0.0.1 -p 5432 -F c -b -v -f "/var/backups/dialog/dialog_$(date +%Y%m%d_%H%M%S).dump" dialog
```

### Restore
To restore into a clean or target database:

```bash
export PGPASSWORD="password"

# Drop existing connections and database if doing full restore
dropdb -U postgres -h 127.0.0.1 -p 5432 --if-exists dialog
createdb -U postgres -h 127.0.0.1 -p 5432 -O postgres dialog

# Restore from dump
pg_restore -U postgres -h 127.0.0.1 -p 5432 -d dialog -v "/var/backups/dialog/dialog_20261017_120000.dump"
```

---

## 3. Queue Workers and Supervisor

Production queues run via Redis using Laravel Queue Workers managed by systemd or Supervisor.

### Supervisor Configuration (`/etc/supervisor/conf.d/dialog-worker.conf`):

```ini
[program:dialog-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php8.4 /home/xojiakbar/PhpstormProjects/dl/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=xojiakbar
numprocs=2
redirect_stderr=true
stdout_logfile=/home/xojiakbar/PhpstormProjects/dl/storage/logs/worker.log
stopwaitsecs=3600
```

### Management Commands:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart dialog-worker:*
```

---

## 4. Scheduled Tasks

Run the Laravel scheduler via crontab:

```bash
* * * * * cd /home/xojiakbar/PhpstormProjects/dl && /usr/bin/php8.4 artisan schedule:run >> /dev/null 2>&1
```

---

## 5. Security & Sensitive Data Handling

- Passwords are encrypted with `bcrypt` / Argon2id.
- API tokens are stored as SHA-256 hashes in `personal_access_tokens`.
- Critical lifecycle actions (creation, submission, moderation approval, status changes) are immutably logged into `audit_logs`.
- Logs never include raw credentials or authorization bearer tokens.
