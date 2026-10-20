# AI-Powered Niche Interview Prep Platform — API

Laravel-based backend for an AI-powered technical interview preparation platform. The system connects a React frontend with a Python AI service to manage interview sessions, questions, answers, evaluations, and reports.

## Tech Stack

- **Backend:** Laravel 13, PHP 8.4
- **Database:** MySQL 8.0
- **Web server:** Nginx
- **Infrastructure:** Docker Compose
- **Database management:** phpMyAdmin
- **Authentication:** Laravel Sanctum (planned/configured as development progresses)

## Prerequisites

Install the following before starting:

- [Git](https://git-scm.com/downloads)
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) with the Linux container engine enabled
- [Visual Studio Code](https://code.visualstudio.com/) or your preferred editor

You do not need to install PHP, Composer, or MySQL directly on your machine. These dependencies run inside Docker.

## Getting Started

### 1. Clone the repository

Open a terminal and run:

```bash
git clone https://github.com/hamzaalbaghdady/gsg-interview-prep-api.git
cd gsg-interview-prep-api
```

### 2. Configure environment variables

Create your local environment file from the example:

**Windows PowerShell:**

```powershell
Copy-Item .env.example .env
```

**Linux/macOS:**

```bash
cp .env.example .env
```

Open `.env` and configure the following values for local development:

```dotenv
APP_NAME="Interview Prep API"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=interview_prep
DB_USERNAME=laravel
DB_PASSWORD=secret
```

Keep the existing entries in `.env` unless there is a specific reason to change them.

**Important:** These database credentials are for local development only. Never use them in production.

### 3. Build and start the containers

Make sure Docker Desktop is running, then execute:

```bash
docker compose up -d --build
```

The first startup may take several minutes while Docker downloads images and installs PHP dependencies.

The environment includes four services:

| Service      | Purpose                | Access                  |
| ------------ | ---------------------- | ----------------------- |
| `app`        | PHP-FPM and Laravel    | Internal Docker network |
| `web`        | Nginx web server       | http://localhost:8000   |
| `db`         | MySQL database         | localhost:3307          |
| `phpmyadmin` | Database management UI | http://localhost:8080   |

Inside Docker, Laravel connects to MySQL using hostname `db` and port `3306`. Port `3307` is only for connections from your host machine.

### 4. Generate the application key

Run:

```bash
docker compose exec app php artisan key:generate
```

This generates a unique encryption key in your local `.env` file. Do not share or commit that file.

### 5. Run database migrations

Initialize the database schema:

```bash
docker compose exec app php artisan migrate
```

### 6. Verify the installation

Open the following URLs:

- **Laravel application:** http://localhost:8000
- **phpMyAdmin:** http://localhost:8080

For local phpMyAdmin access, use:

- Server: `db`
- Username: `laravel`
- Password: `secret`

If the Laravel page does not load, check the troubleshooting section below.

## Docker Commands

Run these commands from the repository root.

### Start the environment

```bash
docker compose up -d
```

### Stop the containers

```bash
docker compose down
```

This stops and removes the containers but preserves the named database and vendor volumes.

### View running services

```bash
docker compose ps
```

### View application logs

```bash
docker compose logs -f app
```

### View Nginx logs

```bash
docker compose logs -f web
```

### Open a shell inside the Laravel container

```bash
docker compose exec app sh
```

### Run Artisan commands

For example:

```bash
docker compose exec app php artisan about
docker compose exec app php artisan migrate:status
```

### Install updated Composer dependencies

After changing `composer.json` or `composer.lock`, run:

```bash
docker compose exec app composer install
```

Commit dependency changes and the updated `composer.lock` file, but never commit the `.env` file.

## Development Workflow

**Do not work directly on `main`.** Create a separate branch for each feature or fix.

### 1. Get the latest changes

```bash
git switch main
git pull origin main
```

### 2. Create a feature branch

Use a descriptive name:

```bash
git switch -c feature/resume-upload
```

Other examples:

- `feature/interview-sessions`
- `feature/interview-evaluation`
- `fix/authentication-error`
- `refactor/interview-service`

### 3. Commit your changes

```bash
git add .
git commit -m "Add resume upload endpoint"
```

Stage only the files relevant to your change whenever possible.

### 4. Push your branch

```bash
git push -u origin feature/resume-upload
```

Replace the example branch name with your actual branch name.

### 5. Open a pull request

Open GitHub and create a pull request from your feature branch into `main`.

Describe what changed, explain how you tested it, and address review feedback before merging. Follow the repository's branch protection rules.

## Environment and Security

- Never commit `.env`, credentials, API keys, tokens, or private certificates.
- `.env.example` should contain only safe example values and no real secrets.
- Keep `composer.lock` under version control to maintain consistent PHP dependencies.
- Do not use the local Docker database credentials in production.
- Do not run `docker compose down -v` unless you intentionally want to delete the named database and vendor volumes. Removing the database volume deletes the local database data.
- Configure production secrets, debug settings, database access, and deployment separately.

## Troubleshooting

### Docker is not responding

Make sure Docker Desktop is running, then check:

```bash
docker version
docker compose ps
```

### Laravel cannot connect to MySQL

Check that the database is healthy:

```bash
docker compose ps
docker compose logs --tail=100 db
```

Verify that `.env` uses `DB_HOST=db` and that the database name and credentials match the Docker Compose configuration.

### The website is unavailable

Check the application and Nginx logs:

```bash
docker compose logs --tail=100 app
docker compose logs --tail=100 web
```

### Dependencies or containers need rebuilding

```bash
docker compose up -d --build
```

If a problem persists, inspect the logs before deleting containers or volumes.

## Project Structure

```text
gsg-interview-prep-api/
├── app/                 # Application logic
├── bootstrap/           # Application bootstrap
├── config/              # Laravel configuration
├── database/
│   ├── migrations/      # Database schema migrations
│   ├── factories/
│   └── seeders/
├── docker/
│   ├── Dockerfile       # PHP-FPM image
│   ├── nginx.conf       # Nginx configuration
│   └── php.ini          # PHP configuration
├── public/              # Web entry point
├── routes/              # API and web routes
├── tests/               # Automated tests
├── .env.example         # Environment template
├── compose.yaml         # Docker services
├── composer.json        # PHP dependencies
└── README.md            # Project documentation
```

This structure may evolve as development progresses.

## License

The project's license will be specified when one is selected.
The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
