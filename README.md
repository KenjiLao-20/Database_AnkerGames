🎮 AnkerGames - Game Library Management System
A web-based dashboard application built with PHP and MySQL to manage a game store/library. It features a modern UI, comprehensive CRUD operations, and interactive data analytics to track user playtime and game trends.

✨ Features
Interactive Dashboard: Visualizes playtime analytics using Chart.js (Stacked bar charts for 12-month trends, and mini sparkline charts for 6-month playtime).

Game Management: Add, edit, and delete games with cover art integration (Steam CDN), pricing, and storage requirements.

Publisher Management: Keep track of game publishers and their contact information.

User Management: Manage user accounts, roles (Admin/User), and wallet balances. Includes a guard against self-deletion.

Secure Authentication: User registration and login system using PHP password_hash() with bcrypt encryption.

Activity Logging: Tracks actions (create, update, delete) silently in the background for auditing.

Responsive UI: Styled with Bootstrap 5 and custom CSS for a sleek, Steam-inspired dark-sidebar design.

🛠️ Tech Stack
Backend: PHP 8.x (using PDO for secure database interactions)

Database: MySQL / MariaDB

Frontend: HTML5, CSS3, Bootstrap 5, Bootstrap Icons

Data Visualization: Chart.js

Typography: Google Fonts (Inter)
