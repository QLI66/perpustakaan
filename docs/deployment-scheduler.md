# Laravel scheduler deployment

The `scheduler` service in `compose.yaml` runs `php artisan schedule:work` in a
separate container built from the same Sail runtime image and mounted
application source as `laravel.test`. It inherits the project's `.env` through
the shared application volume, uses the same database and cache configuration,
does not expose an HTTP port, and restarts with the `unless-stopped` policy.

Run exactly one scheduler replica for each application deployment. The Compose
service declares `deploy.replicas: 1`; do not use `--scale scheduler=...` or
start another `schedule:work`, cron-triggered `schedule:run`, or external
scheduler for the same deployment. Multiple schedulers may dispatch the same
scheduled task concurrently. The Laravel event's `withoutOverlapping()` lock
protects against overlapping expiry executions when all instances use the same
shared cache, but it does not replace the single-replica deployment rule.

Check scheduler status and logs with:

```sh
docker compose ps scheduler
docker compose logs --tail 100 scheduler
docker compose exec scheduler php artisan schedule:list
```

Confirm that exactly one scheduler container is running and that
`reservations:expire` is listed every minute. The scheduler container has no
HTTP healthcheck; monitor its container state/restarts and logs through the
deployment platform. A restart policy restarts a stopped process/container,
but does not by itself detect a live process that has stopped making progress.

For deployment platforms other than Docker Compose, configure one dedicated
scheduler process running `php artisan schedule:work` from the same application
release and environment. Do not configure both that process and a Compose or
external scheduler for the same deployment.
