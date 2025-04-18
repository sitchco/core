# Coding Conventions

This document outlines the coding conventions and guidelines for this project. Adhering to these conventions ensures code consistency, readability, and maintainability.

## General Principles

- **DRY (Don't Repeat Yourself):**  Avoid redundant code. Extract common logic into reusable functions or classes.
- **SOLID Principles:** Aim for SOLID principles in code design, particularly:
    - **Single Responsibility Principle (SRP):** Classes and functions should have one specific job.
    - **Open/Closed Principle (OCP):**  Code should be open for extension but closed for modification.
    - **Liskov Substitution Principle (LSP):** Subtypes must be substitutable for their base types.
    - **Interface Segregation Principle (ISP):**  Interfaces should be specific to clients, avoiding large, monolithic interfaces.
    - **Dependency Inversion Principle (DIP):** Depend on abstractions, not concretions. Utilize Dependency Injection.
- **KISS (Keep It Simple, Stupid):** Favor simplicity over complexity. Write straightforward code that is easy to understand.
- **YAGNI (You Aren't Gonna Need It):**  Don't add functionality until it is actually needed. Avoid premature optimization and feature creep.
- **Domain-Driven Design (DDD):** Structure code around the business domain. Organize code into modules and namespaces that reflect domain concepts.
- **Functional Programming Preference:**  Where appropriate, prefer functional programming paradigms over procedural approaches. Utilize pure functions, immutability, and higher-order functions to enhance code clarity and reduce side effects.

## Language and Style

- **PHP Version:** Use the latest stable PHP version, compatible with PHP8.2.
- **PSR Standards:**  Follow PSR-12 for code style and PSR-4 for autoloading.
- **Namespaces:** Use namespaces to organize code and prevent naming conflicts. Namespace structure should reflect the project's domain and component structure (e.g., `Sitchco\ModuleName`).
- **Class Naming:** PascalCase for class names (e.g., `BackgroundEventManager`, `PostRepository`).
- **Method and Function Naming:** camelCase for method and function names (e.g., `processRoute`, `getArgumentName`). Use verb-noun or verb phrases for methods that perform actions, and noun phrases for getters.
- **Variable Naming:** camelCase for variable names (e.g., `$callback`, `$container`, `$queryVars`). Be descriptive and concise.
- **Constant Naming:** UPPER_SNAKE_CASE for constants (e.g., `FEATURES`, `HOOK_PREFIX`).
- **File Naming:** PascalCase file names with `.php` extension, matching the primary class name in the file (e.g., `Route.php`).
- **Directory Naming:** lowercase for directory names (e.g., `src`, `integration`, `utils`).
- **Function Style:**
    - Use named functions for class methods for better readability and debugging.
    - Use anonymous functions (closures) for callbacks, especially with WordPress hooks, when the function is short and context-specific.
    - Use arrow functions (`fn() => ...`) for concise, short, and simple one-line closures, especially in functional contexts.

## Libraries and Tools

- **Dependency Injection (PHP-DI):** Use PHP-DI for managing dependencies and promoting loose coupling. Define dependencies in `config/container.php`.
- **Templating (Timber):** Use Timber for cleaner separation of PHP logic and HTML in templates.
- **Asynchronous Tasks (WP-Async-Request & WP-Background-Process):**  Employ `WP_Async_Request` for simple asynchronous requests and `WP_Background_Process` for long-running background tasks.
- **Advanced Custom Fields (ACF):**  Use ACF for managing custom fields and content structures. Leverage ACF JSON for version control and deployment of field groups.
- **Testing (WPTest):** Write unit and integration tests using WPTest framework to ensure code quality and prevent regressions.

## Code Structure

- **Modularity:** Organize code into modules. Each module should encapsulate a specific feature or domain area. Create a dedicated directory for each module under `src/`.
- **Configuration:** Use php configuration files to manage module settings, feature flags, and dependency definitions. Store configuration files in the `config/` directory.
- **Utility Classes:** Create utility classes for reusable functions and helpers in the `src/Utils/` directory.
- **Models:** Define data models as classes in the `src/Model/` directory to represent domain entities.
- **Repositories:** Implement repositories in the `src/Repository/` directory to abstract data access logic and interact with data sources (e.g., WordPress database).
- **Events and Queues:** Use `src/Events/` for event-driven architecture and background task management.
- **Tests:** Write tests in the `tests/` directory, mirroring the `src/` structure to test modules and components.
- **Templates:** Store template files in the `templates/` directory.

By following these conventions, we aim to create a robust, maintainable, and scalable WordPress platform.