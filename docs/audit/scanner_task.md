# Laravel + Next.js Integrity Scanner Tasks

- [x] Analyze Project Structure
    - [x] Backend: Models, Controllers, Routes
    - [x] Frontend: API Hooks, Axios configurations
- [x] Create Scanner Script (`scripts/laravel-integrity.mjs`)
    - [x] Layer 1: Parse Laravel Models (Eloqent relationships, fillables)
    - [x] Layer 2: Parse Routes (`api.php`) & Controllers
    - [x] Layer 3: Parse Next.js Client (Axios usage, URL strings)
    - [x] Layer 4: Cross-reference (Broken Links, Unused Endpoints)
- [x] Run Scanner
- [x] Report Findings
