# Dental Clinic - Patient Engagement & Analytics Suite

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)![Laravel](https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)![Node.js](https://img.shields.io/badge/Node.js-339933?style=for-the-badge&logo=nodedotjs&logoColor=white)

An advanced operations and marketing suite built for a real-world dental clinic. This project provides a live analytics dashboard, automates proactive patient engagement (reminders, feedback), and includes an end-to-end marketing funnel to reactivate lapsed patients.

---

## Overview

This project solves three key business needs for a busy dental clinic:

1.  **Business Intelligence:** A public, multi-lingual (English & Arabic) dashboard displays live Key Performance Indicators (KPIs) and data visualizations, allowing stakeholders to monitor clinic performance at a glance.
2.  **Patient Service Automation:** Automates crucial communication like appointment reminders and feedback requests via WhatsApp, improving patient experience and operational efficiency while reducing no-shows.
3.  **Automated Marketing & Growth:** Implements a complete, automated marketing funnel to identify, contact, and track the reactivation of patients who have not visited in over a year, turning past customers into new revenue.

The system is designed with a professional, decoupled architecture using the Gateway Pattern and a robust queue system to ensure maintainability, testability, and scalability.

## Key Features

### 1. Live Analytics Dashboard
-   **Public Facing:** Accessible via a URL for stakeholders (`/clinic-dashboard`).
-   **Multi-Lingual:** Full support for English and Arabic with RTL layout.
-   **Critical KPIs:** Live stats for Today's Revenue, Appointments Today, Avg. Revenue per Patient, and New Patients This Month.
-   **Data Visualization:** Dynamic charts for Monthly Revenue, Top Doctors by Revenue, and New vs. Returning Patient Mix.
-   **Doctor-Specific Metrics:** Ability to filter and view Avg. Revenue per Patient for individual doctors.

### 2. Automated Patient Communication
-   **Appointment Reminders:** A scheduled job (`reminders:send`) runs every 15 minutes to find and notify patients of upcoming appointments via WhatsApp, using different templates for confirmed vs. unconfirmed status.
-   **Feedback Requests:** An hourly job (`feedback:send`) identifies recently completed appointments and sends a WhatsApp message with a link to the clinic's review page, driving online reputation.
-   **Smart Cooldowns:** Both reminders and feedback systems have built-in logic to prevent over-messaging repeat patients.

### 3. Lapsed Patient Reactivation Campaign (Marketing Funnel)
An end-to-end automated system to win back former patients:
-   **Step 1: Select (`marketing:select-daily-batch`):** A daily command identifies patients who haven't visited in over a year and are not on an exclusion list. It stages them for contact.
-   **Step 2: Queue (`marketing:queue-staged-messages`):** A second command takes the daily batch and schedules personalized WhatsApp messages with a staggered, randomized delay to appear human and avoid spam filters.
-   **Step 3: Track (`marketing:track-conversions`):** A third command runs daily to check if any of the contacted patients have booked a new appointment, automatically marking them as "converted" for ROI tracking.

### 4. Marketing & Admin Dashboard
A secure dashboard for administrative tasks:
-   **Manual Broadcast Tool:** Allows an admin to send a custom WhatsApp message to a list of numbers with safe, staggered delays.
-   **Marketing Exclusion Tool:** Provides an interface to permanently add a patient to a "Do Not Contact" list by their mobile number.
-   **Performance Reporting:** A dedicated report page (`/marketing-report`) shows the performance of the lapsed patient campaign, including total messages sent, conversions, and conversion rate, with a searchable list of returned patients.

## Tech Stack

*   **Backend:** PHP 8.2, Laravel 12
*   **Frontend:** JavaScript, Tailwind CSS, Alpine.js, ApexCharts
*   **Database:** MySQL (for the app) and MS SQL Server (for the external clinic data, via the Gateway)
*   **Automation:**
    *   Laravel Scheduler & Queue
    *   Node.js for the WhatsApp API service
    *   `whatsapp-web.js` library for WhatsApp connectivity

## Scheduled & Manual Commands

This application relies heavily on Laravel's scheduler. Here are the key commands:

#### Reminders & Feedback
-   `reminders:send`: Finds and queues appointment reminders. Runs every 15 minutes.
-   `feedback:send`: Finds and queues feedback requests. Runs hourly.

#### Marketing Campaign
-   `marketing:select-daily-batch`: Selects daily lapsed patients. Runs once a day (e.g., 5 AM).
-   `marketing:queue-staged-messages`: Queues the daily batch with delays. Runs once a day (e.g., 10 AM).
-   `marketing:track-conversions`: Checks for returning patients. Runs once a day (e.g., 11 PM).

#### Manual/Admin Tasks
-   `marketing:send-manual {mobile} {message}`: Sends a single, immediate WhatsApp message from the command line for testing or one-off tasks.

## Getting Started

### Prerequisites

*   PHP >= 8.2 & Composer
*   Node.js & npm
*   XAMPP (or other stack with MySQL)
*   Microsoft SQL Server
*   PHP drivers for SQL Server (`sqlsrv`, `pdo_sqlsrv`)

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
    *   Copy `.env.example` to `.env`.
    *   Generate an application key: `php artisan key:generate`.
    *   Configure **both** database connections in your `.env` file: the default `DB_*` variables for MySQL and the `CLINIC_DB_*` variables for your MSSQL server.
    *   Set the `WHATSAPP_API_PORT`.

4.  **Database Migration & Seeding:**
    Run migrations for the application's MySQL database.
    ```bash
    php artisan migrate
    php artisan db:seed
    ```

5.  **Build Frontend Assets:**
    ```bash
    npm run build
    ```

### Running the Full System

You need three terminal windows running simultaneously.

1.  **Start the Laravel Web Server:**
    ```bash
    php artisan serve
    ```

2.  **Start the Laravel Queue Worker:** (Essential for all automated messages)
    ```bash
    php artisan queue:work
    ```

3.  **Start the WhatsApp API Service:**
    ```bash
    node whatsapp-api.cjs
    ```
    *On the first run, scan the QR code in the terminal with WhatsApp to log in.*

You can now access the admin panel at `http://localhost:8000` (or your configured URL) and the public dashboard at `/clinic-dashboard`.

### Running Scheduled Tasks Manually

To test the automation without waiting for the scheduler, you can run the commands manually:
```bash
# To check for and send appointment reminders
php artisan reminders:send

# To check for and send feedback requests
php artisan feedback:send