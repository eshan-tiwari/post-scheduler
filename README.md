# ✦ PostScheduler: Premium Social Media Scheduler

### 🎓 Developers Gallery Internship Capstone Project
A production-grade, premium SaaS application designed for content creators, agencies, and social media managers to orchestrate, schedule, and automate publishing across major platforms simultaneously.

This project was built as a capstone internship task for **Developers Gallery**, utilizing a decoupled full-stack architecture with a **Laravel API Backend** and an **Angular Frontend**.

---

## 🌐 Live Production Deployments
* **Frontend Web App (Vercel)**: [https://developersgallery-scheduler.vercel.app](https://developersgallery-scheduler.vercel.app)
* **Backend REST API (Vercel)**: [https://laravel-backend-zeta.vercel.app](https://laravel-backend-zeta.vercel.app)

*Note: The production frontend dynamically routes API calls to the serverless Vercel backend by default. Local tunnels or offline servers can be configured instantly using the dynamic **⚙️ Backend Tunnel Settings** gear inside the footer of the Login/Register screens.*

---

## 🎨 Design Philosophy: Luxury Dark Mode
* **Harmony**: Dark mahogany background (`#16110D`) paired with polished champagne gold highlights (`#D4A44D`) and glassmorphic card overlays.
* **Typographic Elegance**: Modern sans-serif headers with clean font hierarchies, designed to feel clean, minimal, and premium.
* **Micro-interactions**: Subtle hover state transitions, animated buttons, and responsive loading indicators.

---

## ✨ Features

- 📊 **Unified Dashboard**: Live metrics summary (Total platforms, connected accounts, pending posts, failed queues) in real-time.
- 🕒 **Scheduled Posting**: Multi-platform publishing engine supporting X (Twitter), Bluesky, LinkedIn, Facebook Pages, and Instagram Business.
- ⚡ **Credential Connectors**: Secure AES-256 encrypted credential management for API keys and OAuth2 profiles. 
- 🦋 **Bluesky Native Support**: Fully integrated credentials setup for handles (`.bsky.social`) & App Passwords.
- 🔄 **Live Status Tracker**: Auto-polling engine updates scheduled post statuses (Pending ➜ Published/Failed) in the background without UI flicker.
- 📁 **Queue & Schedule Logs**: Complete publishing history log with error feedback to diagnose failed requests instantly.

---

## 🛠️ Tech Stack

### Frontend
* **Angular 18** (Standalone Components, Zoneless Mode)
* **HTML5 & CSS3** (Vanilla CSS variables design tokens system)
* **RxJS** (State management and silent polling)

### Backend
* **Laravel 11** (Robust REST API architecture)
* **Eloquent ORM** (Secure relation management)
* **Laravel Queues & Scheduler** (Asynchronous jobs for auto-publishing)
* **SQLite** (Embedded light database storage)

---

## 🚀 Getting Started

### Prerequisites
* PHP 8.2+ & Composer
* Node.js 20+ & npm

---

### 1. Backend Setup (Laravel)

Navigate to the backend folder:
```bash
cd laravel-backend
```

Install PHP dependencies:
```bash
composer install
```

Configure Environment:
```bash
cp .env.example .env
php artisan key:generate
```
*Configure your database settings inside `.env`.*

Run database migrations and seeders:
```bash
php artisan migrate --seed
```

Start the local API server:
```bash
php artisan serve
```

Start the background workers (crucial for auto-publishing schedules):
```bash
# Start the queue listener
php artisan queue:work

# Start the cron scheduler
php artisan schedule:work
```

---

### 2. Frontend Setup (Angular)

Navigate to the frontend folder:
```bash
cd auth-app
```

Install npm dependencies:
```bash
npm install
```

Start the Angular server:
```bash
npm run start
```
*Go to `http://localhost:4200` to access the application.*

---

## 🔒 Security
All platform developer API keys, secret credentials, and access tokens are encrypted at rest using Laravel's **AES-256-CBC** cryptography before storage in the database to prevent credential leaks.
