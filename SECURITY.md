# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

If you discover a security vulnerability in this project, please submit it to our security team at [security@example.com](mailto:security@example.com). You will receive a response within 48 hours. If the issue is confirmed, we will release a patch as soon as possible depending on complexity but historically within a few days.

### When to report a vulnerability

- For **critical** issues, anything that compromises the security of user data or the system
- For **high** severity issues, including but not limited to:
  - SQL Injection
  - Cross-Site Scripting (XSS)
  - Cross-Site Request Forgery (CSRF)
  - Authentication/Authorization bypass
  - Data exposure
  - Remote code execution

## Security Best Practices

### For Users
- Always use strong, unique passwords
- Keep your server and PHP version up to date
- Regularly backup your database
- Never share your admin credentials
- Use HTTPS in production

### For Developers
- Always validate and sanitize user input
- Use prepared statements for database queries
- Implement proper authentication and authorization checks
- Keep dependencies up to date
- Follow the principle of least privilege
- Implement proper error handling and logging

## Security Updates

Security updates will be released as patch versions (e.g., 1.0.1, 1.0.2) and should be applied as soon as possible. We recommend subscribing to GitHub's watch feature to be notified of new releases.

## Security Audit

This project undergoes regular security audits. If you would like to conduct a security audit, please contact us first at [security@example.com](mailto:security@example.com).
