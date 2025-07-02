# Contributing to Theater Hall Management System

Thank you for considering contributing to the Theater Hall Management System! We appreciate your time and effort in helping us improve this project.

## How to Contribute

1. **Fork the repository**
   - Click the 'Fork' button on the repository page
   - Clone your forked repository to your local machine
   ```bash
   git clone https://github.com/your-username/Arts-and-culture-management-system.git
   cd Arts-and-culture-management-system
   ```

2. **Set up the development environment**
   - Install required dependencies:
   ```bash
   composer install
   ```
   - Copy `.env.example` to `.env` and configure your environment variables
   - Import the database schema from `database/schema.sql`

3. **Create a new branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

4. **Make your changes**
   - Follow the existing code style
   - Write clear, concise commit messages
   - Add tests if applicable

5. **Test your changes**
   - Run any existing tests
   - Test your changes in different browsers
   - Ensure no existing functionality is broken

6. **Submit a Pull Request**
   - Push your changes to your fork
   - Open a Pull Request to the `main` branch
   - Describe your changes and reference any related issues

## Code Style

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standard
- Use meaningful variable and function names
- Add comments for complex logic
- Keep lines under 120 characters

## Reporting Issues

When reporting issues, please include:
- A clear description of the problem
- Steps to reproduce the issue
- Expected vs actual behavior
- Screenshots if applicable
- Browser/OS version if relevant

## Feature Requests

We welcome feature requests! Please:
1. Check if a similar feature already exists
2. Explain why this feature would be valuable
3. Provide as much detail as possible

## Code Review Process

1. A maintainer will review your PR
2. You may be asked to make changes
3. Once approved, your PR will be merged

## License

By contributing, you agree that your contributions will be licensed under the [MIT License](LICENSE).
