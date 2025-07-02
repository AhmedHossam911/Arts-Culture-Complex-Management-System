# Theater Hall Management System

A comprehensive web application for managing theater hall reservations at Helwan University's Faculty of Arts and Culture Complex.

## Features

- 👥 User authentication (login/signup with admin approval)
- 📊 Admin dashboard with analytics
- 🎭 Theater reservation system with status tracking
- 👤 User management (admin can add users)
- 📅 Reservation management (edit/cancel within 2 days)
- 🔄 Automatic status updates (reserved -> confirmed)
- ✉️ Email notifications
- 🌓 Dark/light mode
- 📱 Responsive design

## Tech Stack

- **Frontend:** HTML5, CSS3, JavaScript, Bootstrap 5
- **Backend:** PHP
- **Database:** MySQL
- **Libraries:**
  - Chart.js/ApexCharts for analytics
  - SweetAlert2 for beautiful alerts
  - DataTables for interactive tables

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Composer (for PHP dependencies)

## Installation

1. Clone the repository:
   ```bash
   git clone [your-repository-url]
   cd "Arts and culture management system"
   ```

2. Import the database:
   - Create a new MySQL database
   - Import the SQL file from `database/schema.sql`

3. Configure the database connection:
   - Copy `.env.example` to `.env`
   - Update the database credentials in `.env`

4. Set up the web server:
   - Point your web server to the project root directory
   - Make sure the `logs` directory is writable by the web server

5. Access the application in your browser:
   - Admin: `http://localhost/admin`
   - User: `http://localhost`

## Default Credentials

- **Admin Panel:**
  - Username: admin
  - Password: admin123 (please change after first login)

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Acknowledgments

- Helwan University - Faculty of Arts and Culture Complex
- All contributors who helped in developing this system
