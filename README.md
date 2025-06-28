# Dental Clinic - Operations & Analytics Suite

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)![Laravel](https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)![Node.js](https://img.shields.io/badge/Node.js-339933?style=for-the-badge&logo=nodedotjs&logoColor=white)

A comprehensive operations suite built for a real-world dental clinic. This project provides a live, public-facing analytics dashboard and includes a robust backend system for automated patient communication via WhatsApp.

---



## Overview

This project solves two key business needs for a busy dental clinic:

1.  **Business Intelligence:** It provides a public, multi-lingual (English & Arabic) dashboard displaying live Key Performance Indicators (KPIs) and data visualizations. This allows stakeholders to monitor the clinic's performance at a glance.
2.  **Patient Engagement Automation:** It automates crucial patient communication, sending appointment reminders and feedback requests via WhatsApp to improve patient experience and operational efficiency.

The system is designed with a professional, decoupled architecture to ensure maintainability, testability, and scalability.

## Key Features

*   **Live Analytics Dashboard:**
    *   Displays critical KPIs like daily revenue, appointment volume, and patient retention rates.
    *   Visualizes data with dynamic charts for monthly revenue and performance per doctor.
    *   Full multi-language support for both English and Arabic, including right-to-left (RTL) layout adjustments.

*   **Automated Appointment Reminders:**
    *   A scheduled background command (`reminders:send`) runs every 15 minutes.
    *   Intelligently finds upcoming appointments within a configurable time window.
    *   Dispatches queued jobs to send personalized reminders via WhatsApp, using different templates for confirmed vs. unconfirmed appointments.
    *   Prevents duplicate reminders by logging every message sent.

*   **Automated Feedback System:**
    *   A scheduled command (`feedback:send`) runs hourly to identify recently completed appointments.
    *   Queues jobs to send a WhatsApp message with a direct link to the clinic's Google Reviews page, helping to drive online engagement.
    *   Includes a configurable "cooldown" period to avoid over-messaging repeat patients.

*   **Decoupled & Professional Architecture:**
    *   **Gateway Pattern:** The `ClinicPatientGateway` class completely isolates the main application from the external clinic database. This is a crucial design choice that makes the application more modular, easier to maintain, and highly testable.
    *   **Robust Background Processing:** Leverages Laravel's built-in Scheduler and Queue system to handle all automated tasks reliably without blocking the user interface.

## System Architecture

The application is designed with a clear separation of concerns.

1.  **Web Interface (Dashboard):**
    `User Request` -> `Route` -> `PublicDashboardController` -> `ClinicPatientGateway` -> `External DB`

2.  **Automation Systems (Reminders/Feedback):**
    `Laravel Scheduler` -> `Artisan Command` -> `Dispatches Job` -> `Queue Worker` -> `WhatsappService` -> `WhatsApp API`

## Tech Stack

*   **Backend:** PHP 8.2, Laravel 12
*   **Frontend:** JavaScript, Tailwind CSS, Alpine.js, ApexCharts
*   **Database:** MySQL (for the app) and MS SQL Server (for the external clinic data, via the Gateway)
*   **Automation:**
    *   Laravel Scheduler & Queue
    *   Node.js for the WhatsApp API service
    *   `whatsapp-web.js` library for WhatsApp connectivity

## Getting Started

### Prerequisites

*   PHP >= 8.2
*   Composer
*   Node.js & npm
*   Access to a MySQL database
*   Access to the clinic's MS SQL database

### Installation & Setup

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/WaaelAkram/referral-tracking-system.git
    cd referral-tracking-system
    ```

2.  **Install dependencies:**
    ```bash
    composer install
    npm install
    ```

3.  **Environment Configuration:**
    *   Copy the example environment file:
        ```bash
        cp .env.example .env
        ```
    *   Generate a new application key:
        ```bash
        php artisan key:generate
        ```
    *   Open the `.env` file and configure your database connections, especially `DB_CONNECTION` (for Laravel) and `DB_MSSQL_...` (for the clinic data). Also set the `WHATSAPP_API_PORT`.

4.  **Database Migration:**
    Run the migrations to create the necessary tables for the application.
    ```bash
    php artisan migrate
    ```

5.  **Create Admin User:**
    Seed the database to create the default admin user (`username: admin`, `password: password`).
    ```bash
    php artisan db:seed
    ```

6.  **Build Frontend Assets:**
    ```bash
    npm run build
    ```

### Running the Application

To run the full system, you will need to start three separate processes in three different terminal windows.

1.  **Start the Laravel Web Server:**
    ```bash
    php artisan serve
    ```

2.  **Start the Laravel Queue Worker:**
    This process is essential for sending reminders and feedback.
    ```bash
    php artisan queue:work
    ```

3.  **Start the WhatsApp API Service:**
    ```bash
    node whatsapp-api.cjs
    ```
    *On the first run, you will need to scan the QR code that appears in the terminal with your phone to log in.*

You can now access the admin panel at `http://localhost:8000/login` and the public dashboard at `http://localhost:8000/clinic-dashboard`.

### Running Scheduled Tasks Manually

To test the automation without waiting for the scheduler, you can run the commands manually:
```bash
# To check for and send appointment reminders
php artisan reminders:send

# To check for and send feedback requests
php artisan feedback:send