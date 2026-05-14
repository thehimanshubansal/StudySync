# StudySync: Smart Study Planner 📚

[![Production Link](https://img.shields.io/badge/MVP-STudySync-green)](https://studysync.infinityfreeapp.com/)

**StudySync** is a full-stack, intelligent academic management system designed to eliminate student burnout. It moves beyond traditional to-do lists by using a **Virtual Schedule Engine** to automatically distribute tasks based on study capacity and leveraging **Google Gemini AI** to provide personalized, prioritized study guidance.

---

### 🌟 Key Features

*   **Intelligent Planning:** AI-powered task prioritization using Google Gemini.
*   **Virtual Schedule Engine:** Automatically distributes workload across a 24-hour timeline.
*   **Progress Visualization:** Weekly/Monthly views, streak counters, and XP-based gamification.
*   **Integrated Productivity:** Built-in "My Notes" and task management system.
*   **Secure Architecture:** Bcrypt password hashing, session management, and prepared SQL statements to prevent injection.
*   **Modern UI:** Responsive design using Tailwind CSS with dark mode support.

---

### 🏗️ Tech Stack

| Layer | Technology |
| :--- | :--- |
| **Frontend** | HTML5, Tailwind CSS, JavaScript (ES6+), Fetch API |
| **Backend** | PHP 8 (REST-style API) |
| **Database** | MySQL (via XAMPP/phpMyAdmin) |
| **AI Integration** | Google Gemini API (REST) |
| **Security** | bcrypt, Prepared Statements, CORS, XSS protection |

---

### 📂 Project Architecture

StudySync follows a **Three-Tier Architecture**:
1.  **Presentation Layer:** HTML5/Tailwind for structure and visuals.
2.  **Application Logic Layer:** PHP 8 acts as a REST API server, handling authentication and business logic.
3.  **Data Layer:** MySQL manages relational data across 4 normalized tables (`users`, `tasks`, `notes`, `daily_activity`).

---

### ⚙️ Local Installation & Setup

1.  **Prerequisites:** 
    *   Install [XAMPP](https://www.apachefriends.org/index.html).
2.  **Clone the Repository:**
    ```bash
    git clone https://github.com/your-username/StudySync.git
    ```
3.  **Setup Database:**
    *   Copy the `StudySync` folder into `C:\xampp\htdocs\`.
    *   Open `http://localhost/phpmyadmin`.
    *   Create a new database, go to the **SQL** tab, and execute the contents of `php/schema.sql`.
4.  **Configure AI:**
    *   Get a free API Key from [Google AI Studio](https://aistudio.google.com/).
    *   Paste your API key into `php/ai.php`.
5.  **Launch:**
    *   Start Apache and MySQL from the XAMPP Control Panel.
    *   Navigate to `http://localhost/StudySync/index.html` in your browser.

---

### 🚀 Usage Scenarios

*   **New Student Registration:** Secure registration with bcrypt hashing.
*   **Daily Task Planning:** Drag-and-drop workflow with AI-recommended focus order.
*   **Weekly/Monthly Planning:** Visualize academic workload to prevent "cramming" and burnout.
*   **AI Study Assistant:** Ask "What should I do first?" and get an actionable plan based on your current pending tasks.

---

### 🛡️ Security Highlights
*   **SQL Injection Prevention:** Uses `mysqli` prepared statements with `bind_param` for all database interactions.
*   **Authentication Security:** `password_hash()` (BCRYPT) ensures credentials are never stored in plain text.
*   **Session Security:** PHP sessions coupled with `httponly` flags prevent unauthorized client-side access.

---

### 👨‍💻 Project Team (Group No. 16)
*   **Geetika**
*   **Himanshu Bansal**
*   **Amar Krishna**
*   **Daksh**

*Submitted to: Mr. Manjeet Pangtey, Assistant Professor, CSE Department, G.B. Pant DSEU Okhla-I.*

---

### 📄 License
This project is for educational purposes as part of the Web Engineering (BT-CS-ES603) course.StudySync - Smart Study Planner
