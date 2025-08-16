<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

# CodeMentor API

This is the backend API for the CodeMentor application, providing services for user authentication, AI tutoring, and code execution.

## Setup

1. Clone the repository
2. Install dependencies:
```bash
composer install
```
3. Copy `.env.example` to `.env`:
```bash
cp .env.example .env
```
4. Generate application key:
```bash
php artisan key:generate
```
5. Set up the database:
```bash
php artisan migrate
```
6. Set up API keys:

### Google Gemini API Key
This application uses Google's Gemini AI for the tutoring functionality.

To obtain a Gemini API key:
1. Go to the [Google AI Studio](https://aistudio.google.com/)
2. Sign in with your Google account
3. Click on "Get API key" in the top-right corner
4. Create a new API key
5. Copy the API key and add it to your `.env` file:
```
GEMINI_API_KEY=your_api_key_here
```

### Together AI API Key
This application also uses Together AI for comparative analysis.

To obtain a Together AI API key:
1. Go to the [Together AI Platform](https://together.ai/)
2. Sign up or sign in to your account
3. Navigate to the API Keys section
4. Create a new API key
5. Copy the API key and add it to your `.env` file:
```
TOGETHER_API_KEY=your_api_key_here
```

**Note**: Both API keys are required for the split-screen comparative analysis feature to work properly. If either key is missing, the corresponding AI model will show a configuration error message.

## Running the application

Start the development server:
```bash
php artisan serve
```

By default, the API will be available at http://localhost:8000.

## API Routes

### Authentication
- `POST /api/register` - Register a new user
- `POST /api/login` - Log in a user
- `POST /api/logout` - Log out a user (requires authentication)
- `GET /api/user` - Get current user (requires authentication)

### AI Tutoring
- `POST /api/tutor/chat` - Get AI tutor response (requires authentication)
- `POST /api/tutor/split-screen-chat` - Get responses from both AI models (requires authentication)
- `POST /api/tutor/execute-code` - Execute Java code (requires authentication)
- `POST /api/tutor/execute-project` - Execute Java project (requires authentication)

## Environment Variables

Important environment variables:
- `GEMINI_API_KEY` - Google Gemini API key for AI tutoring
- `TOGETHER_API_KEY` - Together AI API key for comparative analysis

## Database Seeders

The application includes several seeders to populate the database with sample data:

### Topic Seeders
There are two options for seeding topics:

1. **TopicSeeder** - Creates basic top-level topics without a detailed hierarchy
2. **TopicHierarchySeeder** - Creates a complete topic hierarchy with parent-child relationships

### Lesson Plan Seeders
- **LessonPlanSeeder** - Creates lesson plans, modules, and exercises for topics

### Reset Seeders
- **ResetLessonPlansSeeder** - Resets all lesson plan data to fix duplication issues

## Running Seeders Properly

To avoid duplication issues, follow these steps:

### Fresh Installation
```bash
# Run full database migration
php artisan migrate:fresh

# Run basic seeders with simple topics
php artisan db:seed
```

### Reset Lesson Plans Only
If you need to reset lesson plans without affecting other data:
```bash
# Just reset lesson plans
php artisan db:seed --class=ResetLessonPlansSeeder

# Then re-seed lesson plans
php artisan db:seed --class=LessonPlanSeeder
```

### Change Topic Structure
To switch from simple topics to the full hierarchy:
```bash
# Reset the database
php artisan migrate:fresh

# Update DatabaseSeeder.php to use TopicHierarchySeeder instead of TopicSeeder

# Then run the seeders
php artisan db:seed
```

## Important Notes

- Only use one topic seeder at a time to avoid duplication
- The LessonPlanSeeder now checks for existing lesson plans to prevent duplicates
- If you see duplicate lesson plans, use the ResetLessonPlansSeeder to clean up

## Tutor Impact Comparative Algorithm (TICA)

We compare `gemini` vs `together` on tutoring impact using within-user comparisons over a recent time window.

Parameters:

- Window: `window ∈ {7d, 30d, 90d}`
- Baseline runs: `K ∈ {1,3,5}`
- Lookahead: `L` minutes (15/30/60)
- Minimum sample size: `Nmin` (default 5)

Per assistant reply at time `t` we compute, looking ahead up to `min(t+L, next_reply)`:

- Next‑run success: `success1 ∈ {0,1}` for the first practice run after `t`
- Time‑to‑first‑success: `ttf_min` minutes
- Error reduction: `Δerrors = errors_prior(K) − errors_post(K)`
- Quiz gain: `Δquiz = avg_quiz(after 1d) − avg_quiz(before 7d)`
- Rating, fallback, latency: from the chat message metadata

Aggregation:

- Per user/model, average the above; keep `n` = number of replies used
- Paired deltas per user: `Δ = gemini − together`

Suppression:

- If `n < Nmin`, hide per‑model metrics for that user/model
- For paired stats, report mean/SE only when paired sample `n ≥ Nmin`

API: `GET /api/analytics/models/compare?window=30d&k_runs=3&lookahead_min=30&topic_id=&difficulty=&nmin=5`